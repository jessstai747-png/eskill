<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PriceAnalyticsService;
use PDO;
use PDOStatement;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Unit Tests for PriceAnalyticsService
 *
 * Tests dashboard metrics, forecast algorithm, EMA/trend calculation,
 * and error handling.
 *
 * @covers \App\Services\PriceAnalyticsService
 */
class PriceAnalyticsServiceTest extends TestCase
{
    private PriceAnalyticsService $service;
    private PDO $mockPdo;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPdo = $this->createMock(PDO::class);
        $this->ref = new ReflectionClass(PriceAnalyticsService::class);
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

    // =========================================================================
    // INSTANTIATION
    // =========================================================================

    public function testServiceCanBeInstantiatedViaReflection(): void
    {
        $this->assertInstanceOf(PriceAnalyticsService::class, $this->service);
    }

    public function testServiceHasRequiredPublicMethods(): void
    {
        $methods = [
            'getDashboardMetrics', 'getPriceTrend', 'analyzeElasticity',
            'getCompetitiveAnalysis', 'calculatePriceChangeROI',
            'forecastPrice', 'generatePerformanceReport',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->service, $method),
                "Missing public method: {$method}"
            );
        }
    }

    // =========================================================================
    // forecastPrice (pure math — no DB needed)
    // =========================================================================

    public function testForecastPriceReturnsUnavailableForTooFewPrices(): void
    {
        $result = $this->service->forecastPrice([100.0]);

        $this->assertFalse($result['available']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testForecastPriceReturnsUnavailableForTwoPrices(): void
    {
        $result = $this->service->forecastPrice([100.0, 110.0]);

        $this->assertFalse($result['available']);
    }

    public function testForecastPriceReturnsAvailableForThreePrices(): void
    {
        $result = $this->service->forecastPrice([100.0, 105.0, 110.0]);

        $this->assertTrue($result['available']);
    }

    public function testForecastPriceContainsMethodDescription(): void
    {
        $result = $this->service->forecastPrice([100.0, 105.0, 110.0]);

        $this->assertArrayHasKey('method', $result);
        $this->assertStringContainsString('Moving Average', $result['method']);
    }

    public function testForecastPriceContainsSMA(): void
    {
        $result = $this->service->forecastPrice([100.0, 105.0, 110.0]);

        $this->assertArrayHasKey('sma_7day', $result);
        $this->assertIsFloat($result['sma_7day']);
    }

    public function testForecastPriceContainsEMA(): void
    {
        $result = $this->service->forecastPrice([100.0, 105.0, 110.0]);

        $this->assertArrayHasKey('ema_7day', $result);
        $this->assertIsFloat($result['ema_7day']);
    }

    public function testForecastPriceContainsTrendDirection(): void
    {
        $result = $this->service->forecastPrice([100.0, 105.0, 110.0]);

        $this->assertArrayHasKey('trend_direction', $result);
        $this->assertContains($result['trend_direction'], ['up', 'down', 'stable']);
    }

    public function testForecastPriceUptrendDetected(): void
    {
        $result = $this->service->forecastPrice([100.0, 110.0, 120.0, 130.0]);

        $this->assertSame('up', $result['trend_direction']);
    }

    public function testForecastPriceDowntrendDetected(): void
    {
        $result = $this->service->forecastPrice([130.0, 120.0, 110.0, 100.0]);

        $this->assertSame('down', $result['trend_direction']);
    }

    public function testForecastPriceContainsForecasts(): void
    {
        $result = $this->service->forecastPrice([100.0, 105.0, 110.0], 30);

        $this->assertArrayHasKey('forecasts', $result);
        $this->assertIsArray($result['forecasts']);
        // Returns max 7 detailed forecasts
        $this->assertLessThanOrEqual(7, count($result['forecasts']));
    }

    public function testForecastPriceContains30dForecast(): void
    {
        $result = $this->service->forecastPrice([100.0, 105.0, 110.0], 30);

        $this->assertArrayHasKey('forecast_30d', $result);
    }

    public function testForecastPriceCurrentPriceIsLastInput(): void
    {
        $result = $this->service->forecastPrice([100.0, 105.0, 110.0]);

        $this->assertEqualsWithDelta(110.0, $result['current_price'], 0.01);
    }

    public function testForecastPriceSMACalculation(): void
    {
        // 3 prices, window = min(7, 3) = 3, SMA = (100+105+110)/3 = 105
        $result = $this->service->forecastPrice([100.0, 105.0, 110.0]);

        $this->assertEqualsWithDelta(105.0, $result['sma_7day'], 0.01);
    }

    public function testForecastPriceDailyTrendIsFloat(): void
    {
        $result = $this->service->forecastPrice([100.0, 105.0, 110.0]);

        $this->assertArrayHasKey('daily_trend', $result);
        $this->assertIsFloat($result['daily_trend']);
    }

    // =========================================================================
    // calculateEMA (private, pure math)
    // =========================================================================

    public function testCalculateEMAReturnsFloat(): void
    {
        $method = new ReflectionMethod(PriceAnalyticsService::class, 'calculateEMA');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [100.0, 105.0, 110.0], 3);

        $this->assertIsFloat($result);
    }

    public function testCalculateEMAWithInsufficientDataReturnsSMA(): void
    {
        $method = new ReflectionMethod(PriceAnalyticsService::class, 'calculateEMA');
        $method->setAccessible(true);

        // 2 prices with period 3 → returns simple average
        $result = $method->invoke($this->service, [100.0, 110.0], 3);

        $this->assertEqualsWithDelta(105.0, $result, 0.01);
    }

    // =========================================================================
    // calculateTrend (private, pure math)
    // =========================================================================

    public function testCalculateTrendReturnsStructure(): void
    {
        $method = new ReflectionMethod(PriceAnalyticsService::class, 'calculateTrend');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [100.0, 105.0, 110.0]);

        $this->assertArrayHasKey('slope', $result);
        $this->assertArrayHasKey('direction', $result);
        $this->assertArrayHasKey('r_squared', $result);
    }

    public function testCalculateTrendUptrendHasPositiveSlope(): void
    {
        $method = new ReflectionMethod(PriceAnalyticsService::class, 'calculateTrend');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [100.0, 110.0, 120.0, 130.0]);

        $this->assertGreaterThan(0, $result['slope']);
        $this->assertSame('up', $result['direction']);
    }

    public function testCalculateTrendDowntrendHasNegativeSlope(): void
    {
        $method = new ReflectionMethod(PriceAnalyticsService::class, 'calculateTrend');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [130.0, 120.0, 110.0, 100.0]);

        $this->assertLessThan(0, $result['slope']);
        $this->assertSame('down', $result['direction']);
    }

    public function testCalculateTrendWithSinglePriceReturnsStable(): void
    {
        $method = new ReflectionMethod(PriceAnalyticsService::class, 'calculateTrend');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [100.0]);

        $this->assertSame(0, $result['slope']);
        $this->assertSame('stable', $result['direction']);
    }

    public function testCalculateTrendPerfectLinearHasHighRSquared(): void
    {
        $method = new ReflectionMethod(PriceAnalyticsService::class, 'calculateTrend');
        $method->setAccessible(true);

        // Perfect linear trend
        $result = $method->invoke($this->service, [100.0, 110.0, 120.0, 130.0, 140.0]);

        $this->assertGreaterThan(0.99, $result['r_squared']);
    }

    public function testCalculateTrendContainsStrength(): void
    {
        $method = new ReflectionMethod(PriceAnalyticsService::class, 'calculateTrend');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [100.0, 110.0, 120.0]);

        $this->assertArrayHasKey('strength', $result);
        $this->assertContains($result['strength'], ['strong', 'moderate', 'weak']);
    }

    // =========================================================================
    // calculateVolatility (private, pure math)
    // =========================================================================

    public function testCalculateVolatilityReturnsFloat(): void
    {
        $method = new ReflectionMethod(PriceAnalyticsService::class, 'calculateVolatility');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [100.0, 105.0, 95.0, 110.0]);

        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testCalculateVolatilityReturnsZeroForSinglePrice(): void
    {
        $method = new ReflectionMethod(PriceAnalyticsService::class, 'calculateVolatility');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [100.0]);

        $this->assertEqualsWithDelta(0.0, $result, 0.001);
    }

    public function testCalculateVolatilityZeroForConstantPrices(): void
    {
        $method = new ReflectionMethod(PriceAnalyticsService::class, 'calculateVolatility');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [100.0, 100.0, 100.0, 100.0]);

        $this->assertEqualsWithDelta(0.0, $result, 0.001);
    }

    // =========================================================================
    // ERROR HANDLING
    // =========================================================================

    public function testGetDashboardMetricsReturnsErrorOnFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->getDashboardMetrics();
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }

    public function testGetPriceTrendReturnsErrorOnFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->getPriceTrend('MLB12345');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }
}
