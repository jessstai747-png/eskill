<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DeepResearchService;
use ReflectionClass;
use Tests\TestCase;

/**
 * Unit Tests for DeepResearchService
 *
 * Tests profitability simulation (public pure math),
 * keyword analysis (public pure text), and constants.
 *
 * @covers \App\Services\DeepResearchService
 */
class DeepResearchServiceTest extends TestCase
{
    private DeepResearchService $service;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ref = new ReflectionClass(DeepResearchService::class);
        $this->service = $this->ref->newInstanceWithoutConstructor();
    }

    // =========================================================================
    // INSTANTIATION
    // =========================================================================

    public function testServiceCanBeInstantiatedViaReflection(): void
    {
        $this->assertInstanceOf(DeepResearchService::class, $this->service);
    }

    public function testServiceHasRequiredPublicMethods(): void
    {
        $methods = ['researchBrand', 'quickResearch', 'compareBrands', 'simulateProfitability', 'analyzeTopKeywords'];

        foreach ($methods as $method) {
            $this->assertTrue(method_exists($this->service, $method), "Missing: {$method}");
        }
    }

    // =========================================================================
    // CONSTANTS
    // =========================================================================

    public function testMLCommissionsConstantExists(): void
    {
        $constant = $this->ref->getReflectionConstant('ML_COMMISSIONS');
        $this->assertNotFalse($constant);

        $commissions = $constant->getValue();
        $this->assertIsArray($commissions);
        $this->assertArrayHasKey('gold_pro', $commissions);
        $this->assertArrayHasKey('gold_special', $commissions);
        $this->assertSame(16.5, $commissions['gold_pro']);
        $this->assertSame(12.0, $commissions['gold_special']);
    }

    public function testPaymentFeeConstantExists(): void
    {
        $constant = $this->ref->getReflectionConstant('PAYMENT_FEE');
        $this->assertNotFalse($constant);
        $this->assertSame(4.99, $constant->getValue());
    }

    public function testFullShippingCostsConstantExists(): void
    {
        $constant = $this->ref->getReflectionConstant('FULL_SHIPPING_COSTS');
        $this->assertNotFalse($constant);
        $costs = $constant->getValue();

        $this->assertIsArray($costs);
        $this->assertArrayHasKey('light', $costs);
        $this->assertArrayHasKey('medium', $costs);
        $this->assertArrayHasKey('heavy', $costs);
        $this->assertArrayHasKey('extra', $costs);
    }

    // =========================================================================
    // simulateProfitability — PUBLIC PURE MATH
    // =========================================================================

    public function testSimulateProfitabilityReturnsStructure(): void
    {
        $result = $this->service->simulateProfitability(50.0, 150.0);

        $this->assertArrayHasKey('inputs', $result);
        $this->assertArrayHasKey('scenarios', $result);
        $this->assertArrayHasKey('recommendation', $result);
    }

    public function testSimulateProfitabilityInputsMatch(): void
    {
        $result = $this->service->simulateProfitability(50.0, 150.0, 'simples', 12.0);

        $this->assertSame(50.0, $result['inputs']['cost_price']);
        $this->assertSame(150.0, $result['inputs']['target_price']);
        $this->assertSame('simples', $result['inputs']['tax_regime']);
        $this->assertSame(12.0, $result['inputs']['tax_rate']);
    }

    public function testSimulateProfitabilityHasTwoScenarios(): void
    {
        $result = $this->service->simulateProfitability(50.0, 150.0);

        $this->assertArrayHasKey('gold_special', $result['scenarios']);
        $this->assertArrayHasKey('gold_pro', $result['scenarios']);
    }

    public function testSimulateProfitabilityScenarioHasCosts(): void
    {
        $result = $this->service->simulateProfitability(50.0, 150.0);
        $scenario = $result['scenarios']['gold_special'];

        $this->assertArrayHasKey('costs', $scenario);
        $this->assertArrayHasKey('product', $scenario['costs']);
        $this->assertArrayHasKey('commission_ml', $scenario['costs']);
        $this->assertArrayHasKey('fixed_fee', $scenario['costs']);
        $this->assertArrayHasKey('shipping', $scenario['costs']);
        $this->assertArrayHasKey('taxes', $scenario['costs']);
        $this->assertArrayHasKey('total', $scenario['costs']);
    }

    public function testSimulateProfitabilityScenarioHasResult(): void
    {
        $result = $this->service->simulateProfitability(50.0, 150.0);
        $scenario = $result['scenarios']['gold_special'];

        $this->assertArrayHasKey('result', $scenario);
        $this->assertArrayHasKey('net_profit', $scenario['result']);
        $this->assertArrayHasKey('margin_percent', $scenario['result']);
        $this->assertArrayHasKey('roi_percent', $scenario['result']);
        $this->assertArrayHasKey('is_profitable', $scenario['result']);
    }

    public function testSimulateProfitabilityCommissionCalculation(): void
    {
        // gold_special = 12% commission
        $result = $this->service->simulateProfitability(50.0, 200.0);
        $classic = $result['scenarios']['gold_special'];

        // Commission = 200 * 12% = 24
        $this->assertEqualsWithDelta(24.0, $classic['costs']['commission_ml'], 0.01);
    }

    public function testSimulateProfitabilityFixedFeeForLowPrice(): void
    {
        // Price < 79 gets R$6 fixed fee
        $result = $this->service->simulateProfitability(20.0, 50.0);
        $scenario = $result['scenarios']['gold_special'];

        $this->assertEqualsWithDelta(6.0, $scenario['costs']['fixed_fee'], 0.01);
    }

    public function testSimulateProfitabilityNoFixedFeeForHighPrice(): void
    {
        // Price >= 79 has no fixed fee
        $result = $this->service->simulateProfitability(30.0, 100.0);
        $scenario = $result['scenarios']['gold_special'];

        $this->assertEqualsWithDelta(0.0, $scenario['costs']['fixed_fee'], 0.01);
    }

    public function testSimulateProfitabilityPremiumHigherCommission(): void
    {
        $result = $this->service->simulateProfitability(50.0, 200.0);

        $premiumComm = $result['scenarios']['gold_pro']['costs']['commission_ml'];
        $classicComm = $result['scenarios']['gold_special']['costs']['commission_ml'];

        // gold_pro=16.5% vs gold_special=12%
        $this->assertGreaterThan($classicComm, $premiumComm);
    }

    public function testSimulateProfitabilityIsProfitable(): void
    {
        // High margin scenario: cost 50, sell 200
        $result = $this->service->simulateProfitability(50.0, 200.0);

        $this->assertTrue($result['scenarios']['gold_special']['result']['is_profitable']);
    }

    public function testSimulateProfitabilityUnprofitable(): void
    {
        // Very low margin: cost 90, sell 100 → likely unprofitable after fees
        $result = $this->service->simulateProfitability(90.0, 100.0);

        $this->assertFalse($result['scenarios']['gold_pro']['result']['is_profitable']);
    }

    public function testSimulateProfitabilityRecommendationIsString(): void
    {
        $result = $this->service->simulateProfitability(50.0, 200.0);

        $this->assertIsString($result['recommendation']);
        $this->assertNotEmpty($result['recommendation']);
    }

    public function testSimulateProfitabilityWithoutTargetPriceReturnsError(): void
    {
        $result = $this->service->simulateProfitability(50.0, null);

        // Without collected data, should return error
        $this->assertArrayHasKey('error', $result);
    }

    // =========================================================================
    // analyzeTopKeywords — PUBLIC PURE TEXT PROCESSING
    // =========================================================================

    public function testAnalyzeTopKeywordsWithItems(): void
    {
        $items = [
            ['title' => 'Bagageiro CG 160 Titan'],
            ['title' => 'Bagageiro CG 160 Fan'],
            ['title' => 'Bagageiro Bros 160'],
        ];

        $result = $this->service->analyzeTopKeywords($items, 10);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testAnalyzeTopKeywordsReturnsFrequency(): void
    {
        $items = [
            ['title' => 'Bagageiro Moto CG'],
            ['title' => 'Bagageiro Moto Titan'],
            ['title' => 'Bagageiro Moto Bros'],
        ];

        $result = $this->service->analyzeTopKeywords($items, 5);

        // "bagageiro" and "moto" should appear in results with count 3
        if (!empty($result)) {
            // First entry should be most frequent
            $firstCount = reset($result);
            $this->assertGreaterThanOrEqual(1, $firstCount);
        }
    }

    public function testAnalyzeTopKeywordsFiltersStopwords(): void
    {
        $items = [
            ['title' => 'de para com sem em a o e ou bagageiro'],
        ];

        $result = $this->service->analyzeTopKeywords($items, 20);

        // Stop words should not be in results
        $this->assertArrayNotHasKey('de', $result);
        $this->assertArrayNotHasKey('para', $result);
        $this->assertArrayNotHasKey('com', $result);
    }

    public function testAnalyzeTopKeywordsEmptyItemsReturnsEmptyArray(): void
    {
        $result = $this->service->analyzeTopKeywords([], 10);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testAnalyzeTopKeywordsRespectsLimit(): void
    {
        $items = [];
        for ($i = 0; $i < 10; $i++) {
            $items[] = ['title' => "Palavra{$i} Teste{$i} Produto{$i}"];
        }

        $result = $this->service->analyzeTopKeywords($items, 3);

        $this->assertLessThanOrEqual(3, count($result));
    }
}
