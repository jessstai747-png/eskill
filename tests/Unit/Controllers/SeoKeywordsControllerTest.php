<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Controllers\SeoKeywordsController;

/**
 * Testes estruturais do SeoKeywordsController
 *
 * @covers \App\Controllers\SeoKeywordsController
 */
class SeoKeywordsControllerTest extends TestCase
{
    private static string $sourceCode = '';
    private static \ReflectionClass $reflection;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$reflection = new \ReflectionClass(SeoKeywordsController::class);
        self::$sourceCode = (string) file_get_contents((string) self::$reflection->getFileName());
    }

    // =============================
    // STRICT TYPES
    // =============================

    public function testHasStrictTypesDeclaration(): void
    {
        $this->assertMatchesRegularExpression(
            '/declare\s*\(\s*strict_types\s*=\s*1\s*\)/',
            self::$sourceCode,
            'SeoKeywordsController deve ter declare(strict_types=1)'
        );
    }

    // =============================
    // INSTANCIAÇÃO
    // =============================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(SeoKeywordsController::class));
    }

    public function testExtendsBaseController(): void
    {
        $this->assertTrue(self::$reflection->isSubclassOf('App\Controllers\BaseController'));
    }

    public function testIsDeprecated(): void
    {
        $doc = self::$reflection->getDocComment();
        $this->assertNotFalse($doc);
        $this->assertStringContainsString('@deprecated', $doc);
    }

    // =============================
    // ENDPOINTS
    // =============================

    /**
     * @dataProvider endpointMethodsProvider
     */
    public function testHasEndpoint(string $method): void
    {
        $this->assertTrue(
            method_exists(SeoKeywordsController::class, $method),
            "SeoKeywordsController deve ter método {$method}()"
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function endpointMethodsProvider(): array
    {
        return [
            'distribute' => ['distribute'],
            'classify' => ['classify'],
            'fetch' => ['fetch'],
            'generate' => ['generate'],
            'validateDensity' => ['validateDensity'],
            'calculateDensity' => ['calculateDensity'],
            'getWeights' => ['getWeights'],
            'invalidateCache' => ['invalidateCache'],
            'mine' => ['mine'],
            'mineMoto' => ['mineMoto'],
            'getAttributeKeywords' => ['getAttributeKeywords'],
            'discover' => ['discover'],
            'suggestTitle' => ['suggestTitle'],
        ];
    }

    // =============================
    // METHOD SIGNATURES
    // =============================

    /**
     * @dataProvider stringParameterMethodsProvider
     */
    public function testMethodAcceptsStringParameter(string $method, string $param): void
    {
        $ref = new \ReflectionMethod(SeoKeywordsController::class, $method);
        $params = $ref->getParameters();
        $found = false;
        foreach ($params as $p) {
            if ($p->getName() === $param) {
                $type = $p->getType();
                $this->assertNotNull($type, "{$method}(\${$param}) deve ter type hint");
                $this->assertEquals('string', $type->getName());
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "{$method}() deve ter parâmetro \${$param}");
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function stringParameterMethodsProvider(): array
    {
        return [
            'fetch(categoryId)' => ['fetch', 'categoryId'],
            'generate(categoryId)' => ['generate', 'categoryId'],
            'invalidateCache(categoryId)' => ['invalidateCache', 'categoryId'],
            'mine(categoryId)' => ['mine', 'categoryId'],
            'getAttributeKeywords(categoryId)' => ['getAttributeKeywords', 'categoryId'],
        ];
    }

    /**
     * @dataProvider voidReturnMethodsProvider
     */
    public function testMethodReturnsVoid(string $method): void
    {
        $ref = new \ReflectionMethod(SeoKeywordsController::class, $method);
        $returnType = $ref->getReturnType();
        $this->assertNotNull($returnType, "{$method}() deve ter return type");
        $this->assertEquals('void', $returnType->getName());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function voidReturnMethodsProvider(): array
    {
        return [
            'distribute' => ['distribute'],
            'classify' => ['classify'],
            'fetch' => ['fetch'],
            'generate' => ['generate'],
            'mine' => ['mine'],
            'discover' => ['discover'],
            'suggestTitle' => ['suggestTitle'],
        ];
    }

    // =============================
    // DEPENDENCIES
    // =============================

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
            'distributionService' => ['distributionService'],
            'sourceService' => ['sourceService'],
            'minerService' => ['minerService'],
        ];
    }

    // =============================
    // IMPORTS
    // =============================

    /**
     * @dataProvider importsProvider
     */
    public function testUsesImport(string $import): void
    {
        $this->assertStringContainsString($import, self::$sourceCode);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function importsProvider(): array
    {
        return [
            'KeywordDistributionService' => ['KeywordDistributionService'],
            'KeywordSourceService' => ['KeywordSourceService'],
            'KeywordMinerService' => ['KeywordMinerService'],
        ];
    }

    // =============================
    // INPUT VALIDATION
    // =============================

    public function testDiscoverValidatesEmptyTerm(): void
    {
        $this->assertStringContainsString(
            'Term is required',
            self::$sourceCode,
            'discover() deve validar termo vazio'
        );
    }

    public function testSuggestTitleValidatesRequiredFields(): void
    {
        $this->assertStringContainsString(
            'product_name and category_id are required',
            self::$sourceCode,
            'suggestTitle() deve validar campos obrigatórios'
        );
    }

    // =============================
    // JSON RESPONSE
    // =============================

    public function testHasJsonResponseHelper(): void
    {
        $this->assertStringContainsString('jsonResponse', self::$sourceCode);
        $this->assertStringContainsString('Content-Type: application/json', self::$sourceCode);
    }

    public function testReturnsSuccessFlag(): void
    {
        $matches = preg_match_all('/success.*true/', self::$sourceCode);
        $this->assertGreaterThanOrEqual(3, $matches, 'Deve retornar success=true em múltiplos endpoints');
    }

    // =============================
    // NO BAD PRACTICES
    // =============================

    public function testNoErrorLogUsage(): void
    {
        $this->assertStringNotContainsString('error_log(', self::$sourceCode);
    }

    public function testNoVarDumpUsage(): void
    {
        $this->assertStringNotContainsString('var_dump(', self::$sourceCode);
    }

    // =============================
    // METHOD COUNT
    // =============================

    public function testHasExpectedPublicMethodCount(): void
    {
        $methods = self::$reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        $own = array_filter(
            $methods,
            fn(\ReflectionMethod $m): bool => $m->getDeclaringClass()->getName() === SeoKeywordsController::class
        );
        $this->assertGreaterThanOrEqual(10, count($own),
            'SeoKeywordsController deve ter pelo menos 10 métodos públicos');
    }
}
