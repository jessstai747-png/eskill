#!/usr/bin/env php
<?php
/**
 * Clone A/B Testing Worker
 * 
 * Sincroniza métricas de testes A/B ativos e verifica vencedores
 * 
 * Usage:
 *   php bin/clone-ab-testing-worker.php [options]
 * 
 * Options:
 *   --once           Run once and exit
 *   --test=ID        Process specific test only
 *   --check-winners  Only check for winners
 *   --sync-metrics   Only sync metrics
 *   --dry-run        Show what would be done
 *   --verbose        Verbose output
 */

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Database;
use App\Services\CloneABTestingService;
use App\Services\MercadoLivreClient;

// CLI options
$options = getopt('', [
    'once',
    'test:',
    'check-winners',
    'sync-metrics',
    'dry-run',
    'verbose',
    'help'
]);

if (isset($options['help'])) {
    echo <<<HELP
Clone A/B Testing Worker

Sincroniza métricas de testes A/B ativos e verifica vencedores automaticamente.

Usage:
  php bin/clone-ab-testing-worker.php [options]

Options:
  --once            Executa uma vez e sai
  --test=ID         Processa apenas teste específico
  --check-winners   Apenas verifica vencedores
  --sync-metrics    Apenas sincroniza métricas
  --dry-run         Mostra o que seria feito sem executar
  --verbose         Saída detalhada
  --help            Mostra esta ajuda

Exemplos:
  php bin/clone-ab-testing-worker.php --once
  php bin/clone-ab-testing-worker.php --test=5 --sync-metrics
  php bin/clone-ab-testing-worker.php --check-winners --verbose

HELP;
    exit(0);
}

$runOnce = isset($options['once']);
$specificTest = $options['test'] ?? null;
$checkWinnersOnly = isset($options['check-winners']);
$syncMetricsOnly = isset($options['sync-metrics']);
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
    
    // Log to file
    $logFile = __DIR__ . '/../storage/logs/clone-ab-testing-' . date('Y-m-d') . '.log';
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
    global $runOnce, $specificTest, $checkWinnersOnly, $syncMetricsOnly, $dryRun;
    
    logMessage("Clone A/B Testing Worker iniciado");
    
    if ($dryRun) {
        logMessage("Modo DRY-RUN ativo - nenhuma alteração será feita", 'WARN');
    }
    
    $iteration = 0;
    $sleepInterval = 300; // 5 minutos entre iterações
    
    do {
        $iteration++;
        logVerbose("Iteração #$iteration");
        
        try {
            $db = Database::getInstance();
            
            // Buscar testes ativos
            $query = "SELECT DISTINCT account_id FROM clone_ab_tests WHERE status = 'running'";
            if ($specificTest) {
                $query .= " AND id = :test_id";
            }
            
            $stmt = $db->prepare($query);
            if ($specificTest) {
                $stmt->execute(['test_id' => $specificTest]);
            } else {
                $stmt->execute();
            }
            
            $accounts = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            if (empty($accounts)) {
                logVerbose("Nenhum teste A/B ativo encontrado");
            } else {
                logMessage("Processando " . count($accounts) . " conta(s) com testes ativos");
                
                foreach ($accounts as $accountId) {
                    processAccount((int) $accountId);
                }
            }
            
            // Verificar testes que atingiram duração mínima
            if (!$syncMetricsOnly) {
                checkTestsForCompletion($db);
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
    global $specificTest, $checkWinnersOnly, $syncMetricsOnly, $dryRun;
    
    logVerbose("Processando conta #$accountId");
    
    try {
        $service = new CloneABTestingService($accountId);
        $db = Database::getInstance();
        
        // Buscar testes ativos desta conta
        $query = "SELECT * FROM clone_ab_tests WHERE account_id = :account_id AND status = 'running'";
        if ($specificTest) {
            $query .= " AND id = :test_id";
        }
        
        $stmt = $db->prepare($query);
        $params = ['account_id' => $accountId];
        if ($specificTest) {
            $params['test_id'] = $specificTest;
        }
        $stmt->execute($params);
        
        $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($tests as $test) {
            processTest($service, $test, $accountId);
        }
        
    } catch (\Exception $e) {
        logMessage("Erro ao processar conta #$accountId: " . $e->getMessage(), 'ERROR');
    }
}

function processTest(CloneABTestingService $service, array $test, int $accountId): void
{
    global $checkWinnersOnly, $syncMetricsOnly, $dryRun;
    
    $testId = (int) $test['id'];
    $testName = $test['name'];
    
    logMessage("Processando teste #$testId: $testName");
    
    try {
        // Sincronizar métricas
        if (!$checkWinnersOnly) {
            logVerbose("Sincronizando métricas do teste #$testId");
            
            if (!$dryRun) {
                $syncResult = syncTestMetrics($service, $testId, $accountId);
                
                if ($syncResult['success']) {
                    logMessage("Métricas sincronizadas: {$syncResult['synced']} itens atualizados");
                } else {
                    logMessage("Falha ao sincronizar métricas: " . ($syncResult['error'] ?? 'unknown'), 'ERROR');
                }
            } else {
                logMessage("[DRY-RUN] Sincronizaria métricas do teste #$testId");
            }
        }
        
        // Verificar vencedor
        if (!$syncMetricsOnly) {
            logVerbose("Verificando vencedor do teste #$testId");
            
            $winner = $service->determineWinner($testId);
            
            if ($winner && isset($winner['winner'])) {
                $confidence = round(($winner['confidence'] ?? 0) * 100, 1);
                $winnerName = $winner['winner']['name'] ?? 'Unknown';
                
                logMessage("Teste #$testId: Vencedor detectado - '$winnerName' (confiança: $confidence%)");
                
                // Auto-complete se atingiu threshold
                $threshold = (float) ($test['significance_threshold'] ?? 0.95);
                if (($winner['confidence'] ?? 0) >= $threshold) {
                    $minDays = (int) ($test['min_duration_days'] ?? 7);
                    $startedAt = strtotime($test['started_at'] ?? 'now');
                    $daysRunning = (time() - $startedAt) / 86400;
                    
                    if ($daysRunning >= $minDays) {
                        logMessage("Teste #$testId atingiu critérios de conclusão automática");
                        
                        if (!$dryRun) {
                            $completeResult = $service->completeTest($testId);
                            if ($completeResult['success']) {
                                logMessage("Teste #$testId concluído automaticamente!");
                            }
                        } else {
                            logMessage("[DRY-RUN] Concluiria teste #$testId automaticamente");
                        }
                    } else {
                        $remaining = ceil($minDays - $daysRunning);
                        logVerbose("Teste #$testId precisa de mais $remaining dia(s) para conclusão automática");
                    }
                }
            } else {
                logVerbose("Teste #$testId: Ainda sem vencedor definido");
            }
        }
        
    } catch (\Exception $e) {
        logMessage("Erro ao processar teste #$testId: " . $e->getMessage(), 'ERROR');
    }
}

function syncTestMetrics(CloneABTestingService $service, int $testId, int $accountId): array
{
    $db = Database::getInstance();
    
    // Buscar variações do teste
    $stmt = $db->prepare("
        SELECT v.id, v.name, e.clone_item_id
        FROM clone_ab_test_variations v
        JOIN clone_ab_test_entries e ON e.variation_id = v.id
        WHERE v.test_id = :test_id
    ");
    $stmt->execute(['test_id' => $testId]);
    $entries = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    if (empty($entries)) {
        return ['success' => true, 'synced' => 0, 'message' => 'No entries to sync'];
    }
    
    $synced = 0;
    $errors = 0;
    
    // Agrupar por item para buscar métricas
    $itemIds = array_unique(array_column($entries, 'clone_item_id'));
    
    try {
        $client = new MercadoLivreClient($accountId);
        
        foreach (array_chunk($itemIds, 20) as $chunk) {
            // Buscar métricas dos itens
            $ids = implode(',', $chunk);
            $response = $client->get("/items?ids=$ids&attributes=id,sold_quantity,available_quantity");
            
            if (!empty($response)) {
                foreach ($response as $itemData) {
                    if (!isset($itemData['body'])) continue;
                    
                    $item = $itemData['body'];
                    $itemId = $item['id'];
                    
                    // Buscar visitas
                    $visits = 0;
                    try {
                        $visitsData = $client->get("/items/$itemId/visits/time_window?last=30&unit=day");
                        if (isset($visitsData['total_visits'])) {
                            $visits = (int) $visitsData['total_visits'];
                        }
                    } catch (\Exception $e) {
                        // Ignore visit fetch errors
                    }
                    
                    // Atualizar entrada
                    $updateStmt = $db->prepare("
                        UPDATE clone_ab_test_entries 
                        SET 
                            visits = :visits,
                            sales = :sales,
                            revenue = COALESCE(revenue, 0),
                            updated_at = NOW()
                        WHERE clone_item_id = :item_id
                    ");
                    
                    $updateStmt->execute([
                        'visits' => $visits,
                        'sales' => (int) ($item['sold_quantity'] ?? 0),
                        'item_id' => $itemId
                    ]);
                    
                    $synced++;
                }
            }
            
            // Rate limit
            usleep(200000); // 200ms
        }
        
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage(), 'synced' => $synced];
    }
    
    // Recalcular métricas agregadas por variação
    $db->prepare("
        UPDATE clone_ab_test_variations v
        SET 
            visits = (SELECT COALESCE(SUM(visits), 0) FROM clone_ab_test_entries WHERE variation_id = v.id),
            sales = (SELECT COALESCE(SUM(sales), 0) FROM clone_ab_test_entries WHERE variation_id = v.id),
            revenue = (SELECT COALESCE(SUM(revenue), 0) FROM clone_ab_test_entries WHERE variation_id = v.id),
            updated_at = NOW()
        WHERE test_id = :test_id
    ")->execute(['test_id' => $testId]);
    
    return ['success' => true, 'synced' => $synced];
}

function checkTestsForCompletion(\PDO $db): void
{
    global $dryRun;
    
    logVerbose("Verificando testes pendentes de conclusão");
    
    // Buscar testes que atingiram duração máxima ou condições de auto-stop
    $stmt = $db->query("
        SELECT id, account_id, name, started_at, max_duration_days, auto_stop_on_significance
        FROM clone_ab_tests 
        WHERE status = 'running'
        AND started_at IS NOT NULL
        AND (
            (max_duration_days IS NOT NULL AND DATEDIFF(NOW(), started_at) >= max_duration_days)
            OR (auto_stop_on_significance = 1)
        )
    ");
    
    $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($tests as $test) {
        $testId = (int) $test['id'];
        $maxDays = $test['max_duration_days'];
        $daysRunning = (time() - strtotime($test['started_at'])) / 86400;
        
        // Verificar se atingiu duração máxima
        if ($maxDays && $daysRunning >= $maxDays) {
            logMessage("Teste #$testId '{$test['name']}' atingiu duração máxima de $maxDays dias");
            
            if (!$dryRun) {
                try {
                    $service = new CloneABTestingService((int) $test['account_id']);
                    $result = $service->completeTest($testId);
                    
                    if ($result['success']) {
                        logMessage("Teste #$testId concluído por tempo máximo");
                    }
                } catch (\Exception $e) {
                    logMessage("Erro ao concluir teste #$testId: " . $e->getMessage(), 'ERROR');
                }
            } else {
                logMessage("[DRY-RUN] Concluiria teste #$testId por tempo máximo");
            }
        }
    }
}

// Executar
runWorker();
