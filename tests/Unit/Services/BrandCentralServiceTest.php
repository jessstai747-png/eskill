<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BrandCentralService;
use ReflectionClass;
use Tests\TestCase;

/**
 * @covers \App\Services\BrandCentralService
 */
class BrandCentralServiceTest extends TestCase
{
    private ReflectionClass $reflection;
    private BrandCentralService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(BrandCentralService::class);
        $this->service = $this->reflection->newInstanceWithoutConstructor();
    }

    public function testBuildCustomizationPayloadIncludesOnlyKnownFields(): void
    {
        $payload = $this->invokePrivate('buildCustomizationPayload', [[
            'primary_color' => '#FF0000',
            'banner_url' => 'https://example.com/banner.png',
            'layout' => 'grid',
            'description' => 'Loja oficial',
            'logo' => 'https://example.com/logo.png',
            'ignored' => 'value',
        ]]);

        $this->assertSame('#FF0000', $payload['primary_color']);
        $this->assertSame('https://example.com/banner.png', $payload['banner_url']);
        $this->assertSame('grid', $payload['layout']);
        $this->assertSame('Loja oficial', $payload['description']);
        $this->assertSame('https://example.com/logo.png', $payload['logo']);
        $this->assertArrayNotHasKey('ignored', $payload);
    }

    public function testCalculateRepeatRateHandlesZeroTotalsSafely(): void
    {
        $this->assertSame(0.0, $this->invokePrivate('calculateRepeatRate', [[
            'total_sales' => 0,
            'unique_buyers' => 10,
        ]]));

        $this->assertSame(0.0, $this->invokePrivate('calculateRepeatRate', [[
            'total_sales' => 100,
            'unique_buyers' => 0,
        ]]));
    }

    public function testCalculateRepeatRateReturnsPercentage(): void
    {
        $rate = $this->invokePrivate('calculateRepeatRate', [[
            'total_sales' => 100,
            'unique_buyers' => 80,
        ]]);

        $this->assertSame(20.0, $rate);
    }

    public function testCalculateLoyaltyScoreIsCappedAt100(): void
    {
        $score = $this->invokePrivate('calculateLoyaltyScore', [[
            'total_sales' => 500,
            'unique_buyers' => 300,
            'avg_ticket' => 250,
        ], 5000]);

        $this->assertSame(100, $score);
    }

    public function testGetStoreIdReadsWrappedStorePayload(): void
    {
        $service = $this->getMockBuilder(BrandCentralService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBrandStore'])
            ->getMock();

        $service->method('getBrandStore')->willReturn([
            'success' => true,
            'data' => ['id' => 'STORE-123'],
        ]);

        $method = (new ReflectionClass($service))->getMethod('getStoreId');
        $method->setAccessible(true);

        $this->assertSame('STORE-123', $method->invoke($service));
    }

    public function testGetEmptyStoreHasExpectedShape(): void
    {
        $store = $this->invokePrivate('getEmptyStore');
        $this->assertNull($store['id']);
        $this->assertSame('', $store['name']);
        $this->assertSame('inactive', $store['status']);
        $this->assertSame('#000000', $store['customization']['primary_color']);
    }

    public function testGetEmptyPerformanceHasExpectedShape(): void
    {
        $result = $this->invokePrivate('getEmptyPerformance', [[
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-20',
        ]]);

        $this->assertSame('2026-02-01', $result['period']['start']);
        $this->assertSame('2026-02-20', $result['period']['end']);
        $this->assertSame(0.0, $result['total_revenue']);
        $this->assertSame(0, $result['brand_loyalty_score']);
    }

    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($this->service, $args);
    }
}
