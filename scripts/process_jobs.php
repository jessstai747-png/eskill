<?php

declare(strict_types=1);

/**
 * Script legado para processar jobs pendentes.
 * Delegado ao MercadoLivreOrchestratorService para consolidar o fluxo operacional.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoload.php';

if (!function_exists('log_error')) {
    require_once __DIR__ . '/../app/Helpers/LogHelper.php';
}

use App\Services\MercadoLivreOrchestratorService;

echo "[" . date('Y-m-d H:i:s') . "] Iniciando processamento de jobs (orquestrador ML)...\n";

try {
    $service = new MercadoLivreOrchestratorService(dirname(__DIR__));
    $result = $service->runQueue(10, 1, false, 30, true);

    if (!empty($result['skipped'])) {
        echo "[" . date('Y-m-d H:i:s') . "] Execução ignorada: " . ($result['reason'] ?? 'skipped') . "\n";
        exit(0);
    }

    $processed = (int)($result['jobs_processed'] ?? 0);
    echo "[" . date('Y-m-d H:i:s') . "] Processados {$processed} jobs.\n";

    if (!empty($result['batches']) && is_array($result['batches'])) {
        foreach ($result['batches'] as $batch) {
            if (!is_array($batch)) {
                continue;
            }
            $batchNo = (int)($batch['batch'] ?? 0);
            $batchCount = (int)($batch['processed'] ?? 0);
            echo " - Lote #{$batchNo}: {$batchCount} jobs\n";
        }
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Erro fatal: " . $e->getMessage() . "\n";
    exit(1);
}
