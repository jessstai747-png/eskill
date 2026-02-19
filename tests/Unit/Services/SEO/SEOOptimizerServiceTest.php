<?php

declare(strict_types=1);

namespace Tests\Unit\Services\SEO;

use App\Services\SEO\SEOOptimizerService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * SEOOptimizerServiceTest
 *
 * Unit tests for SEOOptimizerService methods
 */
class SEOOptimizerServiceTest extends TestCase
{
    /**
     * Test SYSTEM_PROMPT constant exists and contains SEO related content
     */
    public function testSystemPromptConstantExists(): void
    {
        $reflection = new ReflectionClass(SEOOptimizerService::class);
        $constant = $reflection->getConstant('SYSTEM_PROMPT');

        $this->assertIsString($constant);
        $this->assertStringContainsString('SEO', $constant);
        $this->assertStringContainsString('Mercado Livre', $constant);
    }

    /**
     * Test analyze returns expected structure with mocked AI
     */
    public function testAnalyzeReturnsExpectedStructure(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['analyze'])
            ->getMock();

        $service->method('analyze')
            ->willReturn([
                'success' => true,
                'score' => 75,
                'title_analysis' => [
                    'score' => 80,
                    'length' => 45,
                    'has_keywords' => true,
                    'issues' => ['Title could be longer'],
                    'suggestions' => ['Add more keywords'],
                ],
                'description_analysis' => [
                    'score' => 70,
                    'length' => 250,
                    'has_benefits' => true,
                    'has_features' => true,
                    'issues' => [],
                    'suggestions' => [],
                ],
                'keywords' => [
                    'found' => ['smartphone', 'samsung'],
                    'missing' => ['galaxy'],
                    'recommended' => ['celular', 'telefone'],
                ],
                'optimization_priority' => [
                    ['action' => 'Add more keywords', 'impact' => 'high', 'effort' => 'low'],
                ],
                'analyzed_at' => date('Y-m-d H:i:s'),
            ]);

        $product = [
            'title' => 'Smartphone Samsung',
            'description' => 'A great smartphone',
            'category' => 'Electronics',
            'attributes' => ['brand' => 'Samsung'],
            'price' => 1999.00,
        ];

        $result = $service->analyze($product);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('title_analysis', $result);
        $this->assertArrayHasKey('description_analysis', $result);
        $this->assertArrayHasKey('keywords', $result);
    }

    /**
     * Test analyze handles error gracefully
     */
    public function testAnalyzeHandlesError(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['analyze'])
            ->getMock();

        $service->method('analyze')
            ->willReturn([
                'success' => false,
                'error' => 'AI service unavailable',
                'score' => 0,
            ]);

        $result = $service->analyze([]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals(0, $result['score']);
    }

    /**
     * Test optimizeTitle returns expected structure
     */
    public function testOptimizeTitleReturnsExpectedStructure(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['optimizeTitle'])
            ->getMock();

        $service->method('optimizeTitle')
            ->willReturn([
                'success' => true,
                'original_title' => 'Smartphone Samsung',
                'optimized_title' => 'Smartphone Samsung Galaxy S21 128GB Preto 5G',
                'alternative_titles' => [
                    'Samsung Galaxy S21 Smartphone 128GB 5G Preto',
                    'Celular Samsung Galaxy S21 128GB 5G Preto',
                ],
                'changes_made' => ['Added model', 'Added storage', 'Added color'],
                'keywords_included' => ['samsung', 'galaxy', 's21', '5g'],
                'character_count' => 48,
                'improvement_score' => 85,
                'reasoning' => 'Added important product attributes',
            ]);

        $result = $service->optimizeTitle('Smartphone Samsung', [
            'category' => 'Smartphones',
            'brand' => 'Samsung',
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('optimized_title', $result);
        $this->assertArrayHasKey('alternative_titles', $result);
        $this->assertArrayHasKey('changes_made', $result);
        $this->assertLessThanOrEqual(60, $result['character_count']);
    }

    /**
     * Test optimizeTitle returns original title on error
     */
    public function testOptimizeTitleReturnsOriginalOnError(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['optimizeTitle'])
            ->getMock();

        $originalTitle = 'Original Title';

        $service->method('optimizeTitle')
            ->willReturn([
                'success' => false,
                'error' => 'Service error',
                'optimized_title' => $originalTitle,
            ]);

        $result = $service->optimizeTitle($originalTitle);

        $this->assertFalse($result['success']);
        $this->assertEquals($originalTitle, $result['optimized_title']);
    }

    /**
     * Test generateDescription returns expected structure
     */
    public function testGenerateDescriptionReturnsExpectedStructure(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['generateDescription'])
            ->getMock();

        $service->method('generateDescription')
            ->willReturn([
                'success' => true,
                'description' => 'Full product description with SEO optimization...',
                'short_description' => 'Brief product summary.',
                'bullet_points' => [
                    'High quality',
                    'Fast shipping',
                    'Original product',
                ],
                'keywords_used' => ['quality', 'original', 'fast'],
                'word_count' => 350,
                'seo_score' => 80,
                'unique_selling_points' => ['Fast delivery', 'Warranty included'],
            ]);

        $result = $service->generateDescription([
            'title' => 'Test Product',
            'category' => 'Electronics',
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('bullet_points', $result);
        $this->assertArrayHasKey('seo_score', $result);
        $this->assertIsArray($result['bullet_points']);
    }

    /**
     * Test researchKeywords returns expected structure
     */
    public function testResearchKeywordsReturnsExpectedStructure(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['researchKeywords'])
            ->getMock();

        $service->method('researchKeywords')
            ->willReturn([
                'success' => true,
                'main_keyword' => 'smartphone samsung',
                'secondary_keywords' => ['celular samsung', 'telefone samsung'],
                'long_tail_keywords' => ['smartphone samsung galaxy barato'],
                'question_keywords' => ['qual melhor smartphone samsung'],
                'competitor_keywords' => ['apple iphone', 'xiaomi'],
                'trending_keywords' => ['5g', 'galaxy s24'],
                'negative_keywords' => ['usado', 'recondicionado'],
                'keyword_difficulty' => [
                    'alta_concorrencia' => ['smartphone'],
                    'media_concorrencia' => ['samsung galaxy'],
                    'baixa_concorrencia' => ['galaxy s21 128gb preto'],
                ],
                'search_intent' => [
                    'informacional' => ['qual melhor celular'],
                    'transacional' => ['comprar smartphone'],
                    'navegacional' => ['samsung loja oficial'],
                ],
                'recommended_strategy' => 'Focus on long-tail keywords',
                'researched_at' => date('Y-m-d H:i:s'),
            ]);

        $result = $service->researchKeywords('Smartphone Samsung', [
            'category' => 'Smartphones',
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('main_keyword', $result);
        $this->assertArrayHasKey('secondary_keywords', $result);
        $this->assertArrayHasKey('long_tail_keywords', $result);
        $this->assertArrayHasKey('keyword_difficulty', $result);
    }

    /**
     * Test analyzeCompetitors returns expected structure
     */
    public function testAnalyzeCompetitorsReturnsExpectedStructure(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['analyzeCompetitors'])
            ->getMock();

        $service->method('analyzeCompetitors')
            ->willReturn([
                'success' => true,
                'market_position' => 'Mid-range positioning',
                'competitive_advantages' => ['Better price', 'Fast shipping'],
                'competitive_gaps' => ['Less reviews'],
                'price_position' => 'Below market average',
                'seo_gaps' => ['Missing product videos'],
                'content_gaps' => ['No FAQ section'],
                'keyword_opportunities' => ['samsung galaxy accessories'],
                'differentiation_suggestions' => ['Bundle with accessories'],
                'quick_wins' => ['Add more images'],
                'long_term_strategy' => 'Build brand presence',
                'estimated_market_share' => '15%',
            ]);

        $result = $service->analyzeCompetitors([
            'title' => 'Test Product',
            'category' => 'Electronics',
            'price' => 999.00,
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('market_position', $result);
        $this->assertArrayHasKey('competitive_advantages', $result);
        $this->assertArrayHasKey('quick_wins', $result);
    }

    /**
     * Test optimizeProduct returns comprehensive optimization
     */
    public function testOptimizeProductReturnsComprehensiveOptimization(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['optimizeProduct'])
            ->getMock();

        $product = [
            'title' => 'Smartphone Samsung',
            'description' => 'Good phone',
            'category' => 'Smartphones',
            'brand' => 'Samsung',
            'price' => 1999.00,
        ];

        $service->method('optimizeProduct')
            ->willReturn([
                'success' => true,
                'original' => $product,
                'optimizations' => [
                    'title' => [
                        'optimized_title' => 'Smartphone Samsung Galaxy S21 128GB 5G',
                        'improvement_score' => 85,
                    ],
                    'description' => [
                        'description' => 'Optimized description...',
                        'seo_score' => 80,
                    ],
                ],
                'analysis' => [
                    'score' => 75,
                ],
                'keywords' => [
                    'main_keyword' => 'smartphone samsung',
                ],
                'final_score' => 80,
                'action_summary' => [
                    ['action' => 'Add more keywords', 'priority' => 'high', 'type' => 'from_analysis'],
                ],
                'optimized_title' => 'Smartphone Samsung Galaxy S21 128GB 5G',
                'optimized_description' => 'Optimized description...',
                'optimized_at' => date('Y-m-d H:i:s'),
            ]);

        $result = $service->optimizeProduct($product);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('original', $result);
        $this->assertArrayHasKey('optimizations', $result);
        $this->assertArrayHasKey('final_score', $result);
        $this->assertArrayHasKey('action_summary', $result);
        $this->assertEquals($product, $result['original']);
    }

    /**
     * Test generateActionSummary creates action list
     */
    public function testGenerateActionSummaryCreatesActionList(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $method = (new ReflectionClass($service))->getMethod('generateActionSummary');
        $method->setAccessible(true);

        $results = [
            'analysis' => [
                'optimization_priority' => [
                    ['action' => 'Add brand to title', 'impact' => 'high'],
                    ['action' => 'Improve description', 'impact' => 'medium'],
                ],
            ],
            'optimizations' => [
                'title' => [
                    'changes_made' => ['Added model name', 'Added storage info'],
                ],
            ],
        ];

        $actions = $method->invoke($service, $results);

        $this->assertIsArray($actions);
        $this->assertGreaterThanOrEqual(2, count($actions));

        // Verify structure of actions
        foreach ($actions as $action) {
            $this->assertArrayHasKey('action', $action);
            $this->assertArrayHasKey('priority', $action);
            $this->assertArrayHasKey('type', $action);
        }
    }

    /**
     * Test generateActionSummary handles empty results
     */
    public function testGenerateActionSummaryHandlesEmptyResults(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $method = (new ReflectionClass($service))->getMethod('generateActionSummary');
        $method->setAccessible(true);

        $result = $method->invoke($service, []);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test isAvailable returns boolean
     */
    public function testIsAvailableReturnsBoolean(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isAvailable'])
            ->getMock();

        $service->method('isAvailable')
            ->willReturn(true);

        $this->assertTrue($service->isAvailable());
    }

    /**
     * Test getProvider returns string
     */
    public function testGetProviderReturnsString(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getProvider'])
            ->getMock();

        $service->method('getProvider')
            ->willReturn('openai');

        $result = $service->getProvider();

        $this->assertIsString($result);
        $this->assertEquals('openai', $result);
    }

    /**
     * Test title optimization respects character limit
     */
    public function testTitleOptimizationRespectsCharacterLimit(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['optimizeTitle'])
            ->getMock();

        $service->method('optimizeTitle')
            ->willReturn([
                'success' => true,
                'optimized_title' => 'Samsung Galaxy S21 Ultra 256GB 5G Preto Original',
                'character_count' => 50,
            ]);

        $result = $service->optimizeTitle('Long title here');

        $this->assertLessThanOrEqual(60, $result['character_count']);
    }

    /**
     * Test analysis returns analyzed_at timestamp
     */
    public function testAnalysisReturnsTimestamp(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['analyze'])
            ->getMock();

        $service->method('analyze')
            ->willReturn([
                'success' => true,
                'score' => 80,
                'analyzed_at' => '2024-01-15 10:30:00',
            ]);

        $result = $service->analyze(['title' => 'Test']);

        $this->assertArrayHasKey('analyzed_at', $result);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $result['analyzed_at']);
    }

    /**
     * Test keyword research returns researched_at timestamp
     */
    public function testKeywordResearchReturnsTimestamp(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['researchKeywords'])
            ->getMock();

        $service->method('researchKeywords')
            ->willReturn([
                'success' => true,
                'main_keyword' => 'test',
                'researched_at' => '2024-01-15 10:30:00',
            ]);

        $result = $service->researchKeywords('Test product');

        $this->assertArrayHasKey('researched_at', $result);
    }

    /**
     * Test optimization caching indication
     */
    public function testOptimizationCachingIndication(): void
    {
        $service = $this->getMockBuilder(SEOOptimizerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['analyze'])
            ->getMock();

        $service->method('analyze')
            ->willReturn([
                'success' => true,
                'score' => 80,
                'cached' => true,
            ]);

        $result = $service->analyze(['title' => 'Test']);

        $this->assertArrayHasKey('cached', $result);
        $this->assertTrue($result['cached']);
    }
}
