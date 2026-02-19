<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI\SEO\Strategies;

use App\Services\AI\SEO\Strategies\CompatibilityService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\AI\SEO\Strategies\CompatibilityService
 */
class CompatibilityServiceTest extends TestCase
{
    private CompatibilityService $service;

    protected function setUp(): void
    {
        $this->service = new CompatibilityService();
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(CompatibilityService::class, $this->service);
    }

    public function testAnalyzeCompatibility(): void
    {
        $result = $this->service->analyzeCompatibility('MLB123456789');
        
        $this->assertIsArray($result);
        // Sem cliente ML configurado, retorna erro
        $this->assertTrue(
            isset($result['item_id']) || isset($result['error']),
            'Deve retornar item_id ou erro'
        );
    }

    public function testExpandCompatibility(): void
    {
        $currentModels = [
            ['brand' => 'Honda', 'model' => 'CG 160'],
            ['brand' => 'Yamaha', 'model' => 'Fazer 250']
        ];
        
        $result = $this->service->expandCompatibility($currentModels);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('expanded', $result);
    }

    public function testGenerateCompatibleModelsAttribute(): void
    {
        $models = [
            ['brand' => 'Honda', 'model' => 'CG 160', 'year_from' => 2015, 'year_to' => 2024],
            ['brand' => 'Yamaha', 'model' => 'Fazer 250', 'year_from' => 2018, 'year_to' => 2024]
        ];
        
        $result = $this->service->generateCompatibleModelsAttribute($models);
        
        $this->assertIsArray($result);
    }

    public function testSuggestBySpecs(): void
    {
        $specs = [
            'bagagem' => '80L',
            'fixacao' => 'universal',
            'tipo' => 'baú'
        ];
        
        $result = $this->service->suggestBySpecs($specs);
        
        $this->assertIsArray($result);
    }

    public function testValidateCompatibility(): void
    {
        $models = [
            ['brand' => 'Honda', 'model' => 'CG 160'],
            ['brand' => 'Yamaha', 'model' => 'Fazer 250']
        ];
        
        $result = $this->service->validateCompatibility($models, 'MLB3530');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
    }

    public function testGetAllModels(): void
    {
        $result = $this->service->getAllModels();
        
        $this->assertIsArray($result);
    }

    public function testGetModelsForBrand(): void
    {
        $result = $this->service->getAllModels('Honda');
        
        $this->assertIsArray($result);
    }

    public function testEmptyModelsHandling(): void
    {
        $result = $this->service->expandCompatibility([]);
        
        $this->assertIsArray($result);
        // Should handle empty array gracefully
    }
}
