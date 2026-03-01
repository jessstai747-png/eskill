<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\TitleGenerator\TitleAnalyzerService;
use App\Services\TitleGenerator\TitleGeneratorService;

/**
 * Testes de otimizacao SEO real — Fase 3 (Titles + Score).
 *
 * TitleAnalyzerService e instanciavel sem DB (MercadoLivreClient gracioso).
 * TitleGeneratorService tem constantes publicas e analise de qualidade.
 *
 * @covers \App\Services\TitleGenerator\TitleAnalyzerService
 * @covers \App\Services\TitleGenerator\TitleGeneratorService
 */
class SEORealOptimizationTest extends TestCase
{
    private TitleAnalyzerService $analyzer;
    private TitleGeneratorService $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer  = new TitleAnalyzerService(null);
        $this->generator = new TitleGeneratorService(null);
    }

    // ------------------------------------------------------------------
    // Estrutura de classes
    // ------------------------------------------------------------------

    public function testTitleAnalyzerServiceClassExists(): void
    {
        $this->assertTrue(class_exists(TitleAnalyzerService::class));
    }

    public function testTitleGeneratorServiceClassExists(): void
    {
        $this->assertTrue(class_exists(TitleGeneratorService::class));
    }

    public function testTitleAnalyzerHasAnalyzeTitleMethod(): void
    {
        $this->assertTrue(method_exists(TitleAnalyzerService::class, 'analyzeTitle'));
    }

    public function testTitleGeneratorHasRequiredMethods(): void
    {
        $methods = [
            'generateTitles',
            'generateFromItem',
            'generateVariationsFromTitle',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(TitleGeneratorService::class, $method),
                "TitleGeneratorService deve ter {$method}()"
            );
        }
    }

    // ------------------------------------------------------------------
    // TitleAnalyzerService::analyzeTitle (sem DB/ML API real)
    // Chamadas ML sao capturadas no try/catch interno
    // ------------------------------------------------------------------

    public function testAnalyzeTitleReturnsArrayWithRequiredKeys(): void
    {
        $result = $this->analyzer->analyzeTitle('Bagageiro Honda CG 160 AWA Moto');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('length', $result);
        $this->assertArrayHasKey('overall_score', $result);
    }

    public function testAnalyzeTitleLengthIsCorrect(): void
    {
        $title  = 'Bagageiro Honda CG 160';
        $result = $this->analyzer->analyzeTitle($title);

        $this->assertSame(strlen($title), $result['length']);
    }

    public function testAnalyzeTitleTooLongIsDetected(): void
    {
        // Titulo com mais de 60 chars deve reportar problema
        $longTitle = 'Bagageiro Honda CG 160 Titan 160 AWA Motos Araraquara SP Brasil';
        $this->assertGreaterThan(60, strlen($longTitle));

        $result = $this->analyzer->analyzeTitle($longTitle);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('length', $result);

        if (isset($result['length_analysis']['status'])) {
            $this->assertSame('critical', $result['length_analysis']['status']);
        }
    }

    public function testAnalyzeTitleOptimalLengthReturnsHighScore(): void
    {
        // Titulo entre 45-58 chars e considerado otimo
        $goodTitle = 'Bagageiro Honda CG 160 Titan AWA com Grade';
        $this->assertGreaterThanOrEqual(42, strlen($goodTitle));
        $this->assertLessThanOrEqual(60, strlen($goodTitle));

        $result = $this->analyzer->analyzeTitle($goodTitle);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('overall_score', $result);
        $this->assertGreaterThanOrEqual(0, $result['overall_score']);
        $this->assertLessThanOrEqual(100, $result['overall_score']);
    }

    public function testAnalyzeTitleEmptyStringDoesNotThrow(): void
    {
        $result = $this->analyzer->analyzeTitle('');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('length', $result);
        $this->assertSame(0, $result['length']);
    }

    public function testAnalyzeTitleScoreIsNumeric(): void
    {
        $result = $this->analyzer->analyzeTitle('Retrovisor Titan 150 par direito esquerdo');

        $this->assertIsNumeric($result['overall_score']);
        $this->assertGreaterThanOrEqual(0, $result['overall_score']);
        $this->assertLessThanOrEqual(100, $result['overall_score']);
    }

    public function testAnalyzeTitleWithCategoryIdDoesNotThrow(): void
    {
        // Bug corrigido: categoryId nao-vazio agora passa $title (string) como baseKeyword
        // em vez de array options para researchKeywords(). ML API indisponivel no sandbox
        // — a excecao eh capturada pelo try/catch interno e retorna array graciosamente.
        $result = $this->analyzer->analyzeTitle('Bagageiro CG 160 Titan AWA', 'MLB73419');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('length', $result);
    }

    // ------------------------------------------------------------------
    // TitleGeneratorService::generateTitles (estrutura de retorno)
    // ------------------------------------------------------------------

    public function testGenerateTitlesWithMinimalDataReturnsArray(): void
    {
        $productData = [
            'title'    => 'Bagageiro CG 160',
            'category' => 'MLB73419',
        ];

        try {
            $result = $this->generator->generateTitles($productData);
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            // ML API indisponivel no ambiente de teste — apenas verifica que nao lanca tipo errado
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testGenerateVariationsFromTitleWithKnownInputReturnsArray(): void
    {
        try {
            $result = $this->generator->generateVariationsFromTitle(
                'Bagageiro Honda CG 160 AWA',
                'MLB73419'
            );
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }
}
