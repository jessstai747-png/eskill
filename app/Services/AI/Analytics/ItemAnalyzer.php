<?php

declare(strict_types=1);

namespace App\Services\AI\Analytics;

use App\Database;
use PDO;

/**
 * Intelligent Item Analyzer
 * Evaluates items and prioritizes them for AI optimization
 */
class ItemAnalyzer
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Analyze all items and create prioritized optimization plan
     * 
     * @return array
     */
    public function analyzeAllItems(): array
    {
        $items = $this->getAllItems();
        $analyzed = [];
        
        foreach ($items as $item) {
            $score = $this->calculatePriorityScore($item);
            $item['priority_score'] = $score;
            $item['priority_level'] = $this->getPriorityLevel($score);
            $item['estimated_roi'] = $this->estimateROI($item);
            $item['optimization_cost'] = $this->estimateOptimizationCost($item);
            $item['issues'] = $this->identifyIssues($item);
            
            $analyzed[] = $item;
        }
        
        // Sort by priority score (highest first)
        usort($analyzed, fn($a, $b) => $b['priority_score'] <=> $a['priority_score']);
        
        return $analyzed;
    }
    
    /**
     * Calculate priority score for an item (0-100)
     * Higher score = higher priority for optimization
     * 
     * @param array $item
     * @return int
     */
    private function calculatePriorityScore(array $item): int
    {
        $score = 0;
        
        // 1. Sales velocity (0-30 points)
        $salesVelocity = $item['sales_count'] ?? 0;
        if ($salesVelocity > 50) {
            $score += 30;
        } elseif ($salesVelocity > 20) {
            $score += 20;
        } elseif ($salesVelocity > 5) {
            $score += 10;
        }
        
        // 2. Current quality issues (0-25 points)
        $titleLength = mb_strlen($item['title'] ?? '');
        if ($titleLength < 30) {
            $score += 15; // Very poor title
        } elseif ($titleLength < 50) {
            $score += 10; // Poor title
        }
        
        $hasDescription = !empty($item['description']);
        if (!$hasDescription) {
            $score += 10; // No description
        } elseif (mb_strlen($item['description']) < 100) {
            $score += 5; // Short description
        }
        
        // 3. Price range (0-20 points)
        $price = floatval($item['price'] ?? 0);
        if ($price > 100) {
            $score += 20; // High-value items get priority
        } elseif ($price > 50) {
            $score += 15;
        } elseif ($price > 20) {
            $score += 10;
        }
        
        // 4. Status (0-15 points)
        $status = $item['status'] ?? '';
        if ($status === 'active') {
            $score += 15;
        } elseif ($status === 'paused') {
            $score += 5;
        }
        
        // 5. Current SEO score (0-10 points)
        // Lower current score = higher priority
        $currentScore = intval($item['seo_score'] ?? 0);
        if ($currentScore < 30) {
            $score += 10;
        } elseif ($currentScore < 50) {
            $score += 7;
        } elseif ($currentScore < 70) {
            $score += 3;
        }
        
        return min(100, $score);
    }
    
    /**
     * Get priority level from score
     * 
     * @param int $score
     * @return string
     */
    private function getPriorityLevel(int $score): string
    {
        if ($score >= 75) return 'critical';
        if ($score >= 50) return 'high';
        if ($score >= 30) return 'medium';
        return 'low';
    }
    
    /**
     * Estimate ROI for optimizing this item
     * 
     * @param array $item
     * @return array
     */
    private function estimateROI(array $item): array
    {
        $currentViews = intval($item['visits'] ?? 10); // Default estimate
        $price = floatval($item['price'] ?? 0);
        $salesCount = intval($item['sales_count'] ?? 0);
        
        // Calculate current conversion rate
        $currentConversion = $currentViews > 0 ? ($salesCount / $currentViews) : 0.01;
        
        // Expected improvements from AI optimization
        $viewsIncrease = 1.45; // +145%
        $ctrIncrease = 1.89; // +89%
        $conversionIncrease = 1.67; // +67%
        
        // Projected metrics
        $projectedViews = $currentViews * $viewsIncrease;
        $projectedConversion = $currentConversion * $conversionIncrease;
        $projectedSales = $projectedViews * $projectedConversion;
        
        // Revenue estimates (monthly)
        $currentRevenue = $salesCount * $price;
        $projectedRevenue = $projectedSales * $price;
        $additionalRevenue = $projectedRevenue - $currentRevenue;
        
        return [
            'current_views' => $currentViews,
            'projected_views' => round($projectedViews),
            'views_increase' => round(($viewsIncrease - 1) * 100) . '%',
            
            'current_sales' => $salesCount,
            'projected_sales' => round($projectedSales, 1),
            'sales_increase' => round(($projectedSales - $salesCount), 1),
            
            'current_revenue' => $currentRevenue,
            'projected_revenue' => round($projectedRevenue, 2),
            'additional_revenue' => round($additionalRevenue, 2),
            
            'roi_multiplier' => $additionalRevenue > 0 ? round($additionalRevenue / 0.15, 1) : 0
        ];
    }
    
    /**
     * Estimate cost to optimize this item
     * 
     * @param array $item
     * @return float
     */
    private function estimateOptimizationCost(array $item): float
    {
        $cost = 0;
        
        // Title optimization
        if (empty($item['title']) || mb_strlen($item['title']) < 50) {
            $cost += 0.03;
        }
        
        // Description optimization
        if (empty($item['description']) || mb_strlen($item['description']) < 200) {
            $cost += 0.05;
        }
        
        // Attributes/tech sheet
        $attributeCount = count($item['attributes'] ?? []);
        if ($attributeCount < 5) {
            $cost += 0.04;
        }
        
        return $cost;
    }
    
    /**
     * Identify specific issues with an item
     * 
     * @param array $item
     * @return array
     */
    private function identifyIssues(array $item): array
    {
        $issues = [];
        
        // Title issues
        $titleLength = mb_strlen($item['title'] ?? '');
        if ($titleLength == 0) {
            $issues[] = [
                'type' => 'critical',
                'field' => 'title',
                'issue' => 'Sem título',
                'impact' => 'high'
            ];
        } elseif ($titleLength < 30) {
            $issues[] = [
                'type' => 'high',
                'field' => 'title',
                'issue' => 'Título muito curto (<30 chars)',
                'impact' => 'high'
            ];
        } elseif ($titleLength < 50) {
            $issues[] = [
                'type' => 'medium',
                'field' => 'title',
                'issue' => 'Título curto (<50 chars)',
                'impact' => 'medium'
            ];
        }
        
        // Description issues
        $descLength = mb_strlen($item['description'] ?? '');
        if ($descLength == 0) {
            $issues[] = [
                'type' => 'critical',
                'field' => 'description',
                'issue' => 'Sem descrição',
                'impact' => 'high'
            ];
        } elseif ($descLength < 100) {
            $issues[] = [
                'type' => 'high',
                'field' => 'description',
                'issue' => 'Descrição muito curta',
                'impact' => 'high'
            ];
        }
        
        // Attributes issues
        $attrCount = count($item['attributes'] ?? []);
        if ($attrCount < 3) {
            $issues[] = [
                'type' => 'high',
                'field' => 'attributes',
                'issue' => 'Ficha técnica incompleta',
                'impact' => 'medium'
            ];
        }
        
        // Price issues
        $price = floatval($item['price'] ?? 0);
        if ($price == 0) {
            $issues[] = [
                'type' => 'critical',
                'field' => 'price',
                'issue' => 'Sem preço definido',
                'impact' => 'critical'
            ];
        }
        
        // Status issues
        if ($item['status'] !== 'active') {
            $issues[] = [
                'type' => 'medium',
                'field' => 'status',
                'issue' => 'Item não ativo',
                'impact' => 'high'
            ];
        }
        
        return $issues;
    }
    
    /**
     * Get all items from database
     * 
     * @return array
     */
    private function getAllItems(): array
    {
        $stmt = $this->db->query("
            SELECT 
                id,
                ml_id as item_id,
                title,
                price,
                status,
                description,
                visits,
                sales as sales_count,
                category_id,
                created_at,
                updated_at
            FROM items
            ORDER BY sales DESC, visits DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate optimization plan summary
     * 
     * @param array $analyzedItems
     * @return array
     */
    public function generateOptimizationPlan(array $analyzedItems): array
    {
        $critical = array_filter($analyzedItems, fn($i) => $i['priority_level'] === 'critical');
        $high = array_filter($analyzedItems, fn($i) => $i['priority_level'] === 'high');
        $medium = array_filter($analyzedItems, fn($i) => $i['priority_level'] === 'medium');
        $low = array_filter($analyzedItems, fn($i) => $i['priority_level'] === 'low');
        
        $totalCost = array_sum(array_column($analyzedItems, 'optimization_cost'));
        $totalRevenue = array_sum(array_column(array_column($analyzedItems, 'estimated_roi'), 'additional_revenue'));
        
        return [
            'summary' => [
                'total_items' => count($analyzedItems),
                'critical' => count($critical),
                'high' => count($high),
                'medium' => count($medium),
                'low' => count($low),
                'estimated_total_cost' => round($totalCost, 2),
                'estimated_additional_revenue' => round($totalRevenue, 2),
                'estimated_roi' => $totalCost > 0 ? round($totalRevenue / $totalCost, 1) . 'x' : 'N/A'
            ],
            'recommendations' => [
                [
                    'phase' => 'Phase 1: Critical Items (Immediate)',
                    'count' => count($critical),
                    'estimated_cost' => round(array_sum(array_column($critical, 'optimization_cost')), 2),
                    'estimated_revenue' => round(array_sum(array_column(array_column($critical, 'estimated_roi'), 'additional_revenue')), 2),
                    'timeline' => '1-3 days'
                ],
                [
                    'phase' => 'Phase 2: High Priority (Week 1)',
                    'count' => count($high),
                    'estimated_cost' => round(array_sum(array_column($high, 'optimization_cost')), 2),
                    'estimated_revenue' => round(array_sum(array_column(array_column($high, 'estimated_roi'), 'additional_revenue')), 2),
                    'timeline' => '1 week'
                ],
                [
                    'phase' => 'Phase 3: Medium Priority (Week 2-3)',
                    'count' => count($medium),
                    'estimated_cost' => round(array_sum(array_column($medium, 'optimization_cost')), 2),
                    'estimated_revenue' => round(array_sum(array_column(array_column($medium, 'estimated_roi'), 'additional_revenue')), 2),
                    'timeline' => '2-3 weeks'
                ],
                [
                    'phase' => 'Phase 4: Low Priority (Month 1+)',
                    'count' => count($low),
                    'estimated_cost' => round(array_sum(array_column($low, 'optimization_cost')), 2),
                    'estimated_revenue' => round(array_sum(array_column(array_column($low, 'estimated_roi'), 'additional_revenue')), 2),
                    'timeline' => '1+ months'
                ]
            ],
            'top_opportunities' => array_slice($analyzedItems, 0, 10)
        ];
    }
}
