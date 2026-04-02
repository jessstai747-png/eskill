<?php

declare(strict_types=1);

namespace Tests\Unit\Views;

use PHPUnit\Framework\TestCase;

class ClonarAnunciosViewTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $path = dirname(__DIR__, 3) . '/app/Views/dashboard/clonar-anuncios.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->source = $contents;
    }

    public function testSearchFormUsesSemanticAccessibleMarkup(): void
    {
        $this->assertStringContainsString('<form id="cloneSearchForm" class="row g-2 align-items-end" role="search" novalidate>', $this->source);
        $this->assertStringContainsString('label class="form-label fw-semibold small text-muted" for="searchType"', $this->source);
        $this->assertStringContainsString('label class="form-label fw-semibold small text-muted" for="searchQuery"', $this->source);
        $this->assertStringContainsString('type="search" class="form-control" id="searchQuery" name="q"', $this->source);
        $this->assertStringContainsString('id="searchQueryFeedback"', $this->source);
        $this->assertStringContainsString('enterkeyhint="search"', $this->source);
    }

    public function testSearchScriptUsesThemeLoadingAndCancelsPreviousRequests(): void
    {
        $this->assertStringContainsString("btnSearch.classList.toggle('loading', isBusy);", $this->source);
        $this->assertStringContainsString('activeSearchController = new AbortController();', $this->source);
        $this->assertStringContainsString('signal: activeSearchController.signal', $this->source);
        $this->assertStringContainsString("function parseJsonResponse(response, fallbackMessage = 'Erro ao processar a resposta do servidor.')", $this->source);
        $this->assertStringNotContainsString("btn.innerHTML = '<span class=\"spinner-border spinner-border-sm me-1\"></span> Buscando...';", $this->source);
    }

    public function testSearchResultsUseSafeAttributesInsteadOfInlineJavascript(): void
    {
        $this->assertStringContainsString('function escAttr(value)', $this->source);
        $this->assertStringContainsString('class="btn btn-sm btn-outline-primary clone-result-btn"', $this->source);
        $this->assertStringContainsString("window.openCloneModal(button.dataset.itemId || '', button.dataset.itemTitle || '');", $this->source);
        $this->assertStringNotContainsString('onclick="openCloneModal(', $this->source);
    }

    public function testCloneRequestUsesExpectedPayloadAndThemeLoadingState(): void
    {
        $this->assertStringContainsString('id="cloneModalValidation"', $this->source);
        $this->assertStringContainsString('id="btnConfirmClone" disabled', $this->source);
        $this->assertStringContainsString('function runClonePrecheck()', $this->source);
        $this->assertStringContainsString("fetch('/api/catalog/clone/validate', {", $this->source);
        $this->assertStringContainsString("target_account_id: targetAccountId,", $this->source);
        $this->assertStringContainsString("item_ids: [pendingItemId]", $this->source);
        $this->assertStringContainsString("renderClonePrecheck('success', messages);", $this->source);
        $this->assertStringContainsString("renderClonePrecheck('danger', errors.length ? errors.map((error) => escHtml(error)) : ['Não foi possível validar a clonagem.']);", $this->source);
        $this->assertStringContainsString("btn.classList.add('loading');", $this->source);
        $this->assertStringContainsString("source_item_id: pendingItemId,", $this->source);
        $this->assertStringContainsString("btn.classList.remove('loading');", $this->source);
        $this->assertStringContainsString("parseJsonResponse(response, 'Não foi possível interpretar a resposta da clonagem.')", $this->source);
        $this->assertStringContainsString("bootstrap.Modal.getOrCreateInstance(document.getElementById('cloneModal')).hide();", $this->source);
        $this->assertStringContainsString("console.error('Erro ao iniciar clonagem de anúncio.', error);", $this->source);
    }
}
