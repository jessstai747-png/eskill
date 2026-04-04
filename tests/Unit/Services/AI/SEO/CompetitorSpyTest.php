<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI\SEO;

use App\Services\AI\SEO\CompetitorSpy;
use App\Services\MercadoLivreClient;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\AI\SEO\CompetitorSpy
 */
class CompetitorSpyTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function buildSpy(?MercadoLivreClient $mlClient = null, ?\PDO $db = null): CompetitorSpy
    {
        $ref = new \ReflectionClass(CompetitorSpy::class);
        $spy = $ref->newInstanceWithoutConstructor();

        $mlProp = $ref->getProperty('mlClient');
        $mlProp->setAccessible(true);
        $mlProp->setValue($spy, $mlClient);

        $accountProp = $ref->getProperty('accountId');
        $accountProp->setAccessible(true);
        $accountProp->setValue($spy, $mlClient !== null ? 1 : null);

        if ($db !== null) {
            $dbProp = $ref->getProperty('db');
            $dbProp->setAccessible(true);
            $dbProp->setValue($spy, $db);
        }

        return $spy;
    }

    private function makeClient(array $getMap = [], ?string $sellerId = null): MercadoLivreClient
    {
        $client = $this->createMock(MercadoLivreClient::class);

        $client->method('get')->willReturnCallback(
            function (string $endpoint, array $params = []) use ($getMap): array {
                foreach ($getMap as $pattern => $response) {
                    if (str_contains($endpoint, $pattern)) {
                        return $response;
                    }
                }
                return ['error' => 'not_found', 'results' => []];
            }
        );

        $client->method('getSellerId')->willReturn($sellerId);

        return $client;
    }

    private function makeItems(int $count = 3): array
    {
        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $items[] = [
                'id' => "MLB$i",
                'title' => "Bagageiro Lateral CG 160 Honda Todos Modelos Item $i",
                'price' => 50.0 * $i,
                'sold_quantity' => 100 * $i,
                'seller' => ['id' => $i * 10, 'nickname' => "Seller$i", 'seller_reputation' => ['level_id' => 5]],
                'shipping' => ['free_shipping' => $i % 2 === 0, 'mode' => 'me2'],
                'thumbnail' => "http://img.com/$i.jpg",
                'attributes' => array_fill(0, 12, ['id' => 'ATTR', 'value_name' => 'Val']),
                'pictures' => array_fill(0, 6, ['url' => 'http://img.com']),
            ];
        }
        return $items;
    }

    // =========================================================================
    // spyProduct — no mlClient
    // =========================================================================

    public function testSpyProductWithoutMlClientReturnsError(): void
    {
        $spy = $this->buildSpy();
        $result = $spy->spyProduct('bagageiro cg 160');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, $result['analyzed']);
        $this->assertEmpty($result['top_sellers']);
    }

    public function testSpyProductWithEmptyApiResultsReturnsZeroAnalyzed(): void
    {
        $client = $this->makeClient(['/search' => ['results' => []]]);
        $spy = $this->buildSpy($client);

        $result = $spy->spyProduct('xyzproduct');

        $this->assertSame(0, $result['analyzed']);
        $this->assertIsArray($result['keywords_frequency']);
        $this->assertIsArray($result['price_analysis']);
    }

    // =========================================================================
    // spyProduct — with items
    // =========================================================================

    public function testSpyProductExtractsKeywordsFromTitles(): void
    {
        $items = $this->makeItems(3);
        $client = $this->makeClient(['/search' => ['results' => $items]]);
        $spy = $this->buildSpy($client);

        $result = $spy->spyProduct('bagageiro');

        $this->assertSame(3, $result['analyzed']);
        $this->assertNotEmpty($result['keywords_frequency']);
        // Common word 'bagageiro' should appear in all titles
        $this->assertArrayHasKey('bagageiro', $result['keywords_frequency']);
        $this->assertSame(3, $result['keywords_frequency']['bagageiro']);
    }

    public function testSpyProductCalculatesPriceAnalysis(): void
    {
        $items = $this->makeItems(3); // prices: 50, 100, 150

        $client = $this->makeClient(['/search' => ['results' => $items]]);
        $spy = $this->buildSpy($client);

        $result = $spy->spyProduct('bagageiro');

        $price = $result['price_analysis'];
        $this->assertSame(50.0, $price['min']);
        $this->assertSame(150.0, $price['max']);
        $this->assertEqualsWithDelta(100.0, $price['avg'], 0.01);
        $this->assertArrayHasKey('recommended_range', $price);
    }

    public function testSpyProductAnalyzesTitlePatterns(): void
    {
        $items = $this->makeItems(2);
        $client = $this->makeClient(['/search' => ['results' => $items]]);
        $spy = $this->buildSpy($client);

        $result = $spy->spyProduct('bagageiro');

        $this->assertArrayHasKey('title_patterns', $result);
        $this->assertArrayHasKey('avg_length', $result['title_patterns']);
        $this->assertGreaterThan(0, $result['title_patterns']['avg_length']);
    }

    public function testSpyProductAnalyzesShipping(): void
    {
        $items = $this->makeItems(4); // items 2 and 4 have free shipping

        $client = $this->makeClient(['/search' => ['results' => $items]]);
        $spy = $this->buildSpy($client);

        $result = $spy->spyProduct('bagageiro');

        $this->assertArrayHasKey('shipping_analysis', $result);
        $this->assertSame(50.0, $result['shipping_analysis']['free_shipping_percentage']);
    }

    public function testSpyProductFiltersOutMySellerId(): void
    {
        $items = $this->makeItems(3);
        // Seller IDs are: 10, 20, 30. My sellerId is 10 → filter out item 1
        $client = $this->makeClient(['/search' => ['results' => $items]], '10');
        $spy = $this->buildSpy($client);

        $result = $spy->spyProduct('bagageiro');

        $this->assertSame(2, $result['analyzed']);
        foreach ($result['top_sellers'] as $seller) {
            $this->assertNotSame(10, $seller['seller_id']);
        }
    }

    public function testSpyProductIdentifiesWinningStrategies(): void
    {
        $items = $this->makeItems(3);
        $client = $this->makeClient(['/search' => ['results' => $items]]);
        $spy = $this->buildSpy($client);

        $result = $spy->spyProduct('bagageiro');

        $this->assertIsArray($result['winning_strategies']);
        $this->assertNotEmpty($result['winning_strategies']);
    }

    public function testSpyProductKeywordsLimitedToThirty(): void
    {
        $items = $this->makeItems(10);
        $client = $this->makeClient(['/search' => ['results' => $items]]);
        $spy = $this->buildSpy($client);

        $result = $spy->spyProduct('bagageiro');

        $this->assertLessThanOrEqual(30, count($result['keywords_frequency']));
    }

    public function testSpyProductApiExceptionReturnsError(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('get')->will($this->throwException(new \Exception('API timeout')));
        $client->method('getSellerId')->willReturn(null);

        $spy = $this->buildSpy($client);
        $result = $spy->spyProduct('bagageiro');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('API timeout', $result['error']);
    }

    // =========================================================================
    // analyzePriceCompetitiveness
    // =========================================================================

    public function testAnalyzePriceCompetitivenessWithoutMlClientReturnsDefault(): void
    {
        $spy = $this->buildSpy();
        $result = $spy->analyzePriceCompetitiveness(['title' => 'Test', 'price' => 100.0]);

        $this->assertSame(50, $result['score']);
        $this->assertSame('unknown', $result['status']);
        $this->assertSame(100.0, $result['price']);
    }

    public function testAnalyzePriceCompetitivenessWithBestPrice(): void
    {
        $competitors = array_map(fn(int $p) => [
            'price' => (float)$p,
            'shipping' => ['free_shipping' => false],
        ], [100, 120, 130, 115, 105]);

        $client = $this->makeClient(['/search' => ['results' => $competitors]]);
        $spy = $this->buildSpy($client);

        // My price is below min competitor price
        $result = $spy->analyzePriceCompetitiveness([
            'title' => 'Bagageiro Lateral CG 160',
            'price' => 80.0,
            'shipping' => ['free_shipping' => false],
        ]);

        $this->assertSame(100, $result['score']);
        $this->assertSame('best_price', $result['status']);
    }

    public function testAnalyzePriceCompetitivenessWithCompetitivePrice(): void
    {
        $competitors = array_map(fn(int $p) => [
            'price' => (float)$p,
            'shipping' => ['free_shipping' => false],
        ], [100, 110, 115, 105, 108]);

        $client = $this->makeClient(['/search' => ['results' => $competitors]]);
        $spy = $this->buildSpy($client);

        // avg ≈ 107.6, my price = 105 (below avg)
        $result = $spy->analyzePriceCompetitiveness([
            'title' => 'Bagageiro CG',
            'price' => 105.0,
            'shipping' => ['free_shipping' => false],
        ]);

        $this->assertContains($result['status'], ['competitive', 'best_price']);
        $this->assertGreaterThanOrEqual(80, $result['score']);
    }

    public function testAnalyzePriceCompetitivenessExpensiveMarksIssues(): void
    {
        $competitors = array_map(fn(int $p) => [
            'price' => (float)$p,
            'shipping' => ['free_shipping' => false],
        ], [100, 100, 100, 100, 100]);

        $client = $this->makeClient(['/search' => ['results' => $competitors]]);
        $spy = $this->buildSpy($client);

        // avg = 100, my price = 130 (30% above avg)
        $result = $spy->analyzePriceCompetitiveness([
            'title' => 'Bagageiro CG',
            'price' => 130.0,
            'shipping' => ['free_shipping' => false],
        ]);

        $this->assertSame('expensive', $result['status']);
        $this->assertNotEmpty($result['issues']);
    }

    public function testAnalyzePriceCompetitivenessNoFreeShippingPenalty(): void
    {
        $competitors = array_map(fn(int $p) => [
            'price' => (float)$p,
            'shipping' => ['free_shipping' => true],  // All competitors have free shipping
        ], [100, 100, 100, 100, 100]);

        $client = $this->makeClient(['/search' => ['results' => $competitors]]);
        $spy = $this->buildSpy($client);

        $result = $spy->analyzePriceCompetitiveness([
            'title' => 'Bagageiro CG',
            'price' => 100.0,
            'shipping' => ['free_shipping' => false], // I don't have free shipping
        ]);

        // Score penalized for missing free shipping
        $this->assertLessThanOrEqual(85, $result['score']);
        $this->assertTrue(
            count(array_filter($result['issues'], fn(string $i): bool => str_contains($i, 'Frete'))) > 0,
            'Expected free shipping issue in result'
        );
    }

    public function testAnalyzePriceCompetitivenessEmptyCompetitorsReturnsDefault(): void
    {
        $client = $this->makeClient(['/search' => ['results' => []]]);
        $spy = $this->buildSpy($client);

        $result = $spy->analyzePriceCompetitiveness([
            'title' => 'Produto Raro',
            'price' => 100.0,
        ]);

        $this->assertSame(50, $result['score']);
        $this->assertSame('unknown', $result['status']);
    }

    // =========================================================================
    // compareWithCompetitors
    // =========================================================================

    public function testCompareWithCompetitorsWithoutMlClientReturnsError(): void
    {
        $spy = $this->buildSpy();
        $result = $spy->compareWithCompetitors('MLB12345');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('MLB12345', $result['item_id']);
    }

    public function testCompareWithCompetitorsItemNotFound(): void
    {
        $client = $this->makeClient([
            '/items/MLB12345' => [], // item not found returns empty
        ]);
        $spy = $this->buildSpy($client);

        $result = $spy->compareWithCompetitors('MLB12345');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('MLB12345', $result['item_id']);
    }

    public function testCompareWithCompetitorsSuccess(): void
    {
        $myItem = [
            'title' => 'Bagageiro Lateral CG 160 Honda Original',
            'price' => 100.0,
            'attributes' => array_fill(0, 8, ['id' => 'A', 'value_name' => 'v']),
            'pictures' => array_fill(0, 3, ['url' => 'img']),
            'shipping' => ['free_shipping' => false, 'mode' => 'me2'],
            'sold_quantity' => 50,
        ];

        $searchItems = $this->makeItems(3); // prices 50, 100, 150

        $client = $this->makeClient([
            '/items/MLB99' => $myItem,
            '/search' => ['results' => $searchItems],
        ]);

        $spy = $this->buildSpy($client);
        $result = $spy->compareWithCompetitors('MLB99');

        $this->assertSame('MLB99', $result['item_id']);
        $this->assertArrayHasKey('your_listing', $result);
        $this->assertArrayHasKey('comparison', $result);
        $this->assertArrayHasKey('gaps', $result);
        $this->assertArrayHasKey('recommendations', $result);
    }

    // =========================================================================
    // analyzeTopSellers
    // =========================================================================

    public function testAnalyzeTopSellersWithoutMlClientReturnsEmpty(): void
    {
        $spy = $this->buildSpy();
        $result = $spy->analyzeTopSellers('MLB1');

        $this->assertSame('MLB1', $result['category_id']);
        $this->assertEmpty($result['sellers']);
    }

    public function testAnalyzeTopSellersAggregatesSellerData(): void
    {
        $items = [
            [
                'id' => 'MLB1', 'title' => 'Produto A', 'price' => 100.0, 'sold_quantity' => 50,
                'seller' => ['id' => 42, 'nickname' => 'SellerA', 'seller_reputation' => ['level_id' => 5]],
            ],
            [
                'id' => 'MLB2', 'title' => 'Produto B', 'price' => 150.0, 'sold_quantity' => 30,
                'seller' => ['id' => 42, 'nickname' => 'SellerA', 'seller_reputation' => ['level_id' => 5]],
            ],
            [
                'id' => 'MLB3', 'title' => 'Produto C', 'price' => 80.0, 'sold_quantity' => 100,
                'seller' => ['id' => 99, 'nickname' => 'SellerB', 'seller_reputation' => ['level_id' => 3]],
            ],
        ];

        $client = $this->makeClient(['/search' => ['results' => $items]]);
        $spy = $this->buildSpy($client);

        $result = $spy->analyzeTopSellers('MLB1');

        $this->assertNotEmpty($result['sellers']);
        // Seller 42 has 2 items, Seller 99 has 1 item
        $seller42 = null;
        foreach ($result['sellers'] as $seller) {
            if ($seller['seller_id'] === 42) {
                $seller42 = $seller;
                break;
            }
        }
        $this->assertNotNull($seller42, 'Seller 42 should be in results');
        $this->assertSame(2, $seller42['item_count']);
        $this->assertSame(80, $seller42['total_sold']); // 50+30
        $this->assertEqualsWithDelta(125.0, $seller42['avg_price'], 0.01); // (100+150)/2
    }

    public function testAnalyzeTopSellersSortsByTotalSold(): void
    {
        $items = [
            ['id' => 'A1', 'title' => 'P1', 'price' => 100.0, 'sold_quantity' => 5,
             'seller' => ['id' => 1, 'nickname' => 'S1', 'seller_reputation' => []]],
            ['id' => 'B1', 'title' => 'P2', 'price' => 100.0, 'sold_quantity' => 100,
             'seller' => ['id' => 2, 'nickname' => 'S2', 'seller_reputation' => []]],
        ];

        $client = $this->makeClient(['/search' => ['results' => $items]]);
        $spy = $this->buildSpy($client);

        $result = $spy->analyzeTopSellers('MLB1');

        // Seller 2 (100 sold) should come first
        $this->assertSame(2, $result['sellers'][0]['seller_id']);
    }
}
