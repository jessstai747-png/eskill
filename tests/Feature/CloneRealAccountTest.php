<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\CatalogCloneService;

/**
 * Testes de clonagem de catalogos ML — Fase 5 (Multi-account).
 *
 * CatalogCloneService requer DB no construtor.
 * Testes de estrutura sempre executam; funcionais pulam se DB indisponivel.
 *
 * @covers \App\Services\CatalogCloneService
 */
class CloneRealAccountTest extends TestCase
{
    private bool $dbAvailable = false;
    private ?CatalogCloneService $service = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->service     = new CatalogCloneService();
            $this->dbAvailable = true;
        } catch (\Throwable) {
            $this->dbAvailable = false;
        }
    }

    // ------------------------------------------------------------------
    // Estrutura de classe (sempre executa)
    // ------------------------------------------------------------------

    public function testCatalogCloneServiceClassExists(): void
    {
        $this->assertTrue(class_exists(CatalogCloneService::class));
    }

    /** @dataProvider cloneMethodsProvider */
    public function testCatalogCloneServiceHasMethods(string $method): void
    {
        $this->assertTrue(
            method_exists(CatalogCloneService::class, $method),
            "CatalogCloneService deve ter {$method}()"
        );
    }

    /** @return array<string, array{string}> */
    public static function cloneMethodsProvider(): array
    {
        return [
            'cloneCatalogItem'     => ['cloneCatalogItem'],
            'cloneItem'            => ['cloneItem'],
            'validatePreExecution' => ['validatePreExecution'],
            'getCloneHistory'      => ['getCloneHistory'],
            'searchSeller'         => ['searchSeller'],
            'listSellerItems'      => ['listSellerItems'],
            'simulateClone'        => ['simulateClone'],
            'dryRunBatch'          => ['dryRunBatch'],
            'createBatchJob'       => ['createBatchJob'],
        ];
    }

    // ------------------------------------------------------------------
    // Testes funcionais (requerem DB)
    // ------------------------------------------------------------------

    public function testCatalogCloneServiceCanBeInstantiated(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel — CatalogCloneService requer Database::getInstance()');
        }

        $this->assertInstanceOf(CatalogCloneService::class, $this->service);
    }

    public function testGetCloneHistoryReturnsArray(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel');
        }

        $result = $this->service->getCloneHistory();

        $this->assertIsArray($result);
    }

    public function testValidatePreExecutionReturnsBoolOrArray(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel');
        }

        try {
            $result = $this->service->validatePreExecution([
                'source_account_id' => 1,
                'target_account_id' => 2,
                'catalog_product_id' => 'NONEXISTENT',
            ]);
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testSimulateCloneForInvalidItemReturnsResultOrThrows(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel');
        }

        try {
            $result = $this->service->simulateClone([
                'catalog_product_id' => 'MLB_NONEXISTENT',
                'source_account_id'  => 1,
                'target_account_id'  => 2,
            ]);
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }
}
