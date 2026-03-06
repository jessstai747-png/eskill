<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MercadoLivreClient;
use App\Services\ShippingService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ShippingService — DB-free via DI mocking
 *
 * @covers \App\Services\ShippingService
 */
class ShippingServiceTest extends TestCase
{
    /**
     * @return MercadoLivreClient&MockObject
     */
    private function createMockClient(array $getMap = [], array $postMap = [], array $putMap = [], ?string $sellerId = '12345'): MercadoLivreClient
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('getSellerId')->willReturn($sellerId);

        if (!empty($getMap)) {
            $client->method('get')->willReturnCallback(function (string $endpoint) use ($getMap) {
                foreach ($getMap as $pattern => $response) {
                    if (str_contains($endpoint, $pattern)) {
                        return $response;
                    }
                }
                return ['error' => 'not_mocked', 'message' => 'Get not mocked: ' . $endpoint];
            });
        } else {
            $client->method('get')->willReturn(['error' => 'not_mocked']);
        }

        if (!empty($postMap)) {
            $client->method('post')->willReturnCallback(function (string $endpoint) use ($postMap) {
                foreach ($postMap as $pattern => $response) {
                    if (str_contains($endpoint, $pattern)) {
                        return $response;
                    }
                }
                return ['error' => 'not_mocked'];
            });
        } else {
            $client->method('post')->willReturn(['error' => 'not_mocked']);
        }

        if (!empty($putMap)) {
            $client->method('put')->willReturnCallback(function (string $endpoint) use ($putMap) {
                foreach ($putMap as $pattern => $response) {
                    if (str_contains($endpoint, $pattern)) {
                        return $response;
                    }
                }
                return ['error' => 'not_mocked'];
            });
        } else {
            $client->method('put')->willReturn(['error' => 'not_mocked']);
        }

        return $client;
    }

    /**
     * @return PDO&MockObject
     */
    private function createMockDb(?array $fetchResult = null, ?array $fetchAllResult = null, int $fetchColumn = 0): PDO
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);

        if ($fetchResult !== null) {
            $stmt->method('fetch')->willReturn($fetchResult);
        } else {
            $stmt->method('fetch')->willReturn(false);
        }

        if ($fetchAllResult !== null) {
            $stmt->method('fetchAll')->willReturn($fetchAllResult);
        } else {
            $stmt->method('fetchAll')->willReturn([]);
        }

        $stmt->method('fetchColumn')->willReturn($fetchColumn);

        $db = $this->createMock(PDO::class);
        $db->method('prepare')->willReturn($stmt);

        return $db;
    }

    private function buildService(?MercadoLivreClient $client = null, ?PDO $db = null): ShippingService
    {
        return new ShippingService(
            accountId: 1,
            client: $client ?? $this->createMockClient(),
            db: $db,
            skipDbAutoConnect: true
        );
    }

    // -------------------------------------------------------
    // Constructor Tests
    // -------------------------------------------------------

    public function testConstructorWithAllDependencies(): void
    {
        $service = new ShippingService(
            accountId: 1,
            client: $this->createMockClient(),
            db: $this->createMockDb(),
            skipDbAutoConnect: false
        );
        $this->assertInstanceOf(ShippingService::class, $service);
    }

    public function testConstructorWithSkipDbAutoConnect(): void
    {
        $service = new ShippingService(
            accountId: 1,
            client: $this->createMockClient(),
            skipDbAutoConnect: true
        );
        $this->assertInstanceOf(ShippingService::class, $service);
    }

    // -------------------------------------------------------
    // getShippingPreferences Tests
    // -------------------------------------------------------

    public function testGetShippingPreferencesSuccess(): void
    {
        $client = $this->createMockClient([
            '/shipping_preferences' => [
                'free_methods' => [['id' => 1]],
                'cost_rule' => 'custom',
                'free_shipping_rules' => [],
                'handling_time' => ['value' => 48, 'unit' => 'hours'],
                'local_pick_up' => true,
                'dimensions' => [
                    'default_width' => 20,
                    'default_height' => 15,
                    'default_length' => 30,
                    'default_weight' => 1000,
                ],
            ],
        ]);

        $service = $this->buildService(client: $client);
        $result = $service->getShippingPreferences();

        $this->assertArrayHasKey('free_methods', $result);
        $this->assertArrayHasKey('handling_time', $result);
        $this->assertEquals('custom', $result['cost_rule']);
        $this->assertEquals(48, $result['handling_time']['value']);
        $this->assertTrue($result['local_pickup']);
        $this->assertTrue($result['available']);
        $this->assertSame('mercado_livre', $result['source']);
    }

    public function testGetShippingPreferencesApiError(): void
    {
        $client = $this->createMockClient([
            '/shipping_preferences' => ['error' => 'unauthorized'],
        ]);

        $service = $this->buildService(client: $client);
        $result = $service->getShippingPreferences();

        // Returns default preferences on error
        $this->assertEquals('default', $result['cost_rule']);
        $this->assertEquals(24, $result['handling_time']['value']);
        $this->assertFalse($result['available']);
        $this->assertSame('default', $result['source']);
    }

    public function testGetShippingPreferencesReturnsUnavailableMetadataWhenCapabilityIsMissing(): void
    {
        $client = $this->createMockClient([
            '/shipping_preferences' => [
                'error' => 'shipping_preferences_unavailable',
                'message' => 'A conta não possui configuração de preferências de envio disponível via API.',
            ],
        ]);

        $service = $this->buildService(client: $client);
        $result = $service->getShippingPreferences();

        $this->assertFalse($result['available']);
        $this->assertSame('feature_unavailable', $result['source']);
        $this->assertStringContainsString('preferências de envio', mb_strtolower($result['message']));
    }

    // -------------------------------------------------------
    // updateShippingPreferences Tests
    // -------------------------------------------------------

    public function testUpdateShippingPreferencesSuccess(): void
    {
        $client = $this->createMockClient([], [], [
            '/shipping_preferences' => ['status' => 'ok'],
        ]);

        $service = $this->buildService(client: $client);
        $result = $service->updateShippingPreferences([
            'handling_time' => ['value' => 24, 'unit' => 'hours'],
        ]);

        $this->assertTrue($result['success']);
    }

    public function testUpdateShippingPreferencesApiError(): void
    {
        $client = $this->createMockClient([], [], [
            '/shipping_preferences' => ['error' => 'forbidden', 'message' => 'No permission'],
        ]);

        $service = $this->buildService(client: $client);
        $result = $service->updateShippingPreferences(['handling_time' => ['value' => 1]]);

        $this->assertFalse($result['success']);
    }

    public function testUpdateShippingPreferencesReturnsFeatureUnavailableMetadata(): void
    {
        $client = $this->createMockClient([], [], [
            '/shipping_preferences' => [
                'error' => 'shipping_preferences_unavailable',
                'message' => 'A conta não possui configuração de preferências de envio disponível via API.',
            ],
        ]);

        $service = $this->buildService(client: $client);
        $result = $service->updateShippingPreferences(['handling_time' => ['value' => 1]]);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['feature_unavailable']);
        $this->assertSame('shipping_preferences', $result['feature']);
    }

    // -------------------------------------------------------
    // configureFreeShipping Tests
    // -------------------------------------------------------

    public function testConfigureFreeShippingSuccess(): void
    {
        $client = $this->createMockClient([], [], [
            '/shipping_preferences' => ['status' => 'ok'],
        ]);

        $rules = [
            ['min_price' => 100, 'region' => 'all'],
        ];

        $service = $this->buildService(client: $client);
        $result = $service->configureFreeShipping($rules);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['rules_count']);
    }

    public function testConfigureFreeShippingApiError(): void
    {
        $client = $this->createMockClient([], [], [
            '/shipping_preferences' => ['error' => 'bad_request', 'message' => 'Invalid rules'],
        ]);

        $service = $this->buildService(client: $client);
        $result = $service->configureFreeShipping([]);

        $this->assertFalse($result['success']);
    }

    // -------------------------------------------------------
    // simulateShippingCost Tests
    // -------------------------------------------------------

    public function testSimulateShippingCostSuccess(): void
    {
        $client = $this->createMockClient([], [
            '/shipping_options/simulate' => [
                'options' => [
                    [
                        'shipping_method_id' => 1,
                        'name' => 'Normal',
                        'cost' => 15.90,
                        'currency_id' => 'BRL',
                        'estimated_delivery_time' => [
                            'date' => '2026-02-25',
                            'time_from' => 24,
                            'time_to' => 72,
                            'unit' => 'hours',
                        ],
                    ],
                    [
                        'shipping_method_id' => 2,
                        'name' => 'Expresso',
                        'cost' => 29.90,
                        'currency_id' => 'BRL',
                        'estimated_delivery_time' => [
                            'date' => '2026-02-22',
                            'time_from' => 12,
                            'time_to' => 24,
                            'unit' => 'hours',
                        ],
                    ],
                ],
            ],
        ]);

        $service = $this->buildService(client: $client);
        $result = $service->simulateShippingCost(
            ['width' => 30, 'height' => 20, 'length' => 40, 'weight' => 2000, 'price' => 149.90],
            '14800-000'
        );

        $this->assertArrayHasKey('options', $result);
        $this->assertCount(2, $result['options']);
        $this->assertNotNull($result['cheapest']);
        $this->assertNotNull($result['fastest']);
    }

    public function testSimulateShippingCostApiError(): void
    {
        $client = $this->createMockClient([], [
            '/shipping_options/simulate' => ['error' => 'bad_request'],
        ]);

        $service = $this->buildService(client: $client);
        $result = $service->simulateShippingCost([], '14800-000');

        $this->assertEmpty($result['options']);
        $this->assertNull($result['cheapest']);
        $this->assertNull($result['fastest']);
    }

    // -------------------------------------------------------
    // getCategoryDimensions Tests
    // -------------------------------------------------------

    public function testGetCategoryDimensionsSuccess(): void
    {
        $client = $this->createMockClient([
            '/categories/MLB123/shipping_preferences' => [
                'dimensions' => [
                    'width' => 25,
                    'height' => 15,
                    'length' => 35,
                    'weight' => 1500,
                    'max_weight' => 25000,
                ],
            ],
        ]);

        $service = $this->buildService(client: $client);
        $result = $service->getCategoryDimensions('MLB123');

        $this->assertEquals(25, $result['recommended_width']);
        $this->assertEquals(15, $result['recommended_height']);
        $this->assertEquals(25000, $result['max_weight']);
    }

    public function testGetCategoryDimensionsApiError(): void
    {
        $client = $this->createMockClient([
            '/categories/MLB999/shipping_preferences' => ['error' => 'not_found'],
        ]);

        $service = $this->buildService(client: $client);
        $result = $service->getCategoryDimensions('MLB999');

        // Returns defaults
        $this->assertEquals(10, $result['recommended_width']);
        $this->assertEquals(30000, $result['max_weight']);
    }

    // -------------------------------------------------------
    // validateDimensions Tests
    // -------------------------------------------------------

    public function testValidateDimensionsValid(): void
    {
        $service = $this->buildService();
        $result = $service->validateDimensions([
            'width' => 30,
            'height' => 20,
            'length' => 40,
            'weight' => 2000,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertTrue($result['dimensions_ok']);
        $this->assertTrue($result['weight_ok']);
    }

    public function testValidateDimensionsSumExceedsMax(): void
    {
        $service = $this->buildService();
        $result = $service->validateDimensions([
            'width' => 80,
            'height' => 80,
            'length' => 80,
            'weight' => 1000,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testValidateDimensionsSumBelowMin(): void
    {
        $service = $this->buildService();
        $result = $service->validateDimensions([
            'width' => 5,
            'height' => 5,
            'length' => 5,
            'weight' => 100,
        ]);

        $this->assertFalse($result['valid']);
    }

    public function testValidateDimensionsSingleExceedsMax(): void
    {
        $service = $this->buildService();
        $result = $service->validateDimensions([
            'width' => 110,
            'height' => 10,
            'length' => 10,
            'weight' => 1000,
        ]);

        $this->assertFalse($result['valid']);
    }

    public function testValidateDimensionsWeightExceedsMax(): void
    {
        $service = $this->buildService();
        $result = $service->validateDimensions([
            'width' => 30,
            'height' => 20,
            'length' => 40,
            'weight' => 35000,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertFalse($result['weight_ok']);
    }

    public function testValidateDimensionsWeightZero(): void
    {
        $service = $this->buildService();
        $result = $service->validateDimensions([
            'width' => 30,
            'height' => 20,
            'length' => 40,
            'weight' => 0,
        ]);

        $this->assertFalse($result['valid']);
    }

    public function testValidateDimensionsEmptyDefaults(): void
    {
        $service = $this->buildService();
        $result = $service->validateDimensions([]);

        // width=0, height=0, length=0, weight=0 => sum=0 < minSum=26
        $this->assertFalse($result['valid']);
    }

    // -------------------------------------------------------
    // getShippingLabels Tests
    // -------------------------------------------------------

    public function testGetShippingLabelsSuccess(): void
    {
        $client = $this->createMockClient([
            '/shipments/' => ['url' => 'https://labels.mercadolibre.com/label123.pdf'],
        ]);

        $service = $this->buildService(client: $client);
        $result = $service->getShippingLabels(['SH1', 'SH2']);

        $this->assertEquals(2, $result['total']);
        $this->assertCount(2, $result['labels']);
    }

    public function testGetShippingLabelsApiError(): void
    {
        $client = $this->createMockClient([
            '/shipments/' => ['error' => 'not_found'],
        ]);

        $service = $this->buildService(client: $client);
        $result = $service->getShippingLabels(['SH999']);

        $this->assertEquals(0, $result['total']);
    }

    public function testGetShippingLabelsEmptyList(): void
    {
        $service = $this->buildService();
        $result = $service->getShippingLabels([]);

        $this->assertEquals(0, $result['total']);
    }

    // -------------------------------------------------------
    // setHandlingTime Tests
    // -------------------------------------------------------

    public function testSetHandlingTimeSuccess(): void
    {
        $client = $this->createMockClient([], [], [
            '/shipping_preferences' => ['status' => 'ok'],
        ]);

        $service = $this->buildService(client: $client);
        $result = $service->setHandlingTime(48, 'hours');

        $this->assertTrue($result['success']);
        $this->assertEquals('48 hours', $result['handling_time']);
    }

    public function testSetHandlingTimeApiError(): void
    {
        $client = $this->createMockClient([], [], [
            '/shipping_preferences' => ['error' => 'bad_request', 'message' => 'Invalid time'],
        ]);

        $service = $this->buildService(client: $client);
        $result = $service->setHandlingTime(0, 'hours');

        $this->assertFalse($result['success']);
    }

    // -------------------------------------------------------
    // analyzeShippingPerformance Tests
    // -------------------------------------------------------

    public function testAnalyzeShippingPerformanceSuccess(): void
    {
        $dbRow = [
            'total_shipments' => 100,
            'avg_handling_hours' => 18.5,
            'delivered' => 90,
            'cancelled' => 3,
            'delayed' => 5,
        ];

        $db = $this->createMockDb($dbRow);
        $service = $this->buildService(db: $db);
        $result = $service->analyzeShippingPerformance();

        $this->assertEquals(100, $result['total_shipments']);
        $this->assertEquals(90, $result['delivered']);
        $this->assertEquals(90.0, $result['delivery_rate']);
        $this->assertArrayHasKey('score', $result);
    }

    public function testAnalyzeShippingPerformanceNoDb(): void
    {
        $service = $this->buildService();
        $result = $service->analyzeShippingPerformance();

        $this->assertEquals(0, $result['total_shipments']);
        $this->assertEquals('db_unavailable', $result['error']);
    }

    public function testAnalyzeShippingPerformanceWithDateFilters(): void
    {
        $dbRow = [
            'total_shipments' => 50,
            'avg_handling_hours' => 24.0,
            'delivered' => 48,
            'cancelled' => 1,
            'delayed' => 2,
        ];

        $db = $this->createMockDb($dbRow);
        $service = $this->buildService(db: $db);
        $result = $service->analyzeShippingPerformance([
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]);

        $this->assertEquals(50, $result['total_shipments']);
        $this->assertArrayHasKey('period', $result);
    }

    // -------------------------------------------------------
    // generatePickList Tests
    // -------------------------------------------------------

    public function testGeneratePickListSuccess(): void
    {
        $orderRows = [
            [
                'id' => '1',
                'ml_order_id' => 'ORD001',
                'order_data' => json_encode([
                    'order_items' => [
                        [
                            'quantity' => 2,
                            'item' => ['title' => 'Bagageiro CG 160', 'seller_sku' => 'BAG-CG160'],
                        ],
                    ],
                ]),
            ],
            [
                'id' => '2',
                'ml_order_id' => 'ORD002',
                'order_data' => json_encode([
                    'order_items' => [
                        [
                            'quantity' => 1,
                            'item' => ['title' => 'Bagageiro CG 160', 'seller_sku' => 'BAG-CG160'],
                        ],
                        [
                            'quantity' => 3,
                            'item' => ['title' => 'Retrovisor Universal', 'seller_sku' => 'RET-UNIV'],
                        ],
                    ],
                ]),
            ],
        ];

        $db = $this->createMockDb(null, $orderRows);
        $service = $this->buildService(db: $db);
        $result = $service->generatePickList(['ORD001', 'ORD002']);

        $this->assertCount(2, $result);

        // Sorted by title
        $bagageiro = null;
        $retrovisor = null;
        foreach ($result as $item) {
            if ($item['sku'] === 'BAG-CG160') {
                $bagageiro = $item;
            }
            if ($item['sku'] === 'RET-UNIV') {
                $retrovisor = $item;
            }
        }

        $this->assertNotNull($bagageiro);
        $this->assertEquals(3, $bagageiro['total_quantity']); // 2 + 1
        $this->assertCount(2, $bagageiro['orders']); // ORD001, ORD002

        $this->assertNotNull($retrovisor);
        $this->assertEquals(3, $retrovisor['total_quantity']);
    }

    public function testGeneratePickListEmptyOrderIds(): void
    {
        $service = $this->buildService();
        $result = $service->generatePickList([]);

        $this->assertEmpty($result);
    }

    public function testGeneratePickListNoDb(): void
    {
        $service = $this->buildService();
        $result = $service->generatePickList(['ORD001']);

        $this->assertEmpty($result);
    }

    public function testGeneratePickListInvalidOrderData(): void
    {
        $orderRows = [
            [
                'id' => '1',
                'ml_order_id' => 'ORD001',
                'order_data' => 'invalid json',
            ],
        ];

        $db = $this->createMockDb(null, $orderRows);
        $service = $this->buildService(db: $db);
        $result = $service->generatePickList(['ORD001']);

        $this->assertEmpty($result);
    }

    public function testGeneratePickListSkipsZeroQuantity(): void
    {
        $orderRows = [
            [
                'id' => '1',
                'ml_order_id' => 'ORD001',
                'order_data' => json_encode([
                    'order_items' => [
                        [
                            'quantity' => 0,
                            'item' => ['title' => 'Item Zero', 'seller_sku' => 'ZERO'],
                        ],
                    ],
                ]),
            ],
        ];

        $db = $this->createMockDb(null, $orderRows);
        $service = $this->buildService(db: $db);
        $result = $service->generatePickList(['ORD001']);

        $this->assertEmpty($result);
    }

    // -------------------------------------------------------
    // getShippingIds Tests
    // -------------------------------------------------------

    public function testGetShippingIdsSuccess(): void
    {
        $orderRows = [
            ['order_data' => json_encode(['shipping' => ['id' => 'SH1001']])],
            ['order_data' => json_encode(['shipping' => ['id' => 'SH1002']])],
        ];

        $db = $this->createMockDb(null, $orderRows);
        $service = $this->buildService(db: $db);
        $result = $service->getShippingIds(['ORD001', 'ORD002']);

        $this->assertCount(2, $result);
        $this->assertContains('SH1001', $result);
        $this->assertContains('SH1002', $result);
    }

    public function testGetShippingIdsEmpty(): void
    {
        $service = $this->buildService();
        $result = $service->getShippingIds([]);

        $this->assertEmpty($result);
    }

    public function testGetShippingIdsNoDb(): void
    {
        $service = $this->buildService();
        $result = $service->getShippingIds(['ORD001']);

        $this->assertEmpty($result);
    }

    public function testGetShippingIdsDuplicatesRemoved(): void
    {
        $orderRows = [
            ['order_data' => json_encode(['shipping' => ['id' => 'SH1001']])],
            ['order_data' => json_encode(['shipping' => ['id' => 'SH1001']])],
        ];

        $db = $this->createMockDb(null, $orderRows);
        $service = $this->buildService(db: $db);
        $result = $service->getShippingIds(['ORD001', 'ORD002']);

        $this->assertCount(1, $result);
    }

    public function testGetShippingIdsWithShippingIdField(): void
    {
        $orderRows = [
            ['order_data' => json_encode(['shipping_id' => 'SH2001'])],
        ];

        $db = $this->createMockDb(null, $orderRows);
        $service = $this->buildService(db: $db);
        $result = $service->getShippingIds(['ORD001']);

        $this->assertCount(1, $result);
        $this->assertContains('SH2001', $result);
    }

    // -------------------------------------------------------
    // Scoring Tests
    // -------------------------------------------------------

    public function testShippingScorePerfect(): void
    {
        $dbRow = [
            'total_shipments' => 100,
            'avg_handling_hours' => 12.0,
            'delivered' => 98,
            'cancelled' => 1,
            'delayed' => 2,
        ];

        $db = $this->createMockDb($dbRow);
        $service = $this->buildService(db: $db);
        $result = $service->analyzeShippingPerformance();

        // 98% delivery (>=95%), 2% delay (<=5%), 1% cancel (<=3%), 12h handling (<=48)
        $this->assertEquals(100, $result['score']);
    }

    public function testShippingScorePoor(): void
    {
        $dbRow = [
            'total_shipments' => 100,
            'avg_handling_hours' => 72.0,
            'delivered' => 80,
            'cancelled' => 10,
            'delayed' => 15,
        ];

        $db = $this->createMockDb($dbRow);
        $service = $this->buildService(db: $db);
        $result = $service->analyzeShippingPerformance();

        // 80% delivery (-20), 15% delay (-15), 10% cancel (-15), 72h handling (-10) = 40
        $this->assertEquals(40, $result['score']);
    }

    public function testShippingScoreZeroShipments(): void
    {
        $dbRow = [
            'total_shipments' => 0,
            'avg_handling_hours' => 0,
            'delivered' => 0,
            'cancelled' => 0,
            'delayed' => 0,
        ];

        $db = $this->createMockDb($dbRow);
        $service = $this->buildService(db: $db);
        $result = $service->analyzeShippingPerformance();

        $this->assertEquals(0, $result['score']);
    }
}
