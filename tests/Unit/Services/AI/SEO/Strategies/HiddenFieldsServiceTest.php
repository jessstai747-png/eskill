<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI\SEO\Strategies;

use App\Services\AI\SEO\Strategies\HiddenFieldsService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\AI\SEO\Strategies\HiddenFieldsService
 */
class HiddenFieldsServiceTest extends TestCase
{
    private HiddenFieldsService $service;

    protected function setUp(): void
    {
        $this->service = new HiddenFieldsService();
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(HiddenFieldsService::class, $this->service);
    }

    public function testAnalyzeItem(): void
    {
        // Sem cliente ML configurado, lança exceção
        $this->expectException(\RuntimeException::class);
        $this->service->analyzeItem('MLB123456789');
    }

    public function testGenerateSuggestions(): void
    {
        $productData = [
            'title' => 'Baú Moto 80L Pro Max',
            'description' => 'Baú para moto ideal para delivery',
            'attributes' => []
        ];
        
        $result = $this->service->generateSuggestions($productData, 'MLB3530');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('suggestions', $result);
    }

    public function testGetAvailableFields(): void
    {
        $result = $this->service->getAvailableFields('MLB3530');
        
        $this->assertIsArray($result);
    }

    public function testApplyToItemDryRun(): void
    {
        $fields = [
            'KEYWORDS' => 'baú moto delivery motoboy'
        ];
        
        // Sem cliente ML configurado, lança exceção
        $this->expectException(\RuntimeException::class);
        $this->service->applyToItem('MLB123456789', $fields, true);
    }

    public function testGenerateSuggestionsWithBrand(): void
    {
        $productData = [
            'title' => 'Baú Moto 80L Pro Max',
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'ProTork'],
                ['id' => 'MODEL', 'value_name' => 'Smart Box 80']
            ]
        ];
        
        $result = $this->service->generateSuggestions($productData, 'MLB3530');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('suggestions', $result);
    }

    public function testEmptyProductDataHandling(): void
    {
        $productData = [];
        
        $result = $this->service->generateSuggestions($productData, 'MLB3530');
        
        $this->assertIsArray($result);
        // Should handle empty data gracefully
    }
}
