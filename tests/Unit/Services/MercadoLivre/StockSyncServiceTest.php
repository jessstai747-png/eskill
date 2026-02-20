<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MercadoLivre;

use App\Services\CacheService;
use App\Services\MercadoLivre\StockSyncService;
use ReflectionClass;
use Tests\TestCase;

/**
 * @covers \App\Services\MercadoLivre\StockSyncService
 */
class StockSyncServiceTest extends TestCase
{
    private ReflectionClass $reflection;
    private StockSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(StockSyncService::class);
        $this->service = $this->reflection->newInstanceWithoutConstructor();
    }

    public function testCalculateTargetQuantityMirrorMode(): void
    {
        $result = $this->service->calculateTargetQuantity(12, ['sync_mode' => 'mirror', 'max_stock' => null]);
        $this->assertSame(12, $result);
    }

    public function testCalculateTargetQuantityOffsetMode(): void
    {
        $result = $this->service->calculateTargetQuantity(10, [
            'sync_mode' => 'offset',
            'offset_value' => -3,
            'max_stock' => null,
        ]);
        $this->assertSame(7, $result);
    }

    public function testCalculateTargetQuantityPercentageMode(): void
    {
        $result = $this->service->calculateTargetQuantity(11, [
            'sync_mode' => 'percentage',
            'percentage_value' => 150.0,
            'max_stock' => null,
        ]);
        $this->assertSame(17, $result);
    }

    public function testCalculateTargetQuantityRespectsMinAndMax(): void
    {
        $belowMin = $this->service->calculateTargetQuantity(2, [
            'sync_mode' => 'mirror',
            'min_stock' => 5,
            'max_stock' => null,
        ]);
        $aboveMax = $this->service->calculateTargetQuantity(20, [
            'sync_mode' => 'mirror',
            'min_stock' => 0,
            'max_stock' => 9,
        ]);

        $this->assertSame(5, $belowMin);
        $this->assertSame(9, $aboveMax);
    }

    public function testCalculateTargetQuantityNeverReturnsNegative(): void
    {
        $result = $this->service->calculateTargetQuantity(1, [
            'sync_mode' => 'offset',
            'offset_value' => -10,
            'min_stock' => 0,
            'max_stock' => null,
        ]);

        $this->assertSame(0, $result);
    }

    public function testValidateRuleDataThrowsWhenRequiredFieldMissing(): void
    {
        $method = $this->reflection->getMethod('validateRuleData');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Campo obrigatório ausente');

        $method->invoke($this->service, [
            'user_id' => 1,
            'source_account_id' => 10,
            'target_account_id' => 20,
            'source_item_id' => 'MLB1',
        ]);
    }

    public function testValidateRuleDataThrowsForSameSourceAndTarget(): void
    {
        $method = $this->reflection->getMethod('validateRuleData');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Source e target não podem ser o mesmo item na mesma conta');

        $method->invoke($this->service, [
            'user_id' => 1,
            'source_account_id' => 10,
            'target_account_id' => 10,
            'source_item_id' => 'MLB1',
            'target_item_id' => 'MLB1',
        ]);
    }

    public function testValidateRuleDataThrowsForInvalidSyncMode(): void
    {
        $method = $this->reflection->getMethod('validateRuleData');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Modo de sync inválido');

        $method->invoke($this->service, [
            'user_id' => 1,
            'source_account_id' => 10,
            'target_account_id' => 11,
            'source_item_id' => 'MLB1',
            'target_item_id' => 'MLB2',
            'sync_mode' => 'invalid_mode',
        ]);
    }

    public function testValidateRuleDataAcceptsValidPayload(): void
    {
        $method = $this->reflection->getMethod('validateRuleData');
        $method->setAccessible(true);

        $method->invoke($this->service, [
            'user_id' => 1,
            'source_account_id' => 10,
            'target_account_id' => 11,
            'source_item_id' => 'MLB1',
            'target_item_id' => 'MLB2',
            'sync_mode' => 'mirror',
        ]);

        $this->assertTrue(true);
    }

    public function testCheckRateLimitReturnsTrueWhenBelowLimit(): void
    {
        $cache = $this->createMock(CacheService::class);
        $cache->method('get')->willReturn(5);
        $this->setPrivateProperty('cache', $cache);
        $this->setPrivateProperty('rateLimitPerMinute', 30);

        $method = $this->reflection->getMethod('checkRateLimit');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->service));
    }

    public function testCheckRateLimitReturnsFalseWhenAtLimit(): void
    {
        $cache = $this->createMock(CacheService::class);
        $cache->method('get')->willReturn(30);
        $this->setPrivateProperty('cache', $cache);
        $this->setPrivateProperty('rateLimitPerMinute', 30);

        $method = $this->reflection->getMethod('checkRateLimit');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($this->service));
    }

    public function testTrackApiCallIncrementsCounter(): void
    {
        $store = [];
        $cache = $this->createMock(CacheService::class);
        $cache->method('get')->willReturnCallback(function (string $key) use (&$store) {
            return $store[$key] ?? null;
        });
        $cache->method('set')->willReturnCallback(function (string $key, mixed $value, int $ttl) use (&$store) {
            $store[$key] = $value;
            $this->assertGreaterThan(0, $ttl);
            return true;
        });

        $this->setPrivateProperty('cache', $cache);

        $method = $this->reflection->getMethod('trackApiCall');
        $method->setAccessible(true);
        $method->invoke($this->service);
        $method->invoke($this->service);

        $this->assertSame(2, array_values($store)[0] ?? 0);
    }

    private function setPrivateProperty(string $property, mixed $value): void
    {
        $prop = $this->reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($this->service, $value);
    }
}
