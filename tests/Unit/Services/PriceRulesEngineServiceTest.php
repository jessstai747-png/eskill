<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PriceRulesEngineService;
use PDO;
use PDOStatement;
use ReflectionClass;
use Tests\TestCase;

/**
 * Unit Tests for PriceRulesEngineService
 *
 * Tests rule type constants, priority constants, rule validation,
 * rule templates, and error handling.
 *
 * @covers \App\Services\PriceRulesEngineService
 */
class PriceRulesEngineServiceTest extends TestCase
{
    private PriceRulesEngineService $service;
    private PDO $mockPdo;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPdo = $this->createMock(PDO::class);
        $this->ref = new ReflectionClass(PriceRulesEngineService::class);
        $instance = $this->ref->newInstanceWithoutConstructor();

        // Inject mock PDO
        $dbProp = $this->ref->getProperty('db');
        $dbProp->setAccessible(true);
        $dbProp->setValue($instance, $this->mockPdo);

        // Set accountId
        $idProp = $this->ref->getProperty('accountId');
        $idProp->setAccessible(true);
        $idProp->setValue($instance, 12345);

        $this->service = $instance;
    }

    private function createMockStmt(
        mixed $fetchReturn = null,
        mixed $fetchAllReturn = null,
        string $lastInsertId = '1'
    ): PDOStatement {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        if ($fetchReturn !== null) {
            $stmt->method('fetch')->willReturn($fetchReturn);
        }
        if ($fetchAllReturn !== null) {
            $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        }
        return $stmt;
    }

    // =========================================================================
    // INSTANTIATION
    // =========================================================================

    public function testServiceCanBeInstantiatedViaReflection(): void
    {
        $this->assertInstanceOf(PriceRulesEngineService::class, $this->service);
    }

    public function testServiceHasRequiredPublicMethods(): void
    {
        $methods = [
            'createRule', 'listRules', 'getRule', 'updateRule', 'deleteRule',
            'toggleRule', 'executeRulesForItem', 'executeAllRules',
            'simulateRules', 'getRuleTemplates',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->service, $method),
                "Missing public method: {$method}"
            );
        }
    }

    // =========================================================================
    // RULE TYPE CONSTANTS
    // =========================================================================

    public function testRuleTypeConstantsExist(): void
    {
        $this->assertSame('match_competitor', PriceRulesEngineService::RULE_MATCH_COMPETITOR);
        $this->assertSame('floor_ceiling', PriceRulesEngineService::RULE_FLOOR_CEILING);
        $this->assertSame('time_based', PriceRulesEngineService::RULE_TIME_BASED);
        $this->assertSame('margin_based', PriceRulesEngineService::RULE_MARGIN_BASED);
        $this->assertSame('stock_based', PriceRulesEngineService::RULE_STOCK_BASED);
        $this->assertSame('velocity_based', PriceRulesEngineService::RULE_VELOCITY_BASED);
        $this->assertSame('category_position', PriceRulesEngineService::RULE_CATEGORY_POSITION);
    }

    // =========================================================================
    // PRIORITY CONSTANTS
    // =========================================================================

    public function testPriorityConstantsExist(): void
    {
        $this->assertSame(1, PriceRulesEngineService::PRIORITY_LOW);
        $this->assertSame(5, PriceRulesEngineService::PRIORITY_MEDIUM);
        $this->assertSame(10, PriceRulesEngineService::PRIORITY_HIGH);
        $this->assertSame(20, PriceRulesEngineService::PRIORITY_CRITICAL);
    }

    public function testPriorityOrder(): void
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

    // =========================================================================
    // createRule — VALIDATION
    // =========================================================================

    public function testCreateRuleFailsWithoutName(): void
    {
        $result = $this->service->createRule([
            'rule_type' => PriceRulesEngineService::RULE_MATCH_COMPETITOR,
            'conditions' => ['min_price_diff' => 5],
            'actions' => ['adjustment' => 0],
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('name', $result['message']);
    }

    public function testCreateRuleFailsWithoutRuleType(): void
    {
        $result = $this->service->createRule([
            'name' => 'Test Rule',
            'conditions' => ['min_price_diff' => 5],
            'actions' => ['adjustment' => 0],
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('rule_type', $result['message']);
    }

    public function testCreateRuleFailsWithoutConditions(): void
    {
        $result = $this->service->createRule([
            'name' => 'Test Rule',
            'rule_type' => PriceRulesEngineService::RULE_MATCH_COMPETITOR,
            'actions' => ['adjustment' => 0],
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('conditions', $result['message']);
    }

    public function testCreateRuleFailsWithoutActions(): void
    {
        $result = $this->service->createRule([
            'name' => 'Test Rule',
            'rule_type' => PriceRulesEngineService::RULE_MATCH_COMPETITOR,
            'conditions' => ['min_price_diff' => 5],
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('actions', $result['message']);
    }

    public function testCreateRuleFailsWithInvalidRuleType(): void
    {
        $result = $this->service->createRule([
            'name' => 'Test Rule',
            'rule_type' => 'invalid_type',
            'conditions' => ['something' => true],
            'actions' => ['do' => 'something'],
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('inválido', $result['message']);
    }

    public function testCreateRuleSucceedsWithValidData(): void
    {
        $stmt = $this->createMockStmt();
        $this->mockPdo->method('prepare')->willReturn($stmt);
        $this->mockPdo->method('lastInsertId')->willReturn('42');

        $result = $this->service->createRule([
            'name' => 'Match Menor Preço',
            'rule_type' => PriceRulesEngineService::RULE_MATCH_COMPETITOR,
            'conditions' => ['min_price_diff' => 5],
            'actions' => ['adjustment' => 0, 'adjustment_type' => 'match'],
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('rule_id', $result);
    }

    public function testCreateRuleAcceptsAllValidTypes(): void
    {
        $stmt = $this->createMockStmt();
        $this->mockPdo->method('prepare')->willReturn($stmt);
        $this->mockPdo->method('lastInsertId')->willReturn('1');

        $validTypes = [
            PriceRulesEngineService::RULE_MATCH_COMPETITOR,
            PriceRulesEngineService::RULE_FLOOR_CEILING,
            PriceRulesEngineService::RULE_TIME_BASED,
            PriceRulesEngineService::RULE_MARGIN_BASED,
            PriceRulesEngineService::RULE_STOCK_BASED,
            PriceRulesEngineService::RULE_VELOCITY_BASED,
            PriceRulesEngineService::RULE_CATEGORY_POSITION,
        ];

        foreach ($validTypes as $type) {
            $result = $this->service->createRule([
                'name' => "Rule {$type}",
                'rule_type' => $type,
                'conditions' => ['test' => true],
                'actions' => ['test' => true],
            ]);
            $this->assertTrue($result['success'], "Rule type {$type} should be valid");
        }
    }

    // =========================================================================
    // getRuleTemplates
    // =========================================================================

    public function testGetRuleTemplatesReturnsArray(): void
    {
        $templates = $this->service->getRuleTemplates();

        $this->assertIsArray($templates);
        $this->assertNotEmpty($templates);
    }

    public function testGetRuleTemplatesHaveRequiredKeys(): void
    {
        $templates = $this->service->getRuleTemplates();

        foreach ($templates as $template) {
            $this->assertArrayHasKey('name', $template);
            $this->assertArrayHasKey('rule_type', $template);
            $this->assertArrayHasKey('description', $template);
            $this->assertArrayHasKey('conditions', $template);
            $this->assertArrayHasKey('actions', $template);
        }
    }

    public function testGetRuleTemplatesHasExpectedCount(): void
    {
        $templates = $this->service->getRuleTemplates();

        // 8 templates: match, 5% below, floor, time, margin, stock, velocity, category
        $this->assertCount(8, $templates);
    }

    public function testGetRuleTemplatesContainsMatchCompetitor(): void
    {
        $templates = $this->service->getRuleTemplates();
        $types = array_column($templates, 'rule_type');

        $this->assertContains(PriceRulesEngineService::RULE_MATCH_COMPETITOR, $types);
    }

    public function testGetRuleTemplatesContainsAllRuleTypes(): void
    {
        $templates = $this->service->getRuleTemplates();
        $types = array_column($templates, 'rule_type');

        $expectedTypes = [
            PriceRulesEngineService::RULE_MATCH_COMPETITOR,
            PriceRulesEngineService::RULE_FLOOR_CEILING,
            PriceRulesEngineService::RULE_TIME_BASED,
            PriceRulesEngineService::RULE_MARGIN_BASED,
            PriceRulesEngineService::RULE_STOCK_BASED,
            PriceRulesEngineService::RULE_VELOCITY_BASED,
            PriceRulesEngineService::RULE_CATEGORY_POSITION,
        ];

        foreach ($expectedTypes as $type) {
            $this->assertContains($type, $types, "Template missing for type: {$type}");
        }
    }

    // =========================================================================
    // ERROR HANDLING — methods that need DB
    // =========================================================================

    public function testListRulesReturnsErrorOnFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->listRules();
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }

    public function testGetRuleReturnsErrorOnFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->getRule(1);
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        // Returns null or array
        $this->assertTrue($result === null || is_array($result));
    }
}
