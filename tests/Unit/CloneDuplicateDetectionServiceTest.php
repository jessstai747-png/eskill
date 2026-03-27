<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Services\CloneDuplicateDetectionService;
use App\Database;

class CloneDuplicateDetectionServiceTest extends TestCase
{
    private CloneDuplicateDetectionService $service;
    private PDO $db;
    private int $testAccountId = 999;

    protected function setUp(): void
    {
        try {
            $this->db = Database::getInstance();
            $this->db->query('SELECT 1 FROM catalog_clone_jobs LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'Banco de teste indisponível ou schema ausente para CloneDuplicateDetectionServiceTest: ' . $e->getMessage()
            );
        }

        $this->service = new CloneDuplicateDetectionService();
    }

    /**
     * Testa detecção quando item nunca foi clonado
     */
    public function testNoDuplicateForNewItem(): void
    {
        $result = $this->service->checkDuplicate('MLB999999999', $this->testAccountId);

        $this->assertFalse($result['is_duplicate']);
        $this->assertEmpty($result['existing_items']);
        $this->assertEquals('proceed', $result['recommendation']);
    }

    /**
     * Testa batch check retorna mapa correto
     */
    public function testBatchCheckDuplicates(): void
    {
        $itemIds = ['MLB111', 'MLB222', 'MLB333'];

        $result = $this->service->batchCheckDuplicates($itemIds, $this->testAccountId);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);

        foreach ($itemIds as $itemId) {
            $this->assertArrayHasKey($itemId, $result);
            $this->assertArrayHasKey('is_duplicate', $result[$itemId]);
            $this->assertArrayHasKey('clone_count', $result[$itemId]);
        }
    }

    /**
     * Testa resolução de duplicata com ação 'skip'
     */
    public function testResolveDuplicateWithSkip(): void
    {
        $result = $this->service->resolveDuplicate(
            'MLB123',
            $this->testAccountId,
            'skip'
        );

        $this->assertEquals('skipped', $result['status']);
        $this->assertStringContainsString('pulado', $result['reason']);
    }

    /**
     * Testa resolução de duplicata com ação 'update'
     */
    public function testResolveDuplicateWithUpdate(): void
    {
        $result = $this->service->resolveDuplicate(
            'MLB123',
            $this->testAccountId,
            'update'
        );

        $this->assertEquals('update_required', $result['status']);
        $this->assertArrayHasKey('action', $result);
    }

    /**
     * Testa resolução de duplicata com ação 'create_new'
     */
    public function testResolveDuplicateWithCreateNew(): void
    {
        $result = $this->service->resolveDuplicate(
            'MLB123',
            $this->testAccountId,
            'create_new',
            ['title_suffix' => ' v2', 'sku_suffix' => '_v2']
        );

        $this->assertEquals('proceed', $result['status']);
        $this->assertArrayHasKey('modifications', $result);
        $this->assertEquals(' v2', $result['modifications']['title_suffix']);
        $this->assertEquals('_v2', $result['modifications']['sku_suffix']);
    }

    /**
     * Testa verificação de SKU duplicado
     */
    public function testCheckSkuDuplicate(): void
    {
        // 'SKU-UNIQUE-999' is always new in a clean test DB → explicit non-duplicate assertions
        $result = $this->service->checkSkuDuplicate('SKU-UNIQUE-999', $this->testAccountId);

        $this->assertArrayHasKey('is_duplicate', $result);
        $this->assertArrayHasKey('recommendation', $result);
        $this->assertIsBool($result['is_duplicate']);
        $this->assertIsString($result['recommendation']);
        $this->assertFalse($result['is_duplicate'], 'SKU-UNIQUE-999 should not be a duplicate in a clean DB');
    }

    /**
     * Testa estatísticas de duplicatas
     */
    public function testGetDuplicateStats(): void
    {
        $stats = $this->service->getDuplicateStats($this->testAccountId, 30);

        $this->assertArrayHasKey('summary', $stats);
        $this->assertArrayHasKey('top_duplicates', $stats);
        $this->assertArrayHasKey('period_days', $stats);

        $summary = $stats['summary'];
        $this->assertArrayHasKey('total_source_items', $summary);
        $this->assertArrayHasKey('total_clones', $summary);
        $this->assertArrayHasKey('duplicate_clones', $summary);
    }

    /**
     * Testa registro de novo clone
     */
    public function testRegisterClone(): void
    {
        $sourceId = 'MLB_TEST_' . time();
        $targetId = 'MLB_CLONE_' . time();

        $result = $this->service->registerClone(
            $sourceId,
            $targetId,
            $this->testAccountId,
            'test_job_' . time()
        );

        $this->assertTrue($result);

        // Verificar se foi registrado
        $check = $this->service->checkDuplicate($sourceId, $this->testAccountId);
        $this->assertTrue($check['is_duplicate']);

        // Limpar
        $this->service->markCloneInactive($targetId);
    }

    /**
     * Testa marcação de clone como inativo
     */
    public function testMarkCloneInactive(): void
    {
        $targetId = 'MLB_TEST_INACTIVE_' . time();

        // Registrar primeiro
        $this->service->registerClone(
            'MLB_SOURCE',
            $targetId,
            $this->testAccountId
        );

        // Marcar como inativo
        $result = $this->service->markCloneInactive($targetId);
        $this->assertTrue($result);
    }

    /**
     * Testa limpeza de registros antigos
     */
    public function testCleanupOldInactiveClones(): void
    {
        $result = $this->service->cleanupOldInactiveClones(90);

        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * Testa que batch check com array vazio retorna array vazio
     */
    public function testBatchCheckWithEmptyArray(): void
    {
        $result = $this->service->batchCheckDuplicates([], $this->testAccountId);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Testa opções de resolução em resultado de duplicata
     */
    public function testDuplicateResultHasOptions(): void
    {
        // Register 3 clones for the same source to trigger severity='high' (requires count >= 3)
        $t = time();
        $sourceId = 'MLB_TEST_OPTIONS_' . $t;
        $cloneIds = [
            'MLB_CLONE_A_' . $t,
            'MLB_CLONE_B_' . $t,
            'MLB_CLONE_C_' . $t,
        ];
        foreach ($cloneIds as $cloneId) {
            $this->service->registerClone($sourceId, $cloneId, $this->testAccountId);
        }

        $result = $this->service->checkDuplicate($sourceId, $this->testAccountId);

        $this->assertTrue($result['is_duplicate'], '3 registered clones must be detected as duplicate');
        $this->assertEquals('high', $result['severity'], '3+ clones must set severity=high');
        $this->assertArrayHasKey('options', $result);
        $this->assertArrayHasKey('skip', $result['options']);
        $this->assertArrayHasKey('update', $result['options']);
        $this->assertArrayHasKey('create_new', $result['options']);

        // Cleanup
        foreach ($cloneIds as $cloneId) {
            $this->service->markCloneInactive($cloneId);
        }
    }
}
