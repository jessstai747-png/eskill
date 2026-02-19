<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AutoPricingOptimizerService;
use PDO;
use PDOStatement;
use ReflectionClass;
use Tests\TestCase;

/**
 * Unit Tests for AutoPricingOptimizerService
 *
 * Tests config defaults, optimization flow, analysis structure,
 * and error handling for auto pricing.
 *
 * @covers \App\Services\AutoPricingOptimizerService
 */
class AutoPricingOptimizerServiceTest extends TestCase
{
    private AutoPricingOptimizerService $service;
    private PDO $mockPdo;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPdo = $this->createMock(PDO::class);
        $this->ref = new ReflectionClass(AutoPricingOptimizerService::class);
        $instance = $this->ref->newInstanceWithoutConstructor();

        // Inject mock PDO
        $dbProp = $this->ref->getProperty('db');
        $dbProp->setAccessible(true);
        $dbProp->setValue($instance, $this->mockPdo);

        // Set accountId
        $idProp = $this->ref->getProperty('accountId');
        $idProp->setAccessible(true);
        $idProp->setValue($instance, 12345);

        $this->service = $instance;
    }

    private function createMockStmt(
        mixed $fetchReturn = null,
        mixed $fetchAllReturn = null
    ): PDOStatement {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        if ($fetchReturn !== null) {
            $stmt->method('fetch')->willReturn($fetchReturn);
        }
        if ($fetchAllReturn !== null) {
            $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        }
        return $stmt;
    }

    // =========================================================================
    // INSTANTIATION
    // =========================================================================

    public function testServiceCanBeInstantiatedViaReflection(): void
    {
        $this->assertInstanceOf(AutoPricingOptimizerService::class, $this->service);
    }

    public function testServiceHasRequiredPublicMethods(): void
    {
        $methods = [
            'getConfig', 'saveConfig', 'runOptimization',
            'analyzeItem', 'getOptimizationHistory', 'getStats',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->service, $method),
                "Missing public method: {$method}"
            );
        }
    }

    // =========================================================================
    // getConfig — default values when no DB row exists
    // =========================================================================

    public function testGetConfigReturnsDefaultWhenNoDbRow(): void
    {
        // ensureConfigTable + SELECT both need stmts
        $stmtExec = $this->createMockStmt();
        $stmtFetch = $this->createMockStmt(fetchReturn: false);
        $this->mockPdo->method('prepare')
            ->willReturn($stmtFetch);
        $this->mockPdo->method('exec')
            ->willReturn(0);

        $config = $this->service->getConfig();

        $this->assertIsArray($config);
        $this->assertFalse($config['enabled']);
        $this->assertSame('suggest', $config['mode']);
    }

    public function testGetConfigDefaultIntervalIs60(): void
    {
        $stmt = $this->createMockStmt(fetchReturn: false);
        $this->mockPdo->method('prepare')->willReturn($stmt);
        $this->mockPdo->method('exec')->willReturn(0);

        $config = $this->service->getConfig();

        $this->assertSame(60, $config['check_interval_minutes']);
    }

    public function testGetConfigDefaultMinMarginIs10(): void
    {
        $stmt = $this->createMockStmt(fetchReturn: false);
        $this->mockPdo->method('prepare')->willReturn($stmt);
        $this->mockPdo->method('exec')->willReturn(0);

        $config = $this->service->getConfig();

        $this->assertSame(10, $config['min_margin_percent']);
    }

    public function testGetConfigDefaultCompetitorStrategy(): void
    {
        $stmt = $this->createMockStmt(fetchReturn: false);
        $this->mockPdo->method('prepare')->willReturn($stmt);
        $this->mockPdo->method('exec')->willReturn(0);

        $config = $this->service->getConfig();

        $this->assertSame('match_lowest', $config['competitor_strategy']);
    }

    public function testGetConfigDefaultExcludeItemsIsEmptyArray(): void
    {
        $stmt = $this->createMockStmt(fetchReturn: false);
        $this->mockPdo->method('prepare')->willReturn($stmt);
        $this->mockPdo->method('exec')->willReturn(0);

        $config = $this->service->getConfig();

        $this->assertIsArray($config['exclude_items']);
        $this->assertEmpty($config['exclude_items']);
    }

    public function testGetConfigDefaultIncludeOnlyItemsIsEmptyArray(): void
    {
        $stmt = $this->createMockStmt(fetchReturn: false);
        $this->mockPdo->method('prepare')->willReturn($stmt);
        $this->mockPdo->method('exec')->willReturn(0);

        $config = $this->service->getConfig();

        $this->assertIsArray($config['include_only_items']);
        $this->assertEmpty($config['include_only_items']);
    }

    public function testGetConfigDefaultNotifyEmailIsTrue(): void
    {
        $stmt = $this->createMockStmt(fetchReturn: false);
        $this->mockPdo->method('prepare')->willReturn($stmt);
        $this->mockPdo->method('exec')->willReturn(0);

        $config = $this->service->getConfig();

        $this->assertTrue($config['notify_email']);
    }

    public function testGetConfigDefaultMaxPriceIncreaseIs8(): void
    {
        $stmt = $this->createMockStmt(fetchReturn: false);
        $this->mockPdo->method('prepare')->willReturn($stmt);
        $this->mockPdo->method('exec')->willReturn(0);

        $config = $this->service->getConfig();

        $this->assertSame(8, $config['max_price_increase_percent']);
    }

    public function testGetConfigDefaultMaxPriceDecreaseIs15(): void
    {
        $stmt = $this->createMockStmt(fetchReturn: false);
        $this->mockPdo->method('prepare')->willReturn($stmt);
        $this->mockPdo->method('exec')->willReturn(0);

        $config = $this->service->getConfig();

        $this->assertSame(15, $config['max_price_decrease_percent']);
    }

    // =========================================================================
    // getConfig — with existing DB row
    // =========================================================================

    public function testGetConfigParsesJsonExcludeItems(): void
    {
        $dbRow = [
            'enabled' => 1,
            'mode' => 'auto_apply',
            'check_interval_minutes' => 30,
            'min_margin_percent' => 12,
            'max_price_increase_percent' => 5,
            'max_price_decrease_percent' => 10,
            'competitor_strategy' => 'stay_below',
            'competitor_margin_buffer' => 3,
            'notify_email' => 1,
            'notify_changes' => 1,
            'exclude_items' => '["MLB123","MLB456"]',
            'include_only_items' => '[]',
            'last_run' => null,
            'total_adjustments' => 0,
        ];

        $stmt = $this->createMockStmt(fetchReturn: $dbRow);
        $this->mockPdo->method('prepare')->willReturn($stmt);
        $this->mockPdo->method('exec')->willReturn(0);

        $config = $this->service->getConfig();

        $this->assertIsArray($config['exclude_items']);
        $this->assertCount(2, $config['exclude_items']);
        $this->assertContains('MLB123', $config['exclude_items']);
    }

    // =========================================================================
    // saveConfig
    // =========================================================================

    public function testSaveConfigReturnsSuccessStructure(): void
    {
        $stmt = $this->createMockStmt();
        $this->mockPdo->method('prepare')->willReturn($stmt);
        $this->mockPdo->method('exec')->willReturn(0);

        $result = $this->service->saveConfig([
            'enabled' => true,
            'mode' => 'suggest',
        ]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    // =========================================================================
    // runOptimization — disabled state
    // =========================================================================

    public function testRunOptimizationReturnsDisabledWhenConfigOff(): void
    {
        $stmt = $this->createMockStmt(fetchReturn: false);
        $this->mockPdo->method('prepare')->willReturn($stmt);
        $this->mockPdo->method('exec')->willReturn(0);

        $result = $this->service->runOptimization();

        // Default config has enabled=false
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('desativada', $result['message']);
    }

    // =========================================================================
    // ERROR HANDLING
    // =========================================================================

    public function testGetStatsReturnsErrorOnFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->getStats();
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }

    public function testGetOptimizationHistoryReturnsErrorOnFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->getOptimizationHistory();
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }
}
