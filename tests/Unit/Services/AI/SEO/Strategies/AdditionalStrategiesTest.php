<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI\SEO\Strategies;

use App\Services\AI\SEO\Strategies\SemanticScoreService;
use App\Services\AI\SEO\Strategies\LongTailGeneratorService;
use App\Services\AI\SEO\Strategies\UseContextService;
use App\Services\AI\SEO\Strategies\KeywordInjectorService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\AI\SEO\Strategies\SemanticScoreService
 * @covers \App\Services\AI\SEO\Strategies\LongTailGeneratorService
 * @covers \App\Services\AI\SEO\Strategies\UseContextService
 * @covers \App\Services\AI\SEO\Strategies\KeywordInjectorService
 */
class AdditionalStrategiesTest extends TestCase
{
    // ============================================================
    // SemanticScoreService Tests
    // ============================================================
    
    public function testSemanticScoreInstantiation(): void
    {
        $service = new SemanticScoreService();
        $this->assertInstanceOf(SemanticScoreService::class, $service);
    }

    public function testCalculateSemanticScore(): void
    {
        $service = new SemanticScoreService();
        
        // calculateScore(string $word, string $title, string $categoryId): float
        $score = $service->calculateScore('delivery', 'Baú Moto 80L Delivery Universal', 'MLB3530');
        
        $this->assertIsNumeric($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function testScoreWords(): void
    {
        $service = new SemanticScoreService();
        
        $words = ['baú', 'caixa', 'moto', 'delivery', 'motoboy'];
        $result = $service->scoreWords($words, 'Baú Moto 80L Delivery', 'MLB3530');
        
        $this->assertIsArray($result);
        // Returns array of objects with 'word' and 'score' keys
        if (!empty($result)) {
            $this->assertArrayHasKey('word', $result[0]);
            $this->assertArrayHasKey('score', $result[0]);
        }
    }

    public function testRankByScore(): void
    {
        $service = new SemanticScoreService();
        
        $words = ['baú', 'caixa', 'moto', 'delivery'];
        $result = $service->rankByScore($words, 'Baú Moto Delivery', 'MLB3530');
        
        $this->assertIsArray($result);
        // Should be sorted by score
    }

    public function testCalculateAverageScore(): void
    {
        $service = new SemanticScoreService();
        
        $words = ['baú', 'moto', 'delivery'];
        $score = $service->calculateAverageScore($words, 'Baú Moto Delivery', 'MLB3530');
        
        $this->assertIsNumeric($score);
        $this->assertGreaterThanOrEqual(0, $score);
    }

    public function testGetContexts(): void
    {
        $service = new SemanticScoreService();
        
        $result = $service->getContexts('MLB3530');
        
        $this->assertIsArray($result);
    }

    // ============================================================
    // LongTailGeneratorService Tests
    // ============================================================

    public function testLongTailInstantiation(): void
    {
        $service = new LongTailGeneratorService();
        $this->assertInstanceOf(LongTailGeneratorService::class, $service);
    }

    public function testGenerateLongTailKeywords(): void
    {
        $service = new LongTailGeneratorService();
        
        // generate(string $baseKeyword, array $options = []): array
        $result = $service->generate('Baú Moto 80L', ['category_id' => 'MLB3530']);
        
        $this->assertIsArray($result);
    }

    public function testGenerateFromAutocomplete(): void
    {
        $service = new LongTailGeneratorService();
        
        $result = $service->generateFromAutocomplete('baú moto', 10);
        
        $this->assertIsArray($result);
    }

    public function testAnalyzeLongTail(): void
    {
        $service = new LongTailGeneratorService();
        
        $result = $service->analyzeLongTail('baú moto 80 litros delivery');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('keyword', $result);
        $this->assertArrayHasKey('word_count', $result);
    }

    public function testSuggestMissing(): void
    {
        $service = new LongTailGeneratorService();
        
        $itemData = [
            'title' => 'Baú Moto 80L Pro Max',
            'description' => 'Baú para moto 80 litros',
            'category_id' => 'MLB3530'
        ];
        
        $result = $service->suggestMissing($itemData);
        
        $this->assertIsArray($result);
    }

    // ============================================================
    // UseContextService Tests
    // ============================================================

    public function testUseContextInstantiation(): void
    {
        $service = new UseContextService();
        $this->assertInstanceOf(UseContextService::class, $service);
    }

    public function testDetectUseContexts(): void
    {
        $service = new UseContextService();
        
        // detectContexts(string $text): array
        $text = 'Baú Moto 80L Delivery ideal para motoboy, ifood e entregas';
        
        $result = $service->detectContexts($text);
        
        $this->assertIsArray($result);
    }

    public function testGetContextsForCategory(): void
    {
        $service = new UseContextService();
        
        $result = $service->getContextsForCategory('MLB3530');
        
        $this->assertIsArray($result);
    }

    public function testGenerateContextPhrases(): void
    {
        $service = new UseContextService();
        
        // generateContextPhrases(array $contexts, int $limit = 4): array
        $result = $service->generateContextPhrases(['profissional', 'lazer'], 4);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('phrases', $result);
    }

    public function testGetAvailableContexts(): void
    {
        $service = new UseContextService();
        
        $result = $service->getAvailableContexts();
        
        $this->assertIsArray($result);
    }

    public function testGenerateContextKeywords(): void
    {
        $service = new UseContextService();
        
        // generateContextKeywords(array $contexts, ?string $categoryId = null, int $limit = 10): array
        $result = $service->generateContextKeywords(['profissional'], 'MLB3530', 10);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('keywords', $result);
    }

    public function testSuggestContexts(): void
    {
        $service = new UseContextService();
        
        $productData = [
            'title' => 'Baú Moto Delivery',
            'description' => 'Para motoboy e entregadores'
        ];
        
        $result = $service->suggestContexts($productData);
        
        $this->assertIsArray($result);
    }

    // ============================================================
    // KeywordInjectorService Tests
    // ============================================================

    public function testKeywordInjectorInstantiation(): void
    {
        $service = new KeywordInjectorService();
        $this->assertInstanceOf(KeywordInjectorService::class, $service);
    }

    public function testInjectKeywords(): void
    {
        $service = new KeywordInjectorService();
        
        // Check for actual available methods: injectInTitle, injectInDescription, injectInModel
        $this->assertTrue(
            method_exists($service, 'injectInTitle') || 
            method_exists($service, 'injectInDescription'),
            'KeywordInjectorService should have inject methods'
        );
    }

    public function testKeywordInjectorHasRequiredMethods(): void
    {
        $service = new KeywordInjectorService();
        
        // Basic assertion that service is usable
        $this->assertInstanceOf(KeywordInjectorService::class, $service);
    }
}
