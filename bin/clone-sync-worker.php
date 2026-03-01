#!/usr/bin/env php
<?php

/**
 * Clone Sync Worker
 *
 * Worker para sincronização automática de itens clonados.
 * Atualiza métricas, verifica status e sincroniza dados.
 *
 * Uso: php bin/clone-sync-worker.php [--once] [--account=ID] [--limit=N]
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use App\Database;
use App\Services\CloneSyncService;

// Parse argumentos
$options = getopt('', ['once', 'account:', 'limit:', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Clone Sync Worker - Sincronização automática de itens clonados

Uso: php bin/clone-sync-worker.php [opções]

Opções:
  --once          Executa uma vez e sai (sem loop)
  --account=ID    Processa apenas uma conta específica
  --limit=N       Limite de itens por conta (default: 50)
  --help          Mostra esta ajuda

Exemplos:
  php bin/clone-sync-worker.php --once
  php bin/clone-sync-worker.php --account=123 --limit=100

HELP;
    exit(0);
}

$runOnce = isset($options['once']);
$specificAccount = isset($options['account']) ? (int) $options['account'] : null;
$limit = isset($options['limit']) ? (int) $options['limit'] : 50;

echo "=== Clone Sync Worker ===\n";
echo "Modo: " . ($runOnce ? 'Execução única' : 'Loop contínuo') . "\n";
echo "Limite por conta: $limit itens\n";
if ($specificAccount) {
    echo "Conta específica: $specificAccount\n";
}
echo str_repeat('-', 50) . "\n\n";

// Acquire exclusive lock to prevent concurrent execution
$_lockDir = __DIR__ . '/../storage/locks';
if (!is_dir($_lockDir)) {
    @mkdir($_lockDir, 0755, true);
}
$_lockFile = $_lockDir . '/clone-sync-worker.lock';
$_lock = fopen($_lockFile, 'c');
if ($_lock === false || !flock($_lock, LOCK_EX | LOCK_NB)) {
    echo "[clone-sync-worker] Já em execução (lock ocupado) — saindo\n";
    if ($_lock !== false) {
        fclose($_lock);
    }
    exit(0);
}

$db = Database::getInstance();

/**
 * Obtém contas ativas com itens clonados
 */
function getActiveAccounts(PDO $db, ?int $specificAccount): array
{
    $query = "
        SELECT DISTINCT target_account_id as account_id
        FROM cloned_items
        WHERE status = 'completed'
    ";

    if ($specificAccount) {
        $query .= " AND target_account_id = :account_id";
    }

    $query .= " ORDER BY target_account_id";

    $stmt = $db->prepare($query);
    if ($specificAccount) {
        $stmt->execute(['account_id' => $specificAccount]);
    } else {
        $stmt->execute();
    }

    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'account_id');
}

/**
 * Processa sincronização de uma conta
 */
function syncAccount(int $accountId, int $limit): array
{
    echo "[" . date('H:i:s') . "] Sincronizando conta $accountId...\n";

    $startTime = microtime(true);

    try {
        $sync = new CloneSyncService($accountId);
        $result = $sync->syncAll([
            'limit' => $limit,
            'days' => 30,
            'types' => ['price', 'stock', 'status', 'metrics']
        ]);

        $elapsed = round(microtime(true) - $startTime, 2);

        echo "  → Total: {$result['total']}, Sincronizados: {$result['synced']}, Erros: {$result['errors']}\n";
        echo "  → Tempo: {$elapsed}s\n";

        return $result;
    } catch (Exception $e) {
        echo "  [ERRO] " . $e->getMessage() . "\n";
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Registra métricas de execução
 */
function logExecution(PDO $db, array $stats): void
{
    try {
        $stmt = $db->prepare("
            INSERT INTO worker_execution_logs (
                worker_name, stats, executed_at
            ) VALUES (
                'clone-sync-worker', :stats, NOW()
            )
        ");
        $stmt->execute(['stats' => json_encode($stats)]);
    } catch (PDOException $e) {
        // Tabela pode não existir
    }
}

// Loop principal
$iteration = 0;
$maxIterations = $runOnce ? 1 : PHP_INT_MAX;

while ($iteration < $maxIterations) {
    $iteration++;

    if (!$runOnce) {
        echo "\n[" . date('Y-m-d H:i:s') . "] Iteração #$iteration\n";
    }

    $accounts = getActiveAccounts($db, $specificAccount);

    if (empty($accounts)) {
        echo "Nenhuma conta com itens clonados encontrada.\n";
        if ($runOnce) break;
        sleep(300); // 5 minutos
        continue;
    }

    echo "Contas para processar: " . count($accounts) . "\n\n";

    $totalStats = [
        'accounts' => count($accounts),
        'total_items' => 0,
        'total_synced' => 0,
        'total_errors' => 0,
        'started_at' => date('c'),
        'finished_at' => null
    ];

    foreach ($accounts as $accountId) {
        $result = syncAccount((int) $accountId, $limit);

        $totalStats['total_items'] += ($result['total'] ?? 0);
        $totalStats['total_synced'] += ($result['synced'] ?? 0);
        $totalStats['total_errors'] += ($result['errors'] ?? 0);

        // Pequena pausa entre contas
        usleep(500000); // 500ms
    }

    $totalStats['finished_at'] = date('c');

    // Registrar execução
    logExecution($db, $totalStats);

    echo "\n[Resumo] Items: {$totalStats['total_items']}, Sincronizados: {$totalStats['total_synced']}, Erros: {$totalStats['total_errors']}\n";

    if ($runOnce) {
        break;
    }

    // Intervalo entre execuções (15 minutos)
    echo "\nPróxima execução em 15 minutos...\n";
    sleep(900);
}

echo "\n=== Worker finalizado ===\n";
flock($_lock, LOCK_UN);
fclose($_lock);
