<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\KeywordMinerService;

/**
 * Testes estruturais do KeywordMinerService
 *
 * @covers \App\Services\KeywordMinerService
 */
class KeywordMinerServiceTest extends TestCase
{
    private static string $sourceCode = '';
    private static \ReflectionClass $reflection;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$reflection = new \ReflectionClass(KeywordMinerService::class);
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
            'KeywordMinerService deve ter declare(strict_types=1)'
        );
    }

    // =============================
    // INSTANCIAÇÃO
    // =============================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(KeywordMinerService::class));
    }

    // =============================
    // PUBLIC METHODS
    // =============================

    /**
     * @dataProvider publicMethodsProvider
     */
    public function testHasPublicMethod(string $method): void
    {
        $this->assertTrue(
            method_exists(KeywordMinerService::class, $method),
            "KeywordMinerService deve ter método {$method}()"
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publicMethodsProvider(): array
    {
        return [
            'mineKeywords' => ['mineKeywords'],
            'getDomainDiscovery' => ['getDomainDiscovery'],
            'getCategoryKeywords' => ['getCategoryKeywords'],
            'getAttributeKeywords' => ['getAttributeKeywords'],
            'mineAllMotoCategories' => ['mineAllMotoCategories'],
            'generateTitleSuggestions' => ['generateTitleSuggestions'],
            'saveMinedKeywords' => ['saveMinedKeywords'],
            'getStoredKeywords' => ['getStoredKeywords'],
            'getStats' => ['getStats'],
        ];
    }

    // =============================
    // METHOD SIGNATURES
    // =============================

    public function testMineKeywordsSignature(): void
    {
        $ref = new \ReflectionMethod(KeywordMinerService::class, 'mineKeywords');
        $params = $ref->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('seedTerm', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
        $this->assertEquals('categoryId', $params[1]->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertNull($params[1]->getDefaultValue());
        $returnType = $ref->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    public function testSaveMinedKeywordsSignature(): void
    {
        $ref = new \ReflectionMethod(KeywordMinerService::class, 'saveMinedKeywords');
        $params = $ref->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('keywords', $params[0]->getName());
        $this->assertEquals('source', $params[1]->getName());
        $returnType = $ref->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('int', $returnType->getName());
    }

    public function testGetStoredKeywordsSignature(): void
    {
        $ref = new \ReflectionMethod(KeywordMinerService::class, 'getStoredKeywords');
        $params = $ref->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('categoryId', $params[0]->getName());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertEquals('limit', $params[1]->getName());
        $this->assertEquals(100, $params[1]->getDefaultValue());
    }

    // =============================
    // MOTO CATEGORIES
    // =============================

    public function testHasMotoCategoryIds(): void
    {
        $this->assertStringContainsString('MLB1771', self::$sourceCode, 'Deve conter MLB1771 (Acessórios moto)');
        $this->assertStringContainsString('MLB243551', self::$sourceCode, 'Deve conter MLB243551 (Peças moto)');
        $this->assertStringContainsString('MLB1763', self::$sourceCode, 'Deve conter MLB1763 (Motos)');
    }

    // =============================
    // API INTEGRATION
    // =============================

    public function testUsesMLApiBaseUrl(): void
    {
        $this->assertStringContainsString(
            'api.mercadolibre.com',
            self::$sourceCode,
            'Deve usar URL base da API do Mercado Livre'
        );
    }

    public function testUsesDomainDiscovery(): void
    {
        $this->assertStringContainsString(
            'domain_discovery',
            self::$sourceCode,
            'Deve usar endpoint de domain discovery'
        );
    }

    public function testUsesCategories(): void
    {
        $this->assertStringContainsString(
            '/categories/',
            self::$sourceCode,
            'Deve usar endpoint de categorias'
        );
    }

    public function testUsesAttributes(): void
    {
        $this->assertStringContainsString(
            'attributes',
            self::$sourceCode,
            'Deve buscar atributos de categorias'
        );
    }

    // =============================
    // DATABASE
    // =============================

    public function testUsesPDO(): void
    {
        $this->assertStringContainsString('PDO', self::$sourceCode);
    }

    public function testUsesDatabase(): void
    {
        $this->assertStringContainsString('Database::getInstance', self::$sourceCode);
    }

    public function testUsesPreparedStatements(): void
    {
        $this->assertStringContainsString('prepare(', self::$sourceCode);
        $this->assertStringContainsString('execute(', self::$sourceCode);
    }

    // =============================
    // LOGGING
    // =============================

    public function testUsesLogWarning(): void
    {
        $this->assertStringContainsString(
            'log_warning',
            self::$sourceCode,
            'Deve usar log_warning() para erros não-críticos'
        );
    }

    public function testNoSilentCatches(): void
    {
        // Verifica que não existem catches silenciosos (sem log)
        preg_match_all('/catch\s*\([^)]+\)\s*\{([^}]*)\}/', self::$sourceCode, $matches);
        foreach ($matches[1] as $catchBody) {
            $trimmed = trim($catchBody);
            if (empty($trimmed) || str_starts_with($trimmed, '//')) {
                $this->fail('Encontrado catch silencioso: deve ter log_warning() ou Log::');
            }
        }
        $this->assertTrue(true); // All catches have content
    }

    // =============================
    // CACHING
    // =============================

    public function testUsesInMemoryCache(): void
    {
        $this->assertStringContainsString(
            'cache',
            self::$sourceCode,
            'Deve usar cache para evitar calls repetidos'
        );
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
            fn(\ReflectionMethod $m): bool => $m->getDeclaringClass()->getName() === KeywordMinerService::class
        );
        $this->assertGreaterThanOrEqual(9, count($own),
            'KeywordMinerService deve ter pelo menos 9 métodos públicos');
    }
}
