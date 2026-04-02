<?php

declare(strict_types=1);

namespace Tests\Unit\Views;

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class AwaSellersViewTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $path = dirname(__DIR__, 3) . '/app/Views/dashboard/awa-sellers/index.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->source = $contents;
    }

    public function testDeveRenderizarEstruturaOperacionalQuandoViewCarregada(): void
    {
        $this->assertStringContainsString("include __DIR__ . '/../../layouts/modern/partials/page-header.php';", $this->source);
        $this->assertStringContainsString('<main class="container-fluid px-0 awa-sellers-page">', $this->source);
        $this->assertStringContainsString('<form id="awaSellerFiltersForm" class="row g-3 align-items-end" role="search" novalidate>', $this->source);
        $this->assertStringContainsString('for="filterCity">Cidade</label>', $this->source);
        $this->assertStringContainsString('id="filterCity" name="city"', $this->source);
        $this->assertStringContainsString('for="filterCategory">Categoria</label>', $this->source);
        $this->assertStringContainsString('id="filterCategory" name="category_id"', $this->source);
        $this->assertStringContainsString('id="metricTotalSellers"', $this->source);
        $this->assertStringContainsString('id="awaSellerFeedback" class="alert d-none mt-3 mb-0" role="status" aria-live="polite"', $this->source);
        $this->assertStringContainsString('id="awaSellerTableWrapper"', $this->source);
        $this->assertStringContainsString('id="awaSellerDetailOffcanvas"', $this->source);
        $this->assertStringContainsString('id="awaSellerIdentificationForm"', $this->source);
        $this->assertStringContainsString('id="identificationAuditCount"', $this->source);
        $this->assertStringContainsString('id="identificationAuditList"', $this->source);
        $this->assertStringContainsString('id="identificationSummaryVerified"', $this->source);
        $this->assertStringContainsString('id="awaNewSellersList"', $this->source);
        $this->assertStringContainsString('id="awaRecentScansList"', $this->source);
        $this->assertStringContainsString('id="awaAlertsList"', $this->source);
        $this->assertStringContainsString('id="awaAlertsUnreadBadge"', $this->source);
        $this->assertStringContainsString('id="verifyAwaSellerIdentification"', $this->source);
    }

    public function testDeveUsarAtributosSegurosQuandoRenderizaAcoesDinamicas(): void
    {
        $this->assertStringContainsString('function escAttr(value)', $this->source);
        $this->assertStringContainsString('class="btn btn-sm btn-primary js-open-detail"', $this->source);
        $this->assertStringContainsString('data-seller-id="${escAttr(seller.id)}"', $this->source);
        $this->assertStringContainsString('data-seller-name="${escAttr(seller.nickname || \'\')}"', $this->source);
        $this->assertStringNotContainsString('onclick="', $this->source);
    }

    public function testDeveConsumirEndpointsPersistidosQuandoDashboardInicializa(): void
    {
        $this->assertStringContainsString("metrics: '/api/brand/awa/sellers/metrics'", $this->source);
        $this->assertStringContainsString("filters: '/api/brand/awa/sellers/filters/options'", $this->source);
        $this->assertStringContainsString("sellers: '/api/brand/awa/sellers'", $this->source);
        $this->assertStringContainsString("scan: '/api/brand/awa/sellers/scan'", $this->source);
        $this->assertStringContainsString("exportCsv: '/api/brand/awa/sellers/export/csv'", $this->source);
        $this->assertStringContainsString("identificationSummary: '/api/brand/awa/sellers/identification/summary'", $this->source);
        $this->assertStringContainsString("history: '/api/brand/awa/sellers/history'", $this->source);
        $this->assertStringContainsString("alerts: '/api/brand/awa/sellers/alerts'", $this->source);
        $this->assertStringContainsString('city: document.getElementById(\'filterCity\')', $this->source);
        $this->assertStringContainsString('category: document.getElementById(\'filterCategory\')', $this->source);
        $this->assertStringContainsString('renderSelectOptions(elements.city, data.cities || [], \'Todas\', previousValues.city);', $this->source);
        $this->assertStringContainsString('renderSelectOptions(elements.category, data.categories || [], \'Todas\', previousValues.category);', $this->source);
        $this->assertStringContainsString('await requestJson(endpoints.identificationSummary);', $this->source);
        $this->assertStringContainsString('await requestJson(`${endpoints.history}?days=${days}`);', $this->source);
        $this->assertStringContainsString('await requestJson(`${endpoints.alerts}?limit=${limit}`);', $this->source);
        $this->assertStringContainsString('function buildIdentificationHistoryUrl(sellerId, limit = 10)', $this->source);
        $this->assertStringContainsString('await requestJson(buildIdentificationHistoryUrl(sellerId, limit));', $this->source);
        $this->assertStringContainsString('renderIdentificationAudit(auditHistory);', $this->source);
        $this->assertStringContainsString('await requestJson(`${endpoints.sellers}/${state.currentSellerId}/identification/verify`, {', $this->source);
        $this->assertStringContainsString('await requestJson(endpoints.metrics);', $this->source);
        $this->assertStringContainsString('await requestJson(`${endpoints.sellers}?${query}`);', $this->source);
        $this->assertStringContainsString('window.location.href = `${endpoints.exportCsv}?${buildQuery(getCurrentFilters())}`;', $this->source);
        $this->assertStringContainsString('requestJson(`${endpoints.sellers}/${state.currentSellerId}/identification`, {', $this->source);
        $this->assertStringContainsString('reloadIdentificationAudit(state.currentSellerId),', $this->source);
        $this->assertStringContainsString('loadAlerts(),', $this->source);
        $this->assertStringContainsString('refreshDashboard().catch((error) => {', $this->source);
    }
}
