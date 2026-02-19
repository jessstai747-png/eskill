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
                    $synced = (int) ($syncResult['synced'] ?? 0);
                    $total = (int) ($syncResult['total'] ?? 0);
                    logMessage("Métricas sincronizadas: {$synced}/{$total}");
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
            
            $winnerVariationId = $winner['variation_id'] ?? null;
            $confidence = (float) ($winner['confidence'] ?? 0);

            if ($winnerVariationId !== null) {
                $winnerName = (string) ($winner['variation_name'] ?? 'Unknown');
                logMessage("Teste #$testId: Vencedor detectado - '{$winnerName}' (confiança: {$confidence}%)");

                // Auto-complete quando significativo e expirou a duração do teste
                $durationDays = (int) ($test['duration_days'] ?? 7);
                $startedAtRaw = $test['started_at'] ?? null;
                $startedAt = is_string($startedAtRaw) ? strtotime($startedAtRaw) : false;

                if (($winner['is_significant'] ?? false) && $startedAt !== false) {
                    $daysRunning = (time() - $startedAt) / 86400;
                    if ($daysRunning >= $durationDays) {
                        logMessage("Teste #$testId atingiu critérios de conclusão automática");

                        if (!$dryRun) {
                            $service->completeTest($testId);
                            logMessage("Teste #$testId concluído automaticamente!");
                        } else {
                            logMessage("[DRY-RUN] Concluiria teste #$testId automaticamente");
                        }
                    } else {
                        $remaining = (int) ceil($durationDays - $daysRunning);
                        logVerbose("Teste #$testId precisa de mais {$remaining} dia(s) para conclusão automática");
                    }
                }
            } else {
                $reason = (string) ($winner['reason'] ?? 'Sem vencedor');
                logVerbose("Teste #$testId: Ainda sem vencedor definido ({$reason})");
            }
        }
        
    } catch (\Exception $e) {
        logMessage("Erro ao processar teste #$testId: " . $e->getMessage(), 'ERROR');
    }
}

function syncTestMetrics(CloneABTestingService $service, int $testId, int $accountId): array
{
    try {
        $results = $service->syncMetricsFromML($testId);
        $total = count($results);
        $synced = count(array_filter(
            $results,
            static fn(array $r): bool => (bool) ($r['success'] ?? false)
        ));

        return ['success' => true, 'synced' => $synced, 'total' => $total];
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage(), 'synced' => 0, 'total' => 0];
    }
}

function checkTestsForCompletion(\PDO $db): void
{
    global $dryRun;
    
    logVerbose("Verificando testes pendentes de conclusão");
    
    // Buscar testes que atingiram a duração configurada (duration_days)
    $stmt = $db->query("
        SELECT id, account_id, name, started_at, duration_days
        FROM clone_ab_tests
        WHERE status = 'running'
          AND started_at IS NOT NULL
          AND DATEDIFF(NOW(), started_at) >= duration_days
    ");
    
    $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($tests as $test) {
        $testId = (int) $test['id'];
        $durationDays = (int) ($test['duration_days'] ?? 7);
        logMessage("Teste #$testId '{$test['name']}' atingiu duração configurada de {$durationDays} dia(s)");

        if (!$dryRun) {
            try {
                $service = new CloneABTestingService((int) $test['account_id']);
                $winner = $service->determineWinner($testId);

                if (($winner['is_significant'] ?? false) && (($winner['variation_id'] ?? null) !== null)) {
                    $service->completeTest($testId);
                    logMessage("Teste #$testId concluído automaticamente (significativo)");
                } else {
                    logVerbose("Teste #$testId não possui vencedor significativo no momento");
                }
            } catch (\Exception $e) {
                logMessage("Erro ao concluir teste #$testId: " . $e->getMessage(), 'ERROR');
            }
        } else {
            logMessage("[DRY-RUN] Avaliaria conclusão do teste #$testId");
        }
    }
}

// Executar
runWorker();
