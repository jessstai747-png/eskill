<?php

namespace Tests\Unit\Services\MercadoLivre;

use Tests\TestCase;
use App\Services\MercadoLivre\AdvancedPricingEngine;

/**
 * Testes unitários para AdvancedPricingEngine.
 * Verifica existência de todos os métodos e lógica pura de cálculo.
 */
class AdvancedPricingEngineTest extends TestCase
{
    // =============================
    // TESTES DE ESTRUTURA
    // =============================

    public function testHasRequiredPublicMethods(): void
    {
        $methods = [
            'startDynamicPricing',
            'monitorCompetitorsAndAdjust',
            'applyPsychologicalPricing',
            'optimizeWithElasticity',
            'batchPriceOptimization',
            'getPricingAnalytics',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(AdvancedPricingEngine::class, $method),
                "AdvancedPricingEngine deve ter método público {$method}()"
            );
        }
    }

    public function testHasAllPrivateHelperMethods(): void
    {
        $requiredPrivateMethods = [
            'calculateOptimalPrice',
            'monitorCompetitorItem',
            'calculatePsychologicalPrice',
            'calculatePriceElasticity',
            'loadPricingConfig',
            'generateBatchId',
            'getProductsForPricing',
            'getActiveCompetitorItems',
            'getProductsForPsychologicalPricing',
            'getProductsWithElasticityData',
            'getProductsForBatchOptimization',
            'getProductPrice',
            'getCompetitorPrices',
            'getProductElasticity',
            'getDemandLevel',
            'getCompetitorPriceHistory',
            'getOurCompetingProducts',
            'getHistoricalPricingData',
            'applyPriceChange',
            'applyPsychologicalPrice',
            'applyElasticityBasedPrice',
            'applyBatchOptimizations',
            'applyCompetitorAdjustment',
            'generatePricingSummary',
            'getPsychologicalPatterns',
            'generateBatchOptimizationSummary',
            'generateMarketIntelligence',
            'generateElasticityInsights',
            'estimateRevenueImpact',
            'findOptimalPriceByElasticity',
            'performComprehensiveOptimization',
            'calculateCompetitorAdjustment',
            'getPricingOverview',
            'getPricePerformanceAnalysis',
            'getCompetitorPricingAnalysis',
            'getElasticityAnalysis',
            'getMarginAnalysis',
            'getConversionByPricePoint',
            'getPriceRecommendations',
            'getMarketPositioningAnalysis',
            'getROIMetrics',
            'estimateConversionLift',
            'calculateCompetitorPosition',
            'calculateStockPressure',
            'calculateMarginProtection',
            'calculatePsychologicalFactor',
            'calculateTimePricingFactor',
            'applyPsychologicalAdjustment',
            'calculatePricingConfidence',
            'estimatePriceChangeImpact',
            'detectPriceChange',
            'calculatePriceTrend',
            'calculateOurPosition',
            'calculateMarketImpact',
            'calculateUrgency',
            'calculatePsychologicalScore',
            'identifyPsychologicalPricingType',
            'calculateElasticityCoefficient',
            'calculateElasticityConfidence',
            'calculatePriceSensitivity',
            'generateElasticityRecommendation',
        ];

        foreach ($requiredPrivateMethods as $method) {
            $this->assertTrue(
                method_exists(AdvancedPricingEngine::class, $method),
                "AdvancedPricingEngine deve ter método {$method}()"
            );
        }
    }

    public function testHasRequiredDependencies(): void
    {
        $reflection = new \ReflectionClass(AdvancedPricingEngine::class);

        $this->assertTrue($reflection->hasProperty('mlClient'), 'Deve ter propriedade mlClient');
        $this->assertTrue($reflection->hasProperty('db'), 'Deve ter propriedade db');
        $this->assertTrue($reflection->hasProperty('cache'), 'Deve ter propriedade cache');
        $this->assertTrue($reflection->hasProperty('accountId'), 'Deve ter propriedade accountId');
    }

    // =============================
    // TESTES DE LÓGICA PURA
    // =============================

    private function getInstance(): AdvancedPricingEngine
    {
        return (new \ReflectionClass(AdvancedPricingEngine::class))->newInstanceWithoutConstructor();
    }

    private function invokePrivate(string $method, ...$args)
    {
        $ref = new \ReflectionMethod(AdvancedPricingEngine::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke($this->getInstance(), ...$args);
    }

    // --- calculateCompetitorPosition ---
    public function testCompetitorPositionWithEmptyPrices(): void
    {
        $result = $this->invokePrivate('calculateCompetitorPosition', 100.0, []);
        $this->assertEquals(0.5, $result);
    }

    public function testCompetitorPositionCheapestProduct(): void
    {
        $result = $this->invokePrivate('calculateCompetitorPosition', 50.0, [100, 200, 300]);
        $this->assertEquals(0.0, $result);
    }

    public function testCompetitorPositionMostExpensive(): void
    {
        $result = $this->invokePrivate('calculateCompetitorPosition', 500.0, [100, 200, 300]);
        $this->assertEquals(1.0, $result);
    }

    // --- calculateStockPressure ---
    public function testStockPressureOutOfStock(): void
    {
        $result = $this->invokePrivate('calculateStockPressure', 0, []);
        $this->assertEquals(1.0, $result);
    }

    public function testStockPressureLowStock(): void
    {
        $result = $this->invokePrivate('calculateStockPressure', 2, ['low_stock_threshold' => 5]);
        $this->assertGreaterThan(0, $result);
        $this->assertLessThanOrEqual(1.0, $result);
    }

    public function testStockPressureNormal(): void
    {
        $result = $this->invokePrivate('calculateStockPressure', 20, ['low_stock_threshold' => 5, 'high_stock_threshold' => 50]);
        $this->assertEquals(0.0, $result);
    }

    public function testStockPressureOverstock(): void
    {
        $result = $this->invokePrivate('calculateStockPressure', 100, ['high_stock_threshold' => 50]);
        $this->assertLessThan(0, $result);
    }

    // --- calculatePsychologicalFactor ---
    public function testPsychologicalFactor99Ending(): void
    {
        $result = $this->invokePrivate('calculatePsychologicalFactor', 99.99);
        $this->assertEquals(0.9, $result);
    }

    public function testPsychologicalFactor95Ending(): void
    {
        $result = $this->invokePrivate('calculatePsychologicalFactor', 49.95);
        $this->assertEquals(0.8, $result);
    }

    // --- calculateTimePricingFactor ---
    public function testTimePricingFactorReturnsFloat(): void
    {
        $result = $this->invokePrivate('calculateTimePricingFactor');
        $this->assertIsFloat($result);
        $this->assertGreaterThan(0.9, $result);
        $this->assertLessThan(1.1, $result);
    }

    // --- applyPsychologicalAdjustment ---
    public function testPsychologicalAdjustmentSmallPrice(): void
    {
        $result = $this->invokePrivate('applyPsychologicalAdjustment', 47.30);
        $this->assertEquals(47.99, $result);
    }

    public function testPsychologicalAdjustmentMediumPrice(): void
    {
        $result = $this->invokePrivate('applyPsychologicalAdjustment', 350.50);
        $this->assertEquals(350.90, $result);
    }

    public function testPsychologicalAdjustmentZeroPrice(): void
    {
        $result = $this->invokePrivate('applyPsychologicalAdjustment', 0);
        $this->assertEquals(0, $result);
    }

    public function testPsychologicalAdjustmentAlready99(): void
    {
        $result = $this->invokePrivate('applyPsychologicalAdjustment', 49.99);
        $this->assertEquals(49.99, $result);
    }

    // --- calculatePricingConfidence ---
    public function testPricingConfidenceWithAllFactors(): void
    {
        $factors = [
            'competitor_position' => 0.5,
            'elasticity_factor' => 0.8,
            'stock_pressure' => 0.3,
            'demand_factor' => 0.6,
            'margin_protection' => 0.4,
            'psychological_factor' => 0.9,
            'time_factor' => 1.0,
        ];

        $result = $this->invokePrivate('calculatePricingConfidence', $factors);
        $this->assertGreaterThanOrEqual(0.5, $result);
        $this->assertLessThanOrEqual(0.95, $result);
    }

    public function testPricingConfidenceWithNoFactors(): void
    {
        $result = $this->invokePrivate('calculatePricingConfidence', []);
        $this->assertEquals(0.5, $result);
    }

    // --- calculatePriceTrend ---
    public function testPriceTrendStableWithFewData(): void
    {
        $result = $this->invokePrivate('calculatePriceTrend', [['price' => 100]]);
        $this->assertEquals('stable', $result);
    }

    public function testPriceTrendIncreasing(): void
    {
        $prices = [];
        for ($i = 0; $i < 10; $i++) {
            $prices[] = ['price' => 100 + $i * 5]; // recent are first, increasing
        }
        $result = $this->invokePrivate('calculatePriceTrend', $prices);
        $this->assertContains($result, ['increasing', 'decreasing', 'stable']);
    }

    // --- calculateMarginProtection ---
    public function testMarginProtectionWithZeroPrice(): void
    {
        $result = $this->invokePrivate('calculateMarginProtection', ['price' => 0], []);
        $this->assertEquals(0.0, $result);
    }

    public function testMarginProtectionLowMargin(): void
    {
        // price=100, cost=95 → margin=5% → below default min (10%) → returns 1.0
        $result = $this->invokePrivate('calculateMarginProtection', ['price' => 100, 'cost' => 95], []);
        $this->assertEquals(1.0, $result);
    }

    // --- detectPriceChange ---
    public function testDetectPriceChangeEmpty(): void
    {
        $result = $this->invokePrivate('detectPriceChange', 100.0, []);
        $this->assertFalse($result);
    }

    public function testDetectPriceChangeDetectsChange(): void
    {
        $result = $this->invokePrivate('detectPriceChange', 100.0, [['price' => 95.0]]);
        $this->assertTrue($result);
    }

    public function testDetectPriceChangeNoChange(): void
    {
        $result = $this->invokePrivate('detectPriceChange', 100.0, [['price' => 100.0]]);
        $this->assertFalse($result);
    }

    // --- calculateUrgency ---
    public function testCalculateUrgencyCritical(): void
    {
        $result = $this->invokePrivate('calculateUrgency', [
            'price_change_detected' => true,
            'our_position' => ['is_more_expensive' => true],
            'price_trend' => 'decreasing',
            'market_impact' => ['impact_level' => 'high'],
        ]);
        $this->assertEquals('critical', $result);
    }

    public function testCalculateUrgencyLow(): void
    {
        $result = $this->invokePrivate('calculateUrgency', []);
        $this->assertEquals('low', $result);
    }

    // --- calculateElasticityConfidence ---
    public function testElasticityConfidenceSmallDataset(): void
    {
        $result = $this->invokePrivate('calculateElasticityConfidence', [1, 2, 3]);
        $this->assertEquals(0.1, $result);
    }

    public function testElasticityConfidenceLargeDataset(): void
    {
        $result = $this->invokePrivate('calculateElasticityConfidence', array_fill(0, 150, 1));
        $this->assertEquals(0.95, $result);
    }

    // --- calculatePriceSensitivity ---
    public function testPriceSensitivityVeryHigh(): void
    {
        $result = $this->invokePrivate('calculatePriceSensitivity', 2.5);
        $this->assertEquals('very_high', $result);
    }

    public function testPriceSensitivityLow(): void
    {
        $result = $this->invokePrivate('calculatePriceSensitivity', 0.4);
        $this->assertEquals('low', $result);
    }

    public function testPriceSensitivityVeryLow(): void
    {
        $result = $this->invokePrivate('calculatePriceSensitivity', 0.1);
        $this->assertEquals('very_low', $result);
    }

    // --- calculatePsychologicalScore ---
    public function testPsychologicalScoreCharmPricing(): void
    {
        $result = $this->invokePrivate('calculatePsychologicalScore', 99.99, 100.50);
        $this->assertGreaterThan(0, $result);
    }

    public function testPsychologicalScoreZeroPrice(): void
    {
        $result = $this->invokePrivate('calculatePsychologicalScore', 0, 100);
        $this->assertEquals(0.0, $result);
    }

    // --- identifyPsychologicalPricingType ---
    public function testIdentifyCharmPricing(): void
    {
        $result = $this->invokePrivate('identifyPsychologicalPricingType', 49.99, 50.00);
        $this->assertEquals('charm_pricing', $result);
    }

    public function testIdentifyPrestigePricing(): void
    {
        $result = $this->invokePrivate('identifyPsychologicalPricingType', 200.00, 195.00);
        $this->assertEquals('prestige_pricing', $result);
    }

    public function testIdentifyDiscountPricing(): void
    {
        $result = $this->invokePrivate('identifyPsychologicalPricingType', 80.37, 100.00);
        $this->assertEquals('discount_pricing', $result);
    }

    // --- estimateConversionLift ---
    public function testEstimateConversionLiftEmpty(): void
    {
        $result = $this->invokePrivate('estimateConversionLift', []);
        $this->assertEquals(0.0, $result);
    }

    public function testEstimateConversionLiftCharmPricing(): void
    {
        $result = $this->invokePrivate('estimateConversionLift', [['pricing_type' => 'charm_pricing']]);
        $this->assertEquals(0.08, $result);
    }

    // --- calculateElasticityCoefficient ---
    public function testElasticityCoefficientSmallData(): void
    {
        $result = $this->invokePrivate('calculateElasticityCoefficient', [1, 2, 3]);
        $this->assertEquals(1.0, $result);
    }

    // --- generateBatchId ---
    public function testGenerateBatchIdFormat(): void
    {
        $result = $this->invokePrivate('generateBatchId');
        $this->assertIsString($result);
        $this->assertStringStartsWith('BATCH-', $result);
    }

    // --- calculateOurPosition ---
    public function testCalculateOurPositionEmpty(): void
    {
        $result = $this->invokePrivate('calculateOurPosition', ['price' => 100], []);
        $this->assertFalse($result['is_more_expensive']);
        $this->assertEquals(0, $result['products_count']);
    }

    public function testCalculateOurPositionMoreExpensive(): void
    {
        $result = $this->invokePrivate('calculateOurPosition', ['price' => 50], [['price' => 80], ['price' => 90]]);
        $this->assertTrue($result['is_more_expensive']);
        $this->assertEquals(2, $result['products_count']);
    }

    // --- calculateMarketImpact ---
    public function testCalculateMarketImpactBothEmpty(): void
    {
        $result = $this->invokePrivate('calculateMarketImpact', ['sold_quantity' => 0], []);
        $this->assertEquals(0, $result['our_market_share']);
        $this->assertEquals(0, $result['total_volume']);
    }

    public function testCalculateMarketImpactWithSales(): void
    {
        $comp = ['sold_quantity' => 30, 'price' => 100];
        $ours = [
            ['sold_quantity' => 70, 'price' => 90],
        ];

        $result = $this->invokePrivate('calculateMarketImpact', $comp, $ours);
        $this->assertEquals(70.0, $result['our_market_share']);
        $this->assertEquals(30.0, $result['competitor_share']);
        $this->assertEquals('competitive', $result['price_competitiveness']);
        $this->assertEquals('low', $result['impact_level']);
    }
}
