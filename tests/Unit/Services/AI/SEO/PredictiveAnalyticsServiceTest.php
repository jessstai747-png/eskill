<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI\SEO;

use Tests\TestCase;
use App\Services\AI\SEO\PredictiveAnalyticsService;
use ReflectionClass;
use ReflectionMethod;

/**
 * Testes unitários para AI\SEO\PredictiveAnalyticsService.
 * Foca em métodos de lógica pura (sem dependência de DB/API).
 */
class PredictiveAnalyticsServiceTest extends TestCase
{
    private PredictiveAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $reflection = new ReflectionClass(PredictiveAnalyticsService::class);
        $this->service = $reflection->newInstanceWithoutConstructor();
    }

    private function invoke(string $method, ...$args)
    {
        $ref = new ReflectionMethod(PredictiveAnalyticsService::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke($this->service, ...$args);
    }

    // =============================
    // TESTES DE ESTRUTURA
    // =============================

    public function testHasAllPublicMethods(): void
    {
        $methods = [
            'predictItemPerformance',
            'analyzeMarketTrends',
            'predictOptimalPricing',
            'predictSEOImprovement',
            'predictSeasonalOpportunities',
            'generatePredictiveInsights',
        ];

        $ref = new ReflectionClass(PredictiveAnalyticsService::class);
        foreach ($methods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "Método público '{$method}' não encontrado"
            );
        }
    }

    public function testHasAllPrivateHelperMethods(): void
    {
        $methods = [
            'loadModels',
            'getHistoricalPerformance',
            'generatePrediction',
            'calculateConfidence',
            'generatePredictiveRecommendations',
            'collectMarketData',
            'performTrendAnalysis',
            'identifyOpportunities',
            'generatePricingPrediction',
            'predictPriceImpact',
            'calculateImprovementPotential',
            'calculateViance',
            'identifyMarketWarnings',
            'calculateTrendConfidence',
            'predictCategoryGrowth',
            'getPricingHistory',
            'predictImprovementTimeline',
            'predictSEOImpact',
            'estimateRankingImprovement',
            'calculatePriority',
            'calculateSEOConfidence',
            'identifySeasonalPatterns',
            'predictUpcomingSeasonal',
            'generateSeasonalRecommendations',
            'calculateSeasonalConfidence',
            'generatePreparationTimeline',
            'calculateAccountHealth',
        ];

        $ref = new ReflectionClass(PredictiveAnalyticsService::class);
        foreach ($methods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "Método privado '{$method}' não encontrado"
            );
        }
    }

    // =============================
    // calculateImprovementPotential
    // =============================

    public function testImprovementPotentialAllZeros(): void
    {
        $analysis = [
            'title_score' => 100,
            'description_score' => 100,
            'attributes_score' => 100,
            'images_score' => 100,
            'strategy_score' => 100,
        ];
        $result = $this->invoke('calculateImprovementPotential', $analysis);

        $this->assertEquals(0, $result['title_optimization']);
        $this->assertEquals(0, $result['description_optimization']);
        $this->assertEquals(0, $result['total_gain']);
    }

    public function testImprovementPotentialLowScores(): void
    {
        $analysis = [
            'title_score' => 30,
            'description_score' => 20,
            'attributes_score' => 50,
            'images_score' => 40,
            'strategy_score' => 10,
        ];
        $result = $this->invoke('calculateImprovementPotential', $analysis);

        // title: min(20, 70 * 0.3) = min(20, 21) = 20
        $this->assertEqualsWithDelta(20, $result['title_optimization'], 0.1);
        // description: min(25, 80 * 0.4) = min(25, 32) = 25
        $this->assertEqualsWithDelta(25, $result['description_optimization'], 0.1);
        $this->assertGreaterThan(0, $result['total_gain']);
    }

    public function testImprovementPotentialMissingScoredDefaultsToZero(): void
    {
        $result = $this->invoke('calculateImprovementPotential', []);

        // All (100 - 0) * factor, capped
        $this->assertGreaterThan(0, $result['total_gain']);
        $this->assertEquals($result['title_optimization'] + $result['description_optimization']
                            + $result['attribute_completion'] + $result['image_optimization']
                            + $result['seo_strategy'], $result['total_gain']);
    }

    // =============================
    // calculateViance
    // =============================

    public function testCalculateVianceWithData(): void
    {
        $data = [
            ['views' => 10],
            ['views' => 20],
            ['views' => 30],
        ];
        $result = $this->invoke('calculateViance', $data);
        // mean = 20, variance = ((10-20)^2 + (20-20)^2 + (30-20)^2) / 3 = 200/3 ≈ 66.67
        $this->assertEqualsWithDelta(66.67, $result, 0.1);
    }

    public function testCalculateVianceEmpty(): void
    {
        $result = $this->invoke('calculateViance', []);
        $this->assertEquals(0, $result);
    }

    public function testCalculateVianceUniform(): void
    {
        $data = [['views' => 5], ['views' => 5], ['views' => 5]];
        $result = $this->invoke('calculateViance', $data);
        $this->assertEquals(0, $result);
    }

    // =============================
    // identifyMarketWarnings
    // =============================

    public function testMarketWarningsDemandDecline(): void
    {
        $analysis = ['demand_trend' => -20, 'volatility' => 0, 'competitor_growth' => 0, 'avg_price_change' => 0];
        $result = $this->invoke('identifyMarketWarnings', $analysis);

        $types = array_column($result, 'type');
        $this->assertContains('demand_decline', $types);
    }

    public function testMarketWarningsHighVolatility(): void
    {
        $analysis = ['volatility' => 0.6];
        $result = $this->invoke('identifyMarketWarnings', $analysis);

        $types = array_column($result, 'type');
        $this->assertContains('high_volatility', $types);
        // severity should be critical for > 0.5
        $volWarning = array_filter($result, fn($w) => $w['type'] === 'high_volatility');
        $volWarning = array_values($volWarning)[0];
        $this->assertEquals('critical', $volWarning['severity']);
    }

    public function testMarketWarningsPriceWar(): void
    {
        $analysis = ['avg_price_change' => -25];
        $result = $this->invoke('identifyMarketWarnings', $analysis);

        $types = array_column($result, 'type');
        $this->assertContains('price_war', $types);
    }

    public function testMarketWarningsNoIssues(): void
    {
        $analysis = ['demand_trend' => 5, 'volatility' => 0.1, 'competitor_growth' => 2, 'avg_price_change' => -5];
        $result = $this->invoke('identifyMarketWarnings', $analysis);
        $this->assertEmpty($result);
    }

    // =============================
    // calculateTrendConfidence
    // =============================

    public function testTrendConfidenceHighDataLowVariance(): void
    {
        $analysis = ['data_points' => 150, 'variance' => 0.1, 'trend_slope' => 15, 'time_period' => 60];
        $result = $this->invoke('calculateTrendConfidence', $analysis);

        // base 0.5 + 0.2 (>=100 pts) + 0.135 (variance) + 0.1 (trend) + 0.05 (time)= 0.95 capped
        $this->assertGreaterThanOrEqual(0.90, $result);
        $this->assertLessThanOrEqual(0.95, $result);
    }

    public function testTrendConfidenceLowData(): void
    {
        $analysis = ['data_points' => 5, 'variance' => 0.8, 'trend_slope' => 2];
        $result = $this->invoke('calculateTrendConfidence', $analysis);

        $this->assertLessThanOrEqual(0.60, $result);
    }

    public function testTrendConfidenceBounds(): void
    {
        $result = $this->invoke('calculateTrendConfidence', []);
        $this->assertGreaterThanOrEqual(0.1, $result);
        $this->assertLessThanOrEqual(0.95, $result);
    }

    // =============================
    // predictCategoryGrowth
    // =============================

    public function testCategoryGrowthPositiveTrend(): void
    {
        $analysis = ['trend_slope' => 10, 'competitor_growth' => 5, 'seasonal_factor' => 1.2];
        $result = $this->invoke('predictCategoryGrowth', $analysis);

        $this->assertGreaterThan(0, $result);
        $this->assertLessThanOrEqual(100, $result);
    }

    public function testCategoryGrowthNegativeTrend(): void
    {
        $analysis = ['trend_slope' => -15, 'competitor_growth' => 30, 'seasonal_factor' => 0.8];
        $result = $this->invoke('predictCategoryGrowth', $analysis);

        $this->assertLessThan(0, $result);
        $this->assertGreaterThanOrEqual(-50, $result);
    }

    public function testCategoryGrowthEmptyData(): void
    {
        $result = $this->invoke('predictCategoryGrowth', []);
        $this->assertEquals(0.0, $result);
    }

    // =============================
    // generatePredictiveRecommendations
    // =============================

    public function testRecommendationsDeclining(): void
    {
        $prediction = ['trend' => 'declining'];
        $result = $this->invoke('generatePredictiveRecommendations', $prediction);

        $this->assertCount(1, $result);
        $this->assertEquals('urgent', $result[0]['type']);
    }

    public function testRecommendationsGrowing(): void
    {
        $prediction = ['trend' => 'growing'];
        $result = $this->invoke('generatePredictiveRecommendations', $prediction);

        $this->assertCount(1, $result);
        $this->assertEquals('opportunity', $result[0]['type']);
    }

    public function testRecommendationsStable(): void
    {
        $prediction = ['trend' => 'stable'];
        $result = $this->invoke('generatePredictiveRecommendations', $prediction);
        $this->assertEmpty($result);
    }

    // =============================
    // identifyOpportunities
    // =============================

    public function testIdentifyOpportunitiesPricingGaps(): void
    {
        $analysis = [
            'pricing_gaps' => [
                ['min' => 50, 'max' => 100, 'impact_score' => 0.8],
            ],
        ];
        $result = $this->invoke('identifyOpportunities', $analysis);

        $this->assertCount(1, $result);
        $this->assertEquals('pricing_gap', $result[0]['type']);
        $this->assertEquals(0.8, $result[0]['potential_impact']);
    }

    public function testIdentifyOpportunitiesKeywords(): void
    {
        $analysis = [
            'keyword_opportunities' => [
                ['term' => 'fone bluetooth', 'volume' => 1000, 'competition' => 'low'],
            ],
        ];
        $result = $this->invoke('identifyOpportunities', $analysis);

        $this->assertCount(1, $result);
        $this->assertEquals('keyword', $result[0]['type']);
    }

    public function testIdentifyOpportunitiesEmpty(): void
    {
        $result = $this->invoke('identifyOpportunities', []);
        $this->assertEmpty($result);
    }

    // =============================
    // predictPriceImpact
    // =============================

    public function testPredictPriceImpactStructure(): void
    {
        $prediction = [
            'view_impact' => 15.5,
            'sales_impact' => 10.0,
            'revenue_impact' => 12.3,
            'confidence' => 0.85,
        ];
        $result = $this->invoke('predictPriceImpact', $prediction);

        $this->assertEquals(15.5, $result['view_change_percent']);
        $this->assertEquals(10.0, $result['sales_change_percent']);
        $this->assertEquals(12.3, $result['revenue_change_percent']);
        $this->assertEquals(0.85, $result['confidence']);
    }

    // =============================
    // predictImprovementTimeline
    // =============================

    public function testImprovementTimelineSortedByDays(): void
    {
        $improvements = [
            'title_optimization' => 5,
            'image_optimization' => 10,
            'attribute_completion' => 8,
        ];
        $result = $this->invoke('predictImprovementTimeline', $improvements);

        // attribute_completion (5d), title_optimization (7d), image_optimization (21d)
        $this->assertCount(3, $result);
        $this->assertEquals('attribute_completion', $result[0]['optimization']);
        $this->assertEquals('title_optimization', $result[1]['optimization']);
        $this->assertEquals('image_optimization', $result[2]['optimization']);
    }

    public function testImprovementTimelineSkipsZeroGain(): void
    {
        $improvements = ['title_optimization' => 5, 'description_optimization' => 0];
        $result = $this->invoke('predictImprovementTimeline', $improvements);

        $this->assertCount(1, $result);
        $this->assertEquals('title_optimization', $result[0]['optimization']);
    }

    public function testImprovementTimelineMilestones(): void
    {
        $improvements = ['title_optimization' => 10];
        $result = $this->invoke('predictImprovementTimeline', $improvements);

        $this->assertCount(3, $result[0]['milestones']);
        $this->assertEquals(1, $result[0]['milestones'][0]['day']);
        $this->assertEquals(7, $result[0]['milestones'][2]['day']); // title_optimization = 7 days
    }

    // =============================
    // predictSEOImpact
    // =============================

    public function testSEOImpactScorePrediction(): void
    {
        $improvements = [
            'title_optimization' => 10,
            'description_optimization' => 5,
            'attribute_completion' => 8,
            'image_optimization' => 3,
            'seo_strategy' => 4,
            'total_gain' => 30,
        ];
        $current = ['overall_score' => 50];
        $result = $this->invoke('predictSEOImpact', $improvements, $current);

        $this->assertEquals(80, $result['predicted_score']);
        $this->assertEquals(30, $result['score_gain']);
        $this->assertArrayHasKey('conversion_lift_percent', $result);
        $this->assertArrayHasKey('visibility_lift_percent', $result);
        $this->assertArrayHasKey('estimated_sales_lift_percent', $result);
        $this->assertArrayHasKey('ranking_improvement', $result);
    }

    public function testSEOImpactCapsAt100(): void
    {
        $improvements = ['total_gain' => 60, 'title_optimization' => 20, 'attribute_completion' => 15];
        $current = ['overall_score' => 70];
        $result = $this->invoke('predictSEOImpact', $improvements, $current);

        $this->assertLessThanOrEqual(100, $result['predicted_score']);
    }

    // =============================
    // estimateRankingImprovement
    // =============================

    public function testEstimateRankingImprovementLevels(): void
    {
        $this->assertEquals('significativa', $this->invoke('estimateRankingImprovement', 30.0));
        $this->assertEquals('moderada', $this->invoke('estimateRankingImprovement', 20.0));
        $this->assertEquals('leve', $this->invoke('estimateRankingImprovement', 10.0));
        $this->assertEquals('mínima', $this->invoke('estimateRankingImprovement', 3.0));
    }

    // =============================
    // calculatePriority
    // =============================

    public function testCalculatePriorityCritical(): void
    {
        $improvements = ['total_gain' => 30];
        $impact = ['estimated_sales_lift_percent' => 25];
        $result = $this->invoke('calculatePriority', $improvements, $impact);
        // score = 30*0.4 + 25*0.6 = 12 + 15 = 27 >= 20 → critical
        $this->assertEquals('critical', $result);
    }

    public function testCalculatePriorityLow(): void
    {
        $improvements = ['total_gain' => 2];
        $impact = ['estimated_sales_lift_percent' => 1];
        $result = $this->invoke('calculatePriority', $improvements, $impact);
        // score = 0.8 + 0.6 = 1.4 < 5 → low
        $this->assertEquals('low', $result);
    }

    // =============================
    // calculateSEOConfidence
    // =============================

    public function testSEOConfidenceHighDataSmallGain(): void
    {
        $current = [
            'overall_score' => 60,
            'title_score' => 70,
            'description_score' => 50,
            'attributes_score' => 80,
            'images_score' => 40,
        ];
        $improvements = ['total_gain' => 10];
        $result = $this->invoke('calculateSEOConfidence', $current, $improvements);

        // base 0.5 + fields (4/5 * 0.2 = 0.16) + small gain bonus 0.1 + mid-range 0.1 = 0.86
        $this->assertGreaterThanOrEqual(0.80, $result);
    }

    public function testSEOConfidenceAggressiveGain(): void
    {
        $current = ['overall_score' => 20];
        $improvements = ['total_gain' => 50];
        $result = $this->invoke('calculateSEOConfidence', $current, $improvements);

        // base 0.5 + 0 (no fields) - 0.1 (aggressive) + 0 (score < 30) = 0.4
        $this->assertLessThanOrEqual(0.50, $result);
    }

    public function testSEOConfidenceBounds(): void
    {
        $result = $this->invoke('calculateSEOConfidence', [], []);
        $this->assertGreaterThanOrEqual(0.2, $result);
        $this->assertLessThanOrEqual(0.95, $result);
    }

    // =============================
    // calculateSeasonalConfidence
    // =============================

    public function testSeasonalConfidenceNoPatterns(): void
    {
        $result = $this->invoke('calculateSeasonalConfidence', ['patterns' => []]);
        $this->assertEquals(0.1, $result);
    }

    public function testSeasonalConfidenceRichData(): void
    {
        $patterns = [
            'patterns' => array_fill(0, 24, ['month' => 1]),
            'seasonality_strength' => 0.5,
            'peaks' => [['month_number' => 11], ['month_number' => 12]],
            'valleys' => [['month_number' => 2]],
        ];
        $result = $this->invoke('calculateSeasonalConfidence', $patterns);

        $this->assertGreaterThanOrEqual(0.70, $result);
    }

    // =============================
    // generateSeasonalRecommendations
    // =============================

    public function testSeasonalRecommendationsHighPeak(): void
    {
        $opportunities = [
            ['type' => 'high_peak', 'months_until' => 1, 'event' => 'Black Friday'],
        ];
        $result = $this->invoke('generateSeasonalRecommendations', $opportunities);

        $this->assertCount(1, $result);
        $actions = $result[0]['actions'];
        $actionTypes = array_column($actions, 'action');
        $this->assertContains('prepare_stock', $actionTypes);
        $this->assertContains('price_optimization', $actionTypes);
        $this->assertContains('ads_boost', $actionTypes);
    }

    public function testSeasonalRecommendationsNoSEOWhenClose(): void
    {
        $opportunities = [
            ['type' => 'slight_uptick', 'months_until' => 1, 'event' => null],
        ];
        $result = $this->invoke('generateSeasonalRecommendations', $opportunities);

        $actionTypes = array_column($result[0]['actions'], 'action');
        // SEO prep only when months_until >= 2
        $this->assertNotContains('seo_preparation', $actionTypes);
    }

    public function testSeasonalRecommendationsEmptyOpportunities(): void
    {
        $result = $this->invoke('generateSeasonalRecommendations', []);
        $this->assertEmpty($result);
    }

    // =============================
    // calculateAccountHealth
    // =============================

    public function testAccountHealthEmptyItems(): void
    {
        $result = $this->invoke('calculateAccountHealth', []);
        $this->assertEquals(0, $result['score']);
        $this->assertEquals('no_data', $result['status']);
    }

    public function testAccountHealthWithActiveItems(): void
    {
        $items = [
            ['status' => 'active', 'title' => 'Produto Exemplo Com Titulo Adequado Para SEO 2024'],
            ['status' => 'active', 'title' => 'Outro Produto Ativo Com Titulo Bom'],
            ['status' => 'paused', 'title' => 'Pausado'],
        ];
        $result = $this->invoke('calculateAccountHealth', $items);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('factors', $result);
        $this->assertGreaterThan(0, $result['score']);
        // 2/3 active = 66.7% ratio
        $this->assertEqualsWithDelta(66.7, $result['factors']['active_ratio']['score'], 0.1);
    }
}
