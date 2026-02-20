<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ListingBuilderService;
use App\Services\MercadoLivreClient;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

/**
 * @covers \App\Services\ListingBuilderService
 */
class ListingBuilderServiceTest extends TestCase
{
    private function createMockClient(array $getMap = [], array $postMap = []): MockObject
    {
        $client = $this->createMock(MercadoLivreClient::class);

        $client->method('get')->willReturnCallback(function (string $endpoint, array $query = []) use ($getMap) {
            foreach ($getMap as $pattern => $response) {
                if (str_contains($endpoint, $pattern)) {
                    return $response;
                }
            }
            return ['error' => 'not_mocked', 'endpoint' => $endpoint, 'query' => $query];
        });

        $client->method('post')->willReturnCallback(function (string $endpoint, array $payload = []) use ($postMap) {
            foreach ($postMap as $pattern => $response) {
                if (str_contains($endpoint, $pattern)) {
                    return $response;
                }
            }
            return ['error' => 'not_mocked', 'endpoint' => $endpoint, 'payload' => $payload];
        });

        return $client;
    }

    private function createMockDb(): MockObject
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $db = $this->createMock(PDO::class);
        $db->method('prepare')->willReturn($stmt);

        return $db;
    }

    public function testBuildListingCreatesValidPayload(): void
    {
        $service = new ListingBuilderService(skipDbAutoConnect: true);

        $result = $service->buildListing([
            'title' => 'Corrente 428h Honda',
            'price' => 99.9,
            'category_id' => 'MLB123',
            'quantity' => 3,
            'description' => 'Produto premium',
            'images' => ['https://img.example/1.jpg'],
            'attributes' => [['id' => 'BRAND', 'value_name' => 'Honda']],
        ]);

        $this->assertSame('Corrente 428h Honda', $result['title']);
        $this->assertSame(99.9, $result['price']);
        $this->assertSame('MLB123', $result['category_id']);
        $this->assertSame(3, $result['available_quantity']);
        $this->assertCount(1, $result['pictures']);
        $this->assertSame('https://img.example/1.jpg', $result['pictures'][0]['source']);
        $this->assertCount(1, $result['attributes']);
    }

    public function testBuildDescriptionWithoutAccountReturnsOriginalDescription(): void
    {
        $service = new ListingBuilderService(skipDbAutoConnect: true);

        $description = $service->buildDescription(['description' => 'Descricao manual']);

        $this->assertSame('Descricao manual', $description);
    }

    public function testBuildAttributesWithoutAccountReturnsOriginalAttributes(): void
    {
        $service = new ListingBuilderService(skipDbAutoConnect: true);
        $attrs = [['id' => 'BRAND', 'value_name' => 'Yamaha']];

        $result = $service->buildAttributes([
            'category_id' => 'MLB123',
            'attributes' => $attrs,
        ]);

        $this->assertSame($attrs, $result);
    }

    public function testDuplicateAndOptimizeRejectsEmptyItemId(): void
    {
        $service = new ListingBuilderService(skipDbAutoConnect: true);

        $result = $service->duplicateAndOptimize(' ');

        $this->assertFalse($result['success']);
        $this->assertSame('Item ID inválido', $result['error']);
    }

    public function testDuplicateAndOptimizeBuildsOptimizedListingFromApiData(): void
    {
        $client = $this->createMockClient([
            '/items/MLB1/description' => ['plain_text' => 'Descricao original'],
            '/items/MLB1' => [
                'id' => 'MLB1',
                'title' => 'Pastilha Freio',
                'price' => 120.5,
                'category_id' => 'MLB123',
                'available_quantity' => 4,
                'condition' => 'new',
                'listing_type_id' => 'gold_special',
                'attributes' => [['id' => 'BRAND', 'value_name' => 'Cobreq']],
                'pictures' => [
                    ['secure_url' => 'https://img.example/p1.jpg'],
                    ['url' => 'https://img.example/p2.jpg'],
                ],
            ],
        ]);

        $service = new ListingBuilderService(
            accountId: null,
            client: $client,
            db: null,
            skipDbAutoConnect: true
        );

        $result = $service->duplicateAndOptimize('MLB1');

        $this->assertTrue($result['success']);
        $this->assertSame('MLB1', $result['source_item_id']);
        $this->assertSame('Pastilha Freio', $result['listing']['title']);
        $this->assertCount(2, $result['listing']['pictures']);
        $this->assertSame('Descricao original', $result['listing']['description']['plain_text']);
    }

    public function testPublishListingReturnsErrorWhenMlClientUnavailable(): void
    {
        $service = new ListingBuilderService(skipDbAutoConnect: true);

        $reflection = new \ReflectionClass($service);
        $clientProp = $reflection->getProperty('client');
        $clientProp->setAccessible(true);
        $clientProp->setValue($service, null);

        $result = $service->publishListing(['title' => 'Teste']);

        $this->assertFalse($result['success']);
        $this->assertSame('Cliente Mercado Livre indisponível', $result['error']);
    }

    public function testPublishListingSuccessPersistsWhenDbAvailable(): void
    {
        $client = $this->createMockClient([], [
            '/items' => [
                'id' => 'MLB200',
                'title' => 'Kit Relacao',
                'category_id' => 'MLB321',
                'price' => 199.9,
                'available_quantity' => 2,
                'status' => 'active',
                'permalink' => 'https://mlb.example/item',
            ],
        ]);

        $service = new ListingBuilderService(
            accountId: 1,
            client: $client,
            db: $this->createMockDb(),
            skipDbAutoConnect: true
        );

        $result = $service->publishListing(['title' => 'Kit Relacao']);

        $this->assertTrue($result['success']);
        $this->assertSame('MLB200', $result['item_id']);
        $this->assertSame('https://mlb.example/item', $result['permalink']);
    }

    public function testPublishListingReturnsErrorWhenApiReturnsNoItemId(): void
    {
        $client = $this->createMockClient([], [
            '/items' => ['message' => 'unexpected'],
        ]);

        $service = new ListingBuilderService(
            accountId: 1,
            client: $client,
            db: null,
            skipDbAutoConnect: true
        );

        $result = $service->publishListing(['title' => 'Sem id']);

        $this->assertFalse($result['success']);
        $this->assertSame('API returned no item ID', $result['error']);
    }

    public function testSuggestCategoryReturnsErrorWhenMlClientUnavailable(): void
    {
        $service = new ListingBuilderService(skipDbAutoConnect: true);

        $reflection = new \ReflectionClass($service);
        $clientProp = $reflection->getProperty('client');
        $clientProp->setAccessible(true);
        $clientProp->setValue($service, null);

        $result = $service->suggestCategory('Pastilha');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Cliente Mercado Livre indisponível', $result['error']);
    }
}
