<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MercadoLivreClient;
use App\Services\ShipmentSyncService;
use App\Services\StructuredLogService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\ShipmentSyncService
 */
class ShipmentSyncServiceTest extends TestCase
{
    /**
     * @return StructuredLogService&MockObject
     */
    private function createMockLogger(): StructuredLogService
    {
        return $this->getMockBuilder(StructuredLogService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['warning'])
            ->getMock();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function createPdoWithRows(array $rows): PDO
    {
        $statement = $this->getMockBuilder(PDOStatement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute', 'fetchAll'])
            ->getMock();

        $statement->method('execute')->willReturn(true);
        $statement->method('fetchAll')->willReturn($rows);

        $pdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare'])
            ->getMock();

        $pdo->method('prepare')->willReturn($statement);

        return $pdo;
    }

    /**
     * @return MercadoLivreClient&MockObject
     */
    private function createMockClient(): MercadoLivreClient
    {
        return $this->getMockBuilder(MercadoLivreClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
    }

    private function buildService(
        ?int $accountId,
        ?MercadoLivreClient $client,
        ?PDO $db
    ): ShipmentSyncService {
        return new ShipmentSyncService(
            $accountId,
            $client,
            $db,
            $this->createMockLogger(),
            true
        );
    }

    public function testDeveRetornarErroQuandoDbIndisponivel(): void
    {
        $service = $this->buildService(123, $this->createMockClient(), null);
        $result = $service->syncForAccount(123);

        $this->assertFalse($result['success']);
        $this->assertSame('db_unavailable', $result['error']);
    }

    public function testDeveRetornarSucessoQuandoSemEnvios(): void
    {
        $pdo = $this->createPdoWithRows([]);
        $client = $this->createMockClient();
        $client->expects($this->never())->method('get');

        $service = $this->buildService(123, $client, $pdo);
        $result = $service->syncForAccount(123);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['found']);
        $this->assertSame(0, $result['synced']);
        $this->assertSame(0, $result['errors']);
        $this->assertSame(0, $result['orders_scanned']);
    }

    public function testDeveRegistrarErroQuandoApiFalha(): void
    {
        $orderData = json_encode([
            'id' => 'ORDER123',
            'shipping' => ['id' => 'SHIP123'],
        ], JSON_THROW_ON_ERROR);

        $pdo = $this->createPdoWithRows([
            [
                'ml_order_id' => 'ORDER123',
                'order_data' => $orderData,
                'date_created' => '2026-03-01 10:00:00',
            ],
        ]);

        $client = $this->createMockClient();
        $client->expects($this->once())
            ->method('get')
            ->with('/shipments/SHIP123')
            ->willReturn([
                'error' => 'unauthorized',
                'message' => 'Token expirado',
            ]);

        $service = $this->buildService(123, $client, $pdo);
        $result = $service->syncForAccount(123, 30, ['sleep_us' => 0]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['found']);
        $this->assertSame(0, $result['synced']);
        $this->assertSame(1, $result['errors']);
        $this->assertSame('unauthorized', $result['error_details'][0]['error']);
    }
}
