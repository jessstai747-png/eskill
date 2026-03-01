<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Serviço de monitoramento do Catalog Clone
 *
 * Monitora métricas em tempo real, alertas e performance
 * do sistema de clonagem de catálogo.
 */
class CatalogCloneMonitoringService
{
    private PDO $db;
    private const DEFAULT_WEBHOOK_FAILED_THRESHOLD = 20;
    private const DEFAULT_WEBHOOK_OLDEST_FAILED_MINUTES_THRESHOLD = 30;
    private const DEFAULT_JOB_RETRY_PENDING_THRESHOLD = 120;
    private const DEFAULT_JOB_RECLAIMED_HOURLY_THRESHOLD = 10;
    private const DEFAULT_JOB_STALE_PROCESSING_THRESHOLD = 5;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obter métricas em tempo real
     */
    public function getRealTimeMetrics(): array
    {
        $cloneJobMetrics = $this->getCloneJobMetrics();

        $metrics = [
            'system' => $this->getSystemMetrics(),
            'basic_stats' => $this->buildBasicStats($cloneJobMetrics),
            'clone_jobs' => $cloneJobMetrics,
            'ml_operations' => $this->getMlOperationalMetrics(),
            'errors' => $this->getRecentErrors(),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        return $metrics;
    }

    /**
     * Verificar alertas ativos
     */
    public function checkAlerts(): array
    {
        $alerts = [];

        // Verificar taxa de erros
        $errorRate = $this->getErrorRate();
        if ($errorRate > 10) {
            $alerts[] = [
                'type' => 'error_rate',
                'severity' => 'CRITICAL',
                'message' => "Taxa de erros alta: {$errorRate}%",
                'value' => $errorRate,
            ];
        }

        // Verificar jobs pendentes
        $pendingJobs = $this->getPendingJobCount();
        if ($pendingJobs > 100) {
            $alerts[] = [
                'type' => 'queue_backlog',
                'severity' => 'WARNING',
                'message' => "{$pendingJobs} jobs pendentes na fila",
                'value' => $pendingJobs,
            ];
        }

        // Verificar último job executado
        $lastJob = $this->getLastJobTime();
        if ($lastJob && strtotime($lastJob) < strtotime('-1 hour')) {
            $alerts[] = [
                'type' => 'stale_jobs',
                'severity' => 'WARNING',
                'message' => 'Nenhum job executado na última hora',
                'last_run' => $lastJob,
            ];
        }

        // Alertas operacionais de integração Mercado Livre (fila + webhook)
        $mlOperational = $this->getMlOperationalMetrics();
        $webhookFailedThreshold = $this->getIntEnv(
            'ML_MONITOR_WEBHOOK_FAILED_THRESHOLD',
            self::DEFAULT_WEBHOOK_FAILED_THRESHOLD,
            1,
            100000
        );
        $webhookOldestThresholdMinutes = $this->getIntEnv(
            'ML_MONITOR_WEBHOOK_OLDEST_FAILED_MINUTES_THRESHOLD',
            self::DEFAULT_WEBHOOK_OLDEST_FAILED_MINUTES_THRESHOLD,
            1,
            10080
        );
        $retryPendingThreshold = $this->getIntEnv(
            'ML_MONITOR_JOB_RETRY_PENDING_THRESHOLD',
            self::DEFAULT_JOB_RETRY_PENDING_THRESHOLD,
            1,
            100000
        );
        $reclaimedHourlyThreshold = $this->getIntEnv(
            'ML_MONITOR_JOB_RECLAIMED_HOURLY_THRESHOLD',
            self::DEFAULT_JOB_RECLAIMED_HOURLY_THRESHOLD,
            1,
            100000
        );
        $staleProcessingThreshold = $this->getIntEnv(
            'ML_MONITOR_STALE_PROCESSING_THRESHOLD',
            self::DEFAULT_JOB_STALE_PROCESSING_THRESHOLD,
            1,
            100000
        );

        $failedBacklog = (int)($mlOperational['failed_webhook_backlog'] ?? 0);
        if ($failedBacklog >= $webhookFailedThreshold) {
            $alerts[] = [
                'type' => 'ml_webhook_failed_backlog',
                'severity' => $failedBacklog >= ($webhookFailedThreshold * 2) ? 'CRITICAL' : 'HIGH',
                'message' => "Backlog de webhooks ML falhos: {$failedBacklog}",
                'value' => $failedBacklog,
                'threshold' => $webhookFailedThreshold,
            ];
        }

        $oldestFailedMinutes = (int)($mlOperational['oldest_failed_webhook_minutes'] ?? 0);
        if ($oldestFailedMinutes >= $webhookOldestThresholdMinutes) {
            $alerts[] = [
                'type' => 'ml_webhook_failed_stale',
                'severity' => $oldestFailedMinutes >= ($webhookOldestThresholdMinutes * 2) ? 'CRITICAL' : 'HIGH',
                'message' => "Webhook ML falho mais antigo com {$oldestFailedMinutes} min",
                'value' => $oldestFailedMinutes,
                'threshold' => $webhookOldestThresholdMinutes,
            ];
        }

        $retryPending = (int)($mlOperational['jobs_retry_pending'] ?? 0);
        if ($retryPending >= $retryPendingThreshold) {
            $alerts[] = [
                'type' => 'ml_job_retry_backlog',
                'severity' => $retryPending >= ($retryPendingThreshold * 2) ? 'HIGH' : 'WARNING',
                'message' => "Jobs aguardando retry: {$retryPending}",
                'value' => $retryPending,
                'threshold' => $retryPendingThreshold,
            ];
        }

        $reclaimedLastHour = (int)($mlOperational['jobs_reclaimed_last_hour'] ?? 0);
        if ($reclaimedLastHour >= $reclaimedHourlyThreshold) {
            $alerts[] = [
                'type' => 'ml_job_reclaimed_spike',
                'severity' => $reclaimedLastHour >= ($reclaimedHourlyThreshold * 2) ? 'HIGH' : 'WARNING',
                'message' => "Reclaims de jobs na última hora: {$reclaimedLastHour}",
                'value' => $reclaimedLastHour,
                'threshold' => $reclaimedHourlyThreshold,
            ];
        }

        $staleProcessing = (int)($mlOperational['jobs_processing_stale'] ?? 0);
        if ($staleProcessing >= $staleProcessingThreshold) {
            $alerts[] = [
                'type' => 'ml_job_processing_stale',
                'severity' => 'CRITICAL',
                'message' => "Jobs presos em processing (stale): {$staleProcessing}",
                'value' => $staleProcessing,
                'threshold' => $staleProcessingThreshold,
            ];
        }

        return $alerts;
    }

    /**
     * Relatório de performance por período
     */
    public function getPerformanceReport(string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE(created_at) as date,
                    COUNT(*) as total_jobs,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_duration
             FROM catalog_clone_jobs
             WHERE created_at BETWEEN :start AND :end
             GROUP BY DATE(created_at)
             ORDER BY date"
        );
        $stmt->execute([
            'start' => $startDate . ' 00:00:00',
            'end' => $endDate . ' 23:59:59',
        ]);

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'daily_stats' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    private function getSystemMetrics(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'uptime' => $this->getUptime(),
        ];
    }

    private function getCloneJobMetrics(): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT status, COUNT(*) as count FROM catalog_clone_jobs
                 GROUP BY status"
            );
            $metrics = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $metrics[$row['status']] = (int) $row['count'];
            }
            return $metrics;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getRecentErrors(): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT * FROM error_monitoring
                 ORDER BY created_at DESC LIMIT 10"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getErrorRate(): float
    {
        try {
            $stmt = $this->db->query(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                 FROM catalog_clone_jobs
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['total'] == 0) {
                return 0;
            }
            return round(($row['failed'] / $row['total']) * 100, 1);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getPendingJobCount(): int
    {
        try {
            return (int) $this->db->query(
                "SELECT COUNT(*) FROM catalog_clone_jobs
                 WHERE status = 'pending'"
            )->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getLastJobTime(): ?string
    {
        try {
            $result = $this->db->query(
                "SELECT MAX(updated_at) FROM catalog_clone_jobs
                 WHERE status = 'completed'"
            )->fetchColumn();
            return $result ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getUptime(): string
    {
        if (file_exists('/proc/uptime')) {
            $uptime = (float) file_get_contents('/proc/uptime');
            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            return "{$days}d {$hours}h";
        }
        return 'N/A';
    }

    private function buildBasicStats(array $cloneJobMetrics): array
    {
        if (isset($cloneJobMetrics['error']) && count($cloneJobMetrics) === 1) {
            return [
                'pending_jobs' => 0,
                'completed_jobs' => 0,
                'failed_jobs' => 0,
                'total_jobs' => 0,
                'success_rate' => 0.0,
                'error' => (string)$cloneJobMetrics['error'],
            ];
        }

        $pending = (int)($cloneJobMetrics['pending'] ?? 0);
        $completed = (int)($cloneJobMetrics['completed'] ?? 0);
        $failed = (int)($cloneJobMetrics['failed'] ?? 0);
        $total = 0;

        foreach ($cloneJobMetrics as $count) {
            if (is_numeric($count)) {
                $total += (int)$count;
            }
        }

        $finished = $completed + $failed;
        $successRate = $finished > 0 ? round(($completed / $finished) * 100, 1) : 100.0;

        return [
            'pending_jobs' => $pending,
            'completed_jobs' => $completed,
            'failed_jobs' => $failed,
            'total_jobs' => $total,
            'success_rate' => $successRate,
        ];
    }

    private function getMlOperationalMetrics(): array
    {
        $staleSeconds = $this->getIntEnv('JOB_STALE_PROCESSING_SECONDS', 900, 60, 86400);

        return [
            'failed_webhook_backlog' => $this->safeCount(
                "SELECT COUNT(*) FROM webhook_event_inbox WHERE provider = 'mercadolivre' AND status = 'failed'"
            ),
            'oldest_failed_webhook_minutes' => $this->safeSingleInt(
                "SELECT COALESCE(TIMESTAMPDIFF(MINUTE, MIN(received_at), NOW()), 0)
                 FROM webhook_event_inbox
                 WHERE provider = 'mercadolivre' AND status = 'failed'"
            ),
            'jobs_retry_pending' => $this->safeCount(
                "SELECT COUNT(*) FROM jobs WHERE status = 'pending' AND next_attempt_at IS NOT NULL"
            ),
            'jobs_reclaimed_last_hour' => $this->safeCount(
                "SELECT COUNT(*) FROM jobs
                 WHERE status = 'pending'
                   AND error_message LIKE 'Job reclaimado após timeout%'
                   AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            ),
            'jobs_processing_stale' => $this->safeCount(
                "SELECT COUNT(*) FROM jobs
                 WHERE status = 'processing'
                   AND claimed_at IS NOT NULL
                   AND claimed_at < DATE_SUB(NOW(), INTERVAL {$staleSeconds} SECOND)"
            ),
        ];
    }

    private function safeCount(string $sql): int
    {
        try {
            $value = $this->db->query($sql)->fetchColumn();
            return $value !== false ? (int)$value : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function safeSingleInt(string $sql): int
    {
        try {
            $value = $this->db->query($sql)->fetchColumn();
            return $value !== false ? (int)$value : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function getIntEnv(string $key, int $default, int $min, int $max): int
    {
        $raw = $_ENV[$key] ?? getenv($key);
        $value = is_numeric($raw) ? (int)$raw : $default;
        return max($min, min($max, $value));
    }
}
