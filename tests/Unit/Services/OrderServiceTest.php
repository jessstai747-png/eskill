<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use App\Services\OrderService;
use App\Services\MercadoLivreClient;
use PDO;
use PDOStatement;

/**
 * Testes DB-free para OrderService
 *
 * @covers \App\Services\OrderService
 *
 * Usa mocks de MercadoLivreClient e PDO para testar
 * listOrders, getOrder, syncOrders, filtros, paginação, fallback.
 */
class OrderServiceTest extends TestCase
{
    // ===========================
    // HELPERS
    // ===========================

    /**
     * Cria mock de MercadoLivreClient que retorna seller ID e respostas configuráveis.
     *
     * @param array<string,mixed> $getReturnMap Mapa de endpoint → resposta
     */
    private function createMockClient(
        ?string $sellerId = '123456',
        array $getReturnMap = []
    ): MockObject {
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
     * Cria um OrderService com client e DB mockados.
     * Passa skipDbAutoConnect=true quando $db é null para evitar Database::getInstance().
     */
    private function buildService(
        ?int $accountId = 1,
        ?MockObject $client = null,
        ?PDO $db = null
    ): OrderService {
        return new OrderService(
            $accountId,
            $client,
            $db,
            skipDbAutoConnect: true
        );
    }

    /**
     * Retorna uma resposta ML de sucesso com lista de pedidos.
     *
     * @param list<array<string,mixed>> $orders
     */
    private function buildOrdersSearchResponse(array $orders, int $total = 0): array
    {
        if ($total === 0) {
            $total = count($orders);
        }
        return [
            'results' => $orders,
            'paging' => [
                'total' => $total,
                'offset' => 0,
                'limit' => 50,
            ],
        ];
    }

    /**
     * Retorna dados de pedido fake típico do ML.
     */
    private function buildSampleOrder(int $id = 9000000001, string $status = 'paid', float $amount = 199.90): array
    {
        return [
            'id' => $id,
            'status' => $status,
            'total_amount' => $amount,
            'date_created' => '2026-02-19T10:30:00.000-03:00',
            'buyer' => [
                'id' => 800000001,
                'nickname' => 'COMPRADOR_TESTE',
                'first_name' => 'João',
                'last_name' => 'Silva',
            ],
            'order_items' => [
                [
                    'item' => [
                        'id' => 'MLB1234567',
                        'title' => 'Bagageiro CG 160 Titan Reforçado',
                    ],
                    'quantity' => 1,
                    'unit_price' => $amount,
                ],
            ],
            'shipping' => [
                'id' => 40000000001,
                'status' => 'ready_to_ship',
            ],
            'payments' => [
                [
                    'id' => 60000001,
                    'status' => 'approved',
                    'total_paid_amount' => $amount,
                ],
            ],
        ];
    }

    /**
     * Cria mock PDO com um PDOStatement para INSERT (saveOrder).
     * O statement retorna true no execute() sem realmente inserir nada.
     */
    private function createMockDbForSave(): PDO
    {
        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->method('execute')->willReturn(true);

        $nickStmt = $this->createMock(PDOStatement::class);
        $nickStmt->method('execute')->willReturn(true);
        $nickStmt->method('fetch')->willReturn(['nickname' => 'AWA_MOTOS']);

        $userStmt = $this->createMock(PDOStatement::class);
        $userStmt->method('execute')->willReturn(true);
        $userStmt->method('fetchColumn')->willReturn(1);

        $db = $this->createMock(PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($insertStmt, $nickStmt, $userStmt) {
                if (str_contains($sql, 'INSERT INTO ml_orders')) {
                    return $insertStmt;
                }
                if (str_contains($sql, 'nickname FROM ml_accounts')) {
                    return $nickStmt;
                }
                if (str_contains($sql, 'user_id FROM ml_accounts')) {
                    return $userStmt;
                }
                // Default: return a generic stmt
                $generic = $this->createMock(PDOStatement::class);
                $generic->method('execute')->willReturn(true);
                $generic->method('fetchColumn')->willReturn(0);
                $generic->method('fetchAll')->willReturn([]);
                return $generic;
            });

        return $db;
    }

    /**
     * Cria mock PDO para leitura de pedidos locais (fallback).
     *
     * @param list<array<string,mixed>> $rows
     */
    private function createMockDbForFallback(array $rows = [], int $totalCount = 0): PDO
    {
        if ($totalCount === 0) {
            $totalCount = count($rows);
        }

        $countStmt = $this->createMock(PDOStatement::class);
        $countStmt->method('execute')->willReturn(true);
        $countStmt->method('fetchColumn')->willReturn($totalCount);

        $selectStmt = $this->createMock(PDOStatement::class);
        $selectStmt->method('execute')->willReturn(true);
        $selectStmt->method('fetchAll')->willReturn($rows);

        $nickStmt = $this->createMock(PDOStatement::class);
        $nickStmt->method('execute')->willReturn(true);
        $nickStmt->method('fetch')->willReturn(['nickname' => 'AWA_MOTOS']);

        $singleStmt = $this->createMock(PDOStatement::class);
        $singleStmt->method('execute')->willReturn(true);
        if (!empty($rows)) {
            $singleStmt->method('fetch')->willReturn($rows[0]);
        } else {
            $singleStmt->method('fetch')->willReturn(false);
        }

        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->method('execute')->willReturn(true);

        $userStmt = $this->createMock(PDOStatement::class);
        $userStmt->method('execute')->willReturn(true);
        $userStmt->method('fetchColumn')->willReturn(1);

        $callIndex = 0;
        $db = $this->createMock(PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use (
                $countStmt,
                $selectStmt,
                $nickStmt,
                $singleStmt,
                $insertStmt,
                $userStmt,
                &$callIndex
            ) {
                if (str_contains($sql, 'SELECT COUNT(*)')) {
                    return $countStmt;
                }
                if (str_contains($sql, 'SELECT') && str_contains($sql, 'FROM ml_orders') && !str_contains($sql, 'COUNT')) {
                    if (str_contains($sql, 'LIMIT') && !str_contains($sql, 'OFFSET')) {
                        return $singleStmt;
                    }
                    return $selectStmt;
                }
                if (str_contains($sql, 'INSERT INTO ml_orders')) {
                    return $insertStmt;
                }
                if (str_contains($sql, 'nickname FROM ml_accounts')) {
                    return $nickStmt;
                }
                if (str_contains($sql, 'user_id FROM ml_accounts')) {
                    return $userStmt;
                }
                // For single-row select (getOrderFromDatabase uses LIMIT 1)
                if (str_contains($sql, 'ml_order_id = :order_id')) {
                    return $singleStmt;
                }

                $generic = $this->createMock(PDOStatement::class);
                $generic->method('execute')->willReturn(true);
                $generic->method('fetchColumn')->willReturn(0);
                $generic->method('fetchAll')->willReturn([]);
                $generic->method('fetch')->willReturn(false);
                return $generic;
            });

        return $db;
    }

    // ===========================
    // CLASS STRUCTURE
    // ===========================

    public function testOrderServiceClassExists(): void
    {
        $this->assertTrue(class_exists(OrderService::class));
    }

    public function testOrderServiceHasRequiredMethods(): void
    {
        $methods = ['listOrders', 'getOrder', 'syncOrders'];
        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(OrderService::class, $method),
                "OrderService deve ter método {$method}()"
            );
        }
    }

    // ===========================
    // CONSTRUCTOR & DI
    // ===========================

    public function testConstructorAcceptsInjectedDependencies(): void
    {
        $client = $this->createMockClient();
        $service = $this->buildService(99, $client);
        $this->assertInstanceOf(OrderService::class, $service);
    }

    public function testConstructorAcceptsNullAccountId(): void
    {
        $client = $this->createMockClient();
        $service = $this->buildService(null, $client);
        $this->assertInstanceOf(OrderService::class, $service);
    }

    public function testConstructorAcceptsDbMock(): void
    {
        $client = $this->createMockClient();
        $db = $this->createMockDbForSave();
        $service = new OrderService(1, $client, $db);
        $this->assertInstanceOf(OrderService::class, $service);
    }

    // ===========================
    // listOrders — API SUCCESS
    // ===========================

    public function testListOrdersReturnsResultsFromApi(): void
    {
        $order = $this->buildSampleOrder();
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse([$order]),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders();

        $this->assertTrue($result['success']);
        $this->assertSame('ml_api', $result['source']);
        $this->assertCount(1, $result['orders']);
        $this->assertSame(9000000001, $result['orders'][0]['id']);
        $this->assertSame('paid', $result['orders'][0]['status']);
        $this->assertSame(199.90, $result['orders'][0]['total_amount']);
    }

    public function testListOrdersReturnsMultipleOrders(): void
    {
        $orders = [
            $this->buildSampleOrder(1001, 'paid', 99.90),
            $this->buildSampleOrder(1002, 'shipped', 250.00),
            $this->buildSampleOrder(1003, 'cancelled', 50.00),
        ];

        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse($orders),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders();

        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['orders']);
    }

    public function testListOrdersReturnsEmptyWhenNoOrders(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse([]),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders();

        $this->assertTrue($result['success']);
        $this->assertEmpty($result['orders']);
        $this->assertSame(0, $result['total']);
    }

    public function testListOrdersPaginationMetadata(): void
    {
        $orders = [$this->buildSampleOrder()];
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse($orders, 100),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders(['limit' => 10, 'page' => 2]);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['page']);
        $this->assertSame(10, $result['limit']);
        $this->assertSame(100, $result['total']);
        $this->assertSame(10, $result['pages']); // ceil(100/10)
        $this->assertTrue($result['has_more']);
    }

    public function testListOrdersMaxLimitCappedAt200(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse([]),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders(['limit' => 9999]);

        $this->assertSame(200, $result['limit']);
    }

    public function testListOrdersMinLimitIsOne(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse([]),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders(['limit' => -5]);

        $this->assertSame(1, $result['limit']);
    }

    public function testListOrdersPageDefaultsToOne(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse([]),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders();

        $this->assertSame(1, $result['page']);
    }

    // ===========================
    // listOrders — SORT
    // ===========================

    public function testListOrdersSortsByDateDescByDefault(): void
    {
        $orders = [
            $this->buildSampleOrder(1001, 'paid', 100.0),
            $this->buildSampleOrder(1002, 'paid', 200.0),
        ];
        // Modify dates so 1002 is newer
        $orders[0]['date_created'] = '2026-02-18T10:00:00.000-03:00';
        $orders[1]['date_created'] = '2026-02-19T10:00:00.000-03:00';

        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse($orders),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders();

        $this->assertTrue($result['success']);
        // Default sort is date_created DESC, so 1002 should be first
        $this->assertSame(1002, $result['orders'][0]['id']);
    }

    public function testListOrdersRejectsInvalidSortField(): void
    {
        $orders = [$this->buildSampleOrder()];
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse($orders),
        ]);

        $service = $this->buildService(1, $client);
        // SQL injection attempt as sort field — should fallback to date_created
        $result = $service->listOrders(['sort' => 'DROP TABLE orders;--']);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['orders']);
    }

    public function testListOrdersSortAscending(): void
    {
        $orders = [
            $this->buildSampleOrder(1001, 'paid', 100.0),
            $this->buildSampleOrder(1002, 'paid', 200.0),
        ];
        $orders[0]['date_created'] = '2026-02-18T10:00:00.000-03:00';
        $orders[1]['date_created'] = '2026-02-19T10:00:00.000-03:00';

        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse($orders),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders(['sort' => 'date_created', 'order' => 'ASC']);

        $this->assertSame(1001, $result['orders'][0]['id']);
    }

    // ===========================
    // listOrders — SEARCH FILTER
    // ===========================

    public function testListOrdersSearchByBuyerNickname(): void
    {
        $orders = [
            $this->buildSampleOrder(1001, 'paid', 100.0),
            $this->buildSampleOrder(1002, 'paid', 200.0),
        ];
        $orders[0]['buyer']['nickname'] = 'COMPRADOR_JOAO';
        $orders[1]['buyer']['nickname'] = 'COMPRADOR_MARIA';

        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse($orders),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders(['search' => 'MARIA']);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['orders']);
        $this->assertSame(1002, $result['orders'][0]['id']);
    }

    public function testListOrdersSearchByOrderId(): void
    {
        $orders = [
            $this->buildSampleOrder(999111, 'paid', 150.0),
            $this->buildSampleOrder(888222, 'paid', 250.0),
        ];

        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse($orders),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders(['search' => '999111']);

        $this->assertCount(1, $result['orders']);
        $this->assertSame(999111, $result['orders'][0]['id']);
    }

    // ===========================
    // listOrders — MISSING SELLER ID
    // ===========================

    public function testListOrdersMissingSellerIdReturnsError(): void
    {
        $client = $this->createMockClient(null); // keine seller ID
        $service = $this->buildService(1, $client);
        $result = $service->listOrders();

        $this->assertFalse($result['success']);
        $this->assertSame('missing_seller_id', $result['error']);
    }

    // ===========================
    // listOrders — API ERROR
    // ===========================

    public function testListOrdersApiErrorReturnsFail(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => [
                'error' => 'forbidden',
                'message' => 'Access denied',
                'status' => 403,
            ],
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders();

        $this->assertFalse($result['success']);
        $this->assertSame('ml_api_error', $result['error']);
        $this->assertStringContainsString('Access denied', $result['message']);
    }

    public function testListOrdersApiErrorWithLocalFallback(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => ['error' => 'server_error', 'message' => 'Internal'],
        ]);

        $dbRows = [
            [
                'id' => 1,
                'ml_order_id' => '990001',
                'ml_account_id' => 1,
                'order_data' => json_encode(['buyer' => ['nickname' => 'TEST']]),
                'status' => 'paid',
                'total_amount' => 150.00,
                'date_created' => '2026-02-19 10:00:00',
                'synced_at' => '2026-02-19 10:05:00',
            ],
        ];
        $db = $this->createMockDbForFallback($dbRows, 1);

        $service = new OrderService(1, $client, $db);

        // Enable local cache fallback
        $_ENV['ML_ALLOW_LOCAL_CACHE_FALLBACK'] = 'true';
        $_ENV['APP_ENV'] = 'development';

        try {
            $result = $service->listOrders(['allow_local_cache' => 'true']);

            $this->assertTrue($result['success']);
            $this->assertSame('local', $result['source']);
            $this->assertArrayHasKey('warning', $result);
        } finally {
            unset($_ENV['ML_ALLOW_LOCAL_CACHE_FALLBACK']);
            unset($_ENV['APP_ENV']);
        }
    }

    // ===========================
    // listOrders — SAVES TO DB
    // ===========================

    public function testListOrdersSavesOrdersToDbCache(): void
    {
        $order = $this->buildSampleOrder();

        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->expects($this->atLeastOnce())
            ->method('execute')
            ->willReturn(true);

        $nickStmt = $this->createMock(PDOStatement::class);
        $nickStmt->method('execute')->willReturn(true);
        $nickStmt->method('fetch')->willReturn(['nickname' => 'AWA']);

        $userStmt = $this->createMock(PDOStatement::class);
        $userStmt->method('execute')->willReturn(true);
        $userStmt->method('fetchColumn')->willReturn(1);

        $db = $this->createMock(PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($insertStmt, $nickStmt, $userStmt) {
                if (str_contains($sql, 'INSERT INTO ml_orders')) {
                    return $insertStmt;
                }
                if (str_contains($sql, 'nickname')) {
                    return $nickStmt;
                }
                if (str_contains($sql, 'user_id')) {
                    return $userStmt;
                }
                $g = $this->createMock(PDOStatement::class);
                $g->method('execute')->willReturn(true);
                return $g;
            });

        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse([$order]),
        ]);

        $service = new OrderService(1, $client, $db);
        $result = $service->listOrders();

        $this->assertTrue($result['success']);
    }

    // ===========================
    // listOrders — NORMALIZES ORDER DATA
    // ===========================

    public function testListOrdersNormalizesOrderSummary(): void
    {
        $order = $this->buildSampleOrder(8001, 'paid', 350.00);
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse([$order]),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders();

        $normalized = $result['orders'][0];
        $this->assertSame(8001, $normalized['id']);
        $this->assertSame('paid', $normalized['status']);
        $this->assertSame(350.00, $normalized['total_amount']);
        $this->assertArrayHasKey('buyer', $normalized);
        $this->assertArrayHasKey('order_items', $normalized);
        $this->assertArrayHasKey('shipping', $normalized);
        $this->assertArrayHasKey('payments', $normalized);
    }

    public function testListOrdersHandlesNonArrayOrderItems(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => [
                'results' => ['not_an_array', null, 42],
                'paging' => ['total' => 3],
            ],
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders();

        $this->assertTrue($result['success']);
        $this->assertEmpty($result['orders']); // non-array items skipped
    }

    // ===========================
    // getOrder — SUCCESS
    // ===========================

    public function testGetOrderReturnsFromApi(): void
    {
        $order = $this->buildSampleOrder(7777);
        $client = $this->createMockClient('123456', [
            '/orders/7777' => $order,
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->getOrder('7777');

        $this->assertTrue($result['success']);
        $this->assertSame('ml_api', $result['source']);
        $this->assertSame(7777, $result['id']);
        $this->assertSame('paid', $result['status']);
        $this->assertSame(199.90, $result['total_amount']);
        $this->assertArrayHasKey('data', $result);
    }

    public function testGetOrderCachesResultToDb(): void
    {
        $order = $this->buildSampleOrder(5555);

        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $userStmt = $this->createMock(PDOStatement::class);
        $userStmt->method('execute')->willReturn(true);
        $userStmt->method('fetchColumn')->willReturn(1);

        $db = $this->createMock(PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($insertStmt, $userStmt) {
                if (str_contains($sql, 'INSERT INTO ml_orders')) {
                    return $insertStmt;
                }
                if (str_contains($sql, 'user_id FROM ml_accounts')) {
                    return $userStmt;
                }
                $g = $this->createMock(PDOStatement::class);
                $g->method('execute')->willReturn(true);
                return $g;
            });

        $client = $this->createMockClient('123456', [
            '/orders/5555' => $order,
        ]);

        $service = new OrderService(1, $client, $db);
        $result = $service->getOrder('5555');

        $this->assertTrue($result['success']);
    }

    // ===========================
    // getOrder — ERRORS
    // ===========================

    public function testGetOrderApiErrorReturnsFailure(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/9999' => ['error' => 'not_found', 'message' => 'Order not found'],
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->getOrder('9999');

        $this->assertFalse($result['success']);
        $this->assertSame('not_found', $result['error']);
        $this->assertStringContainsString('Pedido não encontrado', $result['message']);
    }

    public function testGetOrderApiErrorFallsBackToDb(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/3333' => ['error' => 'internal', 'message' => 'Server Error'],
        ]);

        $dbRow = [
            'ml_order_id' => '3333',
            'ml_account_id' => 1,
            'order_data' => json_encode(['buyer' => ['nickname' => 'CACHED']]),
            'status' => 'paid',
            'total_amount' => 199.90,
            'date_created' => '2026-02-19 08:00:00',
            'synced_at' => '2026-02-19 08:05:00',
        ];

        $singleStmt = $this->createMock(PDOStatement::class);
        $singleStmt->method('execute')->willReturn(true);
        $singleStmt->method('fetch')->willReturn($dbRow);

        $db = $this->createMock(PDO::class);
        $db->method('prepare')
            ->willReturn($singleStmt);

        $service = new OrderService(1, $client, $db);
        $result = $service->getOrder('3333', ['allow_local_cache' => true]);

        $this->assertTrue($result['success']);
        $this->assertSame('local', $result['source']);
        $this->assertSame('3333', $result['id']);
        $this->assertArrayHasKey('warning', $result);
    }

    public function testGetOrderApiErrorNoFallbackReturnsNotFound(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/4444' => ['error' => 'not_found', 'message' => 'Not found'],
        ]);

        // No DB, no fallback
        $service = $this->buildService(1, $client);
        $result = $service->getOrder('4444');

        $this->assertFalse($result['success']);
    }

    // ===========================
    // syncOrders — SUCCESS
    // ===========================

    public function testSyncOrdersSuccess(): void
    {
        $orders = [
            $this->buildSampleOrder(2001, 'paid', 100.0),
            $this->buildSampleOrder(2002, 'shipped', 200.0),
        ];

        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse($orders, 2),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->syncOrders();

        $this->assertTrue($result['success']);
        // Without DB, saveOrder returns early, but synced count still increases
        $this->assertSame(2, $result['synced']);
        $this->assertSame(2, $result['total']);
        $this->assertEmpty($result['errors']);
    }

    public function testSyncOrdersLimitCappedAt50(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse([], 0),
        ]);

        $client->expects($this->once())
            ->method('get')
            ->with(
                '/orders/search',
                $this->callback(function (array $params): bool {
                    return $params['limit'] <= 50;
                })
            )
            ->willReturn($this->buildOrdersSearchResponse([], 0));

        $service = $this->buildService(1, $client);
        $result = $service->syncOrders(null, 999);

        $this->assertTrue($result['success']);
    }

    // ===========================
    // syncOrders — ERRORS
    // ===========================

    public function testSyncOrdersNoSellerIdThrowsException(): void
    {
        $client = $this->createMockClient(null);
        $service = $this->buildService(1, $client);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Seller ID não encontrado');
        $service->syncOrders();
    }

    public function testSyncOrdersApiErrorThrowsException(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => ['error' => 'forbidden', 'message' => 'Token expirado'],
        ]);

        $service = $this->buildService(1, $client);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Token expirado');
        $service->syncOrders();
    }

    public function testSyncOrdersPartialFailureReportsErrors(): void
    {
        $orders = [
            $this->buildSampleOrder(3001, 'paid', 100.0),
            $this->buildSampleOrder(3002, 'paid', 200.0),
        ];

        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse($orders, 2),
        ]);

        // DB that fails on save for the second order
        $callCount = 0;
        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->method('execute')
            ->willReturnCallback(function () use (&$callCount): bool {
                $callCount++;
                if ($callCount === 2) {
                    throw new \RuntimeException('DB write failed');
                }
                return true;
            });

        $userStmt = $this->createMock(PDOStatement::class);
        $userStmt->method('execute')->willReturn(true);
        $userStmt->method('fetchColumn')->willReturn(1);

        $db = $this->createMock(PDO::class);
        $db->method('prepare')
            ->willReturnCallback(function (string $sql) use ($insertStmt, $userStmt) {
                if (str_contains($sql, 'INSERT INTO ml_orders')) {
                    return $insertStmt;
                }
                if (str_contains($sql, 'user_id FROM ml_accounts')) {
                    return $userStmt;
                }
                $g = $this->createMock(PDOStatement::class);
                $g->method('execute')->willReturn(true);
                return $g;
            });

        $service = new OrderService(1, $client, $db);
        $result = $service->syncOrders();

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['synced']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame(3002, $result['errors'][0]['order_id']);
    }

    // ===========================
    // API-ONLY MODE (no DB)
    // ===========================

    public function testServiceOperatesWithoutDb(): void
    {
        $order = $this->buildSampleOrder();
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse([$order]),
        ]);

        $service = $this->buildService(1, $client); // no db, skipDbAutoConnect=true
        $result = $service->listOrders();

        $this->assertTrue($result['success']);
        $this->assertSame('ml_api', $result['source']);
        $this->assertCount(1, $result['orders']);
    }

    public function testGetOrderWithoutDbSkipsSaveGracefully(): void
    {
        $order = $this->buildSampleOrder(6001);
        $client = $this->createMockClient('123456', [
            '/orders/6001' => $order,
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->getOrder('6001');

        $this->assertTrue($result['success']);
        $this->assertSame(6001, $result['id']);
    }

    // ===========================
    // SECURITY TESTS
    // ===========================

    public function testUsesPreparedStatements(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/OrderService.php'
        );

        $this->assertStringContainsString('->prepare(', $source);
        $this->assertStringContainsString('->execute(', $source);
    }

    public function testValidatesSortField(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/OrderService.php'
        );

        $this->assertStringContainsString('allowedSortFields', $source);
    }

    public function testSearchUsesParameterizedQuery(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/OrderService.php'
        );

        $this->assertStringContainsString(':search', $source);
    }

    public function testHasStrictTypes(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/OrderService.php'
        );

        $this->assertStringContainsString('declare(strict_types=1)', $source);
    }

    // ===========================
    // OFFSET HANDLING
    // ===========================

    public function testListOrdersOffset(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse([], 0),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders(['offset' => 100, 'limit' => 50]);

        $this->assertSame(3, $result['page']); // floor(100/50)+1 = 3
    }

    // ===========================
    // RESPONSE STRUCTURE
    // ===========================

    public function testListOrdersResponseHasAllRequiredKeys(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse([]),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders();

        $requiredKeys = ['success', 'source', 'results', 'orders', 'page', 'pages', 'limit', 'total', 'has_more'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Resposta deve conter chave '{$key}'");
        }
    }

    public function testGetOrderResponseHasAllRequiredKeys(): void
    {
        $order = $this->buildSampleOrder(1234);
        $client = $this->createMockClient('123456', [
            '/orders/1234' => $order,
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->getOrder('1234');

        $requiredKeys = ['success', 'source', 'id', 'status', 'total_amount', 'date_created', 'data'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Resposta deve conter chave '{$key}'");
        }
    }

    public function testSyncOrdersResponseHasAllRequiredKeys(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse([]),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->syncOrders();

        $requiredKeys = ['success', 'synced', 'total', 'errors'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Resposta deve conter chave '{$key}'");
        }
    }

    // ===========================
    // EDGE CASES
    // ===========================

    public function testListOrdersHandlesNullResultsGracefully(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => ['results' => null, 'paging' => ['total' => 0]],
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders();

        $this->assertTrue($result['success']);
        $this->assertEmpty($result['orders']);
    }

    public function testListOrdersWithStatusFilter(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse([
                $this->buildSampleOrder(5001, 'shipped', 300.0),
            ]),
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->listOrders(['status' => 'shipped']);

        $this->assertTrue($result['success']);
        $this->assertSame('shipped', $result['orders'][0]['status']);
    }

    public function testListOrdersDateFilters(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => $this->buildOrdersSearchResponse([]),
        ]);

        $client->expects($this->once())
            ->method('get')
            ->with(
                '/orders/search',
                $this->callback(function (array $params): bool {
                    return isset($params['order.date_created.from'])
                        && isset($params['order.date_created.to']);
                })
            )
            ->willReturn($this->buildOrdersSearchResponse([]));

        $service = $this->buildService(1, $client);
        $result = $service->listOrders([
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-19',
        ]);

        $this->assertTrue($result['success']);
    }

    public function testSyncOrdersHandlesNonArrayPayload(): void
    {
        $client = $this->createMockClient('123456', [
            '/orders/search' => [
                'results' => [
                    $this->buildSampleOrder(4001),
                    'invalid_item',
                    null,
                ],
                'paging' => ['total' => 3],
            ],
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->syncOrders();

        // Only the valid order should be synced, others should error
        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(1, $result['synced']);
    }

    public function testGetOrderHandlesMissingAmountGracefully(): void
    {
        $order = $this->buildSampleOrder(7001);
        unset($order['total_amount']);

        $client = $this->createMockClient('123456', [
            '/orders/7001' => $order,
        ]);

        $service = $this->buildService(1, $client);
        $result = $service->getOrder('7001');

        $this->assertTrue($result['success']);
        $this->assertSame(0.0, $result['total_amount']);
    }
}
