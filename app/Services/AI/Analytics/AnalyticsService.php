<?php

declare(strict_types=1);

namespace App\Services\AI\Analytics;

use App\Database;
use PDO;

/**
 * AnalyticsService
 *
 * Provides dashboard metrics, executive summary and cost breakdown
 * for the AI Optimization center, reading from ai_logs and ai_metrics tables.
 */
class AnalyticsService
{
    private ?PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getDashboardMetrics(int $days = 30): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $totalRequests = $this->scalar(
            "SELECT COUNT(*) FROM ai_logs WHERE created_at >= ?",
            [$since]
        );
        $totalTokens = $this->scalar(
            "SELECT COALESCE(SUM(tokens_used),0) FROM ai_logs WHERE created_at >= ?",
            [$since]
        );
        $totalCost = $this->scalar(
            "SELECT COALESCE(SUM(cost),0) FROM ai_logs WHERE created_at >= ?",
            [$since]
        );
        $errorCount = $this->scalar(
            "SELECT COUNT(*) FROM ai_logs WHERE level='error' AND created_at >= ?",
            [$since]
        );

        $byProvider = $this->query(
            "SELECT ai_provider, COUNT(*) AS requests,
                    COALESCE(SUM(tokens_used),0) AS tokens,
                    COALESCE(SUM(cost),0) AS cost
             FROM ai_logs WHERE created_at >= ? AND ai_provider IS NOT NULL
             GROUP BY ai_provider ORDER BY requests DESC",
            [$since]
        );

        return [
            'period_days'     => $days,
            'total_requests'  => (int) $totalRequests,
            'total_tokens'    => (int) $totalTokens,
            'total_cost'      => (float) $totalCost,
            'error_count'     => (int) $errorCount,
            'error_rate'      => $totalRequests > 0
                ? round(($errorCount / $totalRequests) * 100, 2)
                : 0.0,
            'by_provider'     => $byProvider,
        ];
    }

    public function getExecutiveSummary(int $days = 30): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $optimizations = $this->scalar(
            "SELECT COUNT(*) FROM ai_logs
             WHERE category='optimization' AND level='info' AND created_at >= ?",
            [$since]
        );
        $avgDuration = $this->scalar(
            "SELECT COALESCE(AVG(duration_ms),0) FROM ai_logs WHERE created_at >= ? AND duration_ms IS NOT NULL",
            [$since]
        );

        $topActions = $this->query(
            "SELECT action, COUNT(*) AS count
             FROM ai_logs WHERE created_at >= ?
             GROUP BY action ORDER BY count DESC LIMIT 5",
            [$since]
        );

        return [
            'period_days'         => $days,
            'total_optimizations' => (int) $optimizations,
            'avg_duration_ms'     => (float) $avgDuration,
            'top_actions'         => $topActions,
            'generated_at'        => date('Y-m-d H:i:s'),
        ];
    }

    public function getCostBreakdown(int $days = 30): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $daily = $this->query(
            "SELECT DATE(created_at) AS day,
                    COALESCE(SUM(cost),0) AS cost,
                    COALESCE(SUM(tokens_used),0) AS tokens,
                    COUNT(*) AS requests
             FROM ai_logs WHERE created_at >= ?
             GROUP BY DATE(created_at) ORDER BY day ASC",
            [$since]
        );

        $byModel = $this->query(
            "SELECT ai_model, COALESCE(SUM(cost),0) AS cost,
                    COALESCE(SUM(tokens_used),0) AS tokens, COUNT(*) AS requests
             FROM ai_logs WHERE created_at >= ? AND ai_model IS NOT NULL
             GROUP BY ai_model ORDER BY cost DESC",
            [$since]
        );

        $totalCost = (float) $this->scalar(
            "SELECT COALESCE(SUM(cost),0) FROM ai_logs WHERE created_at >= ?",
            [$since]
        );

        return [
            'period_days' => $days,
            'total_cost'  => $totalCost,
            'daily'       => $daily,
            'by_model'    => $byModel,
        ];
    }

    private function scalar(string $sql, array $params = []): mixed
    {
        if (!$this->db) {
            return 0;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function query(string $sql, array $params = []): array
    {
        if (!$this->db) {
            return [];
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
