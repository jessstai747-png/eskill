<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Financial;

use PHPUnit\Framework\TestCase;
use App\Services\Financial\PaymentRefundService;
use App\Services\MercadoLivreClient;

/**
 * @covers \App\Services\Financial\PaymentRefundService
 */
class PaymentRefundServiceTest extends TestCase
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

    private function buildService(MercadoLivreClient $mockClient): PaymentRefundService
    {
        $ref = new \ReflectionClass(PaymentRefundService::class);
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
    // getPaymentDetails
    // ===========================

    public function testGetPaymentDetailsSuccess(): void
    {
        $client = $this->createMockClient([
            '/payments/' => [
                'id' => 12345,
                'status' => 'approved',
                'transaction_amount' => 150.50,
                'currency_id' => 'BRL',
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getPaymentDetails('12345');

        $this->assertSame(12345, $result['id']);
        $this->assertSame('approved', $result['status']);
        $this->assertSame(150.50, $result['transaction_amount']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testGetPaymentDetailsApiError(): void
    {
        $client = $this->createMockClient([
            '/payments/' => ['error' => 'not_found', 'message' => 'Payment not found'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getPaymentDetails('99999');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Payment not found', $result['error']);
    }

    public function testGetPaymentDetailsException(): void
    {
        $mock = $this->createMock(MercadoLivreClient::class);
        $mock->method('get')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $service = $this->buildService($mock);
        $result = $service->getPaymentDetails('12345');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Connection refused', $result['error']);
    }

    // ===========================
    // getCurrencyConversion
    // ===========================

    public function testGetCurrencyConversionSuccess(): void
    {
        $client = $this->createMockClient([
            'currency_conversions' => ['ratio' => 5.25],
        ]);

        $service = $this->buildService($client);
        $result = $service->getCurrencyConversion('USD', 'BRL');

        $this->assertSame('USD', $result['from']);
        $this->assertSame('BRL', $result['to']);
        $this->assertSame(5.25, $result['ratio']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testGetCurrencyConversionApiError(): void
    {
        $client = $this->createMockClient([
            'currency_conversions' => ['error' => 'invalid_currency'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getCurrencyConversion('XXX', 'BRL');

        $this->assertArrayHasKey('error', $result);
        $this->assertNull($result['ratio']);
    }

    public function testGetCurrencyConversionException(): void
    {
        $mock = $this->createMock(MercadoLivreClient::class);
        $mock->method('get')
            ->willThrowException(new \RuntimeException('Timeout'));

        $service = $this->buildService($mock);
        $result = $service->getCurrencyConversion('USD', 'BRL');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Timeout', $result['error']);
        $this->assertNull($result['ratio']);
    }

    // ===========================
    // getChargebackDetails
    // ===========================

    public function testGetChargebackDetailsSuccess(): void
    {
        $client = $this->createMockClient([
            '/v1/chargebacks/' => [
                'id' => 'CB-001',
                'amount' => 200.00,
                'currency' => 'BRL',
                'coverage_applied' => true,
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getChargebackDetails('CB-001');

        $this->assertSame('CB-001', $result['id']);
        $this->assertSame(200.0, $result['amount']);
        $this->assertTrue($result['coverage_applied']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function testGetChargebackDetailsApiError(): void
    {
        $client = $this->createMockClient([
            '/v1/chargebacks/' => ['error' => 'not_found'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getChargebackDetails('CB-999');

        $this->assertArrayHasKey('error', $result);
    }

    public function testGetChargebackDetailsException(): void
    {
        $mock = $this->createMock(MercadoLivreClient::class);
        $mock->method('get')
            ->willThrowException(new \RuntimeException('API unavailable'));

        $service = $this->buildService($mock);
        $result = $service->getChargebackDetails('CB-001');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('API unavailable', $result['error']);
    }
}
