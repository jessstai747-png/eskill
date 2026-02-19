<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI\SEO\Strategies;

use App\Services\AI\SEO\Strategies\SynonymExpansionService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\AI\SEO\Strategies\SynonymExpansionService
 */
class SynonymExpansionServiceTest extends TestCase
{
    private SynonymExpansionService $service;

    protected function setUp(): void
    {
        $this->service = new SynonymExpansionService();
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(SynonymExpansionService::class, $this->service);
    }

    public function testExpandReturnsArray(): void
    {
        $result = $this->service->expand('Baú Moto 80 Litros', 'MLB3530');
        
        $this->assertIsArray($result);
    }

    public function testGetHierarchy(): void
    {
        $result = $this->service->getHierarchy('MLB3530');
        
        $this->assertIsArray($result);
    }

    public function testIdentifyLevel(): void
    {
        $result = $this->service->identifyLevel('baú');
        
        $this->assertIsString($result);
        // Should return a level like 'generico', 'qualificado', etc.
    }

    public function testSelectForField(): void
    {
        $result = $this->service->selectForField('Baú Moto 80L', 'title', 'MLB3530');
        
        $this->assertIsArray($result);
    }

    public function testGenerateOptimizedModel(): void
    {
        $result = $this->service->generateOptimizedModel('Baú Moto 80L Pro Max', 'MLB3530');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('model', $result);
    }

    public function testListSynonyms(): void
    {
        $result = $this->service->listSynonyms('MLB3530');
        
        $this->assertIsArray($result);
    }

    public function testGenerateHierarchyForCategory(): void
    {
        $result = $this->service->generateHierarchyForCategory('MLB3530');
        
        $this->assertIsArray($result);
    }

    public function testEmptyTitleHandling(): void
    {
        $result = $this->service->expand('', 'MLB3530');
        
        $this->assertIsArray($result);
        // Should handle empty titles gracefully
    }

    public function testSpecialCharactersInTitle(): void
    {
        $result = $this->service->expand('Baú Moto 80L - Preto/Branco (Universal)', 'MLB3530');
        
        $this->assertIsArray($result);
        // Should handle special characters gracefully
    }
}
