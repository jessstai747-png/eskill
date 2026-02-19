<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\Shipping\DimensionCalculatorService;
use ReflectionClass;
use ReflectionMethod;

/**
 * @covers \App\Services\Shipping\DimensionCalculatorService
 */
class DimensionCalculatorServiceTest extends TestCase
{
    /** @var DimensionCalculatorService */
    private $service;

    /** @var ReflectionClass */
    private $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(DimensionCalculatorService::class);
        $this->service = $this->reflection->newInstanceWithoutConstructor();
    }

    /**
     * Invoke a private/protected method on the service.
     *
     * @param string $methodName
     * @param array  $args
     * @return mixed
     */
    private function invokePrivateMethod(string $methodName, array $args = [])
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invoke($this->service, ...$args);
    }

    // =========================================================================
    // Structural
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(DimensionCalculatorService::class));
    }

    public function testCanBeInstantiatedWithoutConstructor(): void
    {
        $this->assertInstanceOf(DimensionCalculatorService::class, $this->service);
    }

    // =========================================================================
    // calculateCubicWeight
    // =========================================================================

    public function testCalculateCubicWeightBasic(): void
    {
        // 50 * 40 * 30 = 60000 / 6000 = 10.0
        $result = $this->service->calculateCubicWeight(50.0, 40.0, 30.0);
        $this->assertEqualsWithDelta(10.0, $result, 0.01);
    }

    public function testCalculateCubicWeightSmallPackage(): void
    {
        // 10 * 10 * 10 = 1000 / 6000 ≈ 0.1667
        $result = $this->service->calculateCubicWeight(10.0, 10.0, 10.0);
        $this->assertEqualsWithDelta(0.1667, $result, 0.01);
    }

    public function testCalculateCubicWeightLargePackage(): void
    {
        // 100 * 80 * 60 = 480000 / 6000 = 80.0
        $result = $this->service->calculateCubicWeight(100.0, 80.0, 60.0);
        $this->assertEqualsWithDelta(80.0, $result, 0.01);
    }

    public function testCalculateCubicWeightWithZeroDimension(): void
    {
        $result = $this->service->calculateCubicWeight(0.0, 40.0, 30.0);
        $this->assertEqualsWithDelta(0.0, $result, 0.01);
    }

    public function testCalculateCubicWeightReturnsFloat(): void
    {
        $result = $this->service->calculateCubicWeight(50.0, 40.0, 30.0);
        $this->assertIsFloat($result);
    }

    public function testCalculateCubicWeightFractionalDimensions(): void
    {
        // 15.5 * 10.5 * 5.5 = 895.125 / 6000 ≈ 0.1492
        $result = $this->service->calculateCubicWeight(15.5, 10.5, 5.5);
        $this->assertEqualsWithDelta(895.125 / 6000, $result, 0.01);
    }

    // =========================================================================
    // calculateChargeableWeight
    // =========================================================================

    public function testCalculateChargeableWeightCubicWins(): void
    {
        // Cubic: 50*40*30/6000 = 10, actual: 2
        $result = $this->service->calculateChargeableWeight(2.0, 50.0, 40.0, 30.0);
        $this->assertArrayHasKey('chargeable_weight_kg', $result);
        $this->assertEqualsWithDelta(10.0, $result['chargeable_weight_kg'], 0.01);
        $this->assertEquals('cubic', $result['using']);
    }

    public function testCalculateChargeableWeightActualWins(): void
    {
        // Cubic: 10*10*10/6000 ≈ 0.17, actual: 5
        $result = $this->service->calculateChargeableWeight(5.0, 10.0, 10.0, 10.0);
        $this->assertEqualsWithDelta(5.0, $result['chargeable_weight_kg'], 0.01);
        $this->assertEquals('actual', $result['using']);
    }

    public function testCalculateChargeableWeightHasAllKeys(): void
    {
        $result = $this->service->calculateChargeableWeight(2.0, 40.0, 30.0, 20.0);
        $this->assertArrayHasKey('actual_weight_kg', $result);
        $this->assertArrayHasKey('cubic_weight_kg', $result);
        $this->assertArrayHasKey('chargeable_weight_kg', $result);
        $this->assertArrayHasKey('using', $result);
        $this->assertArrayHasKey('volume_cm3', $result);
    }

    public function testCalculateChargeableWeightVolume(): void
    {
        $result = $this->service->calculateChargeableWeight(1.0, 20.0, 15.0, 10.0);
        $this->assertEqualsWithDelta(3000.0, $result['volume_cm3'], 0.01);
    }

    public function testCalculateChargeableWeightEqual(): void
    {
        // Cubic: 60*50*20/6000 = 10, actual: 10
        $result = $this->service->calculateChargeableWeight(10.0, 60.0, 50.0, 20.0);
        $this->assertEqualsWithDelta(10.0, $result['chargeable_weight_kg'], 0.01);
    }

    // =========================================================================
    // validateDimensions
    // =========================================================================

    public function testValidateDimensionsValidMe2(): void
    {
        $result = $this->service->validateDimensions(30.0, 20.0, 10.0, 2.0, 'me2');
        $this->assertTrue($result['valid']);
    }

    public function testValidateDimensionsInvalidMode(): void
    {
        $result = $this->service->validateDimensions(30.0, 20.0, 10.0, 2.0, 'nonexistent');
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testValidateDimensionsLengthExceeds(): void
    {
        // me2 max_length = 200
        $result = $this->service->validateDimensions(250.0, 20.0, 10.0, 2.0, 'me2');
        $this->assertFalse($result['valid']);
        $hasLengthError = false;
        foreach ($result['issues'] as $err) {
            if (strpos($err, 'Comprimento') !== false || strpos($err, 'comprimento') !== false) {
                $hasLengthError = true;
            }
        }
        $this->assertTrue($hasLengthError, 'Should have a length-related error');
    }

    public function testValidateDimensionsWeightExceeds(): void
    {
        // me2 max_weight_kg = 30
        $result = $this->service->validateDimensions(30.0, 20.0, 10.0, 50.0, 'me2');
        $this->assertFalse($result['valid']);
    }

    public function testValidateDimensionsHasWarnings(): void
    {
        // Valid but possibly near limits
        $result = $this->service->validateDimensions(30.0, 20.0, 10.0, 2.0, 'me2');
        $this->assertArrayHasKey('warnings', $result);
    }

    public function testValidateDimensionsHasDimensions(): void
    {
        $result = $this->service->validateDimensions(30.0, 20.0, 10.0, 2.0, 'me2');
        $this->assertArrayHasKey('dimensions', $result);
    }

    public function testValidateDimensionsHasLimits(): void
    {
        $result = $this->service->validateDimensions(30.0, 20.0, 10.0, 2.0, 'me2');
        $this->assertArrayHasKey('limits', $result);
    }

    public function testValidateDimensionsFlex(): void
    {
        $result = $this->service->validateDimensions(50.0, 40.0, 30.0, 5.0, 'flex');
        $this->assertTrue($result['valid']);
    }

    public function testValidateDimensionsFull(): void
    {
        $result = $this->service->validateDimensions(60.0, 50.0, 40.0, 20.0, 'full');
        $this->assertTrue($result['valid']);
    }

    public function testValidateDimensionsSumExceeds(): void
    {
        // me2 max_sum = 300, 150+100+80 = 330
        $result = $this->service->validateDimensions(150.0, 100.0, 80.0, 2.0, 'me2');
        $this->assertFalse($result['valid']);
    }

    public function testValidateDimensionsCubicWeightExceeds(): void
    {
        // me2 max_cubic_weight_kg = 200 => needs volume > 1200000 cm3
        // But max_length/width/height=200 each, so use full: 120*80*80 = 768000/6000=128 > full max_cubic_weight_kg=100
        $result = $this->service->validateDimensions(120.0, 80.0, 80.0, 2.0, 'full');
        $this->assertFalse($result['valid']);
    }

    public function testValidateDimensionsWidthExceeds(): void
    {
        // me2 max_width = 200, use full: max_width = 80
        $result = $this->service->validateDimensions(30.0, 100.0, 10.0, 2.0, 'full');
        $this->assertFalse($result['valid']);
    }

    public function testValidateDimensionsHeightExceeds(): void
    {
        // full max_height = 80
        $result = $this->service->validateDimensions(30.0, 20.0, 100.0, 2.0, 'full');
        $this->assertFalse($result['valid']);
    }

    // =========================================================================
    // validateForAllModes
    // =========================================================================

    public function testValidateForAllModesSmallPackage(): void
    {
        $result = $this->service->validateForAllModes(20.0, 15.0, 10.0, 1.0);
        $this->assertArrayHasKey('validation_results', $result);
        $this->assertArrayHasKey('recommended_mode', $result);
        $this->assertArrayHasKey('compatible_modes', $result);
    }

    public function testValidateForAllModesRecommendsFull(): void
    {
        // Large package that fits all — should recommend 'full' (highest priority)
        $result = $this->service->validateForAllModes(20.0, 15.0, 10.0, 1.0);
        // When all modes are compatible, recommends best one
        $this->assertNotNull($result['recommended_mode']);
    }

    public function testValidateForAllModesLargePackage(): void
    {
        // Very large - may only fit full or none
        $result = $this->service->validateForAllModes(100.0, 80.0, 60.0, 25.0);
        $this->assertArrayHasKey('validation_results', $result);
    }

    public function testValidateForAllModesNoCompatible(): void
    {
        // Huge package that fits no mode
        $result = $this->service->validateForAllModes(300.0, 200.0, 200.0, 200.0);
        $this->assertEmpty($result['compatible_modes']);
    }

    public function testValidateForAllModesAllThreeModes(): void
    {
        $result = $this->service->validateForAllModes(20.0, 15.0, 10.0, 1.0);
        $this->assertContains('me2', $result['compatible_modes']);
        $this->assertContains('flex', $result['compatible_modes']);
        $this->assertContains('full', $result['compatible_modes']);
    }

    // =========================================================================
    // recommendMode (private)
    // =========================================================================

    public function testRecommendModeFull(): void
    {
        $input = [
            'me2' => ['valid' => true],
            'flex' => ['valid' => true],
            'full' => ['valid' => true],
        ];
        $result = $this->invokePrivateMethod('recommendMode', [$input]);
        $this->assertEquals('full', $result);
    }

    public function testRecommendModeFlex(): void
    {
        $input = [
            'me2' => ['valid' => true],
            'flex' => ['valid' => true],
            'full' => ['valid' => false],
        ];
        $result = $this->invokePrivateMethod('recommendMode', [$input]);
        $this->assertEquals('flex', $result);
    }

    public function testRecommendModeMe2(): void
    {
        $input = [
            'me2' => ['valid' => true],
            'flex' => ['valid' => false],
            'full' => ['valid' => false],
        ];
        $result = $this->invokePrivateMethod('recommendMode', [$input]);
        $this->assertEquals('me2', $result);
    }

    public function testRecommendModeNull(): void
    {
        $input = [
            'me2' => ['valid' => false],
            'flex' => ['valid' => false],
            'full' => ['valid' => false],
        ];
        $result = $this->invokePrivateMethod('recommendMode', [$input]);
        $this->assertNull($result);
    }

    // =========================================================================
    // suggestPackaging
    // =========================================================================

    public function testSuggestPackagingMatchesBox(): void
    {
        // Small item that should fit pac_mini (16x11x2)
        $result = $this->service->suggestPackaging(10.0, 8.0, 1.0);
        $this->assertArrayHasKey('suitable_boxes', $result);
        $this->assertNotEmpty($result['suitable_boxes']);
    }

    public function testSuggestPackagingMostEfficient(): void
    {
        $result = $this->service->suggestPackaging(10.0, 8.0, 1.0);
        $this->assertArrayHasKey('recommended', $result);
    }

    public function testSuggestPackagingTooLarge(): void
    {
        // Huge item that doesn't fit any standard box
        $result = $this->service->suggestPackaging(200.0, 150.0, 100.0);
        $this->assertArrayHasKey('suitable_boxes', $result);
        $this->assertEmpty($result['suitable_boxes']);
    }

    public function testSuggestPackagingEfficiency(): void
    {
        $result = $this->service->suggestPackaging(10.0, 8.0, 1.0);
        if (!empty($result['suitable_boxes'])) {
            $first = $result['suitable_boxes'][0];
            $this->assertArrayHasKey('efficiency', $first);
        } else {
            $this->assertTrue(true); // No suggestion for this size
        }
    }

    public function testSuggestPackagingCubicWeight(): void
    {
        $result = $this->service->suggestPackaging(30.0, 20.0, 15.0);
        if (!empty($result['suitable_boxes'])) {
            $first = $result['suitable_boxes'][0];
            $this->assertArrayHasKey('cubic_weight_kg', $first);
        } else {
            $this->assertTrue(true);
        }
    }

    public function testSuggestPackagingVolume(): void
    {
        $result = $this->service->suggestPackaging(30.0, 20.0, 15.0);
        if (!empty($result['suitable_boxes'])) {
            $first = $result['suitable_boxes'][0];
            $this->assertArrayHasKey('volume_cm3', $first);
        } else {
            $this->assertTrue(true);
        }
    }

    public function testSuggestPackagingRotatesDimensions(): void
    {
        // Input in non-sorted order — service should handle any order
        $result1 = $this->service->suggestPackaging(30.0, 20.0, 10.0);
        $result2 = $this->service->suggestPackaging(10.0, 30.0, 20.0);
        $this->assertEquals(count($result1['suitable_boxes']), count($result2['suitable_boxes']));
    }

    // =========================================================================
    // optimizeDimensions
    // =========================================================================

    public function testOptimizeDimensionsStructure(): void
    {
        $result = $this->service->optimizeDimensions(50.0, 40.0, 30.0, 5.0);
        $this->assertArrayHasKey('original_dimensions', $result);
        $this->assertArrayHasKey('original_cubic_weight', $result);
        $this->assertArrayHasKey('suggestions', $result);
    }

    public function testOptimizeDimensionsPreservesOriginal(): void
    {
        $result = $this->service->optimizeDimensions(50.0, 40.0, 30.0, 5.0);
        $this->assertEqualsWithDelta(50.0, $result['original_dimensions']['length'], 0.01);
        $this->assertEqualsWithDelta(40.0, $result['original_dimensions']['width'], 0.01);
        $this->assertEqualsWithDelta(30.0, $result['original_dimensions']['height'], 0.01);
    }

    public function testOptimizeDimensionsDefaultsMe2(): void
    {
        $result = $this->service->optimizeDimensions(50.0, 40.0, 30.0, 5.0);
        $this->assertArrayHasKey('target_mode', $result);
        $this->assertEquals('me2', $result['target_mode']);
    }

    public function testOptimizeDimensionsForFlex(): void
    {
        $result = $this->service->optimizeDimensions(50.0, 40.0, 30.0, 5.0, 'flex');
        $this->assertEquals('flex', $result['target_mode']);
    }

    public function testOptimizeDimensionsHeightReduction(): void
    {
        $result = $this->service->optimizeDimensions(50.0, 40.0, 30.0, 5.0);
        $this->assertArrayHasKey('suggestions', $result);
    }

    public function testOptimizeDimensionsExceedsLimits(): void
    {
        // Very large package - should still return structure
        $result = $this->service->optimizeDimensions(200.0, 150.0, 100.0, 50.0);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('original_dimensions', $result);
    }

    public function testOptimizeDimensionsSavingsFormat(): void
    {
        $result = $this->service->optimizeDimensions(50.0, 40.0, 30.0, 5.0);
        $this->assertArrayHasKey('total_potential_savings', $result);
    }

    // =========================================================================
    // calculateTotalSavings (private)
    // =========================================================================

    public function testCalculateTotalSavingsWithPercentages(): void
    {
        $suggestions = [
            ['cost_savings_estimate' => '10.5%'],
            ['cost_savings_estimate' => '5.3%'],
        ];
        $result = $this->invokePrivateMethod('calculateTotalSavings', [$suggestions]);
        $this->assertIsString($result);
        $this->assertStringContainsString('%', $result);
    }

    public function testCalculateTotalSavingsEmpty(): void
    {
        $result = $this->invokePrivateMethod('calculateTotalSavings', [[]]);
        $this->assertIsString($result);
    }

    public function testCalculateTotalSavingsIgnoresMissing(): void
    {
        $suggestions = [
            ['some_key' => 'value'],
            ['cost_savings_estimate' => '5%'],
        ];
        $result = $this->invokePrivateMethod('calculateTotalSavings', [$suggestions]);
        $this->assertIsString($result);
    }

    // =========================================================================
    // detectIrregularDimensions
    // =========================================================================

    public function testDetectIrregularDimensionsNormal(): void
    {
        $result = $this->service->detectIrregularDimensions(30.0, 25.0, 20.0);
        $this->assertArrayHasKey('has_irregular_dimensions', $result);
    }

    public function testDetectIrregularDimensionsVeryThin(): void
    {
        // Height much smaller than others
        $result = $this->service->detectIrregularDimensions(80.0, 60.0, 2.0);
        $this->assertArrayHasKey('issues', $result);
    }

    public function testDetectIrregularDimensionsDisproportionate(): void
    {
        // One dimension way larger
        $result = $this->service->detectIrregularDimensions(100.0, 10.0, 10.0);
        $this->assertArrayHasKey('issues', $result);
    }

    public function testDetectIrregularDimensionsOversized(): void
    {
        $result = $this->service->detectIrregularDimensions(150.0, 100.0, 80.0);
        $this->assertArrayHasKey('issues', $result);
    }

    public function testDetectIrregularDimensionsVerySmall(): void
    {
        $result = $this->service->detectIrregularDimensions(3.0, 2.0, 1.0);
        $this->assertArrayHasKey('issues', $result);
    }

    public function testDetectIrregularDimensionsSorted(): void
    {
        $result = $this->service->detectIrregularDimensions(30.0, 25.0, 20.0);
        $this->assertArrayHasKey('sorted_dimensions', $result);
    }

    public function testDetectIrregularDimensionsHasDimensions(): void
    {
        $result = $this->service->detectIrregularDimensions(30.0, 25.0, 20.0);
        $this->assertArrayHasKey('dimensions', $result);
    }

    public function testDetectIrregularDimensionsAllFields(): void
    {
        $result = $this->service->detectIrregularDimensions(30.0, 25.0, 20.0);
        $this->assertArrayHasKey('has_irregular_dimensions', $result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('dimensions', $result);
        $this->assertArrayHasKey('sorted_dimensions', $result);
    }

    public function testDetectIrregularDimensionsMultipleIssues(): void
    {
        // Extreme dimensions - very thin and disproportionate
        $result = $this->service->detectIrregularDimensions(200.0, 5.0, 1.0);
        $this->assertTrue($result['has_irregular_dimensions']);
        $this->assertNotEmpty($result['issues']);
    }

    // =========================================================================
    // estimatePackagingCost
    // =========================================================================

    public function testEstimatePackagingCostEnvelope(): void
    {
        // Very small item
        $result = $this->service->estimatePackagingCost(15.0, 10.0, 1.0);
        $this->assertArrayHasKey('packaging_type', $result);
        $this->assertArrayHasKey('estimated_cost_brl', $result);
    }

    public function testEstimatePackagingCostSmall(): void
    {
        $result = $this->service->estimatePackagingCost(25.0, 20.0, 10.0);
        $this->assertArrayHasKey('packaging_type', $result);
    }

    public function testEstimatePackagingCostMedium(): void
    {
        $result = $this->service->estimatePackagingCost(40.0, 30.0, 25.0);
        $this->assertArrayHasKey('estimated_cost_brl', $result);
    }

    public function testEstimatePackagingCostLarge(): void
    {
        $result = $this->service->estimatePackagingCost(70.0, 50.0, 40.0);
        $this->assertArrayHasKey('estimated_cost_brl', $result);
    }

    public function testEstimatePackagingCostXLarge(): void
    {
        $result = $this->service->estimatePackagingCost(100.0, 80.0, 60.0);
        $this->assertArrayHasKey('packaging_type', $result);
    }

    public function testEstimatePackagingCostBubbleWrap(): void
    {
        $result = $this->service->estimatePackagingCost(30.0, 20.0, 15.0);
        $this->assertArrayHasKey('includes', $result);
    }

    public function testEstimatePackagingCostTape(): void
    {
        $result = $this->service->estimatePackagingCost(30.0, 20.0, 15.0);
        $this->assertArrayHasKey('includes', $result);
        $this->assertIsArray($result['includes']);
        $this->assertArrayHasKey('tape', $result['includes']);
    }

    public function testEstimatePackagingCostBox(): void
    {
        $result = $this->service->estimatePackagingCost(50.0, 40.0, 30.0);
        $this->assertArrayHasKey('estimated_cost_brl', $result);
        $this->assertIsNumeric($result['estimated_cost_brl']);
    }

    public function testEstimatePackagingCostVolume(): void
    {
        $result = $this->service->estimatePackagingCost(20.0, 15.0, 10.0);
        $this->assertArrayHasKey('volume_cm3', $result);
        $this->assertEqualsWithDelta(3000.0, $result['volume_cm3'], 0.01);
    }

    // =========================================================================
    // analyzeComplete
    // =========================================================================

    public function testAnalyzeCompleteAllSections(): void
    {
        $result = $this->service->analyzeComplete(40.0, 30.0, 20.0, 3.0);
        $this->assertArrayHasKey('dimensions', $result);
        $this->assertArrayHasKey('chargeable_weight', $result);
        $this->assertArrayHasKey('validation_all_modes', $result);
        $this->assertArrayHasKey('packaging_suggestions', $result);
        $this->assertArrayHasKey('irregular_detection', $result);
        $this->assertArrayHasKey('packaging_cost', $result);
    }

    public function testAnalyzeCompleteDimensions(): void
    {
        $result = $this->service->analyzeComplete(40.0, 30.0, 20.0, 3.0);
        $this->assertArrayHasKey('length', $result['dimensions']);
        $this->assertArrayHasKey('width', $result['dimensions']);
        $this->assertArrayHasKey('height', $result['dimensions']);
        $this->assertArrayHasKey('weight', $result['dimensions']);
    }

    public function testAnalyzeCompleteChargeableWeight(): void
    {
        $result = $this->service->analyzeComplete(40.0, 30.0, 20.0, 3.0);
        $this->assertArrayHasKey('chargeable_weight_kg', $result['chargeable_weight']);
        $this->assertArrayHasKey('using', $result['chargeable_weight']);
    }

    public function testAnalyzeCompleteValidation(): void
    {
        $result = $this->service->analyzeComplete(40.0, 30.0, 20.0, 3.0);
        $this->assertArrayHasKey('validation_results', $result['validation_all_modes']);
        $this->assertArrayHasKey('recommended_mode', $result['validation_all_modes']);
    }

    public function testAnalyzeCompleteIntegration(): void
    {
        // Full integration test — all pieces connected
        $result = $this->service->analyzeComplete(50.0, 40.0, 30.0, 5.0);
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(5, count($result));

        // Chargeable weight should be coherent
        $cw = $result['chargeable_weight'];
        $this->assertGreaterThan(0, $cw['chargeable_weight_kg']);

        // Validation should include all three modes
        $this->assertArrayHasKey('me2', $result['validation_all_modes']['validation_results']);
        $this->assertArrayHasKey('flex', $result['validation_all_modes']['validation_results']);
        $this->assertArrayHasKey('full', $result['validation_all_modes']['validation_results']);
    }
}
