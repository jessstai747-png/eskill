<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\KeywordResearchService;

/**
 * Testes estruturais do KeywordResearchService
 *
 * @covers \App\Services\KeywordResearchService
 */
class KeywordResearchServiceTest extends TestCase
{
    private static string $sourceCode = '';
    private static \ReflectionClass $reflection;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$reflection = new \ReflectionClass(KeywordResearchService::class);
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
            'KeywordResearchService deve ter declare(strict_types=1)'
        );
    }

    // =============================
    // INSTANCIAÇÃO
    // =============================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(KeywordResearchService::class));
    }

    // =============================
    // STOP WORDS
    // =============================

    public function testHasStopWordsConstant(): void
    {
        $this->assertTrue(
            defined(KeywordResearchService::class . '::STOP_WORDS'),
            'Deve ter constante STOP_WORDS'
        );
    }

    public function testStopWordsContainsCommonPortuguese(): void
    {
        $stopWords = KeywordResearchService::STOP_WORDS;
        $expected = ['a', 'o', 'e', 'de', 'da', 'do', 'em', 'para', 'com'];
        foreach ($expected as $word) {
            $this->assertContains($word, $stopWords, "STOP_WORDS deve conter '{$word}'");
        }
    }

    public function testStopWordsIsArray(): void
    {
        $this->assertIsArray(KeywordResearchService::STOP_WORDS);
        $this->assertNotEmpty(KeywordResearchService::STOP_WORDS);
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
            method_exists(KeywordResearchService::class, $method),
            "KeywordResearchService deve ter método {$method}()"
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publicMethodsProvider(): array
    {
        return [
            'researchKeywords' => ['researchKeywords'],
            'getCategoryTrends' => ['getCategoryTrends'],
            'getAutocompleteKeywords' => ['getAutocompleteKeywords'],
            'extractCompetitorKeywords' => ['extractCompetitorKeywords'],
            'getKeywords' => ['getKeywords'],
            'classifyByType' => ['classifyByType'],
            'estimateSearchVolume' => ['estimateSearchVolume'],
            'getWithCompetitionScore' => ['getWithCompetitionScore'],
            'generateKeywordVariations' => ['generateKeywordVariations'],
        ];
    }

    // =============================
    // METHOD SIGNATURES
    // =============================

    public function testResearchKeywordsSignature(): void
    {
        $ref = new \ReflectionMethod(KeywordResearchService::class, 'researchKeywords');
        $params = $ref->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('categoryId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
        $this->assertEquals('baseKeyword', $params[1]->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $returnType = $ref->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    public function testExtractCompetitorKeywordsSignature(): void
    {
        $ref = new \ReflectionMethod(KeywordResearchService::class, 'extractCompetitorKeywords');
        $params = $ref->getParameters();
        $this->assertGreaterThanOrEqual(2, count($params));
        $this->assertEquals('baseKeyword', $params[0]->getName());
        $this->assertEquals('categoryId', $params[1]->getName());
    }

    // =============================
    // DEPENDENCIES
    // =============================

    public function testHasMercadoLivreClient(): void
    {
        $this->assertStringContainsString('MercadoLivreClient', self::$sourceCode);
    }

    public function testHasCacheService(): void
    {
        $this->assertStringContainsString('CacheService', self::$sourceCode);
    }

    public function testHasSiteIdProperty(): void
    {
        $this->assertStringContainsString('siteId', self::$sourceCode);
    }

    // =============================
    // CONSTRUCTOR DI
    // =============================

    public function testConstructorHasNullableParameters(): void
    {
        $ref = new \ReflectionMethod(KeywordResearchService::class, '__construct');
        $params = $ref->getParameters();
        foreach ($params as $p) {
            $this->assertTrue(
                $p->isDefaultValueAvailable(),
                "Parâmetro \${$p->getName()} do construtor deve ter valor padrão (nullable DI)"
            );
        }
    }

    // =============================
    // PATTERNS
    // =============================

    public function testUsesCaching(): void
    {
        $this->assertStringContainsString('cache->remember', self::$sourceCode);
    }

    public function testUsesMLBSiteId(): void
    {
        $this->assertStringContainsString('MLB', self::$sourceCode, 'Deve usar MLB como site padrão');
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
}
