<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\MLStatisticsHelper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Helpers\MLStatisticsHelper
 */
class MLStatisticsHelperTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════════════
    // LINEAR REGRESSION
    // ═══════════════════════════════════════════════════════════════════════

    public function testLinearRegressionPerfectFit(): void
    {
        // y = 2x + 1 (perfect linear)
        $x = [1, 2, 3, 4, 5];
        $y = [3, 5, 7, 9, 11];

        $result = MLStatisticsHelper::linearRegression($x, $y);

        $this->assertTrue($result['valid']);
        $this->assertEqualsWithDelta(2.0, $result['slope'], 0.001);
        $this->assertEqualsWithDelta(1.0, $result['intercept'], 0.001);
        $this->assertEqualsWithDelta(1.0, $result['r_squared'], 0.001);
    }

    public function testLinearRegressionWithNoise(): void
    {
        $x = [1, 2, 3, 4, 5];
        $y = [2.1, 4.3, 5.8, 8.2, 9.9];

        $result = MLStatisticsHelper::linearRegression($x, $y);

        $this->assertTrue($result['valid']);
        $this->assertGreaterThan(0, $result['slope']);
        $this->assertGreaterThan(0.9, $result['r_squared']); // High R² with slight noise
        $this->assertGreaterThan(0, $result['std_error']);
    }

    public function testLinearRegressionTooFewPoints(): void
    {
        $result = MLStatisticsHelper::linearRegression([1], [5]);

        $this->assertFalse($result['valid']);
        $this->assertSame(0, $result['slope']);
    }

    public function testLinearRegressionMismatchedLengths(): void
    {
        $result = MLStatisticsHelper::linearRegression([1, 2, 3], [1, 2]);

        $this->assertFalse($result['valid']);
    }

    public function testLinearRegressionConstantX(): void
    {
        // All X the same → denominator = 0
        $result = MLStatisticsHelper::linearRegression([5, 5, 5], [1, 2, 3]);

        $this->assertFalse($result['valid']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // EXPONENTIAL SMOOTHING
    // ═══════════════════════════════════════════════════════════════════════

    public function testExponentialSmoothingBasic(): void
    {
        $data = [10, 12, 14, 16, 18, 20];
        $result = MLStatisticsHelper::exponentialSmoothing($data, 0.3, 5);

        $this->assertCount(5, $result['forecast']);
        $this->assertIsFloat($result['level']);
        $this->assertIsFloat($result['mape']);
        $this->assertArrayHasKey('smoothed_series', $result);
    }

    public function testExponentialSmoothingEmptyData(): void
    {
        $result = MLStatisticsHelper::exponentialSmoothing([], 0.3, 5);

        $this->assertEmpty($result['forecast']);
        $this->assertSame(100, $result['mape']);
    }

    public function testExponentialSmoothingFlatForecast(): void
    {
        // SES always produces flat forecast (same value repeated)
        $data = [10, 20, 30, 40, 50];
        $result = MLStatisticsHelper::exponentialSmoothing($data, 0.3, 3);

        $forecast = $result['forecast'];
        $this->assertSame($forecast[0], $forecast[1]);
        $this->assertSame($forecast[1], $forecast[2]);
    }

    public function testExponentialSmoothingAssociativeData(): void
    {
        $data = [
            ['value' => 10],
            ['value' => 20],
            ['value' => 30],
        ];

        $result = MLStatisticsHelper::exponentialSmoothing($data, 0.5, 2);

        $this->assertCount(2, $result['forecast']);
        $this->assertGreaterThan(0, $result['level']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HOLT LINEAR TREND
    // ═══════════════════════════════════════════════════════════════════════

    public function testHoltLinearTrendUpward(): void
    {
        $data = [10, 15, 20, 25, 30, 35, 40];
        $result = MLStatisticsHelper::holtLinearTrend($data, 0.3, 0.1, 5);

        $this->assertCount(5, $result['forecast']);
        $this->assertSame('upward', $result['trend_direction']);
        $this->assertGreaterThan(0, $result['trend']);
        // Forecast should be increasing
        $this->assertGreaterThan($result['forecast'][0], $result['forecast'][4]);
    }

    public function testHoltLinearTrendDownward(): void
    {
        $data = [50, 45, 40, 35, 30, 25, 20];
        $result = MLStatisticsHelper::holtLinearTrend($data, 0.3, 0.1, 3);

        $this->assertSame('downward', $result['trend_direction']);
        $this->assertLessThan(0, $result['trend']);
    }

    public function testHoltLinearTrendSinglePoint(): void
    {
        $result = MLStatisticsHelper::holtLinearTrend([42], 0.3, 0.1, 3);

        $this->assertCount(3, $result['forecast']);
        $this->assertSame(0, $result['trend']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HOLT-WINTERS SEASONAL
    // ═══════════════════════════════════════════════════════════════════════

    public function testHoltWintersSeasonalWithEnoughData(): void
    {
        // 3 weeks of data (21 points for period=7)
        $data = [];
        for ($i = 0; $i < 21; $i++) {
            $data[] = 100 + sin($i * 2 * M_PI / 7) * 20 + $i * 0.5;
        }

        $result = MLStatisticsHelper::holtWintersSeasonal($data, 7, 0.3, 0.1, 0.1, 7);

        $this->assertCount(7, $result['forecast']);
        $this->assertArrayHasKey('seasonal_factors', $result);
        $this->assertCount(7, $result['seasonal_factors']);
        $this->assertArrayHasKey('seasonality_strength', $result);
    }

    public function testHoltWintersFallsBackToHoltForShortData(): void
    {
        // Less than 2 periods → falls back to Holt Linear
        $data = [10, 20, 30, 40, 50];
        $result = MLStatisticsHelper::holtWintersSeasonal($data, 7, 0.3, 0.1, 0.1, 3);

        $this->assertCount(3, $result['forecast']);
        // Should have Holt Linear keys (level, trend)
        $this->assertArrayHasKey('level', $result);
        $this->assertArrayHasKey('trend', $result);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // T-TEST
    // ═══════════════════════════════════════════════════════════════════════

    public function testTTestOneSample(): void
    {
        // Large values → significantly different from 0
        $result = MLStatisticsHelper::tTest([100, 110, 105, 108, 102]);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['significant']);
        $this->assertGreaterThan(0, $result['t_statistic']);
        $this->assertLessThan(0.05, $result['p_value']);
    }

    public function testTTestTwoSamples(): void
    {
        // Very different groups
        $sample1 = [10, 12, 11, 13, 14];
        $sample2 = [50, 55, 52, 48, 53];

        $result = MLStatisticsHelper::tTest($sample1, $sample2);

        $this->assertTrue($result['valid']);
        $this->assertTrue($result['significant']);
        $this->assertArrayHasKey('degrees_of_freedom', $result);
    }

    public function testTTestSimilarSamples(): void
    {
        // Same values → not significant
        $sample1 = [10, 11, 10, 11, 10];
        $sample2 = [10, 11, 10, 11, 10];

        $result = MLStatisticsHelper::tTest($sample1, $sample2);

        $this->assertTrue($result['valid']);
        $this->assertFalse($result['significant']);
    }

    public function testTTestTooFewSamples(): void
    {
        $result = MLStatisticsHelper::tTest([5]);

        $this->assertFalse($result['valid']);
        $this->assertSame(1, $result['p_value']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // VARIANCE & STANDARD DEVIATION
    // ═══════════════════════════════════════════════════════════════════════

    public function testVarianceOfConstants(): void
    {
        // All same values → variance = 0
        $this->assertSame(0.0, MLStatisticsHelper::variance([5, 5, 5, 5]));
    }

    public function testVarianceCalculation(): void
    {
        // Known variance: [2,4,4,4,5,5,7,9] → variance ≈ 4.571
        $data = [2, 4, 4, 4, 5, 5, 7, 9];
        $variance = MLStatisticsHelper::variance($data);

        $this->assertEqualsWithDelta(4.571, $variance, 0.01);
    }

    public function testVarianceSingleElement(): void
    {
        $this->assertSame(0.0, MLStatisticsHelper::variance([42]));
    }

    public function testStandardDeviation(): void
    {
        $data = [2, 4, 4, 4, 5, 5, 7, 9];
        $stdDev = MLStatisticsHelper::standardDeviation($data);

        $this->assertEqualsWithDelta(sqrt(4.571), $stdDev, 0.01);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CONFIDENCE INTERVAL
    // ═══════════════════════════════════════════════════════════════════════

    public function testConfidenceInterval95(): void
    {
        $data = [10, 12, 14, 16, 18];
        $ci = MLStatisticsHelper::confidenceInterval($data, 0.95);

        $this->assertArrayHasKey('lower', $ci);
        $this->assertArrayHasKey('upper', $ci);
        $this->assertArrayHasKey('mean', $ci);
        $this->assertArrayHasKey('margin', $ci);

        $this->assertEqualsWithDelta(14.0, $ci['mean'], 0.01);
        $this->assertLessThan($ci['mean'], $ci['lower']);
        $this->assertGreaterThan($ci['mean'], $ci['upper']);
        $this->assertGreaterThan(0, $ci['margin']);
    }

    public function testConfidenceIntervalSingleElement(): void
    {
        $ci = MLStatisticsHelper::confidenceInterval([42]);

        $this->assertEquals(42, $ci['mean']);
        $this->assertEquals(0, $ci['margin']);
    }

    public function testConfidenceInterval99WiderThan95(): void
    {
        $data = [10, 12, 14, 16, 18, 20, 22];
        $ci90 = MLStatisticsHelper::confidenceInterval($data, 0.90);
        $ci95 = MLStatisticsHelper::confidenceInterval($data, 0.95);

        // 95% CI should be wider than 90% CI
        $this->assertGreaterThan($ci90['margin'], $ci95['margin']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CORRELATION
    // ═══════════════════════════════════════════════════════════════════════

    public function testCorrelationPerfectPositive(): void
    {
        $x = [1, 2, 3, 4, 5];
        $y = [2, 4, 6, 8, 10];

        $this->assertEqualsWithDelta(1.0, MLStatisticsHelper::correlation($x, $y), 0.001);
    }

    public function testCorrelationPerfectNegative(): void
    {
        $x = [1, 2, 3, 4, 5];
        $y = [10, 8, 6, 4, 2];

        $this->assertEqualsWithDelta(-1.0, MLStatisticsHelper::correlation($x, $y), 0.001);
    }

    public function testCorrelationNoRelation(): void
    {
        // No clear linear relationship
        $x = [1, 2, 3, 4, 5, 6, 7, 8];
        $y = [5, 1, 8, 2, 7, 3, 6, 4];

        $correlation = MLStatisticsHelper::correlation($x, $y);

        $this->assertLessThan(0.5, abs($correlation));
    }

    public function testCorrelationTooFewPoints(): void
    {
        $this->assertSame(0.0, MLStatisticsHelper::correlation([1], [2]));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // OUTLIER DETECTION
    // ═══════════════════════════════════════════════════════════════════════

    public function testDetectOutliersFindsExtreme(): void
    {
        $data = [10, 11, 12, 10, 11, 100, 10, 12, 11];
        $result = MLStatisticsHelper::detectOutliers($data, 2.0);

        $this->assertNotEmpty($result['outliers']);
        $outlierValues = array_column($result['outliers'], 'value');
        $this->assertContains(100, $outlierValues);
    }

    public function testDetectOutliersNoneInUniform(): void
    {
        $data = [10, 11, 10, 11, 10, 11, 10, 11];
        $result = MLStatisticsHelper::detectOutliers($data);

        $this->assertEmpty($result['outliers']);
    }

    public function testDetectOutliersTooFewPoints(): void
    {
        $result = MLStatisticsHelper::detectOutliers([1, 2]);

        $this->assertEmpty($result['outliers']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CHANGE POINT DETECTION
    // ═══════════════════════════════════════════════════════════════════════

    public function testDetectChangePointsFindsShift(): void
    {
        // Clear mean shift with slight variation so t-test works
        $data = array_merge(
            [10, 11, 10, 12, 10, 11, 10, 10, 11, 10],  // ~10
            [50, 51, 50, 52, 50, 51, 50, 50, 51, 50]   // ~50
        );

        $result = MLStatisticsHelper::detectChangePoints($data, 5);

        $this->assertNotEmpty($result['change_points']);
    }

    public function testDetectChangePointsNoneInFlat(): void
    {
        $data = array_fill(0, 20, 10);
        $result = MLStatisticsHelper::detectChangePoints($data, 5);

        $this->assertEmpty($result['change_points']);
    }

    public function testDetectChangePointsTooShort(): void
    {
        $result = MLStatisticsHelper::detectChangePoints([1, 2, 3], 5);

        $this->assertEmpty($result['change_points']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SEASONAL DECOMPOSITION
    // ═══════════════════════════════════════════════════════════════════════

    public function testSeasonalDecompositionStructure(): void
    {
        // Generate 3 weeks of data with weekly pattern
        $data = [];
        for ($i = 0; $i < 21; $i++) {
            $data[] = 100 + ($i % 7 === 5 ? 20 : 0); // Weekend spike
        }

        $result = MLStatisticsHelper::seasonalDecomposition($data, 7);

        $this->assertArrayHasKey('trend', $result);
        $this->assertArrayHasKey('seasonal', $result);
        $this->assertArrayHasKey('residual', $result);
        $this->assertArrayHasKey('seasonal_factors', $result);
        $this->assertArrayHasKey('seasonal_strength', $result);
        $this->assertCount(21, $result['trend']);
        $this->assertCount(7, $result['seasonal_factors']);
    }

    public function testSeasonalDecompositionShortData(): void
    {
        $data = [10, 20, 30];
        $result = MLStatisticsHelper::seasonalDecomposition($data, 7);

        // Should return input as trend with no seasonality
        $this->assertEquals(0, $result['seasonal_strength']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MOVING AVERAGE
    // ═══════════════════════════════════════════════════════════════════════

    public function testMovingAverageSmooths(): void
    {
        $data = [10, 20, 30, 20, 10, 20, 30];
        $result = MLStatisticsHelper::movingAverage($data, 3);

        $this->assertCount(7, $result);
        // Middle element should be average of neighbors
        $this->assertEqualsWithDelta(20.0, $result[3], 0.01);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ENSEMBLE FORECAST
    // ═══════════════════════════════════════════════════════════════════════

    public function testEnsembleForecastCombinesModels(): void
    {
        $data = [];
        for ($i = 0; $i < 30; $i++) {
            $data[] = 100 + $i * 2 + sin($i * 2 * M_PI / 7) * 10;
        }

        $result = MLStatisticsHelper::ensembleForecast($data, 5);

        $this->assertCount(5, $result['forecast']);
        $this->assertArrayHasKey('models', $result);
        $this->assertArrayHasKey('simple_exponential_smoothing', $result['models']);
        $this->assertArrayHasKey('holt_linear_trend', $result['models']);
        $this->assertArrayHasKey('holt_winters_seasonal', $result['models']);

        // Weights should sum to ~1
        $totalWeight = $result['models']['simple_exponential_smoothing']['weight']
                     + $result['models']['holt_linear_trend']['weight']
                     + $result['models']['holt_winters_seasonal']['weight'];
        $this->assertEqualsWithDelta(1.0, $totalWeight, 0.01);
    }

    public function testEnsembleForecastTrend(): void
    {
        // Upward trending data
        $data = range(10, 40);
        $result = MLStatisticsHelper::ensembleForecast($data, 3);

        $this->assertSame('upward', $result['trend']);
    }
}
