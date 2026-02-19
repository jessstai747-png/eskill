<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use DateTimeImmutable;

/**
 * CloneAnalyticsService - Integração com Analytics Avançado
 * 
 * Fornece métricas avançadas, análises e insights sobre:
 * - Performance de clonagens ao longo do tempo
 * - Comparação de métricas entre períodos
 * - Análise de tendências
 * - KPIs e dashboards analíticos
 * - Previsões e projeções
 */
class CloneAnalyticsService
{
    private PDO $db;
    private ?int $accountId;

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * Obtém métricas consolidadas para dashboard analítico
     */
    public function getDashboardMetrics(string $period = '30d'): array
    {
        $dateFrom = $this->parsePeriod($period);
        
        return [
            'period' => $period,
            'generated_at' => date('Y-m-d H:i:s'),
            'kpis' => $this->getKPIs($dateFrom),
            'trends' => $this->getTrends($dateFrom),
            'performance' => $this->getPerformanceMetrics($dateFrom),
            'breakdown' => $this->getBreakdown($dateFrom),
        ];
    }

    /**
     * Obtém KPIs principais
     */
    public function getKPIs(string $dateFrom): array
    {
        $params = ['date_from' => $dateFrom];
        $accountFilter = '';

        if ($this->accountId) {
            $accountFilter = 'AND account_id = :account_id';
            $params['account_id'] = $this->accountId;
        }

        // Jobs e itens
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT job_id) as total_jobs,
                COUNT(*) as total_items,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_items,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_items,
                AVG(processing_time_seconds) as avg_processing_time
            FROM catalog_clone_items
            WHERE created_at >= :date_from
            {$accountFilter}
        ");
        $stmt->execute($params);
        $itemsData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calcular taxa de sucesso
        $totalItems = (int) ($itemsData['total_items'] ?? 0);
        $successfulItems = (int) ($itemsData['successful_items'] ?? 0);
        $successRate = $totalItems > 0 ? round(($successfulItems / $totalItems) * 100, 2) : 0;

        // Jobs por status
        $stmt = $this->db->prepare("
            SELECT 
                status,
                COUNT(*) as count
            FROM catalog_clone_jobs
            WHERE created_at >= :date_from
            {$accountFilter}
            GROUP BY status
        ");
        $stmt->execute($params);
        $jobsByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Tempo médio de conclusão de jobs
        $stmt = $this->db->prepare("
            SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_job_duration
            FROM catalog_clone_jobs
            WHERE created_at >= :date_from
            AND completed_at IS NOT NULL
            {$accountFilter}
        ");
        $stmt->execute($params);
        $avgDuration = (float) $stmt->fetchColumn();

        // Comparação com período anterior
        $prevDateFrom = $this->getComparisonPeriod($dateFrom);
        $params['prev_date_from'] = $prevDateFrom;
        $params['prev_date_to'] = $dateFrom;

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as prev_items
            FROM catalog_clone_items
            WHERE created_at >= :prev_date_from AND created_at < :prev_date_to
            {$accountFilter}
        ");
        $stmt->execute($params);
        $prevItems = (int) $stmt->fetchColumn();

        $growth = $prevItems > 0 
            ? round((($totalItems - $prevItems) / $prevItems) * 100, 2)
            : ($totalItems > 0 ? 100 : 0);

        return [
            'total_jobs' => (int) ($itemsData['total_jobs'] ?? 0),
            'total_items' => $totalItems,
            'successful_items' => $successfulItems,
            'failed_items' => (int) ($itemsData['failed_items'] ?? 0),
            'success_rate' => $successRate,
            'avg_processing_time' => round((float) ($itemsData['avg_processing_time'] ?? 0), 2),
            'avg_job_duration_seconds' => round($avgDuration, 2),
            'jobs_by_status' => $jobsByStatus,
            'growth_vs_previous' => $growth,
        ];
    }

    /**
     * Obtém tendências ao longo do tempo
     */
    public function getTrends(string $dateFrom): array
    {
        $params = ['date_from' => $dateFrom];
        $accountFilter = '';

        if ($this->accountId) {
            $accountFilter = 'AND account_id = :account_id';
            $params['account_id'] = $this->accountId;
        }

        // Tendência diária de itens clonados
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM catalog_clone_items
            WHERE created_at >= :date_from
            {$accountFilter}
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute($params);
        $dailyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tendência semanal
        $stmt = $this->db->prepare("
            SELECT 
                YEARWEEK(created_at, 1) as week,
                MIN(DATE(created_at)) as week_start,
                COUNT(*) as total,
                AVG(processing_time_seconds) as avg_time
            FROM catalog_clone_items
            WHERE created_at >= :date_from
            {$accountFilter}
            GROUP BY YEARWEEK(created_at, 1)
            ORDER BY week
        ");
        $stmt->execute($params);
        $weeklyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tendência por hora do dia
        $stmt = $this->db->prepare("
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as total
            FROM catalog_clone_items
            WHERE created_at >= :date_from
            {$accountFilter}
            GROUP BY HOUR(created_at)
            ORDER BY hour
        ");
        $stmt->execute($params);
        $hourlyDistribution = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Preencher horas faltantes
        $fullHourly = [];
        for ($h = 0; $h < 24; $h++) {
            $fullHourly[$h] = (int) ($hourlyDistribution[$h] ?? 0);
        }

        return [
            'daily' => $dailyTrend,
            'weekly' => $weeklyTrend,
            'hourly_distribution' => $fullHourly,
        ];
    }

    /**
     * Métricas de performance
     */
    public function getPerformanceMetrics(string $dateFrom): array
    {
        $params = ['date_from' => $dateFrom];
        $accountFilter = '';

        if ($this->accountId) {
            $accountFilter = 'AND account_id = :account_id';
            $params['account_id'] = $this->accountId;
        }

        // Distribuição de tempo de processamento
        $stmt = $this->db->prepare("
            SELECT 
                CASE 
                    WHEN processing_time_seconds < 5 THEN '0-5s'
                    WHEN processing_time_seconds < 15 THEN '5-15s'
                    WHEN processing_time_seconds < 30 THEN '15-30s'
                    WHEN processing_time_seconds < 60 THEN '30-60s'
                    ELSE '60s+'
                END as time_bucket,
                COUNT(*) as count
            FROM catalog_clone_items
            WHERE created_at >= :date_from
            AND processing_time_seconds IS NOT NULL
            {$accountFilter}
            GROUP BY time_bucket
            ORDER BY 
                CASE time_bucket
                    WHEN '0-5s' THEN 1
                    WHEN '5-15s' THEN 2
                    WHEN '15-30s' THEN 3
                    WHEN '30-60s' THEN 4
                    ELSE 5
                END
        ");
        $stmt->execute($params);
        $timeDistribution = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Taxa de erro por tipo
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(error_type, 'unknown') as error_type,
                COUNT(*) as count
            FROM catalog_clone_items
            WHERE created_at >= :date_from
            AND status = 'failed'
            {$accountFilter}
            GROUP BY error_type
            ORDER BY count DESC
            LIMIT 10
        ");
        $stmt->execute($params);
        $errorsByType = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Throughput por minuto (últimas 2 horas)
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:00') as minute,
                COUNT(*) as items_processed
            FROM catalog_clone_items
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
            {$accountFilter}
            GROUP BY minute
            ORDER BY minute
        ");
        $stmt->execute($this->accountId ? ['account_id' => $this->accountId] : []);
        $throughput = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // P50, P90, P99 de tempo de processamento
        $stmt = $this->db->prepare("
            SELECT processing_time_seconds
            FROM catalog_clone_items
            WHERE created_at >= :date_from
            AND processing_time_seconds IS NOT NULL
            {$accountFilter}
            ORDER BY processing_time_seconds
        ");
        $stmt->execute($params);
        $times = array_map('floatval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $percentiles = $this->calculatePercentiles($times, [50, 90, 99]);

        return [
            'time_distribution' => $timeDistribution,
            'errors_by_type' => $errorsByType,
            'throughput_last_2h' => $throughput,
            'percentiles' => [
                'p50' => $percentiles[50] ?? 0,
                'p90' => $percentiles[90] ?? 0,
                'p99' => $percentiles[99] ?? 0,
            ],
        ];
    }

    /**
     * Breakdown por diferentes dimensões
     */
    public function getBreakdown(string $dateFrom): array
    {
        $params = ['date_from' => $dateFrom];
        $accountFilter = '';

        if ($this->accountId) {
            $accountFilter = 'AND i.account_id = :account_id';
            $params['account_id'] = $this->accountId;
        }

        // Por categoria (top 10)
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(i.category_id, 'sem_categoria') as category,
                COUNT(*) as total,
                SUM(CASE WHEN i.status = 'completed' THEN 1 ELSE 0 END) as successful
            FROM catalog_clone_items i
            WHERE i.created_at >= :date_from
            {$accountFilter}
            GROUP BY category
            ORDER BY total DESC
            LIMIT 10
        ");
        $stmt->execute($params);
        $byCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Por seller de origem (top 10)
        $stmt = $this->db->prepare("
            SELECT 
                i.source_seller_id as seller_id,
                COUNT(*) as total,
                SUM(CASE WHEN i.status = 'completed' THEN 1 ELSE 0 END) as successful
            FROM catalog_clone_items i
            WHERE i.created_at >= :date_from
            AND i.source_seller_id IS NOT NULL
            {$accountFilter}
            GROUP BY seller_id
            ORDER BY total DESC
            LIMIT 10
        ");
        $stmt->execute($params);
        $bySeller = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Por usuário
        $stmt = $this->db->prepare("
            SELECT 
                j.user_id,
                u.name as user_name,
                COUNT(DISTINCT j.id) as jobs,
                COUNT(i.id) as items
            FROM catalog_clone_jobs j
            LEFT JOIN catalog_clone_items i ON j.id = i.job_id
            LEFT JOIN users u ON j.user_id = u.id
            WHERE j.created_at >= :date_from
            " . ($this->accountId ? 'AND j.account_id = :account_id' : '') . "
            GROUP BY j.user_id, u.name
            ORDER BY items DESC
            LIMIT 10
        ");
        $stmt->execute($params);
        $byUser = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'by_category' => $byCategory,
            'by_seller' => $bySeller,
            'by_user' => $byUser,
        ];
    }

    /**
     * Compara dois períodos
     */
    public function comparePeriods(string $period1, string $period2): array
    {
        $date1 = $this->parsePeriod($period1);
        $date2 = $this->parsePeriod($period2);

        $metrics1 = $this->getKPIs($date1);
        $metrics2 = $this->getKPIs($date2);

        // Calcular diferenças
        $comparison = [];
        $fields = ['total_jobs', 'total_items', 'successful_items', 'success_rate', 'avg_processing_time'];

        foreach ($fields as $field) {
            $val1 = $metrics1[$field] ?? 0;
            $val2 = $metrics2[$field] ?? 0;
            $diff = $val1 - $val2;
            $pctChange = $val2 != 0 ? round(($diff / $val2) * 100, 2) : 0;

            $comparison[$field] = [
                'period1' => $val1,
                'period2' => $val2,
                'difference' => round($diff, 2),
                'percent_change' => $pctChange,
                'trend' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'stable'),
            ];
        }

        return [
            'period1' => $period1,
            'period2' => $period2,
            'comparison' => $comparison,
        ];
    }

    /**
     * Gera projeção baseada em tendências
     */
    public function getProjection(int $daysAhead = 7): array
    {
        $params = [];
        $accountFilter = '';

        if ($this->accountId) {
            $accountFilter = 'AND account_id = :account_id';
            $params['account_id'] = $this->accountId;
        }

        // Média diária dos últimos 30 dias
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as daily_count
            FROM catalog_clone_items
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            {$accountFilter}
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute($params);
        $dailyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($dailyData) < 7) {
            return [
                'error' => 'Dados insuficientes para projeção',
                'days_available' => count($dailyData),
            ];
        }

        // Calcular tendência linear simples
        $counts = array_column($dailyData, 'daily_count');
        $n = count($counts);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $counts[$i];
            $sumXY += $i * $counts[$i];
            $sumX2 += $i * $i;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Gerar projeção
        $projections = [];
        for ($d = 1; $d <= $daysAhead; $d++) {
            $projectedValue = max(0, round($intercept + $slope * ($n + $d - 1)));
            $projections[] = [
                'date' => date('Y-m-d', strtotime("+{$d} days")),
                'projected_items' => $projectedValue,
            ];
        }

        $avgDaily = array_sum($counts) / $n;
        $projectedTotal = array_sum(array_column($projections, 'projected_items'));

        return [
            'historical_avg_daily' => round($avgDaily, 2),
            'trend' => $slope > 0 ? 'growing' : ($slope < 0 ? 'declining' : 'stable'),
            'trend_slope' => round($slope, 4),
            'projections' => $projections,
            'projected_total' => $projectedTotal,
        ];
    }

    /**
     * Registra evento de analytics
     */
    public function trackEvent(string $eventName, array $eventData = []): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO clone_analytics_events (
                account_id, event_name, event_data, created_at
            ) VALUES (
                :account_id, :event_name, :event_data, NOW()
            )
        ");

        $eventDataJson = json_encode(
            $eventData,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );

        $stmt->execute([
            'account_id' => $this->accountId,
            'event_name' => $eventName,
            'event_data' => $eventDataJson,
        ]);
    }

    /**
     * Obtém eventos de analytics
     */
    public function getEvents(?string $eventName = null, int $limit = 100): array
    {
        $limitSql = max(1, min(500, (int) $limit));

        $params = [];
        $where = ['1=1'];

        if ($this->accountId) {
            $where[] = 'account_id = :account_id';
            $params['account_id'] = $this->accountId;
        }

        if ($eventName) {
            $where[] = 'event_name = :event_name';
            $params['event_name'] = $eventName;
        }

        $sql = "
            SELECT * FROM clone_analytics_events
            WHERE " . implode(' AND ', $where) . "
            ORDER BY created_at DESC
            LIMIT {$limitSql}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($events as &$event) {
            $decoded = json_decode((string) ($event['event_data'] ?? ''), true);
            $event['event_data'] = is_array($decoded) ? $decoded : [];
        }

        return $events;
    }

    /**
     * Calcula percentis de um array
     */
    private function calculatePercentiles(array $data, array $percentiles): array
    {
        $result = [];
        $count = count($data);

        if ($count === 0) {
            foreach ($percentiles as $p) {
                $result[$p] = 0;
            }
            return $result;
        }

        sort($data);

        foreach ($percentiles as $p) {
            $index = ceil(($p / 100) * $count) - 1;
            $index = max(0, min($count - 1, $index));
            $result[$p] = round($data[$index], 2);
        }

        return $result;
    }

    /**
     * Obtém período de comparação
     */
    private function getComparisonPeriod(string $dateFrom, ?DateTimeImmutable $now = null): string
    {
        $now = $now ?? new DateTimeImmutable('now');
        $from = new DateTimeImmutable($dateFrom, $now->getTimezone());

        $seconds = $now->getTimestamp() - $from->getTimestamp();
        if ($seconds <= 0) {
            $seconds = 86400;
        }

        $prevFrom = $from->modify('-' . $seconds . ' seconds');
        return $prevFrom->format('Y-m-d H:i:s');
    }

    /**
     * Converte período em data
     */
    private function parsePeriod(string $period, ?DateTimeImmutable $now = null): string
    {
        $now = $now ?? new DateTimeImmutable('now');

        $from = match ($period) {
            '24h' => $now->modify('-24 hours'),
            '7d' => $now->modify('-7 days'),
            '30d' => $now->modify('-30 days'),
            '90d' => $now->modify('-90 days'),
            '1y' => $now->modify('-1 year'),
            default => $now->modify('-30 days'),
        };

        return $from->format('Y-m-d H:i:s');
    }
}
