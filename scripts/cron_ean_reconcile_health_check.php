<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\EanService;
use App\Services\WebhookInboxService;

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

$staleMinutes = (int)(getenv('EAN_RECONCILE_HEARTBEAT_STALE_MINUTES') ?: 15);
$maxDivergences = (int)(getenv('EAN_RECONCILE_MAX_DIVERGENCES') ?: 20);
$webhookSlaHoursBack = (int)(getenv('EAN_WEBHOOK_SLA_HOURS_BACK') ?: 1);
$webhookMaxAvgSeconds = (int)(getenv('EAN_WEBHOOK_MAX_AVG_SECONDS') ?: 60);
$webhookMaxFailureRatePercent = (float)(getenv('EAN_WEBHOOK_MAX_FAILURE_RATE_PERCENT') ?: 10);
$webhookMaxPendingCount = (int)(getenv('EAN_WEBHOOK_MAX_PENDING_COUNT') ?: 20);
$autoRunbookEnabled = filter_var(getenv('EAN_AUTO_RUNBOOK_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN);
$autoRunbookCooldownSeconds = (int)(getenv('EAN_AUTO_RUNBOOK_COOLDOWN_SECONDS') ?: 600);
$escalationWindowMinutes = (int)(getenv('EAN_ESCALATION_WINDOW_MINUTES') ?: 60);
$predictiveRunbookEnabled = filter_var(getenv('EAN_PREDICTIVE_RUNBOOK_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN);
$predictiveTrendHours = (int)(getenv('EAN_PREDICTIVE_TREND_HOURS') ?: 6);
$predictiveMaxDivergences = (int)(getenv('EAN_PREDICTIVE_MAX_DIVERGENCES') ?: 30);
$predictiveMaxAvgSeconds = (int)(getenv('EAN_PREDICTIVE_MAX_AVG_SECONDS') ?: 75);
$predictiveMaxFailureRatePercent = (float)(getenv('EAN_PREDICTIVE_MAX_FAILURE_RATE_PERCENT') ?: 12);
$circuitBreakerEnabled = filter_var(getenv('EAN_CIRCUIT_BREAKER_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN);
$circuitBreakerThresholdCycles = (int)(getenv('EAN_CIRCUIT_BREAKER_THRESHOLD_CYCLES') ?: 3);
$circuitBreakerOpenMinutes = (int)(getenv('EAN_CIRCUIT_BREAKER_OPEN_MINUTES') ?: 15);
$staleMinutes = max(1, min(240, $staleMinutes));
$maxDivergences = max(0, min(10000, $maxDivergences));
$webhookSlaHoursBack = max(1, min(24, $webhookSlaHoursBack));
$webhookMaxAvgSeconds = max(1, min(3600, $webhookMaxAvgSeconds));
$webhookMaxFailureRatePercent = max(0, min(100, $webhookMaxFailureRatePercent));
$webhookMaxPendingCount = max(0, min(5000, $webhookMaxPendingCount));
$autoRunbookCooldownSeconds = max(0, min(86400, $autoRunbookCooldownSeconds));
$escalationWindowMinutes = max(5, min(1440, $escalationWindowMinutes));
$predictiveTrendHours = max(1, min(72, $predictiveTrendHours));
$predictiveMaxDivergences = max(0, min(50000, $predictiveMaxDivergences));
$predictiveMaxAvgSeconds = max(1, min(3600, $predictiveMaxAvgSeconds));
$predictiveMaxFailureRatePercent = max(0, min(100, $predictiveMaxFailureRatePercent));
$circuitBreakerThresholdCycles = max(1, min(20, $circuitBreakerThresholdCycles));
$circuitBreakerOpenMinutes = max(1, min(240, $circuitBreakerOpenMinutes));

$service = new EanService();
$status = $service->getReconcileStatus();
$report = $service->getFinancialDivergenceReport(24, 200);
$inbox = new WebhookInboxService();
$webhookSla = $inbox->getProviderSlaMetrics('mercadopago', $webhookSlaHoursBack, 200);

$heartbeatUpdatedAt = (string)($status['worker_heartbeat']['updated_at'] ?? '');
$heartbeatTimestamp = $heartbeatUpdatedAt !== '' ? strtotime($heartbeatUpdatedAt) : false;
$heartbeatAgeMinutes = $heartbeatTimestamp ? (int)floor((time() - $heartbeatTimestamp) / 60) : null;

$issues = [];

if (!(bool)($status['credentials_configured'] ?? false)) {
    $issues[] = 'mercadopago_credentials_missing';
}

if ($heartbeatAgeMinutes === null || $heartbeatAgeMinutes > $staleMinutes) {
    $issues[] = 'worker_heartbeat_stale';
}

$totalDivergences = (int)($report['summary']['total_divergences'] ?? 0);
if ($totalDivergences > $maxDivergences) {
    $issues[] = 'divergence_threshold_exceeded';
}

$webhookAvgSeconds = (float)($webhookSla['summary']['avg_processing_seconds'] ?? 0.0);
$webhookFailureRate = (float)($webhookSla['summary']['failure_rate_percent'] ?? 0.0);
$webhookPendingCount = (int)($webhookSla['summary']['received_count'] ?? 0);

if ($webhookAvgSeconds > $webhookMaxAvgSeconds) {
    $issues[] = 'webhook_sla_avg_latency_exceeded';
}

if ($webhookFailureRate > $webhookMaxFailureRatePercent) {
    $issues[] = 'webhook_sla_failure_rate_exceeded';
}

if ($webhookPendingCount > $webhookMaxPendingCount) {
    $issues[] = 'webhook_sla_pending_backlog_exceeded';
}

$predictive = $service->getOperationalTimeseriesTrend($predictiveTrendHours, 500);
$predictiveRisk = [
    'enabled' => $predictiveRunbookEnabled,
    'available' => (bool)($predictive['available'] ?? false),
    'should_trigger' => false,
    'reasons' => [],
    'trend_hours' => $predictiveTrendHours,
    'projection' => $predictive['projection_next_window'] ?? null,
    'thresholds' => [
        'max_divergences' => $predictiveMaxDivergences,
        'max_avg_seconds' => $predictiveMaxAvgSeconds,
        'max_failure_rate_percent' => $predictiveMaxFailureRatePercent,
    ],
];

if ($predictiveRisk['available']) {
    $projection = is_array($predictiveRisk['projection']) ? $predictiveRisk['projection'] : [];
    $projectedDivergences = (int)($projection['total_divergences'] ?? 0);
    $projectedAvgSeconds = (float)($projection['webhook_avg_processing_seconds'] ?? 0.0);
    $projectedFailureRate = (float)($projection['webhook_failure_rate_percent'] ?? 0.0);

    if ($projectedDivergences > $predictiveMaxDivergences) {
        $predictiveRisk['should_trigger'] = true;
        $predictiveRisk['reasons'][] = 'projected_divergences_exceeded';
        $issues[] = 'predictive_divergence_risk';
    }

    if ($projectedAvgSeconds > $predictiveMaxAvgSeconds) {
        $predictiveRisk['should_trigger'] = true;
        $predictiveRisk['reasons'][] = 'projected_webhook_avg_seconds_exceeded';
        $issues[] = 'predictive_webhook_sla_risk';
    }

    if ($projectedFailureRate > $predictiveMaxFailureRatePercent) {
        $predictiveRisk['should_trigger'] = true;
        $predictiveRisk['reasons'][] = 'projected_webhook_failure_rate_exceeded';
        $issues[] = 'predictive_webhook_sla_risk';
    }
}

$issues = array_values(array_unique($issues));

$criticalTrigger = in_array('worker_heartbeat_stale', $issues, true)
    || in_array('webhook_sla_failure_rate_exceeded', $issues, true)
    || in_array('webhook_sla_avg_latency_exceeded', $issues, true)
    || in_array('divergence_threshold_exceeded', $issues, true);

$circuitBreaker = $service->getOperationalCircuitBreakerStatus();
if ($circuitBreakerEnabled) {
    $circuitBreaker = $service->evaluateOperationalCircuitBreaker([
        'threshold_cycles' => $circuitBreakerThresholdCycles,
        'open_minutes' => $circuitBreakerOpenMinutes,
        'predictive_trigger' => (bool)$predictiveRisk['should_trigger'],
        'critical_trigger' => $criticalTrigger,
    ]);
}

$circuitBreakerOpen = (string)($circuitBreaker['state'] ?? 'closed') === 'open';

$alertSeverity = 'warning';
if (
    in_array('mercadopago_credentials_missing', $issues, true)
    || in_array('worker_heartbeat_stale', $issues, true)
    || $webhookFailureRate > ($webhookMaxFailureRatePercent * 2)
    || $webhookAvgSeconds > ($webhookMaxAvgSeconds * 2)
) {
    $alertSeverity = 'critical';
}

if (!empty($issues)) {
    $escalation = $service->evaluateOperationalEscalation($issues, $escalationWindowMinutes);
    $effectiveSeverity = (string)($escalation['severity'] ?? $alertSeverity);

    $service->storeOperationalAlert(
        'ean_reconcile_health_check',
        $effectiveSeverity,
        'Health-check detectou degradação operacional no pipeline EAN/MP/ML',
        [
            'issues' => $issues,
            'heartbeat_age_minutes' => $heartbeatAgeMinutes,
            'total_divergences' => $totalDivergences,
            'webhook_sla' => $webhookSla['summary'] ?? [],
            'escalation' => $escalation,
        ],
        300
    );
}

$runbook = null;
$shouldRunRunbook = $autoRunbookEnabled && (!empty($issues) || ($predictiveRunbookEnabled && (bool)$predictiveRisk['should_trigger']));
if ($shouldRunRunbook) {
    $escalation = $service->evaluateOperationalEscalation($issues, $escalationWindowMinutes);
    $escalationLevel = (int)($escalation['level'] ?? 0);

    $runbook = $service->executeOperationalRunbook($issues, [
        'source' => 'health_check_auto',
        'cooldown_seconds' => $autoRunbookCooldownSeconds,
        'escalation_level' => $escalationLevel,
        'circuit_breaker_open' => $circuitBreakerOpen,
        'retry_limit' => $escalationLevel >= 2 ? 220 : 120,
        'reconcile_limit' => $escalationLevel >= 2 ? 260 : 140,
        'auto_heal_limit' => $escalationLevel >= 2 ? 320 : 200,
    ]);
}

$currentEscalationLevel = isset($escalation) && is_array($escalation) ? (int)($escalation['level'] ?? 0) : 0;
$service->storeOperationalTimeseriesPoint([
    'captured_at' => date('c'),
    'heartbeat_age_minutes' => $heartbeatAgeMinutes ?? -1,
    'total_divergences' => $totalDivergences,
    'pending_purchases' => (int)($status['pending_purchases'] ?? -1),
    'failed_webhook_events' => (int)($status['failed_webhook_events'] ?? -1),
    'webhook_avg_processing_seconds' => $webhookAvgSeconds,
    'webhook_failure_rate_percent' => $webhookFailureRate,
    'webhook_pending_count' => $webhookPendingCount,
    'escalation_level' => $currentEscalationLevel,
    'issues_count' => count($issues),
    'ok' => count($issues) === 0,
]);

$logPayload = [
    'checked_at' => date('c'),
    'ok' => count($issues) === 0,
    'issues' => $issues,
    'heartbeat_age_minutes' => $heartbeatAgeMinutes,
    'stale_minutes_threshold' => $staleMinutes,
    'total_divergences' => $totalDivergences,
    'divergence_threshold' => $maxDivergences,
    'pending_purchases' => (int)($status['pending_purchases'] ?? -1),
    'failed_webhook_events' => (int)($status['failed_webhook_events'] ?? -1),
    'webhook_sla' => [
        'hours_back' => $webhookSlaHoursBack,
        'avg_processing_seconds' => $webhookAvgSeconds,
        'avg_processing_seconds_threshold' => $webhookMaxAvgSeconds,
        'failure_rate_percent' => $webhookFailureRate,
        'failure_rate_percent_threshold' => $webhookMaxFailureRatePercent,
        'pending_count' => $webhookPendingCount,
        'pending_count_threshold' => $webhookMaxPendingCount,
    ],
    'auto_runbook' => [
        'enabled' => $autoRunbookEnabled,
        'cooldown_seconds' => $autoRunbookCooldownSeconds,
        'should_run' => $shouldRunRunbook,
        'result' => $runbook,
    ],
    'circuit_breaker' => [
        'enabled' => $circuitBreakerEnabled,
        'state' => (string)($circuitBreaker['state'] ?? 'unknown'),
        'threshold_cycles' => $circuitBreakerThresholdCycles,
        'open_minutes' => $circuitBreakerOpenMinutes,
        'status' => $circuitBreaker,
    ],
    'escalation' => isset($escalation) ? $escalation : null,
    'predictive' => $predictiveRisk,
    'timeseries_point_saved' => true,
];

$logFile = __DIR__ . '/../storage/logs/ean-reconcile-health.log';
$line = '[' . date('Y-m-d H:i:s') . '] ' . json_encode($logPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
@file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

echo $line;
exit(count($issues) === 0 ? 0 : 2);
