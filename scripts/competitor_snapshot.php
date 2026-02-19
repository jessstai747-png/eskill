<?php

/**
 * Wrapper de cron para snapshot/monitoramento de concorrentes.
 * Compatível com entrada legada do crontab:
 * 0 2 * * * php scripts/competitor_snapshot.php
 *
 * Uso opcional para validação local:
 * php scripts/competitor_snapshot.php --dry-run
 */

require_once __DIR__ . '/../vendor/autoload.php';

$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] competitor_snapshot iniciado\n";

if (in_array('--dry-run', $argv, true)) {
    echo "[{$timestamp}] dry-run: wrapper válido e pronto para execução\n";
    exit(0);
}

$workerScript = __DIR__ . '/../bin/competitor-monitor-worker.php';
if (!file_exists($workerScript)) {
    fwrite(STDERR, "[{$timestamp}] competitor-monitor-worker.php não encontrado\n");
    exit(1);
}

$command = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($workerScript) . ' 2>&1';
exec($command, $output, $exitCode);

if (!empty($output)) {
    echo implode(PHP_EOL, $output) . PHP_EOL;
}

echo '[' . date('Y-m-d H:i:s') . "] competitor_snapshot finalizado com código {$exitCode}\n";
exit($exitCode);
