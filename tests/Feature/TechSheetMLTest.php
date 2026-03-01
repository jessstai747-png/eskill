<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\TechSheetService;

/**
 * Testes da ficha tecnica ML — Fase 4 (Atributos).
 *
 * TechSheetService requer DB no construtor.
 * Testes de estrutura sempre executam; testes funcionais pulam se DB indisponivel.
 *
 * @covers \App\Services\TechSheetService
 */
class TechSheetMLTest extends TestCase
{
    private bool $dbAvailable = false;
    private ?TechSheetService $service = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->service      = new TechSheetService(1);
            $this->dbAvailable  = true;
        } catch (\Throwable) {
            $this->dbAvailable = false;
        }
    }

    // ------------------------------------------------------------------
    // Estrutura de classe (sempre executa)
    // ------------------------------------------------------------------

    public function testTechSheetServiceClassExists(): void
    {
        $this->assertTrue(class_exists(TechSheetService::class));
    }

    /** @dataProvider techSheetMethodsProvider */
    public function testTechSheetServiceHasMethods(string $method): void
    {
        $this->assertTrue(
            method_exists(TechSheetService::class, $method),
            "TechSheetService deve ter {$method}()"
        );
    }

    /** @return array<string, array{string}> */
    public static function techSheetMethodsProvider(): array
    {
        return [
            'listItems'                   => ['listItems'],
            'getItem'                     => ['getItem'],
            'generateSuggestions'         => ['generateSuggestions'],
            'saveDecisions'               => ['saveDecisions'],
            'approvePendingByConfidence'   => ['approvePendingByConfidence'],
            'applyApproved'               => ['applyApproved'],
            'addSuggestions'              => ['addSuggestions'],
        ];
    }

    public function testSourceConstantsAreDefined(): void
    {
        $ref = new \ReflectionClass(TechSheetService::class);

        $constants = $ref->getConstants();

        $expected = ['SOURCE_TITLE', 'SOURCE_BENCHMARK', 'SOURCE_AI', 'SOURCE_INFERENCE', 'SOURCE_DEFAULT', 'SOURCE_MANUAL'];

        foreach ($expected as $const) {
            $this->assertArrayHasKey($const, $constants, "TechSheetService deve definir {$const}");
        }
    }

    public function testSourceConstantValues(): void
    {
        $this->assertSame('title',     TechSheetService::SOURCE_TITLE);
        $this->assertSame('benchmark', TechSheetService::SOURCE_BENCHMARK);
        $this->assertSame('ai',        TechSheetService::SOURCE_AI);
        $this->assertSame('inference', TechSheetService::SOURCE_INFERENCE);
        $this->assertSame('default',   TechSheetService::SOURCE_DEFAULT);
        $this->assertSame('manual',    TechSheetService::SOURCE_MANUAL);
    }

    public function testSafeSourcesIsSubsetOfAllSources(): void
    {
        $ref       = new \ReflectionClass(TechSheetService::class);
        $constants = $ref->getConstants();

        $this->assertArrayHasKey('SAFE_SOURCES', $constants);
        $this->assertArrayHasKey('ALL_SOURCES',  $constants);

        $safeSources = $constants['SAFE_SOURCES'];
        $allSources  = $constants['ALL_SOURCES'];

        $this->assertIsArray($safeSources);
        $this->assertIsArray($allSources);
        $this->assertNotEmpty($allSources);

        foreach ($safeSources as $source) {
            $this->assertContains($source, $allSources, "{$source} deve estar em ALL_SOURCES");
        }
    }

    // ------------------------------------------------------------------
    // Testes funcionais (requerem DB)
    // ------------------------------------------------------------------

    public function testTechSheetServiceCanBeInstantiated(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel — TechSheetService requer Database::getInstance()');
        }

        $this->assertInstanceOf(TechSheetService::class, $this->service);
    }

    public function testListItemsReturnsArray(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel');
        }

        $result = $this->service->listItems();

        $this->assertIsArray($result);
    }

    public function testGenerateSuggestionsForUnknownItemReturnsArrayOrThrows(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel');
        }

        try {
            $result = $this->service->generateSuggestions('NONEXISTENT_ITEM_999');
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }
}
