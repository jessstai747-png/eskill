<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MercadoLivre\AccountGovernanceIntegrationService;
use App\Services\MercadoLivreClient;
use App\Services\ItemMetricsService;
use App\Services\AccountGovernanceService;
use PHPUnit\Framework\TestCase;

/**
 * AccountGovernanceIntegrationServiceTest
 *
 * Tests for the ML API integration layer of Account Governance.
 * Uses mocked MercadoLivreClient to avoid real API calls.
 *
 * @covers \App\Services\MercadoLivre\AccountGovernanceIntegrationService
 */
class AccountGovernanceIntegrationServiceTest extends TestCase
{
    private AccountGovernanceIntegrationService $service;
    private MercadoLivreClient $mockClient;
    private ItemMetricsService $mockMetrics;
    private AccountGovernanceService $governanceService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock for MercadoLivreClient
        $this->mockClient = $this->createMock(MercadoLivreClient::class);
        $this->mockMetrics = $this->createMock(ItemMetricsService::class);
        $this->governanceService = new AccountGovernanceService();

        $this->service = new AccountGovernanceIntegrationService(
            accountId: null,
            mlClient: $this->mockClient,
            metricsService: $this->mockMetrics,
            governanceService: $this->governanceService,
            logger: null
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CONSTRUCTOR TESTS
    // ═══════════════════════════════════════════════════════════════════════

    public function testConstructorWithDependencies(): void
    {
        $service = new AccountGovernanceIntegrationService(
            accountId: 123,
            mlClient: $this->mockClient,
            metricsService: $this->mockMetrics,
            governanceService: $this->governanceService
        );

        $this->assertInstanceOf(AccountGovernanceIntegrationService::class, $service);
        $this->assertSame($this->mockClient, $service->getClient());
        $this->assertSame($this->mockMetrics, $service->getMetricsService());
        $this->assertSame($this->governanceService, $service->getGovernanceService());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ERROR HANDLING TESTS
    // ═══════════════════════════════════════════════════════════════════════

    public function testRunDiagnosticReturnsErrorWhenNoToken(): void
    {
        $this->mockClient
            ->method('getAccessToken')
            ->willReturn('');

        $result = $this->service->runDiagnosticFromAPI();

        $this->assertTrue($result['error']);
        $this->assertEquals('ml_not_configured', $result['error_code']);
        $this->assertStringContainsString('não configurado', strtolower($result['message']));
    }

    public function testRunDiagnosticReturnsErrorWhenSellerFetchFails(): void
    {
        $this->mockClient
            ->method('getAccessToken')
            ->willReturn('valid_token_here');

        $this->mockClient
            ->method('getMe')
            ->willReturn(['error' => true, 'message' => 'Token inválido']);

        $result = $this->service->runDiagnosticFromAPI();

        $this->assertTrue($result['error']);
        $this->assertEquals('seller_fetch_failed', $result['error_code']);
    }

    public function testRunDiagnosticReturnsErrorWhenNoItems(): void
    {
        $this->mockClient
            ->method('getAccessToken')
            ->willReturn('valid_token');

        $this->mockClient
            ->method('getMe')
            ->willReturn($this->createSellerResponse());

        $this->mockClient
            ->method('getSellerId')
            ->willReturn('123456789');

        $this->mockClient
            ->method('get')
            ->willReturn(['results' => [], 'paging' => ['total' => 0]]);

        $result = $this->service->runDiagnosticFromAPI();

        $this->assertTrue($result['error']);
        $this->assertEquals('no_items', $result['error_code']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SUCCESS FLOW TESTS
    // ═══════════════════════════════════════════════════════════════════════

    public function testRunDiagnosticSuccessWithMinimalData(): void
    {
        $this->setupSuccessfulMocks();

        $result = $this->service->runDiagnosticFromAPI([
            'max_items' => 10,
            'fetch_visits' => false,
            'fetch_sales' => false,
        ]);

        $this->assertFalse($result['error'] ?? false);
        $this->assertArrayHasKey('executive_summary', $result);
        $this->assertArrayHasKey('account_status', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('meta', $result);
    }

    public function testRunDiagnosticContainsIntegrationMetadata(): void
    {
        $this->setupSuccessfulMocks();

        $result = $this->service->runDiagnosticFromAPI([
            'max_items' => 5,
            'fetch_visits' => false,
            'fetch_sales' => false,
        ]);

        $this->assertArrayHasKey('integration', $result['meta']);
        $this->assertEquals('mercado_livre_api', $result['meta']['integration']['source']);
        $this->assertArrayHasKey('fetched_at', $result['meta']['integration']);
        $this->assertArrayHasKey('options', $result['meta']['integration']);
    }

    public function testRunDiagnosticProcessesItemsCorrectly(): void
    {
        $this->setupSuccessfulMocks();

        $result = $this->service->runDiagnosticFromAPI([
            'max_items' => 10,
            'include_paused' => false,  // Only active items
            'fetch_visits' => false,
            'fetch_sales' => false,
        ]);

        $this->assertCount(3, $result['items']);

        foreach ($result['items'] as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('classification', $item);
            $this->assertArrayHasKey('score', $item);
            $this->assertArrayHasKey('flags', $item);
            $this->assertArrayHasKey('actions', $item);
        }
    }

    public function testRunDiagnosticRespectsMaxItemsOption(): void
    {
        $this->mockClient
            ->method('getAccessToken')
            ->willReturn('valid_token');

        $this->mockClient
            ->method('getMe')
            ->willReturn($this->createSellerResponse());

        $this->mockClient
            ->method('getSellerId')
            ->willReturn('123456789');

        // Return more items than requested
        $manyItems = [];
        for ($i = 1; $i <= 50; $i++) {
            $manyItems[] = "MLB{$i}";
        }

        $this->mockClient
            ->method('get')
            ->willReturnCallback(function ($endpoint, $params = []) use ($manyItems) {
                if (strpos($endpoint, '/items/search') !== false) {
                    $limit = $params['limit'] ?? 50;
                    $offset = $params['offset'] ?? 0;
                    return [
                        'results' => array_slice($manyItems, $offset, $limit),
                        'paging' => ['total' => 50, 'offset' => $offset, 'limit' => $limit],
                    ];
                }
                if ($endpoint === '/items') {
                    $ids = explode(',', $params['ids'] ?? '');
                    return array_map(fn($id) => [
                        'body' => $this->createItemResponse($id),
                    ], $ids);
                }
                return [];
            });

        $result = $this->service->runDiagnosticFromAPI([
            'max_items' => 5,
            'include_paused' => false,
            'fetch_visits' => false,
            'fetch_sales' => false,
        ]);

        $this->assertLessThanOrEqual(5, count($result['items']));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // REPUTATION MAPPING TESTS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider reputationLevelProvider
     */
    public function testReputationLevelMapping(?string $powerSeller, ?string $levelId, string $expected): void
    {
        $this->mockClient
            ->method('getAccessToken')
            ->willReturn('valid_token');

        $sellerResponse = $this->createSellerResponse();
        $sellerResponse['seller_reputation']['power_seller_status'] = $powerSeller;
        $sellerResponse['seller_reputation']['level_id'] = $levelId;

        $this->mockClient
            ->method('getMe')
            ->willReturn($sellerResponse);

        $this->mockClient
            ->method('getSellerId')
            ->willReturn('123456789');

        $this->mockClient
            ->method('get')
            ->willReturnCallback(fn($endpoint, $params = []) => $this->mockGetForItems($endpoint, $params));

        $result = $this->service->runDiagnosticFromAPI([
            'max_items' => 3,
            'fetch_visits' => false,
            'fetch_sales' => false,
        ]);

        // Verificar que o account_metrics contém o reputation_level mapeado
        $this->assertArrayHasKey('account_metrics', $result);
        $this->assertEquals($expected, $result['account_metrics']['reputation_level']);
    }

    public static function reputationLevelProvider(): array
    {
        return [
            'platinum power seller' => ['platinum', null, 'platinum'],
            'gold power seller' => ['gold', null, 'gold'],
            'silver power seller' => ['silver', null, 'silver'],
            'level 5 green' => [null, '5_green', 'platinum'],
            'level 4 light green' => [null, '4_light_green', 'gold'],
            'level 3 yellow' => [null, '3_yellow', 'silver'],
            'level 2 orange' => [null, '2_orange', 'bronze'],
            'level 1 red' => [null, '1_red', 'red'],
            'unknown level' => [null, null, 'unknown'],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPER METHODS
    // ═══════════════════════════════════════════════════════════════════════

    private function setupSuccessfulMocks(): void
    {
        $this->mockClient
            ->method('getAccessToken')
            ->willReturn('valid_token');

        $this->mockClient
            ->method('getMe')
            ->willReturn($this->createSellerResponse());

        $this->mockClient
            ->method('getSellerId')
            ->willReturn('123456789');

        $this->mockClient
            ->method('get')
            ->willReturnCallback(fn($endpoint, $params = []) => $this->mockGetForItems($endpoint, $params));
    }

    private function mockGetForItems(string $endpoint, array $params = []): array
    {
        if (strpos($endpoint, '/items/search') !== false) {
            return [
                'results' => ['MLB1', 'MLB2', 'MLB3'],
                'paging' => ['total' => 3, 'offset' => 0, 'limit' => 50],
            ];
        }

        if ($endpoint === '/items') {
            $ids = explode(',', $params['ids'] ?? '');
            return array_map(fn($id) => [
                'body' => $this->createItemResponse($id),
            ], $ids);
        }

        if (strpos($endpoint, '/orders/search') !== false) {
            return ['results' => [], 'paging' => ['total' => 0]];
        }

        return [];
    }

    private function createSellerResponse(): array
    {
        return [
            'id' => 123456789,
            'nickname' => 'TEST_SELLER',
            'email' => 'test@example.com',
            'site_id' => 'MLB',
            'registration_date' => '2020-01-01T00:00:00.000-00:00',
            'seller_reputation' => [
                'power_seller_status' => 'gold',
                'level_id' => '4_light_green',
                'transactions' => [
                    'total' => 1000,
                    'completed' => 950,
                ],
                'metrics' => [
                    'claims' => ['rate' => 0.02],
                    'delayed_handling_time' => ['rate' => 0.03],
                    'cancellations' => ['rate' => 0.01],
                ],
            ],
        ];
    }

    private function createItemResponse(string $itemId): array
    {
        $index = (int) filter_var($itemId, FILTER_SANITIZE_NUMBER_INT);

        return [
            'id' => $itemId,
            'title' => "Test Item {$index}",
            'price' => 100.0 + ($index * 10),
            'currency_id' => 'BRL',
            'available_quantity' => 10 + $index,
            'sold_quantity' => 5 * $index,
            'status' => 'active',
            'listing_type_id' => 'gold_special',
            'category_id' => 'MLB1234',
            'permalink' => "https://produto.mercadolivre.com.br/{$itemId}",
            'thumbnail' => "https://http2.mlstatic.com/D_{$itemId}-O.jpg",
            'date_created' => '2023-01-01T00:00:00.000-00:00',
            'last_updated' => '2024-01-15T00:00:00.000-00:00',
        ];
    }
}
