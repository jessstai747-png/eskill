<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use App\Services\RateLimitTrackerService;
use App\Services\AdvancedRedisCacheService;

/**
 * @covers \App\Services\RateLimitTrackerService
 */
class RateLimitTrackerServiceTest extends TestCase
{
    private RateLimitTrackerService $service;
    private AdvancedRedisCacheService|MockObject $mockRedis;

    protected function setUp(): void
    {
        $this->mockRedis = $this->createMock(AdvancedRedisCacheService::class);
        $this->service = new RateLimitTrackerService($this->mockRedis);
    }

    // ── Constructor ─────────────────────────────────────────────────

    public function testConstructorWithInjectedRedis(): void
    {
        $redis = $this->createMock(AdvancedRedisCacheService::class);
        $service = new RateLimitTrackerService($redis);
        $this->assertInstanceOf(RateLimitTrackerService::class, $service);
    }

    // ── trackCall ───────────────────────────────────────────────────

    public function testTrackCallIncrementsCounters(): void
    {
        $this->mockRedis->expects($this->exactly(3))
            ->method('increment');

        $this->mockRedis->expects($this->exactly(3))
            ->method('expire');

        $result = $this->service->trackCall('mercadolivre', 'items');
        $this->assertTrue($result);
    }

    public function testTrackCallForAnthropicProvider(): void
    {
        $this->mockRedis->expects($this->exactly(3))
            ->method('increment');

        $this->mockRedis->expects($this->exactly(3))
            ->method('expire');

        $result = $this->service->trackCall('anthropic');
        $this->assertTrue($result);
    }

    // ── canMakeCall ─────────────────────────────────────────────────

    public function testCanMakeCallReturnsTrueWhenUnderLimit(): void
    {
        $this->mockRedis->method('get')->willReturn('5');

        $result = $this->service->canMakeCall('mercadolivre');
        $this->assertTrue($result['can_call']);
        $this->assertSame('', $result['reason']);
        $this->assertSame(0, $result['wait_seconds']);
    }

    public function testCanMakeCallReturnsFalseWhenMinuteLimitReached(): void
    {
        $this->mockRedis->method('get')
            ->willReturnCallback(function (string $key) {
                if (str_contains($key, ':minute:')) {
                    return '60'; // At ML limit
                }
                return '100';
            });

        $result = $this->service->canMakeCall('mercadolivre');
        $this->assertFalse($result['can_call']);
        $this->assertSame('Minute limit reached', $result['reason']);
        $this->assertGreaterThan(0, $result['wait_seconds']);
    }

    public function testCanMakeCallReturnsFalseWhenHourLimitReached(): void
    {
        $this->mockRedis->method('get')
            ->willReturnCallback(function (string $key) {
                if (str_contains($key, ':minute:')) {
                    return '10'; // Under minute limit
                }
                if (str_contains($key, ':hour:')) {
                    return '3000'; // At ML hour limit
                }
                return '0';
            });

        $result = $this->service->canMakeCall('mercadolivre');
        $this->assertFalse($result['can_call']);
        $this->assertSame('Hour limit reached', $result['reason']);
    }

    public function testCanMakeCallAnthropicDayLimit(): void
    {
        $this->mockRedis->method('get')
            ->willReturnCallback(function (string $key) {
                if (str_contains($key, ':minute:')) {
                    return '10';
                }
                if (str_contains($key, ':day:')) {
                    return '10000'; // At Anthropic day limit
                }
                return '0';
            });

        $result = $this->service->canMakeCall('anthropic');
        $this->assertFalse($result['can_call']);
        $this->assertSame('Daily limit reached', $result['reason']);
    }

    public function testCanMakeCallReturnsUsagePercentage(): void
    {
        $this->mockRedis->method('get')
            ->willReturnCallback(function (string $key) {
                if (str_contains($key, ':minute:')) {
                    return '30';
                }
                if (str_contains($key, ':hour:')) {
                    return '1500';
                }
                return '0';
            });

        $result = $this->service->canMakeCall('mercadolivre');
        $this->assertArrayHasKey('minute', $result['usage_percentage']);
        $this->assertSame(50.0, $result['usage_percentage']['minute']);
        $this->assertSame(50.0, $result['usage_percentage']['hour']);
    }

    // ── getCurrentUsage ─────────────────────────────────────────────

    public function testGetCurrentUsageReturnsCorrectValues(): void
    {
        $this->mockRedis->method('get')
            ->willReturnCallback(function (string $key) {
                if (str_contains($key, ':minute:')) {
                    return '15';
                }
                if (str_contains($key, ':hour:')) {
                    return '200';
                }
                if (str_contains($key, ':day:')) {
                    return '5000';
                }
                return '0';
            });

        $usage = $this->service->getCurrentUsage('mercadolivre');
        $this->assertSame(15, $usage['minute']);
        $this->assertSame(200, $usage['hour']);
        $this->assertSame(5000, $usage['day']);
    }

    public function testGetCurrentUsageReturnsZeroWhenNoData(): void
    {
        $this->mockRedis->method('get')->willReturn(null);

        $usage = $this->service->getCurrentUsage('mercadolivre');
        $this->assertSame(0, $usage['minute']);
        $this->assertSame(0, $usage['hour']);
        $this->assertSame(0, $usage['day']);
    }

    // ── shouldAlert ─────────────────────────────────────────────────

    public function testShouldAlertReturnsNullWhenUnderThreshold(): void
    {
        $this->mockRedis->method('get')->willReturn('10');

        $result = $this->service->shouldAlert('mercadolivre');
        $this->assertNull($result);
    }

    public function testShouldAlertReturnsWarningAt80Percent(): void
    {
        $this->mockRedis->method('get')
            ->willReturnCallback(function (string $key) {
                if (str_contains($key, ':minute:')) {
                    return '50'; // 83% of 60
                }
                return '100';
            });

        $result = $this->service->shouldAlert('mercadolivre');
        $this->assertNotNull($result);
        $this->assertSame('warning', $result['level']);
    }

    public function testShouldAlertReturnsCriticalAt95Percent(): void
    {
        $this->mockRedis->method('get')
            ->willReturnCallback(function (string $key) {
                if (str_contains($key, ':minute:')) {
                    return '58'; // 96.7% of 60
                }
                return '100';
            });

        $result = $this->service->shouldAlert('mercadolivre');
        $this->assertNotNull($result);
        $this->assertSame('critical', $result['level']);
    }

    // ── predictLimitHit ─────────────────────────────────────────────

    public function testPredictLimitHitNoRisk(): void
    {
        $this->mockRedis->method('get')
            ->willReturnCallback(function (string $key) {
                if (str_contains($key, ':minute:')) {
                    return '5';
                }
                if (str_contains($key, ':hour:')) {
                    return '100';
                }
                return '0';
            });

        $result = $this->service->predictLimitHit('mercadolivre', 5);
        $this->assertFalse($result['will_hit_limit']);
        $this->assertSame('normal', $result['recommendation']);
    }

    public function testPredictLimitHitWillHit(): void
    {
        $this->mockRedis->method('get')
            ->willReturnCallback(function (string $key) {
                if (str_contains($key, ':minute:')) {
                    return '50';
                }
                if (str_contains($key, ':hour:')) {
                    return '2800';
                }
                return '0';
            });

        $result = $this->service->predictLimitHit('mercadolivre', 5);
        $this->assertTrue($result['will_hit_limit']);
        $this->assertSame('throttle', $result['recommendation']);
    }

    // ── getStatus ───────────────────────────────────────────────────

    public function testGetStatusReturnsAllProviders(): void
    {
        $this->mockRedis->method('get')->willReturn('10');

        $status = $this->service->getStatus();
        $this->assertArrayHasKey('mercadolivre', $status);
        $this->assertArrayHasKey('anthropic', $status);
        $this->assertArrayHasKey('can_call', $status['mercadolivre']);
        $this->assertArrayHasKey('prediction', $status['mercadolivre']);
    }

    // ── resetCounters ───────────────────────────────────────────────

    public function testResetCountersCallsDelete(): void
    {
        $this->mockRedis->expects($this->exactly(3))
            ->method('delete');

        $result = $this->service->resetCounters('mercadolivre');
        $this->assertTrue($result);
    }

    // ── Default provider limits ─────────────────────────────────────

    public function testUnknownProviderGetsDefaultLimits(): void
    {
        $this->mockRedis->method('get')->willReturn('0');

        $result = $this->service->canMakeCall('unknown_provider');
        $this->assertTrue($result['can_call']);
        $this->assertArrayHasKey('minute', $result['limits']);
        $this->assertSame(60, $result['limits']['minute']);
        $this->assertSame(1000, $result['limits']['hour']);
    }
}
