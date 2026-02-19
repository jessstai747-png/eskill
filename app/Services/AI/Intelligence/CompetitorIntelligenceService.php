<?php

namespace App\Services\AI\Intelligence;

use App\Database;
use PDO;

/**
 * Competitor Intelligence Service
 * 
 * Unifies competitor tracking, strategy detection, and market share analysis.
 * Replaces legacy component CompetitorSpy.
 * 
 * @author AI Development Team
 * @version 2.0.0
 */
class CompetitorIntelligenceService
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Track a specific competitor's activity
     * 
     * @param string $competitorId
     * @return array Current status and recent changes
     */
    public function trackCompetitor(string $competitorId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT competitor_id, status, listing_count, avg_price_deviation, last_updated
                FROM competitor_tracking
                WHERE competitor_id = ?
                ORDER BY last_updated DESC
                LIMIT 1
            ");
            $stmt->execute([$competitorId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return ['competitor_id' => $competitorId, 'error' => 'Dados indisponíveis'];
            }
            return $row;
        } catch (\Exception $e) {
            return ['competitor_id' => $competitorId, 'error' => $e->getMessage()];
        }
    }

    /**
     * Detect competitor's pricing or inventory strategy
     * 
     * @param string $competitorId
     * @return string Strategy name (e.g., 'aggressive_undercut', 'premium_value')
     */
    public function detectStrategy(string $competitorId): string
    {
        try {
            $stmt = $this->db->prepare("
                SELECT price
                FROM competitor_price_history
                WHERE competitor_id = ?
                ORDER BY recorded_at DESC
                LIMIT 10
            ");
            $stmt->execute([$competitorId]);
            $prices = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (count($prices) < 2) {
                return 'unknown';
            }
            $changes = [];
            for ($i = 1; $i < count($prices); $i++) {
                if ($prices[$i - 1] > 0) {
                    $changes[] = ($prices[$i] - $prices[$i - 1]) / $prices[$i - 1];
                }
            }
            $avgChange = array_sum($changes) / max(1, count($changes));
            if ($avgChange < -0.03) {
                return 'aggressive_undercut';
            }
            if (abs($avgChange) <= 0.01) {
                return 'price_matching';
            }
            if ($avgChange > 0.02) {
                return 'premium_value';
            }
            return 'ignore_competition';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    /**
     * Estimate market share for a category
     * 
     * @param string $categoryId
     * @return array Market share distribution
     */
    public function getMarketShare(string $categoryId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT competitor_name, share_percent
                FROM competitor_market_share
                WHERE category_id = ?
                ORDER BY share_percent DESC
            ");
            $stmt->execute([$categoryId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                return ['category_id' => $categoryId, 'error' => 'Dados indisponíveis'];
            }
            return [
                'category_id' => $categoryId,
                'leaders' => array_map(fn($row) => [
                    'name' => $row['competitor_name'],
                    'share' => $row['share_percent']
                ], $rows)
            ];
        } catch (\Exception $e) {
            return ['category_id' => $categoryId, 'error' => $e->getMessage()];
        }
    }
}
