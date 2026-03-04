#!/usr/bin/env php
<?php
/**
 * Competitor Monitor Worker
 *
 * Cron job para monitoramento automático de concorrentes.
 * Escaneia a watchlist e gera alertas de preço.
 *
 * Sugestão de cron: Executar 3x ao dia (8h, 14h, 20h)
 * 0 8,14,20 * * * php /path/to/bin/competitor-monitor-worker.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoload.php';

use App\Database;
use App\Services\CompetitorMonitorService;
use App\Services\StructuredLogService;

// Configuração de ambiente
set_time_limit(0);
ini_set('memory_limit', '512M');

define('WORKER_NAME', 'competitor-monitor-worker');
define('LOG_FILE', __DIR__ . '/../storage/logs/competitor-monitor-worker.log');
define('LOCK_FILE', __DIR__ . '/../storage/locks/competitor-monitor-worker.lock');

// ─── Flock: prevenir execução concorrente ─────────────────────────────────────
$lockDir = dirname(LOCK_FILE);
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0755, true);
}
$lockHandle = fopen(LOCK_FILE, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "[" . WORKER_NAME . "] Outra instância já está em execução — saindo\n";
    if ($lockHandle !== false) {
        fclose($lockHandle);
    }
    exit(0);
}

putenv('LOG_PATH=' . LOG_FILE);
$logger = new StructuredLogService();

/**
 * @return list<int>
 */
function fetchAccountsWithWatchlist(\PDO $db): array
{
    // Compatibilidade: algumas instalações antigas usam coluna `active`, as novas usam `is_active`.
    try {
        $stmt = $db->query("SELECT DISTINCT account_id FROM pricing_watchlist WHERE is_active = 1");
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    } catch (\Throwable $e) {
        $stmt = $db->query("SELECT DISTINCT account_id FROM pricing_watchlist WHERE active = 1");
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    $accounts = [];
    foreach ($rows as $v) {
        $id = (int)$v;
        if ($id > 0) {
            $accounts[] = $id;
        }
    }
    return $accounts;
}

/**
 * @return list<array{item_id:string, keywords:string|null}>
 */
function fetchWatchlistItems(\PDO $db, int $accountId): array
{
    try {
        $stmt = $db->prepare("SELECT item_id, keywords FROM pricing_watchlist WHERE account_id = :account_id AND is_active = 1");
        $stmt->execute(['account_id' => $accountId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        $stmt = $db->prepare("SELECT item_id, keywords FROM pricing_watchlist WHERE account_id = :account_id AND active = 1");
        $stmt->execute(['account_id' => $accountId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

echo "=== Competitor Monitor Worker ===" . PHP_EOL;
echo "Iniciado em: " . date('Y-m-d H:i:s') . PHP_EOL;
echo str_repeat('-', 50) . PHP_EOL;

$logger->info('Worker started', ['worker' => WORKER_NAME, 'started_at' => date(DATE_ATOM)]);

try {
    $db = Database::getInstance();

    // Buscar todas as contas ativas com items na watchlist
    $accounts = fetchAccountsWithWatchlist($db);

    if (empty($accounts)) {
        echo "Nenhuma conta com items na watchlist." . PHP_EOL;
        $logger->info('No watchlist accounts found', ['worker' => WORKER_NAME]);
        exit(0);
    }

    echo "Contas a processar: " . count($accounts) . PHP_EOL;

    $totalScanned = 0;
    $totalAlerts = 0;

    foreach ($accounts as $accountId) {
        $accountId = (int)$accountId;
        echo PHP_EOL . "Processando conta: {$accountId}" . PHP_EOL;
        $logger->info('Processing account', ['worker' => WORKER_NAME, 'account_id' => $accountId]);

        try {
            $service = new CompetitorMonitorService($accountId);

            // Buscar items da watchlist
            $items = fetchWatchlistItems($db, $accountId);

            echo "  Items na watchlist: " . count($items) . PHP_EOL;
            $logger->info('Watchlist items loaded', ['worker' => WORKER_NAME, 'account_id' => $accountId, 'count' => count($items)]);

            foreach ($items as $item) {
                try {
                    $itemId = (string)($item['item_id'] ?? '');
                    $keywords = isset($item['keywords']) ? (string)$item['keywords'] : null;
                    if ($itemId === '') {
                        continue;
                    }

                    echo "  Escaneando: {$itemId}...";
                    $logger->info('Scan started', ['worker' => WORKER_NAME, 'account_id' => $accountId, 'item_id' => $itemId]);

                    // Escanear concorrentes
                    $result = $service->scanCompetitors(
                        $itemId,
                        $keywords
                    );

                    if ($result['success']) {
                        $competitorCount = count($result['competitors'] ?? []);
                        echo " OK ({$competitorCount} concorrentes)" . PHP_EOL;
                        $totalScanned++;

                        $logger->info('Scan finished', [
                            'worker' => WORKER_NAME,
                            'account_id' => $accountId,
                            'item_id' => $itemId,
                            'competitors_found' => $competitorCount,
                            'alerts_generated' => isset($result['alerts']) && is_array($result['alerts']) ? count($result['alerts']) : 0,
                        ]);

                        // Verificar alertas gerados
                        if (!empty($result['alerts_generated'])) {
                            $totalAlerts += count($result['alerts_generated']);
                            foreach ($result['alerts_generated'] as $alert) {
                                echo "    [ALERTA] {$alert['type']}: {$alert['message']}" . PHP_EOL;
                                $logger->warning('Alert generated', [
                                    'worker' => WORKER_NAME,
                                    'account_id' => $accountId,
                                    'item_id' => $itemId,
                                    'alert' => $alert,
                                ]);
                            }
                        }
                    } else {
                        $err = (string)($result['error'] ?? $result['message'] ?? 'Desconhecido');
                        echo " ERRO: " . $err . PHP_EOL;
                        $logger->error('Scan failed', [
                            'worker' => WORKER_NAME,
                            'account_id' => $accountId,
                            'item_id' => $itemId,
                            'error' => $err,
                            'result' => $result,
                        ]);
                    }

                    // Rate limiting - aguardar entre scans
                    usleep(500000); // 500ms

                } catch (Exception $e) {
                    echo " EXCEPTION: {$e->getMessage()}" . PHP_EOL;
                    $logger->error('Exception scanning item', [
                        'worker' => WORKER_NAME,
                        'account_id' => $accountId,
                        'item_id' => $item['item_id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (Exception $e) {
            echo "  ERRO na conta {$accountId}: {$e->getMessage()}" . PHP_EOL;
            $logger->error('Account processing error', ['worker' => WORKER_NAME, 'account_id' => $accountId, 'error' => $e->getMessage()]);
        }
    }

    echo PHP_EOL . str_repeat('-', 50) . PHP_EOL;
    echo "Resumo:" . PHP_EOL;
    echo "  Items escaneados: {$totalScanned}" . PHP_EOL;
    echo "  Alertas gerados: {$totalAlerts}" . PHP_EOL;
    echo "  Finalizado em: " . date('Y-m-d H:i:s') . PHP_EOL;

    $logger->info('Worker finished', [
        'worker' => WORKER_NAME,
        'items_scanned' => $totalScanned,
        'alerts_generated' => $totalAlerts,
        'finished_at' => date(DATE_ATOM),
    ]);
} catch (Exception $e) {
    echo "ERRO FATAL: {$e->getMessage()}" . PHP_EOL;
    $logger->critical('Fatal error', ['worker' => WORKER_NAME, 'error' => $e->getMessage()]);
    exit(1);
} finally {
    if (isset($lockHandle) && is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

exit(0);
