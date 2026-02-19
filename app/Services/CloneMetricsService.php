<?php

namespace App\Services;

use App\Database;
use Exception;

/**
 * CloneMetricsService
 * 
 * Coleta, agrega e fornece métricas de clonagem para observabilidade
 */
class CloneMetricsService
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Registra uma métrica de clone
     */
    public function recordMetric(
        string $metricName,
        float $value,
        ?int $accountId = null,
        ?string $categoryId = null,
        ?string $templateSlug = null,
        ?array $dimensions = null
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO clone_metrics 
            (metric_name, metric_value, account_id, category_id, template_slug, dimensions, created_at)
            VALUES (:name, :value, :account, :category, :template, :dimensions, NOW())
        ");

        $stmt->execute([
            'name' => $metricName,
            'value' => $value,
            'account' => $accountId,
            'category' => $categoryId,
            'template' => $templateSlug,
            'dimensions' => $dimensions ? json_encode($dimensions) : null,
        ]);
    }

    /**
     * Registra métricas de um job completo
     */
    public function recordJobMetrics(array $jobData): void
    {
        $jobId = $jobData['job_id'];
        $accountId = $jobData['target_account_id'] ?? null;
        $templateSlug = $jobData['template_slug'] ?? null;

        // Métricas básicas do job
        $this->recordMetric('job.total_items', (float)($jobData['total_items'] ?? 0), $accountId, null, $templateSlug);
        $this->recordMetric('job.successful_items', (float)($jobData['successful_items'] ?? 0), $accountId, null, $templateSlug);
        $this->recordMetric('job.failed_items', (float)($jobData['failed_items'] ?? 0), $accountId, null, $templateSlug);

        // Calcular taxa de sucesso
        $total = $jobData['total_items'] ?? 0;
        $successful = $jobData['successful_items'] ?? 0;
        if ($total > 0) {
            $successRate = ($successful / $total) * 100;
            $this->recordMetric('job.success_rate', $successRate, $accountId, null, $templateSlug);
        }

        // Duração do job
        if (isset($jobData['started_at']) && isset($jobData['completed_at'])) {
            $start = strtotime($jobData['started_at']);
            $end = strtotime($jobData['completed_at']);
            $duration = $end - $start;
            $this->recordMetric('job.duration_seconds', (float)$duration, $accountId, null, $templateSlug);

            // Velocidade (itens por minuto)
            if ($duration > 0 && $total > 0) {
                $speed = ($total / $duration) * 60;
                $this->recordMetric('job.items_per_minute', $speed, $accountId, null, $templateSlug);
            }
        }
    }

    /**
     * Registra métricas de um item clonado
     */
    public function recordItemMetrics(
        string $sourceItemId,
        string $targetItemId,
        int $accountId,
        ?string $categoryId = null,
        ?string $templateSlug = null,
        array $cloneDetails = []
    ): void {
        // Clone realizado
        $this->recordMetric('item.cloned', 1, $accountId, $categoryId, $templateSlug, [
            'source' => $sourceItemId,
            'target' => $targetItemId,
        ]);

        // Diferença de preço se disponível
        if (isset($cloneDetails['source_price']) && isset($cloneDetails['target_price'])) {
            $priceDiff = $cloneDetails['target_price'] - $cloneDetails['source_price'];
            $pricePercent = $cloneDetails['source_price'] > 0 
                ? ($priceDiff / $cloneDetails['source_price']) * 100 
                : 0;
            $this->recordMetric('item.price_difference_percent', $pricePercent, $accountId, $categoryId, $templateSlug);
        }
    }

    /**
     * Obtém dashboard de métricas agregadas
     */
    public function getDashboard(?int $accountId = null, int $days = 30): array
    {
        $whereAccount = $accountId ? "AND account_id = :account_id" : "";
        $params = ['days' => $days];
        if ($accountId) {
            $params['account_id'] = $accountId;
        }

        // Totais do período
        $totals = $this->getTotals($accountId, $days);

        // Série temporal (por dia)
        $timeSeries = $this->getTimeSeries($accountId, $days);

        // Por categoria (top 10)
        $byCategory = $this->getByCategory($accountId, $days, 10);

        // Por template
        $byTemplate = $this->getByTemplate($accountId, $days);

        // Tendência de sucesso
        $successTrend = $this->getSuccessTrend($accountId, $days);

        return [
            'period' => [
                'days' => $days,
                'start' => date('Y-m-d', strtotime("-{$days} days")),
                'end' => date('Y-m-d'),
            ],
            'totals' => $totals,
            'time_series' => $timeSeries,
            'by_category' => $byCategory,
            'by_template' => $byTemplate,
            'success_trend' => $successTrend,
        ];
    }

    /**
     * Obtém totais agregados
     */
    private function getTotals(?int $accountId, int $days): array
    {
        $accountFilter = $accountId ? "AND account_id = :account_id" : "";

        // Total de itens clonados
        $sql = "
            SELECT COALESCE(SUM(metric_value), 0) as total
            FROM clone_metrics
            WHERE metric_name = 'item.cloned'
            AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            {$accountFilter}
        ";

        $stmt = $this->db->prepare($sql);
        $params = ['days' => $days];
        if ($accountId) {
            $params['account_id'] = $accountId;
        }
        $stmt->execute($params);
        $totalCloned = (int) $stmt->fetchColumn();

        // Jobs executados
        $sql = "
            SELECT COUNT(DISTINCT dimensions->>'$.job_id') as total
            FROM clone_metrics
            WHERE metric_name = 'job.total_items'
            AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            {$accountFilter}
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $totalJobs = (int) $stmt->fetchColumn();

        // Taxa de sucesso média
        $sql = "
            SELECT COALESCE(AVG(metric_value), 0) as avg_rate
            FROM clone_metrics
            WHERE metric_name = 'job.success_rate'
            AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            {$accountFilter}
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $avgSuccessRate = round((float) $stmt->fetchColumn(), 2);

        // Velocidade média (itens/minuto)
        $sql = "
            SELECT COALESCE(AVG(metric_value), 0) as avg_speed
            FROM clone_metrics
            WHERE metric_name = 'job.items_per_minute'
            AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            {$accountFilter}
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $avgSpeed = round((float) $stmt->fetchColumn(), 2);

        return [
            'total_cloned' => $totalCloned,
            'total_jobs' => $totalJobs,
            'avg_success_rate' => $avgSuccessRate,
            'avg_items_per_minute' => $avgSpeed,
        ];
    }

    /**
     * Obtém série temporal de clones por dia
     */
    private function getTimeSeries(?int $accountId, int $days): array
    {
        $accountFilter = $accountId ? "AND account_id = :account_id" : "";

        $sql = "
            SELECT 
                DATE(created_at) as date,
                SUM(metric_value) as count
            FROM clone_metrics
            WHERE metric_name = 'item.cloned'
            AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            {$accountFilter}
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ";

        $stmt = $this->db->prepare($sql);
        $params = ['days' => $days];
        if ($accountId) {
            $params['account_id'] = $accountId;
        }
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtém clones por categoria
     */
    private function getByCategory(?int $accountId, int $days, int $limit = 10): array
    {
        $accountFilter = $accountId ? "AND account_id = :account_id" : "";

        $limitSql = max(1, min((int)$limit, 200));

        $sql = "
            SELECT 
                category_id,
                SUM(metric_value) as count
            FROM clone_metrics
            WHERE metric_name = 'item.cloned'
            AND category_id IS NOT NULL
            AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            {$accountFilter}
            GROUP BY category_id
            ORDER BY count DESC
            LIMIT {$limitSql}
        ";

        $stmt = $this->db->prepare($sql);
        $params = ['days' => $days];
        if ($accountId) {
            $params['account_id'] = $accountId;
        }
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        if ($accountId) {
            $stmt->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtém clones por template
     */
    private function getByTemplate(?int $accountId, int $days): array
    {
        $accountFilter = $accountId ? "AND account_id = :account_id" : "";

        $sql = "
            SELECT 
                COALESCE(template_slug, 'sem_template') as template,
                SUM(metric_value) as count
            FROM clone_metrics
            WHERE metric_name = 'item.cloned'
            AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            {$accountFilter}
            GROUP BY template_slug
            ORDER BY count DESC
        ";

        $stmt = $this->db->prepare($sql);
        $params = ['days' => $days];
        if ($accountId) {
            $params['account_id'] = $accountId;
        }
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtém tendência de taxa de sucesso
     */
    private function getSuccessTrend(?int $accountId, int $days): array
    {
        $accountFilter = $accountId ? "AND account_id = :account_id" : "";

        $sql = "
            SELECT 
                DATE(created_at) as date,
                AVG(metric_value) as avg_rate
            FROM clone_metrics
            WHERE metric_name = 'job.success_rate'
            AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            {$accountFilter}
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ";

        $stmt = $this->db->prepare($sql);
        $params = ['days' => $days];
        if ($accountId) {
            $params['account_id'] = $accountId;
        }
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtém métricas de jobs recentes
     */
    public function getRecentJobsMetrics(?int $accountId = null, int $limit = 10): array
    {
        $accountFilter = $accountId ? "AND target_account_id = :account_id" : "";

        $limitSql = max(1, min((int)$limit, 200));

        $sql = "
            SELECT 
                job_id,
                target_account_id,
                template_slug,
                total_items,
                successful_items,
                failed_items,
                started_at,
                completed_at,
                TIMESTAMPDIFF(SECOND, started_at, completed_at) as duration_seconds,
                ROUND((successful_items / NULLIF(total_items, 0)) * 100, 2) as success_rate
            FROM catalog_clone_jobs
            WHERE status IN ('completed', 'completed_with_errors')
            {$accountFilter}
            ORDER BY completed_at DESC
            LIMIT {$limitSql}
        ";

        $stmt = $this->db->prepare($sql);
        if ($accountId) {
            $stmt->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtém métricas de erros mais frequentes
     */
    public function getTopErrors(?int $accountId = null, int $days = 30, int $limit = 10): array
    {
        $accountFilter = $accountId ? "AND ccj.target_account_id = :account_id" : "";

        $limitSql = max(1, min((int)$limit, 200));

        $sql = "
            SELECT 
                ccji.error_message,
                COUNT(*) as count,
                MAX(ccji.updated_at) as last_occurrence
            FROM catalog_clone_job_items ccji
            JOIN catalog_clone_jobs ccj ON ccji.job_id = ccj.job_id
            WHERE ccji.status = 'failed'
            AND ccji.error_message IS NOT NULL
            AND ccji.updated_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            {$accountFilter}
            GROUP BY ccji.error_message
            ORDER BY count DESC
            LIMIT {$limitSql}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        if ($accountId) {
            $stmt->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtém comparativo semanal
     */
    public function getWeeklyComparison(?int $accountId = null): array
    {
        $accountFilter = $accountId ? "AND account_id = :account_id" : "";

        // Semana atual
        $sqlCurrent = "
            SELECT COALESCE(SUM(metric_value), 0) as total
            FROM clone_metrics
            WHERE metric_name = 'item.cloned'
            AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)
            {$accountFilter}
        ";

        // Semana passada
        $sqlPrevious = "
            SELECT COALESCE(SUM(metric_value), 0) as total
            FROM clone_metrics
            WHERE metric_name = 'item.cloned'
            AND YEARWEEK(created_at, 1) = YEARWEEK(DATE_SUB(NOW(), INTERVAL 1 WEEK), 1)
            {$accountFilter}
        ";

        $stmt = $this->db->prepare($sqlCurrent);
        $params = $accountId ? ['account_id' => $accountId] : [];
        $stmt->execute($params);
        $currentWeek = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare($sqlPrevious);
        $stmt->execute($params);
        $previousWeek = (int) $stmt->fetchColumn();

        $change = $previousWeek > 0 
            ? round((($currentWeek - $previousWeek) / $previousWeek) * 100, 2)
            : ($currentWeek > 0 ? 100 : 0);

        return [
            'current_week' => $currentWeek,
            'previous_week' => $previousWeek,
            'change_percent' => $change,
            'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable'),
        ];
    }
}
