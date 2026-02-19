<?php

/**
 * Script para processar jobs pendentes
 * Pode ser executado via cron ou manualmente
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoload.php';

// Defensive: ensure global helper functions exist even if the runtime is in a partial-deploy state
// or composer autoload files were not regenerated.
if (!function_exists('log_error')) {
    require_once __DIR__ . '/../app/Helpers/LogHelper.php';
}

use App\Services\JobService;

echo "[" . date('Y-m-d H:i:s') . "] Iniciando processamento de jobs...\n";

try {
    $jobService = new JobService();

    // Processar até 10 jobs por execução
    $results = $jobService->process(10);

    $count = count($results);
    echo "[" . date('Y-m-d H:i:s') . "] Processados {$count} jobs.\n";

    foreach ($results as $result) {
        $status = $result['status'] ?? 'unknown';
        $id = $result['id'] ?? '?';
        $type = $result['type'] ?? '?';

        if ($status === 'completed') {
            echo " - Job #{$id} ({$type}): Sucesso\n";
        } else {
            $error = $result['error'] ?? 'Erro desconhecido';
            echo " - Job #{$id} ({$type}): Falha/Pendente - {$error}\n";
        }
    }
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Erro fatal: " . $e->getMessage() . "\n";
    exit(1);
}
