<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Testes unitários para SEOKillerEngine
 * 
 * Testa lógica de análise de títulos, imagens e scoring
 * SEM dependências externas (ML API, DB, AI)
 */
class SEOKillerEngineTest extends TestCase
{
    /**
     * Helper: invoca método privado via Reflection
     */
    private function invokePrivateMethod(object $obj, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($obj, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($obj, $args);
    }

    /**
     * Cria instância do engine sem chamar o constructor (bypass DB/API)
     */
    private function createEngineStub(): object
    {
        $ref = new \ReflectionClass(\App\Services\AI\SEO\SEOKillerEngine::class);
        return $ref->newInstanceWithoutConstructor();
    }

    // ========================================
    // analyzeTitles()
    // ========================================

    public function testAnalyzeTitlesDetectsShortTitles(): void
    {
        $engine = $this->createEngineStub();

        // 4 de 5 itens com título curto (<40 chars) = 80% > threshold de 30%
        $items = [
            ['title' => 'Peça curta'],
            ['title' => 'Item'],
            ['title' => 'Outro item curto'],
            ['title' => 'Pequeno'],
            ['title' => 'Kit Reparo Carburador Completo Para Moto Honda CG 150 Titan'],
        ];

        $result = $this->invokePrivateMethod($engine, 'analyzeTitles', [$items]);

        $this->assertNotEmpty($result['problems'], 'Deve detectar problema de títulos curtos');
        $this->assertSame('title', $result['problems'][0]['category']);
        $this->assertSame(4, $result['problems'][0]['affected_items']);
    }

    public function testAnalyzeTitlesNoProblemsWhenGoodTitles(): void
    {
        $engine = $this->createEngineStub();

        // Todos os títulos com tamanho bom (40-60 chars)
        $items = [
            ['title' => 'Kit Reparo Carburador Honda CG 150 Titan 2010'],
            ['title' => 'Pistão Motor Yamaha YBR 125 Factor Completo'],
            ['title' => 'Correia Dentada Gates PowerGrip 129 Dentes'],
            ['title' => 'Junta Cabeçote Motor Volkswagen Gol 1.0 8V'],
            ['title' => 'Rolamento Roda Dianteira Fiat Palio 2008 Kit'],
        ];

        $result = $this->invokePrivateMethod($engine, 'analyzeTitles', [$items]);

        $this->assertEmpty($result['problems'], 'Não deve gerar problemas para títulos bons');
    }

    public function testAnalyzeTitlesDetectsNoNumbers(): void
    {
        $engine = $this->createEngineStub();

        // 4 de 5 sem números = 80% > threshold 50%
        $items = [
            ['title' => 'Kit Reparo Carburador Honda Titan Completo Peças'],
            ['title' => 'Pistão Motor Yamaha Factor Completo Original'],
            ['title' => 'Correia Dentada Gates PowerGrip Premium Nova'],
            ['title' => 'Junta Cabeçote Motor Volkswagen Gol Oitavo'],
            ['title' => 'Rolamento Roda Fiat Palio 2008 Kit Completo'],
        ];

        $result = $this->invokePrivateMethod($engine, 'analyzeTitles', [$items]);

        $this->assertNotEmpty($result['opportunities'], 'Deve detectar oportunidade de adicionar números');
        $this->assertSame('title', $result['opportunities'][0]['category']);
    }

    public function testAnalyzeTitlesHandlesEmptyItems(): void
    {
        $engine = $this->createEngineStub();

        $result = $this->invokePrivateMethod($engine, 'analyzeTitles', [[]]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('problems', $result);
        $this->assertArrayHasKey('opportunities', $result);
    }

    // ========================================
    // DIAGNOSIS_WEIGHTS consistency
    // ========================================

    public function testDiagnosisWeightsSumTo100(): void
    {
        $ref = new \ReflectionClass(\App\Services\AI\SEO\SEOKillerEngine::class);
        $weights = $ref->getConstant('DIAGNOSIS_WEIGHTS');

        $this->assertSame(100, array_sum($weights), 'Pesos do diagnóstico devem somar 100');
    }

    public function testDiagnosisHasAllRequiredDimensions(): void
    {
        $ref = new \ReflectionClass(\App\Services\AI\SEO\SEOKillerEngine::class);
        $weights = $ref->getConstant('DIAGNOSIS_WEIGHTS');

        $expected = [
            'title_quality',
            'description_quality',
            'attributes_completeness',
            'image_quality',
            'price_competitiveness',
            'visibility_factors',
        ];

        foreach ($expected as $dimension) {
            $this->assertArrayHasKey($dimension, $weights, "Dimensão '{$dimension}' deve existir nos pesos");
        }
    }
}
