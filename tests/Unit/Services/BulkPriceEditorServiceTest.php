<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BulkPriceEditorService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\BulkPriceEditorService
 */
final class BulkPriceEditorServiceTest extends TestCase
{
    private BulkPriceEditorService $service;
    private \ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ref = new \ReflectionClass(BulkPriceEditorService::class);
        $this->service = $this->ref->newInstanceWithoutConstructor();
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invoke(string $method, array $args = []): mixed
    {
        $refMethod = $this->ref->getMethod($method);
        $refMethod->setAccessible(true);
        return $refMethod->invokeArgs($this->service, $args);
    }

    public function testServiceCanBeInstantiatedViaReflection(): void
    {
        $this->assertInstanceOf(BulkPriceEditorService::class, $this->service);
    }

    public function testRoundPriceNearest(): void
    {
        $this->assertSame(100.0, $this->invoke('roundPrice', [99.5, 'nearest']));
        $this->assertSame(99.0, $this->invoke('roundPrice', [99.4, 'nearest']));
        $this->assertSame(100.0, $this->invoke('roundPrice', [100.0, 'nearest']));
    }

    public function testRoundPriceUp(): void
    {
        $this->assertSame(100.0, $this->invoke('roundPrice', [99.1, 'up']));
        $this->assertSame(100.0, $this->invoke('roundPrice', [99.9, 'up']));
        $this->assertSame(100.0, $this->invoke('roundPrice', [100.0, 'up']));
    }

    public function testRoundPriceDown(): void
    {
        $this->assertSame(99.0, $this->invoke('roundPrice', [99.9, 'down']));
        $this->assertSame(99.0, $this->invoke('roundPrice', [99.1, 'down']));
        $this->assertSame(100.0, $this->invoke('roundPrice', [100.0, 'down']));
    }

    public function testRoundPriceTo090(): void
    {
        $rounded1 = $this->invoke('roundPrice', [99.5, '0.90']);
        $this->assertSame(99.90, $rounded1);

        $rounded2 = $this->invoke('roundPrice', [100.3, '0.90']);
        $this->assertSame(100.90, $rounded2);
    }

    public function testRoundPriceTo099(): void
    {
        $rounded1 = $this->invoke('roundPrice', [99.5, '0.99']);
        $this->assertSame(99.99, $rounded1);

        $rounded2 = $this->invoke('roundPrice', [100.3, '0.99']);
        $this->assertSame(100.99, $rounded2);
    }

    public function testRoundPriceTo5(): void
    {
        $this->assertSame(100.0, $this->invoke('roundPrice', [99.0, '5']));
        $this->assertSame(100.0, $this->invoke('roundPrice', [102.0, '5']));
        $this->assertSame(105.0, $this->invoke('roundPrice', [103.0, '5']));
    }

    public function testRoundPriceTo10(): void
    {
        $this->assertSame(100.0, $this->invoke('roundPrice', [99.0, '10']));
        $this->assertSame(100.0, $this->invoke('roundPrice', [104.0, '10']));
        $this->assertSame(110.0, $this->invoke('roundPrice', [105.0, '10']));
    }

    public function testRoundPriceDefaultRoundsToTwoDecimals(): void
    {
        $this->assertSame(99.99, $this->invoke('roundPrice', [99.991, 'unknown_mode']));
        $this->assertSame(100.12, $this->invoke('roundPrice', [100.123, 'whatever']));
    }

    public function testCalculatePriceForMarginReturnsExpectedPriceWhenMarginIsPossible(): void
    {
        $item = [
            'current_price' => 100.0,
            'product_cost' => 50.0,
            'ml_commission' => 10.0,
            'tax_rate' => 0.0,
            'shipping_cost' => 10.0,
            'packaging_cost' => 0.0,
        ];

        $price = $this->invoke('calculatePriceForMargin', [$item, 20.0]);
        // totalCost=60, denom=1-0.10-0-0.20=0.70 => 85.714285...
        $this->assertEqualsWithDelta(85.7142857, (float) $price, 0.0001);
    }

    public function testCalculatePriceForMarginFallsBackToCurrentPriceWhenDenominatorIsZeroOrNegative(): void
    {
        $item = [
            'current_price' => 123.45,
            'product_cost' => 10.0,
            'ml_commission' => 60.0,
            'tax_rate' => 30.0,
            'shipping_cost' => 0.0,
            'packaging_cost' => 0.0,
        ];

        $price = $this->invoke('calculatePriceForMargin', [$item, 20.0]);
        $this->assertSame(123.45, (float) $price);
    }

    public function testCalculateNewMarginReturnsNullWhenCostIsZeroOrMissing(): void
    {
        $item = ['product_cost' => 0.0];
        $margin = $this->invoke('calculateNewMargin', [$item, 100.0]);
        $this->assertNull($margin);
    }

    public function testCalculateNewMarginReturnsExpectedMargin(): void
    {
        $item = [
            'product_cost' => 50.0,
            'ml_commission' => 10.0,
            'tax_rate' => 0.0,
            'shipping_cost' => 0.0,
            'packaging_cost' => 0.0,
        ];

        $margin = $this->invoke('calculateNewMargin', [$item, 100.0]);
        $this->assertSame(40.0, (float) $margin);
    }

    public function testValidatePriceChangeFailsWhenBelowMinPrice(): void
    {
        $result = $this->invoke('validatePriceChange', [100.0, 0.50, []]);
        $this->assertIsArray($result);
        $this->assertFalse((bool) ($result['valid'] ?? true));
        $this->assertIsString($result['error'] ?? null);
        $this->assertStringContainsString('Preço abaixo do mínimo', (string) $result['error']);
    }

    public function testValidatePriceChangeFailsWhenChangeExceedsMaxPercent(): void
    {
        $result = $this->invoke('validatePriceChange', [100.0, 200.0, ['product_cost' => 10.0]]);
        $this->assertFalse((bool) ($result['valid'] ?? true));
        $this->assertStringContainsString('Mudança excede limite', (string) ($result['error'] ?? ''));
    }

    public function testValidatePriceChangeWarnsWhenMarginIsNegative(): void
    {
        $item = [
            'product_cost' => 200.0,
            'ml_commission' => 16.0,
            'tax_rate' => 9.0,
            'shipping_cost' => 0.0,
            'packaging_cost' => 0.0,
        ];

        $result = $this->invoke('validatePriceChange', [100.0, 100.0, $item]);
        $this->assertTrue((bool) ($result['valid'] ?? false));
        $this->assertStringContainsString('margem será negativa', (string) ($result['warning'] ?? ''));
    }

    public function testValidatePriceChangeWarnsWhenChangeIsSignificant(): void
    {
        $item = [
            'product_cost' => 10.0,
            'ml_commission' => 0.0,
            'tax_rate' => 0.0,
            'shipping_cost' => 0.0,
            'packaging_cost' => 0.0,
        ];

        $result = $this->invoke('validatePriceChange', [100.0, 121.0, $item]);
        $this->assertTrue((bool) ($result['valid'] ?? false));
        $this->assertStringContainsString('Mudança significativa', (string) ($result['warning'] ?? ''));
    }

    public function testCalculateNewPricePercentIncrease(): void
    {
        $item = ['current_price' => 100.0];
        $op = ['type' => BulkPriceEditorService::OP_PERCENT_INCREASE, 'value' => 10];
        $newPrice = $this->invoke('calculateNewPrice', [$item, $op]);
        $this->assertSame(110.0, (float) $newPrice);
    }

    public function testCalculateNewPricePercentDecrease(): void
    {
        $item = ['current_price' => 100.0];
        $op = ['type' => BulkPriceEditorService::OP_PERCENT_DECREASE, 'value' => 10];
        $newPrice = $this->invoke('calculateNewPrice', [$item, $op]);
        $this->assertSame(90.0, (float) $newPrice);
    }

    public function testCalculateNewPriceFixedIncrease(): void
    {
        $item = ['current_price' => 100.0];
        $op = ['type' => BulkPriceEditorService::OP_FIXED_INCREASE, 'value' => 5];
        $newPrice = $this->invoke('calculateNewPrice', [$item, $op]);
        $this->assertSame(105.0, (float) $newPrice);
    }

    public function testCalculateNewPriceFixedDecrease(): void
    {
        $item = ['current_price' => 100.0];
        $op = ['type' => BulkPriceEditorService::OP_FIXED_DECREASE, 'value' => 5];
        $newPrice = $this->invoke('calculateNewPrice', [$item, $op]);
        $this->assertSame(95.0, (float) $newPrice);
    }

    public function testCalculateNewPriceSetPriceEnforcesMinPrice(): void
    {
        $item = ['current_price' => 100.0];
        $op = ['type' => BulkPriceEditorService::OP_SET_PRICE, 'value' => 0.5];
        $newPrice = $this->invoke('calculateNewPrice', [$item, $op]);
        $this->assertSame(1.0, (float) $newPrice);
    }

    public function testCalculateNewPriceAppliesOptionalRoundingAfterOperation(): void
    {
        $item = ['current_price' => 100.0];
        $op = ['type' => BulkPriceEditorService::OP_PERCENT_INCREASE, 'value' => 10, 'round_to' => '0.99'];
        $newPrice = $this->invoke('calculateNewPrice', [$item, $op]);
        $this->assertSame(110.99, (float) $newPrice);
    }

    public function testGetOperationTemplatesReturnsExpectedTemplates(): void
    {
        $templates = (new BulkPriceEditorServiceTest_Proxy())->getOperationTemplates();
        $this->assertCount(8, $templates);

        foreach ($templates as $tpl) {
            $this->assertArrayHasKey('name', $tpl);
            $this->assertArrayHasKey('type', $tpl);
            $this->assertArrayHasKey('description', $tpl);
            $this->assertArrayHasKey('value_label', $tpl);
            $this->assertArrayHasKey('example', $tpl);
        }
    }
}

/**
 * Minimal proxy that bypasses the real constructor (which hits DB/API).
 *
 * This is intentionally scoped to this test file.
 */
final class BulkPriceEditorServiceTest_Proxy extends BulkPriceEditorService
{
    public function __construct()
    {
        // Bypass parent constructor.
    }
}
