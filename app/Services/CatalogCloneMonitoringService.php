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

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obter métricas em tempo real
     */
    public function getRealTimeMetrics(): array
    {
        $metrics = [
            'system' => $this->getSystemMetrics(),
            'clone_jobs' => $this->getCloneJobMetrics(),
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
                'severity' => 'critical',
                'message' => "Taxa de erros alta: {$errorRate}%",
                'value' => $errorRate,
            ];
        }

        // Verificar jobs pendentes
        $pendingJobs = $this->getPendingJobCount();
        if ($pendingJobs > 100) {
            $alerts[] = [
                'type' => 'queue_backlog',
                'severity' => 'warning',
                'message' => "{$pendingJobs} jobs pendentes na fila",
                'value' => $pendingJobs,
            ];
        }

        // Verificar último job executado
        $lastJob = $this->getLastJobTime();
        if ($lastJob && strtotime($lastJob) < strtotime('-1 hour')) {
            $alerts[] = [
                'type' => 'stale_jobs',
                'severity' => 'warning',
                'message' => 'Nenhum job executado na última hora',
                'last_run' => $lastJob,
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
                 WHERE status = 'failed'
                   AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
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
}
