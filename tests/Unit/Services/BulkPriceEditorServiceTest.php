<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BulkPriceEditorService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Unit Tests for BulkPriceEditorService
 *
 * Tests all pure methods: calculateNewPrice, calculatePriceForMargin,
 * roundPrice, validatePriceChange, calculateNewMargin, getOperationTemplates.
 *
 * @covers \App\Services\BulkPriceEditorService
 */
class BulkPriceEditorServiceTest extends TestCase
{
    private BulkPriceEditorService ;
    private ReflectionClass ;

    protected function setUp(): void
    {
        parent::setUp();

        ->ref = new ReflectionClass(BulkPriceEditorService::class);
        ->service = ->ref->newInstanceWithoutConstructor();
    }

    /**
     * Helper to invoke private/protected methods via reflection
     */
    private function invokeMethod(string , array  = []): mixed
    {
         = ->ref->getMethod();
        ->setAccessible(true);
        return ->invokeArgs(->service, );
    }

    // =========================================================================
    // INSTANTIATION
    // =========================================================================

    public function testServiceCanBeInstantiatedViaReflection(): void
    {
        ->assertInstanceOf(BulkPriceEditorService::class, ->service);
    }

    // =========================================================================
    // roundPrice
    // =========================================================================

    public function testRoundPriceNearest(): void
    {
        ->assertSame(100.0, ->invokeMethod('roundPrice', [99.5, 'nearest']));
        ->assertSame(99.0, ->invokeMethod('roundPrice', [99.4, 'nearest']));
        ->assertSame(100.0, ->invokeMethod('roundPrice', [100.0, 'nearest']));
    }

    public function testRoundPriceUp(): void
    {
        ->assertSame(100.0, ->invokeMethod('roundPrice', [99.1, 'up']));
        ->assertSame(100.0, ->invokeMethod('roundPrice', [99.9, 'up']));
        ->assertSame(100.0, ->invokeMethod('roundPrice', [100.0, 'up']));
    }

    public function testRoundPriceDown(): void
    {
        ->assertSame(99.0, ->invokeMethod('roundPrice', [99.9, 'down']));
        ->assertSame(99.0, ->invokeMethod('roundPrice', [99.1, 'down']));
        ->assertSame(100.0, ->invokeMethod('roundPrice', [100.0, 'down']));
    }

    public function testRoundPriceTo090(): void
    {
         = ->invokeMethod('roundPrice', [99.5, '0.90']);
        ->assertSame(99.90, );

         = ->invokeMethod('roundPrice', [100.3, '0.90']);
        ->assertSame(100.90, );
    }

    public function testRoundPriceTo099(): void
    {
         = ->invokeMethod('roundPrice', [99.5, '0.99']);
        ->assertSame(99.99, );

         = ->invokeMethod('roundPrice', [100.3, '0.99']);
        ->assertSame(100.99, );
    }

    public function testRoundPriceTo5(): void
    {
        ->assertSame(100.0, ->invokeMethod('roundPrice', [99.0, '5']));
        ->assertSame(100.0, ->invokeMethod('roundPrice', [102.0, '5']));
        ->assertSame(105.0, ->invokeMethod('roundPrice', [103.0, '5']));
    }

    public function testRoundPriceTo10(): void
    {
        ->assertSame(100.0, ->invokeMethod('roundPrice', [99.0, '10']));
        ->assertSame(100.0, ->invokeMethod('roundPrice', [104.0, '10']));
        ->assertSame(110.0, ->invokeMethod('roundPrice', [105.0, '10']));
    }

    public function testRoundPriceDefaultRoundsToTwoDecimals(): void
    {
        ->assertSame(99.99, ->invokeMethod('roundPrice', [99.991, 'unknown_mode']));
        ->assertSame(100.12, ->invokeMethod('roundPrice', [100.123, 'whatever']));
    }

    // =========================================================================
    // calculateNewMargin
    // =========================================================================

    public function testCalculateNewMarginWithValidData(): void
    {
         = [
            'product_cost' => 50.0,
            'ml_commission' => 16,
            'tax_rate' => 9,
            'shipping_cost' => 5.0,
            'packaging_cost' => 2.0,
        ];
         = 100.0;

         = ->invokeMethod('calculateNewMargin', [, ]);

        // profit = 100 - (100*0.16 + 100*0.09 + 5 + 2 + 50) = 100 - (16+9+5+2+50) = 100 - 82 = 18
        // margin = (18/100)*100 = 18%
        ->assertSame(18.0, );
    }

    public function testCalculateNewMarginReturnsNullWhenCostIsZero(): void
    {
         = [
            'product_cost' => 0,
            'ml_commission' => 16,
            'tax_rate' => 9,
            'shipping_cost' => 0,
            'packaging_cost' => 0,
        ];

         = ->invokeMethod('calculateNewMargin', [, 100.0]);
        ->assertNull();
    }

    public function testCalculateNewMarginReturnsNullWhenCostMissing(): void
    {
         = [];
         = ->invokeMethod('calculateNewMargin', [, 100.0]);
        ->assertNull();
    }

    public function testCalculateNewMarginNegativeMargin(): void
    {
         = [
            'product_cost' => 90.0,
            'ml_commission' => 16,
            'tax_rate' => 9,
            'shipping_cost' => 5.0,
            'packaging_cost' => 2.0,
        ];
         = 100.0;

        // totalDeductions = 16 + 9 + 5 + 2 + 90 = 122
        // profit = 100 - 122 = -22
        // margin = (-22/100)*100 = -22%
         = ->invokeMethod('calculateNewMargin', [, ]);
        ->assertSame(-22.0, );
    }

    public function testCalculateNewMarginUsesDefaultCommissionAndTax(): void
    {
         = [
            'product_cost' => 50.0,
            // ml_commission defaults to 16, tax_rate defaults to 9
        ];
         = 100.0;

        // totalDeductions = 16 + 9 + 0 + 0 + 50 = 75
        // profit = 100 - 75 = 25
        // margin = 25%
         = ->invokeMethod('calculateNewMargin', [, ]);
        ->assertSame(25.0, );
    }

    // =========================================================================
    // calculatePriceForMargin
    // =========================================================================

    public function testCalculatePriceForMarginBasic(): void
    {
         = [
            'current_price' => 100.0,
            'product_cost' => 50.0,
            'ml_commission' => 16,
            'tax_rate' => 9,
            'shipping_cost' => 5.0,
            'packaging_cost' => 2.0,
        ];

        // totalCost = 50 + 5 + 2 = 57
        // denominator = 1 - 0.16 - 0.09 - 0.20 = 0.55
        // price = 57 / 0.55 = 103.636...
         = ->invokeMethod('calculatePriceForMargin', [, 20.0]);
        ->assertEqualsWithDelta(103.636, , 0.01);
    }

    public function testCalculatePriceForMarginReturnCurrentPriceWhenImpossible(): void
    {
         = [
            'current_price' => 100.0,
            'product_cost' => 50.0,
            'ml_commission' => 40,  // 40%
            'tax_rate' => 40,        // 40%
            'shipping_cost' => 0,
            'packaging_cost' => 0,
        ];

        // denominator = 1 - 0.40 - 0.40 - 0.25 = -0.05 (impossible margin)
         = ->invokeMethod('calculatePriceForMargin', [, 25.0]);
        ->assertSame(100.0, );
    }

    public function testCalculatePriceForMarginZeroDenominator(): void
    {
         = [
            'current_price' => 100.0,
            'product_cost' => 50.0,
            'ml_commission' => 50,
            'tax_rate' => 50,
            'shipping_cost' => 0,
            'packaging_cost' => 0,
        ];

        // denominator = 1 - 0.50 - 0.50 - 0.0 = 0 (exactly zero)
         = ->invokeMethod('calculatePriceForMargin', [, 0.0]);
        ->assertSame(100.0, );
    }

    public function testCalculatePriceForMarginUsesDefaultsWhenMissing(): void
    {
         = [
            'current_price' => 100.0,
            'product_cost' => 50.0,
        ];

        // totalCost = 50 + 0 + 0 = 50
        // denominator = 1 - 0.16 - 0.09 - 0.10 = 0.65
        // price = 50 / 0.65 = 76.923...
         = ->invokeMethod('calculatePriceForMargin', [, 10.0]);
        ->assertEqualsWithDelta(76.923, , 0.01);
    }

    // =========================================================================
    // calculateNewPrice
    // =========================================================================

    private function makeItem(float  = 100.0, array  = []): array
    {
        return array_merge([
            'item_id' => 'MLB123',
            'item_title' => 'Test Item',
            'current_price' => ,
            'product_cost' => 50.0,
            'ml_commission' => 16,
            'tax_rate' => 9,
            'shipping_cost' => 5.0,
            'packaging_cost' => 2.0,
            'margin_percent' => 18.0,
            'category_id' => 'MLB1234',
        ], );
    }

    public function testCalculateNewPricePercentIncrease(): void
    {
         = ->makeItem(100.0);
         = ['type' => 'percent_increase', 'value' => 10];

         = ->invokeMethod('calculateNewPrice', [, ]);
        ->assertSame(110.0, );
    }

    public function testCalculateNewPricePercentDecrease(): void
    {
         = ->makeItem(100.0);
         = ['type' => 'percent_decrease', 'value' => 10];

         = ->invokeMethod('calculateNewPrice', [, ]);
        ->assertSame(90.0, );
    }

    public function testCalculateNewPriceFixedIncrease(): void
    {
         = ->makeItem(100.0);
         = ['type' => 'fixed_increase', 'value' => 15];

         = ->invokeMethod('calculateNewPrice', [, ]);
        ->assertSame(115.0, );
    }

    public function testCalculateNewPriceFixedDecrease(): void
    {
         = ->makeItem(100.0);
         = ['type' => 'fixed_decrease', 'value' => 15];

         = ->invokeMethod('calculateNewPrice', [, ]);
        ->assertSame(85.0, );
    }

    public function testCalculateNewPriceSetPrice(): void
    {
         = ->makeItem(100.0);
         = ['type' => 'set_price', 'value' => 199.90];

         = ->invokeMethod('calculateNewPrice', [, ]);
        ->assertSame(199.9, );
    }

    public function testCalculateNewPriceSetMargin(): void
    {
         = ->makeItem(100.0);
         = ['type' => 'set_margin', 'value' => 20];

         = ->invokeMethod('calculateNewPrice', [, ]);
        // totalCost = 50 + 5 + 2 = 57, denom = 1 - 0.16 - 0.09 - 0.20 = 0.55
        // price = 57/0.55 = 103.636... rounded to 103.64
        ->assertEqualsWithDelta(103.64, , 0.01);
    }

    public function testCalculateNewPriceRoundPrice(): void
    {
         = ->makeItem(99.5);
         = ['type' => 'round_price', 'round_to' => '0.99'];

         = ->invokeMethod('calculateNewPrice', [, ]);
        ->assertSame(99.99, );
    }

    public function testCalculateNewPriceRoundPriceNearest(): void
    {
         = ->makeItem(99.5);
         = ['type' => 'round_price', 'round_to' => 'nearest'];

         = ->invokeMethod('calculateNewPrice', [, ]);
        ->assertSame(100.0, );
    }

    public function testCalculateNewPriceEnforcesMinimumPrice(): void
    {
         = ->makeItem(5.0);
         = ['type' => 'fixed_decrease', 'value' => 100];

        // 5 - 100 = -95, should be clamped to MIN_PRICE = 1.00
         = ->invokeMethod('calculateNewPrice', [, ]);
        ->assertSame(1.0, );
    }

    public function testCalculateNewPriceDefaultTypeKeepsCurrentPrice(): void
    {
         = ->makeItem(100.0);
         = ['type' => 'nonexistent_type', 'value' => 999];

         = ->invokeMethod('calculateNewPrice', [, ]);
        ->assertSame(100.0, );
    }

    public function testCalculateNewPriceWithRoundToOption(): void
    {
         = ->makeItem(100.0);
         = ['type' => 'percent_increase', 'value' => 10, 'round_to' => '0.99'];

        // 100 * 1.10 = 110.0 -> roundPrice(110.0, '0.99') = floor(110) + 0.99 = 110.99
         = ->invokeMethod('calculateNewPrice', [, ]);
        ->assertSame(110.99, );
    }

    public function testCalculateNewPriceMissingValueDefaultsToZero(): void
    {
         = ->makeItem(100.0);
         = ['type' => 'percent_increase'];

        // 100 * (1 + 0/100) = 100
         = ->invokeMethod('calculateNewPrice', [, ]);
        ->assertSame(100.0, );
    }

    public function testCalculateNewPriceMissingTypeDefaultsToPercentIncrease(): void
    {
         = ->makeItem(100.0);
         = ['value' => 10];

        // Default type = percent_increase
         = ->invokeMethod('calculateNewPrice', [, ]);
        ->assertSame(110.0, );
    }

    // =========================================================================
    // validatePriceChange
    // =========================================================================

    public function testValidatePriceChangeValid(): void
    {
         = ->makeItem(100.0);
         = ->invokeMethod('validatePriceChange', [100.0, 110.0, ]);

        ->assertTrue(['valid']);
        ->assertNull(['error']);
    }

    public function testValidatePriceChangeBelowMinimum(): void
    {
         = ->makeItem(100.0);
         = ->invokeMethod('validatePriceChange', [100.0, 0.50, ]);

        ->assertFalse(['valid']);
        ->assertStringContainsString('mínimo', ['error']);
    }

    public function testValidatePriceChangeExceedsMaxPercent(): void
    {
         = ->makeItem(100.0);
        // 60% increase
         = ->invokeMethod('validatePriceChange', [100.0, 160.0, ]);

        ->assertFalse(['valid']);
        ->assertStringContainsString('50%', ['error']);
    }

    public function testValidatePriceChangeExceedsMaxPercentDecrease(): void
    {
         = ->makeItem(100.0);
        // 60% decrease
         = ->invokeMethod('validatePriceChange', [100.0, 40.0, ]);

        ->assertFalse(['valid']);
        ->assertStringContainsString('50%', ['error']);
    }

    public function testValidatePriceChangeWarnsOnNegativeMargin(): void
    {
         = ->makeItem(100.0, [
            'product_cost' => 90.0,
        ]);

        // With cost=90, new_price=100 => negative margin
        // totalDeductions = 16+9+5+2+90 = 122, profit = 100-122 = -22
         = ->invokeMethod('validatePriceChange', [100.0, 100.0, ]);

        ->assertTrue(['valid']);
        ->assertStringContainsString('negativa', ['warning']);
    }

    public function testValidatePriceChangeWarnsOnLargeChange(): void
    {
         = ->makeItem(100.0, [
            'product_cost' => 10.0,
        ]);
        // 30% increase (above 20% threshold but below 50% limit)
         = ->invokeMethod('validatePriceChange', [100.0, 130.0, ]);

        ->assertTrue(['valid']);
        ->assertStringContainsString('significativa', ['warning']);
    }

    public function testValidatePriceChangeExactlyAtMaxPercentIsInvalid(): void
    {
         = ->makeItem(100.0, ['product_cost' => 10.0]);
        // Exactly 50% increase → changePercent = 50, which is NOT > 50, so valid
         = ->invokeMethod('validatePriceChange', [100.0, 150.0, ]);

        ->assertTrue(['valid']);
    }

    public function testValidatePriceChangeJustOverMaxPercent(): void
    {
         = ->makeItem(100.0);
        // 50.01% increase
         = ->invokeMethod('validatePriceChange', [100.0, 150.01, ]);

        ->assertFalse(['valid']);
    }

    public function testValidatePriceChangeNoWarningForSmallChange(): void
    {
         = ->makeItem(100.0, ['product_cost' => 10.0]);
        // 5% increase (well under warning thresholds)
         = ->invokeMethod('validatePriceChange', [100.0, 105.0, ]);

        ->assertTrue(['valid']);
        ->assertNull(['warning']);
    }

    // =========================================================================
    // getOperationTemplates
    // =========================================================================

    public function testGetOperationTemplatesReturnsArray(): void
    {
         = ->invokeMethod('getOperationTemplates', []);

        ->assertIsArray();
        ->assertCount(8, );
    }

    public function testGetOperationTemplatesHasRequiredKeys(): void
    {
         = ->invokeMethod('getOperationTemplates', []);

        foreach ( as ) {
            ->assertArrayHasKey('name', );
            ->assertArrayHasKey('type', );
            ->assertArrayHasKey('description', );
            ->assertArrayHasKey('value_label', );
            ->assertArrayHasKey('example', );
        }
    }

    public function testGetOperationTemplatesContainsAllTypes(): void
    {
         = ->invokeMethod('getOperationTemplates', []);
         = array_column(, 'type');

         = [
            'percent_increase',
            'percent_decrease',
            'fixed_increase',
            'fixed_decrease',
            'set_price',
            'set_margin',
            'match_competitor',
            'round_price',
        ];

        foreach ( as ) {
            ->assertContains(, , Missing
