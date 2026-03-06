<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Financial;

use PHPUnit\Framework\TestCase;
use App\Services\Financial\ShippingCostService;
use App\Services\MercadoLivreClient;

/**
 * @covers \App\Services\Financial\ShippingCostService
 */
class ShippingCostServiceTest extends TestCase
{
    private function createMockClient(array $getReturnMap = []): MercadoLivreClient
    {
        $mock = $this->createMock(MercadoLivreClient::class);

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

    private function buildService(MercadoLivreClient $mockClient): ShippingCostService
    {
        $ref = new \ReflectionClass(ShippingCostService::class);
        $service = $ref->newInstanceWithoutConstructor();

        $clientProp = $ref->getProperty('client');
        $clientProp->setAccessible(true);
        $clientProp->setValue($service, $mockClient);

        $accountIdProp = $ref->getProperty('accountId');
        $accountIdProp->setAccessible(true);
        $accountIdProp->setValue($service, 1);

        return $service;
    }

    // ===========================
    // getShipmentCosts
    // ===========================

    public function testGetShipmentCostsSuccess(): void
    {
        $client = $this->createMockClient([
            '/shipments/' => [
                'id' => 'SH-001',
                'status' => 'delivered',
                'mode' => 'me2',
                'logistic_type' => 'fulfillment',
                'seller_cost' => 15.50,
                'base_cost' => 20.00,
                'list_cost' => 25.00,
                'receiver_cost' => 0,
                'free_shipping' => true,
                'dimensions' => ['height' => 10, 'width' => 20, 'length' => 30, 'weight' => 500],
                'shipping_option' => ['name' => 'Normal', 'shipping_method_id' => 1, 'delivery_type' => 'delivery'],
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getShipmentCosts('SH-001');

        $this->assertSame('SH-001', $result['shipment_id']);
        $this->assertSame('delivered', $result['status']);
        $this->assertSame(15.50, $result['costs']['seller_cost']);
        $this->assertTrue($result['costs']['free_shipping']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testGetShipmentCostsApiError(): void
    {
        $client = $this->createMockClient([
            '/shipments/' => ['error' => 'not_found', 'message' => 'Shipment not found'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getShipmentCosts('SH-999');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Shipment not found', $result['error']);
    }

    public function testGetShipmentCostsException(): void
    {
        $mock = $this->createMock(MercadoLivreClient::class);
        $mock->method('get')
            ->willThrowException(new \RuntimeException('Connection timeout'));

        $service = $this->buildService($mock);
        $result = $service->getShipmentCosts('SH-001');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Connection timeout', $result['error']);
    }

    // ===========================
    // getOrderShipments
    // ===========================

    public function testGetOrderShipmentsSuccess(): void
    {
        $client = $this->createMockClient([
            '/orders/' => [
                ['id' => 'SH-001', 'status' => 'delivered', 'mode' => 'me2', 'seller_cost' => 10.0, 'free_shipping' => false],
                ['id' => 'SH-002', 'status' => 'shipped', 'mode' => 'me1', 'seller_cost' => 5.0, 'free_shipping' => true],
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getOrderShipments('ORD-001');

        $this->assertSame('ORD-001', $result['order_id']);
        $this->assertCount(2, $result['results']);
        $this->assertSame(2, $result['total']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testGetOrderShipmentsApiError(): void
    {
        $client = $this->createMockClient([
            '/orders/' => ['error' => 'not_found', 'message' => 'Order not found'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getOrderShipments('ORD-999');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame([], $result['results']);
    }

    public function testGetOrderShipmentsException(): void
    {
        $mock = $this->createMock(MercadoLivreClient::class);
        $mock->method('get')
            ->willThrowException(new \RuntimeException('Service unavailable'));

        $service = $this->buildService($mock);
        $result = $service->getOrderShipments('ORD-001');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Service unavailable', $result['error']);
        $this->assertSame([], $result['results']);
    }
}
