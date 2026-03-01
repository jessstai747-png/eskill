<?php

declare(strict_types=1);

/**
 * Wrapper legado de cron para fila.
 * Agora delega ao MercadoLivreOrchestratorService (sem shell exec encadeado).
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoload.php';

if (!function_exists('log_error')) {
    require_once __DIR__ . '/../app/Helpers/LogHelper.php';
}

use App\Services\MercadoLivreOrchestratorService;

$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] run_queue iniciado (orquestrador ML)\n";

try {
    $service = new MercadoLivreOrchestratorService(dirname(__DIR__));
    $result = $service->runQueue(10, 1, false, 30, true);

    if (!empty($result['skipped'])) {
        echo '[' . date('Y-m-d H:i:s') . '] run_queue ignorado: ' . ($result['reason'] ?? 'skipped') . "\n";
        exit(0);
    }

    $processed = (int)($result['jobs_processed'] ?? 0);
    $batchesRun = (int)($result['batches_run'] ?? 0);
    echo '[' . date('Y-m-d H:i:s') . "] run_queue finalizado: jobs_processed={$processed}, batches_run={$batchesRun}\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] run_queue erro: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
