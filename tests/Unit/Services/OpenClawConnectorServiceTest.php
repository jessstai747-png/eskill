<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ItemService;
use App\Services\OpenClawConnectorService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * @covers \App\Services\OpenClawConnectorService
 */
class OpenClawConnectorServiceTest extends TestCase
{
    private OpenClawConnectorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new ReflectionClass(OpenClawConnectorService::class);
        $this->service = $ref->newInstanceWithoutConstructor();
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionMethod(OpenClawConnectorService::class, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($this->service, ...$args);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildItemsPage(string $prefix, int $count, int $total, bool $hasMore): array
    {
        return [
            'success' => true,
            'source' => 'ml_api',
            'items' => array_map(static fn(int $n): array => ['id' => $prefix . $n], range(1, $count)),
            'total' => $total,
            'has_more' => $hasMore,
        ];
    }

    private function createItemServiceMockForPaginationBridge(): ItemService
    {
        $itemService = $this->createMock(ItemService::class);
        $calls = 0;
        $itemService->expects($this->exactly(3))
            ->method('listItems')
            ->willReturnCallback(function (array $filters) use (&$calls): array {
                $calls++;

                if ($calls === 1) {
                    $this->assertSame(50, $filters['limit']);
                    $this->assertSame(0, $filters['offset']);
                    return $this->buildItemsPage('MLB-A-', 50, 140, true);
                }

                if ($calls === 2) {
                    $this->assertSame(50, $filters['limit']);
                    $this->assertSame(50, $filters['offset']);
                    return $this->buildItemsPage('MLB-B-', 50, 140, true);
                }

                $this->assertSame(20, $filters['limit']);
                $this->assertSame(100, $filters['offset']);
                return $this->buildItemsPage('MLB-C-', 20, 140, true);
            });

        return $itemService;
    }

    public function testNormalizeConnectorItemFiltersDeveMapearCategoryIdEPerPage(): void
    {
        $result = $this->invoke('normalizeConnectorItemFilters', [
            'category_id' => 'MLB1234',
            'per_page' => 180,
            'page' => 2,
            'search' => 'bagageiro',
        ]);

        $this->assertSame('MLB1234', $result['category']);
        $this->assertSame(180, $result['per_page']);
        $this->assertSame(2, $result['page']);
        $this->assertSame('bagageiro', $result['search']);
    }

    public function testNormalizeConnectorOrderFiltersDeveConverterPerPageParaLimit(): void
    {
        $result = $this->invoke('normalizeConnectorOrderFilters', [
            'per_page' => 200,
            'page' => 3,
            'status' => 'paid',
            'sort' => 'date_created',
            'order' => 'DESC',
        ]);

        $this->assertSame(200, $result['limit']);
        $this->assertSame(3, $result['page']);
        $this->assertSame('paid', $result['status']);
        $this->assertSame('date_created', $result['sort']);
        $this->assertSame('DESC', $result['order']);
    }

    public function testListItemsWithMlPaginationBridgeDeveAgregarMaisDe50Itens(): void
    {
        $itemService = $this->createItemServiceMockForPaginationBridge();

        $result = $this->invoke(
            'listItemsWithMlPaginationBridge',
            $itemService,
            ['status' => 'active'],
            1,
            120
        );

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(120, $result['limit']);
        $this->assertSame(140, $result['total']);
        $this->assertTrue($result['has_more']);
        $this->assertCount(120, $result['items']);
        $this->assertCount(120, $result['results']);
    }

    public function testListItemsWithMlPaginationBridgeDeveRetornarErroDaPrimeiraChamada(): void
    {
        $itemService = $this->createMock(ItemService::class);
        $itemService->expects($this->once())
            ->method('listItems')
            ->willReturn([
                'success' => false,
                'error' => 'ml_api_error',
                'message' => 'Falha na API',
            ]);

        $result = $this->invoke(
            'listItemsWithMlPaginationBridge',
            $itemService,
            ['status' => 'active'],
            1,
            100
        );

        $this->assertFalse($result['success']);
        $this->assertSame('ml_api_error', $result['error']);
    }
}
