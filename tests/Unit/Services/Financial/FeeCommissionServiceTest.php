<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Financial;

use PHPUnit\Framework\TestCase;
use App\Services\Financial\FeeCommissionService;
use App\Services\MercadoLivreClient;

/**
 * @covers \App\Services\Financial\FeeCommissionService
 */
class FeeCommissionServiceTest extends TestCase
{
    // ===========================
    // Helpers
    // ===========================

    private function createMockClient(array $returnMap = []): MercadoLivreClient
    {
        $mock = $this->createMock(MercadoLivreClient::class);

        $mock->method('get')
            ->willReturnCallback(function (string $endpoint, array $params = []) use ($returnMap): array {
                foreach ($returnMap as $pattern => $response) {
                    if (str_contains($endpoint, $pattern)) {
                        return $response;
                    }
                }
                return ['error' => 'not_found', 'message' => 'Endpoint not mocked'];
            });

        return $mock;
    }

    private function buildService(MercadoLivreClient $mockClient, ?\PDO $mockDb = null): FeeCommissionService
    {
        $ref = new \ReflectionClass(FeeCommissionService::class);
        $service = $ref->newInstanceWithoutConstructor();

        $clientProp = $ref->getProperty('client');
        $clientProp->setAccessible(true);
        $clientProp->setValue($service, $mockClient);

        $accountIdProp = $ref->getProperty('accountId');
        $accountIdProp->setAccessible(true);
        $accountIdProp->setValue($service, 1);

        if ($mockDb !== null) {
            $dbProp = $ref->getProperty('db');
            $dbProp->setAccessible(true);
            $dbProp->setValue($service, $mockDb);
        }

        return $service;
    }

    // ===========================
    // getBillingInfo
    // ===========================

    public function testGetBillingInfoSuccess(): void
    {
        $client = $this->createMockClient([
            'billing/info' => ['billing_allowed' => true, 'billing_option' => 'INVOICE'],
        ]);

        $client->method('getSellerId')->willReturn('123456');

        $service = $this->buildService($client);
        $result = $service->getBillingInfo();

        $this->assertTrue($result['billing_allowed']);
        $this->assertSame('INVOICE', $result['billing_option']);
    }

    public function testGetBillingInfoNoSeller(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('getSellerId')->willReturn(null);

        $service = $this->buildService($client);
        $result = $service->getBillingInfo();

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Seller ID', $result['error']);
    }

    public function testGetBillingInfoApiError(): void
    {
        $client = $this->createMockClient([
            'billing/info' => ['error' => 'forbidden', 'message' => 'Acesso negado'],
        ]);
        $client->method('getSellerId')->willReturn('123456');

        $service = $this->buildService($client);
        $result = $service->getBillingInfo();

        $this->assertFalse($result['billing_allowed']);
        $this->assertSame('Acesso negado', $result['message']);
    }

    // ===========================
    // getBillingDetails
    // ===========================

    public function testGetBillingDetailsSuccess(): void
    {
        $client = $this->createMockClient([
            '/group/ML/details' => [
                'total' => 2,
                'last_id' => 500,
                'offset' => 0,
                'limit' => 150,
                'results' => [
                    [
                        'charge_info' => [
                            'detail_id' => 'D001',
                            'detail_amount' => 25.50,
                            'legal_document_number' => 'NF001',
                            'legal_document_status' => 'APPROVED',
                            'transaction_detail' => 'commission',
                            'detail_type' => 'charge',
                            'detail_sub_type' => 'COMMISSION',
                            'creation_date_time' => '2026-04-01T10:00:00Z',
                            'debited_from_operation' => true,
                        ],
                        'sales_info' => [[
                            'order_id' => '111',
                            'transaction_amount' => 200.00,
                            'payer_nickname' => 'BUYER1',
                        ]],
                        'items_info' => [[
                            'item_id' => 'MLB123',
                            'item_title' => 'Bagageiro Honda CG',
                            'item_price' => 200.00,
                            'item_amount' => 1,
                        ]],
                        'shipping_info' => ['receiver_shipping_cost' => 10.0],
                        'discount_info' => ['discount_amount' => 0.0],
                    ],
                ],
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getBillingDetails('2026-04-01');

        $this->assertCount(1, $result['results']);
        $this->assertSame('D001', $result['results'][0]['detail_id']);
        $this->assertSame(25.50, $result['results'][0]['detail_amount']);
        $this->assertSame('MLB123', $result['results'][0]['item_id']);
        $this->assertSame('111', $result['results'][0]['order_id']);
        $this->assertSame('2026-04-01', $result['period']);
        $this->assertSame('BILL', $result['document_type']);
    }

    public function testGetBillingDetailsApiError(): void
    {
        $client = $this->createMockClient([
            '/group/ML/details' => ['error' => 'not_found', 'message' => 'Período inválido'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getBillingDetails('2026-04-01', 'BILL', 150, 0);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Período inválido', $result['error']);
        $this->assertEmpty($result['results']);
    }

    public function testGetBillingDetailsEmptyResults(): void
    {
        $client = $this->createMockClient([
            '/group/ML/details' => ['results' => [], 'total' => 0],
        ]);

        $service = $this->buildService($client);
        $result = $service->getBillingDetails('2026-04-01');

        $this->assertCount(0, $result['results']);
        $this->assertSame(0, $result['total']);
    }

    // ===========================
    // getMercadoPagoBillingDetails
    // ===========================

    public function testGetMercadoPagoBillingDetailsSuccess(): void
    {
        $client = $this->createMockClient([
            '/group/MP/details' => [
                'total' => 1,
                'results' => [
                    [
                        'charge_info' => [
                            'detail_id' => 'MP001',
                            'detail_amount' => 5.0,
                            'detail_type' => 'charge',
                            'detail_sub_type' => 'TAX',
                            'creation_date_time' => '2026-04-01T12:00:00Z',
                        ],
                        'operation_info' => [
                            'operation_type' => 'payment',
                            'transaction_amount' => 100.0,
                        ],
                        'perception_info' => [
                            'aliquot' => 3.5,
                            'taxable_amount' => 100.0,
                        ],
                    ],
                ],
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getMercadoPagoBillingDetails('2026-04-01');

        $this->assertCount(1, $result['results']);
        $this->assertSame('MP001', $result['results'][0]['detail_id']);
        $this->assertSame(5.0, $result['results'][0]['detail_amount']);
        $this->assertSame(3.5, $result['results'][0]['perception_aliquot']);
        $this->assertSame('2026-04-01', $result['period']);
    }

    public function testGetMercadoPagoBillingDetailsApiError(): void
    {
        $client = $this->createMockClient([
            '/group/MP/details' => ['error' => 'server_error', 'message' => 'Erro interno'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getMercadoPagoBillingDetails('2026-04-01');

        $this->assertArrayHasKey('error', $result);
        $this->assertEmpty($result['results']);
    }

    // ===========================
    // getBillingByOrder
    // ===========================

    public function testGetBillingByOrderSuccess(): void
    {
        $client = $this->createMockClient([
            '/group/ML/order/details' => [
                'results' => [
                    [
                        'order_id' => '999',
                        'payment_info' => [[
                            'payment_id' => 'PAY001',
                            'status' => 'approved',
                            'money_release_days' => 15,
                            'tax_details' => [],
                        ]],
                        'sale_fee' => [
                            'gross' => 200.0,
                            'net' => 165.0,
                            'rebate' => 0.0,
                            'discount' => 35.0,
                        ],
                        'details' => [
                            [
                                'charge_info' => [
                                    'detail_id' => 'C001',
                                    'detail_amount' => 35.0,
                                    'detail_type' => 'charge',
                                    'detail_sub_type' => 'COMMISSION',
                                ],
                                'sales_info' => [[]],
                                'items_info' => [[
                                    'item_id' => 'MLB999',
                                    'item_title' => 'Bagageiro',
                                ]],
                                'discount_info' => ['discount_amount' => 0.0],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getBillingByOrder(['999']);

        $this->assertCount(1, $result['results']);
        $this->assertSame('999', $result['results'][0]['order_id']);
        $this->assertSame(200.0, $result['results'][0]['sale_fee']['gross']);
        $this->assertSame(165.0, $result['results'][0]['sale_fee']['net']);
        $this->assertCount(1, $result['results'][0]['charges']);
    }

    public function testGetBillingByOrderApiError(): void
    {
        $client = $this->createMockClient([
            '/group/ML/order/details' => ['error' => 'not_found', 'message' => 'Order not found'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getBillingByOrder(['000']);

        $this->assertArrayHasKey('error', $result);
        $this->assertEmpty($result['results']);
    }

    public function testGetBillingByOrderWithPackId(): void
    {
        $client = $this->createMockClient([
            '/group/ML/order/details' => ['results' => [], 'total' => 0],
        ]);

        $service = $this->buildService($client);
        $result = $service->getBillingByOrder(['111', '222'], 'PACK001');

        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['results']);
    }

    // ===========================
    // getFlexShippingBillingDetails
    // ===========================

    public function testGetFlexShippingBillingDetailsSuccess(): void
    {
        $client = $this->createMockClient([
            '/group/ML/flex/details' => [
                'total' => 1,
                'results' => [
                    [
                        'charge_info' => [
                            'detail_id' => 'FLEX001',
                            'detail_amount' => 18.50,
                            'detail_type' => 'charge',
                        ],
                        'shipping_info' => [
                            'shipping_id' => 'SHP001',
                            'receiver_shipping_cost' => 18.50,
                            'order' => [
                                'order_id' => '777',
                                'buyer_nickname' => 'BUYER_FLEX',
                                'total_amount' => 300.0,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getFlexShippingBillingDetails('2026-04-01');

        $this->assertCount(1, $result['results']);
        $this->assertSame('FLEX001', $result['results'][0]['detail_id']);
        $this->assertSame(18.50, $result['results'][0]['detail_amount']);
        $this->assertSame('777', $result['results'][0]['order_id']);
    }

    public function testGetFlexShippingBillingDetailsApiError(): void
    {
        $client = $this->createMockClient([
            '/group/ML/flex/details' => ['error' => 'forbidden', 'message' => 'Sem acesso a Flex'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getFlexShippingBillingDetails('2026-04-01');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Sem acesso a Flex', $result['error']);
    }

    // ===========================
    // getBuyerBillingInfo
    // ===========================

    public function testGetBuyerBillingInfoSuccess(): void
    {
        $client = $this->createMockClient([
            '/orders/' => [
                'buyer' => [
                    'id' => 55,
                    'nickname' => 'COMPRADOR_TESTE',
                    'billing_info' => [
                        'doc_type' => 'CPF',
                        'doc_number' => '123.456.789-00',
                        'first_name' => 'João',
                        'last_name' => 'Silva',
                        'additional_info' => [],
                    ],
                ],
                'shipping' => ['receiver_address' => ['city' => 'Araraquara']],
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getBuyerBillingInfo('ORDER123');

        $this->assertSame('ORDER123', $result['order_id']);
        $this->assertSame(55, $result['buyer_id']);
        $this->assertSame('CPF', $result['billing_info']['doc_type']);
        $this->assertSame('João', $result['billing_info']['first_name']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testGetBuyerBillingInfoApiError(): void
    {
        $client = $this->createMockClient([
            '/orders/' => ['error' => 'not_found', 'message' => 'Order não encontrada'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getBuyerBillingInfo('ORDER999');

        $this->assertArrayHasKey('error', $result);
        $this->assertNull($result['data']);
    }

    public function testGetBuyerBillingInfoNoBillingData(): void
    {
        $client = $this->createMockClient([
            '/orders/' => [
                'buyer' => ['id' => 55, 'nickname' => 'TEST', 'billing_info' => []],
                'shipping' => [],
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getBuyerBillingInfo('ORDER123');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('não disponíveis', $result['error']);
    }

    // ===========================
    // getPerceptionsSummary
    // ===========================

    public function testGetPerceptionsSummarySuccess(): void
    {
        $client = $this->createMockClient([
            'perceptions/summary' => [
                'summary' => [
                    [
                        'document_id' => 'P001',
                        'amount' => 12.50,
                        'taxable_amount' => 250.0,
                        'aliquot' => 5.0,
                        'coefficient' => 1.0,
                        'status' => 'approved',
                        'tax_type' => 'CIVA',
                        'tax_ids' => [],
                    ],
                ],
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getPerceptionsSummary('2026-04-01');

        $this->assertCount(1, $result['results']);
        $this->assertSame(12.50, $result['results'][0]['amount']);
        $this->assertSame(5.0, $result['results'][0]['aliquot']);
        $this->assertSame('approved', $result['results'][0]['status']);
        $this->assertSame('ML', $result['group']);
    }

    public function testGetPerceptionsSummaryApiError(): void
    {
        $client = $this->createMockClient([
            'perceptions/summary' => ['error' => 'forbidden', 'message' => 'Percepciones indisponível'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getPerceptionsSummary('2026-04-01', 'MP');

        $this->assertArrayHasKey('error', $result);
        $this->assertEmpty($result['results']);
    }

    // ===========================
    // getPerceptionsDetails
    // ===========================

    public function testGetPerceptionsDetailsSuccess(): void
    {
        $client = $this->createMockClient([
            'perceptions/details' => [
                'total' => 1,
                'offset' => 0,
                'results' => [
                    [
                        'detail_id' => 'PD001',
                        'taxable_amount' => 100.0,
                        'aliquot' => 5.0,
                        'tax_amount' => 5.0,
                        'amount' => 5.0,
                        'gross_amount' => 105.0,
                    ],
                ],
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getPerceptionsDetails('ML', 123, 'CIVA');

        $this->assertCount(1, $result['results']);
        $this->assertSame(5.0, $result['results'][0]['tax_amount']);
        $this->assertSame('ML', $result['group']);
        $this->assertSame(123, $result['document_id']);
    }

    public function testGetPerceptionsDetailsApiError(): void
    {
        $client = $this->createMockClient([
            'perceptions/details' => ['error' => 'not_found', 'message' => 'Documento inválido'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getPerceptionsDetails('ML', 0, 'CIVA');

        $this->assertArrayHasKey('error', $result);
        $this->assertEmpty($result['results']);
    }

    // ===========================
    // getPaymentReport
    // ===========================

    public function testGetPaymentReportSuccess(): void
    {
        $client = $this->createMockClient([
            '/payment/details' => [
                'results' => [
                    [
                        'payment_info' => [
                            'payment_id' => 'PAY100',
                            'payment_date' => '2026-04-01',
                            'payment_amount' => 500.0,
                            'amount_in_this_period' => 500.0,
                            'payment_status' => 'approved',
                        ],
                    ],
                ],
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getPaymentReport('2026-04-01', 50, 0);

        $this->assertCount(1, $result['results']);
        $this->assertSame('PAY100', $result['results'][0]['payment_id']);
        $this->assertSame(500.0, $result['results'][0]['payment_amount']);
        $this->assertSame('2026-04-01', $result['period']);
    }

    public function testGetPaymentReportApiError(): void
    {
        $client = $this->createMockClient([
            '/payment/details' => ['error' => 'server_error', 'message' => 'Indisponível'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getPaymentReport('2026-04-01');

        $this->assertArrayHasKey('error', $result);
        $this->assertEmpty($result['results']);
    }

    // ===========================
    // getPaymentChargesDetail
    // ===========================

    public function testGetPaymentChargesDetailSuccess(): void
    {
        $client = $this->createMockClient([
            '/billing/integration/payment/' => [
                'payment_details' => [
                    [
                        'payment_info' => [
                            'payment_id' => 'P999',
                            'payment_date' => '2026-04-01',
                            'association_amount' => 150.0,
                            'payment_amount' => 300.0,
                        ],
                        'charge_info' => [
                            'detail_id' => 'CH001',
                            'detail_description' => 'Comissão',
                            'detail_date' => '2026-04-01',
                        ],
                    ],
                ],
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getPaymentChargesDetail('P999');

        $this->assertCount(1, $result['results']);
        $this->assertSame('P999', $result['results'][0]['payment_id']);
        $this->assertSame('CH001', $result['results'][0]['charge_detail_id']);
        $this->assertSame('P999', $result['payment_id']);
    }

    public function testGetPaymentChargesDetailApiError(): void
    {
        $client = $this->createMockClient([
            '/billing/integration/payment/' => ['error' => 'not_found', 'message' => 'Pagamento não encontrado'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getPaymentChargesDetail('XPTO');

        $this->assertArrayHasKey('error', $result);
        $this->assertEmpty($result['results']);
    }

    // ===========================
    // getFeesBreakdown — DB dependent
    // ===========================

    public function testGetFeesBreakdownWithData(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetch')->willReturn([
            'total_orders' => 5,
            'gross_revenue' => 1000.0,
            'ml_commission' => 80.0,
            'payment_fees' => 30.0,
            'fixed_fees' => 10.0,
            'shipping_cost' => 50.0,
        ]);

        $mockDb = $this->createMock(\PDO::class);
        $mockDb->method('prepare')->willReturn($mockStmt);

        $client = $this->createMock(MercadoLivreClient::class);
        $service = $this->buildService($client, $mockDb);

        $result = $service->getFeesBreakdown('2026-04-01', '2026-04-30');

        $this->assertSame(1000.0, $result['gross_revenue']);
        $this->assertSame(80.0, $result['fees']['ml_commission']);
        $this->assertSame(30.0, $result['fees']['payment_fees']);
        $this->assertSame(10.0, $result['fees']['fixed_fees']);
        $this->assertSame(120.0, $result['fees']['total']);
        $this->assertSame(12.0, $result['fee_rate']); // (120/1000)*100
        $this->assertSame(5, $result['total_orders']);
        $this->assertCount(3, $result['breakdown_by_type']);
    }

    public function testGetFeesBreakdownZeroRevenue(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetch')->willReturn([
            'total_orders' => 0,
            'gross_revenue' => 0,
            'ml_commission' => 0,
            'payment_fees' => 0,
            'fixed_fees' => 0,
            'shipping_cost' => 0,
        ]);

        $mockDb = $this->createMock(\PDO::class);
        $mockDb->method('prepare')->willReturn($mockStmt);

        $client = $this->createMock(MercadoLivreClient::class);
        $service = $this->buildService($client, $mockDb);

        $result = $service->getFeesBreakdown('2026-04-01', '2026-04-30');

        $this->assertSame(0.0, $result['gross_revenue']);
        $this->assertSame(0.0, $result['fee_rate']); // division by zero guard
        $this->assertSame('2026-04-01', $result['period']['start']);
    }

    // ===========================
    // getBillingPeriodSummary — aggregation logic
    // ===========================

    public function testGetBillingPeriodSummaryAggregatesAllSources(): void
    {
        $client = $this->createMockClient([
            '/group/ML/details' => [
                'results' => [
                    ['charge_info' => ['detail_amount' => 100.0, 'detail_sub_type' => 'COMMISSION'], 'sales_info' => [[]], 'items_info' => [[]], 'shipping_info' => [], 'discount_info' => []],
                    ['charge_info' => ['detail_amount' => 50.0, 'detail_sub_type' => 'FIXED_FEE'], 'sales_info' => [[]], 'items_info' => [[]], 'shipping_info' => [], 'discount_info' => []],
                ],
            ],
            '/group/MP/details' => [
                'results' => [
                    ['charge_info' => ['detail_amount' => 20.0], 'operation_info' => [], 'perception_info' => []],
                ],
            ],
            '/group/ML/full/details' => [
                'results' => [
                    ['charge_info' => ['detail_amount' => 30.0], 'fulfillment_info' => ['fulfillment_type' => 'STORAGE'], 'items_info' => [[]]],
                ],
            ],
            '/group/ML/flex/details' => [
                'results' => [
                    ['charge_info' => ['detail_amount' => 15.0], 'shipping_info' => ['order' => []]],
                ],
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getBillingPeriodSummary('2026-04-01');

        $this->assertSame('2026-04-01', $result['period']);
        $this->assertSame(150.0, $result['summary']['mercado_libre']['total']);
        $this->assertSame(2, $result['summary']['mercado_libre']['count']);
        $this->assertSame(20.0, $result['summary']['mercado_pago']['total']);
        $this->assertSame(15.0, $result['summary']['flex_shipping']['total']);
        $this->assertSame(215.0, $result['summary']['grand_total']);
        $this->assertArrayHasKey('generated_at', $result);
    }

    public function testGetBillingPeriodSummaryEmptySources(): void
    {
        $client = $this->createMockClient([
            '/group/ML/details' => ['results' => []],
            '/group/MP/details' => ['results' => []],
            '/group/ML/full/details' => ['results' => []],
            '/group/ML/flex/details' => ['results' => []],
        ]);

        $service = $this->buildService($client);
        $result = $service->getBillingPeriodSummary('2026-04-01');

        $this->assertSame(0.0, $result['summary']['grand_total']);
        $this->assertSame(0, $result['summary']['mercado_libre']['count']);
    }
}
