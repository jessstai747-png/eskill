<?php

namespace App\Services\AI\Scoring;

use App\Database;
use PDO;

/**
 * Performance Tracker for AI Optimizations
 * Tracks and analyzes the impact of AI optimizations on listings
 * 
 * Metrics tracked:
 * - Impressions
 * - Clicks (CTR)
 * - Conversions
 * - Revenue
 * - Position changes
 * - ROI calculation
 * 
 * @author AI Development Team
 * @version 1.0.0
 */
class PerformanceTracker
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTablesExist();
    }
    
    /**
     * Ensure tracking tables exist
     */
    private function ensureTablesExist(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS ai_performance_tracking (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    item_id VARCHAR(50) NOT NULL,
                    optimization_id INT NULL,
                    date DATE NOT NULL,
                    impressions INT DEFAULT 0,
                    clicks INT DEFAULT 0,
                    visits INT DEFAULT 0,
                    conversions INT DEFAULT 0,
                    revenue DECIMAL(12,2) DEFAULT 0,
                    position_avg DECIMAL(5,2) NULL,
                    ctr DECIMAL(5,4) DEFAULT 0,
                    conversion_rate DECIMAL(5,4) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_item_date (item_id, date),
                    INDEX idx_item (item_id),
                    INDEX idx_date (date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS ai_optimization_roi (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    item_id VARCHAR(50) NOT NULL,
                    optimization_date DATE NOT NULL,
                    optimization_type VARCHAR(50) NOT NULL,
                    ai_cost DECIMAL(8,4) DEFAULT 0,
                    revenue_before DECIMAL(12,2) DEFAULT 0,
                    revenue_after DECIMAL(12,2) DEFAULT 0,
                    revenue_change DECIMAL(12,2) DEFAULT 0,
                    roi_percentage DECIMAL(8,2) DEFAULT 0,
                    analysis_period_days INT DEFAULT 30,
                    status ENUM('pending', 'calculating', 'complete') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_item (item_id),
                    INDEX idx_date (optimization_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\PDOException $e) {
            log_error('Falha ao criar tabelas do PerformanceTracker', [
                'service' => 'AI\\Scoring\\PerformanceTracker',
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Record daily metrics for an item
     * 
     * @param string $itemId
     * @param array $metrics
     * @return bool
     */
    public function recordMetrics(string $itemId, array $metrics): bool
    {
        $date = $metrics['date'] ?? date('Y-m-d');
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_performance_tracking 
                (item_id, date, impressions, clicks, visits, conversions, revenue, position_avg, ctr, conversion_rate)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    impressions = VALUES(impressions),
                    clicks = VALUES(clicks),
                    visits = VALUES(visits),
                    conversions = VALUES(conversions),
                    revenue = VALUES(revenue),
                    position_avg = VALUES(position_avg),
                    ctr = VALUES(ctr),
                    conversion_rate = VALUES(conversion_rate)
            ");
            
            // Calculate CTR and conversion rate
            $impressions = $metrics['impressions'] ?? 0;
            $clicks = $metrics['clicks'] ?? 0;
            $visits = $metrics['visits'] ?? 0;
            $conversions = $metrics['conversions'] ?? 0;
            
            $ctr = $impressions > 0 ? $clicks / $impressions : 0;
            $conversionRate = $visits > 0 ? $conversions / $visits : 0;
            
            return $stmt->execute([
                $itemId,
                $date,
                $impressions,
                $clicks,
                $visits,
                $conversions,
                $metrics['revenue'] ?? 0,
                $metrics['position_avg'] ?? null,
                $ctr,
                $conversionRate
            ]);
            
        } catch (\Exception $e) {
            log_warning('Falha ao registrar métricas de performance', [
                'service' => 'AI\\Scoring\\PerformanceTracker',
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Get performance metrics for an item
     * 
     * @param string $itemId
     * @param int $days Number of days to look back
     * @return array
     */
    public function getMetrics(string $itemId, int $days = 30): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    date,
                    impressions,
                    clicks,
                    visits,
                    conversions,
                    revenue,
                    position_avg,
                    ctr,
                    conversion_rate
                FROM ai_performance_tracking
                WHERE item_id = ?
                AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ORDER BY date ASC
            ");
            
            $stmt->execute([$itemId, $days]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            log_warning('Falha ao buscar métricas de performance', [
                'service' => 'AI\\Scoring\\PerformanceTracker',
                'item_id' => $itemId,
                'days' => $days,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
    
    /**
     * Get aggregated metrics for an item
     * 
     * @param string $itemId
     * @param int $days
     * @return array
     */
    public function getAggregatedMetrics(string $itemId, int $days = 30): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as days_tracked,
                    SUM(impressions) as total_impressions,
                    SUM(clicks) as total_clicks,
                    SUM(visits) as total_visits,
                    SUM(conversions) as total_conversions,
                    SUM(revenue) as total_revenue,
                    AVG(position_avg) as avg_position,
                    AVG(ctr) as avg_ctr,
                    AVG(conversion_rate) as avg_conversion_rate
                FROM ai_performance_tracking
                WHERE item_id = ?
                AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ");
            
            $stmt->execute([$itemId, $days]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'period_days' => $days,
                'days_tracked' => intval($result['days_tracked'] ?? 0),
                'impressions' => intval($result['total_impressions'] ?? 0),
                'clicks' => intval($result['total_clicks'] ?? 0),
                'visits' => intval($result['total_visits'] ?? 0),
                'conversions' => intval($result['total_conversions'] ?? 0),
                'revenue' => floatval($result['total_revenue'] ?? 0),
                'avg_position' => round(floatval($result['avg_position'] ?? 0), 2),
                'avg_ctr' => round(floatval($result['avg_ctr'] ?? 0) * 100, 2) . '%',
                'avg_conversion_rate' => round(floatval($result['avg_conversion_rate'] ?? 0) * 100, 2) . '%'
            ];
            
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Calculate ROI for an optimization
     * 
     * @param string $itemId
     * @param string $optimizationDate
     * @param float $aiCost
     * @param int $analysisPeriodDays
     * @return array
     */
    public function calculateROI(string $itemId, string $optimizationDate, float $aiCost = 0.03, int $analysisPeriodDays = 30): array
    {
        try {
            // Get revenue before optimization (same period before)
            $stmt = $this->db->prepare("
                SELECT SUM(revenue) as revenue
                FROM ai_performance_tracking
                WHERE item_id = ?
                AND date < ?
                AND date >= DATE_SUB(?, INTERVAL ? DAY)
            ");
            $stmt->execute([$itemId, $optimizationDate, $optimizationDate, $analysisPeriodDays]);
            $before = $stmt->fetch(PDO::FETCH_ASSOC);
            $revenueBefore = floatval($before['revenue'] ?? 0);
            
            // Get revenue after optimization
            $stmt = $this->db->prepare("
                SELECT SUM(revenue) as revenue
                FROM ai_performance_tracking
                WHERE item_id = ?
                AND date >= ?
                AND date <= DATE_ADD(?, INTERVAL ? DAY)
            ");
            $stmt->execute([$itemId, $optimizationDate, $optimizationDate, $analysisPeriodDays]);
            $after = $stmt->fetch(PDO::FETCH_ASSOC);
            $revenueAfter = floatval($after['revenue'] ?? 0);
            
            // Calculate ROI
            $revenueChange = $revenueAfter - $revenueBefore;
            $roiPercentage = $aiCost > 0 ? ($revenueChange / $aiCost) * 100 : 0;
            
            // Save ROI calculation
            $stmt = $this->db->prepare("
                INSERT INTO ai_optimization_roi 
                (item_id, optimization_date, optimization_type, ai_cost, revenue_before, revenue_after, revenue_change, roi_percentage, analysis_period_days, status)
                VALUES (?, ?, 'full', ?, ?, ?, ?, ?, ?, 'complete')
                ON DUPLICATE KEY UPDATE
                    revenue_after = VALUES(revenue_after),
                    revenue_change = VALUES(revenue_change),
                    roi_percentage = VALUES(roi_percentage),
                    status = 'complete'
            ");
            
            $stmt->execute([
                $itemId,
                $optimizationDate,
                $aiCost,
                $revenueBefore,
                $revenueAfter,
                $revenueChange,
                $roiPercentage,
                $analysisPeriodDays
            ]);
            
            return [
                'item_id' => $itemId,
                'optimization_date' => $optimizationDate,
                'ai_cost' => $aiCost,
                'revenue_before' => $revenueBefore,
                'revenue_after' => $revenueAfter,
                'revenue_change' => $revenueChange,
                'roi_percentage' => round($roiPercentage, 2),
                'roi_multiplier' => $aiCost > 0 ? round($revenueChange / $aiCost, 1) . 'x' : 'N/A',
                'analysis_period_days' => $analysisPeriodDays,
                'is_profitable' => $revenueChange > $aiCost
            ];
            
        } catch (\Exception $e) {
            log_error('Falha no cálculo de ROI', [
                'service' => 'AI\\Scoring\\PerformanceTracker',
                'item_id' => $itemId,
                'optimization_date' => $optimizationDate,
                'error' => $e->getMessage(),
            ]);
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Compare performance before and after optimization
     * 
     * @param string $itemId
     * @param string $optimizationDate
     * @param int $daysBefore
     * @param int $daysAfter
     * @return array
     */
    public function comparePerformance(string $itemId, string $optimizationDate, int $daysBefore = 14, int $daysAfter = 14): array
    {
        try {
            // Get metrics before
            $stmt = $this->db->prepare("
                SELECT 
                    AVG(impressions) as avg_impressions,
                    AVG(clicks) as avg_clicks,
                    AVG(visits) as avg_visits,
                    AVG(conversions) as avg_conversions,
                    AVG(revenue) as avg_revenue,
                    AVG(ctr) as avg_ctr,
                    AVG(conversion_rate) as avg_conversion_rate
                FROM ai_performance_tracking
                WHERE item_id = ?
                AND date < ?
                AND date >= DATE_SUB(?, INTERVAL ? DAY)
            ");
            $stmt->execute([$itemId, $optimizationDate, $optimizationDate, $daysBefore]);
            $before = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get metrics after
            $stmt = $this->db->prepare("
                SELECT 
                    AVG(impressions) as avg_impressions,
                    AVG(clicks) as avg_clicks,
                    AVG(visits) as avg_visits,
                    AVG(conversions) as avg_conversions,
                    AVG(revenue) as avg_revenue,
                    AVG(ctr) as avg_ctr,
                    AVG(conversion_rate) as avg_conversion_rate
                FROM ai_performance_tracking
                WHERE item_id = ?
                AND date >= ?
                AND date <= DATE_ADD(?, INTERVAL ? DAY)
            ");
            $stmt->execute([$itemId, $optimizationDate, $optimizationDate, $daysAfter]);
            $after = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate changes
            $changes = [];
            $metrics = ['impressions', 'clicks', 'visits', 'conversions', 'revenue', 'ctr', 'conversion_rate'];
            
            foreach ($metrics as $metric) {
                $beforeVal = floatval($before['avg_' . $metric] ?? 0);
                $afterVal = floatval($after['avg_' . $metric] ?? 0);
                $change = $beforeVal > 0 ? (($afterVal - $beforeVal) / $beforeVal) * 100 : 0;
                
                $changes[$metric] = [
                    'before' => round($beforeVal, 2),
                    'after' => round($afterVal, 2),
                    'change_percent' => round($change, 2),
                    'improved' => $afterVal > $beforeVal
                ];
            }
            
            return [
                'item_id' => $itemId,
                'optimization_date' => $optimizationDate,
                'period_before' => $daysBefore . ' days',
                'period_after' => $daysAfter . ' days',
                'metrics' => $changes,
                'overall_improvement' => $this->calculateOverallImprovement($changes)
            ];
            
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Calculate overall improvement score
     */
    private function calculateOverallImprovement(array $changes): array
    {
        $improvements = 0;
        $totalChange = 0;
        $weightedChange = 0;
        
        $weights = [
            'conversions' => 3,
            'revenue' => 3,
            'clicks' => 2,
            'visits' => 2,
            'impressions' => 1,
            'ctr' => 2,
            'conversion_rate' => 3
        ];
        
        foreach ($changes as $metric => $data) {
            if ($data['improved']) {
                $improvements++;
            }
            $weight = $weights[$metric] ?? 1;
            $weightedChange += $data['change_percent'] * $weight;
            $totalChange += $data['change_percent'];
        }
        
        $totalWeight = array_sum($weights);
        
        return [
            'metrics_improved' => $improvements,
            'total_metrics' => count($changes),
            'avg_change_percent' => round($totalChange / count($changes), 2),
            'weighted_score' => round($weightedChange / $totalWeight, 2),
            'verdict' => $weightedChange > 0 ? 'positive' : ($weightedChange < 0 ? 'negative' : 'neutral')
        ];
    }
    
    /**
     * Get top performing optimized items
     * 
     * @param int $limit
     * @param int $days
     * @return array
     */
    public function getTopPerformers(int $limit = 10, int $days = 30): array
    {
        try {
            $limitSql = max(1, min(200, (int)$limit));
            $stmt = $this->db->prepare("
                SELECT 
                    r.item_id,
                    r.optimization_date,
                    r.revenue_change,
                    r.roi_percentage,
                    AVG(p.conversion_rate) as avg_conversion_rate
                FROM ai_optimization_roi r
                LEFT JOIN ai_performance_tracking p ON r.item_id = p.item_id
                WHERE r.status = 'complete'
                AND r.optimization_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY r.item_id, r.optimization_date
                ORDER BY r.revenue_change DESC
                LIMIT {$limitSql}
            ");
            
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Get dashboard summary
     * 
     * @param int $days
     * @return array
     */
    public function getDashboardSummary(int $days = 30): array
    {
        try {
            // Get overall stats
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(DISTINCT item_id) as items_tracked,
                    SUM(impressions) as total_impressions,
                    SUM(clicks) as total_clicks,
                    SUM(conversions) as total_conversions,
                    SUM(revenue) as total_revenue
                FROM ai_performance_tracking
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $overall = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get ROI stats
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as optimizations_tracked,
                    SUM(ai_cost) as total_ai_cost,
                    SUM(revenue_change) as total_revenue_change,
                    AVG(roi_percentage) as avg_roi_percentage
                FROM ai_optimization_roi
                WHERE status = 'complete'
                AND optimization_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $roi = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'period_days' => $days,
                'items_tracked' => intval($overall['items_tracked'] ?? 0),
                'total_impressions' => intval($overall['total_impressions'] ?? 0),
                'total_clicks' => intval($overall['total_clicks'] ?? 0),
                'total_conversions' => intval($overall['total_conversions'] ?? 0),
                'total_revenue' => floatval($overall['total_revenue'] ?? 0),
                'optimizations_tracked' => intval($roi['optimizations_tracked'] ?? 0),
                'total_ai_cost' => floatval($roi['total_ai_cost'] ?? 0),
                'total_revenue_from_ai' => floatval($roi['total_revenue_change'] ?? 0),
                'avg_roi' => round(floatval($roi['avg_roi_percentage'] ?? 0), 2) . '%'
            ];
            
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
