<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MercadoLivre;

use Tests\TestCase;
use App\Services\MercadoLivre\CompetitorIntelligenceService;

/**
 * Testes unitários para CompetitorIntelligenceService.
 * Verifica existência de todos os métodos e lógica pura.
 */
class CompetitorIntelligenceServiceTest extends TestCase
{
    // =============================
    // TESTES DE ESTRUTURA
    // =============================

    public function testHasRequiredPublicMethods(): void
    {
        $methods = [
            'startCompetitorMonitoring',
            'analyzeMarketOpportunities',
            'trackCompetitiveAdvantages',
            'generateIntelligenceReports',
            'realTimeMarketMonitoring',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(CompetitorIntelligenceService::class, $method),
                "CompetitorIntelligenceService deve ter método público {$method}()"
            );
        }
    }

    public function testHasAllPrivateHelperMethods(): void
    {
        $requiredPrivateMethods = [
            'monitorCompetitor',
            'analyzeCategoryOpportunities',
            'calculateCompetitiveAdvantage',
            'generateDailyCompetitorReport',
            'loadIntelligenceConfig',
            'generateWeeklyMarketAnalysis',
            'generateMonthlyOpportunityReport',
            'generatePriceCompetitionAnalysis',
            'generateMarketShareTrends',
            'generateEmergingThreatsReport',
            'getCompetitorsForMonitoring',
            'createCompetitorAlert',
            'generateMarketIntelligence',
            'generateMonitoringSummary',
            'getMonitoredCategories',
            'findCrossCategoryOpportunities',
            'calculateOpportunityScore',
            'getOurProducts',
            'getCompetitorProducts',
            'calculateOverallCompetitivePosition',
            'generateCompetitiveRecommendations',
            'generateActionPlan',
            'extractReportInsights',
            'extractActionItems',
            'assessInsightPriority',
            'getRealTimeMarketData',
            'detectSignificantChanges',
            'generateRealTimeAlerts',
            'calculateMarketPulse',
            'generateRealTimeRecommendations',
            'monitorCompetitorPrices',
            'monitorCompetitorListings',
            'monitorCompetitorAdvertising',
            'monitorCompetitorReputation',
            'getCategoryMarketData',
            'calculatePriceDistribution',
            'identifyCategoryGaps',
            'calculateCategoryOpportunityScore',
            'generateCategoryRecommendations',
            'estimateCategoryMarketSize',
            'assessCompetitionLevel',
            'calculateQualityScore',
            'calculateListingScore',
            'calculateServiceScore',
            'assessCompetitivePosition',
            'identifyProductStrengths',
            'identifyProductWeaknesses',
            'determineActionNeeded',
            'generateExecutiveSummary',
            'getActiveCompetitors',
            'getCompetitorChangesBetween',
            'mapAlertTypeToChangeType',
            'assessChangeImpact',
            'generateDailyMarketSummary',
            'generateDailyRecommendations',
        ];

        foreach ($requiredPrivateMethods as $method) {
            $this->assertTrue(
                method_exists(CompetitorIntelligenceService::class, $method),
                "CompetitorIntelligenceService deve ter método {$method}()"
            );
        }
    }

    public function testHasRequiredDependencies(): void
    {
        $reflection = new \ReflectionClass(CompetitorIntelligenceService::class);

        $this->assertTrue($reflection->hasProperty('mlClient'), 'Deve ter propriedade mlClient');
        $this->assertTrue($reflection->hasProperty('db'), 'Deve ter propriedade db');
        $this->assertTrue($reflection->hasProperty('cache'), 'Deve ter propriedade cache');
        $this->assertTrue($reflection->hasProperty('accountId'), 'Deve ter propriedade accountId');
    }

    // =============================
    // TESTES DE LÓGICA PURA
    // =============================

    public function testCalculateOpportunityScoreWithEmptyData(): void
    {
        $method = new \ReflectionMethod(CompetitorIntelligenceService::class, 'calculateOpportunityScore');
        $method->setAccessible(true);

        $instance = (new \ReflectionClass(CompetitorIntelligenceService::class))->newInstanceWithoutConstructor();

        $result = $method->invoke($instance, [], []);
        $this->assertEquals(0.0, $result);
    }

    public function testCalculateOpportunityScoreWithData(): void
    {
        $method = new \ReflectionMethod(CompetitorIntelligenceService::class, 'calculateOpportunityScore');
        $method->setAccessible(true);

        $instance = (new \ReflectionClass(CompetitorIntelligenceService::class))->newInstanceWithoutConstructor();

        $opportunities = [
            ['opportunity_score' => 5.0],
            ['opportunity_score' => 7.0],
            ['opportunity_score' => 3.0],
        ];
        $crossCategory = [['x' => 1], ['y' => 2]];

        $result = $method->invoke($instance, $opportunities, $crossCategory);
        // avg = (5+7+3)/3 = 5.0, crossBonus = 2*0.5 = 1.0 => 6.0
        $this->assertEqualsWithDelta(6.0, $result, 0.01);
    }

    public function testCalculateOpportunityScoreCapsAt10(): void
    {
        $method = new \ReflectionMethod(CompetitorIntelligenceService::class, 'calculateOpportunityScore');
        $method->setAccessible(true);

        $instance = (new \ReflectionClass(CompetitorIntelligenceService::class))->newInstanceWithoutConstructor();

        $opportunities = [['opportunity_score' => 9.5]];
        $crossCategory = array_fill(0, 10, ['x' => 1]); // 10 * 0.5 = 5.0

        $result = $method->invoke($instance, $opportunities, $crossCategory);
        $this->assertEquals(10.0, $result);
    }

    public function testCalculateOverallCompetitivePositionEmpty(): void
    {
        $method = new \ReflectionMethod(CompetitorIntelligenceService::class, 'calculateOverallCompetitivePosition');
        $method->setAccessible(true);

        $instance = (new \ReflectionClass(CompetitorIntelligenceService::class))->newInstanceWithoutConstructor();

        $result = $method->invoke($instance, []);
        $this->assertEquals('unknown', $result['position']);
        $this->assertEquals(0, $result['score']);
    }

    public function testCalculateOverallCompetitivePositionLeader(): void
    {
        $method = new \ReflectionMethod(CompetitorIntelligenceService::class, 'calculateOverallCompetitivePosition');
        $method->setAccessible(true);

        $instance = (new \ReflectionClass(CompetitorIntelligenceService::class))->newInstanceWithoutConstructor();

        $advantages = [
            ['advantages' => ['overall_advantage' => 0.5]],
            ['advantages' => ['overall_advantage' => 0.4]],
        ];

        $result = $method->invoke($instance, $advantages);
        $this->assertEquals('leader', $result['position']);
        $this->assertEquals(2, $result['strong_products']);
        $this->assertEquals(0, $result['weak_products']);
        $this->assertEquals('improving', $result['trend']);
    }

    public function testCalculateOverallCompetitivePositionWeak(): void
    {
        $method = new \ReflectionMethod(CompetitorIntelligenceService::class, 'calculateOverallCompetitivePosition');
        $method->setAccessible(true);

        $instance = (new \ReflectionClass(CompetitorIntelligenceService::class))->newInstanceWithoutConstructor();

        $advantages = [
            ['advantages' => ['overall_advantage' => -0.3]],
            ['advantages' => ['overall_advantage' => -0.2]],
        ];

        $result = $method->invoke($instance, $advantages);
        $this->assertEquals('weak', $result['position']);
        $this->assertEquals('declining', $result['trend']);
    }

    public function testAssessInsightPriority(): void
    {
        $method = new \ReflectionMethod(CompetitorIntelligenceService::class, 'assessInsightPriority');
        $method->setAccessible(true);

        $instance = (new \ReflectionClass(CompetitorIntelligenceService::class))->newInstanceWithoutConstructor();

        // Should return a string priority
        $result = $method->invoke($instance, 'Preço do concorrente caiu drasticamente');
        $this->assertIsString($result);
        $this->assertContains($result, ['critical', 'high', 'medium', 'low']);
    }

    public function testGenerateActionPlanStructure(): void
    {
        $method = new \ReflectionMethod(CompetitorIntelligenceService::class, 'generateActionPlan');
        $method->setAccessible(true);

        $instance = (new \ReflectionClass(CompetitorIntelligenceService::class))->newInstanceWithoutConstructor();

        $recommendations = [
            ['priority' => 'high', 'message' => 'Reduzir preço', 'type' => 'price_adjustment', 'product_id' => 'MLB1'],
            ['priority' => 'medium', 'message' => 'Melhorar anúncio', 'type' => 'listing_optimization', 'product_id' => 'MLB2'],
            ['priority' => 'low', 'message' => 'Monitorar marca', 'type' => 'brand_monitoring'],
        ];

        $result = $method->invoke($instance, $recommendations);
        $this->assertArrayHasKey('immediate', $result);
        $this->assertArrayHasKey('short_term', $result);
        $this->assertArrayHasKey('long_term', $result);
        $this->assertCount(1, $result['immediate']);
        $this->assertCount(1, $result['short_term']);
        $this->assertCount(1, $result['long_term']);
    }

    public function testMapAlertTypeToChangeType(): void
    {
        $method = new \ReflectionMethod(CompetitorIntelligenceService::class, 'mapAlertTypeToChangeType');
        $method->setAccessible(true);

        $instance = (new \ReflectionClass(CompetitorIntelligenceService::class))->newInstanceWithoutConstructor();

        $result = $method->invoke($instance, 'price_drop');
        $this->assertIsString($result);
    }

    public function testCalculatePriceDistribution(): void
    {
        $method = new \ReflectionMethod(CompetitorIntelligenceService::class, 'calculatePriceDistribution');
        $method->setAccessible(true);

        $instance = (new \ReflectionClass(CompetitorIntelligenceService::class))->newInstanceWithoutConstructor();

        $prices = [10.0, 20.0, 30.0, 40.0, 50.0];

        $result = $method->invoke($instance, $prices);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('p10', $result);
        $this->assertArrayHasKey('p50', $result);
        $this->assertArrayHasKey('p90', $result);
    }

    public function testAssessChangeImpact(): void
    {
        $method = new \ReflectionMethod(CompetitorIntelligenceService::class, 'assessChangeImpact');
        $method->setAccessible(true);

        $instance = (new \ReflectionClass(CompetitorIntelligenceService::class))->newInstanceWithoutConstructor();

        $result = $method->invoke($instance, []);
        $this->assertIsString($result);
        $this->assertContains($result, ['critical', 'high', 'medium', 'low']);
    }
}
