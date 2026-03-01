<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\ExportService;

/**
 * Testes de exportacao CSV — Fase 6 (Relatorios).
 *
 * Os metodos de streaming (exportUserDataToCSV, exportAnalysisToCSV, etc.)
 * utilizam header()/exit() e nao sao testados via PHPUnit diretamente.
 * Testamos estrutura de classe e o unico metodo que retorna valor:
 * exportReportToPDF() -> string HTML.
 *
 * @covers \App\Services\ExportService
 */
class CsvExportTest extends TestCase
{
    private ExportService $export;

    protected function setUp(): void
    {
        parent::setUp();
        $this->export = new ExportService();
    }

    // ------------------------------------------------------------------
    // Estrutura de classe
    // ------------------------------------------------------------------

    public function testExportServiceClassExists(): void
    {
        $this->assertTrue(class_exists(ExportService::class));
    }

    public function testExportServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ExportService::class, $this->export);
    }

    /** @dataProvider csvMethodsProvider */
    public function testExportServiceHasCsvMethods(string $method): void
    {
        $this->assertTrue(
            method_exists(ExportService::class, $method),
            "ExportService deve ter {$method}()"
        );
    }

    /** @return array<string, array{string}> */
    public static function csvMethodsProvider(): array
    {
        return [
            'exportUserDataToCSV'   => ['exportUserDataToCSV'],
            'exportUserDataToJSON'  => ['exportUserDataToJSON'],
            'exportAnalysisToCSV'   => ['exportAnalysisToCSV'],
            'exportToJSON'          => ['exportToJSON'],
            'exportReportToPDF'     => ['exportReportToPDF'],
            'generatePDF'           => ['generatePDF'],
        ];
    }

    // ------------------------------------------------------------------
    // exportReportToPDF retorna HTML (unico metodo sem header/exit)
    // ------------------------------------------------------------------

    public function testExportReportToPdfDefaultTitleIsPresent(): void
    {
        $html = $this->export->exportReportToPDF([]);

        $this->assertIsString($html);
        $this->assertStringContainsString('<html', $html);
        // Titulo padrao do metodo e 'Relatorio'
        $this->assertStringContainsString('Relat', $html);
    }

    public function testExportReportToPdfCustomTitle(): void
    {
        $html = $this->export->exportReportToPDF([], 'Exportacao CSV AWA');

        $this->assertStringContainsString('Exportacao CSV AWA', $html);
    }

    public function testExportReportToPdfWithSummaryData(): void
    {
        $data = [
            'summary' => [
                'Total Itens'      => 120,
                'Itens Otimizados' => 95,
                'Score Medio SEO'  => '78%',
            ],
        ];

        $html = $this->export->exportReportToPDF($data, 'Analise SEO');

        $this->assertStringContainsString('Total Itens', $html);
        $this->assertStringContainsString('120', $html);
        $this->assertStringContainsString('Analise SEO', $html);
    }

    public function testExportReportToPdfWithNestedData(): void
    {
        $data = [
            'summary' => ['Registros' => 5],
            'items'   => [
                ['title' => 'Bagageiro CG 160', 'score' => 88],
                ['title' => 'Retrovisor Titan',  'score' => 72],
            ],
        ];

        $html = $this->export->exportReportToPDF($data, 'Exportacao de Anuncios');

        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    public function testExportReportToPdfReturnTypeIsString(): void
    {
        $ref    = new \ReflectionMethod(ExportService::class, 'exportReportToPDF');
        $return = $ref->getReturnType();

        $this->assertNotNull($return, 'exportReportToPDF deve ter tipo de retorno declarado');
        $this->assertSame('string', (string) $return);
    }
}
