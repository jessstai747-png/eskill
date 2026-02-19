<?php

/**
 * Wrapper de cron para processamento de fila de jobs.
 * Compatível com entrada legada do crontab:
 * * * * * php scripts/run_queue.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoload.php';

if (!function_exists('log_error')) {
    require_once __DIR__ . '/../app/Helpers/LogHelper.php';
}

$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] run_queue iniciado\n";

$processJobsScript = __DIR__ . '/process_jobs.php';

if (!file_exists($processJobsScript)) {
    fwrite(STDERR, "[{$timestamp}] process_jobs.php não encontrado\n");
    exit(1);
}

$command = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($processJobsScript) . ' 2>&1';
exec($command, $output, $exitCode);

if (!empty($output)) {
    echo implode(PHP_EOL, $output) . PHP_EOL;
}

echo '[' . date('Y-m-d H:i:s') . "] run_queue finalizado com código {$exitCode}\n";
exit($exitCode);
