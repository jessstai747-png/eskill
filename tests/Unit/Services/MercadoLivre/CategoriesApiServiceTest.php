<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MercadoLivre;

use App\Services\MercadoLivre\CategoriesApiException;
use App\Services\MercadoLivre\CategoriesApiGatewayInterface;
use App\Services\MercadoLivre\CategoriesApiService;
use PHPUnit\Framework\TestCase;

class CategoriesApiServiceTest extends TestCase
{
    public function testListSiteCategoriesReturnsTypedDtos(): void
    {
        $gateway = new InMemoryCategoriesGateway([
            [
                ['id' => 'MLB123', 'name' => 'Categoria A'],
                ['id' => 'MLB456', 'name' => 'Categoria B'],
            ],
        ]);

        $service = new CategoriesApiService($gateway, static fn(int $delay): null => null);
        $categories = $service->listSiteCategories('MLB');

        $this->assertCount(2, $categories);
        $this->assertSame('MLB123', $categories[0]->id);
        $this->assertSame('Categoria A', $categories[0]->name);
        $this->assertSame(1, $gateway->calls);
    }

    public function testGetCategoryReturnsTypedDetail(): void
    {
        $gateway = new InMemoryCategoriesGateway([
            [
                'id' => 'MLB123',
                'name' => 'Categoria A',
                'picture' => 'https://img.example/cat.png',
                'permalink' => 'https://www.mercadolivre.com.br/c/categoria-a',
                'total_items_in_this_category' => 100,
                'path_from_root' => [
                    ['id' => 'MLB123', 'name' => 'Categoria A'],
                ],
                'children_categories' => [
                    ['id' => 'MLB124', 'name' => 'Subcat', 'total_items_in_this_category' => 10],
                ],
            ],
        ]);

        $service = new CategoriesApiService($gateway, static fn(int $delay): null => null);
        $detail = $service->getCategory('MLB123');

        $this->assertSame('MLB123', $detail->id);
        $this->assertSame('Categoria A', $detail->name);
        $this->assertSame(100, $detail->totalItemsInThisCategory);
        $this->assertCount(1, $detail->pathFromRoot);
        $this->assertCount(1, $detail->childrenCategories);
    }

    public function testRetriesWithExponentialBackoffForTransientErrors(): void
    {
        $gateway = new InMemoryCategoriesGateway([
            ['error' => 'connection_error', 'message' => 'timeout', 'status' => 0],
            ['error' => 'internal_error', 'message' => 'service unavailable', 'status' => 503],
            [
                ['id' => 'MLB111', 'name' => 'Ok'],
            ],
        ]);

        $delays = [];
        $service = new CategoriesApiService(
            $gateway,
            static function (int $delay) use (&$delays): void {
                $delays[] = $delay;
            },
            3,
            100,
            1000
        );

        $result = $service->listSiteCategories('MLB');

        $this->assertCount(1, $result);
        $this->assertSame(3, $gateway->calls);
        $this->assertSame([100, 200], $delays);
    }

    public function testThrowsOnNonRetryableError(): void
    {
        $gateway = new InMemoryCategoriesGateway([
            ['error' => 'bad_request', 'message' => 'site_id inválido', 'status' => 400],
        ]);

        $service = new CategoriesApiService($gateway, static fn(int $delay): null => null, 3, 100, 500);

        try {
            $service->listSiteCategories('MLB');
            $this->fail('Era esperada CategoriesApiException');
        } catch (CategoriesApiException $e) {
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame('bad_request', $e->getApiErrorCode());
            $this->assertSame(1, $gateway->calls);
        }
    }

    public function testThrowsAfterRetryExhaustion(): void
    {
        $gateway = new InMemoryCategoriesGateway([
            ['error' => 'connection_error', 'message' => 'timeout', 'status' => 0],
            ['error' => 'connection_error', 'message' => 'timeout', 'status' => 0],
            ['error' => 'connection_error', 'message' => 'timeout', 'status' => 0],
        ]);

        $delays = [];
        $service = new CategoriesApiService(
            $gateway,
            static function (int $delay) use (&$delays): void {
                $delays[] = $delay;
            },
            3,
            50,
            1000
        );

        $this->expectException(CategoriesApiException::class);

        try {
            $service->listSiteCategories('MLB');
        } finally {
            $this->assertSame(3, $gateway->calls);
            $this->assertSame([50, 100], $delays);
        }
    }
}

final class InMemoryCategoriesGateway implements CategoriesApiGatewayInterface
{
    /** @var array<int, array> */
    private array $responses;
    public int $calls = 0;

    public function __construct(array $responses)
    {
        $this->responses = array_values($responses);
    }

    public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array
    {
        $this->calls++;
        $index = $this->calls - 1;

        if (!isset($this->responses[$index])) {
            return ['error' => 'test_no_response', 'message' => 'Sem resposta mockada', 'status' => 500];
        }

        return $this->responses[$index];
    }
}
