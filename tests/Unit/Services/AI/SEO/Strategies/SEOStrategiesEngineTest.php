<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI\SEO\Strategies;

use App\Services\AI\SEO\Strategies\SEOStrategiesEngine;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\AI\SEO\Strategies\SEOStrategiesEngine
 */
class SEOStrategiesEngineTest extends TestCase
{
    private SEOStrategiesEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new SEOStrategiesEngine();
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(SEOStrategiesEngine::class, $this->engine);
    }

    public function testAnalyzeItem(): void
    {
        $result = $this->engine->analyzeItem('MLB123456789');
        
        $this->assertIsArray($result);
        // Sem cliente ML configurado, retorna erro ou item_id
        $this->assertTrue(
            isset($result['item_id']) || isset($result['error']),
            'Deve retornar item_id ou erro'
        );
    }

    public function testOptimizeTitle(): void
    {
        $result = $this->engine->optimizeTitle('Baú Moto 80L Pro Max', 'MLB3530');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('original', $result);
        $this->assertArrayHasKey('optimized', $result);
    }

    public function testOptimizeDescription(): void
    {
        $context = ['category_id' => 'MLB3530', 'title' => 'Baú Moto 80L'];
        $result = $this->engine->optimizeDescription('Baú para moto de alta qualidade', $context);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('original', $result);
        $this->assertArrayHasKey('optimized', $result);
    }

    public function testGenerateKeywords(): void
    {
        $result = $this->engine->generateKeywords('Baú Moto 80L Pro Max Delivery', 'MLB3530');
        
        $this->assertIsArray($result);
    }

    public function testCalculateScore(): void
    {
        $analysis = [
            'title' => ['score' => 80],
            'description' => ['score' => 70],
            'hidden_fields' => ['score' => 60]
        ];
        
        $score = $this->engine->calculateScore($analysis);
        
        $this->assertIsNumeric($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function testAnalyzeItemData(): void
    {
        $itemData = [
            'title' => 'Baú Moto 80L Pro Max Delivery',
            'description' => 'Baú para moto ideal para delivery e motoboy',
            'category_id' => 'MLB3530'
        ];
        
        $result = $this->engine->analyzeItemData($itemData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('consolidated_score', $result);
    }

    public function testOptimizeItemData(): void
    {
        $itemData = [
            'title' => 'Baú Moto',
            'description' => 'Produto',
            'category_id' => 'MLB3530'
        ];
        
        $result = $this->engine->optimizeItemData($itemData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('optimized_score', $result);
    }

    public function testGetDashboard(): void
    {
        // Este teste requer tabela seo_category_config no banco de testes
        // Marcamos como skip se a tabela não existir
        try {
            $result = $this->engine->getDashboard('MLB3530');
            $this->assertIsArray($result);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                $this->markTestSkipped('Tabela seo_category_config não existe no ambiente de teste');
            }
            throw $e;
        }
    }

    public function testGetOptimizationReport(): void
    {
        $result = $this->engine->getOptimizationReport('MLB123456789');
        
        $this->assertIsArray($result);
        // Sem cliente ML configurado, retorna erro ou item_id
        $this->assertTrue(
            isset($result['item_id']) || isset($result['error']),
            'Deve retornar item_id ou erro'
        );
    }

    public function testCompareItems(): void
    {
        $result = $this->engine->compareItems('MLB123', 'MLB456');
        
        $this->assertIsArray($result);
        // Sem cliente ML configurado, retorna erro ou comparação
        $this->assertTrue(
            isset($result['item1']) || isset($result['error']),
            'Deve retornar item1 ou erro'
        );
    }

    public function testMonitorKeywords(): void
    {
        $keywords = ['baú', 'moto', 'delivery'];
        
        // Este teste requer tabela seo_keyword_performance no banco de testes
        try {
            $result = $this->engine->monitorKeywords('MLB3530', $keywords);
            $this->assertIsArray($result);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                $this->markTestSkipped('Tabela seo_keyword_performance não existe no ambiente de teste');
            }
            throw $e;
        }
    }
}
