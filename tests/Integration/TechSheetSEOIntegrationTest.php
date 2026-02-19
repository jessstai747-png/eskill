<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\TechSheetSEOIntegrationService;
use App\Services\AI\SEO\Strategies\SEOAnalysisCacheService;
use App\Services\AI\SEO\Strategies\SEOStrategiesEngine;

/**
 * 🧪 Tests for Tech Sheet SEO Integration
 * 
 * Tests the integration between Ficha Técnica and SEO Strategies Engine
 */
class TechSheetSEOIntegrationTest extends TestCase
{
    private const TEST_ACCOUNT_ID = 1;
    private TechSheetSEOIntegrationService $service;
    private SEOAnalysisCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TechSheetSEOIntegrationService(self::TEST_ACCOUNT_ID);
        $this->cacheService = new SEOAnalysisCacheService(self::TEST_ACCOUNT_ID, 60);
    }

    /**
     * @test
     */
    public function it_can_instantiate_integration_service(): void
    {
        $this->assertInstanceOf(TechSheetSEOIntegrationService::class, $this->service);
    }

    /**
     * @test
     */
    public function it_can_instantiate_cache_service(): void
    {
        $this->assertInstanceOf(SEOAnalysisCacheService::class, $this->cacheService);
    }

    /**
     * @test
     */
    public function it_can_instantiate_strategies_engine(): void
    {
        $engine = new SEOStrategiesEngine(self::TEST_ACCOUNT_ID);
        $this->assertInstanceOf(SEOStrategiesEngine::class, $engine);
    }

    /**
     * @test
     */
    public function cache_service_returns_null_for_uncached_items(): void
    {
        $result = $this->cacheService->get('NONEXISTENT_ITEM_' . time());
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function cache_service_can_store_and_retrieve_analysis(): void
    {
        $itemId = 'TEST_ITEM_' . time();
        $analysis = [
            'overall_score' => 75.5,
            'strategies' => [
                'synonyms' => ['score' => 80],
                'hidden_fields' => ['score' => 70],
            ],
            'suggestions' => [
                ['value' => 'test keyword', 'reason' => 'SEO boost'],
            ],
            'category_id' => 'MLB1234',
            'item_title' => 'Test Product',
            'item_price' => 199.99,
        ];

        // Store in cache
        $stored = $this->cacheService->set($itemId, $analysis);
        $this->assertTrue($stored);

        // Retrieve from cache
        $cached = $this->cacheService->get($itemId);
        $this->assertNotNull($cached);
        $this->assertEquals(75.5, $cached['overall_score']);
        $this->assertTrue($cached['from_cache']);
        $this->assertArrayHasKey('strategies', $cached);

        // Cleanup
        $this->cacheService->invalidate($itemId);
    }

    /**
     * @test
     */
    public function cache_service_can_invalidate_item(): void
    {
        $itemId = 'TEST_INVALIDATE_' . time();
        $analysis = ['overall_score' => 50, 'strategies' => []];

        $this->cacheService->set($itemId, $analysis);
        $this->assertNotNull($this->cacheService->get($itemId));

        $invalidated = $this->cacheService->invalidate($itemId);
        $this->assertTrue($invalidated);
        $this->assertNull($this->cacheService->get($itemId));
    }

    /**
     * @test
     */
    public function cache_service_returns_stats(): void
    {
        $stats = $this->cacheService->getStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_cached', $stats);
        $this->assertArrayHasKey('valid_cache', $stats);
        $this->assertArrayHasKey('avg_score', $stats);
    }

    /**
     * @test
     */
    public function cache_service_returns_score_distribution(): void
    {
        $distribution = $this->cacheService->getScoreDistribution();
        
        $this->assertIsArray($distribution);
        $this->assertArrayHasKey('excellent', $distribution);
        $this->assertArrayHasKey('good', $distribution);
        $this->assertArrayHasKey('warning', $distribution);
        $this->assertArrayHasKey('critical', $distribution);
    }

    /**
     * @test
     */
    public function cache_service_can_batch_get(): void
    {
        $itemIds = ['MLB1', 'MLB2', 'MLB3'];
        $result = $this->cacheService->batchGet($itemIds);
        
        $this->assertIsArray($result);
    }

    /**
     * @test
     */
    public function cache_service_can_cleanup_expired(): void
    {
        $deleted = $this->cacheService->cleanup();
        $this->assertIsInt($deleted);
    }

    /**
     * @test
     */
    public function analyze_seo_returns_proper_error_for_invalid_item(): void
    {
        $result = $this->service->analyzeSEO('INVALID_ITEM_ID_' . time());
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * @test
     */
    public function get_seo_score_returns_array(): void
    {
        $result = $this->service->getSEOScore('TEST_ITEM');
        
        $this->assertIsArray($result);
    }

    /**
     * @test
     */
    public function generate_seo_report_returns_array(): void
    {
        $result = $this->service->generateSEOReport('TEST_ITEM');
        
        $this->assertIsArray($result);
    }

    /**
     * @test
     */
    public function batch_apply_seo_suggestions_handles_empty_array(): void
    {
        $result = $this->service->batchApplySEOSuggestions([]);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['total']);
    }

    /**
     * @test
     */
    public function all_strategy_services_can_be_instantiated(): void
    {
        $services = [
            \App\Services\AI\SEO\Strategies\SynonymExpansionService::class,
            \App\Services\AI\SEO\Strategies\SemanticScoreService::class,
            \App\Services\AI\SEO\Strategies\KeywordSourceService::class,
            \App\Services\AI\SEO\Strategies\HiddenFieldsService::class,
            \App\Services\AI\SEO\Strategies\KeywordInjectorService::class,
            \App\Services\AI\SEO\Strategies\SearchTypeCoverageService::class,
            \App\Services\AI\SEO\Strategies\FieldWeightService::class,
            \App\Services\AI\SEO\Strategies\UseContextService::class,
            \App\Services\AI\SEO\Strategies\LongTailGeneratorService::class,
            \App\Services\AI\SEO\Strategies\CompatibilityService::class,
            \App\Services\AI\SEO\Strategies\FAQOptimizerService::class,
            \App\Services\AI\SEO\Strategies\SEOStrategiesEngine::class,
        ];

        foreach ($services as $serviceClass) {
            $instance = new $serviceClass(self::TEST_ACCOUNT_ID);
            $this->assertInstanceOf($serviceClass, $instance);
        }
    }

    /**
     * @test
     * @dataProvider strategyMethodsProvider
     */
    public function strategies_engine_has_required_methods(string $method): void
    {
        $engine = new SEOStrategiesEngine(self::TEST_ACCOUNT_ID);
        $this->assertTrue(method_exists($engine, $method));
    }

    public static function strategyMethodsProvider(): array
    {
        return [
            ['analyzeItem'],
            ['optimizeTitle'],
            ['optimizeDescription'],
            ['generateKeywords'],
            ['calculateScore'],
        ];
    }
}
