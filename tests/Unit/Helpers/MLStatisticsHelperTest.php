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
    // ========== linearRegression ==========

    public function testLinearRegressionPerfectLine(): void
    {
        // y = 2x + 1
        $x = [1, 2, 3, 4, 5];
        $y = [3, 5, 7, 9, 11];

        $result = MLStatisticsHelper::linearRegression($x, $y);

        $this->assertEqualsWithDelta(2.0, $result['slope'], 0.001);
        $this->assertEqualsWithDelta(1.0, $result['intercept'], 0.001);
        $this->assertEqualsWithDelta(1.0, $result['r_squared'], 0.001);
        $this->assertEqualsWithDelta(0.0, $result['std_error'], 0.001);
        $this->assertTrue($result['valid']);
    }

    public function testLinearRegressionHorizontalLine(): void
    {
        $x = [1, 2, 3, 4, 5];
        $y = [5, 5, 5, 5, 5];

        $result = MLStatisticsHelper::linearRegression($x, $y);

        $this->assertEqualsWithDelta(0.0, $result['slope'], 0.001);
        $this->assertEqualsWithDelta(5.0, $result['intercept'], 0.001);
        $this->assertTrue($result['valid']);
    }

    public function testLinearRegressionSinglePoint(): void
    {
        $result = MLStatisticsHelper::linearRegression([1], [2]);

        $this->assertFalse($result['valid']);
        $this->assertEqualsWithDelta(0.0, $result['slope'], 0.001);
    }

    public function testLinearRegressionEmptyArrays(): void
    {
        $result = MLStatisticsHelper::linearRegression([], []);

        $this->assertFalse($result['valid']);
    }

    public function testLinearRegressionMismatchedSizes(): void
    {
        $result = MLStatisticsHelper::linearRegression([1, 2, 3], [1, 2]);

        $this->assertFalse($result['valid']);
    }

    public function testLinearRegressionAllSameX(): void
    {
        // All x values the same => denominator = 0
        $result = MLStatisticsHelper::linearRegression([3, 3, 3], [1, 2, 3]);

        $this->assertFalse($result['valid']);
        $this->assertEqualsWithDelta(0.0, $result['slope'], 0.001);
        $this->assertEqualsWithDelta(2.0, $result['intercept'], 0.001);
    }

    public function testLinearRegressionNegativeSlope(): void
    {
        // y = -x + 10
        $x = [1, 2, 3, 4, 5];
        $y = [9, 8, 7, 6, 5];

        $result = MLStatisticsHelper::linearRegression($x, $y);

        $this->assertEqualsWithDelta(-1.0, $result['slope'], 0.001);
        $this->assertEqualsWithDelta(10.0, $result['intercept'], 0.001);
        $this->assertTrue($result['valid']);
    }

    // ========== exponentialSmoothing ==========

    public function testExponentialSmoothingConstantSeries(): void
    {
        $data = [10, 10, 10, 10, 10];

        $result = MLStatisticsHelper::exponentialSmoothing($data, 0.3, 5);

        $this->assertEqualsWithDelta(10.0, $result['level'], 0.01);
        $this->assertEqualsWithDelta(0.0, $result['mape'], 0.1);
        $this->assertCount(5, $result['forecast']);
        foreach ($result['forecast'] as $f) {
            $this->assertEqualsWithDelta(10.0, $f, 0.01);
        }
    }

    public function testExponentialSmoothingEmptyData(): void
    {
        $result = MLStatisticsHelper::exponentialSmoothing([], 0.3, 5);

        $this->assertEmpty($result['forecast']);
        $this->assertEqualsWithDelta(0.0, $result['level'], 0.01);
    }

    public function testExponentialSmoothingSingleValue(): void
    {
        $result = MLStatisticsHelper::exponentialSmoothing([42], 0.3, 3);

        $this->assertEqualsWithDelta(42.0, $result['level'], 0.01);
        $this->assertCount(3, $result['forecast']);
    }

    public function testExponentialSmoothingIncreasingSequence(): void
    {
        $data = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        $result = MLStatisticsHelper::exponentialSmoothing($data, 0.3, 5);

        // Level should be pulled toward the last values
        $this->assertGreaterThan(5.0, $result['level']);
        $this->assertCount(5, $result['forecast']);
        $this->assertArrayHasKey('smoothed_series', $result);
        $this->assertCount(10, $result['smoothed_series']);
    }

    public function testExponentialSmoothingHighAlpha(): void
    {
        $data = [1, 10, 1, 10, 1];
        $resultHigh = MLStatisticsHelper::exponentialSmoothing($data, 0.9, 1);
        $resultLow = MLStatisticsHelper::exponentialSmoothing($data, 0.1, 1);

        // High alpha follows data more closely — different levels
        $this->assertNotEquals($resultHigh['level'], $resultLow['level']);
    }

    public function testExponentialSmoothingAssociativeArray(): void
    {
        $data = [
            ['value' => 10],
            ['value' => 20],
            ['value' => 30],
        ];

        $result = MLStatisticsHelper::exponentialSmoothing($data, 0.3, 3);

        $this->assertGreaterThan(0, $result['level']);
        $this->assertCount(3, $result['forecast']);
    }

    // ========== holtLinearTrend ==========

    public function testHoltLinearTrendUpward(): void
    {
        $data = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        $result = MLStatisticsHelper::holtLinearTrend($data, 0.3, 0.1, 5);

        $this->assertGreaterThan(0, $result['trend']);
        $this->assertEquals('upward', $result['trend_direction']);
        $this->assertCount(5, $result['forecast']);
        // Forecast should continue upward
        $this->assertGreaterThan($result['forecast'][0], $result['forecast'][4]);
    }

    public function testHoltLinearTrendDownward(): void
    {
        $data = [10, 9, 8, 7, 6, 5, 4, 3, 2, 1];

        $result = MLStatisticsHelper::holtLinearTrend($data, 0.3, 0.1, 5);

        $this->assertLessThan(0, $result['trend']);
        $this->assertEquals('downward', $result['trend_direction']);
    }

    public function testHoltLinearTrendStable(): void
    {
        $data = [5, 5, 5, 5, 5, 5, 5, 5, 5, 5];

        $result = MLStatisticsHelper::holtLinearTrend($data, 0.3, 0.1, 5);

        $this->assertEquals('stable', $result['trend_direction']);
        $this->assertEqualsWithDelta(0.0, $result['trend'], 0.02);
    }

    public function testHoltLinearTrendSingleDataPoint(): void
    {
        $result = MLStatisticsHelper::holtLinearTrend([42], 0.3, 0.1, 3);

        $this->assertEqualsWithDelta(42.0, $result['level'], 0.01);
        $this->assertEqualsWithDelta(0.0, $result['trend'], 0.01);
        $this->assertCount(3, $result['forecast']);
    }

    public function testHoltLinearTrendForecastLength(): void
    {
        $data = [1, 3, 5, 7, 9];

        $result = MLStatisticsHelper::holtLinearTrend($data, 0.3, 0.1, 10);

        $this->assertCount(10, $result['forecast']);
    }

    // ========== holtWintersSeasonal ==========

    public function testHoltWintersSeasonalWithClearSeasonality(): void
    {
        // Create data with period=7 seasonality: base + sin pattern
        $data = [];
        for ($i = 0; $i < 28; $i++) {
            $data[] = 100 + 20 * sin(2 * M_PI * $i / 7) + $i * 0.5;
        }

        $result = MLStatisticsHelper::holtWintersSeasonal($data, 7, 0.3, 0.1, 0.1, 7);

        $this->assertArrayHasKey('seasonal_factors', $result);
        $this->assertCount(7, $result['seasonal_factors']);
        $this->assertCount(7, $result['forecast']);
        $this->assertGreaterThan(0, $result['seasonality_strength']);
    }

    public function testHoltWintersSeasonalDataTooShort(): void
    {
        // Less than 2*period=14 elements => fallback to holtLinearTrend
        $data = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        $result = MLStatisticsHelper::holtWintersSeasonal($data, 7, 0.3, 0.1, 0.1, 5);

        // Should fallback — has forecast but no seasonal_factors from holtLinearTrend
        $this->assertArrayHasKey('forecast', $result);
        $this->assertCount(5, $result['forecast']);
    }

    public function testHoltWintersSeasonalConstantData(): void
    {
        $data = array_fill(0, 21, 50);

        $result = MLStatisticsHelper::holtWintersSeasonal($data, 7, 0.3, 0.1, 0.1, 7);

        $this->assertEqualsWithDelta(50.0, $result['level'], 1.0);
        $this->assertEquals('stable', $result['trend_direction']);
    }

    public function testHoltWintersSeasonalFactorsAvgNearOne(): void
    {
        $data = [];
        for ($i = 0; $i < 28; $i++) {
            $data[] = 50 + 10 * sin(2 * M_PI * $i / 7);
        }

        $result = MLStatisticsHelper::holtWintersSeasonal($data, 7, 0.3, 0.1, 0.1, 7);

        // For multiplicative model, seasonal factors should average near 1
        if (isset($result['seasonal_factors'])) {
            $avgFactor = array_sum($result['seasonal_factors']) / count($result['seasonal_factors']);
            $this->assertEqualsWithDelta(1.0, $avgFactor, 0.3);
        }
    }

    // ========== tTest ==========

    public function testTTestSignificantlyDifferentSamples(): void
    {
        $sample1 = [10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $sample2 = [50, 51, 52, 53, 54, 55, 56, 57, 58, 59];

        $result = MLStatisticsHelper::tTest($sample1, $sample2);

        $this->assertTrue($result['significant']);
        $this->assertTrue($result['valid']);
        $this->assertLessThan(0.05, $result['p_value']);
    }

    public function testTTestSimilarSamples(): void
    {
        $sample1 = [10, 11, 12, 13, 14];
        $sample2 = [10, 11, 12, 13, 14];

        $result = MLStatisticsHelper::tTest($sample1, $sample2);

        $this->assertFalse($result['significant']);
        $this->assertTrue($result['valid']);
    }

    public function testTTestOneSample(): void
    {
        // Large values => mean far from 0 => significant
        $sample = [100, 101, 102, 103, 104];

        $result = MLStatisticsHelper::tTest($sample);

        $this->assertTrue($result['significant']);
        $this->assertTrue($result['valid']);
    }

    public function testTTestOneSampleNearZero(): void
    {
        // Values near 0 => not significant vs mean=0
        $sample = [-0.1, 0.1, -0.05, 0.05, 0.0];

        $result = MLStatisticsHelper::tTest($sample);

        $this->assertFalse($result['significant']);
    }

    public function testTTestSmallSample(): void
    {
        $result = MLStatisticsHelper::tTest([1]);

        $this->assertFalse($result['significant']);
        $this->assertFalse($result['valid']);
    }

    public function testTTestSingleValueInSample2(): void
    {
        $result = MLStatisticsHelper::tTest([1, 2, 3, 4, 5], [99]);

        $this->assertFalse($result['valid']);
    }

    public function testTTestReturnsDegreesOfFreedom(): void
    {
        $sample1 = [1, 2, 3, 4, 5];
        $sample2 = [6, 7, 8, 9, 10];

        $result = MLStatisticsHelper::tTest($sample1, $sample2);

        $this->assertArrayHasKey('degrees_of_freedom', $result);
        $this->assertGreaterThan(0, $result['degrees_of_freedom']);
    }

    // ========== variance ==========

    public function testVarianceConstantData(): void
    {
        $this->assertEqualsWithDelta(0.0, MLStatisticsHelper::variance([5, 5, 5, 5]), 0.001);
    }

    public function testVarianceKnownValue(): void
    {
        // Variance of [2, 4, 4, 4, 5, 5, 7, 9] = 4.571... (sample variance)
        $data = [2, 4, 4, 4, 5, 5, 7, 9];
        $result = MLStatisticsHelper::variance($data);

        $this->assertEqualsWithDelta(4.5714, $result, 0.01);
    }

    public function testVarianceSingleElement(): void
    {
        $this->assertEqualsWithDelta(0.0, MLStatisticsHelper::variance([42]), 0.001);
    }

    public function testVarianceTwoElements(): void
    {
        // Variance of [0, 10]: mean=5, sum_sq=50, var = 50/1 = 50
        $this->assertEqualsWithDelta(50.0, MLStatisticsHelper::variance([0, 10]), 0.001);
    }

    // ========== standardDeviation ==========

    public function testStandardDeviationKnownValue(): void
    {
        $data = [2, 4, 4, 4, 5, 5, 7, 9];
        $result = MLStatisticsHelper::standardDeviation($data);

        $this->assertEqualsWithDelta(sqrt(4.5714), $result, 0.01);
    }

    public function testStandardDeviationIsSqrtOfVariance(): void
    {
        $data = [1, 3, 5, 7, 9];
        $variance = MLStatisticsHelper::variance($data);
        $stdDev = MLStatisticsHelper::standardDeviation($data);

        $this->assertEqualsWithDelta(sqrt($variance), $stdDev, 0.0001);
    }

    public function testStandardDeviationConstantData(): void
    {
        $this->assertEqualsWithDelta(0.0, MLStatisticsHelper::standardDeviation([3, 3, 3]), 0.001);
    }

    // ========== confidenceInterval ==========

    public function testConfidenceIntervalKnownData(): void
    {
        $data = [10, 20, 30, 40, 50];

        $result = MLStatisticsHelper::confidenceInterval($data, 0.95);

        $this->assertEqualsWithDelta(30.0, $result['mean'], 0.01);
        $this->assertLessThan($result['mean'], $result['lower']);
        $this->assertGreaterThan($result['mean'], $result['upper']);
        $this->assertGreaterThan(0, $result['margin']);
    }

    public function testConfidenceIntervalSingleElement(): void
    {
        $result = MLStatisticsHelper::confidenceInterval([42]);

        $this->assertEqualsWithDelta(42.0, $result['mean'], 0.01);
        $this->assertEqualsWithDelta(0.0, $result['margin'], 0.001);
        $this->assertEqualsWithDelta($result['lower'], $result['upper'], 0.001);
    }

    public function testConfidenceIntervalUpperGteqLower(): void
    {
        $data = [5, 10, 15, 20, 25];

        $result = MLStatisticsHelper::confidenceInterval($data, 0.95);

        $this->assertGreaterThanOrEqual($result['lower'], $result['upper']);
    }

    public function testConfidenceIntervalMeanBetweenBounds(): void
    {
        $data = [2, 4, 6, 8, 10];

        $result = MLStatisticsHelper::confidenceInterval($data, 0.95);

        $this->assertGreaterThanOrEqual($result['lower'], $result['mean']);
        $this->assertLessThanOrEqual($result['upper'], $result['mean']);
    }

    public function testConfidenceInterval90NarrowerThan95(): void
    {
        $data = [10, 20, 30, 40, 50];

        $result90 = MLStatisticsHelper::confidenceInterval($data, 0.90);
        $result95 = MLStatisticsHelper::confidenceInterval($data, 0.95);

        // 90% CI should be narrower than 95% CI
        $this->assertLessThan($result95['margin'], $result90['margin']);
    }

    public function testConfidenceInterval99WiderThan95(): void
    {
        $data = [10, 20, 30, 40, 50];

        $result95 = MLStatisticsHelper::confidenceInterval($data, 0.95);
        $result99 = MLStatisticsHelper::confidenceInterval($data, 0.99);

        // 99% CI should be wider than 95% CI
        $this->assertGreaterThan($result95['margin'], $result99['margin']);
    }

    // ========== correlation ==========

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

    public function testCorrelationNoCorrelation(): void
    {
        // Alternating pattern has near-zero correlation with linear
        $x = [1, 2, 3, 4, 5, 6];
        $y = [1, -1, 1, -1, 1, -1];

        $result = MLStatisticsHelper::correlation($x, $y);
        $this->assertEqualsWithDelta(0.0, $result, 0.3);
    }

    public function testCorrelationSingleElement(): void
    {
        $this->assertEqualsWithDelta(0.0, MLStatisticsHelper::correlation([1], [2]), 0.001);
    }

    public function testCorrelationMismatchedSizesTruncated(): void
    {
        // Should truncate to minimum length
        $x = [1, 2, 3, 4, 5];
        $y = [2, 4, 6];

        $result = MLStatisticsHelper::correlation($x, $y);

        // Correlation of [1,2,3] vs [2,4,6] = 1.0
        $this->assertEqualsWithDelta(1.0, $result, 0.001);
    }

    // ========== detectOutliers ==========

    public function testDetectOutliersWithOutlier(): void
    {
        $data = [10, 11, 10, 11, 10, 11, 10, 200];

        $result = MLStatisticsHelper::detectOutliers($data, 2.0);

        $this->assertNotEmpty($result['outliers']);
        $this->assertContains(7, $result['indices']);
        $this->assertArrayHasKey('mean', $result);
        $this->assertArrayHasKey('std_dev', $result);
    }

    public function testDetectOutliersWithoutOutliers(): void
    {
        $data = [10, 11, 10, 11, 10, 11, 10, 11];

        $result = MLStatisticsHelper::detectOutliers($data, 2.5);

        $this->assertEmpty($result['outliers']);
        $this->assertEmpty($result['indices']);
    }

    public function testDetectOutliersConstantData(): void
    {
        $data = [5, 5, 5, 5, 5];

        $result = MLStatisticsHelper::detectOutliers($data, 2.5);

        // stddev=0 => no outliers possible
        $this->assertEmpty($result['outliers']);
    }

    public function testDetectOutliersSmallData(): void
    {
        $result = MLStatisticsHelper::detectOutliers([1, 2], 2.5);

        $this->assertEmpty($result['outliers']);
        $this->assertEmpty($result['indices']);
    }

    public function testDetectOutliersThresholdInResult(): void
    {
        $data = [1, 2, 3, 4, 5, 100];

        $result = MLStatisticsHelper::detectOutliers($data, 2.0);

        $this->assertEqualsWithDelta(2.0, $result['threshold'], 0.001);
    }

    // ========== detectChangePoints ==========

    public function testDetectChangePointsClearShift(): void
    {
        // Segments need variance for t-test (constant segments => variance=0 => t=0)
        $data = [2, 1, 3, 2, 1, 3, 2, 50, 51, 49, 50, 51, 49, 50];

        $result = MLStatisticsHelper::detectChangePoints($data, 5);

        $this->assertNotEmpty($result['change_points']);
        $firstCp = $result['change_points'][0];
        $this->assertGreaterThanOrEqual(5, $firstCp['index']);
        $this->assertLessThanOrEqual(9, $firstCp['index']);
    }

    public function testDetectChangePointsNoChange(): void
    {
        $data = [5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5];

        $result = MLStatisticsHelper::detectChangePoints($data, 5);

        $this->assertEmpty($result['change_points']);
    }

    public function testDetectChangePointsDataTooShort(): void
    {
        $result = MLStatisticsHelper::detectChangePoints([1, 2, 3], 5);

        $this->assertEmpty($result['change_points']);
    }

    public function testDetectChangePointsReturnsMeans(): void
    {
        $data = [2, 1, 3, 2, 1, 3, 2, 50, 51, 49, 50, 51, 49, 50];

        $result = MLStatisticsHelper::detectChangePoints($data, 5);

        $this->assertNotEmpty($result['change_points']);
        $cp = $result['change_points'][0];
        $this->assertArrayHasKey('before_mean', $cp);
        $this->assertArrayHasKey('after_mean', $cp);
        $this->assertLessThan($cp['after_mean'], $cp['before_mean']);
    }

    // ========== seasonalDecomposition ==========

    public function testSeasonalDecompositionWithSeasonality(): void
    {
        $data = [];
        for ($i = 0; $i < 28; $i++) {
            $data[] = 50 + 15 * sin(2 * M_PI * $i / 7) + $i * 0.2;
        }

        $result = MLStatisticsHelper::seasonalDecomposition($data, 7);

        $this->assertArrayHasKey('trend', $result);
        $this->assertArrayHasKey('seasonal', $result);
        $this->assertArrayHasKey('residual', $result);
        $this->assertArrayHasKey('seasonal_factors', $result);
        $this->assertCount(7, $result['seasonal_factors']);
        $this->assertGreaterThan(0, $result['seasonal_strength']);
    }

    public function testSeasonalDecompositionConstantData(): void
    {
        $data = array_fill(0, 21, 10);

        $result = MLStatisticsHelper::seasonalDecomposition($data, 7);

        $this->assertEqualsWithDelta(0.0, $result['seasonal_strength'], 0.01);
    }

    public function testSeasonalDecompositionDataTooShort(): void
    {
        $data = [1, 2, 3, 4, 5];

        $result = MLStatisticsHelper::seasonalDecomposition($data, 7);

        // Falls back — returns original values as trend
        $this->assertEqualsWithDelta(0.0, $result['seasonal_strength'], 0.001);
        $this->assertCount(5, $result['trend']);
    }

    public function testSeasonalDecompositionFactorsSumNearZero(): void
    {
        $data = [];
        for ($i = 0; $i < 28; $i++) {
            $data[] = 100 + 20 * sin(2 * M_PI * $i / 7);
        }

        $result = MLStatisticsHelper::seasonalDecomposition($data, 7);

        // Additive seasonal factors should sum to ~0
        $sumFactors = array_sum($result['seasonal_factors']);
        $this->assertEqualsWithDelta(0.0, $sumFactors, 0.1);
    }

    public function testSeasonalDecompositionTrendDirection(): void
    {
        $data = [];
        for ($i = 0; $i < 21; $i++) {
            $data[] = 10 + $i * 2;
        }

        $result = MLStatisticsHelper::seasonalDecomposition($data, 7);

        $this->assertEquals('upward', $result['trend_direction']);
    }

    public function testSeasonalDecompositionComponentLengths(): void
    {
        $data = [];
        for ($i = 0; $i < 21; $i++) {
            $data[] = 50 + 10 * sin(2 * M_PI * $i / 7);
        }

        $result = MLStatisticsHelper::seasonalDecomposition($data, 7);

        $this->assertCount(21, $result['trend']);
        $this->assertCount(21, $result['seasonal']);
        $this->assertCount(21, $result['residual']);
    }

    // ========== movingAverage ==========

    public function testMovingAverageConstantData(): void
    {
        $data = [5, 5, 5, 5, 5];

        $result = MLStatisticsHelper::movingAverage($data, 3);

        foreach ($result as $val) {
            $this->assertEqualsWithDelta(5.0, $val, 0.001);
        }
    }

    public function testMovingAverageKnownCalculation(): void
    {
        $data = [1, 2, 3, 4, 5];

        // Window=3, centered: middle element (index 2) uses [2,3,4] avg=3
        $result = MLStatisticsHelper::movingAverage($data, 3);

        $this->assertCount(5, $result);
        $this->assertEqualsWithDelta(3.0, $result[2], 0.001);
    }

    public function testMovingAverageOutputSameLength(): void
    {
        $data = [10, 20, 30, 40, 50, 60];

        $result = MLStatisticsHelper::movingAverage($data, 3);

        $this->assertCount(6, $result);
    }

    public function testMovingAverageWindowOne(): void
    {
        $data = [1, 5, 3, 7, 2];

        // Window=1 (halfWindow=0): each element should be itself
        $result = MLStatisticsHelper::movingAverage($data, 1);

        for ($i = 0; $i < count($data); $i++) {
            $this->assertEqualsWithDelta($data[$i], $result[$i], 0.001);
        }
    }

    public function testMovingAverageSmoothsData(): void
    {
        $data = [1, 10, 1, 10, 1, 10, 1];

        $result = MLStatisticsHelper::movingAverage($data, 3);

        // Smoothed values should have less variance than original
        $origVar = MLStatisticsHelper::variance($data);
        $smoothedVar = MLStatisticsHelper::variance($result);
        $this->assertLessThan($origVar, $smoothedVar);
    }

    // ========== ensembleForecast ==========

    public function testEnsembleForecastUpwardTrend(): void
    {
        $data = [];
        for ($i = 0; $i < 30; $i++) {
            $data[] = 10 + $i * 2;
        }

        $result = MLStatisticsHelper::ensembleForecast($data, 5);

        $this->assertCount(5, $result['forecast']);
        $lastData = end($data);
        $this->assertGreaterThan($lastData * 0.5, $result['forecast'][0]);
        $this->assertEquals('upward', $result['trend']);
    }

    public function testEnsembleForecastConstantData(): void
    {
        $data = array_fill(0, 30, 25);

        $result = MLStatisticsHelper::ensembleForecast($data, 5);

        $this->assertCount(5, $result['forecast']);
        foreach ($result['forecast'] as $f) {
            $this->assertEqualsWithDelta(25.0, $f, 2.0);
        }
    }

    public function testEnsembleForecastCorrectLength(): void
    {
        $data = array_fill(0, 20, 10);

        $result = MLStatisticsHelper::ensembleForecast($data, 15);

        $this->assertCount(15, $result['forecast']);
    }

    public function testEnsembleForecastModelWeightsSumToOne(): void
    {
        $data = [];
        for ($i = 0; $i < 30; $i++) {
            $data[] = 50 + $i;
        }

        $result = MLStatisticsHelper::ensembleForecast($data, 5);

        $totalWeight = 0;
        foreach ($result['models'] as $model) {
            $totalWeight += $model['weight'];
        }
        $this->assertEqualsWithDelta(1.0, $totalWeight, 0.01);
    }

    public function testEnsembleForecastHasThreeModels(): void
    {
        $data = array_fill(0, 30, 10);

        $result = MLStatisticsHelper::ensembleForecast($data, 5);

        $this->assertCount(3, $result['models']);
        $this->assertArrayHasKey('simple_exponential_smoothing', $result['models']);
        $this->assertArrayHasKey('holt_linear_trend', $result['models']);
        $this->assertArrayHasKey('holt_winters_seasonal', $result['models']);
    }

    public function testEnsembleForecastReturnsLevel(): void
    {
        $data = array_fill(0, 20, 42);

        $result = MLStatisticsHelper::ensembleForecast($data, 3);

        $this->assertArrayHasKey('level', $result);
        $this->assertEqualsWithDelta(42.0, $result['level'], 1.0);
    }
}
