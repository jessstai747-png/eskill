<?php

namespace Tests\Unit\Services\SEO;

use PHPUnit\Framework\TestCase;
use App\Services\SEO\HiddenAttributesDetector;
use App\Services\SEO\SearchCoverageService;
use App\Services\SEO\CompatibilityService;

class CoverageAndHiddenFieldsTest extends TestCase
{
    private SearchCoverageService $coverageService;
    private CompatibilityService $compatibilityService;

    protected function setUp(): void
    {
        // Mock DB and Client if possible, but for unit testing logic we focus on logic methods
        $this->coverageService = new SearchCoverageService();
        $this->compatibilityService = new CompatibilityService();
    }

    public function testCompatibilityDetection()
    {
        $title = "Suporte Bauleto Honda CG 160 Titan";
        $detected = $this->compatibilityService->detectFromTitle($title);
        
        $this->assertArrayHasKey('honda', $detected);
        $this->assertContains('Titan', $detected['honda']);
        $this->assertContains('CG 160', $detected['honda']);
    }

    public function testCompatibilityGeneration()
    {
        $compatibilities = ['honda' => ['Titan', 'Fan']];
        $text = $this->compatibilityService->generateCompatibilityText($compatibilities);
        
        $this->assertStringContainsString('Honda', $text);
        $this->assertStringContainsString('Titan', $text);
        $this->assertStringContainsString('Fan', $text);
    }

    public function testCoverageAnalysis()
    {
        $item = [
            'id' => 'MLB123',
            'title' => 'Bauleto 45 Litros Universal Moto',
            'description' => str_repeat('Descricao rica do produto com muitas palavras para cobrir long tail. ', 10),
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Pro Tork'],
                ['id' => 'MODEL', 'value_name' => 'Smart Box'],
                ['id' => 'COLOR', 'value_name' => 'Preto'],
                ['id' => 'VOLUME', 'value_name' => '45 L'],
                ['id' => 'MATERIAL', 'value_name' => 'Plástico']
            ]
        ];

        $results = $this->coverageService->analyzeCoverage($item);
        
        $this->assertGreaterThan(80, $results['score']);
        $this->assertEquals('covered', $results['coverage']['generica']['status']);
        $this->assertEquals('covered', $results['coverage']['marca_modelo']['status']);
    }

    public function testCoverageGaps()
    {
        $item = [
            'id' => 'MLB123',
            'title' => 'Baú', // Curto
            'attributes' => [] // Sem atributos
        ];

        $results = $this->coverageService->analyzeCoverage($item);
        
        $this->assertLessThan(50, $results['score']);
        $this->assertArrayHasKey('qualificada', $results['gaps']);
        $this->assertArrayHasKey('marca_modelo', $results['gaps']);
    }

    public function testHiddenFieldGeneration()
    {
        $detector = new class extends HiddenAttributesDetector {
            public function __construct()
            {
            }
        };

        $keywords = $detector->generateKeywordsFieldValue(
            'Bauleto 45L Universal',
            ['bauleto', ['word' => 'moto']]
        );
        $this->assertStringContainsString('bauleto', strtolower($keywords));

        $mpn = $detector->generateMPNValue([
            'id' => 'MLB1234',
            'seller_custom_field' => 'SKU-99'
        ]);
        $this->assertEquals('SKU-99', $mpn);

        $line = $detector->generateLineValue([
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Pro Tork'],
                ['id' => 'MODEL', 'value_name' => 'Smart Box']
            ]
        ]);
        $this->assertStringContainsString('Pro Tork', $line);
    }
}
