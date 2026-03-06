<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Financial;

use PHPUnit\Framework\TestCase;
use App\Services\Financial\ClaimDisputeService;
use App\Services\MercadoLivreClient;

/**
 * @covers \App\Services\Financial\ClaimDisputeService
 */
class ClaimDisputeServiceTest extends TestCase
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

    private function buildService(MercadoLivreClient $mockClient): ClaimDisputeService
    {
        $ref = new \ReflectionClass(ClaimDisputeService::class);
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
    // getClaims
    // ===========================

    public function testGetClaimsSuccess(): void
    {
        $client = $this->createMockClient([
            'claims/search' => [
                'data' => [
                    [
                        'id' => 'CLM-001',
                        'resource_id' => 'ORD-001',
                        'status' => 'opened',
                        'type' => 'mediations',
                        'stage' => 'claim',
                        'players' => [['role' => 'complainant', 'type' => 'buyer', 'user_id' => '111']],
                        'date_created' => '2024-01-01T00:00:00Z',
                    ],
                ],
                'paging' => ['total' => 1],
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getClaims('opened');

        $this->assertArrayNotHasKey('error', $result);
        $this->assertCount(1, $result['results']);
        $this->assertSame('CLM-001', $result['results'][0]['claim_id']);
        $this->assertSame('opened', $result['status_filter']);
    }

    public function testGetClaimsApiError(): void
    {
        $client = $this->createMockClient([
            'claims/search' => ['error' => 'forbidden', 'message' => 'Access denied'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getClaims();

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Access denied', $result['error']);
        $this->assertSame([], $result['results']);
    }

    public function testGetClaimsException(): void
    {
        $mock = $this->createMock(MercadoLivreClient::class);
        $mock->method('get')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $service = $this->buildService($mock);
        $result = $service->getClaims();

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Connection refused', $result['error']);
        $this->assertSame([], $result['results']);
    }

    // ===========================
    // getClaimDetails
    // ===========================

    public function testGetClaimDetailsSuccess(): void
    {
        $client = $this->createMockClient([
            'claims/CLM-001' => [
                'id' => 'CLM-001',
                'status' => 'opened',
                'type' => 'mediations',
                'resolution' => null,
                'players' => [
                    ['role' => 'complainant', 'type' => 'buyer', 'user_id' => '111', 'available_actions' => [['action' => 'respond']]],
                ],
                'date_created' => '2024-01-01T00:00:00Z',
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getClaimDetails('CLM-001');

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame('CLM-001', $result['claim_id']);
        $this->assertSame('opened', $result['status']);
    }

    public function testGetClaimDetailsApiError(): void
    {
        $client = $this->createMockClient([
            'claims/CLM-999' => ['error' => 'not_found'],
        ]);

        $service = $this->buildService($client);
        $result = $service->getClaimDetails('CLM-999');

        $this->assertArrayHasKey('error', $result);
        $this->assertNull($result['data']);
    }

    public function testGetClaimDetailsException(): void
    {
        $mock = $this->createMock(MercadoLivreClient::class);
        $mock->method('get')
            ->willThrowException(new \RuntimeException('Timeout'));

        $service = $this->buildService($mock);
        $result = $service->getClaimDetails('CLM-001');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Timeout', $result['error']);
        $this->assertNull($result['data']);
    }

    // ===========================
    // getClaimReputationImpact
    // ===========================

    public function testGetClaimReputationImpactSuccess(): void
    {
        $client = $this->createMockClient([
            'affects-reputation' => [
                'affects_reputation' => 'affected',
                'has_incentive' => true,
                'due_date' => '2024-02-01',
            ],
        ]);

        $service = $this->buildService($client);
        $result = $service->getClaimReputationImpact('CLM-001');

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame('affected', $result['affects_reputation']);
        $this->assertTrue($result['has_incentive']);
    }

    public function testGetClaimReputationImpactException(): void
    {
        $mock = $this->createMock(MercadoLivreClient::class);
        $mock->method('get')
            ->willThrowException(new \RuntimeException('API down'));

        $service = $this->buildService($mock);
        $result = $service->getClaimReputationImpact('CLM-001');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('API down', $result['error']);
    }

    // ===========================
    // getReturnDetails
    // ===========================

    public function testGetReturnDetailsException(): void
    {
        $mock = $this->createMock(MercadoLivreClient::class);
        $mock->method('get')
            ->willThrowException(new \RuntimeException('Network error'));

        $service = $this->buildService($mock);
        $result = $service->getReturnDetails('CLM-001');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Network error', $result['error']);
        $this->assertNull($result['data']);
    }

    // ===========================
    // getClaimActionsHistory
    // ===========================

    public function testGetClaimActionsHistoryException(): void
    {
        $mock = $this->createMock(MercadoLivreClient::class);
        $mock->method('get')
            ->willThrowException(new \RuntimeException('Server error'));

        $service = $this->buildService($mock);
        $result = $service->getClaimActionsHistory('CLM-001');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Server error', $result['error']);
        $this->assertSame([], $result['results']);
    }
}
