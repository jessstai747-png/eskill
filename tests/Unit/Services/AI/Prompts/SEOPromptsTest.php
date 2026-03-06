<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI\Prompts;

use PHPUnit\Framework\TestCase;
use App\Services\AI\Prompts\SEOPrompts;

/**
 * @covers \App\Services\AI\Prompts\SEOPrompts
 */
class SEOPromptsTest extends TestCase
{
    public function testSystemOptimizationConstantIsNotEmpty(): void
    {
        $this->assertNotEmpty(SEOPrompts::SYSTEM_OPTIMIZATION);
        $this->assertStringContainsString('SEO', SEOPrompts::SYSTEM_OPTIMIZATION);
    }

    public function testAnalyzeTitleIncludesTitle(): void
    {
        $prompt = SEOPrompts::analyzeTitle([
            'title' => 'Bagageiro CG 160 Titan',
            'category' => 'Acessorios',
        ]);

        $this->assertStringContainsString('Bagageiro CG 160 Titan', $prompt);
        $this->assertStringContainsString('Acessorios', $prompt);
    }

    public function testAnalyzeTitleIncludesForbiddenWords(): void
    {
        $prompt = SEOPrompts::analyzeTitle([
            'title' => 'Test',
            'category' => 'Test',
            'forbidden_words' => ['gratis', 'oferta', 'promocao'],
        ]);

        $this->assertStringContainsString('gratis', $prompt);
        $this->assertStringContainsString('oferta', $prompt);
        $this->assertStringContainsString('promocao', $prompt);
    }

    public function testAnalyzeTitleRequestsJsonFormat(): void
    {
        $prompt = SEOPrompts::analyzeTitle([
            'title' => 'Test',
            'category' => 'Test',
        ]);

        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringContainsString('score', $prompt);
        $this->assertStringContainsString('issues', $prompt);
    }

    public function testAnalyzeDescriptionIncludesDescription(): void
    {
        $prompt = SEOPrompts::analyzeDescription([
            'description' => 'Bagageiro de alta qualidade para CG 160',
        ]);

        $this->assertStringContainsString('Bagageiro de alta qualidade', $prompt);
    }

    public function testAnalyzeDescriptionRequestsJsonFormat(): void
    {
        $prompt = SEOPrompts::analyzeDescription([
            'description' => 'Test desc',
        ]);

        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringContainsString('score', $prompt);
        $this->assertStringContainsString('keyword_density', $prompt);
    }

    public function testAnalyzeKeywordsIncludesTitleAndDescription(): void
    {
        $prompt = SEOPrompts::analyzeKeywords([
            'title' => 'Bagageiro CG 160',
            'description' => 'Produto reforçado',
            'category' => 'Acessorios',
        ]);

        $this->assertStringContainsString('Bagageiro CG 160', $prompt);
        $this->assertStringContainsString('Produto reforçado', $prompt);
        $this->assertStringContainsString('Acessorios', $prompt);
    }

    public function testAnalyzeKeywordsIncludesCategoryKeywords(): void
    {
        $prompt = SEOPrompts::analyzeKeywords([
            'title' => 'Test',
            'description' => 'Test',
            'category' => 'Test',
            'category_keywords' => ['bagageiro', 'moto', 'cg160'],
        ]);

        $this->assertStringContainsString('bagageiro', $prompt);
        $this->assertStringContainsString('cg160', $prompt);
    }

    public function testAnalyzeKeywordsRequestsJsonFormat(): void
    {
        $prompt = SEOPrompts::analyzeKeywords([
            'title' => 'Test',
            'description' => 'Test',
            'category' => 'Test',
        ]);

        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringContainsString('primary_keywords', $prompt);
        $this->assertStringContainsString('long_tail_keywords', $prompt);
    }

    public function testAnalyzeTitleHandlesEmptyForbiddenWords(): void
    {
        $prompt = SEOPrompts::analyzeTitle([
            'title' => 'Test',
            'category' => 'Test',
        ]);

        $this->assertIsString($prompt);
        $this->assertNotEmpty($prompt);
    }

    public function testAnalyzeKeywordsHandlesEmptyCategoryKeywords(): void
    {
        $prompt = SEOPrompts::analyzeKeywords([
            'title' => 'Test',
            'description' => 'Test',
            'category' => 'Test',
        ]);

        $this->assertIsString($prompt);
        $this->assertNotEmpty($prompt);
    }
}
