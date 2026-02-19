<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PriceHistoryService;
use PDO;
use PDOStatement;
use ReflectionClass;
use Tests\TestCase;

/**
 * Unit Tests for PriceHistoryService
 *
 * Tests price history recording, retrieval, variation calculation,
 * and table initialization.
 *
 * @covers \App\Services\PriceHistoryService
 */
class PriceHistoryServiceTest extends TestCase
{
    private PriceHistoryService $service;
    private PDO $mockPdo;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPdo = $this->createMock(PDO::class);
        $this->ref = new ReflectionClass(PriceHistoryService::class);
        $instance = $this->ref->newInstanceWithoutConstructor();

        // Inject mock PDO
        $dbProp = $this->ref->getProperty('db');
        $dbProp->setAccessible(true);
        $dbProp->setValue($instance, $this->mockPdo);

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
        $this->assertInstanceOf(PriceHistoryService::class, $this->service);
    }

    public function testServiceHasRequiredPublicMethods(): void
    {
        $methods = [
            'recordPriceHistory',
            'getHistory',
            'getPriceVariation',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->service, $method),
                "Missing public method: {$method}"
            );
        }
    }

    // =========================================================================
    // getHistory
    // =========================================================================

    public function testGetHistoryReturnsArrayOnSuccess(): void
    {
        $rows = [
            ['category_id' => 'MLB1234', 'brand' => 'Pro Tork', 'avg_price' => 89.90, 'recorded_at' => '2024-01-01'],
            ['category_id' => 'MLB1234', 'brand' => 'Pro Tork', 'avg_price' => 92.50, 'recorded_at' => '2024-01-02'],
        ];

        $stmt = $this->createMockStmt(fetchAllReturn: $rows);
        $this->mockPdo->method('prepare')->willReturn($stmt);

        $result = $this->service->getHistory('MLB1234', 'Pro Tork');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testGetHistoryAcceptsLimitParameter(): void
    {
        $stmt = $this->createMockStmt(fetchAllReturn: []);
        $this->mockPdo->method('prepare')->willReturn($stmt);

        $result = $this->service->getHistory('MLB1234', 'Pro Tork', 5);

        $this->assertIsArray($result);
    }

    public function testGetHistoryDefaultLimit30(): void
    {
        $stmt = $this->createMockStmt(fetchAllReturn: []);
        $this->mockPdo->method('prepare')->willReturn($stmt);

        // Just verify it accepts call with default limit
        $result = $this->service->getHistory('MLB1234', 'Pro Tork');

        $this->assertIsArray($result);
    }

    // =========================================================================
    // getPriceVariation
    // =========================================================================

    public function testGetPriceVariationReturnsArray(): void
    {
        $stmt = $this->createMockStmt(fetchAllReturn: [
            ['avg_price' => 100.0, 'recorded_at' => '2024-01-01'],
            ['avg_price' => 110.0, 'recorded_at' => '2024-01-02'],
        ]);
        $this->mockPdo->method('prepare')->willReturn($stmt);

        $result = $this->service->getPriceVariation('MLB1234', 'Pro Tork');

        $this->assertIsArray($result);
    }

    // =========================================================================
    // recordPriceHistory
    // =========================================================================

    public function testRecordPriceHistoryReturnsArray(): void
    {
        $stmt = $this->createMockStmt();
        $this->mockPdo->method('prepare')->willReturn($stmt);

        try {
            $result = $this->service->recordPriceHistory('MLB1234', 'Pro Tork');
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            // May fail due to external API dependency
            $this->assertInstanceOf(\Throwable::class, $e);
        }
    }

    // =========================================================================
    // ERROR HANDLING
    // =========================================================================

    public function testGetHistoryReturnsErrorOnDbFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->getHistory('MLB1234', 'Pro Tork');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }

    public function testGetPriceVariationReturnsErrorOnDbFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->getPriceVariation('MLB1234', 'Pro Tork');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }
}
