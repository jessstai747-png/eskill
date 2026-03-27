<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CatalogCloneService;
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
            ['createSellerBatchJob'],
            ['createBatchJob'],
            ['resolveItemIds'],
            ['pricePreviewBatch'],
            ['checkLocalDuplicate'],
            ['searchSeller'],
            ['validatePreExecution'],
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
            ['normalizeFacets'],
            ['normalizeSummaryResponse'],
            ['resolveSellerByNickname'],
            ['getSellerSnapshot'],
            ['saveSellerSnapshot'],
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

    // =========================================================================
    // Behavioral: createSellerBatchJob
    // =========================================================================

    public function testCreateSellerBatchJobThrowsOnMissingTargetAccountId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/target_account_id/');
        $instance = $this->reflection->newInstanceWithoutConstructor();
        $instance->createSellerBatchJob([
            'source_seller_id' => '12345678',
        ]);
    }

    public function testCreateSellerBatchJobThrowsOnZeroTargetAccountId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $instance = $this->reflection->newInstanceWithoutConstructor();
        $instance->createSellerBatchJob([
            'target_account_id' => 0,
            'source_seller_id'  => '12345678',
        ]);
    }

    public function testCreateSellerBatchJobThrowsOnMissingSourceSellerId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/source_seller_id/');
        $instance = $this->reflection->newInstanceWithoutConstructor();
        $instance->createSellerBatchJob([
            'target_account_id' => 5,
        ]);
    }

    public function testCreateSellerBatchJobThrowsOnNonNumericSellerId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $instance = $this->reflection->newInstanceWithoutConstructor();
        $instance->createSellerBatchJob([
            'target_account_id' => 5,
            'source_seller_id'  => 'nao-tem-digitos',
        ]);
    }

    public function testCreateSellerBatchJobThrowsWhenItemCountExceedsLimit(): void
    {
        $_ENV['CLONE_JOB_MAX_ITEMS'] = '3';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/limite/i');
        try {
            $instance = $this->reflection->newInstanceWithoutConstructor();
            $instance->createSellerBatchJob([
                'target_account_id' => 5,
                'source_seller_id'  => '12345678',
                'item_ids'          => ['MLB1', 'MLB2', 'MLB3', 'MLB4'],
            ]);
        } finally {
            unset($_ENV['CLONE_JOB_MAX_ITEMS']);
        }
    }

    public function testCreateSellerBatchJobAppliesGuardrailDefaultsFalse(): void
    {
        /** @var \App\Services\CatalogCloneService&\PHPUnit\Framework\MockObject\MockObject $mock */
        $mock = $this->getMockBuilder(\App\Services\CatalogCloneService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createBatchJob'])
            ->getMock();

        $capturedParams = [];
        $mock->expects($this->once())
            ->method('createBatchJob')
            ->willReturnCallback(function (array $params) use (&$capturedParams): array {
                $capturedParams = $params;
                return ['job_id' => 'test_job_001'];
            });

        $result = $mock->createSellerBatchJob([
            'target_account_id' => 5,
            'source_seller_id'  => '12345678',
        ]);

        $this->assertSame(['job_id' => 'test_job_001'], $result);
        $this->assertFalse($capturedParams['options']['include_description']);
        $this->assertFalse($capturedParams['options']['include_pictures']);
        $this->assertSame('seller', $capturedParams['source_type']);
    }

    public function testCreateSellerBatchJobRespectsExplicitGuardrailTrue(): void
    {
        /** @var \App\Services\CatalogCloneService&\PHPUnit\Framework\MockObject\MockObject $mock */
        $mock = $this->getMockBuilder(\App\Services\CatalogCloneService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createBatchJob'])
            ->getMock();

        $capturedParams = [];
        $mock->method('createBatchJob')
            ->willReturnCallback(function (array $params) use (&$capturedParams): array {
                $capturedParams = $params;
                return ['job_id' => 'test_job_002'];
            });

        $mock->createSellerBatchJob([
            'target_account_id' => 5,
            'source_seller_id'  => '12345678',
            'options'           => ['include_description' => true, 'include_pictures' => true],
        ]);

        $this->assertTrue($capturedParams['options']['include_description']);
        $this->assertTrue($capturedParams['options']['include_pictures']);
    }

    public function testCreateSellerBatchJobStoresSellerFiltersInOptions(): void
    {
        /** @var \App\Services\CatalogCloneService&\PHPUnit\Framework\MockObject\MockObject $mock */
        $mock = $this->getMockBuilder(\App\Services\CatalogCloneService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createBatchJob'])
            ->getMock();

        $capturedParams = [];
        $mock->method('createBatchJob')
            ->willReturnCallback(function (array $params) use (&$capturedParams): array {
                $capturedParams = $params;
                return ['job_id' => 'test_job_003'];
            });

        $mock->createSellerBatchJob([
            'target_account_id' => 5,
            'source_seller_id'  => '99887766',
            'filters'           => ['brand' => 'Honda', 'category' => 'MLB1234'],
        ]);

        $filters = $capturedParams['options']['seller_filters'];
        $this->assertSame('Honda', $filters['brand']);
        $this->assertSame('MLB1234', $filters['category']);
        $this->assertSame('99887766', $capturedParams['source_seller_id']);
    }

    public function testCreateSellerBatchJobStripsNonDigitsFromSellerId(): void
    {
        /** @var \App\Services\CatalogCloneService&\PHPUnit\Framework\MockObject\MockObject $mock */
        $mock = $this->getMockBuilder(\App\Services\CatalogCloneService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createBatchJob'])
            ->getMock();

        $capturedParams = [];
        $mock->method('createBatchJob')
            ->willReturnCallback(function (array $params) use (&$capturedParams): array {
                $capturedParams = $params;
                return ['job_id' => 'test_job_004'];
            });

        $mock->createSellerBatchJob([
            'target_account_id' => 5,
            'source_seller_id'  => 'MLB12345678',
        ]);

        $this->assertSame('12345678', $capturedParams['source_seller_id']);
    }

    public function testCreateSellerBatchJobPassesItemIdsForward(): void
    {
        /** @var \App\Services\CatalogCloneService&\PHPUnit\Framework\MockObject\MockObject $mock */
        $mock = $this->getMockBuilder(\App\Services\CatalogCloneService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createBatchJob'])
            ->getMock();

        $capturedParams = [];
        $mock->method('createBatchJob')
            ->willReturnCallback(function (array $params) use (&$capturedParams): array {
                $capturedParams = $params;
                return ['job_id' => 'test_job_005'];
            });

        $itemIds = ['MLB111', 'MLB222', 'MLB333'];
        $mock->createSellerBatchJob([
            'target_account_id' => 5,
            'source_seller_id'  => '12345678',
            'item_ids'          => $itemIds,
        ]);

        $this->assertSame($itemIds, $capturedParams['item_ids']);
    }

    // =========================================================================
    // Behavioral: normalizeFacets
    // =========================================================================

    public function testNormalizeFacetsConvertsBrandsToIndexedArray(): void
    {
        $brandCounts = ['Honda' => 15, 'Yamaha' => 8, 'Suzuki' => 3];
        $result = $this->invokePrivateMethod('normalizeFacets', [$brandCounts, []]);

        $this->assertCount(3, $result['brands']);
        $this->assertSame('Honda', $result['brands'][0]['id']);
        $this->assertSame('Honda', $result['brands'][0]['name']);
        $this->assertSame(15, $result['brands'][0]['count']);
        $this->assertSame('Yamaha', $result['brands'][1]['id']);
        $this->assertSame(8, $result['brands'][1]['count']);
    }

    public function testNormalizeFacetsConvertsCategoriesToIndexedArray(): void
    {
        $categoryFacets = [
            'MLB12345' => ['name' => 'Acessórios para Motos', 'count' => 42],
            'MLB67890' => ['name' => 'Bagageiros', 'count' => 18],
        ];
        $result = $this->invokePrivateMethod('normalizeFacets', [[], $categoryFacets]);

        $this->assertCount(2, $result['categories']);
        $this->assertSame('MLB12345', $result['categories'][0]['id']);
        $this->assertSame('Acessórios para Motos', $result['categories'][0]['name']);
        $this->assertSame(42, $result['categories'][0]['count']);
        $this->assertSame('MLB67890', $result['categories'][1]['id']);
    }

    public function testNormalizeFacetsReturnsEmptyArraysWhenEmpty(): void
    {
        $result = $this->invokePrivateMethod('normalizeFacets', [[], []]);

        $this->assertSame([], $result['brands']);
        $this->assertSame([], $result['categories']);
    }

    public function testNormalizeFacetsCategoryFallbackToIdWhenNameMissing(): void
    {
        $categoryFacets = ['MLB999' => ['count' => 5]];
        $result = $this->invokePrivateMethod('normalizeFacets', [[], $categoryFacets]);

        $this->assertSame('MLB999', $result['categories'][0]['name']);
    }

    // =========================================================================
    // Behavioral: normalizeSummaryResponse
    // =========================================================================

    public function testNormalizeSummaryResponseAddsNicknameAlias(): void
    {
        $summary = ['seller_nickname' => 'LOJA_TESTE', 'seller_reputation' => '5_green'];
        $result = $this->invokePrivateMethod('normalizeSummaryResponse', [$summary, [], []]);

        $this->assertSame('LOJA_TESTE', $result['nickname']);
        $this->assertSame('LOJA_TESTE', $result['seller_nickname']);
    }

    public function testNormalizeSummaryResponseWrapsReputationAsObject(): void
    {
        $summary = ['seller_nickname' => 'X', 'seller_reputation' => '5_green'];
        $result = $this->invokePrivateMethod('normalizeSummaryResponse', [$summary, [], []]);

        $this->assertIsArray($result['seller_reputation']);
        $this->assertSame('5_green', $result['seller_reputation']['level_id']);
    }

    public function testNormalizeSummaryResponseWrapsNullReputation(): void
    {
        $summary = ['seller_nickname' => 'X', 'seller_reputation' => null];
        $result = $this->invokePrivateMethod('normalizeSummaryResponse', [$summary, [], []]);

        $this->assertIsArray($result['seller_reputation']);
        $this->assertNull($result['seller_reputation']['level_id']);
    }

    public function testNormalizeSummaryResponseConvertsTopBrands(): void
    {
        $brandCounts = ['Honda' => 10, 'Yamaha' => 5];
        $summary = ['seller_nickname' => 'X', 'seller_reputation' => null];
        $result = $this->invokePrivateMethod('normalizeSummaryResponse', [$summary, $brandCounts, []]);

        $this->assertCount(2, $result['top_brands']);
        $this->assertSame('Honda', $result['top_brands'][0]['name']);
        $this->assertSame(10, $result['top_brands'][0]['count']);
    }

    public function testNormalizeSummaryResponseConvertsTopCategories(): void
    {
        $categoryFacets = [
            'MLB123' => ['name' => 'Cat A', 'count' => 20],
            'MLB456' => ['name' => 'Cat B', 'count' => 8],
        ];
        $summary = ['seller_nickname' => 'X', 'seller_reputation' => null];
        $result = $this->invokePrivateMethod('normalizeSummaryResponse', [$summary, [], $categoryFacets]);

        $this->assertCount(2, $result['top_categories']);
        $this->assertSame('MLB123', $result['top_categories'][0]['id']);
        $this->assertSame('Cat A', $result['top_categories'][0]['name']);
        $this->assertSame(20, $result['top_categories'][0]['count']);
    }

    public function testNormalizeSummaryResponsePreservesOriginalKeys(): void
    {
        $brandCounts = ['Honda' => 10];
        $categoryFacets = ['MLB1' => ['name' => 'X', 'count' => 5]];
        $summary = [
            'status' => 'success',
            'seller_id' => '12345',
            'seller_nickname' => 'Test',
            'seller_reputation' => '3_yellow',
            'total_items' => 100,
            'brands' => $brandCounts,
            'categories' => $categoryFacets,
        ];
        $result = $this->invokePrivateMethod('normalizeSummaryResponse', [$summary, $brandCounts, $categoryFacets]);

        // Original keys preserved for backward compat
        $this->assertSame('success', $result['status']);
        $this->assertSame('12345', $result['seller_id']);
        $this->assertSame(100, $result['total_items']);
        $this->assertSame($brandCounts, $result['brands']);
        $this->assertSame($categoryFacets, $result['categories']);
    }

    // =========================================================================
    // Behavioral: normalizeCloneOptions (static method)
    // =========================================================================

    public function testNormalizeCloneOptionsWithStringPricing(): void
    {
        $result = CatalogCloneService::normalizeCloneOptions([
            'pricing_strategy' => 'markup',
            'price_value' => 15,
            'initial_status' => 'paused',
            'include_description' => true,
            'include_pictures' => false,
        ]);

        // 'markup' is mapped to 'markup_percent' for calculatePrice() compatibility
        $this->assertSame(['type' => 'markup_percent', 'value' => 15.0], $result['pricing_strategy']);
        $this->assertSame(['type' => 'copy'], $result['stock_strategy']);
        $this->assertTrue($result['options']['include_description']);
        $this->assertFalse($result['options']['include_pictures']);
        $this->assertTrue($result['options']['start_paused']);
    }

    public function testNormalizeCloneOptionsWithArrayPricing(): void
    {
        $result = CatalogCloneService::normalizeCloneOptions([
            'pricing_strategy' => ['type' => 'competitive'],
        ]);

        $this->assertSame(['type' => 'competitive'], $result['pricing_strategy']);
    }

    public function testNormalizeCloneOptionsDefaultsToCopy(): void
    {
        $result = CatalogCloneService::normalizeCloneOptions([]);

        $this->assertSame(['type' => 'copy'], $result['pricing_strategy']);
        $this->assertSame(['type' => 'copy'], $result['stock_strategy']);
        $this->assertFalse($result['options']['include_description']);
        $this->assertFalse($result['options']['include_pictures']);
        $this->assertTrue($result['options']['start_paused']); // paused by default
    }

    public function testNormalizeCloneOptionsActiveStatus(): void
    {
        $result = CatalogCloneService::normalizeCloneOptions([
            'initial_status' => 'active',
        ]);

        $this->assertFalse($result['options']['start_paused']);
    }

    public function testNormalizeCloneOptionsPreservesSellerFilters(): void
    {
        $filters = ['category' => 'MLB123', 'brand' => 'Honda', 'max_items' => 500];
        $result = CatalogCloneService::normalizeCloneOptions([
            'seller_filters' => $filters,
        ]);

        $this->assertSame($filters, $result['options']['seller_filters']);
    }

    public function testNormalizeCloneOptionsPriceValueWithoutStrategy(): void
    {
        $result = CatalogCloneService::normalizeCloneOptions([
            'price_value' => 25.50,
        ]);

        $this->assertSame(['type' => 'copy', 'value' => 25.50], $result['pricing_strategy']);
    }

    public function testNormalizeCloneOptionsStockArrayPreserved(): void
    {
        $result = CatalogCloneService::normalizeCloneOptions([
            'stock_strategy' => ['type' => 'fixed', 'value' => 10],
        ]);

        $this->assertSame(['type' => 'fixed', 'value' => 10], $result['stock_strategy']);
    }

    // =========================================================================
    // Structure: searchSeller method exists
    // =========================================================================

    public function testSearchSellerMethodExists(): void
    {
        $this->assertTrue($this->reflection->hasMethod('searchSeller'));
        $this->assertTrue($this->reflection->getMethod('searchSeller')->isPublic());
    }

    public function testResolveSellerByNicknameMethodExists(): void
    {
        $this->assertTrue($this->reflection->hasMethod('resolveSellerByNickname'));
        $this->assertTrue($this->reflection->getMethod('resolveSellerByNickname')->isPrivate());
    }

    // =========================================================================
    // Behavioral: normalizeCloneOptions type mapping
    // =========================================================================

    public function testNormalizeCloneOptionsMapsMarkdownToMarkdownPercent(): void
    {
        $result = CatalogCloneService::normalizeCloneOptions([
            'pricing_strategy' => 'markdown',
            'price_value' => 10,
        ]);

        $this->assertSame('markdown_percent', $result['pricing_strategy']['type']);
        $this->assertSame(10.0, $result['pricing_strategy']['value']);
    }

    public function testNormalizeCloneOptionsMapsArrayMarkupType(): void
    {
        $result = CatalogCloneService::normalizeCloneOptions([
            'pricing_strategy' => ['type' => 'markup', 'value' => 20],
        ]);

        // Array pricing also gets type remapped
        $this->assertSame('markup_percent', $result['pricing_strategy']['type']);
        $this->assertSame(20, $result['pricing_strategy']['value']);
    }

    public function testNormalizeCloneOptionsPreservesNonMappedTypes(): void
    {
        // Types that are NOT in the mapping table should pass through unchanged
        $copyResult = CatalogCloneService::normalizeCloneOptions([
            'pricing_strategy' => 'copy',
        ]);
        $this->assertSame('copy', $copyResult['pricing_strategy']['type']);

        $fixedResult = CatalogCloneService::normalizeCloneOptions([
            'pricing_strategy' => 'fixed',
            'price_value' => 99.90,
        ]);
        $this->assertSame('fixed', $fixedResult['pricing_strategy']['type']);
        $this->assertSame(99.90, $fixedResult['pricing_strategy']['value']);

        $compResult = CatalogCloneService::normalizeCloneOptions([
            'pricing_strategy' => 'competitive',
        ]);
        $this->assertSame('competitive', $compResult['pricing_strategy']['type']);
    }

    // =========================================================================
    // Structure: validatePreExecution method exists
    // =========================================================================

    public function testValidatePreExecutionMethodExists(): void
    {
        $this->assertTrue($this->reflection->hasMethod('validatePreExecution'));
        $this->assertTrue($this->reflection->getMethod('validatePreExecution')->isPublic());
    }
}
