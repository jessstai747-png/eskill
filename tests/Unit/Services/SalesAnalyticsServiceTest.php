<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use App\Services\SalesAnalyticsService;
use App\Services\MercadoLivreClient;
use App\Services\CacheService;

/**
 * Testes DB-free para SalesAnalyticsService
 *
 * @covers \App\Services\SalesAnalyticsService
 *
 * Usa mocks de MercadoLivreClient e CacheService para testar
 * getSalesData, getQuickSummary, capability error handling.
 */
class SalesAnalyticsServiceTest extends TestCase
{
    // ===========================
    // HELPERS
    // ===========================

    /**
     * Cria mock de MercadoLivreClient.
     *
     * @param array<string,mixed> $getReturnMap Mapa de endpoint → resposta
     * @return MercadoLivreClient&MockObject
     */
    private function createMockClient(
        ?string $sellerId = '123456',
        array $getReturnMap = []
    ): MercadoLivreClient {
        $mock = $this->createMock(MercadoLivreClient::class);

        $mock->method('getSellerId')
            ->willReturn($sellerId);

        if (!empty($getReturnMap)) {
            $mock->method('get')
                ->willReturnCallback(function (string $endpoint, array $params = []) use ($getReturnMap): array {
                    foreach ($getReturnMap as $pattern => $response) {
                        if (str_contains($endpoint, $pattern)) {
                            return $response;
                        }
                    }
                    return ['error' => 'not_found', 'message' => 'Mock: endpoint not configured'];
                });
        } else {
            $mock->method('get')
                ->willReturn(['error' => 'not_configured', 'message' => 'Mock: no return map']);
        }

        return $mock;
    }

    /**
     * Cria mock de CacheService que sempre retorna cache miss.
     */
    private function createMockCache(): CacheService
    {
        $mock = $this->createMock(CacheService::class);
        $mock->method('get')->willReturn(null);
        $mock->method('set')->willReturn(true);
        return $mock;
    }

    /**
     * Constrói SalesAnalyticsService com dependências injetadas.
     */
    private function buildService(
        ?MercadoLivreClient $client = null,
        ?CacheService $cache = null
    ): SalesAnalyticsService {
        return new SalesAnalyticsService(
            '1',
            $client,
            $cache ?? $this->createMockCache()
        );
    }

    /**
     * Retorna resposta de pedido ML de sucesso.
     */
    private function buildOrdersResponse(int $count = 3): array
    {
        $orders = [];
        for ($i = 0; $i < $count; $i++) {
            $orders[] = [
                'id' => 200000000 + $i,
                'status' => 'paid',
                'date_created' => date('Y-m-d\TH:i:s.000-00:00', strtotime('-' . $i . ' days')),
                'total_amount' => 150.00 + ($i * 10),
                'order_items' => [
                    [
                        'item' => [
                            'id' => 'MLB' . (1000 + $i),
                            'title' => 'Produto Teste ' . $i,
                            'category_id' => 'MLB1234',
                        ],
                        'quantity' => 1,
                        'sale_price' => 150.00 + ($i * 10),
                    ],
                ],
                'buyer' => ['id' => 'BUYER_' . $i],
            ];
        }

        return [
            'results' => $orders,
            'paging' => [
                'total' => $count,
                'offset' => 0,
                'limit' => 50,
            ],
        ];
    }

    /**
     * Retorna resposta semântica de orders_access_unavailable.
     */
    private function buildOrdersCapabilityUnavailableResponse(): array
    {
        return [
            'error' => 'orders_access_unavailable',
            'feature' => 'orders',
            'optional_feature' => true,
            'message' => 'A conta ou aplicação não possui permissão para consultar pedidos via API.',
            'status' => 403,
        ];
    }

    // ===========================
    // getSalesData — NORMAL FLOW
    // ===========================

    public function testGetSalesDataReturnsValidStructure(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersResponse(3),
        ]);

        $service = $this->buildService($client);
        $result = $service->getSalesData('7d');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_sales', $result);
        $this->assertArrayHasKey('total_orders', $result);
        $this->assertArrayHasKey('average_ticket', $result);
        $this->assertArrayHasKey('conversion_rate', $result);
        $this->assertArrayHasKey('sales_by_period', $result);
        $this->assertArrayHasKey('top_products', $result);
        $this->assertArrayHasKey('sales_by_category', $result);
        $this->assertArrayHasKey('date_from', $result);
        $this->assertArrayHasKey('date_to', $result);
        $this->assertArrayHasKey('currency', $result);
    }

    public function testGetSalesDataCalculatesMetricsFromOrders(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersResponse(3),
        ]);

        $service = $this->buildService($client);
        $result = $service->getSalesData('7d');

        // 3 orders: 150, 160, 170 = 480 total
        $this->assertEquals(3, $result['total_orders']);
        $this->assertEquals(480.0, $result['total_sales']);
        $this->assertEquals(160.0, $result['average_ticket']);
        $this->assertEquals('BRL', $result['currency']);
    }

    public function testGetSalesDataReturnsEmptyWhenNoOrders(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => ['results' => [], 'paging' => ['total' => 0]],
        ]);

        $service = $this->buildService($client);
        $result = $service->getSalesData('30d');

        $this->assertEquals(0, $result['total_orders']);
        $this->assertEquals(0.0, $result['total_sales']);
    }

    // ===========================
    // getSalesData — CAPABILITY ERROR
    // ===========================

    public function testGetSalesDataReturnsFeatureUnavailableOnCapabilityError(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersCapabilityUnavailableResponse(),
        ]);

        $service = $this->buildService($client);
        $result = $service->getSalesData('30d');

        $this->assertTrue($result['feature_unavailable']);
        $this->assertEquals('orders_access_unavailable', $result['error']);
        $this->assertEquals(0, $result['total_orders']);
        $this->assertEquals(0.0, $result['total_sales']);
    }

    public function testGetSalesDataDoesNotSetFeatureUnavailableOnGenericError(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => ['error' => 'internal_error', 'message' => 'Server error'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getSalesData('30d');

        // Generic errors do NOT set feature_unavailable — they just return empty structure
        $this->assertArrayNotHasKey('feature_unavailable', $result);
    }

    // ===========================
    // getQuickSummary — NORMAL FLOW
    // ===========================

    public function testGetQuickSummaryReturnsSuccessWithMetrics(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersResponse(2),
        ]);

        $service = $this->buildService($client);
        $result = $service->getQuickSummary();

        $this->assertTrue($result['success']);
        $this->assertEquals('7d', $result['period']);
        $this->assertArrayHasKey('metrics', $result);
        $this->assertArrayHasKey('total_sales', $result['metrics']);
        $this->assertArrayHasKey('total_orders', $result['metrics']);
    }

    // ===========================
    // getQuickSummary — CAPABILITY ERROR
    // ===========================

    public function testGetQuickSummaryReturnsFeatureUnavailableOnCapabilityError(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersCapabilityUnavailableResponse(),
        ]);

        $service = $this->buildService($client);
        $result = $service->getQuickSummary();

        $this->assertFalse($result['success']);
        $this->assertEquals('orders_access_unavailable', $result['error']);
        $this->assertTrue($result['feature_unavailable']);
    }

    public function testGetQuickSummaryReturnsGenericErrorOnException(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('getSellerId')->willReturn('123456');
        $client->method('get')->willThrowException(new \Exception('Connection timeout'));

        $service = $this->buildService($client);
        $result = $service->getQuickSummary();

        $this->assertFalse($result['success']);
        $this->assertEquals('Connection timeout', $result['error']);
        $this->assertArrayNotHasKey('feature_unavailable', $result);
    }

    // ===========================
    // getSellerId USAGE (bug fix verification)
    // ===========================

    public function testUsesSellIdNotGetUserId(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);

        // getSellerId MUST be called (not getUserId which doesn't exist)
        $client->expects($this->atLeastOnce())
            ->method('getSellerId')
            ->willReturn('SELLER_999');

        $client->method('get')
            ->willReturn($this->buildOrdersResponse(1));

        $service = $this->buildService($client);
        $service->getQuickSummary();

        // If getUserId() was called, the test would already fail with
        // "method does not exist" — getSellerId expectation confirms fix
    }
}
