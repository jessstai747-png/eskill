<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$logsDir = $root . '/storage/logs';
$stateFile = $root . '/storage/cron_remediation_state.json';
$dryRun = in_array('--dry-run', $argv, true);

$remediationMap = [
    'scheduler' => [
        'command' => PHP_BINARY . ' ' . escapeshellarg($root . '/scripts/scheduler.php'),
        'cooldown' => 15 * 60,
    ],
    'queue' => [
        'command' => PHP_BINARY . ' ' . escapeshellarg($root . '/scripts/run_queue.php'),
        'cooldown' => 5 * 60,
    ],
    'clone_worker' => [
        'command' => PHP_BINARY . ' ' . escapeshellarg($root . '/bin/catalog-clone-worker.php') . ' --once',
        'cooldown' => 10 * 60,
    ],
    'clone_post_actions' => [
        'command' => PHP_BINARY . ' ' . escapeshellarg($root . '/bin/clone-post-actions-worker.php') . ' --once',
        'cooldown' => 15 * 60,
    ],
];

$healthCmd = PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/cron_health_check.php') . ' 2>/dev/null';
$healthOutput = [];
$healthExit = 0;
exec($healthCmd, $healthOutput, $healthExit);
$healthJson = trim(implode(PHP_EOL, $healthOutput));
$healthReport = json_decode($healthJson, true);

if (!is_array($healthReport) || !isset($healthReport['results']) || !is_array($healthReport['results'])) {
    $msg = '[' . date('Y-m-d H:i:s') . "] cron_auto_remediate: falha ao ler relatório de health\n";
    @file_put_contents($logsDir . '/cron_remediation.log', $msg, FILE_APPEND);
    fwrite(STDERR, $msg);
    exit(3);
}

$state = [];
if (file_exists($stateFile)) {
    $raw = file_get_contents($stateFile);
    $decoded = json_decode((string)$raw, true);
    if (is_array($decoded)) {
        $state = $decoded;
    }
}

$now = time();
$actions = [];

foreach ($healthReport['results'] as $result) {
    $name = (string)($result['name'] ?? '');
    $status = (string)($result['status'] ?? 'ok');

    if (!isset($remediationMap[$name])) {
        continue;
    }

    if (!in_array($status, ['critical', 'warning'], true)) {
        continue;
    }

    $cfg = $remediationMap[$name];
    $lastRun = (int)($state[$name]['last_attempt'] ?? 0);
    $cooldown = (int)$cfg['cooldown'];

    if (($now - $lastRun) < $cooldown) {
        $actions[] = [
            'name' => $name,
            'status' => 'skipped_cooldown',
            'cooldown_seconds' => $cooldown,
            'seconds_since_last_attempt' => $now - $lastRun,
        ];
        continue;
    }

    $command = (string)$cfg['command'];

    if ($dryRun) {
        $actions[] = [
            'name' => $name,
            'status' => 'dry_run',
            'command' => $command,
        ];
        continue;
    }

    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);

    $state[$name] = [
        'last_attempt' => $now,
        'last_exit_code' => $exitCode,
        'last_status' => $exitCode === 0 ? 'ok' : 'error',
        'updated_at' => date('c'),
    ];

    $actions[] = [
        'name' => $name,
        'status' => $exitCode === 0 ? 'remediated' : 'failed',
        'exit_code' => $exitCode,
        'command' => $command,
        'output_preview' => array_slice($output, 0, 20),
    ];
}

if (!$dryRun) {
    @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// ── Phase 8: Alertas de escalation ──
$alertSummary = ['alerts_sent' => 0, 'recoveries' => 0];

if (!$dryRun && class_exists(\App\Services\CronAlertService::class)) {
    try {
        $cronAlerts = new \App\Services\CronAlertService();
        $alertSummary = $cronAlerts->processResults(
            $healthReport['results'] ?? [],
            $actions
        );
    } catch (\Throwable $e) {
        $alertSummary['error'] = $e->getMessage();
        error_log('cron_auto_remediate: CronAlertService falhou: ' . $e->getMessage());
    }
}

$report = [
    'timestamp' => date('c'),
    'dry_run' => $dryRun,
    'health_overall' => $healthReport['overall'] ?? 'unknown',
    'health_exit_code' => $healthExit,
    'actions' => $actions,
    'alerts' => $alertSummary,
];

$line = '[' . date('Y-m-d H:i:s') . '] ' . json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
@file_put_contents($logsDir . '/cron_remediation.log', $line, FILE_APPEND);

if (PHP_SAPI === 'cli') {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    header('Content-Type: application/json');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

$failed = array_filter($actions, static fn(array $a): bool => ($a['status'] ?? '') === 'failed');
exit(empty($failed) ? 0 : 1);
