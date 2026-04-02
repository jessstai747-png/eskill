<?php

declare(strict_types=1);

namespace Tests\Unit\Views;

use PHPUnit\Framework\TestCase;

class CloneOperationsViewTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $path = dirname(__DIR__, 3) . '/app/Views/dashboard/clone_operations.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->source = $contents;
    }

    public function testExportAreaUsesAccessibleStatusRegion(): void
    {
        $this->assertStringContainsString('id="exportFeedback"', $this->source);
        $this->assertStringContainsString('role="status"', $this->source);
        $this->assertStringContainsString('aria-live="polite"', $this->source);
        $this->assertStringContainsString('function renderExportFeedback(type, message, actions = [])', $this->source);
    }

    public function testExportFlowUsesDirectDownloadUrlAndNoBlockingAlerts(): void
    {
        $this->assertStringContainsString('window.location.href = result.download_url || `/api/clone/export/download/${result.file}`;', $this->source);
        $this->assertStringContainsString('renderExportFeedback(\'success\', `Export criado: ${result.filename || result.file}`', $this->source);
        $this->assertStringNotContainsString("alert('Export criado: ' + (result.file || 'Verifique a lista de exports'));", $this->source);
        $this->assertStringNotContainsString("alert('Erro ao exportar: ' + error.message);", $this->source);
    }

    public function testExportHistoryUsesModalTableInsteadOfConsoleLogging(): void
    {
        $this->assertStringContainsString('id="exportHistoryModal"', $this->source);
        $this->assertStringContainsString('id="exportHistoryBody"', $this->source);
        $this->assertStringContainsString("bootstrap.Modal.getOrCreateInstance(document.getElementById('exportHistoryModal')).show();", $this->source);
        $this->assertStringContainsString('Baixar</a>', $this->source);
        $this->assertStringNotContainsString("console.log('Exports:', data.exports);", $this->source);
        $this->assertStringNotContainsString("alert('Ver console para lista de exports');", $this->source);
    }

    public function testHealthCardUsesMonitoringCompatibilityEndpoint(): void
    {
        $this->assertStringContainsString("requestJson('/api/catalog/clone/monitoring/health')", $this->source);
        $this->assertStringContainsString('function formatHealthStatus(status, legacyStatus = \'\')', $this->source);
        $this->assertStringContainsString('Degradado (warning)', $this->source);
    }

    public function testHealthAndOperationsSectionsRenderInlineErrors(): void
    {
        $this->assertStringContainsString('document.getElementById(\'healthChecks\').innerHTML = `<p class="text-danger mb-0">${escapeHtml(error.message)}</p>`;', $this->source);
        $this->assertStringContainsString("document.getElementById('operationsHistory').innerHTML =", $this->source);
        $this->assertStringNotContainsString("console.error('Error loading health:', error);", $this->source);
        $this->assertStringNotContainsString("console.error('Error loading history:', error);", $this->source);
    }
}
