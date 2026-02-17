<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\AI\SEO\Strategies\SEOStrategiesEngine;
use App\Services\AI\SEO\Strategies\SEOAnalysisCacheService;

/**
 * Integração com SEO Strategies Engine
 *
 * Trait usada pelo SEOKillerEngine para delegar análises
 * ao sistema de 12 estratégias SEO avançadas.
 *
 * Requer que a classe use $this->accountId (int).
 */
trait SEOStrategiesIntegrationTrait
{
    /**
     * Run advanced SEO analysis using all 12 strategies
     */
    public function runStrategiesAnalysis(string $itemId): array
    {
        $engine = new SEOStrategiesEngine($this->accountId);
        return $engine->analyzeItem($itemId);
    }

    /**
     * Get detailed SEO strategies score for an item
     */
    public function getStrategiesScore(string $itemId): array
    {
        $engine = new SEOStrategiesEngine($this->accountId);
        $analysis = $engine->analyzeItem($itemId);

        return [
            'item_id' => $itemId,
            'overall_score' => $analysis['overall_score'] ?? 0,
            'strategies' => $analysis['strategies'] ?? [],
            'recommendations' => $analysis['recommendations'] ?? [],
        ];
    }

    /**
     * Optimize item using all 12 SEO strategies
     */
    public function optimizeWithStrategies(string $itemId): array
    {
        $engine = new SEOStrategiesEngine($this->accountId);

        $titleOpt = $engine->optimizeTitle($itemId);
        $descOpt = $engine->optimizeDescription($itemId);
        $keywords = $engine->generateKeywords($itemId);

        return [
            'item_id' => $itemId,
            'title_optimization' => $titleOpt,
            'description_optimization' => $descOpt,
            'generated_keywords' => $keywords,
            'strategies_applied' => 12,
        ];
    }

    /**
     * Batch analyze items with SEO strategies
     */
    public function batchStrategiesAnalysis(array $itemIds, int $limit = 10): array
    {
        $results = [];
        $engine = new SEOStrategiesEngine($this->accountId);
        $cache = new SEOAnalysisCacheService($this->accountId);

        $itemIds = array_slice($itemIds, 0, $limit);

        foreach ($itemIds as $itemId) {
            $results[$itemId] = $this->analyzeItemWithCache($engine, $cache, $itemId);
        }

        return [
            'total' => count($itemIds),
            'processed' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Analisa um item usando cache quando disponível
     */
    private function analyzeItemWithCache(
        SEOStrategiesEngine $engine,
        SEOAnalysisCacheService $cache,
        string $itemId
    ): array {
        try {
            $cached = $cache->get($itemId);
            if ($cached !== null) {
                return [
                    'success' => true,
                    'score' => $cached['overall_score'],
                    'from_cache' => true,
                ];
            }

            $analysis = $engine->analyzeItem($itemId);
            $cache->set($itemId, $analysis);

            return [
                'success' => true,
                'score' => $analysis['overall_score'] ?? 0,
                'from_cache' => false,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get SEO strategies dashboard data
     */
    public function getStrategiesDashboard(): array
    {
        $cache = new SEOAnalysisCacheService($this->accountId);

        return [
            'cache_stats' => $cache->getStats(),
            'score_distribution' => $cache->getScoreDistribution(),
            'low_score_items' => $cache->getLowScoreItems(10, 50),
            'stale_items' => $cache->getStaleItems(20),
        ];
    }
}
