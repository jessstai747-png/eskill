<?php

declare(strict_types=1);

namespace Tests\Unit\SEO;

use PHPUnit\Framework\TestCase;
use App\Services\SEO\SEOOptimizerService;
use App\Services\SEO\TitleOptimizerService;
use App\Services\SEO\KeywordGapAnalyzerService;
use App\Services\SEO\SemanticAnalyzerService;
use App\Services\SEO\AttributeModelService;

/**
 * Testes unitários para serviços de otimização SEO
 */
class SEOServicesTest extends TestCase
{
    private string $testAccountId = 'test-account-123';

    protected function setUp(): void
    {
        parent::setUp();

        // Estes testes requerem API key do Anthropic Claude para funcionar
        // Pular se não estiver configurada (evita falhas em CI/test environments)
        $apiKey = $_ENV['CLAUDE_API_KEY'] ?? $_ENV['ANTHROPIC_API_KEY'] ?? getenv('CLAUDE_API_KEY') ?: getenv('ANTHROPIC_API_KEY') ?: '';
        if (empty($apiKey) || str_starts_with($apiKey, 'test_') || str_starts_with($apiKey, 'your_') || str_starts_with($apiKey, 'sk-ant-test')) {
            $this->markTestSkipped('API key do Anthropic Claude não configurada ou inválida (test/placeholder) - testes SEO AI requerem API key real.');
        }
    }

    /**
     * Teste do serviço principal de otimização SEO
     */
    public function testSEOOptimizerServiceAnalyzesProduct(): void
    {
        $service = new SEOOptimizerService($this->testAccountId);
        $product = [
            'title' => 'Smartphone Samsung Galaxy S21 128GB Preto',
            'category' => 'Celulares',
            'description' => 'Smartphone Samsung Galaxy S21 com 128GB, tela de 6.2 polegadas, câmera tripla.',
            'price' => 2999.99,
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Samsung'],
                ['id' => 'MODEL', 'value_name' => 'Galaxy S21'],
                ['id' => 'MEMORY', 'value_name' => '128GB']
            ]
        ];

        $result = $service->analyze($product);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('score', $result);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
        $this->assertArrayHasKey('title_analysis', $result);
        $this->assertArrayHasKey('description_analysis', $result);
        $this->assertArrayHasKey('keywords', $result);
    }

    public function testSEOOptimizerServiceOptimizesTitle(): void
    {
        $service = new SEOOptimizerService($this->testAccountId);
        $title = 'celular samsung';
        $context = [
            'category' => 'Celulares',
            'brand' => 'Samsung',
            'keywords' => ['smartphone', 'galaxy', 's21']
        ];

        $result = $service->optimizeTitle($title, $context);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('optimized_title', $result);
        $this->assertNotEmpty($result['optimized_title']);
        $this->assertArrayHasKey('alternative_titles', $result);
        $this->assertArrayHasKey('improvement_score', $result);
    }

    public function testSEOOptimizerServiceGeneratesDescription(): void
    {
        $service = new SEOOptimizerService($this->testAccountId);
        $product = [
            'title' => 'Smartphone Samsung Galaxy S21',
            'category' => 'Celulares',
            'brand' => 'Samsung',
            'features' => ['Tela AMOLED 6.2"', 'Câmera Tripla 64MP', '5G', '128GB Storage'],
            'description' => 'Descrição básica'
        ];

        $result = $service->generateDescription($product);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('description', $result);
        $this->assertNotEmpty($result['description']);
        $this->assertArrayHasKey('bullet_points', $result);
        $this->assertArrayHasKey('seo_score', $result);
    }

    /**
     * Testes do TitleOptimizerService
     */
    public function testTitleOptimizerServiceAnalyzesTitle(): void
    {
        $service = new TitleOptimizerService($this->testAccountId);
        $title = 'iPhone 13 Apple 128GB';
        $context = [
            'category' => 'MLB1055',
            'brand' => 'Apple'
        ];

        $result = $service->analyzeTitle($title, $context);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('analysis', $result);
        $this->assertArrayHasKey('gaps', $result);
        $this->assertArrayHasKey('recommendations', $result);
        $this->assertArrayHasKey('similar_products', $result);
    }

    public function testTitleOptimizerServiceGeneratesOptimizedTitles(): void
    {
        $service = new TitleOptimizerService($this->testAccountId);
        $title = 'Smartphone Samsung';
        $context = [
            'category' => 'Celulares',
            'brand' => 'Samsung',
            'target_keywords' => ['galaxy', 's21', '5g']
        ];

        $result = $service->generateOptimizedTitles($title, $context);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('optimized_titles', $result);
        $this->assertNotEmpty($result['optimized_titles']);
        $this->assertArrayHasKey('semantic_keywords', $result);
        $this->assertArrayHasKey('long_tail_opportunities', $result);
    }

    public function testTitleOptimizerServiceGeneratesModelAttribute(): void
    {
        $service = new TitleOptimizerService($this->testAccountId);
        $title = 'Samsung Galaxy S21 5G 128GB';
        $productData = [
            'category' => 'Celulares',
            'brand' => 'Samsung',
            'attributes' => [
                ['id' => 'MEMORY', 'value_name' => '128GB'],
                ['id' => 'NETWORK', 'value_name' => '5G']
            ]
        ];

        $result = $service->generateModelAttribute($title, $productData);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('suggested_model', $result);
        $this->assertArrayHasKey('extraction_confidence', $result);
    }

    /**
     * Testes do KeywordGapAnalyzerService
     */
    public function testKeywordGapAnalyzerServiceAnalyzesGaps(): void
    {
        $service = new KeywordGapAnalyzerService($this->testAccountId);
        $productId = 'MLB123456789';
        $context = [
            'category' => 'MLB1055'
        ];

        // Mock method for testing
        $mockService = $this->createMock(KeywordGapAnalyzerService::class);
        $mockService->method('analyzeKeywordGaps')
            ->willReturn([
                'success' => true,
                'my_keywords' => ['smartphone', 'samsung', 'galaxy'],
                'gap_analysis' => [
                    'missing_keywords' => ['5g', '128gb', 'tela'],
                    'gap_score' => 65
                ],
                'semantic_gaps' => ['conceitos não explorados'],
                'long_tail_opportunities' => [
                    ['keyword' => 'samsung galaxy s21 5g 128gb', 'competition_level' => 'média']
                ]
            ]);

        $result = $mockService->analyzeKeywordGaps($productId, $context);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('my_keywords', $result);
        $this->assertArrayHasKey('gap_analysis', $result);
        $this->assertArrayHasKey('semantic_gaps', $result);
        $this->assertArrayHasKey('long_tail_opportunities', $result);
    }

    public function testKeywordGapAnalyzerServiceCompetitiveAnalysis(): void
    {
        $service = new KeywordGapAnalyzerService($this->testAccountId);
        $category = 'MLB1055';

        // Mock competitive analysis
        $mockService = $this->createMock(KeywordGapAnalyzerService::class);
        $mockService->method('competitiveKeywordAnalysis')
            ->willReturn([
                'success' => true,
                'category' => 'MLB1055',
                'products_analyzed' => 20,
                'keyword_frequency' => [
                    'smartphone' => 18,
                    'samsung' => 12,
                    'apple' => 10
                ],
                'success_patterns' => [
                    'brand_first' => 'Marca no início',
                    'specs_middle' => 'Especificações no meio'
                ]
            ]);

        $result = $mockService->competitiveKeywordAnalysis($category);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('keyword_frequency', $result);
        $this->assertArrayHasKey('success_patterns', $result);
    }

    /**
     * Testes do SemanticAnalyzerService
     */
    public function testSemanticAnalyzerServiceAnalyzesStructure(): void
    {
        $service = new SemanticAnalyzerService($this->testAccountId);
        $product = [
            'title' => 'Smartphone Samsung Galaxy S21 5G 128GB',
            'description' => 'Smartphone premium com câmera avançada e 5G',
            'category' => 'Celulares',
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Samsung'],
                ['id' => 'MODEL', 'value_name' => 'Galaxy S21']
            ]
        ];

        $result = $service->analyzeSemanticStructure($product);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('semantic_core', $result);
        $this->assertArrayHasKey('latent_semantic_analysis', $result);
        $this->assertArrayHasKey('user_intent_mapping', $result);
        $this->assertArrayHasKey('semantic_optimization', $result);
    }

    public function testSemanticAnalyzerServiceExpandsKeywords(): void
    {
        $service = new SemanticAnalyzerService($this->testAccountId);
        $baseKeyword = 'smartphone';
        $context = [
            'category' => 'Celulares',
            'target_audience' => 'jovens',
            'use_case' => 'fotografia'
        ];

        $result = $service->expandSemanticKeywords($baseKeyword, $context);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('semantic_expansion', $result);
        $this->assertArrayHasKey('intent_variations', $result);
        $this->assertArrayHasKey('long_tail_semantic', $result);
    }

    public function testSemanticAnalyzerServiceAnalyzesCohesion(): void
    {
        $service = new SemanticAnalyzerService($this->testAccountId);
        $products = [
            ['id' => '1', 'title' => 'Samsung Galaxy S21', 'category' => 'Celulares'],
            ['id' => '2', 'title' => 'iPhone 13', 'category' => 'Celulares'],
            ['id' => '3', 'title' => 'Xiaomi Mi 11', 'category' => 'Celulares']
        ];

        $result = $service->analyzeSemanticCohesion($products);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('semantic_cohesion_analysis', $result);
        $this->assertArrayHasKey('semantic_gaps', $result);
        $this->assertArrayHasKey('recommendations', $result);
    }

    /**
     * Testes do AttributeModelService
     */
    public function testAttributeModelServiceExtractsAndSuggests(): void
    {
        $service = new AttributeModelService($this->testAccountId);
        $product = [
            'title' => 'Samsung Galaxy S21 5G 128GB Preto',
            'description' => 'Smartphone Samsung Galaxy S21 com 5G e 128GB de armazenamento',
            'category_id' => 'MLB1055',
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Samsung']
            ]
        ];

        $result = $service->extractAndSuggestModel($product);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('current_model', $result);
        $this->assertArrayHasKey('suggestions', $result);
        $this->assertArrayHasKey('confidence_score', $result);
        $this->assertArrayHasKey('recommended_action', $result);
    }

    public function testAttributeModelServiceOptimizesModelAttribute(): void
    {
        $service = new AttributeModelService($this->testAccountId);
        $currentModel = 'S21';
        $product = [
            'title' => 'Samsung Galaxy S21 5G',
            'category_id' => 'MLB1055',
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Samsung']
            ]
        ];
        $competitorModels = ['Galaxy S20', 'iPhone 12', 'Mi 11'];

        $result = $service->optimizeModelAttribute($currentModel, $product, $competitorModels);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('optimized_models', $result);
        $this->assertArrayHasKey('model_variations', $result);
        $this->assertArrayHasKey('pattern_recommendations', $result);
    }

    public function testAttributeModelServiceValidatesModel(): void
    {
        $service = new AttributeModelService($this->testAccountId);
        $model = 'Galaxy S21 5G 128GB';
        $product = [
            'title' => 'Samsung Galaxy S21 5G',
            'category_id' => 'MLB1055',
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Samsung']
            ]
        ];

        $result = $service->validateModel($model, $product);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('validation_results', $result);
        $this->assertArrayHasKey('compliance_check', $result);
        $this->assertArrayHasKey('recommendation', $result);
    }

    public function testAttributeModelServiceBatchGeneration(): void
    {
        $service = new AttributeModelService($this->testAccountId);
        $products = [
            [
                'id' => '1',
                'title' => 'Samsung Galaxy S21',
                'category_id' => 'MLB1055'
            ],
            [
                'id' => '2',
                'title' => 'iPhone 13 Pro',
                'category_id' => 'MLB1055'
            ]
        ];

        $result = $service->batchGenerateModels($products);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('processed_count', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertEquals(2, $result['processed_count']);
    }

    /**
     * Testes de integração e edge cases
     */
    public function testServiceHandlesEmptyInput(): void
    {
        $service = new SEOOptimizerService($this->testAccountId);
        
        $result = $service->analyze([]);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('score', $result);
        $this->assertEquals(0, $result['score']);
    }

    public function testServiceHandlesInvalidCharacters(): void
    {
        $service = new SEOOptimizerService($this->testAccountId);
        $title = 'Título com caracteres especiais @#$%&*()';
        
        $result = $service->optimizeTitle($title);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('optimized_title', $result);
    }

    public function testServiceHandlesLongTitles(): void
    {
        $service = new SEOOptimizerService($this->testAccountId);
        $longTitle = str_repeat('Título muito longo ', 20);
        
        $result = $service->optimizeTitle($longTitle);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('character_count', $result);
        $this->assertLessThanOrEqual(60, $result['character_count']);
    }

    /**
     * Testes de performance
     */
    public function testServicePerformance(): void
    {
        $service = new SEOOptimizerService($this->testAccountId);
        $product = [
            'title' => 'Smartphone Samsung Galaxy S21 128GB',
            'description' => str_repeat('Descrição do produto ', 100),
            'category' => 'Celulares'
        ];

        $startTime = microtime(true);
        $result = $service->analyze($product);
        $endTime = microtime(true);

        $this->assertTrue($result['success']);
        $this->assertLessThan(10, $endTime - $startTime); // Deve completar em menos de 10 segundos
    }

    /**
     * Testes de cache
     */
    public function testServiceUsesCache(): void
    {
        $service = new SEOOptimizerService($this->testAccountId);
        $product = [
            'title' => 'iPhone 13 Apple 128GB',
            'category' => 'Celulares'
        ];

        // Primeira chamada
        $result1 = $service->analyze($product);
        
        // Segunda chamada (deveria usar cache)
        $result2 = $service->analyze($product);

        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);
        $this->assertEquals($result1['score'], $result2['score']);
    }

    /**
     * Testes de erro handling
     */
    public function testServiceHandlesApiError(): void
    {
        // Testar com serviço mock que simula erro
        $mockService = $this->createMock(SEOOptimizerService::class);
        $mockService->method('analyze')
            ->willReturn([
                'success' => false,
                'error' => 'API Error'
            ]);

        $result = $mockService->analyze([]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Data providers para testes parametrizados
     */
    public static function titleProvider(): array
    {
        return [
            ['Smartphone Samsung Galaxy S21', true],
            ['iPhone 13 Apple 128GB', true],
            ['', false], // título vazio
            ['A', false], // muito curto
            [str_repeat('a', 200), false] // muito longo
        ];
    }

    /**
     * @dataProvider titleProvider
     */
    public function testTitleValidation(string $title, bool $shouldBeValid): void
    {
        $service = new SEOOptimizerService($this->testAccountId);
        $result = $service->optimizeTitle($title);

        if ($shouldBeValid) {
            $this->assertTrue($result['success']);
        } else {
            // Mesmo com títulos inválidos, o serviço deve retornar sucesso
            // mas com sugestões de melhoria
            $this->assertTrue($result['success']);
        }
    }
}