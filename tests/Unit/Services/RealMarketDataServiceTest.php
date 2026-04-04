<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CacheService;
use App\Services\MercadoLivreClient;
use App\Services\RealMarketDataService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\RealMarketDataService
 */
class RealMarketDataServiceTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function buildService(
        MercadoLivreClient $client,
        CacheService $cache,
        ?\PDO $db = null
    ): RealMarketDataService {
        $ref = new \ReflectionClass(RealMarketDataService::class);
        $svc = $ref->newInstanceWithoutConstructor();

        $clientProp = $ref->getProperty('mlClient');
        $clientProp->setAccessible(true);
        $clientProp->setValue($svc, $client);

        $cacheProp = $ref->getProperty('cache');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue($svc, $cache);

        $accountProp = $ref->getProperty('accountId');
        $accountProp->setAccessible(true);
        $accountProp->setValue($svc, 1);

        if ($db !== null) {
            $dbProp = $ref->getProperty('db');
            $dbProp->setAccessible(true);
            $dbProp->setValue($svc, $db);
        }

        return $svc;
    }

    /** Cache always misses, set() is a no-op */
    private function emptyCache(): CacheService
    {
        $cache = $this->createMock(CacheService::class);
        $cache->method('has')->willReturn(false);
        $cache->method('get')->willReturn(null);
        $cache->method('set')->willReturn(true);
        return $cache;
    }

    /** Cache always hits and returns $data */
    private function hitCache(mixed $data): CacheService
    {
        $cache = $this->createMock(CacheService::class);
        $cache->method('has')->willReturn(true);
        $cache->method('get')->willReturn($data);
        return $cache;
    }

    private function makeStmt(mixed $fetchReturn = false, array $fetchAllReturn = []): \PDOStatement
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        return $stmt;
    }

    private function makeDb(mixed $fetchReturn = false, array $fetchAllReturn = []): \PDO
    {
        $stmt = $this->makeStmt($fetchReturn, $fetchAllReturn);
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willReturn($stmt);
        return $db;
    }

    // =========================================================================
    // getCategoryDetails
    // =========================================================================

    public function testGetCategoryDetailsCacheHit(): void
    {
        $cached = ['id' => 'MLB1', 'name' => 'Cached Cat'];

        $client = $this->createMock(MercadoLivreClient::class);
        $client->expects($this->never())->method('getCategory');

        $svc = $this->buildService($client, $this->hitCache($cached));
        $result = $svc->getCategoryDetails('MLB1');

        $this->assertSame('Cached Cat', $result['name']);
    }

    public function testGetCategoryDetailsSuccess(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('getCategory')->willReturn([
            'id' => 'MLB1',
            'name' => 'Motocicletas',
            'path_from_root' => [['id' => 'MLB1', 'name' => 'Motocicletas']],
            'total_items_in_this_category' => 5000,
        ]);
        $client->method('getCategoryAttributes')->willReturn([
            ['id' => 'BRAND', 'name' => 'Marca', 'tags' => ['required' => true], 'values' => []],
            ['id' => 'MODEL', 'name' => 'Modelo', 'tags' => [], 'values' => []],
        ]);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->getCategoryDetails('MLB1');

        $this->assertSame('MLB1', $result['id']);
        $this->assertSame('Motocicletas', $result['name']);
        $this->assertSame(2, $result['attribute_count']);
        $this->assertSame(5000, $result['total_items_in_this_category']);
    }

    public function testGetCategoryDetailsEmpty(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('getCategory')->willReturn([]);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->getCategoryDetails('MLB999');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('não encontrada', $result['error']);
    }

    // =========================================================================
    // analyzePricing
    // =========================================================================

    public function testAnalyzePricingApiSuccess(): void
    {
        $items = [
            ['price' => 100.0, 'shipping' => ['free_shipping' => true, 'tags' => []]],
            ['price' => 200.0, 'shipping' => ['free_shipping' => false, 'tags' => ['fulfillment']]],
            ['price' => 300.0, 'shipping' => ['free_shipping' => true, 'tags' => []]],
        ];

        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('searchItems')->willReturn([
            'results' => $items,
            'paging' => ['total' => 3],
        ]);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->analyzePricing('MLB1');

        $this->assertSame(3, $result['sample_size']);
        $this->assertSame(100.0, $result['price_stats']['min']);
        $this->assertSame(300.0, $result['price_stats']['max']);
        $this->assertSame(200.0, $result['price_stats']['avg']);
        $this->assertSame(200.0, $result['price_stats']['median']);
    }

    public function testAnalyzePricingFallsBackWhenApiReturnsError(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('searchItems')->willReturn(['error' => 'forbidden']);

        // Local DB returns empty
        $db = $this->makeDb(false, []);
        $svc = $this->buildService($client, $this->emptyCache(), $db);
        $result = $svc->analyzePricing('MLB1');

        $this->assertSame('local_db', $result['source']);
        $this->assertSame(0, $result['sample_size']);
    }

    public function testAnalyzePricingFallsBackWhenApiResultsEmpty(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('searchItems')->willReturn(['results' => [], 'paging' => []]);

        $dbRows = [
            ['price' => '150.00', 'data' => json_encode(['shipping' => ['free_shipping' => false, 'tags' => []]])],
            ['price' => '250.00', 'data' => json_encode(['shipping' => ['free_shipping' => true, 'tags' => []]])],
        ];

        $db = $this->makeDb(false, $dbRows);
        $svc = $this->buildService($client, $this->emptyCache(), $db);
        $result = $svc->analyzePricing('MLB1');

        $this->assertSame('local_db', $result['source']);
        $this->assertSame(2, $result['sample_size']);
        $this->assertSame(150.0, $result['price_stats']['min']);
        $this->assertSame(250.0, $result['price_stats']['max']);
    }

    public function testAnalyzePricingNoPricesReturnsError(): void
    {
        $items = [
            ['price' => 0, 'shipping' => ['free_shipping' => false, 'tags' => []]],
        ];

        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('searchItems')->willReturn([
            'results' => $items,
            'paging' => ['total' => 1],
        ]);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->analyzePricing('MLB1');

        $this->assertArrayHasKey('error', $result);
    }

    public function testAnalyzePricingMedianEvenCount(): void
    {
        $items = array_map(
            fn(float $p) => ['price' => $p, 'shipping' => ['free_shipping' => false, 'tags' => []]],
            [100.0, 200.0, 300.0, 400.0]
        );

        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('searchItems')->willReturn([
            'results' => $items,
            'paging' => ['total' => 4],
        ]);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->analyzePricing('MLB1');

        // Median of [100,200,300,400] = (200+300)/2 = 250
        $this->assertSame(250.0, $result['price_stats']['median']);
    }

    // =========================================================================
    // getTrends
    // =========================================================================

    public function testGetTrendsFromMlApi(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('getTrends')->willReturn(['bagageiro cg 160', 'bau moto', 'retrovisor titan']);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->getTrends('MLB1');

        $this->assertSame('ml_trends_api', $result['source']);
        $this->assertCount(3, $result['keywords']);
        $this->assertSame(3, $result['total']);
    }

    public function testGetTrendsFallbackToDomainDiscovery(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('getTrends')->willReturn([]);
        $client->method('getCategory')->willReturn(['name' => 'Bagageiros']);
        $client->method('get')->willReturn([
            ['domain_id' => 'd1', 'domain_name' => 'Bagageiros Lateral', 'category_id' => 'MLB1', 'category_name' => 'Bagageiros'],
        ]);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->getTrends('MLB1');

        $this->assertSame('domain_discovery', $result['source']);
        $this->assertNotEmpty($result['keywords']);
    }

    public function testGetTrendsFallbackToAttributes(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('getTrends')->willReturn([]);
        $client->method('getCategory')->willReturn(['name' => 'Bagageiros']);
        $client->method('get')->willReturn(['error' => 'forbidden']);
        $client->method('getCategoryAttributes')->willReturn([
            ['name' => 'Marca', 'tags' => [], 'values' => [['name' => 'Honda'], ['name' => 'Yamaha']]],
            ['name' => 'Modelo', 'tags' => [], 'values' => []],
        ]);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->getTrends('MLB1');

        $this->assertSame('category_attributes', $result['source']);
        $this->assertContains('Marca', $result['keywords']);
        $this->assertContains('Honda', $result['keywords']);
    }

    // =========================================================================
    // discoverRelatedDomains
    // =========================================================================

    public function testDiscoverRelatedDomainsCacheHit(): void
    {
        $cached = [['domain_id' => 'd1', 'domain_name' => 'Bagageiro', 'category_id' => 'MLB1', 'category_name' => 'Bags']];

        $client = $this->createMock(MercadoLivreClient::class);
        $client->expects($this->never())->method('get');

        $svc = $this->buildService($client, $this->hitCache($cached));
        $result = $svc->discoverRelatedDomains('bagageiro');

        $this->assertCount(1, $result);
        $this->assertSame('Bagageiro', $result[0]['domain_name']);
    }

    public function testDiscoverRelatedDomainsApiSuccess(): void
    {
        $apiResponse = [
            ['domain_id' => 'd1', 'domain_name' => 'Bagageiro Lateral', 'category_id' => 'MLB10', 'category_name' => 'Bagageiros'],
            ['domain_id' => 'd2', 'domain_name' => 'Baú para Moto', 'category_id' => 'MLB11', 'category_name' => 'Baús'],
        ];

        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('get')->willReturn($apiResponse);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->discoverRelatedDomains('moto');

        $this->assertCount(2, $result);
        $this->assertSame('Bagageiro Lateral', $result[0]['domain_name']);
        $this->assertSame('MLB10', $result[0]['category_id']);
    }

    public function testDiscoverRelatedDomainsApiErrorReturnsEmpty(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('get')->willReturn(['error' => 'forbidden']);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->discoverRelatedDomains('moto');

        $this->assertSame([], $result);
    }

    // =========================================================================
    // analyzeCompetitors
    // =========================================================================

    public function testAnalyzeCompetitorsSuccess(): void
    {
        $items = [
            [
                'id' => 'MLB111', 'title' => 'Bagageiro CG 160', 'price' => 150.0,
                'original_price' => null, 'sold_quantity' => 100, 'condition' => 'new',
                'listing_type_id' => 'gold_special',
                'seller' => ['id' => 1, 'nickname' => 'Seller1', 'power_seller_status' => 'gold', 'seller_reputation' => ['level_id' => 5]],
                'shipping' => ['free_shipping' => true, 'mode' => 'me2', 'tags' => ['fulfillment']],
                'official_store_id' => null, 'catalog_product_id' => 'MLB1P1', 'permalink' => 'http://example.com',
            ],
        ];

        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('searchItems')->willReturn([
            'results' => $items,
            'paging' => ['total' => 500],
        ]);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->analyzeCompetitors('MLB1');

        $this->assertNotEmpty($result['competitors']);
        $this->assertSame(500, $result['total_in_category']);
        $this->assertSame('MLB111', $result['competitors'][0]['item_id']);
        $this->assertNotEmpty($result['insights']);
    }

    public function testAnalyzeCompetitorsApiErrorReturnsError(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('searchItems')->willReturn(['error' => 'forbidden']);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->analyzeCompetitors('MLB1');

        $this->assertArrayHasKey('error', $result);
        $this->assertEmpty($result['competitors']);
    }

    public function testAnalyzeCompetitorsDeduplicatesSellers(): void
    {
        $makeItem = fn(string $id, int $sellerId) => [
            'id' => $id, 'title' => 'Item', 'price' => 100.0, 'original_price' => null,
            'sold_quantity' => 10, 'condition' => 'new', 'listing_type_id' => 'gold',
            'seller' => ['id' => $sellerId, 'nickname' => 'S', 'seller_reputation' => []],
            'shipping' => ['free_shipping' => false, 'mode' => 'me2', 'tags' => []],
            'official_store_id' => null, 'catalog_product_id' => null, 'permalink' => '',
        ];

        $items = [$makeItem('A1', 42), $makeItem('A2', 42), $makeItem('A3', 99)];

        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('searchItems')->willReturn(['results' => $items, 'paging' => ['total' => 3]]);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->analyzeCompetitors('MLB1');

        // Seller 42 should appear only once
        $this->assertCount(2, $result['competitors']);
    }

    // =========================================================================
    // getAvailableFilters
    // =========================================================================

    public function testGetAvailableFiltersSuccess(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('searchItems')->willReturn([
            'results' => [],
            'available_filters' => [
                [
                    'id' => 'BRAND',
                    'name' => 'Marca',
                    'values' => [
                        ['id' => 'honda', 'name' => 'Honda', 'results' => 200],
                        ['id' => 'yamaha', 'name' => 'Yamaha', 'results' => 150],
                    ],
                ],
            ],
        ]);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->getAvailableFilters('MLB1');

        $this->assertSame(1, $result['total_filters']);
        $this->assertSame('BRAND', $result['filters'][0]['id']);
        $this->assertSame('Honda', $result['filters'][0]['values'][0]['name']);
    }

    public function testGetAvailableFiltersApiError(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('searchItems')->willReturn(['error' => 'forbidden']);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->getAvailableFilters('MLB1');

        $this->assertArrayHasKey('error', $result);
        $this->assertEmpty($result['filters']);
    }

    // =========================================================================
    // findSimilarProducts
    // =========================================================================

    public function testFindSimilarProductsApiError(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('searchItems')->willReturn(['error' => 'forbidden']);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->findSimilarProducts('Bagageiro CG 160', 'MLB1');

        $this->assertArrayHasKey('error', $result);
        $this->assertEmpty($result['products']);
    }

    public function testFindSimilarProductsReturnsRankedResults(): void
    {
        $items = [
            ['id' => 'MLB1', 'title' => 'Bagageiro CG 160 Honda', 'price' => 100.0, 'sold_quantity' => 50,
             'shipping' => ['free_shipping' => false, 'tags' => []], 'official_store_id' => null],
            ['id' => 'MLB2', 'title' => 'Retrovisor CG 160', 'price' => 30.0, 'sold_quantity' => 20,
             'shipping' => ['free_shipping' => true, 'tags' => []], 'official_store_id' => null],
        ];

        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('searchItems')->willReturn(['results' => $items, 'paging' => []]);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->findSimilarProducts('Bagageiro CG 160 Honda', 'MLB1');

        $this->assertNotEmpty($result['products']);
        // First item should have higher similarity
        $this->assertGreaterThanOrEqual(
            $result['products'][1]['similarity_score'] ?? 0,
            $result['products'][0]['similarity_score']
        );
    }

    // =========================================================================
    // suggestPrice
    // =========================================================================

    public function testSuggestPriceUsesWeightedAvgFromSimilar(): void
    {
        $items = [
            ['id' => 'A', 'title' => 'Bagageiro CG 160 Honda', 'price' => 100.0, 'sold_quantity' => 50,
             'shipping' => ['free_shipping' => false, 'tags' => []], 'official_store_id' => null],
            ['id' => 'B', 'title' => 'Bagageiro Honda CG 160 Lateral', 'price' => 120.0, 'sold_quantity' => 30,
             'shipping' => ['free_shipping' => true, 'tags' => []], 'official_store_id' => null],
        ];

        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('searchItems')->willReturn(['results' => $items, 'paging' => []]);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->suggestPrice('MLB1', 'Bagageiro CG 160 Honda');

        $this->assertTrue($result['success']);
        $this->assertSame('similar_products', $result['method']);
        $this->assertArrayHasKey('suggested_price', $result);
        $this->assertArrayHasKey('competitive_price', $result);
        $this->assertArrayHasKey('premium_price', $result);
        $this->assertGreaterThan(0, $result['suggested_price']);
    }

    public function testSuggestPriceFallsBackToCategoryWhenNoSimilar(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        // findSimilarProducts returns empty
        $client->method('searchItems')->willReturnOnConsecutiveCalls(
            ['error' => 'no results'],  // for findSimilarProducts
            ['results' => [             // for analyzePricing fallback via local db trigger
                ['price' => 200.0, 'shipping' => ['free_shipping' => false, 'tags' => []]],
            ], 'paging' => []]
        );

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->suggestPrice('MLB1', 'Produto Raro');

        $this->assertTrue($result['success']);
        $this->assertSame('category_average', $result['method']);
        $this->assertSame('low', $result['confidence']);
    }

    // =========================================================================
    // autocomplete
    // =========================================================================

    public function testAutocompleteReturnsSuccessWithSuggestions(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('getAutocompleteSuggestions')->willReturn([
            'suggested_queries' => [
                ['q' => 'bagageiro cg 160'],
                ['q' => 'bagageiro honda'],
            ],
        ]);
        $client->method('get')->willReturn(['error' => 'forbidden']); // domain discovery fails

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->autocomplete('bagageiro');

        $this->assertTrue($result['success']);
        $this->assertSame('bagageiro', $result['query']);
        $this->assertCount(2, $result['suggestions']);
        $this->assertSame('bagageiro cg 160', $result['suggestions'][0]['text']);
    }

    public function testAutocompleteReturnsSuccessWhenNoSuggestions(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('getAutocompleteSuggestions')->willReturn(['error' => 'no results']);
        $client->method('get')->willReturn(['error' => 'forbidden']);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->autocomplete('xyz_no_results');

        $this->assertTrue($result['success']);
        $this->assertSame([], $result['suggestions']);
    }

    // =========================================================================
    // getMarketStats
    // =========================================================================

    public function testGetMarketStatsReturnsFormattedStats(): void
    {
        $statsRow = [
            'total_items' => '120',
            'active_items' => '100',
            'avg_price' => '150.5000',
            'min_price' => '50.00',
            'max_price' => '500.00',
            'categories' => '5',
            'total_stock' => '300',
        ];

        $topCats = [
            ['category_id' => 'MLB1', 'category_name' => 'Bagageiros', 'items_count' => '60', 'avg_price' => '160.00'],
        ];

        // Two prepare calls: first returns stats, second returns top categories
        $stmt1 = $this->makeStmt($statsRow, []);
        $stmt2 = $this->makeStmt(false, $topCats);

        $callCount = 0;
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willReturnCallback(function () use (&$callCount, $stmt1, $stmt2) {
            $callCount++;
            return $callCount === 1 ? $stmt1 : $stmt2;
        });

        $client = $this->createMock(MercadoLivreClient::class);
        $svc = $this->buildService($client, $this->emptyCache(), $db);
        $result = $svc->getMarketStats();

        $this->assertTrue($result['success']);
        $this->assertSame(120, $result['stats']['total_items']);
        $this->assertSame(100, $result['stats']['active_items']);
        $this->assertSame(150.5, $result['stats']['avg_price']);
        $this->assertSame(50.0, $result['stats']['price_range']['min']);
        $this->assertSame(500.0, $result['stats']['price_range']['max']);
        $this->assertSame(5, $result['stats']['total_categories']);
        $this->assertSame(300, $result['stats']['total_stock']);
        $this->assertCount(1, $result['top_categories']);
    }

    // =========================================================================
    // getCategoryRequirements
    // =========================================================================

    public function testGetCategoryRequirementsSuccess(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('getCategory')->willReturn([
            'id' => 'MLB1',
            'name' => 'Bagageiros',
            'path_from_root' => [],
            'total_items_in_this_category' => 100,
        ]);
        $client->method('getCategoryAttributes')->willReturn([
            ['id' => 'BRAND', 'name' => 'Marca', 'value_type' => 'string',
             'tags' => ['required' => true], 'values' => []],
            ['id' => 'MODEL', 'name' => 'Modelo', 'value_type' => 'string',
             'tags' => [], 'values' => []],
        ]);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->getCategoryRequirements('MLB1');

        $this->assertTrue($result['success']);
        $this->assertSame('MLB1', $result['category_id']);
        $this->assertSame('Bagageiros', $result['category_name']);
        $this->assertNotEmpty($result['requirements']);
        $this->assertArrayHasKey('required', $result['requirements']);
        $this->assertArrayHasKey('recommended', $result['requirements']);
        $this->assertArrayHasKey('optional', $result['requirements']);
    }

    public function testGetCategoryRequirementsNotFound(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('getCategory')->willReturn([]);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->getCategoryRequirements('MLB999');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    // =========================================================================
    // analyzeMarket
    // =========================================================================

    public function testAnalyzeMarketCacheHit(): void
    {
        $cached = ['success' => true, 'category_id' => 'MLB1', 'from_cache' => true];

        $client = $this->createMock(MercadoLivreClient::class);
        $client->expects($this->never())->method('getCategory');

        $svc = $this->buildService($client, $this->hitCache($cached));
        $result = $svc->analyzeMarket('MLB1');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['from_cache']);
    }

    public function testAnalyzeMarketComposesAllSections(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);

        // getCategoryDetails
        $client->method('getCategory')->willReturn([
            'id' => 'MLB1', 'name' => 'Bagageiros',
            'path_from_root' => [], 'total_items_in_this_category' => 50,
        ]);
        $client->method('getCategoryAttributes')->willReturn([]);

        // analyzePricing - API success
        $client->method('searchItems')->willReturn([
            'results' => [
                ['price' => 100.0, 'shipping' => ['free_shipping' => false, 'tags' => []]],
            ],
            'paging' => ['total' => 1],
            'available_filters' => [],
        ]);

        // getTrends
        $client->method('getTrends')->willReturn(['bagageiro']);

        $svc = $this->buildService($client, $this->emptyCache());
        $result = $svc->analyzeMarket('MLB1');

        $this->assertTrue($result['success']);
        $this->assertSame('MLB1', $result['category_id']);
        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('pricing', $result);
        $this->assertArrayHasKey('trends', $result);
        $this->assertArrayHasKey('competitors', $result);
        $this->assertArrayHasKey('filters', $result);
    }
}
