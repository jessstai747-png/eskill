<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Services\CatalogCloneService
 */
class CatalogCloneServiceTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\App\Services\CatalogCloneService::class);
    }

    private function invokePrivateMethod(string $methodName, array $args = []): mixed
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);
        $instance = $this->reflection->newInstanceWithoutConstructor();
        return $method->invokeArgs($instance, $args);
    }

    // =========================================================================
    // Structural
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(\App\Services\CatalogCloneService::class));
    }

    public function testHasDeclareStrictTypes(): void
    {
        $file = $this->reflection->getFileName();
        $this->assertNotFalse($file);
        $this->assertStringContainsString('declare(strict_types=1)', file_get_contents($file));
    }

    /**
     * @dataProvider publicMethodsProvider
     */
    public function testPublicMethodExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function publicMethodsProvider(): array
    {
        return [
            ['cloneCatalogItem'],
            ['cloneItem'],
            ['simulateClone'],
            ['getCloneHistory'],
            ['getCloneMetrics'],
            ['searchItemsWithFilters'],
            ['createCloneSchedule'],
            ['getActiveSchedules'],
            ['cancelSchedule'],
            ['processScheduledClones'],
            ['listSellerItems'],
            ['getSellerSummary'],
            ['resolveItemIds'],
            ['pricePreviewBatch'],
            ['checkLocalDuplicate'],
        ];
    }

    /**
     * @dataProvider privateMethodsProvider
     */
    public function testPrivateMethodExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPrivate());
    }

    public static function privateMethodsProvider(): array
    {
        return [
            ['validateCloneParams'],
            ['calculateFinalPrice'],
            ['calculatePrice'],
            ['calculateStock'],
            ['sanitizeTitle'],
            ['preparePictures'],
            ['filterClonableAttributes'],
            ['prepareVariations'],
            ['calculateNextExecution'],
            ['extractBrandFromItem'],
            ['checkNonCatalogDuplicate'],
            ['logAndReturnError'],
            ['logResult'],
        ];
    }

    // =========================================================================
    // Behavioral: calculateStock
    // =========================================================================

    public function testCalculateStockCopy(): void
    {
        $result = $this->invokePrivateMethod('calculateStock', [10, ['type' => 'copy']]);
        $this->assertSame(10, $result);
    }

    public function testCalculateStockFixed(): void
    {
        $result = $this->invokePrivateMethod('calculateStock', [10, ['type' => 'fixed', 'value' => 5]]);
        $this->assertSame(5, $result);
    }

    public function testCalculateStockZero(): void
    {
        $result = $this->invokePrivateMethod('calculateStock', [10, ['type' => 'zero']]);
        $this->assertSame(0, $result);
    }

    public function testCalculateStockPercentage(): void
    {
        $result = $this->invokePrivateMethod('calculateStock', [100, ['type' => 'percentage', 'value' => 50]]);
        $this->assertSame(50, $result);
    }

    public function testCalculateStockPercentageNeverNegative(): void
    {
        $result = $this->invokePrivateMethod('calculateStock', [1, ['type' => 'percentage', 'value' => -100]]);
        $this->assertSame(0, $result);
    }

    public function testCalculateStockDefault(): void
    {
        $result = $this->invokePrivateMethod('calculateStock', [7, ['type' => 'unknown']]);
        $this->assertSame(7, $result);
    }

    // =========================================================================
    // Behavioral: sanitizeTitle
    // =========================================================================

    public function testSanitizeTitleNoOptions(): void
    {
        $result = $this->invokePrivateMethod('sanitizeTitle', ['Bagageiro CG 160', []]);
        $this->assertSame('Bagageiro CG 160', $result);
    }

    public function testSanitizeTitleWithPrefix(): void
    {
        $result = $this->invokePrivateMethod('sanitizeTitle', ['CG 160', ['title_prefix' => 'Bagageiro']]);
        $this->assertSame('Bagageiro CG 160', $result);
    }

    public function testSanitizeTitleWithSuffix(): void
    {
        $result = $this->invokePrivateMethod('sanitizeTitle', ['Bagageiro CG', ['title_suffix' => '160']]);
        $this->assertSame('Bagageiro CG 160', $result);
    }

    public function testSanitizeTitleTruncatesAt60(): void
    {
        $longTitle = str_repeat('A', 70);
        $result = $this->invokePrivateMethod('sanitizeTitle', [$longTitle, []]);
        $this->assertLessThanOrEqual(60, mb_strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    public function testSanitizeTitleTrims(): void
    {
        $result = $this->invokePrivateMethod('sanitizeTitle', ['  CG 160  ', []]);
        $this->assertSame('CG 160', $result);
    }

    // =========================================================================
    // Behavioral: preparePictures
    // =========================================================================

    public function testPreparePicturesReturnsSourceUrls(): void
    {
        $pics = [
            ['url' => 'https://img.com/1.jpg'],
            ['url' => 'https://img.com/2.jpg'],
        ];
        $result = $this->invokePrivateMethod('preparePictures', [$pics]);
        $this->assertCount(2, $result);
        $this->assertSame('https://img.com/1.jpg', $result[0]['source']);
        $this->assertSame('https://img.com/2.jpg', $result[1]['source']);
    }

    public function testPreparePicturesHandlesSourceKey(): void
    {
        $pics = [['source' => 'https://img.com/1.jpg']];
        $result = $this->invokePrivateMethod('preparePictures', [$pics]);
        $this->assertSame('https://img.com/1.jpg', $result[0]['source']);
    }

    public function testPreparePicturesEmptyArray(): void
    {
        $result = $this->invokePrivateMethod('preparePictures', [[]]);
        $this->assertSame([], $result);
    }

    // =========================================================================
    // Behavioral: filterClonableAttributes
    // =========================================================================

    public function testFilterClonableAttributesExcludesItemCondition(): void
    {
        $attrs = [
            ['id' => 'ITEM_CONDITION', 'value_name' => 'Novo'],
            ['id' => 'BRAND', 'value_name' => 'Honda'],
        ];
        $result = $this->invokePrivateMethod('filterClonableAttributes', [$attrs]);
        $ids = array_column($result, 'id');
        $this->assertNotContains('ITEM_CONDITION', $ids);
        $this->assertContains('BRAND', $ids);
    }

    public function testFilterClonableAttributesExcludesSellerSKU(): void
    {
        $attrs = [
            ['id' => 'SELLER_SKU', 'value_name' => 'ABC-123'],
            ['id' => 'COLOR', 'value_name' => 'Preto'],
        ];
        $result = $this->invokePrivateMethod('filterClonableAttributes', [$attrs]);
        $ids = array_column($result, 'id');
        $this->assertNotContains('SELLER_SKU', $ids);
        $this->assertContains('COLOR', $ids);
    }

    // =========================================================================
    // Behavioral: extractBrandFromItem
    // =========================================================================

    public function testExtractBrandFromItemBRAND(): void
    {
        $item = ['attributes' => [['id' => 'BRAND', 'value_name' => 'Honda']]];
        $result = $this->invokePrivateMethod('extractBrandFromItem', [$item]);
        $this->assertSame('Honda', $result);
    }

    public function testExtractBrandFromItemMARCA(): void
    {
        $item = ['attributes' => [['id' => 'MARCA', 'value_name' => 'Yamaha']]];
        $result = $this->invokePrivateMethod('extractBrandFromItem', [$item]);
        $this->assertSame('Yamaha', $result);
    }

    public function testExtractBrandFromItemNoBrand(): void
    {
        $item = ['attributes' => [['id' => 'COLOR', 'value_name' => 'Preto']]];
        $result = $this->invokePrivateMethod('extractBrandFromItem', [$item]);
        $this->assertNull($result);
    }

    public function testExtractBrandFromItemNoAttributes(): void
    {
        $result = $this->invokePrivateMethod('extractBrandFromItem', [[]]);
        $this->assertNull($result);
    }

    // =========================================================================
    // Behavioral: calculateNextExecution
    // =========================================================================

    public function testCalculateNextExecutionDaily(): void
    {
        $result = $this->invokePrivateMethod('calculateNextExecution', ['daily']);
        $expected = (new \DateTime())->modify('+1 day');
        $this->assertSame($expected->format('Y-m-d'), substr($result, 0, 10));
    }

    public function testCalculateNextExecutionWeekly(): void
    {
        $result = $this->invokePrivateMethod('calculateNextExecution', ['weekly']);
        $expected = (new \DateTime())->modify('+1 week');
        $this->assertSame($expected->format('Y-m-d'), substr($result, 0, 10));
    }

    public function testCalculateNextExecutionMonthly(): void
    {
        $result = $this->invokePrivateMethod('calculateNextExecution', ['monthly']);
        $expected = (new \DateTime())->modify('+1 month');
        $this->assertSame($expected->format('Y-m-d'), substr($result, 0, 10));
    }

    // =========================================================================
    // Behavioral: calculatePrice
    // =========================================================================

    public function testCalculatePriceCopy(): void
    {
        $result = $this->invokePrivateMethod('calculatePrice', [100.0, ['type' => 'copy'], 1, []]);
        $this->assertSame(100.0, $result);
    }

    public function testCalculatePriceMarkupPercent(): void
    {
        $result = $this->invokePrivateMethod('calculatePrice', [100.0, ['type' => 'markup_percent', 'value' => 10], 1, []]);
        $this->assertSame(110.0, $result);
    }

    public function testCalculatePriceMarkdownPercent(): void
    {
        $result = $this->invokePrivateMethod('calculatePrice', [100.0, ['type' => 'markdown_percent', 'value' => 20], 1, []]);
        $this->assertSame(80.0, $result);
    }

    public function testCalculatePriceFixed(): void
    {
        $result = $this->invokePrivateMethod('calculatePrice', [100.0, ['type' => 'fixed', 'value' => 50], 1, []]);
        $this->assertSame(50.0, $result);
    }

    public function testCalculatePriceDefault(): void
    {
        $result = $this->invokePrivateMethod('calculatePrice', [99.99, ['type' => 'unknown'], 1, []]);
        $this->assertSame(99.99, $result);
    }

    // =========================================================================
    // Behavioral: validateCloneParams
    // =========================================================================

    public function testValidateCloneParamsValid(): void
    {
        // Should not throw
        $this->invokePrivateMethod('validateCloneParams', [[
            'source_account_id' => '123',
            'source_item_id' => 'MLB456789',
            'target_account_id' => '789',
        ]]);
        $this->assertTrue(true);
    }

    public function testValidateCloneParamsMissingField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invokePrivateMethod('validateCloneParams', [[
            'source_account_id' => '123',
            'target_account_id' => '789',
        ]]);
    }

    public function testValidateCloneParamsInvalidItemId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invokePrivateMethod('validateCloneParams', [[
            'source_account_id' => '123',
            'source_item_id' => 'INVALID',
            'target_account_id' => '789',
        ]]);
    }

    public function testValidateCloneParamsNonNumericAccount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invokePrivateMethod('validateCloneParams', [[
            'source_account_id' => 'abc',
            'source_item_id' => 'MLB123456',
            'target_account_id' => '789',
        ]]);
    }

    // =========================================================================
    // Behavioral: prepareVariations
    // =========================================================================

    public function testPrepareVariationsReturnsArray(): void
    {
        $variations = [
            [
                'attribute_combinations' => [['id' => 'COLOR', 'value_name' => 'Preto']],
                'available_quantity' => 5,
                'price' => 100.0,
                'picture_ids' => ['pic1'],
            ],
        ];
        $result = $this->invokePrivateMethod('prepareVariations', [$variations, 90.0]);
        $this->assertCount(1, $result);
        $this->assertSame(100.0, $result[0]['price']);
        $this->assertSame(['pic1'], $result[0]['picture_ids']);
    }

    public function testPrepareVariationsUsesBasePriceWhenMissing(): void
    {
        $variations = [
            [
                'attribute_combinations' => [],
                'available_quantity' => 3,
            ],
        ];
        $result = $this->invokePrivateMethod('prepareVariations', [$variations, 75.0]);
        $this->assertSame(75.0, $result[0]['price']);
    }
}
