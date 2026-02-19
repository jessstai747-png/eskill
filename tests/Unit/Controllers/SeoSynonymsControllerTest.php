<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Controllers\SeoSynonymsController;

/**
 * Testes estruturais do SeoSynonymsController
 *
 * @covers \App\Controllers\SeoSynonymsController
 */
class SeoSynonymsControllerTest extends TestCase
{
    private static string $sourceCode = '';
    private static \ReflectionClass $reflection;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$reflection = new \ReflectionClass(SeoSynonymsController::class);
        self::$sourceCode = (string) file_get_contents((string) self::$reflection->getFileName());
    }

    public function testHasStrictTypesDeclaration(): void
    {
        $this->assertMatchesRegularExpression(
            '/declare\s*\(\s*strict_types\s*=\s*1\s*\)/',
            self::$sourceCode
        );
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(SeoSynonymsController::class));
    }

    public function testIsDeprecated(): void
    {
        $doc = self::$reflection->getDocComment();
        $this->assertNotFalse($doc);
        $this->assertStringContainsString('@deprecated', $doc);
    }

    /**
     * @dataProvider endpointMethodsProvider
     */
    public function testHasEndpoint(string $method): void
    {
        $this->assertTrue(method_exists(SeoSynonymsController::class, $method));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function endpointMethodsProvider(): array
    {
        return [
            'getHierarchy' => ['getHierarchy'],
            'expand' => ['expand'],
            'generateModel' => ['generateModel'],
            'calculateScore' => ['calculateScore'],
            'getContexts' => ['getContexts'],
        ];
    }

    /**
     * @dataProvider stringParameterMethodsProvider
     */
    public function testMethodAcceptsStringParameter(string $method, string $param): void
    {
        $ref = new \ReflectionMethod(SeoSynonymsController::class, $method);
        $params = $ref->getParameters();
        $found = false;
        foreach ($params as $p) {
            if ($p->getName() === $param) {
                $this->assertEquals('string', $p->getType()->getName());
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function stringParameterMethodsProvider(): array
    {
        return [
            'getHierarchy(categoryId)' => ['getHierarchy', 'categoryId'],
            'getContexts(categoryId)' => ['getContexts', 'categoryId'],
        ];
    }

    /**
     * @dataProvider dependenciesProvider
     */
    public function testHasDependency(string $property): void
    {
        $properties = self::$reflection->getProperties(\ReflectionProperty::IS_PRIVATE);
        $names = array_map(fn(\ReflectionProperty $p): string => $p->getName(), $properties);
        $this->assertContains($property, $names);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function dependenciesProvider(): array
    {
        return [
            'synonymExpansionService' => ['synonymExpansionService'],
            'semanticScoreService' => ['semanticScoreService'],
            'request' => ['request'],
        ];
    }

    public function testUsesSynonymExpansionService(): void
    {
        $this->assertStringContainsString('SynonymExpansionService', self::$sourceCode);
    }

    public function testUsesSemanticScoreService(): void
    {
        $this->assertStringContainsString('SemanticScoreService', self::$sourceCode);
    }

    public function testHasJsonResponseHelper(): void
    {
        $this->assertStringContainsString('jsonResponse', self::$sourceCode);
        $this->assertStringContainsString('Content-Type: application/json', self::$sourceCode);
    }

    public function testNoErrorLogUsage(): void
    {
        $this->assertStringNotContainsString('error_log(', self::$sourceCode);
    }

    public function testNoVarDumpUsage(): void
    {
        $this->assertStringNotContainsString('var_dump(', self::$sourceCode);
    }
}
