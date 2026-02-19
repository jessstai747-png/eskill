<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI\SEO\Strategies;

use App\Services\AI\SEO\Strategies\SearchTypeCoverageService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\AI\SEO\Strategies\SearchTypeCoverageService
 */
class SearchTypeCoverageServiceTest extends TestCase
{
    private SearchTypeCoverageService $service;

    protected function setUp(): void
    {
        $this->service = new SearchTypeCoverageService();
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(SearchTypeCoverageService::class, $this->service);
    }

    public function testAnalyzeCoverage(): void
    {
        $itemData = [
            'title' => 'Baú Moto 80L Pro Max Delivery',
            'description' => 'Baú para moto ideal para delivery e motoboy',
            'category_id' => 'MLB3530',
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'ProTork'],
                ['id' => 'MODEL', 'value_name' => 'Smart Box 80']
            ]
        ];
        
        $result = $this->service->analyzeCoverage($itemData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('overall_score', $result);
        $this->assertArrayHasKey('coverage', $result);
    }

    public function testGenerateCoverageKeywords(): void
    {
        $productData = [
            'brand' => 'ProTork',
            'model' => 'Smart Box',
            'specs' => ['capacidade' => '80L', 'material' => 'plastico'],
            'use_case' => 'delivery'
        ];
        
        // generateCoverageKeywords(string $baseKeyword, array $productData, ?string $categoryId = null)
        $result = $this->service->generateCoverageKeywords('baú moto', $productData, 'MLB3530');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('keywords_by_type', $result);
    }

    public function testOptimizeForCoverage(): void
    {
        $itemData = [
            'title' => 'Baú Moto',
            'description' => 'Produto de qualidade',
            'category_id' => 'MLB3530'
        ];
        $keywords = ['delivery', 'motoboy', '80L', 'universal'];
        
        $result = $this->service->optimizeForCoverage($itemData, $keywords);
        
        $this->assertIsArray($result);
    }

    public function testClassifySearchQuery(): void
    {
        $result = $this->service->classifySearchQuery('baú moto 80 litros delivery');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('confidence', $result);
    }

    public function testSuggestMissingKeywords(): void
    {
        $itemData = [
            'title' => 'Baú Moto',
            'category_id' => 'MLB3530'
        ];
        
        // First get coverage analysis
        $coverage = $this->service->analyzeCoverage($itemData);
        
        // suggestMissingKeywords(array $currentCoverage, string $baseKeyword, ?string $categoryId = null)
        $result = $this->service->suggestMissingKeywords($coverage, 'baú moto', 'MLB3530');
        
        $this->assertIsArray($result);
    }

    public function testEmptyItemDataHandling(): void
    {
        $itemData = [];
        
        $result = $this->service->analyzeCoverage($itemData);
        
        $this->assertIsArray($result);
        // Should handle empty data gracefully
    }

    public function testClassifyDifferentQueryTypes(): void
    {
        // Generic search
        $generic = $this->service->classifySearchQuery('baú');
        $this->assertIsArray($generic);
        
        // Long tail search
        $longTail = $this->service->classifySearchQuery('baú moto 80 litros pro max delivery universal');
        $this->assertIsArray($longTail);
        
        // Brand/model search
        $brandModel = $this->service->classifySearchQuery('protork smart box 80');
        $this->assertIsArray($brandModel);
    }
}
