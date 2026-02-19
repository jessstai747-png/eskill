<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$logsDir = $root . '/storage/logs';

$checks = [
    ['name' => 'scheduler', 'file' => $logsDir . '/cron_scheduler.log', 'max_age' => 15 * 60, 'severity' => 'critical'],
    ['name' => 'queue', 'file' => $logsDir . '/queue.log', 'max_age' => 5 * 60, 'severity' => 'critical'],
    ['name' => 'orders', 'file' => $logsDir . '/cron_orders.log', 'max_age' => 30 * 60, 'severity' => 'warning'],
    ['name' => 'items', 'file' => $logsDir . '/cron_items.log', 'max_age' => 7 * 3600, 'severity' => 'warning'],
    ['name' => 'questions', 'file' => $logsDir . '/cron_questions.log', 'max_age' => 2 * 3600, 'severity' => 'warning'],
    ['name' => 'clone_worker', 'file' => $logsDir . '/cron-catalog-clone.log', 'max_age' => 5 * 60, 'severity' => 'critical'],
    ['name' => 'clone_post_actions', 'file' => $logsDir . '/cron-post-actions.log', 'max_age' => 10 * 60, 'severity' => 'warning'],
    ['name' => 'clone_health', 'file' => $logsDir . '/cron-health.log', 'max_age' => 10 * 60, 'severity' => 'warning'],
    ['name' => 'token_refresh', 'file' => $logsDir . '/token_refresh.log', 'max_age' => 2 * 3600, 'severity' => 'warning'],
];

$now = time();
$results = [];
$criticalFailures = 0;
$warningFailures = 0;

foreach ($checks as $check) {
    $file = $check['file'];
    $exists = file_exists($file);

    if (!$exists) {
        $status = $check['severity'] === 'critical' ? 'critical' : 'warning';
        $results[] = [
            'name' => $check['name'],
            'status' => $status,
            'reason' => 'missing_file',
            'file' => $file,
            'last_modified' => null,
            'age_seconds' => null,
            'max_age_seconds' => $check['max_age'],
        ];
        if ($status === 'critical') {
            $criticalFailures++;
        } else {
            $warningFailures++;
        }
        continue;
    }

    $mtime = filemtime($file);
    $age = $now - $mtime;

    if ($age > $check['max_age']) {
        $status = $check['severity'] === 'critical' ? 'critical' : 'warning';
        $results[] = [
            'name' => $check['name'],
            'status' => $status,
            'reason' => 'stale_log',
            'file' => $file,
            'last_modified' => date('c', $mtime),
            'age_seconds' => $age,
            'max_age_seconds' => $check['max_age'],
        ];
        if ($status === 'critical') {
            $criticalFailures++;
        } else {
            $warningFailures++;
        }
    } else {
        $results[] = [
            'name' => $check['name'],
            'status' => 'ok',
            'reason' => 'healthy',
            'file' => $file,
            'last_modified' => date('c', $mtime),
            'age_seconds' => $age,
            'max_age_seconds' => $check['max_age'],
        ];
    }
}

$overall = 'healthy';
$exitCode = 0;
if ($criticalFailures > 0) {
    $overall = 'critical';
    $exitCode = 2;
} elseif ($warningFailures > 0) {
    $overall = 'warning';
    $exitCode = 1;
}

$report = [
    'timestamp' => date('c'),
    'overall' => $overall,
    'critical_failures' => $criticalFailures,
    'warning_failures' => $warningFailures,
    'total_checks' => count($checks),
    'results' => $results,
];

$auditFile = $logsDir . '/cron_health_audit.log';
$line = sprintf("[%s] overall=%s critical=%d warning=%d checks=%d\n", date('Y-m-d H:i:s'), $overall, $criticalFailures, $warningFailures, count($checks));
@file_put_contents($auditFile, $line, FILE_APPEND);

if ($overall !== 'healthy') {
    $alertFile = $logsDir . '/cron_health_alerts.log';
    @file_put_contents($alertFile, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);

    // Phase 8: Alerta direto para jobs sem remediação mapeada
    if (class_exists(\App\Services\CronAlertService::class)) {
        try {
            $cronAlerts = new \App\Services\CronAlertService();
            foreach ($results as $r) {
                $status = (string) ($r['status'] ?? 'ok');
                $name = (string) ($r['name'] ?? '');
                if ($name !== '' && $status !== 'ok') {
                    $cronAlerts->recordFailure($name, 'health_check: ' . ($r['reason'] ?? $status));
                } elseif ($name !== '' && $status === 'ok') {
                    $cronAlerts->recordRecovery($name);
                }
            }
        } catch (\Throwable $e) {
            error_log('cron_health_check: CronAlertService falhou: ' . $e->getMessage());
        }
    }
}

header('Content-Type: application/json');
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($exitCode);
