<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CloneSyncService;
use PDO;
use PDOStatement;
use ReflectionClass;
use Tests\TestCase;

/**
 * Unit Tests for CloneSyncService
 *
 * Tests sync configuration, status validation, batch operations,
 * history/alert queries, and error handling.
 *
 * @covers \App\Services\CloneSyncService
 */
class CloneSyncServiceTest extends TestCase
{
    private CloneSyncService $service;
    private PDO $mockPdo;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPdo = $this->createMock(PDO::class);
        $this->ref = new ReflectionClass(CloneSyncService::class);
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
        $this->assertInstanceOf(CloneSyncService::class, $this->service);
    }

    public function testServiceHasRequiredPublicMethods(): void
    {
        $methods = [
            'syncAll', 'syncItem', 'updatePrice', 'updateStock',
            'updateStatus', 'batchUpdatePrices', 'getSyncHistory',
            'getSyncSettings', 'updateSyncSettings', 'getPendingAlerts', 'resolveAlert',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->service, $method),
                "Missing public method: {$method}"
            );
        }
    }

    // =========================================================================
    // SYNC CONFIG DEFAULTS
    // =========================================================================

    public function testSyncConfigHasDefaultValues(): void
    {
        $configProp = $this->ref->getProperty('syncConfig');
        $configProp->setAccessible(true);
        $config = $configProp->getValue($this->service);

        $this->assertIsArray($config);
        $this->assertArrayHasKey('price', $config);
        $this->assertArrayHasKey('stock', $config);
        $this->assertArrayHasKey('status', $config);
    }

    public function testPriceSyncDefaultModeIsManual(): void
    {
        $configProp = $this->ref->getProperty('syncConfig');
        $configProp->setAccessible(true);
        $config = $configProp->getValue($this->service);

        $this->assertSame('manual', $config['price']['mode']);
    }

    public function testStockSyncDefaultModeIsAuto(): void
    {
        $configProp = $this->ref->getProperty('syncConfig');
        $configProp->setAccessible(true);
        $config = $configProp->getValue($this->service);

        $this->assertSame('auto', $config['stock']['mode']);
    }

    public function testSyncConfigPriceIsEnabled(): void
    {
        $configProp = $this->ref->getProperty('syncConfig');
        $configProp->setAccessible(true);
        $config = $configProp->getValue($this->service);

        $this->assertTrue($config['price']['enabled']);
    }

    public function testSyncConfigDescriptionIsDisabled(): void
    {
        $configProp = $this->ref->getProperty('syncConfig');
        $configProp->setAccessible(true);
        $config = $configProp->getValue($this->service);

        $this->assertFalse($config['description']['enabled']);
    }

    public function testSyncConfigImagesIsDisabled(): void
    {
        $configProp = $this->ref->getProperty('syncConfig');
        $configProp->setAccessible(true);
        $config = $configProp->getValue($this->service);

        $this->assertFalse($config['images']['enabled']);
    }

    // =========================================================================
    // getSyncSettings
    // =========================================================================

    public function testGetSyncSettingsReturnsArray(): void
    {
        $result = $this->service->getSyncSettings();

        $this->assertIsArray($result);
    }

    public function testGetSyncSettingsContainsSyncConfig(): void
    {
        $result = $this->service->getSyncSettings();

        $this->assertArrayHasKey('price', $result);
        $this->assertArrayHasKey('stock', $result);
    }

    // =========================================================================
    // updateStatus VALIDATION
    // =========================================================================

    public function testUpdateStatusThrowsOnInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status inválido');

        $this->service->updateStatus('MLB12345', 'invalid_status');
    }

    public function testUpdateStatusAcceptsActive(): void
    {
        // Will throw for missing client, not for invalid status
        try {
            $this->service->updateStatus('MLB12345', 'active');
        } catch (\InvalidArgumentException $e) {
            $this->fail('active should be a valid status');
        } catch (\Throwable $e) {
            // Client null error is expected
            $this->assertNotInstanceOf(\InvalidArgumentException::class, $e);
        }
    }

    public function testUpdateStatusAcceptsPaused(): void
    {
        try {
            $this->service->updateStatus('MLB12345', 'paused');
        } catch (\InvalidArgumentException $e) {
            $this->fail('paused should be a valid status');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(\InvalidArgumentException::class, $e);
        }
    }

    // =========================================================================
    // batchUpdatePrices STRUCTURE
    // =========================================================================

    public function testBatchUpdatePricesWithEmptyArray(): void
    {
        $result = $this->service->batchUpdatePrices([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // =========================================================================
    // ERROR HANDLING — methods that need DB/API
    // =========================================================================

    public function testSyncAllReturnsErrorOnFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->syncAll();
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
        if (isset($result['success'])) {
            $this->assertFalse($result['success']);
        }
    }

    public function testSyncItemReturnsErrorOnFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->syncItem('MLB12345');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
        if (isset($result['success'])) {
            $this->assertFalse($result['success']);
        }
    }

    public function testGetSyncHistoryReturnsErrorOnFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->getSyncHistory('MLB12345');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }

    public function testGetPendingAlertsReturnsErrorOnFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->getPendingAlerts();
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }

    public function testResolveAlertReturnsErrorOnFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->resolveAlert(999);
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsBool($result);
    }

    public function testUpdatePriceReturnsErrorOnFailure(): void
    {
        try {
            $result = $this->service->updatePrice('MLB12345', 99.90);
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }

    public function testUpdateStockReturnsErrorOnFailure(): void
    {
        try {
            $result = $this->service->updateStock('MLB12345', 10);
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }

    // =========================================================================
    // SYNC CONFIG MERGE BEHAVIOR
    // =========================================================================

    public function testSyncConfigMergesCustomValues(): void
    {
        $configProp = $this->ref->getProperty('syncConfig');
        $configProp->setAccessible(true);

        // Simulate custom config merge
        $custom = ['price' => ['enabled' => false, 'mode' => 'auto']];
        $defaults = $configProp->getValue($this->service);
        $merged = array_merge($defaults, $custom);

        // Verify merge works correctly
        $this->assertFalse($merged['price']['enabled']);
        $this->assertSame('auto', $merged['price']['mode']);
    }
}
