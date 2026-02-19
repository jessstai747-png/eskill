<?php

declare(strict_types=1);

namespace Tests\Unit\Services\SEO;

use PHPUnit\Framework\TestCase;
use App\Services\SEO\SearchCoverageService;

class SearchCoverageServiceTest extends TestCase
{
    private SearchCoverageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SearchCoverageService();
    }

    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(SearchCoverageService::class, $this->service);
    }

    public function testAnalyzeCoverageReturnsCorrectStructure(): void
    {
        $item = [
            'title' => 'Bauleto 41L Pro Tork Universal',
            'description' => str_repeat('Descrição detalhada do produto com muitas palavras para teste. ', 15),
            'attributes' => [
                ['id' => 'BRAND', 'name' => 'Marca', 'value_name' => 'Pro Tork'],
                ['id' => 'MODEL', 'name' => 'Modelo', 'value_name' => 'Smart Box'],
                ['id' => 'CAPACITY', 'name' => 'Capacidade', 'value_name' => '41L'],
                ['id' => 'COLOR', 'name' => 'Cor', 'value_name' => 'Preto'],
            ],
        ];

        $result = $this->service->analyzeCoverage($item);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('coverage', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('gaps', $result);
        $this->assertArrayHasKey('suggestions', $result);
    }

    public function testAnalyzeCoverageReturnsAllSearchTypes(): void
    {
        $item = ['title' => 'Produto Teste'];

        $result = $this->service->analyzeCoverage($item);
        $coverage = $result['coverage'];

        $expectedTypes = ['generica', 'qualificada', 'long_tail', 'marca_modelo', 'filtros'];
        foreach ($expectedTypes as $type) {
            $this->assertArrayHasKey($type, $coverage, "Missing search type: {$type}");
            $this->assertArrayHasKey('status', $coverage[$type]);
            $this->assertArrayHasKey('field', $coverage[$type]);
            $this->assertArrayHasKey('weight', $coverage[$type]);
        }
    }

    public function testCalculateCoverageScoreWithFullCoverage(): void
    {
        $coverage = [
            'generica' => ['status' => 'covered', 'weight' => 30],
            'qualificada' => ['status' => 'covered', 'weight' => 25],
            'long_tail' => ['status' => 'covered', 'weight' => 20],
            'marca_modelo' => ['status' => 'covered', 'weight' => 15],
            'filtros' => ['status' => 'covered', 'weight' => 10],
        ];

        $score = $this->service->calculateCoverageScore($coverage);

        $this->assertEquals(100, $score);
    }

    public function testCalculateCoverageScoreWithNoCoverage(): void
    {
        $coverage = [
            'generica' => ['status' => 'missing', 'weight' => 30],
            'qualificada' => ['status' => 'missing', 'weight' => 25],
            'long_tail' => ['status' => 'missing', 'weight' => 20],
            'marca_modelo' => ['status' => 'missing', 'weight' => 15],
            'filtros' => ['status' => 'missing', 'weight' => 10],
        ];

        $score = $this->service->calculateCoverageScore($coverage);

        $this->assertEquals(0, $score);
    }

    public function testCalculateCoverageScoreWithPartialCoverage(): void
    {
        $coverage = [
            'generica' => ['status' => 'covered', 'weight' => 30],      // 30
            'qualificada' => ['status' => 'covered', 'weight' => 25],   // 25
            'long_tail' => ['status' => 'missing', 'weight' => 20],     // 0
            'marca_modelo' => ['status' => 'missing', 'weight' => 15],  // 0
            'filtros' => ['status' => 'missing', 'weight' => 10],       // 0
        ];

        $score = $this->service->calculateCoverageScore($coverage);

        // (30 + 25) / 100 * 100 = 55
        $this->assertEquals(55, $score);
    }

    public function testIdentifyGapsReturnsOnlyMissingTypes(): void
    {
        $coverage = [
            'generica' => ['status' => 'covered', 'weight' => 30, 'field' => 'title'],
            'qualificada' => ['status' => 'missing', 'weight' => 25, 'field' => 'title_model'],
            'long_tail' => ['status' => 'covered', 'weight' => 20, 'field' => 'description'],
            'marca_modelo' => ['status' => 'missing', 'weight' => 15, 'field' => 'attributes_description'],
            'filtros' => ['status' => 'covered', 'weight' => 10, 'field' => 'attributes'],
        ];

        $gaps = $this->service->identifyGaps($coverage);

        $this->assertCount(2, $gaps);
        $this->assertArrayHasKey('qualificada', $gaps);
        $this->assertArrayHasKey('marca_modelo', $gaps);
        $this->assertArrayNotHasKey('generica', $gaps);
        $this->assertArrayNotHasKey('long_tail', $gaps);
        $this->assertArrayNotHasKey('filtros', $gaps);
    }

    public function testIdentifyGapsReturnsCorrectStructure(): void
    {
        $coverage = [
            'generica' => ['status' => 'missing', 'weight' => 30, 'field' => 'title'],
        ];

        $gaps = $this->service->identifyGaps($coverage);

        $this->assertArrayHasKey('generica', $gaps);
        $gap = $gaps['generica'];
        
        $this->assertArrayHasKey('type', $gap);
        $this->assertArrayHasKey('field', $gap);
        $this->assertArrayHasKey('weight', $gap);
        $this->assertArrayHasKey('suggestion', $gap);
        $this->assertEquals('generica', $gap['type']);
        $this->assertEquals('title', $gap['field']);
        $this->assertEquals(30, $gap['weight']);
    }

    public function testSuggestImprovementsSortsByImportance(): void
    {
        $gaps = [
            'filtros' => ['type' => 'filtros', 'field' => 'attributes', 'weight' => 10, 'suggestion' => 'Add filters'],
            'generica' => ['type' => 'generica', 'field' => 'title', 'weight' => 30, 'suggestion' => 'Improve title'],
            'long_tail' => ['type' => 'long_tail', 'field' => 'description', 'weight' => 20, 'suggestion' => 'Expand description'],
        ];

        $suggestions = $this->service->suggestImprovements($gaps);

        $this->assertCount(3, $suggestions);
        // Should be sorted by importance (weight) descending
        $this->assertEquals('generica', $suggestions[0]['type']);
        $this->assertEquals('long_tail', $suggestions[1]['type']);
        $this->assertEquals('filtros', $suggestions[2]['type']);
    }

    public function testGenericSearchCoveredWithGoodTitle(): void
    {
        $item = [
            'title' => 'Bauleto Universal 41L',
        ];

        $result = $this->service->analyzeCoverage($item);

        $this->assertEquals('covered', $result['coverage']['generica']['status']);
    }

    public function testGenericSearchNotCoveredWithEmptyTitle(): void
    {
        $item = [
            'title' => '',
        ];

        $result = $this->service->analyzeCoverage($item);

        $this->assertEquals('missing', $result['coverage']['generica']['status']);
    }

    public function testLongTailCoveredWithLongDescription(): void
    {
        $item = [
            'title' => 'Produto',
            'description' => str_repeat('Esta é uma descrição bem completa do produto. ', 20), // >80 words
        ];

        $result = $this->service->analyzeCoverage($item);

        $this->assertEquals('covered', $result['coverage']['long_tail']['status']);
    }

    public function testLongTailNotCoveredWithShortDescription(): void
    {
        $item = [
            'title' => 'Produto',
            'description' => 'Descrição curta.',
        ];

        $result = $this->service->analyzeCoverage($item);

        $this->assertEquals('missing', $result['coverage']['long_tail']['status']);
    }

    public function testBrandModelCoveredWithAttributes(): void
    {
        $item = [
            'title' => 'Produto',
            'attributes' => [
                ['id' => 'BRAND', 'name' => 'Marca', 'value_name' => 'Pro Tork'],
            ],
        ];

        $result = $this->service->analyzeCoverage($item);

        $this->assertEquals('covered', $result['coverage']['marca_modelo']['status']);
    }

    public function testBrandModelNotCoveredWithoutBrandAttribute(): void
    {
        $item = [
            'title' => 'Produto',
            'attributes' => [
                ['id' => 'COLOR', 'name' => 'Cor', 'value_name' => 'Preto'],
            ],
        ];

        $result = $this->service->analyzeCoverage($item);

        $this->assertEquals('missing', $result['coverage']['marca_modelo']['status']);
    }

    public function testFiltersCoveredWithEnoughAttributes(): void
    {
        $item = [
            'title' => 'Produto',
            'attributes' => [
                ['id' => 'ATTR1', 'value_name' => 'Value1'],
                ['id' => 'ATTR2', 'value_name' => 'Value2'],
                ['id' => 'ATTR3', 'value_name' => 'Value3'],
            ],
        ];

        $result = $this->service->analyzeCoverage($item);

        $this->assertEquals('covered', $result['coverage']['filtros']['status']);
    }

    public function testFiltersNotCoveredWithFewAttributes(): void
    {
        $item = [
            'title' => 'Produto',
            'attributes' => [
                ['id' => 'ATTR1', 'value_name' => 'Value1'],
            ],
        ];

        $result = $this->service->analyzeCoverage($item);

        $this->assertEquals('missing', $result['coverage']['filtros']['status']);
    }

    public function testCalculateCoverageScoreHandlesEmptyArray(): void
    {
        $score = $this->service->calculateCoverageScore([]);

        $this->assertEquals(0, $score);
    }

    public function testAnalyzeCoverageWithCompleteItem(): void
    {
        $item = [
            'title' => 'Bauleto 41L Pro Tork Universal Moto Preto',
            'model' => 'Smart Box Pro',
            'description' => str_repeat('Bauleto profissional para moto com capacidade de 41 litros. Material ABS de alta resistência. Ideal para delivery, motoboy e uso urbano. ', 10),
            'attributes' => [
                ['id' => 'BRAND', 'name' => 'Marca', 'value_name' => 'Pro Tork'],
                ['id' => 'MODEL', 'name' => 'Modelo', 'value_name' => 'Smart Box Pro'],
                ['id' => 'CAPACITY', 'name' => 'Capacidade', 'value_name' => '41L'],
                ['id' => 'MATERIAL', 'name' => 'Material', 'value_name' => 'ABS'],
                ['id' => 'COLOR', 'name' => 'Cor', 'value_name' => 'Preto'],
            ],
        ];

        $result = $this->service->analyzeCoverage($item);

        // All search types should be covered with a complete item
        $this->assertEquals(100, $result['score']);
        $this->assertEmpty($result['gaps']);
        $this->assertEmpty($result['suggestions']);
    }

    public function testDescriptionAsArrayWithPlainText(): void
    {
        $item = [
            'title' => 'Produto Teste',
            'description' => [
                'plain_text' => str_repeat('Descrição detalhada do produto. ', 30),
            ],
        ];

        $result = $this->service->analyzeCoverage($item);

        $this->assertEquals('covered', $result['coverage']['long_tail']['status']);
    }
}
