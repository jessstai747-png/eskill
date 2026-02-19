<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\MercadoLivre\MLAnalyticsIntelligenceService;

/**
 * Testes unitários para as integrações reais do MLAnalyticsIntelligenceService.
 * Verifica existência de todos os métodos implementados e estrutura.
 */
class MLAnalyticsIntelligenceServiceTest extends TestCase
{
    // =============================
    // TESTES DE ESTRUTURA
    // =============================

    public function testHasRequiredPublicMethods(): void
    {
        $methods = [
            'getComprehensiveAnalytics',
            'getDetailedSearchAnalytics',
            'getCategoryIntelligence',
            'getCustomerJourneyAnalysis',
            'getConversionFunnelAnalysis',
            'getROIAttribution',
            'generatePredictiveAnalytics',
            'getDemandForecasting',
            'getIntelligenceReports',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(MLAnalyticsIntelligenceService::class, $method),
                "MLAnalyticsIntelligenceService deve ter método público {$method}()"
            );
        }
    }

    public function testHasAllPrivateHelperMethods(): void
    {
        $requiredPrivateMethods = [
            // Core analytics
            'getPerformanceOverview',
            'getSearchAnalytics',
            'getCustomerJourneyAnalysis',
            'getConversionFunnel',
            'getROIAttribution',
            'getPredictiveInsights',
            'getMarketPositioning',
            'getOperationalMetrics',
            // Search detail
            'getSearchData',
            'extractSearchInsights',
            'generateSearchRecommendations',
            'getMostSearchedTerms',
            'getSearchVolumeTrends',
            'getSeasonalSearchPatterns',
            'getKeywordPerformance',
            'getOpportunityKeywords',
            // Category
            'getActiveCategories',
            'analyzeCategoryPerformance',
            'performCrossCategoryAnalysis',
            'generateCategoryRankings',
            'generateCategoryOpportunityMatrix',
            'generateCategoryStrategicRecommendations',
            // Customer Journey
            'analyzeTouchpoints',
            'analyzeDropOffPoints',
            'identifyConversionPoints',
            'getLifetimeValueAnalysis',
            'generateJourneyOptimization',
            // Funnel
            'identifyFunnelLeakage',
            'extractFunnelInsights',
            'generateFunnelOptimizations',
            'estimateFunnelImprovement',
            // ROI
            'calculateROIByProduct',
            'calculateROIByCategory',
            'performCostAnalysis',
            'calculateROIMetrics',
            // Predictions
            'calculatePredictionConfidence',
            'generateActionableInsights',
            'generateImplementationRoadmap',
            // Demand forecasting
            'getDemandHistoricalData',
            'forecastProductDemand',
            'calculateConfidenceIntervals',
            'identifyDemandRiskFactors',
            // Intelligence reports
            'generateDailyIntelligence',
            'generateWeeklyInsights',
            'generateMonthlyStrategic',
            'generateCompetitiveIntelligence',
            'generatePerformanceScorecards',
            'calculateDataQualityScore',
            'generateIntelligenceRecommendations',
            // Previously missing methods (now declared)
            'extractSearchPatternInsights',
            'identifySearchOptimizations',
            'predictPriceOptimization',
            'predictInventoryNeeds',
            'predictMarketTrends',
            'predictCustomerBehavior',
            'predictCompetitorActions',
            'predictSeasonalPatterns',
            'generateOpportunityScoring',
            // Phase 4 — newly implemented methods
            'getSegmentJourneys',
            'getSegmentFunnels',
            'getProductFunnels',
            'performFunnelAttribution',
            'calculateOptimizationImpact',
            'performMultiTouchAttribution',
            'getChannelPerformance',
            'compareAttributionModels',
            'generateBudgetOptimization',
            'extractAttributionInsights',
            'generateBudgetRecommendations',
            'getSearchSuccessRates',
            'getSearchByDevice',
            'getSearchByLocation',
            'getSearchByTime',
            'getAbandonedSearches',
            'segmentSearchUsers',
            'analyzeSearchFunnel',
        ];

        foreach ($requiredPrivateMethods as $method) {
            $this->assertTrue(
                method_exists(MLAnalyticsIntelligenceService::class, $method),
                "MLAnalyticsIntelligenceService deve ter método {$method}()"
            );
        }
    }

    public function testHasRequiredDependencies(): void
    {
        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);

        $this->assertTrue($reflection->hasProperty('mlClient'), 'Deve ter propriedade mlClient');
        $this->assertTrue($reflection->hasProperty('db'), 'Deve ter propriedade db');
        $this->assertTrue($reflection->hasProperty('cache'), 'Deve ter propriedade cache');
        $this->assertTrue($reflection->hasProperty('accountId'), 'Deve ter propriedade accountId');
    }

    public function testSeasonalSearchPatternsReturnsExpectedStructure(): void
    {
        $method = new \ReflectionMethod(MLAnalyticsIntelligenceService::class, 'getSeasonalSearchPatterns');
        $method->setAccessible(true);

        // Instanciar via reflection sem construtor para testar método puro
        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $result = $method->invoke($instance, []);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('current_season', $result);
        $this->assertArrayHasKey('month', $result);
        $this->assertIsString($result['current_season']);
        $this->assertIsInt($result['month']);
        $this->assertGreaterThanOrEqual(1, $result['month']);
        $this->assertLessThanOrEqual(12, $result['month']);
    }

    public function testImplementationRoadmapHasWeeks(): void
    {
        $method = new \ReflectionMethod(MLAnalyticsIntelligenceService::class, 'generateImplementationRoadmap');
        $method->setAccessible(true);

        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $result = $method->invoke($instance, []);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('week', $result[0]);
        $this->assertArrayHasKey('action', $result[0]);
    }

    public function testConfidenceIntervalsWithEmptyData(): void
    {
        $method = new \ReflectionMethod(MLAnalyticsIntelligenceService::class, 'calculateConfidenceIntervals');
        $method->setAccessible(true);

        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $result = $method->invoke($instance, []);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('mean', $result);
        $this->assertArrayHasKey('std_dev', $result);
        $this->assertArrayHasKey('ci_95_lower', $result);
        $this->assertArrayHasKey('ci_95_upper', $result);
        $this->assertArrayHasKey('sample_size', $result);
        $this->assertSame(0, $result['sample_size']);
    }

    public function testConfidenceIntervalsWithData(): void
    {
        $method = new \ReflectionMethod(MLAnalyticsIntelligenceService::class, 'calculateConfidenceIntervals');
        $method->setAccessible(true);

        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $data = [
            ['sales' => 10],
            ['sales' => 12],
            ['sales' => 8],
            ['sales' => 15],
            ['sales' => 11],
        ];

        $result = $method->invoke($instance, $data);
        $this->assertArrayHasKey('mean', $result);
        $this->assertArrayHasKey('std_dev', $result);
        $this->assertArrayHasKey('ci_95_lower', $result);
        $this->assertArrayHasKey('ci_95_upper', $result);

        // Mean of [10,12,8,15,11] = 56/5 = 11.2
        $this->assertEqualsWithDelta(11.2, $result['mean'], 0.1);

        // CI upper must be > CI lower
        $this->assertGreaterThan($result['ci_95_lower'], $result['ci_95_upper']);
        $this->assertSame(5, $result['sample_size']);
    }

    public function testForecastProductDemandWithEmptyData(): void
    {
        $method = new \ReflectionMethod(MLAnalyticsIntelligenceService::class, 'forecastProductDemand');
        $method->setAccessible(true);

        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $result = $method->invoke($instance, [], []);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testForecastProductDemandGenerates30DayForecast(): void
    {
        $method = new \ReflectionMethod(MLAnalyticsIntelligenceService::class, 'forecastProductDemand');
        $method->setAccessible(true);

        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $historicalData = [];
        for ($i = 0; $i < 30; $i++) {
            $historicalData[] = ['sales' => rand(5, 20), 'date' => date('Y-m-d', strtotime("-{$i} days"))];
        }

        $result = $method->invoke($instance, $historicalData, []);
        $this->assertCount(30, $result);
        $this->assertArrayHasKey('day', $result[0]);
        $this->assertArrayHasKey('predicted_sales', $result[0]);
    }

    public function testDemandRiskFactorsWithInsufficientData(): void
    {
        $method = new \ReflectionMethod(MLAnalyticsIntelligenceService::class, 'identifyDemandRiskFactors');
        $method->setAccessible(true);

        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        // Menos de 30 registros — deve indicar risco
        $result = $method->invoke($instance, [['sales' => 5]], ['current_season' => 'regular']);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('insuficientes', $result[0]);
    }

    public function testDataFreshnessReturnsString(): void
    {
        $method = new \ReflectionMethod(MLAnalyticsIntelligenceService::class, 'getDataFreshness');
        $method->setAccessible(true);

        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        // Injetar um PDO em memória para evitar acesso a banco externo
        $pdo = new \PDO('sqlite::memory:');
        $dbProp = $reflection->getProperty('db');
        $dbProp->setAccessible(true);
        $dbProp->setValue($instance, $pdo);

        $accountProp = $reflection->getProperty('accountId');
        $accountProp->setAccessible(true);
        $accountProp->setValue($instance, 1);

        $result = $method->invoke($instance);
        $this->assertIsString($result);
        $this->assertNotSame('', trim($result));
    }

    public function testCalculatePredictionConfidenceReturnsDimensions(): void
    {
        $method = new \ReflectionMethod(MLAnalyticsIntelligenceService::class, 'calculatePredictionConfidence');
        $method->setAccessible(true);

        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $predictions = [
            'demand_forecasting' => ['confidence_intervals' => ['sample_size' => 45]],
            'market_trends' => ['category_trend_summary' => [['category_id' => 'MLB1']]],
            'inventory_needs' => ['avg_daily_sales' => 12.5],
            'seasonal_patterns' => ['month_adjustments' => [1 => 0.9, 11 => 1.3]],
        ];

        $result = $method->invoke($instance, $predictions);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('overall_confidence', $result);
        $this->assertArrayHasKey('confidence_by_dimension', $result);
        $this->assertArrayHasKey('methodology', $result);
        $this->assertGreaterThan(0, $result['overall_confidence']);
        $this->assertLessThanOrEqual(1, $result['overall_confidence']);
        $this->assertArrayHasKey('demand', $result['confidence_by_dimension']);
        $this->assertArrayHasKey('market', $result['confidence_by_dimension']);
    }

    public function testGenerateActionableInsightsHandlesTrendDirection(): void
    {
        $method = new \ReflectionMethod(MLAnalyticsIntelligenceService::class, 'generateActionableInsights');
        $method->setAccessible(true);

        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $predictions = [
            'demand_forecasting' => [
                'forecast' => [
                    'product_demand' => [
                        ['day' => 1, 'predicted_sales' => 10],
                        ['day' => 30, 'predicted_sales' => 14],
                    ],
                ],
            ],
            'inventory_needs' => ['reorder_point' => 120],
            'competitor_actions' => [
                ['category_id' => 'MLB123', 'likely_action' => 'aggressive_discounting'],
            ],
        ];

        $result = $method->invoke($instance, $predictions);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $joined = mb_strtolower(implode(' ', $result));
        $this->assertStringContainsString('demanda', $joined);
        $this->assertStringContainsString('reposição', $joined);
    }

    public function testAnalyticsSummaryCountsSections(): void
    {
        $method = new \ReflectionMethod(MLAnalyticsIntelligenceService::class, 'generateAnalyticsSummary');
        $method->setAccessible(true);

        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $analytics = [
            'section1' => ['data' => 'some'],
            'section2' => [],
            'section3' => ['more' => 'data'],
        ];

        $result = $method->invoke($instance, $analytics);
        $this->assertArrayHasKey('sections_populated', $result);
        $this->assertArrayHasKey('sections_empty', $result);
        $this->assertArrayHasKey('data_completeness', $result);
        $this->assertEquals(2, $result['sections_populated']);
        $this->assertEquals(1, $result['sections_empty']);
        $this->assertEqualsWithDelta(66.7, $result['data_completeness'], 0.1);
    }

    // =============================
    // TESTES PHASE 4 — Search Analytics (pure logic)
    // =============================

    public function testSearchByDeviceReturnsEstimatedDistribution(): void
    {
        $method = new \ReflectionMethod(MLAnalyticsIntelligenceService::class, 'getSearchByDevice');
        $method->setAccessible(true);

        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $searchData = ['MLB123' => ['celular', 'smartphone'], 'MLB456' => ['fone']];
        $result = $method->invoke($instance, $searchData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('mobile', $result);
        $this->assertArrayHasKey('desktop', $result);
        $this->assertArrayHasKey('tablet', $result);
        $this->assertEquals(72.0, $result['mobile']['estimated_share_pct']);
        $this->assertEquals(3, $result['total_terms_tracked']);
    }

    public function testSearchByLocationHasStateDistribution(): void
    {
        $method = new \ReflectionMethod(MLAnalyticsIntelligenceService::class, 'getSearchByLocation');
        $method->setAccessible(true);

        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $searchData = ['MLB123' => ['termo1', 'termo2']];
        $result = $method->invoke($instance, $searchData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('distribution', $result);
        $this->assertNotEmpty($result['distribution']);
        $this->assertEquals('SP', $result['distribution'][0]['state']);
        $this->assertEquals(35.0, $result['distribution'][0]['share_pct']);
    }

    public function testSearchByTimeHasHourlyAndDayDistribution(): void
    {
        $method = new \ReflectionMethod(MLAnalyticsIntelligenceService::class, 'getSearchByTime');
        $method->setAccessible(true);

        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $result = $method->invoke($instance, ['MLB123' => ['termo']]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('by_hour_range', $result);
        $this->assertArrayHasKey('by_day_of_week', $result);
        $this->assertArrayHasKey('peak_hours', $result);
        $this->assertEquals('14-18h', $result['peak_hours']);
        $this->assertEquals(1, $result['total_terms_tracked']);
    }

    public function testSearchByDeviceWithEmptyData(): void
    {
        $method = new \ReflectionMethod(MLAnalyticsIntelligenceService::class, 'getSearchByDevice');
        $method->setAccessible(true);

        $reflection = new \ReflectionClass(MLAnalyticsIntelligenceService::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $result = $method->invoke($instance, []);

        $this->assertEquals(0, $result['total_terms_tracked']);
        $this->assertEquals(0, $result['mobile']['estimated_searches']);
    }
}
