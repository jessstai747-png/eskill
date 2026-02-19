<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AIPredictionsService;
use PDO;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Unit Tests for AIPredictionsService
 *
 * Tests ML prediction algorithms: linear regression, exponential smoothing,
 * seasonal forecast, confidence calculation, and trend detection.
 *
 * @covers \App\Services\AIPredictionsService
 */
class AIPredictionsServiceTest extends TestCase
{
    private AIPredictionsService $service;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ref = new ReflectionClass(AIPredictionsService::class);
        $instance = $this->ref->newInstanceWithoutConstructor();

        // Inject mock PDO
        $mockPdo = $this->createMock(PDO::class);
        $dbProp = $this->ref->getProperty('db');
        $dbProp->setAccessible(true);
        $dbProp->setValue($instance, $mockPdo);

        $this->service = $instance;
    }

    // =========================================================================
    // INSTANTIATION
    // =========================================================================

    public function testServiceCanBeInstantiatedViaReflection(): void
    {
        $this->assertInstanceOf(AIPredictionsService::class, $this->service);
    }

    public function testServiceHasRequiredPublicMethods(): void
    {
        $methods = ['predictSales', 'identifyRisingStars', 'predictBestPromotionTime', 'predictCategoryDemand'];

        foreach ($methods as $method) {
            $this->assertTrue(method_exists($this->service, $method), "Missing: {$method}");
        }
    }

    // =========================================================================
    // linearRegression (private, pure math)
    // =========================================================================

    public function testLinearRegressionReturnsCorrectStepCount(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'linearRegression');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [10, 20, 30, 40, 50], 5);

        $this->assertCount(5, $result);
    }

    public function testLinearRegressionPredictsTrend(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'linearRegression');
        $method->setAccessible(true);

        // Perfect linear: 10, 20, 30, 40, 50 → next should be ~60, 70, ...
        $result = $method->invoke($this->service, [10, 20, 30, 40, 50], 3);

        $this->assertEqualsWithDelta(60.0, $result[0], 1.0);
        $this->assertEqualsWithDelta(70.0, $result[1], 1.0);
        $this->assertEqualsWithDelta(80.0, $result[2], 1.0);
    }

    public function testLinearRegressionWithFlatData(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'linearRegression');
        $method->setAccessible(true);

        // Flat data: 50, 50, 50 → predictions should be ~50
        $result = $method->invoke($this->service, [50, 50, 50], 2);

        $this->assertEqualsWithDelta(50.0, $result[0], 1.0);
        $this->assertEqualsWithDelta(50.0, $result[1], 1.0);
    }

    // =========================================================================
    // exponentialSmoothing (private, pure math)
    // =========================================================================

    public function testExponentialSmoothingReturnsCorrectStepCount(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'exponentialSmoothing');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [10, 20, 30], 5, 0.3);

        $this->assertCount(5, $result);
    }

    public function testExponentialSmoothingReturnsConstantForecast(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'exponentialSmoothing');
        $method->setAccessible(true);

        // All forecasted steps are equal (same "last" smoothed value)
        $result = $method->invoke($this->service, [10, 20, 30], 3, 0.3);

        $this->assertSame($result[0], $result[1]);
        $this->assertSame($result[1], $result[2]);
    }

    public function testExponentialSmoothingWithHighAlpha(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'exponentialSmoothing');
        $method->setAccessible(true);

        // alpha=1.0 → forecast equals last data point
        $result = $method->invoke($this->service, [10, 20, 30], 1, 1.0);

        $this->assertEqualsWithDelta(30.0, $result[0], 0.01);
    }

    // =========================================================================
    // seasonalForecast (private, pure math)
    // =========================================================================

    public function testSeasonalForecastReturnsCorrectStepCount(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'seasonalForecast');
        $method->setAccessible(true);

        $data = [10, 12, 15, 20, 18, 14, 11]; // One week
        $result = $method->invoke($this->service, $data, 7);

        $this->assertCount(7, $result);
    }

    public function testSeasonalForecastRepeatsPattern(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'seasonalForecast');
        $method->setAccessible(true);

        // 2 weeks of identical data → forecast should match pattern
        $week = [10, 20, 30, 40, 50, 60, 70];
        $data = array_merge($week, $week);
        $result = $method->invoke($this->service, $data, 7);

        // First 7 predictions should match the weekly pattern
        for ($i = 0; $i < 7; $i++) {
            $this->assertEqualsWithDelta($week[$i], $result[$i], 1.0, "Day {$i} mismatch");
        }
    }

    // =========================================================================
    // calculateConfidence (private, pure math)
    // =========================================================================

    public function testCalculateConfidenceReturnsFloat(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'calculateConfidence');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [10, 20, 30], 30);

        $this->assertIsFloat($result);
    }

    public function testCalculateConfidenceCapsAt100(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'calculateConfidence');
        $method->setAccessible(true);

        // Very stable data (low variance) + lots of data points
        $result = $method->invoke($this->service, [50, 50, 50, 50, 50], 200);

        $this->assertLessThanOrEqual(100.0, $result);
    }

    public function testCalculateConfidenceHigherWithMoreData(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'calculateConfidence');
        $method->setAccessible(true);

        $lowData = $method->invoke($this->service, [10, 20, 30], 10);
        $highData = $method->invoke($this->service, [10, 20, 30], 90);

        $this->assertGreaterThanOrEqual($lowData, $highData);
    }

    public function testCalculateConfidenceHigherWithLowVariance(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'calculateConfidence');
        $method->setAccessible(true);

        $highVariance = $method->invoke($this->service, [10, 100, 10, 100], 30);
        $lowVariance = $method->invoke($this->service, [50, 51, 50, 51], 30);

        $this->assertGreaterThanOrEqual($highVariance, $lowVariance);
    }

    // =========================================================================
    // getConfidenceLevel (private, pure mapping)
    // =========================================================================

    public function testGetConfidenceLevelVeryHigh(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'getConfidenceLevel');
        $method->setAccessible(true);

        $this->assertSame('very_high', $method->invoke($this->service, 85.0));
        $this->assertSame('very_high', $method->invoke($this->service, 80.0));
    }

    public function testGetConfidenceLevelHigh(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'getConfidenceLevel');
        $method->setAccessible(true);

        $this->assertSame('high', $method->invoke($this->service, 60.0));
        $this->assertSame('high', $method->invoke($this->service, 79.9));
    }

    public function testGetConfidenceLevelMedium(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'getConfidenceLevel');
        $method->setAccessible(true);

        $this->assertSame('medium', $method->invoke($this->service, 40.0));
        $this->assertSame('medium', $method->invoke($this->service, 59.9));
    }

    public function testGetConfidenceLevelLow(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'getConfidenceLevel');
        $method->setAccessible(true);

        $this->assertSame('low', $method->invoke($this->service, 20.0));
        $this->assertSame('low', $method->invoke($this->service, 39.9));
    }

    // =========================================================================
    // detectTrend (private, pure logic)
    // =========================================================================

    public function testDetectTrendRising(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'detectTrend');
        $method->setAccessible(true);

        // Second half much higher than first
        $result = $method->invoke($this->service, [10, 12, 14, 30, 35, 40]);

        $this->assertSame('rising', $result);
    }

    public function testDetectTrendFalling(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'detectTrend');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [40, 35, 30, 10, 12, 8]);

        $this->assertSame('falling', $result);
    }

    public function testDetectTrendStable(): void
    {
        $method = new ReflectionMethod(AIPredictionsService::class, 'detectTrend');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, [50, 51, 49, 50, 51, 49]);

        $this->assertSame('stable', $result);
    }
}
