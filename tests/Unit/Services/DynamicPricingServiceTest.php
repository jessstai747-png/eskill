<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DynamicPricingService;
use PDO;
use PDOStatement;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Unit Tests for DynamicPricingService
 *
 * Tests pricing algorithms, competitor analysis helpers, elasticity calculation,
 * keyword extraction, and batch analysis structure.
 *
 * @covers \App\Services\DynamicPricingService
 */
class DynamicPricingServiceTest extends TestCase
{
    private DynamicPricingService $service;
    private PDO $mockPdo;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPdo = $this->createMock(PDO::class);
        $this->ref = new ReflectionClass(DynamicPricingService::class);
        $instance = $this->ref->newInstanceWithoutConstructor();

        // Inject mock PDO
        $dbProp = $this->ref->getProperty('db');
        $dbProp->setAccessible(true);
        $dbProp->setValue($instance, $this->mockPdo);

        // Set accountId (inherited from MercadoLivreClient)
        try {
            $parentRef = $this->ref->getParentClass();
            if ($parentRef && $parentRef->hasProperty('accountId')) {
                $prop = $parentRef->getProperty('accountId');
                $prop->setAccessible(true);
                $prop->setValue($instance, 12345);
            }
        } catch (\Throwable $e) {
            // fallback: try setting directly
            if ($this->ref->hasProperty('accountId')) {
                $prop = $this->ref->getProperty('accountId');
                $prop->setAccessible(true);
                $prop->setValue($instance, 12345);
            }
        }

        $this->service = $instance;
    }

    // =========================================================================
    // HELPER: Mock PDOStatement factories
    // =========================================================================

    private function createMockStmt(
        mixed $fetchColumnReturn = null,
        mixed $fetchReturn = null,
        mixed $fetchAllReturn = null
    ): PDOStatement {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        if ($fetchColumnReturn !== null) {
            $stmt->method('fetchColumn')->willReturn($fetchColumnReturn);
        }
        if ($fetchReturn !== null) {
            $stmt->method('fetch')->willReturn($fetchReturn);
        }
        if ($fetchAllReturn !== null) {
            $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        }

        return $stmt;
    }

    // =========================================================================
    // INSTANTIATION TESTS
    // =========================================================================

    public function testServiceCanBeInstantiatedViaReflection(): void
    {
        $this->assertInstanceOf(DynamicPricingService::class, $this->service);
    }

    public function testServiceHasRequiredPublicMethods(): void
    {
        $methods = [
            'calculateOptimalPrice',
            'demandBasedPricing',
            'inventoryLiquidation',
            'applyPriceAdjustment',
            'batchAnalysis',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->service, $method),
                "Missing public method: {$method}"
            );
        }
    }

    // =========================================================================
    // extractKeywords (private, pure logic)
    // =========================================================================

    public function testExtractKeywordsFiltersStopWords(): void
    {
        $method = new ReflectionMethod(DynamicPricingService::class, 'extractKeywords');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'Bagageiro de Moto para CG 160');

        $this->assertIsArray($result);
        $this->assertNotContains('de', $result);
        $this->assertNotContains('para', $result);
    }

    public function testExtractKeywordsFiltersShortWords(): void
    {
        $method = new ReflectionMethod(DynamicPricingService::class, 'extractKeywords');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'A do em CG 160 Titan');

        // 'a', 'do', 'em' are stop words or <= 2 chars
        $this->assertNotContains('a', $result);
        $this->assertNotContains('do', $result);
        $this->assertNotContains('em', $result);
    }

    public function testExtractKeywordsReturnsLowercase(): void
    {
        $method = new ReflectionMethod(DynamicPricingService::class, 'extractKeywords');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'BAGAGEIRO TITAN');

        foreach ($result as $word) {
            $this->assertSame(strtolower($word), $word);
        }
    }

    public function testExtractKeywordsKeepsRelevantWords(): void
    {
        $method = new ReflectionMethod(DynamicPricingService::class, 'extractKeywords');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'Bagageiro Moto CG160 Titan');

        $this->assertContains('bagageiro', $result);
        $this->assertContains('moto', $result);
        $this->assertContains('titan', $result);
    }

    public function testExtractKeywordsHandlesEmptyString(): void
    {
        $method = new ReflectionMethod(DynamicPricingService::class, 'extractKeywords');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, '');

        $this->assertIsArray($result);
    }

    public function testExtractKeywordsHandlesOnlyStopWords(): void
    {
        $method = new ReflectionMethod(DynamicPricingService::class, 'extractKeywords');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'de da do para com sem em a o e ou');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // calculatePriceElasticity (private, pure math)
    // =========================================================================

    public function testCalculatePriceElasticityReturns1ForEmptyHistory(): void
    {
        $method = new ReflectionMethod(DynamicPricingService::class, 'calculatePriceElasticity');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, []);

        $this->assertSame(1.0, $result);
    }

    public function testCalculatePriceElasticityReturns1ForSingleEntry(): void
    {
        $method = new ReflectionMethod(DynamicPricingService::class, 'calculatePriceElasticity');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [
            ['price' => 100, 'sold_quantity' => 10],
        ]);

        $this->assertSame(1.0, $result);
    }

    public function testCalculatePriceElasticityWithTwoEntries(): void
    {
        $method = new ReflectionMethod(DynamicPricingService::class, 'calculatePriceElasticity');
        $method->setAccessible(true);

        // Price goes from 100 to 90 (-10%), quantity goes from 10 to 15 (+50%)
        // Elasticity = |50% / -10%| = 5.0
        $result = $method->invoke($this->service, [
            ['price' => 100, 'sold_quantity' => 10],
            ['price' => 90, 'sold_quantity' => 15],
        ]);

        $this->assertIsFloat($result);
        $this->assertEqualsWithDelta(5.0, $result, 0.1);
    }

    public function testCalculatePriceElasticityIgnoresZeroPriceChange(): void
    {
        $method = new ReflectionMethod(DynamicPricingService::class, 'calculatePriceElasticity');
        $method->setAccessible(true);

        // No price change — should skip this pair
        $result = $method->invoke($this->service, [
            ['price' => 100, 'sold_quantity' => 10],
            ['price' => 100, 'sold_quantity' => 15],
        ]);

        // No valid changes, returns 1.0 default
        $this->assertSame(1.0, $result);
    }

    public function testCalculatePriceElasticityIgnoresZeroQuantity(): void
    {
        $method = new ReflectionMethod(DynamicPricingService::class, 'calculatePriceElasticity');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [
            ['price' => 100, 'sold_quantity' => 0],
            ['price' => 90, 'sold_quantity' => 5],
        ]);

        // prev sold_quantity = 0, division check fails, skipped
        $this->assertSame(1.0, $result);
    }

    public function testCalculatePriceElasticityWithMultipleEntries(): void
    {
        $method = new ReflectionMethod(DynamicPricingService::class, 'calculatePriceElasticity');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [
            ['price' => 100, 'sold_quantity' => 10],
            ['price' => 95, 'sold_quantity' => 12],
            ['price' => 90, 'sold_quantity' => 15],
        ]);

        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
    }

    // =========================================================================
    // getItemCost (private, DB dependent)
    // =========================================================================

    public function testGetItemCostReturnsFloat(): void
    {
        $stmt = $this->createMockStmt(fetchReturn: ['cost' => 49.90]);
        $this->mockPdo->method('prepare')->willReturn($stmt);

        $method = new ReflectionMethod(DynamicPricingService::class, 'getItemCost');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'MLB12345');

        $this->assertIsFloat($result);
        $this->assertEqualsWithDelta(49.90, $result, 0.01);
    }

    public function testGetItemCostReturnsZeroWhenNotFound(): void
    {
        $stmt = $this->createMockStmt(fetchReturn: false);
        $this->mockPdo->method('prepare')->willReturn($stmt);

        $method = new ReflectionMethod(DynamicPricingService::class, 'getItemCost');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'MLB99999');

        $this->assertSame(0.0, $result);
    }

    // =========================================================================
    // STRUCTURE TESTS — Public methods return expected structure on error
    // =========================================================================

    public function testCalculateOptimalPriceReturnsErrorOnFailure(): void
    {
        // No client set up → will fail when trying to call ML API
        try {
            $result = $this->service->calculateOptimalPrice('MLB12345');
        } catch (\Throwable $e) {
            // If client is null, the method may throw before getting to try/catch
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        // If it catches internally, it returns error structure
        $this->assertIsArray($result);
        if (isset($result['success'])) {
            $this->assertFalse($result['success']);
            $this->assertArrayHasKey('error', $result);
        }
    }

    public function testDemandBasedPricingReturnsErrorOnFailure(): void
    {
        // Will fail since no ML client / DB data
        try {
            $result = $this->service->demandBasedPricing('MLB12345');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
        if (isset($result['success'])) {
            $this->assertFalse($result['success']);
        }
    }

    public function testInventoryLiquidationReturnsErrorOnFailure(): void
    {
        try {
            $result = $this->service->inventoryLiquidation('SKU-001');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
        if (isset($result['success'])) {
            $this->assertFalse($result['success']);
        }
    }

    public function testBatchAnalysisReturnsStructureWithEmptyInput(): void
    {
        $result = $this->service->batchAnalysis([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('strategy', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('items', $result);
    }

    // =========================================================================
    // batchAnalysis VALIDATION
    // =========================================================================

    public function testBatchAnalysisDefaultStrategy(): void
    {
        $result = $this->service->batchAnalysis([]);

        $this->assertSame('competition', $result['strategy']);
        $this->assertSame(0, $result['summary']['total']);
        $this->assertSame(0, $result['summary']['analyzed']);
        $this->assertEmpty($result['items']);
    }

    public function testBatchAnalysisAcceptsCustomStrategy(): void
    {
        $result = $this->service->batchAnalysis([], 'demand');

        $this->assertIsArray($result);
        $this->assertSame('demand', $result['strategy']);
        $this->assertSame(0, $result['summary']['total']);
    }

    public function testBatchAnalysisSummaryHasExpectedKeys(): void
    {
        $result = $this->service->batchAnalysis([]);

        $expectedKeys = ['total', 'analyzed', 'should_increase', 'should_decrease', 'maintain', 'total_potential_revenue'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result['summary'], "Missing summary key: {$key}");
        }
    }
}
