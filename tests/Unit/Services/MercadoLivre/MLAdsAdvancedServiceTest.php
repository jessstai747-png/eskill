<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MercadoLivre;

use Tests\TestCase;
use App\Services\MercadoLivre\MLAdsAdvancedService;

/**
 * Testes unitários para MLAdsAdvancedService.
 * Verifica existência de todos os métodos e lógica pura de cálculos de ads.
 */
class MLAdsAdvancedServiceTest extends TestCase
{
    // =============================
    // TESTES DE ESTRUTURA
    // =============================

    public function testHasRequiredPublicMethods(): void
    {
        $methods = [
            'optimizeCampaigns',
            'manageBids',
            'setupAdvancedTargeting',
            'setupCrossProductUpselling',
            'automateBudgets',
            'getPerformanceAnalytics',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(MLAdsAdvancedService::class, $method),
                "MLAdsAdvancedService deve ter método público {$method}()"
            );
        }
    }

    public function testHasAllPrivateHelperMethods(): void
    {
        $requiredPrivateMethods = [
            'optimizeSingleCampaign',
            'calculateOptimalBid',
            'createBehavioralAudiences',
            'setupRetargeting',
            'findRelatedProducts',
            'getAllActiveCampaignIds',
            'getCampaignPerformanceData',
            'loadAdsConfig',
            'getActiveCampaignsWithItems',
            'getAllCampaignsWithMetrics',
            'calculateOptimalBudget',
            'applyCampaignOptimizations',
            'generateOptimizationSummary',
            'analyzeCampaignPerformance',
            'generateCampaignRecommendations',
            'estimateImprovement',
            'calculateRecommendationConfidence',
            'applyBidAdjustment',
            'estimateBidImpact',
            'getItemBidPerformance',
            'calculateConversionRateFactor',
            'getCompetitionLevel',
            'getBudgetUtilization',
            'getTimeOfDayFactor',
            'getDayOfWeekFactor',
            'calculateBidAdjustment',
            'applyBudgetChange',
            'applyTargetingConfiguration',
            'calculateTotalReach',
            'estimateAudienceSize',
            'setupDemographicTargeting',
            'setupGeographicTargeting',
            'setupInterestBasedTargeting',
            'getCategoryProducts',
            'getFrequentlyBoughtTogether',
            'getComplementaryProducts',
            'getUpgradeProducts',
            'calculateRelatedProductScore',
            'generateUpsellConfiguration',
            'createUpsellCampaign',
            'getPerformanceOverview',
            'getCampaignPerformance',
            'getKeywordPerformance',
            'getAudiencePerformance',
            'getROIAnalysis',
            'getOptimizationOpportunities',
            'getCompetitorAdAnalysis',
            'getDataFreshness',
        ];

        foreach ($requiredPrivateMethods as $method) {
            $this->assertTrue(
                method_exists(MLAdsAdvancedService::class, $method),
                "MLAdsAdvancedService deve ter método {$method}()"
            );
        }
    }

    public function testHasRequiredDependencies(): void
    {
        $reflection = new \ReflectionClass(MLAdsAdvancedService::class);

        $this->assertTrue($reflection->hasProperty('mlClient'), 'Deve ter propriedade mlClient');
        $this->assertTrue($reflection->hasProperty('db'), 'Deve ter propriedade db');
        $this->assertTrue($reflection->hasProperty('cache'), 'Deve ter propriedade cache');
        $this->assertTrue($reflection->hasProperty('accountId'), 'Deve ter propriedade accountId');
    }

    // =============================
    // TESTES DE LÓGICA PURA
    // =============================

    private function getInstance(): MLAdsAdvancedService
    {
        return (new \ReflectionClass(MLAdsAdvancedService::class))->newInstanceWithoutConstructor();
    }

    private function invokePrivate(string $method, ...$args)
    {
        $ref = new \ReflectionMethod(MLAdsAdvancedService::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke($this->getInstance(), ...$args);
    }

    // --- calculateConversionRateFactor ---
    public function testConversionRateFactorHighCVR(): void
    {
        $result = $this->invokePrivate('calculateConversionRateFactor', ['clicks' => 100, 'conversions' => 15]);
        $this->assertEquals(1.3, $result);
    }

    public function testConversionRateFactorMediumCVR(): void
    {
        $result = $this->invokePrivate('calculateConversionRateFactor', ['clicks' => 100, 'conversions' => 5]);
        $this->assertEquals(1.15, $result);
    }

    public function testConversionRateFactorLowCVR(): void
    {
        $result = $this->invokePrivate('calculateConversionRateFactor', ['clicks' => 100, 'conversions' => 2]);
        $this->assertEquals(1.0, $result);
    }

    public function testConversionRateFactorVeryLowCVR(): void
    {
        $result = $this->invokePrivate('calculateConversionRateFactor', ['clicks' => 100, 'conversions' => 1]);
        $this->assertEquals(0.85, $result);
    }

    public function testConversionRateFactorZeroClicks(): void
    {
        $result = $this->invokePrivate('calculateConversionRateFactor', ['clicks' => 0, 'conversions' => 0]);
        $this->assertEquals(0.7, $result);
    }

    // --- getTimeOfDayFactor ---
    public function testTimeOfDayFactorReturnsFloat(): void
    {
        $result = $this->invokePrivate('getTimeOfDayFactor');
        $this->assertIsFloat($result);
        $this->assertGreaterThanOrEqual(0.80, $result);
        $this->assertLessThanOrEqual(1.15, $result);
    }

    // --- getDayOfWeekFactor ---
    public function testDayOfWeekFactorReturnsFloat(): void
    {
        $result = $this->invokePrivate('getDayOfWeekFactor');
        $this->assertIsFloat($result);
        $this->assertGreaterThanOrEqual(0.95, $result);
        $this->assertLessThanOrEqual(1.10, $result);
    }

    // --- calculateBidAdjustment ---
    public function testBidAdjustmentWithFactors(): void
    {
        $factors = [
            'conversion_rate' => 1.15,
            'competition_level' => 0.7,
            'budget_utilization' => 0.5,
            'time_of_day' => 1.0,
            'day_of_week' => 1.0,
        ];

        $result = $this->invokePrivate('calculateBidAdjustment', $factors);
        $this->assertIsFloat($result);
        // Should be bounded between -0.5 and 0.5
        $this->assertGreaterThanOrEqual(-0.5, $result);
        $this->assertLessThanOrEqual(0.5, $result);
    }

    // --- estimateAudienceSize ---
    public function testEstimateAudienceSizeBehavioral(): void
    {
        $result = $this->invokePrivate('estimateAudienceSize', 'behavioral');
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testEstimateAudienceSizeDemographic(): void
    {
        $result = $this->invokePrivate('estimateAudienceSize', 'demographic');
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testEstimateAudienceSizeGeographic(): void
    {
        $result = $this->invokePrivate('estimateAudienceSize', 'geographic');
        $this->assertIsInt($result);
    }

    public function testEstimateAudienceSizeInterestBased(): void
    {
        $result = $this->invokePrivate('estimateAudienceSize', 'interest_based');
        $this->assertIsInt($result);
    }

    public function testEstimateAudienceSizeUnknown(): void
    {
        $result = $this->invokePrivate('estimateAudienceSize', 'random_type');
        $this->assertIsInt($result);
    }

    // --- calculateTotalReach ---
    public function testCalculateTotalReachEmpty(): void
    {
        $result = $this->invokePrivate('calculateTotalReach', []);
        $this->assertEquals(0, $result);
    }

    public function testCalculateTotalReachWithData(): void
    {
        $targeting = [
            ['audience_size' => 10000],
            ['audience_size' => 5000],
            ['audience_size' => 3000],
        ];

        $result = $this->invokePrivate('calculateTotalReach', $targeting);
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    // --- calculateRelatedProductScore ---
    public function testCalculateRelatedProductScoreSameCategory(): void
    {
        $product = ['category_id' => 'CAT1', 'price' => 100, 'sold_quantity' => 50];
        $related = ['category_id' => 'CAT1', 'price' => 90, 'sold_quantity' => 30];

        $result = $this->invokePrivate('calculateRelatedProductScore', $product, $related, 'complementary');
        $this->assertIsFloat($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testCalculateRelatedProductScoreDifferentCategory(): void
    {
        $product = ['category_id' => 'CAT1', 'price' => 100, 'sold_quantity' => 50];
        $related = ['category_id' => 'CAT2', 'price' => 200, 'sold_quantity' => 10];

        $result = $this->invokePrivate('calculateRelatedProductScore', $product, $related, 'upgrade');
        $this->assertIsFloat($result);
    }

    // --- generateUpsellConfiguration ---
    public function testGenerateUpsellConfiguration(): void
    {
        $product = ['id' => 'MLB1', 'title' => 'Produto Base', 'category_id' => 'CAT1', 'price' => 100];
        $relatedProducts = [
            ['id' => 'MLB2', 'title' => 'Acessório', 'price' => 30, 'score' => 0.8, 'type' => 'complementary'],
        ];

        $result = $this->invokePrivate('generateUpsellConfiguration', $product, $relatedProducts);
        $this->assertArrayHasKey('base_product', $result);
        $this->assertArrayHasKey('upsell_products', $result);
        $this->assertArrayHasKey('strategy', $result);
        $this->assertEquals('MLB1', $result['base_product']);
    }

    // --- generateOptimizationSummary ---
    public function testGenerateOptimizationSummaryEmpty(): void
    {
        $result = $this->invokePrivate('generateOptimizationSummary', []);
        $this->assertArrayHasKey('total_campaigns', $result);
        $this->assertEquals(0, $result['total_campaigns']);
    }

    public function testGenerateOptimizationSummaryWithResults(): void
    {
        $results = [
            ['status' => 'optimized', 'actions_applied' => 3],
            ['status' => 'optimized', 'actions_applied' => 2],
            ['status' => 'skipped', 'actions_applied' => 0],
        ];

        $result = $this->invokePrivate('generateOptimizationSummary', $results);
        $this->assertEquals(3, $result['total_campaigns']);
        $this->assertArrayHasKey('optimized', $result);
    }

    // --- analyzeCampaignPerformance ---
    public function testAnalyzeCampaignPerformanceEmpty(): void
    {
        $result = $this->invokePrivate('analyzeCampaignPerformance', []);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('performance_rating', $result);
    }

    public function testAnalyzeCampaignPerformanceHighROAS(): void
    {
        $performance = [
            'impressions' => 10000,
            'clicks' => 500,
            'cost' => 100,
            'revenue' => 500,
            'conversions' => 25,
        ];

        $result = $this->invokePrivate('analyzeCampaignPerformance', $performance);
        $this->assertArrayHasKey('ctr', $result);
        $this->assertArrayHasKey('roas', $result);
        $this->assertArrayHasKey('cpc', $result);
    }

    // --- estimateImprovement ---
    public function testEstimateImprovementEmpty(): void
    {
        $result = $this->invokePrivate('estimateImprovement', []);
        $this->assertArrayHasKey('estimated_improvement', $result);
    }

    public function testEstimateImprovementWithRecommendations(): void
    {
        $recommendations = [
            ['type' => 'increase_budget', 'priority' => 'high'],
            ['type' => 'optimize_keywords', 'priority' => 'medium'],
        ];

        $result = $this->invokePrivate('estimateImprovement', $recommendations);
        $this->assertArrayHasKey('estimated_improvement', $result);
        $this->assertIsArray($result);
    }

    // --- calculateRecommendationConfidence ---
    public function testRecommendationConfidenceRange(): void
    {
        $analysis = ['ctr' => 0.05, 'roas' => 3.0, 'cpc' => 0.5];
        $recommendations = [['type' => 'bid_increase']];

        $result = $this->invokePrivate('calculateRecommendationConfidence', $analysis, $recommendations);
        $this->assertIsFloat($result);
        $this->assertGreaterThanOrEqual(0.0, $result);
        $this->assertLessThanOrEqual(1.0, $result);
    }

    // --- estimateBidImpact ---
    public function testEstimateBidImpactEmpty(): void
    {
        $result = $this->invokePrivate('estimateBidImpact', []);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_items_adjusted', $result);
    }

    public function testEstimateBidImpactWithAdjustments(): void
    {
        $adjustments = [
            ['item_id' => 'MLB1', 'old_bid' => 1.0, 'new_bid' => 1.5, 'adjustment' => 0.5],
            ['item_id' => 'MLB2', 'old_bid' => 2.0, 'new_bid' => 1.8, 'adjustment' => -0.2],
        ];

        $result = $this->invokePrivate('estimateBidImpact', $adjustments);
        $this->assertEquals(2, $result['total_items_adjusted']);
    }

    // --- setupDemographicTargeting ---
    public function testSetupDemographicTargeting(): void
    {
        $result = $this->invokePrivate('setupDemographicTargeting');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('demographic', $result['type']);
    }

    // --- setupInterestBasedTargeting ---
    public function testSetupInterestBasedTargeting(): void
    {
        $result = $this->invokePrivate('setupInterestBasedTargeting');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('interest_based', $result['type']);
    }

    // --- getDataFreshness ---
    public function testGetDataFreshness(): void
    {
        $result = $this->invokePrivate('getDataFreshness');
        $this->assertIsString($result);
    }
}
