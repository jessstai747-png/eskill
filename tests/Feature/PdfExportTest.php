<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\PdfService;
use App\Services\ExportService;

/**
 * Testes de exportacao PDF — Fase 6 (Relatorios).
 *
 * PdfService usa DomPDF; sem dependencia de DB ou ML API.
 * ExportService::exportReportToPDF() retorna HTML (sem header/exit).
 *
 * @covers \App\Services\PdfService
 * @covers \App\Services\ExportService
 */
class PdfExportTest extends TestCase
{
    private PdfService $pdf;
    private ExportService $export;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdf    = new PdfService();
        $this->export = new ExportService();
    }

    // ------------------------------------------------------------------
    // Estrutura de classes
    // ------------------------------------------------------------------

    public function testPdfServiceClassExists(): void
    {
        $this->assertTrue(class_exists(PdfService::class));
    }

    public function testExportServiceClassExists(): void
    {
        $this->assertTrue(class_exists(ExportService::class));
    }

    public function testPdfServiceHasRequiredMethods(): void
    {
        $methods = [
            'generateSalesReport',
            'generateMarketAnalysis',
            'generateOrdersReport',
            'generateExecutiveDashboard',
            'generateListingAnalysis',
            'getPdfOutput',
            'getPdfContent',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(PdfService::class, $method),
                "PdfService deve ter {$method}()"
            );
        }
    }

    public function testExportServiceHasRequiredMethods(): void
    {
        $methods = [
            'exportUserDataToJSON',
            'exportUserDataToCSV',
            'exportAnalysisToCSV',
            'exportToJSON',
            'exportReportToPDF',
            'generatePDF',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(ExportService::class, $method),
                "ExportService deve ter {$method}()"
            );
        }
    }

    // ------------------------------------------------------------------
    // Geracao de PDF real (DomPDF, sem DB)
    // ------------------------------------------------------------------

    public function testGenerateSalesReportReturnsPdfBinary(): void
    {
        $data = [
            'total_sales'     => 12500.00,
            'total_orders'    => 87,
            'average_ticket'  => 143.68,
            'conversion_rate' => 3.4,
            'sales_by_period' => [
                ['period' => 'Jan/2026', 'orders' => 42, 'value' => 6200.00],
                ['period' => 'Fev/2026', 'orders' => 45, 'value' => 6300.00],
            ],
            'top_products' => [
                ['title' => 'Bagageiro CG 160',     'quantity' => 22, 'revenue' => 3080.00],
                ['title' => 'Retrovisor Titan 150', 'quantity' => 15, 'revenue' => 1350.00],
            ],
        ];

        $result = $this->pdf->generateSalesReport($data, 'month');

        $this->assertIsString($result, 'generateSalesReport deve retornar string');
        $this->assertNotEmpty($result, 'PDF nao pode ser vazio');
        $this->assertStringStartsWith('%PDF', $result, 'Conteudo deve ser PDF valido');
    }

    public function testGenerateSalesReportWithEmptyDataDoesNotCrash(): void
    {
        $result = $this->pdf->generateSalesReport([]);

        $this->assertIsString($result);
        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testGenerateOrdersReportReturnsPdfBinary(): void
    {
        $orders = [[
            'id'       => 'ORDER-001',
            'date'     => '2026-02-15',
            'buyer'    => 'Joao Silva',
            'items'    => [['title' => 'Bagageiro CG 160', 'quantity' => 1, 'unit_price' => 140.00]],
            'total'    => 140.00,
            'status'   => 'paid',
            'shipping' => 'Mercado Envios',
        ]];

        $summary = ['total_revenue' => 140.00, 'total_orders' => 1, 'avg_ticket' => 140.00];
        $result  = $this->pdf->generateOrdersReport($orders, $summary);

        $this->assertIsString($result);
        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testGenerateListingAnalysisReturnsPdfBinary(): void
    {
        $listing = [
            'item_id'     => 'MLB123456',
            'title'       => 'Bagageiro Honda CG 160 Titan AWA',
            'category'    => 'Bagageiros e Fixadores',
            'price'       => 139.90,
            'visits'      => 1240,
            'conversions' => 18,
            'condition'   => 'new',
            'attributes'  => [['name' => 'Marca', 'value' => 'AWA']],
        ];

        $seoScore = ['overall' => 82, 'title_score' => 90, 'keyword_density' => 4.2];
        $result   = $this->pdf->generateListingAnalysis($listing, $seoScore);

        $this->assertIsString($result);
        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testGetPdfContentFromHtml(): void
    {
        $html   = '<html><body><h1>AWA Motos</h1><p>Relatorio de vendas.</p></body></html>';
        $result = $this->pdf->getPdfContent($html);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertStringStartsWith('%PDF', $result);
    }

    // ------------------------------------------------------------------
    // ExportService::exportReportToPDF (retorna HTML, testavel)
    // ------------------------------------------------------------------

    public function testExportReportToPdfReturnsHtmlString(): void
    {
        $data = [
            'summary' => [
                'Total de Pedidos' => 87,
                'Receita Total'    => 'R$ 12.500,00',
                'Ticket Medio'     => 'R$ 143,68',
            ],
        ];

        $html = $this->export->exportReportToPDF($data, 'Relatorio Mensal');

        $this->assertIsString($html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('Relatorio Mensal', $html);
    }

    public function testExportReportToPdfIncludesSummaryKeys(): void
    {
        $data = ['summary' => ['Vendas' => 50, 'Cancelamentos' => 3]];
        $html = $this->export->exportReportToPDF($data);

        $this->assertStringContainsString('Vendas', $html);
        $this->assertStringContainsString('Cancelamentos', $html);
    }

    public function testExportReportToPdfHandlesEmptyData(): void
    {
        $html = $this->export->exportReportToPDF([]);

        $this->assertIsString($html);
        $this->assertStringContainsString('<html', $html);
    }
}
