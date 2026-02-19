#!/usr/bin/env php
<?php
/**
 * Clone ROI Sync Worker
 * 
 * Sincroniza métricas de ROI para itens clonados
 * 
 * Usage:
 *   php bin/clone-roi-sync-worker.php [options]
 * 
 * Options:
 *   --once           Run once and exit
 *   --account=ID     Process specific account only
 *   --days=N         Sync items cloned in last N days (default: 30)
 *   --dry-run        Show what would be done
 *   --verbose        Verbose output
 */

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Database;
use App\Services\CloneROIAnalysisService;
use App\Services\MercadoLivreClient;

// CLI options
$options = getopt('', [
    'once',
    'account:',
    'days:',
    'dry-run',
    'verbose',
    'help'
]);

if (isset($options['help'])) {
    echo <<<HELP
Clone ROI Sync Worker

Sincroniza métricas de performance para análise de ROI dos clones.

Usage:
  php bin/clone-roi-sync-worker.php [options]

Options:
  --once            Executa uma vez e sai
  --account=ID      Processa apenas conta específica
  --days=N          Sincroniza itens clonados nos últimos N dias (default: 30)
  --dry-run         Mostra o que seria feito sem executar
  --verbose         Saída detalhada
  --help            Mostra esta ajuda

Exemplos:
  php bin/clone-roi-sync-worker.php --once
  php bin/clone-roi-sync-worker.php --account=123 --days=7
  php bin/clone-roi-sync-worker.php --verbose

HELP;
    exit(0);
}

$runOnce = isset($options['once']);
$specificAccount = $options['account'] ?? null;
$days = (int) ($options['days'] ?? 30);
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

// Logging
function logMessage(string $message, string $level = 'INFO'): void
{
    global $verbose;
    
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[$timestamp] [$level] $message\n";
    
    if ($level === 'ERROR' || $verbose || $level === 'INFO') {
        echo $formatted;
    }
    
    $logFile = __DIR__ . '/../storage/logs/clone-roi-sync-' . date('Y-m-d') . '.log';
    file_put_contents($logFile, $formatted, FILE_APPEND | LOCK_EX);
}

function logVerbose(string $message): void
{
    global $verbose;
    if ($verbose) {
        logMessage($message, 'DEBUG');
    }
}

// Main worker loop
function runWorker(): void
{
    global $runOnce, $specificAccount, $days, $dryRun;
    
    logMessage("Clone ROI Sync Worker iniciado");
    logMessage("Período de sincronização: últimos $days dias");
    
    if ($dryRun) {
        logMessage("Modo DRY-RUN ativo", 'WARN');
    }
    
    $iteration = 0;
    $sleepInterval = 3600; // 1 hora entre iterações
    
    do {
        $iteration++;
        logVerbose("Iteração #$iteration");
        
        try {
            $db = Database::getInstance();
            
            // Buscar contas com clones recentes
            $query = "
                SELECT DISTINCT target_account_id as account_id
                FROM cloned_items 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ";
            
            if ($specificAccount) {
                $query .= " AND target_account_id = :account_id";
            }
            
            $stmt = $db->prepare($query);
            $params = ['days' => $days];
            if ($specificAccount) {
                $params['account_id'] = $specificAccount;
            }
            $stmt->execute($params);
            
            $accounts = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            if (empty($accounts)) {
                logVerbose("Nenhuma conta com clones recentes encontrada");
            } else {
                logMessage("Processando " . count($accounts) . " conta(s)");
                
                foreach ($accounts as $accountId) {
                    processAccount((int) $accountId);
                }
            }
            
            // Calcular ROI consolidado
            if (!$dryRun) {
                calculateConsolidatedROI($db);
            }
            
        } catch (\Exception $e) {
            logMessage("Erro no worker: " . $e->getMessage(), 'ERROR');
        }
        
        if (!$runOnce) {
            logVerbose("Aguardando $sleepInterval segundos...");
            sleep($sleepInterval);
        }
        
    } while (!$runOnce);
    
    logMessage("Worker finalizado");
}

function processAccount(int $accountId): void
{
    global $days, $dryRun;
    
    logMessage("Processando conta #$accountId");
    
    try {
        $db = Database::getInstance();
        $client = new MercadoLivreClient($accountId);
        
        // Buscar clones recentes
        $stmt = $db->prepare("
            SELECT 
                id, source_item_id, target_item_id, created_at,
                source_snapshot
            FROM cloned_items 
            WHERE target_account_id = :account_id
            AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY created_at DESC
            LIMIT 500
        ");
        $stmt->execute(['account_id' => $accountId, 'days' => $days]);
        
        $clones = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        logMessage("Encontrados " . count($clones) . " clones para sincronizar");
        
        $synced = 0;
        $errors = 0;
        
        foreach (array_chunk($clones, 20) as $chunk) {
            $results = syncChunk($client, $chunk, $accountId);
            $synced += $results['synced'];
            $errors += $results['errors'];
            
            // Rate limit
            usleep(300000); // 300ms
        }
        
        logMessage("Conta #$accountId: $synced itens sincronizados, $errors erros");
        
    } catch (\Exception $e) {
        logMessage("Erro ao processar conta #$accountId: " . $e->getMessage(), 'ERROR');
    }
}

function syncChunk(MercadoLivreClient $client, array $clones, int $accountId): array
{
    global $dryRun;
    
    $db = Database::getInstance();
    $synced = 0;
    $errors = 0;
    
    // Buscar dados dos clones
    $cloneIds = array_column($clones, 'target_item_id');
    $cloneIdsStr = implode(',', $cloneIds);
    
    try {
        $response = $client->get("/items?ids=$cloneIdsStr&attributes=id,sold_quantity,available_quantity,price");
        
        if (!empty($response)) {
            foreach ($response as $itemData) {
                if (!isset($itemData['body'])) continue;
                
                $item = $itemData['body'];
                $itemId = $item['id'];
                
                // Encontrar clone correspondente
                $clone = null;
                foreach ($clones as $c) {
                    if ($c['target_item_id'] === $itemId) {
                        $clone = $c;
                        break;
                    }
                }
                
                if (!$clone) continue;
                
                // Buscar visitas do clone
                $cloneVisits = 0;
                try {
                    $visitsData = $client->get("/items/$itemId/visits/time_window?last=30&unit=day");
                    $cloneVisits = (int) ($visitsData['total_visits'] ?? 0);
                } catch (\Exception $e) {
                    // Ignore
                }
                
                $cloneSales = (int) ($item['sold_quantity'] ?? 0);
                $cloneRevenue = $cloneSales * (float) ($item['price'] ?? 0);
                
                // Buscar métricas do original (se acessível)
                $originalVisits = 0;
                $originalSales = 0;
                $originalRevenue = 0;
                
                $sourceSnapshot = json_decode($clone['source_snapshot'] ?? '{}', true);
                if (!empty($sourceSnapshot)) {
                    $originalSales = (int) ($sourceSnapshot['sold_quantity'] ?? 0);
                    $originalRevenue = $originalSales * (float) ($sourceSnapshot['price'] ?? 0);
                }
                
                // Calcular ROI
                $roi = 0;
                if ($originalRevenue > 0) {
                    $roi = (($cloneRevenue - $originalRevenue) / $originalRevenue) * 100;
                } elseif ($cloneRevenue > 0) {
                    $roi = 100; // 100% de ganho se original era 0
                }
                
                if (!$dryRun) {
                    // Atualizar ou inserir registro de ROI
                    $stmt = $db->prepare("
                        INSERT INTO clone_roi_analysis (
                            account_id, clone_id, source_item_id, clone_item_id,
                            original_visits, original_sales, original_revenue,
                            clone_visits, clone_sales, clone_revenue,
                            roi_percent, period_days, calculated_at
                        ) VALUES (
                            :account_id, :clone_id, :source_item_id, :clone_item_id,
                            :original_visits, :original_sales, :original_revenue,
                            :clone_visits, :clone_sales, :clone_revenue,
                            :roi_percent, 30, NOW()
                        )
                        ON DUPLICATE KEY UPDATE
                            clone_visits = VALUES(clone_visits),
                            clone_sales = VALUES(clone_sales),
                            clone_revenue = VALUES(clone_revenue),
                            roi_percent = VALUES(roi_percent),
                            calculated_at = NOW()
                    ");
                    
                    $stmt->execute([
                        'account_id' => $accountId,
                        'clone_id' => $clone['id'],
                        'source_item_id' => $clone['source_item_id'],
                        'clone_item_id' => $itemId,
                        'original_visits' => $originalVisits,
                        'original_sales' => $originalSales,
                        'original_revenue' => $originalRevenue,
                        'clone_visits' => $cloneVisits,
                        'clone_sales' => $cloneSales,
                        'clone_revenue' => $cloneRevenue,
                        'roi_percent' => $roi
                    ]);
                    
                    $synced++;
                } else {
                    logVerbose("[DRY-RUN] Sincronizaria ROI do item $itemId: ROI = " . round($roi, 1) . "%");
                    $synced++;
                }
            }
        }
    } catch (\Exception $e) {
        logMessage("Erro ao buscar dados: " . $e->getMessage(), 'ERROR');
        $errors++;
    }
    
    return ['synced' => $synced, 'errors' => $errors];
}

function calculateConsolidatedROI(\PDO $db): void
{
    logVerbose("Calculando ROI consolidado por conta");
    
    try {
        // Calcular métricas agregadas por conta
        $db->exec("
            INSERT INTO clone_analytics_aggregates (
                account_id, metric_type, metric_name, metric_value, period_start, period_end, created_at
            )
            SELECT 
                account_id,
                'roi' as metric_type,
                'avg_roi_percent' as metric_name,
                AVG(roi_percent) as metric_value,
                DATE_SUB(NOW(), INTERVAL 30 DAY) as period_start,
                NOW() as period_end,
                NOW() as created_at
            FROM clone_roi_analysis
            WHERE calculated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY account_id
            ON DUPLICATE KEY UPDATE
                metric_value = VALUES(metric_value),
                created_at = NOW()
        ");
        
        logVerbose("ROI consolidado atualizado");
        
    } catch (\Exception $e) {
        logMessage("Erro ao calcular ROI consolidado: " . $e->getMessage(), 'ERROR');
    }
}

// Executar
runWorker();
