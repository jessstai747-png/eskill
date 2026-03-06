<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Quality;

use PHPUnit\Framework\TestCase;
use App\Services\Quality\QualityScoreService;
use App\Services\MercadoLivreClient;

/**
 * @covers \App\Services\Quality\QualityScoreService
 */
class QualityScoreServiceTest extends TestCase
{
    private function buildService(MercadoLivreClient $mockClient): QualityScoreService
    {
        $ref = new \ReflectionClass(QualityScoreService::class);
        $service = $ref->newInstanceWithoutConstructor();

        $clientProp = $ref->getProperty('client');
        $clientProp->setAccessible(true);
        $clientProp->setValue($service, $mockClient);

        // Inject CategoryService mock
        $catMock = $this->createMock(\App\Services\CategoryService::class);
        $catProp = $ref->getProperty('categoryService');
        $catProp->setAccessible(true);
        $catProp->setValue($service, $catMock);

        // Inject HealthCheckService mock
        $healthMock = $this->createMock(\App\Services\Quality\HealthCheckService::class);
        $healthMock->method('checkItemHealth')
            ->willReturn(['success' => true, 'health' => ['score' => 80]]);
        $healthProp = $ref->getProperty('healthCheck');
        $healthProp->setAccessible(true);
        $healthProp->setValue($service, $healthMock);

        return $service;
    }

    public function testCalculateQualityScoreSuccess(): void
    {
        $mock = $this->createMock(MercadoLivreClient::class);
        $mock->method('get')
            ->willReturnCallback(function (string $endpoint): array {
                if (str_contains($endpoint, '/description')) {
                    return ['plain_text' => 'Bagageiro para CG 160 Titan, reforçado e resistente.'];
                }
                return [
                    'id' => 'MLB123',
                    'title' => 'Bagageiro CG 160 Titan Reforçado',
                    'status' => 'active',
                    'price' => 189.90,
                    'pictures' => [['id' => 'p1'], ['id' => 'p2'], ['id' => 'p3']],
                    'attributes' => [['id' => 'BRAND', 'value_name' => 'AWA']],
                    'shipping' => ['free_shipping' => true],
                    'listing_type_id' => 'gold_special',
                    'sold_quantity' => 50,
                    'available_quantity' => 10,
                ];
            });

        $service = $this->buildService($mock);
        $result = $service->calculateQualityScore('MLB123');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('quality_score', $result);
        $this->assertIsArray($result['quality_score']);
        $this->assertArrayHasKey('total', $result['quality_score']);
    }

    public function testCalculateQualityScoreItemApiError(): void
    {
        $mock = $this->createMock(MercadoLivreClient::class);
        $mock->method('get')
            ->willReturn(['error' => 'not_found', 'message' => 'Item not found']);

        $service = $this->buildService($mock);
        $result = $service->calculateQualityScore('MLB999');

        $this->assertFalse($result['success']);
        $this->assertSame('Item not found', $result['error']);
    }

    public function testCalculateQualityScoreItemException(): void
    {
        $mock = $this->createMock(MercadoLivreClient::class);
        $mock->method('get')
            ->willThrowException(new \RuntimeException('API unreachable'));

        $service = $this->buildService($mock);
        $result = $service->calculateQualityScore('MLB123');

        $this->assertFalse($result['success']);
        $this->assertSame('API unreachable', $result['error']);
    }

    public function testCalculateQualityScoreDescriptionFailureGraceful(): void
    {
        $callCount = 0;
        $mock = $this->createMock(MercadoLivreClient::class);
        $mock->method('get')
            ->willReturnCallback(function (string $endpoint) use (&$callCount): array {
                $callCount++;
                if (str_contains($endpoint, '/description')) {
                    throw new \RuntimeException('Description API failed');
                }
                return [
                    'id' => 'MLB123',
                    'title' => 'Bagageiro CG 160',
                    'status' => 'active',
                    'price' => 189.90,
                    'pictures' => [['id' => 'p1']],
                    'attributes' => [],
                    'shipping' => ['free_shipping' => false],
                    'listing_type_id' => 'gold_special',
                    'sold_quantity' => 0,
                    'available_quantity' => 5,
                ];
            });

        $service = $this->buildService($mock);
        $result = $service->calculateQualityScore('MLB123');

        // Should succeed even with description failure (graceful degradation)
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('quality_score', $result);
    }
}
