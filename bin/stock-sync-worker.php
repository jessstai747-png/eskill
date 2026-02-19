#!/usr/bin/env php
<?php

/**
 * Stock Sync Worker
 *
 * Worker para sincronização automática de estoque entre contas do Mercado Livre.
 * Processa fila de sincronização e executa full sync periodicamente.
 *
 * Features:
 * - Lock para evitar execução duplicada (via arquivo .lock)
 * - Processamento de fila com prioridade
 * - Full sync periódico configurável
 * - Cleanup automático de itens antigos
 * - Logging via Monolog
 *
 * Uso: php bin/stock-sync-worker.php [--once] [--user=ID] [--limit=N] [--full-sync]
 */

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use App\Database;
use App\Services\MercadoLivre\StockSyncService;

// =========================================================================
// CLI Arguments
// =========================================================================

$options = getopt('', ['once', 'user:', 'limit:', 'full-sync', 'cleanup', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Stock Sync Worker — Sincronização de estoque entre contas ML

Uso: php bin/stock-sync-worker.php [opções]

Opções:
  --once            Executa uma vez e sai (sem loop)
  --user=ID         Processa apenas um usuário específico
  --limit=N         Limite de itens por ciclo (default: 50)
  --full-sync       Força full sync antes de processar fila
  --cleanup         Executa limpeza de fila antiga e sai
  --help            Mostra esta ajuda

Exemplos:
  php bin/stock-sync-worker.php --once
  php bin/stock-sync-worker.php --user=1 --full-sync
  php bin/stock-sync-worker.php --cleanup

HELP;
    exit(0);
}

$runOnce = isset($options['once']);
$specificUser = isset($options['user']) ? (int) $options['user'] : null;
$limit = isset($options['limit']) ? (int) $options['limit'] : 50;
$forceFullSync = isset($options['full-sync']);
$runCleanup = isset($options['cleanup']);

// =========================================================================
// Lock — impedir execução duplicada
// =========================================================================

$lockFile = __DIR__ . '/../storage/stock-sync-worker.lock';
$lockDir = dirname($lockFile);

if (!is_dir($lockDir)) {
    mkdir($lockDir, 0755, true);
}

$lockFp = fopen($lockFile, 'c+');

if ($lockFp === false) {
    echo "[ERRO] Não foi possível criar arquivo de lock: {$lockFile}\n";
    exit(1);
}

if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "[ERRO] Worker já está em execução (lock ativo). Saindo.\n";
    fclose($lockFp);
    exit(0);
}

// Escrever PID no lock
ftruncate($lockFp, 0);
fwrite($lockFp, (string) getmypid());
fflush($lockFp);

// Registrar handler de shutdown para liberar lock
register_shutdown_function(function () use ($lockFp, $lockFile): void {
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    @unlink($lockFile);
});

// Capturar sinais para shutdown gracioso
$shouldStop = false;
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    $signalHandler = function (int $signal) use (&$shouldStop): void {
        echo "\n[" . date('H:i:s') . "] Sinal {$signal} recebido, finalizando graciosamente...\n";
        $shouldStop = true;
    };
    pcntl_signal(SIGTERM, $signalHandler);
    pcntl_signal(SIGINT, $signalHandler);
}

// =========================================================================
// Setup
// =========================================================================

echo "=== Stock Sync Worker ===\n";
echo "PID: " . getmypid() . "\n";
echo "Modo: " . ($runOnce ? 'Execução única' : 'Loop contínuo') . "\n";
echo "Limite por ciclo: {$limit} itens\n";
if ($specificUser) {
    echo "Usuário específico: {$specificUser}\n";
}
echo str_repeat('-', 50) . "\n\n";

try {
    $db = Database::getInstance();
} catch (\Throwable $e) {
    echo "[ERRO FATAL] Não foi possível conectar ao banco: " . $e->getMessage() . "\n";
    exit(1);
}

$service = new StockSyncService();

// =========================================================================
// Modo cleanup
// =========================================================================

if ($runCleanup) {
    echo "[" . date('H:i:s') . "] Executando limpeza de fila...\n";
    $deleted = $service->cleanupQueue(7);
    echo "  → {$deleted} itens antigos removidos\n";
    echo "=== Cleanup finalizado ===\n";
    exit(0);
}

// =========================================================================
// Funções auxiliares
// =========================================================================

/**
 * Obtém usuários ativos com sync habilitado
 *
 * @return array<int, int>
 */
function getEnabledUsers(\PDO $db, ?int $specificUser): array
{
    if ($specificUser !== null) {
        return [$specificUser];
    }

    try {
        $stmt = $db->query("
            SELECT DISTINCT user_id
            FROM stock_sync_rules
            WHERE is_active = 1
            ORDER BY user_id
        ");
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'user_id');
    } catch (\PDOException $e) {
        log_error('Failed to get enabled users for stock sync', ['error' => $e->getMessage()]);
        return [];
    }
}

/**
 * Verifica se é hora de executar full sync para um usuário
 */
function shouldRunFullSync(\PDO $db, int $userId, bool $force): bool
{
    if ($force) {
        return true;
    }

    try {
        $stmt = $db->prepare("
            SELECT full_sync_interval_minutes
            FROM stock_sync_settings
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $intervalMinutes = (int) ($row['full_sync_interval_minutes'] ?? 60);

        // Verificar último full sync
        $stmt = $db->prepare("
            SELECT MAX(last_synced_at) AS last_sync
            FROM stock_sync_rules
            WHERE user_id = :user_id AND is_active = 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $lastSync = $stmt->fetch(\PDO::FETCH_ASSOC)['last_sync'] ?? null;

        if ($lastSync === null) {
            return true;
        }

        $elapsedMinutes = (time() - strtotime($lastSync)) / 60;
        return $elapsedMinutes >= $intervalMinutes;
    } catch (\PDOException $e) {
        return false;
    }
}

/**
 * Registra métricas da execução do worker
 */
function logWorkerExecution(\PDO $db, array $stats): void
{
    try {
        $stmt = $db->prepare("
            INSERT INTO worker_execution_logs (worker_name, stats, executed_at)
            VALUES ('stock-sync-worker', :stats, NOW())
        ");
        $stmt->execute(['stats' => json_encode($stats)]);
    } catch (\PDOException $e) {
        // Tabela pode não existir — não é crítico
    }
}

// =========================================================================
// Loop principal
// =========================================================================

$iteration = 0;
$maxIterations = $runOnce ? 1 : PHP_INT_MAX;

while ($iteration < $maxIterations && !$shouldStop) {
    $iteration++;

    if (!$runOnce) {
        echo "\n[" . date('Y-m-d H:i:s') . "] Iteração #{$iteration}\n";
    }

    $users = getEnabledUsers($db, $specificUser);

    if (empty($users)) {
        echo "Nenhum usuário com sync ativo encontrado.\n";
        if ($runOnce) {
            break;
        }
        sleep(60);
        continue;
    }

    echo "Usuários para processar: " . count($users) . "\n";

    $totalStats = [
        'users' => count($users),
        'full_sync_queued' => 0,
        'queue_processed' => 0,
        'queue_failed' => 0,
        'queue_skipped' => 0,
        'started_at' => date('c'),
        'finished_at' => null,
    ];

    foreach ($users as $userId) {
        if ($shouldStop) {
            echo "[" . date('H:i:s') . "] Parando por sinal recebido.\n";
            break;
        }

        $userId = (int) $userId;
        echo "\n[" . date('H:i:s') . "] Processando usuário {$userId}...\n";

        try {
            // 1) Full sync se necessário
            if (shouldRunFullSync($db, $userId, $forceFullSync)) {
                echo "  → Executando full sync...\n";
                $fullSyncStats = $service->fullSync($userId);
                echo "  → Full sync: {$fullSyncStats['queued']} enfileirados, {$fullSyncStats['skipped']} sem alteração, {$fullSyncStats['errors']} erros\n";
                $totalStats['full_sync_queued'] += $fullSyncStats['queued'];
            }

            // 2) Processar fila
            $pending = $service->getPendingCount();
            if ($pending > 0) {
                echo "  → Processando fila ({$pending} pendentes)...\n";
                $queueStats = $service->processQueue($limit);
                echo "  → Fila: {$queueStats['processed']} processados, {$queueStats['failed']} falhas, {$queueStats['skipped']} ignorados\n";
                $totalStats['queue_processed'] += $queueStats['processed'];
                $totalStats['queue_failed'] += $queueStats['failed'];
                $totalStats['queue_skipped'] += $queueStats['skipped'];
            } else {
                echo "  → Nenhum item pendente na fila.\n";
            }
        } catch (\Throwable $e) {
            echo "  [ERRO] " . $e->getMessage() . "\n";
            log_error('Stock sync worker error for user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        // Pausa entre usuários
        usleep(500_000); // 500ms
    }

    $totalStats['finished_at'] = date('c');

    // Cleanup periódico (a cada 100 iterações no loop contínuo)
    if ($iteration % 100 === 0) {
        echo "\n[" . date('H:i:s') . "] Executando limpeza periódica...\n";
        $cleaned = $service->cleanupQueue(7);
        echo "  → {$cleaned} itens antigos removidos\n";
    }

    // Log execução
    logWorkerExecution($db, $totalStats);

    echo "\n[Resumo] Full sync: {$totalStats['full_sync_queued']}, Processados: {$totalStats['queue_processed']}, Falhas: {$totalStats['queue_failed']}, Ignorados: {$totalStats['queue_skipped']}\n";

    if ($runOnce) {
        break;
    }

    // Intervalo entre execuções: 30 segundos
    echo "\nPróxima execução em 30 segundos...\n";

    for ($i = 0; $i < 30 && !$shouldStop; $i++) {
        sleep(1);
    }
}

echo "\n=== Stock Sync Worker finalizado ===\n";
