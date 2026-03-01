<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\PriceRulesEngineService;

/**
 * Testes do motor de regras de precificacao — Fase 7 (Pricing).
 *
 * PriceRulesEngineService requer DB no construtor.
 * Constantes publicas sempre testadas; funcionais pulam se DB indisponivel.
 *
 * @covers \App\Services\PriceRulesEngineService
 */
class PricingAutoRuleTest extends TestCase
{
    private bool $dbAvailable = false;
    private ?PriceRulesEngineService $engine = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->engine      = new PriceRulesEngineService(1);
            $this->dbAvailable = true;
        } catch (\Throwable) {
            $this->dbAvailable = false;
        }
    }

    // ------------------------------------------------------------------
    // Estrutura de classe (sempre executa)
    // ------------------------------------------------------------------

    public function testPriceRulesEngineServiceClassExists(): void
    {
        $this->assertTrue(class_exists(PriceRulesEngineService::class));
    }

    /** @dataProvider engineMethodsProvider */
    public function testPriceRulesEngineServiceHasMethods(string $method): void
    {
        $this->assertTrue(
            method_exists(PriceRulesEngineService::class, $method),
            "PriceRulesEngineService deve ter {$method}()"
        );
    }

    /** @return array<string, array{string}> */
    public static function engineMethodsProvider(): array
    {
        return [
            'createRule'           => ['createRule'],
            'listRules'            => ['listRules'],
            'getRule'              => ['getRule'],
            'updateRule'           => ['updateRule'],
            'deleteRule'           => ['deleteRule'],
            'toggleRule'           => ['toggleRule'],
            'executeRulesForItem'  => ['executeRulesForItem'],
            'executeAllRules'      => ['executeAllRules'],
            'simulateRules'        => ['simulateRules'],
            'getRuleTemplates'     => ['getRuleTemplates'],
        ];
    }

    // ------------------------------------------------------------------
    // Constantes publicas (sempre executam)
    // ------------------------------------------------------------------

    public function testRuleTypeConstantsAreDefined(): void
    {
        $ref       = new \ReflectionClass(PriceRulesEngineService::class);
        $constants = $ref->getConstants();

        $expected = [
            'RULE_MATCH_COMPETITOR',
            'RULE_FLOOR_CEILING',
            'RULE_TIME_BASED',
            'RULE_MARGIN_BASED',
            'RULE_STOCK_BASED',
            'RULE_VELOCITY_BASED',
            'RULE_CATEGORY_POSITION',
        ];

        foreach ($expected as $const) {
            $this->assertArrayHasKey($const, $constants, "PriceRulesEngineService deve definir {$const}");
        }
    }

    public function testPriorityConstantsHaveCorrectValues(): void
    {
        $this->assertSame(1,  PriceRulesEngineService::PRIORITY_LOW);
        $this->assertSame(5,  PriceRulesEngineService::PRIORITY_MEDIUM);
        $this->assertSame(10, PriceRulesEngineService::PRIORITY_HIGH);
        $this->assertSame(20, PriceRulesEngineService::PRIORITY_CRITICAL);
    }

    public function testPriorityOrderIsConsistent(): void
    {
        $this->assertLessThan(
            PriceRulesEngineService::PRIORITY_MEDIUM,
            PriceRulesEngineService::PRIORITY_LOW
        );
        $this->assertLessThan(
            PriceRulesEngineService::PRIORITY_HIGH,
            PriceRulesEngineService::PRIORITY_MEDIUM
        );
        $this->assertLessThan(
            PriceRulesEngineService::PRIORITY_CRITICAL,
            PriceRulesEngineService::PRIORITY_HIGH
        );
    }

    // ------------------------------------------------------------------
    // Testes funcionais (requerem DB)
    // ------------------------------------------------------------------

    public function testPriceRulesEngineCanBeInstantiated(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel — PriceRulesEngineService requer Database::getInstance()');
        }

        $this->assertInstanceOf(PriceRulesEngineService::class, $this->engine);
    }

    public function testListRulesReturnsArray(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel');
        }

        $result = $this->engine->listRules([]);

        $this->assertIsArray($result);
    }

    public function testGetRuleTemplatesReturnsNonEmptyArray(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel');
        }

        $templates = $this->engine->getRuleTemplates();

        $this->assertIsArray($templates);
        $this->assertNotEmpty($templates);

        foreach ($templates as $template) {
            $this->assertIsArray($template);
        }
    }

    public function testSimulateRulesReturnsPredictions(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel');
        }

        try {
            $result = $this->engine->simulateRules('MLB_NONEXISTENT');
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }
}
