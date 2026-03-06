<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Financial;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use App\Services\Financial\SellerReputationService;
use App\Services\MercadoLivreClient;

/**
 * Testes DB-free para SellerReputationService
 *
 * @covers \App\Services\Financial\SellerReputationService
 *
 * Usa ReflectionClass::newInstanceWithoutConstructor() para evitar
 * a dependência de Database::getInstance() do trait HasFinancialDependencies.
 */
class SellerReputationServiceTest extends TestCase
{
    // ===========================
    // HELPERS
    // ===========================

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
                    return ['error' => 'not_found'];
                });
        }

        return $mock;
    }

    /**
     * Cria instância de SellerReputationService sem chamar o constructor
     * (evita Database::getInstance()) e injeta mock do ML client via reflection.
     */
    private function buildService(MercadoLivreClient $mockClient): SellerReputationService
    {
        $ref = new \ReflectionClass(SellerReputationService::class);
        /** @var SellerReputationService $service */
        $service = $ref->newInstanceWithoutConstructor();

        // Injetar mock client (propriedade private do trait)
        $clientProp = $ref->getProperty('client');
        $clientProp->setAccessible(true);
        $clientProp->setValue($service, $mockClient);

        return $service;
    }

    private function buildOrdersCapabilityUnavailableResponse(): array
    {
        return [
            'error' => 'orders_access_unavailable',
            'message' => 'The user does not have the orders capability.',
            'feature' => 'orders',
            'optional_feature' => true,
        ];
    }

    private function buildOrdersSuccessResponse(array $orders = [], int $total = 0): array
    {
        return [
            'results' => $orders,
            'paging' => [
                'total' => $total ?: count($orders),
                'offset' => 0,
                'limit' => 50,
            ],
        ];
    }

    private function buildSampleOrder(string $status = 'paid', float $amount = 100.0, string $buyerId = '999'): array
    {
        return [
            'id' => random_int(100000, 999999),
            'status' => $status,
            'total_amount' => $amount,
            'buyer' => ['id' => $buyerId],
            'date_created' => date('Y-m-d\TH:i:s.000-00:00'),
        ];
    }

    // ===========================
    // calculateConversionRate
    // ===========================

    public function testConversionRateReturnsFeatureUnavailableOnCapabilityError(): void
    {
        $mockClient = $this->createMockClient('123456', [
            '/users/' => ['total_visits' => 1000],
            '/orders/search' => $this->buildOrdersCapabilityUnavailableResponse(),
        ]);

        $service = $this->buildService($mockClient);
        $result = $service->calculateConversionRate('2024-01-01', '2024-01-31');

        $this->assertTrue($result['feature_unavailable']);
        $this->assertSame('orders_access_unavailable', $result['error']);
        $this->assertSame(0, $result['total_sales']);
        $this->assertSame(0, $result['conversion_rate']);
    }

    public function testConversionRateCalculatesCorrectlyWithValidOrders(): void
    {
        $mockClient = $this->createMockClient('123456', [
            '/users/' => ['total_visits' => 1000],
            '/orders/search' => $this->buildOrdersSuccessResponse([], 50),
        ]);

        $service = $this->buildService($mockClient);
        $result = $service->calculateConversionRate('2024-01-01', '2024-01-31');

        $this->assertArrayNotHasKey('feature_unavailable', $result);
        $this->assertSame(50, $result['total_sales']);
        $this->assertGreaterThan(0, $result['conversion_rate']);
    }

    public function testConversionRateDoesNotSetFeatureUnavailableOnGenericError(): void
    {
        $mockClient = $this->createMockClient('123456', [
            '/users/' => ['total_visits' => 500],
            '/orders/search' => ['error' => 'internal_error', 'message' => 'Something went wrong'],
        ]);

        $service = $this->buildService($mockClient);
        $result = $service->calculateConversionRate('2024-01-01', '2024-01-31');

        $this->assertArrayNotHasKey('feature_unavailable', $result);
        // Should still return a result with 0 sales (paging.total not set)
        $this->assertSame(0, $result['total_sales']);
    }

    // ===========================
    // generateSellerPerformanceReport
    // ===========================

    public function testPerformanceReportReturnsFeatureUnavailableOnCapabilityError(): void
    {
        // This method calls getSellerReputation(), getSellerVisitsByTimeWindow(),
        // calculateConversionRate(), and then /orders/search directly.
        // All API calls go through the same mock — /orders/search returns capability error.
        $mockClient = $this->createMockClient('123456', [
            '/users/' => [
                'nickname' => 'TestSeller',
                'seller_reputation' => [
                    'level_id' => '5_green',
                    'power_seller_status' => 'gold',
                    'transactions' => ['total' => 100, 'completed' => 95, 'canceled' => 5, 'ratings' => ['positive' => 0.95, 'neutral' => 0.03, 'negative' => 0.02]],
                    'metrics' => ['claims' => ['rate' => 0.01], 'cancellations' => ['rate' => 0.02], 'delayed_handling_time' => ['rate' => 0.01]],
                ],
            ],
            '/orders/search' => $this->buildOrdersCapabilityUnavailableResponse(),
        ]);

        $service = $this->buildService($mockClient);
        $result = $service->generateSellerPerformanceReport('2024-01-01', '2024-01-31');

        $this->assertTrue($result['feature_unavailable']);
        $this->assertSame('orders_access_unavailable', $result['error']);
        $this->assertNull($result['sales']);
        $this->assertNull($result['scores']);
    }

    // ===========================
    // calculateCustomerLTV
    // ===========================

    public function testCustomerLTVReturnsFeatureUnavailableOnCapabilityError(): void
    {
        $mockClient = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersCapabilityUnavailableResponse(),
        ]);

        $service = $this->buildService($mockClient);
        $result = $service->calculateCustomerLTV(12);

        $this->assertTrue($result['feature_unavailable']);
        $this->assertSame('orders_access_unavailable', $result['error']);
        $this->assertNull($result['metrics']);
        $this->assertNull($result['averages']);
        $this->assertNull($result['ltv']);
        $this->assertEmpty($result['insights']);
    }

    public function testCustomerLTVCalculatesMetricsWithValidOrders(): void
    {
        $orders = [
            $this->buildSampleOrder('paid', 150.0, 'buyer1'),
            $this->buildSampleOrder('paid', 200.0, 'buyer2'),
            $this->buildSampleOrder('paid', 100.0, 'buyer1'),
        ];

        $mockClient = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSuccessResponse($orders, 3),
        ]);

        $service = $this->buildService($mockClient);
        $result = $service->calculateCustomerLTV(12);

        $this->assertArrayNotHasKey('feature_unavailable', $result);
        $this->assertSame(12, $result['period_months']);
        $this->assertSame(450.0, $result['metrics']['total_revenue']);
        $this->assertSame(3, $result['metrics']['total_orders']);
        $this->assertSame(2, $result['metrics']['unique_customers']);
        $this->assertSame(1, $result['metrics']['repeat_customers']);
        $this->assertGreaterThan(0, $result['averages']['order_value']);
    }

    public function testCustomerLTVDoesNotSetFeatureUnavailableOnGenericError(): void
    {
        $mockClient = $this->createMockClient('123456', [
            '/orders/search' => ['error' => 'timeout', 'message' => 'Request timed out'],
        ]);

        $service = $this->buildService($mockClient);
        $result = $service->calculateCustomerLTV(6);

        $this->assertArrayNotHasKey('feature_unavailable', $result);
        // With no results or paging, should return zeros
        $this->assertSame(0, $result['metrics']['total_orders']);
    }

    // ===========================
    // isOrdersCapabilityUnavailable (indirect)
    // ===========================

    public function testCapabilityCheckRequiresAllThreeFields(): void
    {
        // Missing 'feature' field — should NOT trigger feature_unavailable
        $mockClient = $this->createMockClient('123456', [
            '/orders/search' => [
                'error' => 'orders_access_unavailable',
                'optional_feature' => true,
                // 'feature' => 'orders'  — missing
            ],
        ]);

        $service = $this->buildService($mockClient);
        $result = $service->calculateCustomerLTV(12);

        $this->assertArrayNotHasKey('feature_unavailable', $result);
    }
}
