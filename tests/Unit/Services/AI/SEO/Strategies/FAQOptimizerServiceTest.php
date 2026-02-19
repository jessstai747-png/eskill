<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI\SEO\Strategies;

use App\Services\AI\SEO\Strategies\FAQOptimizerService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\AI\SEO\Strategies\FAQOptimizerService
 */
class FAQOptimizerServiceTest extends TestCase
{
    private FAQOptimizerService $service;

    protected function setUp(): void
    {
        $this->service = new FAQOptimizerService();
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(FAQOptimizerService::class, $this->service);
    }

    public function testGenerateFAQs(): void
    {
        $productData = [
            'title' => 'Baú Moto 80L Pro Max Delivery',
            'category_id' => 'MLB3530',
            'price' => 299.90,
            'attributes' => [
                ['id' => 'CAPACITY', 'value_name' => '80L'],
                ['id' => 'BRAND', 'value_name' => 'ProTork']
            ]
        ];
        
        $result = $this->service->generateFAQs($productData, 5);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('faqs', $result);
    }

    public function testOptimizeFAQs(): void
    {
        $faqs = [
            ['question' => 'Cabe capacete?', 'answer' => 'Sim, cabe 1 capacete.'],
            ['question' => 'É universal?', 'answer' => 'Sim, serve em várias motos.']
        ];
        $keywords = ['delivery', 'motoboy', 'universal'];
        
        $result = $this->service->optimizeFAQs($faqs, $keywords);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('optimized_faqs', $result);
    }

    public function testGenerateSchema(): void
    {
        $faqs = [
            ['question' => 'Cabe capacete?', 'answer' => 'Sim, cabe 1 capacete fechado.'],
            ['question' => 'É resistente?', 'answer' => 'Sim, feito em ABS.']
        ];
        
        $result = $this->service->generateSchema($faqs);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('@type', $result);
        $this->assertEquals('FAQPage', $result['@type']);
    }

    public function testGenerateHTML(): void
    {
        $faqs = [
            ['question' => 'Cabe capacete?', 'answer' => 'Sim'],
            ['question' => 'É universal?', 'answer' => 'Sim']
        ];
        
        $result = $this->service->generateHTML($faqs);
        
        $this->assertIsString($result);
        $this->assertStringContainsString('Cabe capacete?', $result);
    }

    public function testGenerateDescriptionText(): void
    {
        $faqs = [
            ['question' => 'Cabe capacete?', 'answer' => 'Sim, cabe 1 capacete.'],
            ['question' => 'É resistente?', 'answer' => 'Sim, feito em ABS.']
        ];
        
        $result = $this->service->generateDescriptionText($faqs);
        
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testValidateFAQs(): void
    {
        $faqs = [
            ['question' => 'Cabe capacete?', 'answer' => 'Sim'],  // Answer too short
            ['question' => '', 'answer' => 'Resposta'],  // Empty question
            ['question' => 'Pergunta válida?', 'answer' => 'Resposta válida e completa.']
        ];
        
        $result = $this->service->validateFAQs($faqs);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('is_valid', $result);
    }

    public function testSuggestForCategory(): void
    {
        $result = $this->service->suggestForCategory('MLB3530');
        
        $this->assertIsArray($result);
    }

    public function testEmptyProductDataHandling(): void
    {
        $productData = [];
        
        $result = $this->service->generateFAQs($productData, 5);
        
        $this->assertIsArray($result);
        // Should handle empty data gracefully
    }
}
