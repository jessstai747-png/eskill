#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Governance Diagnostic Worker
 *
 * Background worker que executa diagnosticos periodicos de governanca
 * em todas as contas ML ativas.
 *
 * Uso:
 *   php bin/governance-diagnostic-worker.php [--once] [--account=ID] [--verbose]
 *
 * Crontab recomendado (a cada 6h):
 *   0 0,6,12,18 * * * php /path/to/bin/governance-diagnostic-worker.php --once
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\MercadoLivre\AccountGovernanceIntegrationService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

// --- Configuration ---
const WORKER_NAME = 'governance-diagnostic-worker';
const SLEEP_BETWEEN_CYCLES_SEC = 21600;
const MAX_ITEMS_PER_DIAGNOSTIC = 300;
const CRITICAL_STATUSES = ['paused', 'inactive', 'closed'];

// Load environment
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

// --- Logger Setup ---
$logger = new Logger(WORKER_NAME);

$consoleHandler = new StreamHandler('php://stdout', Logger::INFO);
$consoleHandler->setFormatter(new LineFormatter(
    "[%datetime%] %channel%.%level_name%: %message% %context%\n",
    'Y-m-d H:i:s'
));
$logger->pushHandler($consoleHandler);

$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$fileHandler = new RotatingFileHandler($logDir . '/governance-worker.log', 7, Logger::DEBUG);
$logger->pushHandler($fileHandler);

// --- Parse CLI Arguments ---
$options = getopt('', ['once', 'account:', 'verbose', 'help']);

if (isset($options['help'])) {
    echo "Governance Diagnostic Worker\n";
    echo "============================\n";
    echo "Usage: php governance-diagnostic-worker.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --once       Run single cycle and exit\n";
    echo "  --account=ID Run diagnostic for specific account ID only\n";
    echo "  --verbose    Show detailed output\n";
    echo "  --help       Show this help message\n";
    exit(0);
}

$runOnce = isset($options['once']);
$specificAccountId = $options['account'] ?? null;
$verbose = isset($options['verbose']);

// --- Database Connection ---
function getDbConnection(): PDO
{
    return new PDO(
        sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_NAME'] ?? 'eskill'
        ),
        $_ENV['DB_USER'] ?? 'root',
        $_ENV['DB_PASS'] ?? '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

/**
 * Get all active ML accounts
 * @return array<int, array{id: int, user_id: string, nickname: string, access_token: string}>
 */
function getActiveAccounts(PDO $db, ?string $specificId = null): array
{
    $sql = "SELECT id, user_id, nickname, access_token, refresh_token 
            FROM mercadolivre_auth 
            WHERE access_token IS NOT NULL AND access_token != ''";

    if ($specificId !== null) {
        $sql .= " AND id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $specificId]);
    } else {
        $stmt = $db->query($sql);
    }

    return $stmt->fetchAll();
}

/**
 * Get last diagnostic for an account
 * @return array<string, mixed>|null
 */
function getLastDiagnostic(PDO $db, int $accountId): ?array
{
    $sql = "SELECT * FROM governance_diagnostic_history 
            WHERE account_id = :account_id 
            ORDER BY created_at DESC LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->execute(['account_id' => $accountId]);

    $result = $stmt->fetch();
    return $result !== false ? $result : null;
}

/**
 * Save diagnostic to history
 * @param array<string, mixed> $result
 */
function saveDiagnostic(PDO $db, int $accountId, array $result): bool
{
    $sql = "INSERT INTO governance_diagnostic_history 
            (account_id, account_status, total_items, healthy_items, problem_items, 
             critical_actions, top_causes, executive_summary, full_result) 
            VALUES 
            (:account_id, :account_status, :total_items, :healthy_items, :problem_items,
             :critical_actions, :top_causes, :executive_summary, :full_result)";

    $activeCount = $result['metrics']['status_distribution']['active'] ?? 0;
    $accountStatus = $activeCount > 0 ? 'active' : 'inactive';

    $stmt = $db->prepare($sql);
    return $stmt->execute([
        'account_id' => $accountId,
        'account_status' => $accountStatus,
        'total_items' => $result['metrics']['totals']['items'] ?? 0,
        'healthy_items' => $result['metrics']['totals']['healthy'] ?? 0,
        'problem_items' => $result['metrics']['totals']['problems'] ?? 0,
        'critical_actions' => $result['metrics']['priority_breakdown']['critical'] ?? 0,
        'top_causes' => json_encode($result['metrics']['top_causes'] ?? []),
        'executive_summary' => json_encode($result['executive_summary'] ?? []),
        'full_result' => json_encode($result),
    ]);
}

/**
 * Send critical alert if needed
 * @param array<string, mixed> $account
 * @param array<string, mixed> $result
 */
function sendCriticalAlert(Logger $logger, array $account, array $result): void
{
    $criticalActions = $result['metrics']['priority_breakdown']['critical'] ?? 0;

    if ($criticalActions > 0) {
        $logger->critical('Account requires immediate attention!', [
            'account_id' => $account['id'],
            'nickname' => $account['nickname'] ?? 'N/A',
            'critical_actions' => $criticalActions,
            'health_score' => $result['metrics']['health_score'] ?? 0,
        ]);
    }
}

/**
 * Process a single account
 * @param array<string, mixed> $account
 * @return array{success: bool, account_id: int, health_score?: float, error?: string}
 */
function processAccount(PDO $db, Logger $logger, array $account, bool $verbose): array
{
    $accountId = (int) $account['id'];
    $nickname = $account['nickname'] ?? "Account #$accountId";

    $logger->info("Processing account: $nickname", ['account_id' => $accountId]);

    try {
        $service = new AccountGovernanceIntegrationService(
            accountId: $accountId,
            logger: $logger
        );

        $result = $service->runDiagnosticFromAPI(['max_items' => MAX_ITEMS_PER_DIAGNOSTIC]);

        if ($verbose) {
            $logger->info('Diagnostic complete', [
                'account_id' => $accountId,
                'health_score' => $result['metrics']['health_score'] ?? 'N/A',
                'total_items' => $result['metrics']['totals']['items'] ?? 0,
                'problems' => $result['metrics']['totals']['problems'] ?? 0,
            ]);
        }

        saveDiagnostic($db, $accountId, $result);
        sendCriticalAlert($logger, $account, $result);

        return [
            'success' => true,
            'account_id' => $accountId,
            'health_score' => $result['metrics']['health_score'] ?? 0,
        ];
    } catch (\Throwable $e) {
        $logger->error("Failed to process account", [
            'account_id' => $accountId,
            'error' => $e->getMessage(),
        ]);

        return [
            'success' => false,
            'account_id' => $accountId,
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Main cycle
 * @return array{processed: int, success: int, failed: int}
 */
function runCycle(PDO $db, Logger $logger, ?string $specificAccountId, bool $verbose): array
{
    $startTime = microtime(true);
    $results = ['processed' => 0, 'success' => 0, 'failed' => 0];

    $accounts = getActiveAccounts($db, $specificAccountId);
    $logger->info('Starting governance diagnostic cycle', [
        'accounts_count' => count($accounts),
    ]);

    foreach ($accounts as $account) {
        $result = processAccount($db, $logger, $account, $verbose);
        $results['processed']++;

        if ($result['success']) {
            $results['success']++;
        } else {
            $results['failed']++;
        }

        usleep(500000);
    }

    $duration = round(microtime(true) - $startTime, 2);
    $logger->info('Cycle completed', [
        'duration_sec' => $duration,
        'processed' => $results['processed'],
        'success' => $results['success'],
        'failed' => $results['failed'],
    ]);

    return $results;
}

// --- Main Entry Point ---
$logger->info('Worker starting', [
    'mode' => $runOnce ? 'single' : 'continuous',
    'specific_account' => $specificAccountId,
]);

try {
    $db = getDbConnection();

    if ($runOnce) {
        runCycle($db, $logger, $specificAccountId, $verbose);
    } else {
        while (true) {
            runCycle($db, $logger, $specificAccountId, $verbose);

            $logger->info("Sleeping for " . SLEEP_BETWEEN_CYCLES_SEC . " seconds...");
            sleep(SLEEP_BETWEEN_CYCLES_SEC);
        }
    }
} catch (\Throwable $e) {
    $logger->critical('Worker crashed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit(1);
}

$logger->info('Worker finished');
exit(0);
