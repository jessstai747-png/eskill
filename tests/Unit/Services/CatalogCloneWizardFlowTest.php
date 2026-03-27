<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CatalogCloneService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Teste de integração do fluxo do wizard de clonagem por seller:
 *   searchSeller → listSellerItems → getSellerSummary → createSellerBatchJob
 *
 * Cobre também o mecanismo de snapshot/cache introduzido na tarefa 3.5.
 *
 * @covers \App\Services\CatalogCloneService
 */
class CatalogCloneWizardFlowTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(CatalogCloneService::class);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function newInstance(): CatalogCloneService
    {
        return $this->reflection->newInstanceWithoutConstructor();
    }

    /**
     * Injeta um PDO mock como propriedade $db da instância, via Reflection.
     *
     * @param CatalogCloneService $instance
     * @param PDO&MockObject      $pdo
     */
    private function injectPdo(CatalogCloneService $instance, PDO $pdo): void
    {
        $prop = $this->reflection->getProperty('db');
        $prop->setAccessible(true);
        $prop->setValue($instance, $pdo);
    }

    /**
     * Invoca um método privado da instância fornecida.
     *
     * @param CatalogCloneService $instance
     * @param string              $methodName
     * @param array<int, mixed>   $args
     */
    private function invokePrivate(CatalogCloneService $instance, string $methodName, array $args = []): mixed
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($instance, $args);
    }

    /** Cria um PDOStatement mock configurável via método() do MockObject. */
    private function mockStatement(): MockObject
    {
        return $this->createMock(PDOStatement::class);
    }

    // =========================================================================
    // Estrutural: novos métodos privados existem
    // =========================================================================

    public function testGetSellerSnapshotMethodExists(): void
    {
        $this->assertTrue($this->reflection->hasMethod('getSellerSnapshot'));
        $method = $this->reflection->getMethod('getSellerSnapshot');
        $this->assertTrue($method->isPrivate());
    }

    public function testSaveSellerSnapshotMethodExists(): void
    {
        $this->assertTrue($this->reflection->hasMethod('saveSellerSnapshot'));
        $method = $this->reflection->getMethod('saveSellerSnapshot');
        $this->assertTrue($method->isPrivate());
    }

    public function testGetSellerSnapshotHasCorrectSignature(): void
    {
        $method = $this->reflection->getMethod('getSellerSnapshot');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertSame('sellerId', $params[0]->getName());
        $this->assertSame('filters', $params[1]->getName());
    }

    public function testSaveSellerSnapshotHasCorrectSignature(): void
    {
        $method = $this->reflection->getMethod('saveSellerSnapshot');
        $params = $method->getParameters();

        $this->assertCount(4, $params);
        $this->assertSame('sellerId', $params[0]->getName());
        $this->assertSame('filters', $params[1]->getName());
        $this->assertSame('data', $params[2]->getName());
        $this->assertSame('ttlSeconds', $params[3]->getName());
        $this->assertSame(3600, $params[3]->getDefaultValue());
    }

    // =========================================================================
    // Behavioral: getSellerSnapshot — cache miss
    // =========================================================================

    public function testGetSellerSnapshotReturnNullOnMiss(): void
    {
        $stmt = $this->mockStatement();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $instance = $this->newInstance();
        $this->injectPdo($instance, $pdo);

        $result = $this->invokePrivate($instance, 'getSellerSnapshot', ['12345678', []]);
        $this->assertNull($result);
    }

    public function testGetSellerSnapshotReturnsParsedArrayOnHit(): void
    {
        $data = ['status' => 'success', 'total' => 300, 'items' => [['id' => 'MLB001']]];

        $stmt = $this->mockStatement();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['snapshot_data' => json_encode($data)]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $instance = $this->newInstance();
        $this->injectPdo($instance, $pdo);

        $result = $this->invokePrivate($instance, 'getSellerSnapshot', ['12345678', ['limit' => 50]]);
        $this->assertIsArray($result);
        $this->assertSame('success', $result['status']);
        $this->assertSame(300, $result['total']);
    }

    public function testGetSellerSnapshotReturnNullIfJsonIsInvalid(): void
    {
        $stmt = $this->mockStatement();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['snapshot_data' => 'not-valid-json']);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $instance = $this->newInstance();
        $this->injectPdo($instance, $pdo);

        $result = $this->invokePrivate($instance, 'getSellerSnapshot', ['12345678', []]);
        $this->assertNull($result);
    }

    public function testGetSellerSnapshotReturnNullOnDbException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \RuntimeException('DB failure'));

        $instance = $this->newInstance();
        $this->injectPdo($instance, $pdo);

        // Não deve propagar exceção — deve retornar null silenciosamente
        $result = $this->invokePrivate($instance, 'getSellerSnapshot', ['12345678', []]);
        $this->assertNull($result);
    }

    // =========================================================================
    // Behavioral: getSellerSnapshot — hash consistency
    // =========================================================================

    public function testGetSellerSnapshotUsesConsistentHashForSameFilters(): void
    {
        $capturedSql = null;
        $capturedArgs = null;

        $stmt = $this->mockStatement();
        $stmt->method('execute')->willReturnCallback(function (array $args) use (&$capturedArgs): bool {
            $capturedArgs = $args;
            return true;
        });
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt): MockObject {
            $capturedSql = $sql;
            return $stmt;
        });

        $instance = $this->newInstance();
        $this->injectPdo($instance, $pdo);

        $filters = ['limit' => 50, 'category' => 'MLB1234'];
        $this->invokePrivate($instance, 'getSellerSnapshot', ['99887766', $filters]);

        $expectedHash = hash('sha256', json_encode($filters));
        $this->assertSame('99887766', $capturedArgs[0]);
        $this->assertSame($expectedHash, $capturedArgs[1]);
    }

    public function testGetSellerSnapshotHashDiffersForDifferentFilters(): void
    {
        $capturedArgs = [];

        $stmt = $this->mockStatement();
        $stmt->method('execute')->willReturnCallback(function (array $args) use (&$capturedArgs): bool {
            $capturedArgs[] = $args;
            return true;
        });
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $instance = $this->newInstance();
        $this->injectPdo($instance, $pdo);

        $this->invokePrivate($instance, 'getSellerSnapshot', ['123', ['limit' => 50]]);
        $this->invokePrivate($instance, 'getSellerSnapshot', ['123', ['limit' => 100]]);

        $this->assertNotSame($capturedArgs[0][1], $capturedArgs[1][1], 'Hashes devem diferir para filtros diferentes');
    }

    // =========================================================================
    // Behavioral: saveSellerSnapshot
    // =========================================================================

    public function testSaveSellerSnapshotExecutesUpsertSql(): void
    {
        $capturedSql = null;
        $capturedArgs = null;

        $stmt = $this->mockStatement();
        $stmt->method('execute')->willReturnCallback(function (array $args) use (&$capturedArgs): bool {
            $capturedArgs = $args;
            return true;
        });

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use (&$capturedSql, $stmt): MockObject {
            $capturedSql = $sql;
            return $stmt;
        });

        $instance = $this->newInstance();
        $this->injectPdo($instance, $pdo);

        $data = ['items' => [['id' => 'MLB001'], ['id' => 'MLB002']], 'total' => 2];
        $this->invokePrivate($instance, 'saveSellerSnapshot', ['12345678', ['brand' => 'Honda'], $data, 1800]);

        $this->assertIsString($capturedSql);
        $this->assertStringContainsStringIgnoringCase('INSERT INTO seller_catalog_snapshots', (string)$capturedSql);
        $this->assertStringContainsStringIgnoringCase('ON DUPLICATE KEY UPDATE', (string)$capturedSql);

        // Seller ID é sempre o primeiro argumento
        $this->assertSame('12345678', $capturedArgs[0]);
        // Contagem de itens (index 4)
        $this->assertSame(2, $capturedArgs[4]);
    }

    public function testSaveSellerSnapshotUsesCustomTtl(): void
    {
        $capturedArgs = null;

        $stmt = $this->mockStatement();
        $stmt->method('execute')->willReturnCallback(function (array $args) use (&$capturedArgs): bool {
            $capturedArgs = $args;
            return true;
        });

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $instance = $this->newInstance();
        $this->injectPdo($instance, $pdo);

        $before = time() + 7200;
        $this->invokePrivate($instance, 'saveSellerSnapshot', ['12345678', [], [], 7200]);
        $after = time() + 7200;

        // expires_at está na posição 5 do array de args
        $expiresAt = strtotime($capturedArgs[5]);
        $this->assertGreaterThanOrEqual($before, $expiresAt);
        $this->assertLessThanOrEqual($after, $expiresAt);
    }

    public function testSaveSellerSnapshotHandlesDbExceptionSilently(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new \RuntimeException('DB failure'));

        $instance = $this->newInstance();
        $this->injectPdo($instance, $pdo);

        // Não deve propagar exceção
        $this->invokePrivate($instance, 'saveSellerSnapshot', ['12345678', [], ['items' => []]]);
        $this->assertTrue(true); // chegou aqui = sem exceção
    }

    // =========================================================================
    // Behavioral: createSellerBatchJob — guardrails (wizard flow)
    // =========================================================================

    public function testWizardFlowBatchJobDefaultGuardrails(): void
    {
        /** @var CatalogCloneService&MockObject $mock */
        $mock = $this->getMockBuilder(CatalogCloneService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createBatchJob'])
            ->getMock();

        $captured = [];
        $mock->expects($this->once())
            ->method('createBatchJob')
            ->willReturnCallback(function (array $p) use (&$captured): array {
                $captured = $p;
                return ['job_id' => 'wiz_test_001'];
            });

        $result = $mock->createSellerBatchJob([
            'target_account_id' => 7,
            'source_seller_id'  => '12345678',
            'filters'           => ['brand' => 'Yamaha'],
        ]);

        $this->assertSame('wiz_test_001', $result['job_id']);
        $this->assertFalse($captured['options']['include_description']);
        $this->assertFalse($captured['options']['include_pictures']);
        $this->assertSame('seller', $captured['source_type']);
        $this->assertSame('Yamaha', $captured['options']['seller_filters']['brand']);
    }

    public function testWizardFlowBatchJobSellerIdStripped(): void
    {
        /** @var CatalogCloneService&MockObject $mock */
        $mock = $this->getMockBuilder(CatalogCloneService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createBatchJob'])
            ->getMock();

        $captured = [];
        $mock->method('createBatchJob')
            ->willReturnCallback(function (array $p) use (&$captured): array {
                $captured = $p;
                return ['job_id' => 'wiz_test_002'];
            });

        $mock->createSellerBatchJob([
            'target_account_id' => 7,
            'source_seller_id'  => 'MLB12345678',
        ]);

        $this->assertSame('12345678', $captured['source_seller_id']);
    }

    public function testWizardFlowBatchJobItemIdsForwarded(): void
    {
        /** @var CatalogCloneService&MockObject $mock */
        $mock = $this->getMockBuilder(CatalogCloneService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createBatchJob'])
            ->getMock();

        $captured = [];
        $mock->method('createBatchJob')
            ->willReturnCallback(function (array $p) use (&$captured): array {
                $captured = $p;
                return ['job_id' => 'wiz_test_003'];
            });

        $itemIds = ['MLB101', 'MLB202', 'MLB303'];
        $mock->createSellerBatchJob([
            'target_account_id' => 7,
            'source_seller_id'  => '12345678',
            'item_ids'          => $itemIds,
        ]);

        $this->assertSame($itemIds, $captured['item_ids']);
    }

    public function testWizardFlowBatchJobThrowsOnInvalidSeller(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $instance = $this->newInstance();
        $instance->createSellerBatchJob([
            'target_account_id' => 7,
            'source_seller_id'  => 'sem-numeros',
        ]);
    }

    public function testWizardFlowBatchJobThrowsOnMissingTarget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $instance = $this->newInstance();
        $instance->createSellerBatchJob([
            'source_seller_id' => '12345678',
        ]);
    }
}
