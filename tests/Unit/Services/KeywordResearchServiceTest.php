<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\KeywordResearchService;

/**
 * Testes do KeywordResearchService
 */
class KeywordResearchServiceTest extends TestCase
{
    private KeywordResearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KeywordResearchService();
    }

    // =============================
    // TESTES DE INSTANCIAÇÃO
    // =============================

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(KeywordResearchService::class, $this->service);
    }

    public function testCanBeInstantiatedWithNullAccountId(): void
    {
        // Testar instanciação sem account_id (null)
        $service = new KeywordResearchService(null);
        $this->assertInstanceOf(KeywordResearchService::class, $service);
    }

    // =============================
    // TESTES DE MÉTODOS
    // =============================

    public function testHasRequiredMethods(): void
    {
        $methods = [
            'researchKeywords',
            'getCategoryTrends',
            'getAutocompleteKeywords',
            'extractCompetitorKeywords',
            'estimateSearchVolume',
            'generateKeywordVariations',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->service, $method),
                "KeywordResearchService deve ter método {$method}()"
            );
        }
    }

    // =============================
    // TESTES DE STOP WORDS
    // =============================

    public function testHasStopWordsConstant(): void
    {
        $reflection = new \ReflectionClass(KeywordResearchService::class);
        $this->assertTrue($reflection->hasConstant('STOP_WORDS'));
    }

    public function testStopWordsContainsCommonWords(): void
    {
        $reflection = new \ReflectionClass(KeywordResearchService::class);
        $stopWords = $reflection->getConstant('STOP_WORDS');

        $expectedWords = ['a', 'o', 'e', 'de', 'da', 'do', 'em', 'um', 'uma', 'para', 'com'];

        foreach ($expectedWords as $word) {
            $this->assertContains(
                $word,
                $stopWords,
                "Stop words deve conter '{$word}'"
            );
        }
    }

    // =============================
    // TESTES DE VARIAÇÃO DE KEYWORDS
    // =============================

    public function testGenerateKeywordVariationsExists(): void
    {
        $this->assertTrue(
            method_exists($this->service, 'generateKeywordVariations'),
            'Deve ter método generateKeywordVariations'
        );
    }

    // =============================
    // TESTES DE ESTRUTURA
    // =============================

    public function testHasMercadoLivreClient(): void
    {
        $reflection = new \ReflectionClass(KeywordResearchService::class);
        $this->assertTrue($reflection->hasProperty('client'));
    }

    public function testHasCacheService(): void
    {
        $reflection = new \ReflectionClass(KeywordResearchService::class);
        $this->assertTrue($reflection->hasProperty('cache'));
    }

    public function testHasSiteId(): void
    {
        $reflection = new \ReflectionClass(KeywordResearchService::class);
        $this->assertTrue($reflection->hasProperty('siteId'));
    }

    // =============================
    // TESTES DE DOCUMENTAÇÃO
    // =============================

    public function testClassHasDocumentation(): void
    {
        $reflection = new \ReflectionClass(KeywordResearchService::class);
        $docComment = $reflection->getDocComment();

        $this->assertNotFalse($docComment);
        $this->assertStringContainsString('Palavras-Chave', $docComment);
    }

    public function testResearchKeywordsHasDocumentation(): void
    {
        $reflection = new \ReflectionMethod(KeywordResearchService::class, 'researchKeywords');
        $this->assertNotFalse($reflection->getDocComment());
    }

    // =============================
    // TESTES DE PARÂMETROS
    // =============================

    public function testResearchKeywordsAcceptsParameters(): void
    {
        $reflection = new \ReflectionMethod(KeywordResearchService::class, 'researchKeywords');
        $params = $reflection->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('categoryId', $params[0]->getName());
        $this->assertEquals('baseKeyword', $params[1]->getName());
        $this->assertTrue($params[1]->allowsNull());
    }

    public function testEstimateSearchVolumeAcceptsParameters(): void
    {
        $reflection = new \ReflectionMethod(KeywordResearchService::class, 'estimateSearchVolume');
        $params = $reflection->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params));
        $this->assertEquals('keyword', $params[0]->getName());
    }

    // =============================
    // TESTES DE RETORNO
    // =============================

    public function testResearchKeywordsReturnsArray(): void
    {
        $reflection = new \ReflectionMethod(KeywordResearchService::class, 'researchKeywords');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    public function testGetCategoryTrendsReturnsArray(): void
    {
        $reflection = new \ReflectionMethod(KeywordResearchService::class, 'getCategoryTrends');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }
}
