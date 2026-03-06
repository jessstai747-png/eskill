<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI\Core;

use PHPUnit\Framework\TestCase;
use App\Services\AI\Core\PromptBuilder;

/**
 * @covers \App\Services\AI\Core\PromptBuilder
 */
class PromptBuilderTest extends TestCase
{
    private PromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new PromptBuilder();
    }

    public function testBuildTitleOptimizationPromptContainsCurrentTitle(): void
    {
        $prompt = $this->builder->buildTitleOptimizationPrompt('Bagageiro CG 160');

        $this->assertStringContainsString('Bagageiro CG 160', $prompt);
        $this->assertStringContainsString('TÍTULO ATUAL', $prompt);
    }

    public function testBuildTitleOptimizationPromptIncludesCategory(): void
    {
        $prompt = $this->builder->buildTitleOptimizationPrompt('Bagageiro', [
            'category' => 'Acessórios para Motos',
        ]);

        $this->assertStringContainsString('Acessórios para Motos', $prompt);
    }

    public function testBuildTitleOptimizationPromptIncludesBrandAndModel(): void
    {
        $prompt = $this->builder->buildTitleOptimizationPrompt('Bagageiro', [
            'brand' => 'AWA',
            'model' => 'CG 160',
        ]);

        $this->assertStringContainsString('AWA', $prompt);
        $this->assertStringContainsString('CG 160', $prompt);
    }

    public function testBuildTitleOptimizationPromptIncludesAttributes(): void
    {
        $prompt = $this->builder->buildTitleOptimizationPrompt('Bagageiro', [
            'attributes' => [
                ['name' => 'Material', 'value_name' => 'Aço'],
                ['name' => 'Cor', 'value_name' => 'Preto'],
            ],
        ]);

        $this->assertStringContainsString('Material: Aço', $prompt);
        $this->assertStringContainsString('Cor: Preto', $prompt);
    }

    public function testBuildTitleOptimizationPromptIncludesKeywords(): void
    {
        $prompt = $this->builder->buildTitleOptimizationPrompt('Bagageiro', [
            'keywords' => ['bagageiro', 'moto', 'cg160', 'titan'],
        ]);

        $this->assertStringContainsString('bagageiro', $prompt);
        $this->assertStringContainsString('cg160', $prompt);
    }

    public function testBuildTitleOptimizationPromptRequires60CharLimit(): void
    {
        $prompt = $this->builder->buildTitleOptimizationPrompt('Test');

        $this->assertStringContainsString('60 caracteres', $prompt);
    }

    public function testBuildTitleOptimizationPromptRequestsJsonFormat(): void
    {
        $prompt = $this->builder->buildTitleOptimizationPrompt('Test');

        $this->assertStringContainsString('optimized_title', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
    }

    public function testBuildDescriptionOptimizationPromptContainsProductTitle(): void
    {
        $prompt = $this->builder->buildDescriptionOptimizationPrompt([
            'title' => 'Bagageiro CG 160 Titan',
            'category' => 'Acessórios',
        ]);

        $this->assertStringContainsString('Bagageiro CG 160 Titan', $prompt);
    }

    public function testBuildDescriptionOptimizationPromptIncludesKeywords(): void
    {
        $prompt = $this->builder->buildDescriptionOptimizationPrompt(
            ['title' => 'Bagageiro'],
            ['bagageiro', 'moto', 'cg160']
        );

        $this->assertStringContainsString('bagageiro', $prompt);
        $this->assertStringContainsString('cg160', $prompt);
    }

    public function testBuildDescriptionOptimizationPromptRequestsJsonFormat(): void
    {
        $prompt = $this->builder->buildDescriptionOptimizationPrompt(['title' => 'Test']);

        $this->assertStringContainsString('description', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
    }

    public function testBuildDescriptionOptimizationPromptSetsCharLimits(): void
    {
        $prompt = $this->builder->buildDescriptionOptimizationPrompt(['title' => 'Test']);

        $this->assertStringContainsString('800', $prompt);
        $this->assertStringContainsString('4500', $prompt);
    }

    public function testBuildQualityAnalysisPromptContainsListingData(): void
    {
        $prompt = $this->builder->buildQualityAnalysisPrompt([
            'title' => 'Bagageiro CG 160',
            'description' => 'Descrição do produto',
            'attributes' => [['name' => 'Cor', 'value_name' => 'Preto']],
            'images' => ['img1.jpg', 'img2.jpg'],
        ]);

        $this->assertStringContainsString('Bagageiro CG 160', $prompt);
        $this->assertStringContainsString('Descrição do produto', $prompt);
        $this->assertStringContainsString('1', $prompt); // 1 attribute
        $this->assertStringContainsString('2', $prompt); // 2 images
    }

    public function testBuildQualityAnalysisPromptIncludesScoringCriteria(): void
    {
        $prompt = $this->builder->buildQualityAnalysisPrompt(['title' => 'Test']);

        $this->assertStringContainsString('25 pontos', $prompt);
        $this->assertStringContainsString('overall_score', $prompt);
    }

    public function testBuildSystemMessageReturnsOptimizer(): void
    {
        $message = $this->builder->buildSystemMessage('optimizer');

        $this->assertStringContainsString('otimização', $message);
        $this->assertNotEmpty($message);
    }

    public function testBuildSystemMessageReturnsAnalyzer(): void
    {
        $message = $this->builder->buildSystemMessage('analyzer');

        $this->assertStringContainsString('analista', $message);
    }

    public function testBuildSystemMessageReturnsCopywriter(): void
    {
        $message = $this->builder->buildSystemMessage('copywriter');

        $this->assertStringContainsString('copywriter', $message);
    }

    public function testBuildSystemMessageDefaultsToOptimizer(): void
    {
        $result1 = $this->builder->buildSystemMessage('unknown_role');
        $result2 = $this->builder->buildSystemMessage('optimizer');

        $this->assertSame($result1, $result2);
    }

    public function testBuildTitleOptimizationPromptWithEmptyContext(): void
    {
        $prompt = $this->builder->buildTitleOptimizationPrompt('Test', []);

        $this->assertStringContainsString('Test', $prompt);
        $this->assertStringContainsString('produto', $prompt); // default category
    }

    public function testBuildDescriptionOptimizationPromptWithFeatures(): void
    {
        $prompt = $this->builder->buildDescriptionOptimizationPrompt([
            'title' => 'Bagageiro',
            'features' => ['Reforçado', 'Pintura eletrostática', 'Resistente'],
        ]);

        $this->assertStringContainsString('Reforçado', $prompt);
        $this->assertStringContainsString('Resistente', $prompt);
    }
}
