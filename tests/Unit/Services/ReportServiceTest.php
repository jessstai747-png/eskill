<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ReportService;
use ReflectionClass;
use Tests\TestCase;

/**
 * @covers \App\Services\ReportService
 */
class ReportServiceTest extends TestCase
{
    private ReportService $service;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReportService();
        $this->reflection = new ReflectionClass(ReportService::class);
    }

    public function testGenerateSalesReportMethodExists(): void
    {
        $this->assertTrue($this->reflection->hasMethod('generateSalesReport'));
        $this->assertTrue($this->reflection->getMethod('generateSalesReport')->isPublic());
    }

    public function testGenerateCsvExportMethodExists(): void
    {
        $this->assertTrue($this->reflection->hasMethod('generateCsvExport'));
        $this->assertTrue($this->reflection->getMethod('generateCsvExport')->isPublic());
    }

    public function testRenderHtmlBuildsSalesTemplate(): void
    {
        $method = $this->reflection->getMethod('renderHtml');
        $method->setAccessible(true);

        $html = $method->invoke($this->service, 'sales_report', [
            'type' => 'sales',
            'period' => '01/01/2026 a 31/01/2026',
            'total_sales' => 1000.0,
            'orders_count' => 20,
            'avg_ticket' => 50.0,
            'net_profit' => 400.0,
            'top_products' => [
                ['name' => 'Produto A', 'qty' => 2, 'total' => 100.0],
            ],
        ]);

        $this->assertStringContainsString('Relatório de Vendas', $html);
        $this->assertStringContainsString('Produto A', $html);
        $this->assertStringContainsString('Receita Total', $html);
    }

    public function testRenderHtmlBuildsInventoryTemplate(): void
    {
        $method = $this->reflection->getMethod('renderHtml');
        $method->setAccessible(true);

        $html = $method->invoke($this->service, 'inventory_report', [
            'type' => 'inventory',
            'total_items' => 10,
            'total_sell_value' => 1000.0,
            'items' => [
                ['title' => 'Item 1', 'available_quantity' => 2, 'price' => 10.0],
            ],
        ]);

        $this->assertStringContainsString('Relatório de Valorização de Estoque', $html);
        $this->assertStringContainsString('Item 1', $html);
    }

    public function testRenderHtmlBuildsCustomerTemplate(): void
    {
        $method = $this->reflection->getMethod('renderHtml');
        $method->setAccessible(true);

        $html = $method->invoke($this->service, 'customer_report', [
            'type' => 'customer',
            'top_customers' => [
                ['name' => 'Cliente X', 'revenue' => 200.0, 'orders' => 3, 'state' => 'SP'],
            ],
        ]);

        $this->assertStringContainsString('Relatório de Melhores Clientes', $html);
        $this->assertStringContainsString('Cliente X', $html);
    }
}
