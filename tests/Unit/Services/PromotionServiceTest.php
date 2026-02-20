<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PromotionService;
use ReflectionClass;
use Tests\TestCase;

/**
 * @covers \App\Services\PromotionService
 */
class PromotionServiceTest extends TestCase
{
    private ReflectionClass $reflection;
    private PromotionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(PromotionService::class);
        $this->service = $this->reflection->newInstanceWithoutConstructor();
    }

    public function testBuildCouponPayloadAppliesDefaults(): void
    {
        $payload = $this->invokePrivate('buildCouponPayload', [[
            'code' => 'CUPOMTESTE',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'max_uses' => 100,
            'start_date' => '2026-02-20',
            'end_date' => '2026-02-28',
        ]]);

        $this->assertSame('CUPOMTESTE', $payload['code']);
        $this->assertSame('percentage', $payload['discount_type']);
        $this->assertSame(10, $payload['discount_value']);
        $this->assertSame(100, $payload['max_uses']);
        $this->assertSame([], $payload['items']);
    }

    public function testCalculateConversionRateHandlesZeroImpressions(): void
    {
        $rate = $this->invokePrivate('calculateConversionRate', [[
            'redemptions' => 10,
            'impressions' => 0,
        ]]);

        $this->assertSame(0.0, $rate);
    }

    public function testCalculateConversionRateUsesRedemptionsAndImpressions(): void
    {
        $rate = $this->invokePrivate('calculateConversionRate', [[
            'redemptions' => 5,
            'impressions' => 100,
        ]]);

        $this->assertSame(5.0, $rate);
    }

    public function testCalculateCouponRoiHandlesZeroDiscount(): void
    {
        $roi = $this->invokePrivate('calculateCouponROI', [[
            'revenue' => 1000,
            'discount_given' => 0,
        ]]);

        $this->assertSame(0.0, $roi);
    }

    public function testCalculateCouponRoiReturnsPercentage(): void
    {
        $roi = $this->invokePrivate('calculateCouponROI', [[
            'revenue' => 2000,
            'discount_given' => 500,
        ]]);

        $this->assertSame(300.0, $roi);
    }

    public function testGetEmptyPromotionsHasCanonicalShape(): void
    {
        $empty = $this->invokePrivate('getEmptyPromotions');
        $this->assertSame(0, $empty['total']);
        $this->assertSame([], $empty['promotions']);
    }

    public function testGetEmptyCouponMetricsHasCanonicalShape(): void
    {
        $empty = $this->invokePrivate('getEmptyCouponMetrics');
        $this->assertSame(0, $empty['redemptions']);
        $this->assertSame(0, $empty['revenue']);
        $this->assertSame(0, $empty['roi']);
    }

    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($this->service, $args);
    }
}
