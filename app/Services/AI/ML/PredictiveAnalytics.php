<?php

namespace App\Services\AI\ML;

use App\Database;
use PDO;

/**
 * Predictive Analytics Service
 * 
 * Previsões baseadas em heurísticas e dados históricos (baselines e pesos fixos).
 * Não usa Machine Learning — calcula estimativas com fórmulas aritméticas simples.
 * 
 * Features:
 * - View predictions
 * - CTR estimation
 * - Conversion forecasting
 * - Revenue projection
 * - Opportunity identification
 * 
 * @author AI Development Team
 * @version 1.0.0
 */
class PredictiveAnalytics
{
    private PDO $db;
    private ?int $accountId;
    
    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->ensureTablesExist();
    }
    
    /**
     * Ensure tables exist
     */
    private function ensureTablesExist(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS ai_predictions (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    account_id INT NULL,
                    item_id VARCHAR(50) NOT NULL,
                    prediction_type ENUM('views', 'ctr', 'conversion', 'revenue') NOT NULL,
                    predicted_value DECIMAL(15,4) NOT NULL,
                    confidence DECIMAL(5,4) DEFAULT 0.5,
                    actual_value DECIMAL(15,4) NULL,
                    prediction_date DATE NOT NULL,
                    evaluation_date DATE NULL,
                    is_accurate TINYINT(1) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_item (item_id),
                    INDEX idx_type (prediction_type),
                    INDEX idx_date (prediction_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\PDOException $e) {
            log_error('Falha ao criar tabela ai_predictions', [
                'service' => 'PredictiveAnalytics',
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Predict views for an item
     * 
     * @param string $itemId
     * @param array $itemData
     * @return array
     */
    public function predictViews(string $itemId, array $itemData): array
    {
        $factors = $this->calculateViewFactors($itemData);
        
        $baseViews = $this->getHistoricalAverage($itemId, 'views');
        $predictedViews = $baseViews * $factors['multiplier'];
        
        $prediction = [
            'type' => 'views',
            'item_id' => $itemId,
            'current_daily_views' => $baseViews,
            'predicted_daily_views' => round($predictedViews),
            'change_percentage' => round((($predictedViews / max($baseViews, 1)) - 1) * 100, 1),
            'confidence' => $factors['confidence'],
            'factors' => $factors['contributing'],
        ];
        
        $this->savePrediction($itemId, 'views', $predictedViews, $factors['confidence']);
        
        return $prediction;
    }
    
    /**
     * Predict CTR for an item
     * 
     * @param string $itemId
     * @param array $itemData
     * @return array
     */
    public function predictCTR(string $itemId, array $itemData): array
    {
        $titleScore = $this->scoreTitleForCTR($itemData['title'] ?? '');
        $imageScore = $this->scoreImagesForCTR($itemData['images'] ?? []);
        $priceScore = $this->scorePriceForCTR($itemData);
        
        // CTR formula: base + weighted factors
        $baseCTR = 2.0; // 2% baseline
        $predictedCTR = $baseCTR + 
            ($titleScore * 0.8) + 
            ($imageScore * 0.6) + 
            ($priceScore * 0.4);
        
        $predictedCTR = min(8.0, max(0.5, $predictedCTR)); // Cap between 0.5% and 8%
        
        $confidence = ($titleScore + $imageScore + $priceScore) / 3 * 0.8;
        
        $prediction = [
            'type' => 'ctr',
            'item_id' => $itemId,
            'predicted_ctr' => round($predictedCTR, 2) . '%',
            'confidence' => round($confidence, 2),
            'breakdown' => [
                'title_impact' => round($titleScore, 2),
                'image_impact' => round($imageScore, 2),
                'price_impact' => round($priceScore, 2),
            ],
        ];
        
        $this->savePrediction($itemId, 'ctr', $predictedCTR, $confidence);
        
        return $prediction;
    }
    
    /**
     * Predict conversion rate
     * 
     * @param string $itemId
     * @param array $itemData
     * @return array
     */
    public function predictConversion(string $itemId, array $itemData): array
    {
        $descScore = $this->scoreDescriptionForConversion($itemData['description'] ?? '');
        $attrScore = $this->scoreAttributesForConversion($itemData['attributes'] ?? []);
        $trustScore = $this->scoreTrustFactors($itemData);
        
        // Conversion formula
        $baseConversion = 3.0; // 3% baseline
        $predictedConversion = $baseConversion + 
            ($descScore * 0.5) + 
            ($attrScore * 0.3) + 
            ($trustScore * 0.4);
        
        $predictedConversion = min(15.0, max(1.0, $predictedConversion));
        
        $confidence = ($descScore + $attrScore + $trustScore) / 3 * 0.75;
        
        $prediction = [
            'type' => 'conversion',
            'item_id' => $itemId,
            'predicted_conversion' => round($predictedConversion, 2) . '%',
            'confidence' => round($confidence, 2),
            'breakdown' => [
                'description_impact' => round($descScore, 2),
                'attributes_impact' => round($attrScore, 2),
                'trust_impact' => round($trustScore, 2),
            ],
        ];
        
        $this->savePrediction($itemId, 'conversion', $predictedConversion, $confidence);
        
        return $prediction;
    }
    
    /**
     * Predict revenue impact
     * 
     * @param string $itemId
     * @param array $itemData
     * @param array $optimizations
     * @return array
     */
    public function predictRevenue(string $itemId, array $itemData, array $optimizations = []): array
    {
        $views = $this->predictViews($itemId, $itemData);
        $ctr = $this->predictCTR($itemId, $itemData);
        $conversion = $this->predictConversion($itemId, $itemData);
        
        $price = floatval($itemData['price'] ?? 100);
        
        // Calculate projected revenue
        $dailyViews = $views['predicted_daily_views'] ?? 100;
        $ctrValue = floatval(str_replace('%', '', $ctr['predicted_ctr'] ?? '2'));
        $convValue = floatval(str_replace('%', '', $conversion['predicted_conversion'] ?? '3'));
        
        $dailyClicks = $dailyViews * ($ctrValue / 100);
        $dailySales = $dailyClicks * ($convValue / 100);
        $dailyRevenue = $dailySales * $price;
        
        $monthlyRevenue = $dailyRevenue * 30;
        
        // Estimate improvement from optimizations
        $improvementFactor = 1.0;
        if (!empty($optimizations)) {
            $scoreImprovement = $optimizations['improvement'] ?? 0;
            $improvementFactor = 1 + ($scoreImprovement / 100) * 0.3; // 30% of score improvement
        }
        
        $projection = [
            'item_id' => $itemId,
            'current_price' => $price,
            'daily' => [
                'views' => $dailyViews,
                'clicks' => round($dailyClicks),
                'sales' => round($dailySales, 1),
                'revenue' => round($dailyRevenue, 2),
            ],
            'monthly' => [
                'sales' => round($dailySales * 30),
                'revenue' => round($monthlyRevenue, 2),
            ],
            'with_optimization' => [
                'monthly_revenue' => round($monthlyRevenue * $improvementFactor, 2),
                'increase' => round(($improvementFactor - 1) * 100, 1) . '%',
            ],
            'confidence' => round(min($views['confidence'] ?? 0.5, $ctr['confidence'] ?? 0.5, $conversion['confidence'] ?? 0.5), 2),
        ];
        
        $this->savePrediction($itemId, 'revenue', $monthlyRevenue, $projection['confidence']);
        
        return $projection;
    }
    
    /**
     * Identify optimization opportunities
     * 
     * @param int $limit
     * @return array
     */
    public function identifyOpportunities(int $limit = 20): array
    {
        try {
            $limitSql = max(1, min(200, (int)$limit));
            // Find items with high potential but low current score
            $stmt = $this->db->prepare("
                SELECT 
                    al.item_id,
                    al.score_before,
                    al.score_after,
                    COUNT(*) as optimization_count,
                    AVG(al.score_after - al.score_before) as avg_improvement
                FROM ai_audit_log al
                WHERE al.account_id = ? OR al.account_id IS NULL
                GROUP BY al.item_id
                HAVING score_before < 70
                ORDER BY avg_improvement DESC
                LIMIT {$limitSql}
            ");
            
            $stmt->execute([$this->accountId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $opportunities = [];
            foreach ($items as $item) {
                $opportunities[] = [
                    'item_id' => $item['item_id'],
                    'current_score' => intval($item['score_before']),
                    'potential_score' => intval($item['score_before'] + ($item['avg_improvement'] * 2)),
                    'estimated_improvement' => round($item['avg_improvement'], 1),
                    'priority' => $item['score_before'] < 50 ? 'high' : 'medium',
                ];
            }
            
            return [
                'opportunities' => $opportunities,
                'total' => count($opportunities),
            ];
            
        } catch (\Exception $e) {
            return ['opportunities' => [], 'total' => 0];
        }
    }

    /**
     * Get dashboard-formatted metrics for the AI Center
     * Used by AICenterController
     * 
     * @param int|null $accountId
     * @return array
     */
    public function getDashboardMetrics(?int $accountId = null): array
    {
        try {
            $accId = $accountId ?? $this->accountId;
            
            // Get total forecasted revenue from predictions
            $stmt = $this->db->prepare("
                SELECT SUM(predicted_value) as total_revenue
                FROM ai_predictions 
                WHERE prediction_type = 'revenue'
                AND prediction_date >= CURDATE() 
                AND prediction_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                AND (account_id = ? OR account_id IS NULL)
            ");
            $stmt->execute([$accId]);
            $revenueData = $stmt->fetchColumn() ?: 0;
            
            // Format as currency
            $forecastedSales = 'R$ ' . number_format($revenueData, 2, ',', '.');
            
            // Get market opportunities count
            $opportunitiesData = $this->identifyOpportunities(100);
            $marketOpportunities = $opportunitiesData['total'] ?? 0;
            
            // Calculate confidence level from recent predictions
            $stmt = $this->db->prepare("
                SELECT AVG(confidence) as avg_confidence
                FROM ai_predictions
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND (account_id = ? OR account_id IS NULL)
            ");
            $stmt->execute([$accId]);
            $avgConfidence = $stmt->fetchColumn() ?: 0;
            $confidenceLevel = round($avgConfidence * 100) . '%';
            
            // Get historical data for chart (last 7 days of predictions)
            $stmt = $this->db->query("
                SELECT 
                    DATE(prediction_date) as date,
                    SUM(predicted_value) as daily_value
                FROM ai_predictions
                WHERE prediction_type = 'revenue'
                AND prediction_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(prediction_date)
                ORDER BY date ASC
            ");
            $chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'forecasted_sales' => $forecastedSales,
                'market_opportunities' => $marketOpportunities,
                'confidence_level' => $confidenceLevel,
                'chart_data' => array_map(fn($row) => [
                    'date' => $row['date'],
                    'value' => floatval($row['daily_value'])
                ], $chartData)
            ];
            
        } catch (\Exception $e) {
            return [
                'forecasted_sales' => 'R$ 0,00',
                'market_opportunities' => 0,
                'confidence_level' => '0%',
                'chart_data' => []
            ];
        }
    }
    
    /**
     * Generate proactive alerts
     */
    public function generateAlerts(): array
    {
        $alerts = [];
        
        // Check for items with declining performance
        try {
            $stmt = $this->db->prepare("
                SELECT item_id, COUNT(*) as rollbacks
                FROM ai_audit_log
                WHERE action = 'rollback'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY item_id
                HAVING rollbacks >= 2
            ");
            $stmt->execute();
            
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $alerts[] = [
                    'type' => 'warning',
                    'category' => 'performance',
                    'item_id' => $row['item_id'],
                    'message' => "Item com {$row['rollbacks']} rollbacks nos últimos 7 dias",
                    'action' => 'review_optimization_strategy',
                ];
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        return $alerts;
    }
    
    // Scoring helper methods
    private function calculateViewFactors(array $itemData): array
    {
        $multiplier = 1.0;
        $contributing = [];
        
        // Title quality
        $titleLen = mb_strlen($itemData['title'] ?? '');
        if ($titleLen >= 50) {
            $multiplier += 0.15;
            $contributing['title_optimized'] = '+15%';
        }
        
        // Image count
        $imageCount = count($itemData['images'] ?? []);
        if ($imageCount >= 5) {
            $multiplier += 0.20;
            $contributing['images_complete'] = '+20%';
        }
        
        // Free shipping
        if ($itemData['free_shipping'] ?? false) {
            $multiplier += 0.10;
            $contributing['free_shipping'] = '+10%';
        }
        
        return [
            'multiplier' => $multiplier,
            'confidence' => min(0.85, 0.5 + (count($contributing) * 0.1)),
            'contributing' => $contributing,
        ];
    }
    
    private function scoreTitleForCTR(string $title): float
    {
        $score = 0;
        if (mb_strlen($title) >= 50) $score += 0.5;
        if (preg_match('/\d+/', $title)) $score += 0.3; // Has numbers
        return $score;
    }
    
    private function scoreImagesForCTR(array $images): float
    {
        return min(1.0, count($images) * 0.2);
    }
    
    private function scorePriceForCTR(array $itemData): float
    {
        if (isset($itemData['original_price']) && isset($itemData['price'])) {
            if ($itemData['price'] < $itemData['original_price']) {
                return 0.8; // Has discount
            }
        }
        return 0.3;
    }
    
    private function scoreDescriptionForConversion(string $desc): float
    {
        $score = 0;
        if (mb_strlen($desc) >= 1000) $score += 0.5;
        if (strpos($desc, '•') !== false) $score += 0.3;
        return $score;
    }
    
    private function scoreAttributesForConversion(array $attrs): float
    {
        return min(1.0, count($attrs) * 0.05);
    }
    
    private function scoreTrustFactors(array $itemData): float
    {
        $score = 0;
        if ($itemData['free_shipping'] ?? false) $score += 0.3;
        if (($itemData['sold_quantity'] ?? 0) > 10) $score += 0.4;
        return $score;
    }
    
    private function getHistoricalAverage(string $itemId, string $type): float
    {
        // Default baseline values
        return match($type) {
            'views' => 50.0,
            'ctr' => 2.0,
            'conversion' => 3.0,
            default => 0.0,
        };
    }
    
    private function savePrediction(string $itemId, string $type, float $value, float $confidence): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_predictions 
                (account_id, item_id, prediction_type, predicted_value, confidence, prediction_date)
                VALUES (?, ?, ?, ?, ?, CURDATE())
            ");
            $stmt->execute([$this->accountId, $itemId, $type, $value, $confidence]);
        } catch (\Exception $e) {
            // Ignore
        }
    }
}
