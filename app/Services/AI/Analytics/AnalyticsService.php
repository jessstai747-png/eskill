<?php

namespace App\Services\AI\Analytics;

use App\Services\AI\Core\AuditLogService;

/**
 * Advanced Analytics Service
 * Provides insights, ROI calculation and performance metrics
 */
class AnalyticsService
{
    private AuditLogService $auditLog;
    private \PDO $db;
    
    public function __construct()
    {
        $this->auditLog = new AuditLogService();
        $this->db = \App\Database::getInstance();
    }
    
    /**
     * Get comprehensive dashboard metrics
     * 
     * @param int $days Days to look back
     * @return array
     */
    public function getDashboardMetrics(int $days = 30): array
    {
        try {
            $stats = $this->auditLog->getStatistics([
                'start_date' => date('Y-m-d', strtotime("-{$days} days")),
                'end_date' => date('Y-m-d'),
            ]);
            
            $performance = $this->auditLog->getPerformanceReport(null, $days);
            
            $roi = $this->calculateROI($stats['overall']['total_cost'] ?? 0, $performance);
            
            return [
                'period' => [
                    'days' => $days,
                    'start_date' => date('Y-m-d', strtotime("-{$days} days")),
                    'end_date' => date('Y-m-d'),
                ],
                'optimizations' => [
                    'total' => $stats['overall']['total_optimizations'] ?? 0,
                    'applied' => $stats['overall']['total_applied'] ?? 0,
                    'rollbacks' => $stats['overall']['total_rollbacks'] ?? 0,
                    'success_rate' => $stats['success_rate'] ?? 0,
                ],
                'costs' => [
                    'total' => round($stats['overall']['total_cost'] ?? 0, 2),
                    'average_per_optimization' => round($stats['overall']['avg_cost'] ?? 0, 2),
                    'by_provider' => $stats['by_provider'] ?? [],
                ],
                'performance' => [
                    'views_gain' => $performance['total_views_gain'] ?? 0,
                    'visits_gain' => $performance['total_visits_gain'] ?? 0,
                    'sales_gain' => $performance['total_sales_gain'] ?? 0,
                    'revenue_gain' => round($performance['total_revenue_gain'] ?? 0, 2),
                    'avg_improvement' => [
                        'views' => round($performance['avg_views_improvement'] ?? 0, 1),
                        'visits' => round($performance['avg_visits_improvement'] ?? 0, 1),
                        'sales' => round($performance['avg_sales_improvement'] ?? 0, 1),
                    ],
                ],
                'roi' => $roi,
            ];
        } catch (\PDOException $e) {
            return [
                'error' => 'Dados indisponíveis',
                'details' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate ROI
     * 
     * @param float $totalCost
     * @param array $performance
     * @return array
     */
    private function calculateROI(float $totalCost, array $performance): array
    {
        $revenueGain = $performance['total_revenue_gain'] ?? 0;
        
        if ($totalCost <= 0) {
            return [
                'roi_percentage' => 0,
                'roi_multiplier' => 0,
                'revenue_gain' => 0,
                'cost' => 0,
                'net_profit' => 0,
            ];
        }
        
        $netProfit = $revenueGain - $totalCost;
        $roiPercentage = ($netProfit / $totalCost) * 100;
        $roiMultiplier = $revenueGain / $totalCost;
        
        return [
            'roi_percentage' => round($roiPercentage, 2),
            'roi_multiplier' => round($roiMultiplier, 2),
            'revenue_gain' => round($revenueGain, 2),
            'cost' => round($totalCost, 2),
            'net_profit' => round($netProfit, 2),
            'break_even' => $revenueGain >= $totalCost,
        ];
    }
    
    /**
     * Get top performing optimizations
     * 
     * @param int $limit
     * @return array
     */
    public function getTopPerformers(int $limit = 10): array
    {
        $limitSql = max(1, min(200, (int)$limit));
        $sql = "SELECT 
            al.item_id,
            al.created_at as optimization_date,
            SUM(pt.revenue_after - pt.revenue_before) as revenue_impact,
            AVG((pt.sales_after - pt.sales_before) / NULLIF(pt.sales_before, 0) * 100) as sales_improvement,
            al.cost
        FROM ai_audit_log al
        LEFT JOIN ai_performance_tracking pt ON al.id = pt.audit_log_id
        WHERE al.action = 'apply'
        GROUP BY al.item_id, al.created_at, al.cost
        HAVING revenue_impact > 0
        ORDER BY revenue_impact DESC
        LIMIT {$limitSql}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get cost breakdown
     * 
     * @param int $days
     * @return array
     */
    public function getCostBreakdown(int $days = 30): array
    {
        $sql = "SELECT 
            DATE(created_at) as date,
            ai_provider,
            ai_model,
            COUNT(*) as operations,
            SUM(cost) as total_cost,
            AVG(cost) as avg_cost
        FROM ai_audit_log
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        AND cost > 0
        GROUP BY DATE(created_at), ai_provider, ai_model
        ORDER BY date DESC, total_cost DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);
        
        $breakdown = $stmt->fetchAll();
        
        // Aggregate by day
        $byDay = [];
        foreach ($breakdown as $row) {
            $date = $row['date'];
            if (!isset($byDay[$date])) {
                $byDay[$date] = [
                    'date' => $date,
                    'total_cost' => 0,
                    'operations' => 0,
                    'by_provider' => [],
                ];
            }
            
            $byDay[$date]['total_cost'] += $row['total_cost'];
            $byDay[$date]['operations'] += $row['operations'];
            $byDay[$date]['by_provider'][] = [
                'provider' => $row['ai_provider'],
                'model' => $row['ai_model'],
                'operations' => $row['operations'],
                'cost' => round($row['total_cost'], 2),
            ];
        }
        
        return array_values($byDay);
    }
    
    /**
     * Get optimization trends
     * 
     * @param int $days
     * @return array
     */
    public function getOptimizationTrends(int $days = 30): array
    {
        $sql = "SELECT 
            DATE(created_at) as date,
            action,
            COUNT(*) as count,
            SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful
        FROM ai_audit_log
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(created_at), action
        ORDER BY date ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);
        
        $trends = $stmt->fetchAll();
        
        // Format by date
        $byDate = [];
        foreach ($trends as $row) {
            $date = $row['date'];
            if (!isset($byDate[$date])) {
                $byDate[$date] = [
                    'date' => $date,
                    'optimize' => 0,
                    'apply' => 0,
                    'preview' => 0,
                    'rollback' => 0,
                    'total' => 0,
                    'success_rate' => 0,
                ];
            }
            
            $action = $row['action'];
            $byDate[$date][$action] = (int)$row['count'];
            $byDate[$date]['total'] += (int)$row['count'];
            
            if ($byDate[$date]['total'] > 0) {
                $byDate[$date]['success_rate'] = round(
                    ((int)$row['successful'] / (int)$row['count']) * 100,
                    1
                );
            }
        }
        
        return array_values($byDate);
    }
    
    /**
     * Get performance comparison report
     * 
     * @param array $itemIds
     * @return array
     */
    public function comparePerformance(array $itemIds): array
    {
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        
        $sql = "SELECT 
            item_id,
            SUM(views_after - views_before) as views_diff,
            SUM(visits_after - visits_before) as visits_diff,
            SUM(sales_after - sales_before) as sales_diff,
            SUM(revenue_after - revenue_before) as revenue_diff,
            AVG((sales_after - sales_before) / NULLIF(sales_before, 0) * 100) as sales_improvement_pct
        FROM ai_performance_tracking
        WHERE item_id IN ({$placeholders})
        GROUP BY item_id
        ORDER BY revenue_diff DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($itemIds);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Generate executive summary
     * 
     * @param int $days
     * @return array
     */
    public function getExecutiveSummary(int $days = 30): array
    {
        $metrics = $this->getDashboardMetrics($days);
        $topPerformers = $this->getTopPerformers(5);
        
        // Calculate key insights
        $insights = [];
        
        if ($metrics['roi']['roi_percentage'] > 200) {
            $insights[] = "🎉 Excelente ROI de {$metrics['roi']['roi_percentage']}%";
        }
        
        if ($metrics['optimizations']['success_rate'] > 95) {
            $insights[] = "✅ Alta taxa de sucesso ({$metrics['optimizations']['success_rate']}%)";
        }
        
        if ($metrics['performance']['sales_gain'] > 0) {
            $insights[] = "📈 +{$metrics['performance']['sales_gain']} vendas geradas";
        }
        
        return [
            'period' => $metrics['period'],
            'key_metrics' => [
                'optimizations' => $metrics['optimizations']['total'],
                'roi' => $metrics['roi']['roi_percentage'] . '%',
                'revenue_gain' => 'R$ ' . number_format($metrics['roi']['revenue_gain'], 2, ',', '.'),
                'cost' => 'R$ ' . number_format($metrics['costs']['total'], 2, ',', '.'),
            ],
            'insights' => $insights,
            'top_performers' => array_slice($topPerformers, 0, 3),
            'recommendations' => $this->generateRecommendations($metrics),
        ];
    }
    
    /**
     * Generate recommendations based on metrics
     * 
     * @param array $metrics
     * @return array
     */
    private function generateRecommendations(array $metrics): array
    {
        $recommendations = [];
        
        // Cost optimization
        $avgCost = $metrics['costs']['average_per_optimization'];
        if ($avgCost > 0.30) {
            $recommendations[] = [
                'type' => 'cost',
                'priority' => 'medium',
                'recommendation' => "Custo médio elevado (R$ {$avgCost}). Considere usar modelos mais baratos como Claude Haiku.",
            ];
        }
        
        // Application rate
        $applyRate = $metrics['optimizations']['total'] > 0
            ? ($metrics['optimizations']['applied'] / $metrics['optimizations']['total']) * 100
            : 0;
            
        if ($applyRate < 50) {
            $recommendations[] = [
                'type' => 'adoption',
                'priority' => 'high',
                'recommendation' => "Taxa de aplicação baixa ({$applyRate}%). Revise qualidade das otimizações.",
            ];
        }
        
        // ROI
        if ($metrics['roi']['roi_percentage'] < 100) {
            $recommendations[] = [
                'type' => 'roi',
                'priority' => 'high',
                'recommendation' => "ROI abaixo de 100%. Foque em anúncios com maior volume de vendas.",
            ];
        }
        
        return $recommendations;
    }
}
