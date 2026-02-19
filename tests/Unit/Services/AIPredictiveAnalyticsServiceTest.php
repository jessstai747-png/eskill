<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AIPredictiveAnalyticsService;
use ReflectionClass;
use ReflectionMethod;

/**
 * Testes unitários para AIPredictiveAnalyticsService.
 * Foca em métodos de lógica pura (sem dependência de DB/API).
 */
class AIPredictiveAnalyticsServiceTest extends TestCase
{
    private AIPredictiveAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $reflection = new ReflectionClass(AIPredictiveAnalyticsService::class);
        $this->service = $reflection->newInstanceWithoutConstructor();
    }

    private function invoke(string $method, ...$args)
    {
        $ref = new ReflectionMethod(AIPredictiveAnalyticsService::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke($this->service, ...$args);
    }

    // =============================
    // TESTES DE ESTRUTURA
    // =============================

    public function testHasAllPublicMethods(): void
    {
        $methods = [
            'predictProductPerformance',
            'predictMarketDemand',
            'predictOptimalPricing',
        ];

        $ref = new ReflectionClass(AIPredictiveAnalyticsService::class);
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
            'identifyCurrentStage',
            'predictStageTransitions',
            'getLifecycleStrategies',
            'calculateStageConfidence',
            'generateLifecycleTimeline',
            'identifyLifecycleRisks',
            'analyzeMarketRisks',
            'analyzeCompetitiveRisks',
            'analyzeSupplyChainRisks',
            'analyzeRegulatoryRisks',
            'analyzeEconomicRisks',
            'analyzeSeasonalRisks',
            'calculateOverallRisk',
            'generateMitigationPlan',
            'generateRiskAlerts',
            'getRiskLevel',
            'getFactorCoefficient',
            'getMarketContext',
            'calculatePredictionConfidence',
            'generatePredictiveInsights',
            'generatePredictiveRecommendations',
            'getExternalFactors',
            'identifyInfluenceFactors',
            'generateScenarios',
            'evaluateModelPerformance',
        ];

        $ref = new ReflectionClass(AIPredictiveAnalyticsService::class);
        foreach ($methods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "Método privado '{$method}' não encontrado"
            );
        }
    }

    // =============================
    // LIFECYCLE — identifyCurrentStage
    // =============================

    public function testIdentifyCurrentStageIntroductionWhenFewData(): void
    {
        $product = ['id' => 'MLB1'];
        $historical = ['sales_vector' => [1, 0, 1, 0, 0], 'data_points' => 5];
        $result = $this->invoke('identifyCurrentStage', $product, $historical);
        $this->assertEquals('introduction', $result);
    }

    public function testIdentifyCurrentStageDeclineWhenRecentSalesZero(): void
    {
        $product = ['id' => 'MLB1'];
        // 14 data points, earlier has sales but recent 7 are zero → decline
        $sales = array_fill(0, 14, 0);
        $sales[0] = 1;
        $sales[5] = 1;
        $historical = ['sales_vector' => $sales, 'data_points' => 14];
        $result = $this->invoke('identifyCurrentStage', $product, $historical);
        $this->assertEquals('decline', $result);
    }

    public function testIdentifyCurrentStageGrowth(): void
    {
        $product = ['id' => 'MLB1'];
        // Earlier avg low, recent avg high => growth
        $earlier = array_fill(0, 23, 2);
        $recent = array_fill(0, 7, 10);
        $sales = array_merge($earlier, $recent);
        $historical = ['sales_vector' => $sales, 'data_points' => 30];
        $result = $this->invoke('identifyCurrentStage', $product, $historical);
        $this->assertEquals('growth', $result);
    }

    public function testIdentifyCurrentStageDecline(): void
    {
        $product = ['id' => 'MLB1'];
        // Earlier avg high, recent avg low => decline
        $earlier = array_fill(0, 23, 10);
        $recent = array_fill(0, 7, 2);
        $sales = array_merge($earlier, $recent);
        $historical = ['sales_vector' => $sales, 'data_points' => 30];
        $result = $this->invoke('identifyCurrentStage', $product, $historical);
        $this->assertEquals('decline', $result);
    }

    public function testIdentifyCurrentStageMaturityWhenStable(): void
    {
        $product = ['id' => 'MLB1'];
        // Similar earlier and recent avg => maturity
        $sales = array_fill(0, 30, 5);
        $historical = ['sales_vector' => $sales, 'data_points' => 30];
        $result = $this->invoke('identifyCurrentStage', $product, $historical);
        $this->assertEquals('maturity', $result);
    }

    public function testIdentifyCurrentStageEmptySalesVector(): void
    {
        $result = $this->invoke('identifyCurrentStage', ['id' => 'X'], ['sales_vector' => []]);
        $this->assertEquals('introduction', $result);
    }

    // =============================
    // LIFECYCLE — getLifecycleStrategies
    // =============================

    public function testLifecycleStrategiesReturnsAllKeysForEachStage(): void
    {
        $stages = ['introduction', 'growth', 'maturity', 'decline'];
        $transitions = ['next_stage' => 'growth'];

        foreach ($stages as $stage) {
            $result = $this->invoke('getLifecycleStrategies', $stage, $transitions);
            $this->assertArrayHasKey('focus', $result, "Stage '{$stage}' sem 'focus'");
            $this->assertArrayHasKey('pricing', $result);
            $this->assertArrayHasKey('advertising', $result);
            $this->assertArrayHasKey('seo', $result);
            $this->assertArrayHasKey('inventory', $result);
            $this->assertArrayHasKey('prepare_for', $result);
        }
    }

    public function testLifecycleStrategiesFocusByStage(): void
    {
        $t = ['next_stage' => 'growth'];
        $this->assertEquals('visibility', $this->invoke('getLifecycleStrategies', 'introduction', $t)['focus']);
        $this->assertEquals('market_expansion', $this->invoke('getLifecycleStrategies', 'growth', $t)['focus']);
        $this->assertEquals('profit_optimization', $this->invoke('getLifecycleStrategies', 'maturity', $t)['focus']);
        $this->assertEquals('harvest_or_revitalize', $this->invoke('getLifecycleStrategies', 'decline', $t)['focus']);
    }

    // =============================
    // LIFECYCLE — calculateStageConfidence
    // =============================

    public function testStageConfidenceHighQuality90PlusPoints(): void
    {
        $historical = ['quality' => 'high', 'data_points' => 100];
        $result = $this->invoke('calculateStageConfidence', 'maturity', $historical);
        // base 0.80 + 0.10 (>90 pts) + 0.05 (maturity) = 0.95
        $this->assertEquals(0.95, $result);
    }

    public function testStageConfidenceLowQualityFewPoints(): void
    {
        $historical = ['quality' => 'low', 'data_points' => 10];
        $result = $this->invoke('calculateStageConfidence', 'introduction', $historical);
        // base 0.45 + 0 (few pts) - 0.05 (introduction) = 0.40
        $this->assertEquals(0.40, $result);
    }

    public function testStageConfidenceBounds(): void
    {
        // Cannot exceed 0.95
        $highResult = $this->invoke('calculateStageConfidence', 'growth', ['quality' => 'high', 'data_points' => 200]);
        $this->assertLessThanOrEqual(0.95, $highResult);

        // Cannot go below 0.30
        $lowResult = $this->invoke('calculateStageConfidence', 'decline', ['quality' => 'low', 'data_points' => 1]);
        $this->assertGreaterThanOrEqual(0.30, $lowResult);
    }

    // =============================
    // LIFECYCLE — generateLifecycleTimeline
    // =============================

    public function testLifecycleTimelineHasPhases(): void
    {
        $transitions = ['next_stage' => 'growth', 'timeline' => '1-3 months', 'probability' => 0.70];
        $result = $this->invoke('generateLifecycleTimeline', $transitions);

        $this->assertArrayHasKey('phases', $result);
        $this->assertArrayHasKey('generated_at', $result);
        $this->assertGreaterThanOrEqual(1, count($result['phases']));

        // First phase should be the next stage
        $this->assertEquals('growth', $result['phases'][0]['stage']);
        $this->assertEquals(0.70, $result['phases'][0]['probability']);
    }

    public function testLifecycleTimelineTwoPhasesWhenNextHasSuccessor(): void
    {
        // growth → maturity → decline
        $transitions = ['next_stage' => 'growth', 'timeline' => '1-3 months', 'probability' => 0.70];
        $result = $this->invoke('generateLifecycleTimeline', $transitions);

        $this->assertCount(2, $result['phases']);
        $this->assertEquals('maturity', $result['phases'][1]['stage']);
        // Second phase probability = 0.70 * 0.6 = 0.42
        $this->assertEquals(0.42, $result['phases'][1]['probability']);
    }

    public function testLifecycleTimelineOnePhaseForEndOfLife(): void
    {
        // decline → end_of_life (no successor)
        $transitions = ['next_stage' => 'end_of_life', 'timeline' => '3-6 months', 'probability' => 0.60];
        $result = $this->invoke('generateLifecycleTimeline', $transitions);
        $this->assertCount(1, $result['phases']);
    }

    // =============================
    // LIFECYCLE — identifyLifecycleRisks
    // =============================

    public function testLifecycleRisksForEachStage(): void
    {
        $stages = ['introduction', 'growth', 'maturity', 'decline'];

        foreach ($stages as $stage) {
            $result = $this->invoke('identifyLifecycleRisks', $stage, ['velocity' => 0]);
            $this->assertIsArray($result);
            $this->assertGreaterThanOrEqual(2, count($result), "Stage '{$stage}' deveria ter pelo menos 2 riscos");
            $this->assertArrayHasKey('risk', $result[0]);
            $this->assertArrayHasKey('description', $result[0]);
            $this->assertArrayHasKey('mitigation', $result[0]);
        }
    }

    public function testLifecycleRisksRapidDeclineAddedWhenVelocityLow(): void
    {
        $result = $this->invoke('identifyLifecycleRisks', 'maturity', ['velocity' => -0.25]);
        $riskNames = array_column($result, 'risk');
        $this->assertContains('rapid_decline', $riskNames);
    }

    public function testLifecycleRisksNoRapidDeclineWhenVelocityOK(): void
    {
        $result = $this->invoke('identifyLifecycleRisks', 'maturity', ['velocity' => -0.10]);
        $riskNames = array_column($result, 'risk');
        $this->assertNotContains('rapid_decline', $riskNames);
    }

    // =============================
    // RISK — getRiskLevel
    // =============================

    public function testGetRiskLevelThresholds(): void
    {
        $this->assertEquals('high', $this->invoke('getRiskLevel', 0.7));
        $this->assertEquals('high', $this->invoke('getRiskLevel', 0.61));
        $this->assertEquals('medium', $this->invoke('getRiskLevel', 0.5));
        $this->assertEquals('medium', $this->invoke('getRiskLevel', 0.31));
        $this->assertEquals('low', $this->invoke('getRiskLevel', 0.3));
        $this->assertEquals('low', $this->invoke('getRiskLevel', 0.1));
        $this->assertEquals('low', $this->invoke('getRiskLevel', 0));
    }

    // =============================
    // RISK — calculateOverallRisk
    // =============================

    public function testCalculateOverallRiskAverage(): void
    {
        $risks = [
            ['score' => 0.4],
            ['score' => 0.6],
            ['score' => 0.2],
        ];
        $result = $this->invoke('calculateOverallRisk', $risks);
        $this->assertEqualsWithDelta(0.4, $result, 0.001);
    }

    public function testCalculateOverallRiskEmptyArray(): void
    {
        $result = $this->invoke('calculateOverallRisk', []);
        $this->assertEquals(0, $result);
    }

    // =============================
    // RISK — analyzeRegulatoryRisks
    // =============================

    public function testRegulatoryRisksRegulatedProduct(): void
    {
        $product = ['title' => 'Bateria Notebook Dell Inspiron'];
        $result = $this->invoke('analyzeRegulatoryRisks', $product);
        $this->assertGreaterThan(0.05, $result['score']);
        $this->assertNotEmpty($result['factors']);
        $this->assertArrayHasKey('level', $result);
    }

    public function testRegulatoryRisksElectricalProduct(): void
    {
        $product = ['title' => 'Carregador USB-C 65W Bivolt'];
        $result = $this->invoke('analyzeRegulatoryRisks', $product);
        // Should detect both "carregador" and "bivolt"
        $this->assertGreaterThanOrEqual(0.15, $result['score']);
    }

    public function testRegulatoryRisksNonRegulatedProduct(): void
    {
        $product = ['title' => 'Capa de Celular Transparente'];
        $result = $this->invoke('analyzeRegulatoryRisks', $product);
        $this->assertEquals(0.05, $result['score']);
        $this->assertContains('Sem riscos regulatórios significativos identificados', $result['factors']);
    }

    // =============================
    // RISK — analyzeMarketRisks
    // =============================

    public function testMarketRisksHighVolatilityNegativeTrend(): void
    {
        $product = ['id' => 'MLB1'];
        $context = ['trend' => 'negative', 'volatility' => 'high', 'market_saturation' => 'high'];
        $result = $this->invoke('analyzeMarketRisks', $product, $context);

        $this->assertGreaterThanOrEqual(0.60, $result['score']);
        $this->assertEquals('high', $result['level']);
        $this->assertNotEmpty($result['factors']);
    }

    public function testMarketRisksLowVolatilityStableTrend(): void
    {
        $product = ['id' => 'MLB1'];
        $context = ['trend' => 'stable', 'volatility' => 'low', 'market_saturation' => 'low'];
        $result = $this->invoke('analyzeMarketRisks', $product, $context);

        $this->assertLessThanOrEqual(0.30, $result['score']);
        $this->assertEquals('low', $result['level']);
    }

    // =============================
    // RISK — analyzeCompetitiveRisks
    // =============================

    public function testCompetitiveRisksHighCompetition(): void
    {
        $product = ['id' => 'MLB1'];
        $context = ['competitor_count' => 60, 'trend' => 'negative'];
        $result = $this->invoke('analyzeCompetitiveRisks', $product, $context);

        $this->assertGreaterThanOrEqual(0.50, $result['score']);
        $this->assertNotEmpty($result['factors']);
    }

    public function testCompetitiveRisksLowCompetition(): void
    {
        $product = ['id' => 'MLB1'];
        $context = ['competitor_count' => 5, 'trend' => 'stable'];
        $result = $this->invoke('analyzeCompetitiveRisks', $product, $context);

        $this->assertLessThanOrEqual(0.30, $result['score']);
    }

    // =============================
    // RISK — analyzeEconomicRisks
    // =============================

    public function testEconomicRisksStructure(): void
    {
        $context = ['volatility' => 'high', 'trend' => 'negative'];
        $result = $this->invoke('analyzeEconomicRisks', $context);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('factors', $result);
        $this->assertArrayHasKey('level', $result);
        $this->assertGreaterThan(0.15, $result['score']);
    }

    public function testEconomicRisksAlwaysHasMonitoringFactor(): void
    {
        $result = $this->invoke('analyzeEconomicRisks', []);
        $this->assertContains('Monitorar indicadores: inflação, taxa de juros, câmbio', $result['factors']);
    }

    // =============================
    // RISK — analyzeSupplyChainRisks
    // =============================

    public function testSupplyChainRisksCriticalStock(): void
    {
        $product = ['available_quantity' => 5];
        $historical = ['sales_vector' => array_fill(0, 14, 3)]; // 3/dia, 5 unidades = ~1.7 dias
        $result = $this->invoke('analyzeSupplyChainRisks', $product, $historical);

        $this->assertGreaterThanOrEqual(0.40, $result['score']);
    }

    public function testSupplyChainRisksAbundantStock(): void
    {
        $product = ['available_quantity' => 500];
        $historical = ['sales_vector' => array_fill(0, 7, 1)]; // 1/dia, 500 unidades = 500 dias
        $result = $this->invoke('analyzeSupplyChainRisks', $product, $historical);

        $this->assertLessThanOrEqual(0.20, $result['score']);
    }

    // =============================
    // RISK — generateMitigationPlan
    // =============================

    public function testMitigationPlanSortedByPriority(): void
    {
        $risks = [
            'economic_risks' => ['score' => 0.2, 'factors' => []],
            'market_risks' => ['score' => 0.7, 'factors' => []],
            'competitive_risks' => ['score' => 0.4, 'factors' => []],
        ];
        $result = $this->invoke('generateMitigationPlan', $risks);

        $this->assertArrayHasKey('actions', $result);
        $this->assertArrayHasKey('total_risks_assessed', $result);
        $this->assertEquals(3, $result['total_risks_assessed']);

        // First action should be from market_risks (highest score = critical)
        $this->assertEquals('critical', $result['actions'][0]['priority']);
        $this->assertEquals('market_risks', $result['actions'][0]['risk_type']);
    }

    public function testMitigationPlanEmptyRisks(): void
    {
        $result = $this->invoke('generateMitigationPlan', []);
        $this->assertEquals(0, $result['total_risks_assessed']);
        $this->assertEmpty($result['actions']);
    }

    // =============================
    // RISK — generateRiskAlerts
    // =============================

    public function testRiskAlertsHighScore(): void
    {
        $risks = [
            'market_risks' => ['score' => 0.6, 'factors' => ['Alta volatilidade']],
        ];
        $result = $this->invoke('generateRiskAlerts', $risks);

        $this->assertCount(1, $result);
        $this->assertEquals('critical', $result[0]['level']);
        $this->assertTrue($result[0]['action_required']);
        $this->assertEquals('Risco de Mercado', $result[0]['label']);
    }

    public function testRiskAlertsNoAlertsWhenLowScores(): void
    {
        $risks = [
            'market_risks' => ['score' => 0.1, 'factors' => []],
            'economic_risks' => ['score' => 0.2, 'factors' => []],
        ];
        $result = $this->invoke('generateRiskAlerts', $risks);

        // Should return "all_clear" info alert
        $this->assertCount(1, $result);
        $this->assertEquals('info', $result[0]['level']);
        $this->assertEquals('all_clear', $result[0]['type']);
    }

    public function testRiskAlertsWarningLevel(): void
    {
        $risks = [
            'competitive_risks' => ['score' => 0.35, 'factors' => ['Concorrência alta']],
        ];
        $result = $this->invoke('generateRiskAlerts', $risks);

        $this->assertCount(1, $result);
        $this->assertEquals('warning', $result[0]['level']);
        $this->assertFalse($result[0]['action_required']);
    }

    // =============================
    // SCENARIOS — generateScenarios
    // =============================

    public function testGenerateScenariosBasicForecast(): void
    {
        $forecast = [10, 12, 11, 13, 10, 12];
        $factors = ['trend' => 0.5, 'seasonality' => 0.1];
        $result = $this->invoke('generateScenarios', $forecast, $factors);

        $this->assertArrayHasKey('optimistic', $result);
        $this->assertArrayHasKey('base', $result);
        $this->assertArrayHasKey('pessimistic', $result);

        $this->assertGreaterThan($result['base']['value'], $result['optimistic']['value']);
        $this->assertLessThan($result['base']['value'], $result['pessimistic']['value']);
        $this->assertEquals(0.60, $result['base']['probability']);
    }

    public function testGenerateScenariosEmptyForecast(): void
    {
        $result = $this->invoke('generateScenarios', [], []);
        $this->assertEquals(100, $result['optimistic']['value']);
        $this->assertEquals(100, $result['base']['value']);
        $this->assertEquals(100, $result['pessimistic']['value']);
    }

    public function testGenerateScenariosPessimisticNeverNegative(): void
    {
        $forecast = [1, 1, 1, 1, 100]; // High variance
        $factors = ['trend' => 0, 'seasonality' => 0];
        $result = $this->invoke('generateScenarios', $forecast, $factors);

        $this->assertGreaterThanOrEqual(0, $result['pessimistic']['value']);
    }

    // =============================
    // CONFIDENCE — calculatePredictionConfidence
    // =============================

    public function testPredictionConfidenceHighData(): void
    {
        $predictions = [
            ['avg_daily_sales' => 10],
            ['avg_daily_sales' => 11],
            ['avg_daily_sales' => 10.5],
        ];
        $historical = ['data_points' => 100, 'quality' => 'high'];
        $result = $this->invoke('calculatePredictionConfidence', $predictions, $historical);

        $this->assertArrayHasKey('overall_confidence', $result);
        $this->assertArrayHasKey('model_agreement', $result);
        $this->assertArrayHasKey('reliability', $result);
        $this->assertEquals('high', $result['data_quality']);
        $this->assertEquals(100, $result['data_points']);
        $this->assertGreaterThanOrEqual(0.80, $result['overall_confidence']);
    }

    public function testPredictionConfidenceLowData(): void
    {
        $predictions = [['avg_daily_sales' => 5]];
        $historical = ['data_points' => 10, 'quality' => 'low'];
        $result = $this->invoke('calculatePredictionConfidence', $predictions, $historical);

        $this->assertLessThanOrEqual(0.60, $result['overall_confidence']);
        $this->assertEquals('low', $result['reliability']);
    }

    public function testPredictionConfidenceMaxCap(): void
    {
        // Even with best inputs, should cap at 0.95
        $predictions = [['avg_daily_sales' => 10], ['avg_daily_sales' => 10]];
        $historical = ['data_points' => 500, 'quality' => 'high'];
        $result = $this->invoke('calculatePredictionConfidence', $predictions, $historical);

        $this->assertLessThanOrEqual(0.95, $result['overall_confidence']);
    }

    // =============================
    // INSIGHTS — generatePredictiveInsights
    // =============================

    public function testPredictiveInsightsIncreasingDemand(): void
    {
        $consolidated = ['forecast' => 'increasing'];
        $confidence = ['overall_confidence' => 0.85];
        $result = $this->invoke('generatePredictiveInsights', $consolidated, $confidence);

        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertEquals('demand', $result[0]['type']);
        $this->assertEquals('medium', $result[0]['priority']);
    }

    public function testPredictiveInsightsDecreasingDemandHighPriority(): void
    {
        $consolidated = ['forecast' => 'decreasing'];
        $confidence = ['overall_confidence' => 0.70];
        $result = $this->invoke('generatePredictiveInsights', $consolidated, $confidence);

        $this->assertEquals('high', $result[0]['priority']);
    }

    public function testPredictiveInsightsDeclineLifecycle(): void
    {
        $consolidated = ['forecast' => 'stable', 'current_stage' => 'decline'];
        $confidence = ['overall_confidence' => 0.60];
        $result = $this->invoke('generatePredictiveInsights', $consolidated, $confidence);

        $types = array_column($result, 'type');
        $this->assertContains('lifecycle', $types);

        $lifecycleInsight = array_filter($result, fn($i) => $i['type'] === 'lifecycle');
        $lifecycleInsight = array_values($lifecycleInsight)[0];
        $this->assertEquals('high', $lifecycleInsight['priority']);
    }

    public function testPredictiveInsightsEmptyGivesGeneralFallback(): void
    {
        $result = $this->invoke('generatePredictiveInsights', [], ['overall_confidence' => 0.3]);
        $this->assertCount(1, $result);
        $this->assertEquals('general', $result[0]['type']);
    }

    // =============================
    // RECOMMENDATIONS — generatePredictiveRecommendations
    // =============================

    public function testRecommendationsDecreasingDemand(): void
    {
        $consolidated = ['forecast' => 'decreasing'];
        $insights = [['type' => 'demand', 'priority' => 'high']];
        $result = $this->invoke('generatePredictiveRecommendations', $consolidated, $insights);

        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertEquals('high', $result[0]['priority']);
    }

    public function testRecommendationsIncreasingDemandTwoActions(): void
    {
        $consolidated = ['forecast' => 'increasing'];
        $insights = [['type' => 'demand', 'priority' => 'medium']];
        $result = $this->invoke('generatePredictiveRecommendations', $consolidated, $insights);

        $this->assertGreaterThanOrEqual(2, count($result));
    }

    public function testRecommendationsDeclineLifecycle(): void
    {
        $consolidated = ['current_stage' => 'decline'];
        $insights = [['type' => 'lifecycle', 'priority' => 'high']];
        $result = $this->invoke('generatePredictiveRecommendations', $consolidated, $insights);

        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertEquals('high', $result[0]['priority']);
    }

    public function testRecommendationsEmptyInsightsFallback(): void
    {
        $result = $this->invoke('generatePredictiveRecommendations', [], []);
        $this->assertCount(1, $result);
        $this->assertEquals('low', $result[0]['priority']);
    }

    public function testRecommendationsSortedByPriority(): void
    {
        $consolidated = ['forecast' => 'increasing', 'current_stage' => 'decline'];
        $insights = [
            ['type' => 'demand', 'priority' => 'medium'],
            ['type' => 'lifecycle', 'priority' => 'high'],
        ];
        $result = $this->invoke('generatePredictiveRecommendations', $consolidated, $insights);

        // "high" recommendations should come before "medium"
        $firstPriority = $result[0]['priority'];
        $this->assertEquals('high', $firstPriority);
    }

    // =============================
    // UTILITIES — getFactorCoefficient
    // =============================

    public function testGetFactorCoefficientKnownFactors(): void
    {
        $this->assertEquals(0.5, $this->invoke('getFactorCoefficient', 'seasonality'));
        $this->assertEquals(1.2, $this->invoke('getFactorCoefficient', 'trend'));
    }

    public function testGetFactorCoefficientUnknownFactor(): void
    {
        $this->assertEquals(0.1, $this->invoke('getFactorCoefficient', 'unknown_factor'));
    }
}
