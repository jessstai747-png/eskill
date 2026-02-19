<?php

namespace Tests\Unit\Services\SEO;

use PHPUnit\Framework\TestCase;
use App\Services\SEO\KeywordDistributionService;

class KeywordDistributionServiceTest extends TestCase
{
    private KeywordDistributionService $service;

    protected function setUp(): void
    {
        $this->service = new KeywordDistributionService();
    }

    public function testDistributeKeywords(): void
    {
        $item = [
            'title' => 'Bauleto 41L Universal',
            'category_id' => 'MLB3530'
        ];

        $result = $this->service->distribute($item, 'MLB3530');
        
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('description', $result);
        
        // Verificar limites
        $this->assertLessThanOrEqual(5, count($result['title']['keywords']));
        $this->assertLessThanOrEqual(7, count($result['model']['keywords']));
    }

    public function testValidateDensity(): void
    {
        $text = "Este é um bauleto de 41 litros. O bauleto é universal.";
        $keywords = ['bauleto'];
        
        $result = $this->service->validateDensity($text, $keywords);
        
        $this->assertArrayHasKey('bauleto', $result);
        $this->assertEquals(2, $result['bauleto']['occurrences']);
        $this->assertArrayHasKey('status', $result['bauleto']);
        $this->assertEquals('high', $result['bauleto']['status']);
    }

    public function testCalculateDensity(): void
    {
        $text = "Este é um bauleto de 41 litros. O bauleto é universal.";
        $keyword = 'bauleto';

        $density = $this->service->calculateDensity($text, $keyword);

        // 2 occurrences / 9 words * 100 = 22.22
        $this->assertEquals(22.22, round($density, 2));
    }

    public function testClassifyKeywords(): void
    {
        $keywords = ['bauleto', 'capacete', 'moto', 'viagem', 'bauleto para moto'];
        $categoryId = 'MLB3530';

        $result = $this->service->classifyKeywords($keywords, $categoryId);

        $this->assertArrayHasKey('core', $result);
        $this->assertArrayHasKey('support', $result);
        $this->assertArrayHasKey('technical', $result);
        $this->assertArrayHasKey('context', $result);
    }
}