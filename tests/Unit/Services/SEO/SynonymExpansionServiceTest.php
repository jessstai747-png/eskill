<?php

declare(strict_types=1);

namespace Tests\Unit\Services\SEO;

use PHPUnit\Framework\TestCase;
use App\Services\SEO\SynonymExpansionService;

class SynonymExpansionServiceTest extends TestCase
{
    private SynonymExpansionService $service;

    protected function setUp(): void
    {
        $this->service = new SynonymExpansionService();
    }

    public function testIdentifyLevelGenerico(): void
    {
        $title = "Bauleto 41 Litros Universal";
        $level = $this->service->identifyLevel($title);
        $this->assertEquals('nivel_1', $level);
    }

    public function testGenerateOptimizedModel(): void
    {
        $title = "Bauleto Baú 41 Litros Universal";
        $result = $this->service->generateOptimizedModel($title, 'MLB3530');

        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('synonyms_used', $result);
        $this->assertArrayHasKey('score', $result);

        // Não deve repetir palavras do título
        $this->assertStringNotContainsString('bauleto', strtolower($result['model']));
        $this->assertStringNotContainsString('baú', strtolower($result['model']));
    }

    public function testSelectForFieldModel(): void
    {
        $title = "Bauleto 41L Universal";
        $synonyms = $this->service->selectForField($title, 'model', 'MLB3530');

        $this->assertIsArray($synonyms);
        $this->assertLessThanOrEqual(7, count($synonyms));

        // Deve retornar sinônimos de níveis 2 e 3
        foreach ($synonyms as $synonym) {
            $this->assertNotEmpty($synonym['word']);
            $this->assertArrayHasKey('score', $synonym);
        }
    }
    
    public function testExpandSynonyms(): void
    {
        $title = "Bauleto 41L Universal";
        $synonyms = $this->service->expand($title, 'MLB3530');
        
        $this->assertIsArray($synonyms);
        $this->assertArrayHasKey('nivel_1_generico', $synonyms);
        $this->assertArrayHasKey('nivel_2_qualificado', $synonyms);
    }
    
    public function testGetHierarchy(): void
    {
        $hierarchy = $this->service->getHierarchy('MLB3530');
        
        $this->assertIsArray($hierarchy);
        $this->assertNotEmpty($hierarchy);
    }
}