<?php

declare(strict_types=1);

$pageTitleSafe = htmlspecialchars((string) ($pageTitle ?? 'AWA Sellers'), ENT_QUOTES, 'UTF-8');
$pageSubtitleSafe = htmlspecialchars(
    (string) ($pageSubtitle ?? 'Monitore sellers AWA com base persistida e identificação jurídica rastreável.'),
    ENT_QUOTES,
    'UTF-8'
);

$title = $pageTitleSafe;
$subtitle = $pageSubtitleSafe;
$breadcrumbs = [
    ['label' => 'Marketing', 'url' => '/dashboard/brand-analysis'],
    ['label' => 'AWA Sellers', 'url' => ''],
];
$actions = <<<HTML
<button type="button" class="btn btn-outline-secondary" id="refreshAwaSellers">
    <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
</button>
<button type="button" class="btn btn-outline-primary" id="exportAwaSellersCsv">
    <i class="bi bi-download me-1"></i> Exportar CSV
</button>
<button type="button" class="btn btn-primary" id="runAwaSellerScan">
    <i class="bi bi-broadcast-pin me-1"></i> Executar Scan
</button>
HTML;

include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<main class="container-fluid px-0 awa-sellers-page">
    <section class="row g-3 mb-4" aria-label="Indicadores principais do módulo AWA Sellers">
        <div class="col-6 col-xl-3">
            <article class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Lojas detectadas</p>
                    <div class="d-flex align-items-end justify-content-between gap-2">
                        <h2 class="fw-bold mb-0" id="metricTotalSellers">--</h2>
                        <span class="badge text-bg-light" id="metricActiveSellers">Ativas: --</span>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-6 col-xl-3">
            <article class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Anúncios rastreados</p>
                    <h2 class="fw-bold mb-0" id="metricTotalItems">--</h2>
                    <small class="text-muted" id="metricItemsWithBrandAttribute">Com atributo BRAND: --</small>
                </div>
            </article>
        </div>
        <div class="col-12 col-xl-3">
            <article class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Último scan</p>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge text-bg-secondary" id="metricLastScanStatus">Sem histórico</span>
                        <strong id="metricLastScanId">#--</strong>
                    </div>
                    <small class="text-muted d-block" id="metricLastScanDate">Aguardando primeira varredura persistida</small>
                </div>
            </article>
        </div>
        <div class="col-12 col-xl-3">
            <article class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Cobertura por tipo de match</p>
                    <div class="d-flex flex-wrap gap-2 small" id="metricMatchTypes">
                        <span class="badge rounded-pill text-bg-light">attribute_match: --</span>
                        <span class="badge rounded-pill text-bg-light">title_match_only: --</span>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <section class="row g-3 mb-4" aria-label="Resumo jurídico e histórico recente">
        <div class="col-12 col-xl-3">
            <article class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-1">Identificação jurídica</h5>
                            <small class="text-muted">Cobertura atual da base local por status.</small>
                        </div>
                        <span class="badge text-bg-light" id="identificationSummaryUnidentified">Sem ID: --</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="border rounded-3 p-3 bg-light-subtle h-100">
                                <div class="small text-muted">Verificados</div>
                                <strong class="fs-4" id="identificationSummaryVerified">--</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-3 p-3 bg-light-subtle h-100">
                                <div class="small text-muted">Pendentes</div>
                                <strong class="fs-4" id="identificationSummaryPending">--</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-3 p-3 bg-light-subtle h-100">
                                <div class="small text-muted">Conflitos</div>
                                <strong class="fs-4" id="identificationSummaryConflict">--</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-3 p-3 bg-light-subtle h-100">
                                <div class="small text-muted">Indisponíveis</div>
                                <strong class="fs-4" id="identificationSummaryNotAvailable">--</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-12 col-xl-3">
            <article class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-1">Novos sellers</h5>
                            <small class="text-muted">Primeiras detecções na janela recente.</small>
                        </div>
                        <span class="badge text-bg-light" id="historyDaysBadge">7 dias</span>
                    </div>
                    <div class="list-group list-group-flush" id="awaNewSellersList">
                        <div class="list-group-item px-0 text-muted">Carregando histórico...</div>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-12 col-xl-3">
            <article class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-1">Scans recentes</h5>
                            <small class="text-muted">Últimas execuções persistidas da conta ativa.</small>
                        </div>
                        <span class="badge text-bg-light" id="recentScansCount">--</span>
                    </div>
                    <div class="list-group list-group-flush" id="awaRecentScansList">
                        <div class="list-group-item px-0 text-muted">Carregando scans...</div>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-12 col-xl-3">
            <article class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 gap-3">
                        <div>
                            <h5 class="mb-1">Alertas AWA</h5>
                            <small class="text-muted">Novos sellers, picos de volume e pendências.</small>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-2">
                            <span class="badge text-bg-light" id="awaAlertsCount">--</span>
                            <span class="badge text-bg-warning text-dark" id="awaAlertsUnreadBadge">Não lidos: --</span>
                        </div>
                    </div>
                    <div class="list-group list-group-flush" id="awaAlertsList">
                        <div class="list-group-item px-0 text-muted">Carregando alertas...</div>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4 col-xl-3">
                    <label class="form-label fw-semibold small text-muted" for="scanMaxResults">Escopo do scan</label>
                    <select class="form-select" id="scanMaxResults">
                        <option value="100">100 anúncios por categoria</option>
                        <option value="250">250 anúncios por categoria</option>
                        <option value="500" selected>500 anúncios por categoria</option>
                        <option value="1000">1000 anúncios por categoria</option>
                    </select>
                </div>
                <div class="col-md-8 col-xl-9">
                    <label class="form-label fw-semibold small text-muted" for="scanCategories">Categorias (opcional)</label>
                    <input
                        type="text"
                        class="form-control"
                        id="scanCategories"
                        placeholder="Ex.: MLB214858, MLB5750"
                        aria-describedby="scanCategoriesHelp"
                    >
                    <div class="form-text" id="scanCategoriesHelp">
                        Deixe vazio para usar as categorias padrão da análise AWA. Separe múltiplas categorias por vírgula.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form id="awaSellerFiltersForm" class="row g-3 align-items-end" role="search" novalidate>
                <div class="col-md-4 col-xl-3">
                    <label class="form-label fw-semibold small text-muted" for="filterSearch">Buscar loja, cidade ou seller ID</label>
                    <input
                        type="search"
                        class="form-control"
                        id="filterSearch"
                        name="search"
                        placeholder="Ex.: AWA, Araraquara, 123456"
                        enterkeyhint="search"
                    >
                </div>
                <div class="col-6 col-md-2 col-xl-1">
                    <label class="form-label fw-semibold small text-muted" for="filterState">UF</label>
                    <select class="form-select" id="filterState" name="state">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label fw-semibold small text-muted" for="filterCity">Cidade</label>
                    <select class="form-select" id="filterCity" name="city">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label fw-semibold small text-muted" for="filterCategory">Categoria</label>
                    <select class="form-select" id="filterCategory" name="category_id">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-1">
                    <label class="form-label fw-semibold small text-muted" for="filterReputation">Reputação</label>
                    <select class="form-select" id="filterReputation" name="reputation_level">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-1">
                    <label class="form-label fw-semibold small text-muted" for="filterIdStatus">Identificação</label>
                    <select class="form-select" id="filterIdStatus" name="id_status">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-6 col-md-2 col-xl-1">
                    <label class="form-label fw-semibold small text-muted" for="filterMinItems">Mín. anúncios</label>
                    <input type="number" class="form-control" id="filterMinItems" name="min_items" min="1" step="1" placeholder="1">
                </div>
                <div class="col-6 col-md-2 col-xl-1">
                    <label class="form-label fw-semibold small text-muted" for="filterPerPage">Por página</label>
                    <select class="form-select" id="filterPerPage" name="per_page">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="col-12 col-xl-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill" id="applyAwaSellerFilters">
                        <i class="bi bi-search me-1"></i> Filtrar
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="clearAwaSellerFilters" aria-label="Limpar filtros">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </form>
            <div id="awaSellerFeedback" class="alert d-none mt-3 mb-0" role="status" aria-live="polite"></div>
        </div>
    </section>

    <section class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
            <div>
                <h5 class="mb-0">Base local de lojas AWA</h5>
                <small class="text-muted">A navegação usa a base persistida, paginada e pronta para auditoria.</small>
            </div>
            <div class="text-muted small" id="awaSellerResultsSummary">Carregando sellers...</div>
        </div>
        <div class="card-body p-0">
            <div id="awaSellerTableLoading" class="text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
                <p class="text-muted mb-0">Buscando sellers persistidos...</p>
            </div>

            <div id="awaSellerTableEmpty" class="text-center py-5 d-none">
                <i class="bi bi-shop-window fs-1 text-muted d-block mb-3"></i>
                <h6 class="fw-semibold">Nenhuma loja encontrada</h6>
                <p class="text-muted mb-0">Execute um scan ou ajuste os filtros para popular a base local.</p>
            </div>

            <div class="table-responsive d-none" id="awaSellerTableWrapper">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Loja</th>
                            <th>Localização</th>
                            <th>Reputação</th>
                            <th class="text-center">Anúncios</th>
                            <th>Identificação</th>
                            <th class="text-end pe-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="awaSellersTableBody"></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-0 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="small text-muted" id="awaSellerPaginationSummary">Página 1</div>
            <nav aria-label="Paginação da base AWA Sellers">
                <ul class="pagination pagination-sm mb-0" id="awaSellerPagination"></ul>
            </nav>
        </div>
    </section>
</main>

<div class="offcanvas offcanvas-end" tabindex="-1" id="awaSellerDetailOffcanvas" aria-labelledby="awaSellerDetailTitle">
    <div class="offcanvas-header border-bottom">
        <div>
            <h5 class="offcanvas-title mb-1" id="awaSellerDetailTitle">Detalhes da loja</h5>
            <small class="text-muted" id="awaSellerDetailSubtitle">Selecione uma loja para inspecionar a base local.</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body">
        <div id="awaSellerDetailLoading" class="text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
            <p class="text-muted mb-0">Carregando detalhes da loja...</p>
        </div>

        <div id="awaSellerDetailContent" class="d-none">
            <section class="mb-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h5 class="mb-1" id="detailNickname">--</h5>
                        <div class="small text-muted" id="detailMeta">Seller --</div>
                    </div>
                    <a href="#" class="btn btn-sm btn-outline-primary d-none" id="detailPermalink" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Ver perfil
                    </a>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                            <div class="text-muted small">Cidade / UF</div>
                            <strong id="detailLocation">--</strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                            <div class="text-muted small">Reputação</div>
                            <strong id="detailReputation">--</strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                            <div class="text-muted small">Anúncios detectados</div>
                            <strong id="detailItemsCount">--</strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                            <div class="text-muted small">Categorias</div>
                            <strong id="detailCategories">--</strong>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Identificação</h6>
                    <span class="badge text-bg-secondary" id="detailIdentificationStatus">pending</span>
                </div>
                <form id="awaSellerIdentificationForm" class="row g-3" novalidate>
                    <div class="col-md-6">
                        <label class="form-label small text-muted" for="identificationCnpj">CNPJ</label>
                        <input type="text" class="form-control" id="identificationCnpj" name="cnpj" placeholder="00.000.000/0000-00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted" for="identificationCompanyName">Razão social</label>
                        <input type="text" class="form-control" id="identificationCompanyName" name="razao_social" placeholder="Nome jurídico da empresa">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted" for="identificationSourceType">Origem</label>
                        <select class="form-select" id="identificationSourceType" name="source_type">
                            <option value="manual">Manual</option>
                            <option value="authorized_ml_account">Conta autorizada</option>
                            <option value="internal_registry">Registro interno</option>
                            <option value="external_registry">Registro externo</option>
                            <option value="website_review">Revisão website</option>
                            <option value="legal_team_validation">Validação jurídica</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted" for="identificationStatus">Status</label>
                        <select class="form-select" id="identificationStatus" name="verification_status">
                            <option value="pending">pending</option>
                            <option value="verified">verified</option>
                            <option value="not_available">not_available</option>
                            <option value="conflict">conflict</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted" for="identificationConfidence">Confiança</label>
                        <input type="number" class="form-control" id="identificationConfidence" name="confidence_score" min="0" max="100" step="1" value="50">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted" for="identificationSourceReference">Referência</label>
                        <input type="text" class="form-control" id="identificationSourceReference" name="source_reference" placeholder="URL, protocolo ou observação curta">
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted" for="identificationNotes">Observações</label>
                        <textarea class="form-control" id="identificationNotes" name="notes" rows="3" placeholder="Notas internas sobre a origem ou validação do dado"></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center gap-3">
                        <div class="small text-muted" id="identificationFeedback">Atualize os dados jurídicos desta loja com rastreabilidade.</div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-success" id="verifyAwaSellerIdentification">
                                <i class="bi bi-patch-check me-1"></i> Verificar
                            </button>
                            <button type="submit" class="btn btn-primary" id="saveAwaSellerIdentification">
                                <i class="bi bi-save me-1"></i> Salvar identificação
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="mb-0">Auditoria da identificação</h6>
                        <small class="text-muted">Últimas alterações manuais e verificações do CNPJ/razão social.</small>
                    </div>
                    <span class="badge text-bg-light" id="identificationAuditCount">--</span>
                </div>
                <div class="list-group border rounded-3" id="identificationAuditList">
                    <div class="list-group-item px-3 py-4 text-muted">Carregando auditoria...</div>
                </div>
            </section>

            <section>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Itens observados</h6>
                    <span class="small text-muted" id="detailItemsSummary">Carregando itens...</span>
                </div>
                <div class="table-responsive border rounded-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th class="text-center">Match</th>
                                <th class="text-center">Preço</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody id="awaSellerItemsTableBody"></tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>

<style>
.awa-sellers-page .card {
    border-radius: 1rem;
}

.awa-sellers-page .table > :not(caption) > * > * {
    vertical-align: middle;
}

.awa-sellers-page .badge-soft {
    background: rgba(13, 110, 253, 0.12);
    color: #0d6efd;
}
</style>

<script nonce="<?= htmlspecialchars((string) (defined('CSP_NONCE') ? CSP_NONCE : ($cspNonce ?? '')), ENT_QUOTES, 'UTF-8') ?>">
(() => {
    const state = {
        page: 1,
        currentSellerId: null,
        detailOffcanvas: null,
    };

    const endpoints = {
        metrics: '/api/brand/awa/sellers/metrics',
        filters: '/api/brand/awa/sellers/filters/options',
        sellers: '/api/brand/awa/sellers',
        scan: '/api/brand/awa/sellers/scan',
        exportCsv: '/api/brand/awa/sellers/export/csv',
        identificationSummary: '/api/brand/awa/sellers/identification/summary',
        history: '/api/brand/awa/sellers/history',
        alerts: '/api/brand/awa/sellers/alerts',
    };

    const elements = {
        feedback: document.getElementById('awaSellerFeedback'),
        scanMaxResults: document.getElementById('scanMaxResults'),
        scanCategories: document.getElementById('scanCategories'),
        refreshButton: document.getElementById('refreshAwaSellers'),
        scanButton: document.getElementById('runAwaSellerScan'),
        exportButton: document.getElementById('exportAwaSellersCsv'),
        filtersForm: document.getElementById('awaSellerFiltersForm'),
        search: document.getElementById('filterSearch'),
        state: document.getElementById('filterState'),
        city: document.getElementById('filterCity'),
        category: document.getElementById('filterCategory'),
        reputation: document.getElementById('filterReputation'),
        idStatus: document.getElementById('filterIdStatus'),
        minItems: document.getElementById('filterMinItems'),
        perPage: document.getElementById('filterPerPage'),
        clearFilters: document.getElementById('clearAwaSellerFilters'),
        tableLoading: document.getElementById('awaSellerTableLoading'),
        tableEmpty: document.getElementById('awaSellerTableEmpty'),
        tableWrapper: document.getElementById('awaSellerTableWrapper'),
        tableBody: document.getElementById('awaSellersTableBody'),
        resultsSummary: document.getElementById('awaSellerResultsSummary'),
        paginationSummary: document.getElementById('awaSellerPaginationSummary'),
        pagination: document.getElementById('awaSellerPagination'),
        metricTotalSellers: document.getElementById('metricTotalSellers'),
        metricActiveSellers: document.getElementById('metricActiveSellers'),
        metricTotalItems: document.getElementById('metricTotalItems'),
        metricItemsWithBrandAttribute: document.getElementById('metricItemsWithBrandAttribute'),
        metricLastScanStatus: document.getElementById('metricLastScanStatus'),
        metricLastScanId: document.getElementById('metricLastScanId'),
        metricLastScanDate: document.getElementById('metricLastScanDate'),
        metricMatchTypes: document.getElementById('metricMatchTypes'),
        identificationSummaryVerified: document.getElementById('identificationSummaryVerified'),
        identificationSummaryPending: document.getElementById('identificationSummaryPending'),
        identificationSummaryConflict: document.getElementById('identificationSummaryConflict'),
        identificationSummaryNotAvailable: document.getElementById('identificationSummaryNotAvailable'),
        identificationSummaryUnidentified: document.getElementById('identificationSummaryUnidentified'),
        historyDaysBadge: document.getElementById('historyDaysBadge'),
        recentScansCount: document.getElementById('recentScansCount'),
        awaNewSellersList: document.getElementById('awaNewSellersList'),
        awaRecentScansList: document.getElementById('awaRecentScansList'),
        awaAlertsCount: document.getElementById('awaAlertsCount'),
        awaAlertsUnreadBadge: document.getElementById('awaAlertsUnreadBadge'),
        awaAlertsList: document.getElementById('awaAlertsList'),
        detailLoading: document.getElementById('awaSellerDetailLoading'),
        detailContent: document.getElementById('awaSellerDetailContent'),
        detailTitle: document.getElementById('awaSellerDetailTitle'),
        detailSubtitle: document.getElementById('awaSellerDetailSubtitle'),
        detailNickname: document.getElementById('detailNickname'),
        detailMeta: document.getElementById('detailMeta'),
        detailPermalink: document.getElementById('detailPermalink'),
        detailLocation: document.getElementById('detailLocation'),
        detailReputation: document.getElementById('detailReputation'),
        detailItemsCount: document.getElementById('detailItemsCount'),
        detailCategories: document.getElementById('detailCategories'),
        detailIdentificationStatus: document.getElementById('detailIdentificationStatus'),
        detailItemsSummary: document.getElementById('detailItemsSummary'),
        detailItemsTableBody: document.getElementById('awaSellerItemsTableBody'),
        identificationForm: document.getElementById('awaSellerIdentificationForm'),
        identificationAuditCount: document.getElementById('identificationAuditCount'),
        identificationAuditList: document.getElementById('identificationAuditList'),
        identificationCnpj: document.getElementById('identificationCnpj'),
        identificationCompanyName: document.getElementById('identificationCompanyName'),
        identificationSourceType: document.getElementById('identificationSourceType'),
        identificationStatus: document.getElementById('identificationStatus'),
        identificationConfidence: document.getElementById('identificationConfidence'),
        identificationSourceReference: document.getElementById('identificationSourceReference'),
        identificationNotes: document.getElementById('identificationNotes'),
        identificationFeedback: document.getElementById('identificationFeedback'),
        verifyIdentificationButton: document.getElementById('verifyAwaSellerIdentification'),
        detailOffcanvas: document.getElementById('awaSellerDetailOffcanvas'),
    };

    function escHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escAttr(value) {
        return escHtml(value).replace(/`/g, '&#096;');
    }

    function setFeedback(type, message) {
        elements.feedback.className = `alert alert-${type}`;
        elements.feedback.textContent = message;
        elements.feedback.classList.remove('d-none');
    }

    function clearFeedback() {
        elements.feedback.className = 'alert d-none';
        elements.feedback.textContent = '';
    }

    function formatNumber(value) {
        return new Intl.NumberFormat('pt-BR').format(Number(value || 0));
    }

    function formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        }).format(Number(value || 0));
    }

    function formatDate(value) {
        if (!value) {
            return '—';
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return date.toLocaleString('pt-BR');
    }

    function buildQuery(params) {
        const query = new URLSearchParams();

        Object.entries(params).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '') {
                return;
            }

            query.set(key, String(value));
        });

        return query.toString();
    }

    function getStatusBadgeClass(status) {
        const map = {
            completed: 'text-bg-success',
            running: 'text-bg-primary',
            pending: 'text-bg-warning text-dark',
            failed: 'text-bg-danger',
            verified: 'text-bg-success',
            conflict: 'text-bg-danger',
            not_available: 'text-bg-secondary',
            active: 'text-bg-success',
            paused: 'text-bg-warning text-dark',
        };

        return map[status] || 'text-bg-light';
    }

    function getMatchBadgeClass(type) {
        const map = {
            attribute_match: 'text-bg-success',
            attribute_mismatch: 'text-bg-danger',
            title_match_only: 'text-bg-warning text-dark',
            unclassified: 'text-bg-secondary',
        };

        return map[type] || 'text-bg-light';
    }

    function getSeverityBadgeClass(severity) {
        const map = {
            info: 'text-bg-light',
            warning: 'text-bg-warning text-dark',
            danger: 'text-bg-danger',
            success: 'text-bg-success',
        };

        return map[severity] || 'text-bg-light';
    }

    function formatAlertType(type) {
        const map = {
            awa_new_seller: 'Novo seller',
            awa_volume_spike: 'Pico de volume',
            awa_unidentified_seller: 'Sem identificação',
        };

        return map[type] || type || 'Alerta operacional';
    }

    function normalizeIdStatus(value) {
        return value || 'pending';
    }

    function parseCategories(value) {
        if (Array.isArray(value)) {
            return value.filter((item) => String(item).trim() !== '');
        }

        if (typeof value === 'string' && value.trim() !== '') {
            return value.split(',').map((item) => item.trim()).filter((item) => item !== '');
        }

        return [];
    }

    function buildIdentificationHistoryUrl(sellerId, limit = 10) {
        return `${endpoints.sellers}/${sellerId}/identification/history?limit=${limit}`;
    }

    function formatAuditValue(value) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        if (Array.isArray(value)) {
            return escHtml(value.join(', '));
        }

        if (typeof value === 'object') {
            return escHtml(JSON.stringify(value));
        }

        return escHtml(String(value));
    }

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                ...(options.headers || {}),
            },
            ...options,
        });

        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload || payload.success === false) {
            const message = payload?.error || payload?.message || 'Não foi possível concluir a operação.';
            throw new Error(message);
        }

        return payload;
    }

    function getCurrentFilters() {
        return {
            search: elements.search.value.trim(),
            state: elements.state.value,
            city: elements.city.value,
            category_id: elements.category.value,
            reputation_level: elements.reputation.value,
            id_status: elements.idStatus.value,
            min_items: elements.minItems.value,
            per_page: elements.perPage.value,
            page: state.page,
        };
    }

    function setLoadingState(isLoading) {
        elements.tableLoading.classList.toggle('d-none', !isLoading);
        elements.tableWrapper.classList.add('d-none');
        elements.tableEmpty.classList.add('d-none');
    }

    function renderSelectOptions(select, values, emptyLabel, selectedValue) {
        const options = [`<option value="">${escHtml(emptyLabel)}</option>`];

        values.forEach((value) => {
            const normalizedValue = String(value || '').trim();
            if (normalizedValue === '') {
                return;
            }

            const isSelected = normalizedValue === selectedValue ? ' selected' : '';
            options.push(`<option value="${escAttr(normalizedValue)}"${isSelected}>${escHtml(normalizedValue)}</option>`);
        });

        select.innerHTML = options.join('');
    }

    async function loadMetrics() {
        const response = await requestJson(endpoints.metrics);
        const data = response.data || {};

        elements.metricTotalSellers.textContent = formatNumber(data.total_sellers);
        elements.metricActiveSellers.textContent = `Ativas: ${formatNumber(data.active_sellers)}`;
        elements.metricTotalItems.textContent = formatNumber(data.total_items);
        elements.metricItemsWithBrandAttribute.textContent = `Com atributo BRAND: ${formatNumber(data.items_with_brand_attribute)}`;

        const lastScan = data.last_scan || null;
        elements.metricLastScanStatus.className = `badge ${getStatusBadgeClass(lastScan ? lastScan.status : 'pending')}`;
        elements.metricLastScanStatus.textContent = lastScan ? String(lastScan.status || 'pending') : 'Sem histórico';
        elements.metricLastScanId.textContent = lastScan ? `#${lastScan.id}` : '#--';
        elements.metricLastScanDate.textContent = lastScan
            ? `Iniciado em ${formatDate(lastScan.started_at)}${lastScan.finished_at ? ` · Finalizado em ${formatDate(lastScan.finished_at)}` : ''}`
            : 'Aguardando primeira varredura persistida';

        const matchTypes = data.match_types || {};
        const entries = Object.entries(matchTypes);
        elements.metricMatchTypes.innerHTML = entries.length > 0
            ? entries.map(([key, value]) => (
                `<span class="badge rounded-pill ${getMatchBadgeClass(key)}">${escHtml(key)}: ${formatNumber(value)}</span>`
            )).join('')
            : '<span class="badge rounded-pill text-bg-light">Sem dados de match ainda</span>';
    }

    async function loadFilterOptions() {
        const previousValues = {
            state: elements.state.value,
            city: elements.city.value,
            category: elements.category.value,
            reputation: elements.reputation.value,
            idStatus: elements.idStatus.value,
        };

        const response = await requestJson(endpoints.filters);
        const data = response.data || {};

        renderSelectOptions(elements.state, data.states || [], 'Todas', previousValues.state);
        renderSelectOptions(elements.city, data.cities || [], 'Todas', previousValues.city);
        renderSelectOptions(elements.category, data.categories || [], 'Todas', previousValues.category);
        renderSelectOptions(elements.reputation, data.reputation_levels || [], 'Todas', previousValues.reputation);
        renderSelectOptions(elements.idStatus, data.id_statuses || [], 'Todas', previousValues.idStatus);
    }

    async function loadIdentificationSummary() {
        const response = await requestJson(endpoints.identificationSummary);
        const data = response.data || {};
        const byStatus = data.by_status || {};

        elements.identificationSummaryVerified.textContent = formatNumber(byStatus.verified);
        elements.identificationSummaryPending.textContent = formatNumber(byStatus.pending);
        elements.identificationSummaryConflict.textContent = formatNumber(byStatus.conflict);
        elements.identificationSummaryNotAvailable.textContent = formatNumber(byStatus.not_available);
        elements.identificationSummaryUnidentified.textContent = `Sem ID: ${formatNumber(data.unidentified)}`;
    }

    async function loadHistory(days = 7) {
        const response = await requestJson(`${endpoints.history}?days=${days}`);
        const data = response.data || response;
        const newSellers = Array.isArray(data.new_sellers) ? data.new_sellers : [];
        const scanRuns = Array.isArray(data.scan_runs) ? data.scan_runs : [];

        elements.historyDaysBadge.textContent = `${formatNumber(data.days || days)} dias`;
        elements.recentScansCount.textContent = `${formatNumber(scanRuns.length)} scans`;

        elements.awaNewSellersList.innerHTML = newSellers.length > 0
            ? newSellers.slice(0, 5).map((seller) => `
                <div class="list-group-item px-0">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold">${escHtml(seller.nickname || 'Sem nickname')}</div>
                            <div class="small text-muted">seller_id ${escHtml(seller.seller_id || '—')} · ${escHtml(seller.city || '—')} / ${escHtml(seller.state || '—')}</div>
                        </div>
                        <span class="badge text-bg-light">${formatDate(seller.first_seen_at)}</span>
                    </div>
                </div>
            `).join('')
            : '<div class="list-group-item px-0 text-muted">Nenhum seller novo na janela recente.</div>';

        elements.awaRecentScansList.innerHTML = scanRuns.length > 0
            ? scanRuns.slice(0, 5).map((scan) => `
                <div class="list-group-item px-0">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                        <div class="fw-semibold">Scan #${escHtml(scan.id)}</div>
                        <span class="badge ${getStatusBadgeClass(scan.status || 'pending')}">${escHtml(scan.status || 'pending')}</span>
                    </div>
                    <div class="small text-muted">
                        ${formatNumber(scan.sellers_found)} sellers · ${formatNumber(scan.items_found)} anúncios · ${formatDate(scan.started_at)}
                    </div>
                </div>
            `).join('')
            : '<div class="list-group-item px-0 text-muted">Ainda não há scans recentes para exibir.</div>';
    }

    async function loadAlerts(limit = 5) {
        const response = await requestJson(`${endpoints.alerts}?limit=${limit}`);
        const data = response.data || response;
        const alerts = Array.isArray(data.alerts) ? data.alerts : [];
        const unreadCount = Number(data.unread_count || alerts.filter((alert) => !alert.read_at).length || 0);

        elements.awaAlertsCount.textContent = `${formatNumber(data.count || alerts.length)} alertas`;
        elements.awaAlertsUnreadBadge.textContent = `Não lidos: ${formatNumber(unreadCount)}`;

        elements.awaAlertsList.innerHTML = alerts.length > 0
            ? alerts.map((alert) => `
                <div class="list-group-item px-0">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <span class="badge ${getSeverityBadgeClass(alert.severity)}">${escHtml(alert.severity || 'info')}</span>
                        <span class="small text-muted">${formatDate(alert.created_at)}</span>
                    </div>
                    <div class="fw-semibold small mb-1">${escHtml(alert.message || 'Alerta operacional AWA')}</div>
                    <div class="small text-muted">
                        ${escHtml(formatAlertType(alert.type))}${alert.read_at ? '' : ' · Não lido'}
                    </div>
                </div>
            `).join('')
            : '<div class="list-group-item px-0 text-muted">Nenhum alerta AWA recente para a conta ativa.</div>';
    }

    async function loadSellers(page = 1) {
        state.page = page;
        setLoadingState(true);
        clearFeedback();

        const query = buildQuery(getCurrentFilters());
        const response = await requestJson(`${endpoints.sellers}?${query}`);
        const sellers = response.data || [];
        const pagination = response.pagination || {
            page: 1,
            per_page: Number(elements.perPage.value || 50),
            total: 0,
            last_page: 1,
        };

        renderSellersTable(sellers, pagination);
    }

    function renderSellersTable(sellers, pagination) {
        if (!Array.isArray(sellers) || sellers.length === 0) {
            elements.tableLoading.classList.add('d-none');
            elements.tableWrapper.classList.add('d-none');
            elements.tableEmpty.classList.remove('d-none');
            elements.resultsSummary.textContent = 'Nenhuma loja encontrada para os filtros atuais.';
            elements.paginationSummary.textContent = 'Página 1 de 1';
            elements.pagination.innerHTML = '';
            return;
        }

        elements.tableBody.innerHTML = sellers.map((seller) => {
            const identificationStatus = normalizeIdStatus(seller.id_status);
            const permalinkButton = seller.permalink
                ? `<a href="${escAttr(seller.permalink)}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer">Perfil</a>`
                : '';

            return `
                <tr>
                    <td class="ps-4">
                        <div class="fw-semibold">${escHtml(seller.nickname || 'Sem nickname')}</div>
                        <div class="small text-muted">seller_id ${escHtml(seller.seller_id || '—')} · ${escHtml(seller.user_type || 'normal')}</div>
                    </td>
                    <td>
                        <div>${escHtml(seller.city || '—')}</div>
                        <div class="small text-muted">${escHtml(seller.state || 'Sem UF')}</div>
                    </td>
                    <td>
                        <span class="badge badge-soft">${escHtml(seller.reputation_level || 'Sem reputação')}</span>
                    </td>
                    <td class="text-center">
                        <strong>${formatNumber(seller.items_count)}</strong>
                    </td>
                    <td>
                        <div class="d-flex flex-column gap-1">
                            <span class="badge ${getStatusBadgeClass(identificationStatus)} align-self-start">${escHtml(identificationStatus)}</span>
                            <small class="text-muted">${seller.cnpj ? escHtml(seller.cnpj) : 'Sem CNPJ'}</small>
                        </div>
                    </td>
                    <td class="text-end pe-4">
                        <div class="d-flex justify-content-end gap-2">
                            ${permalinkButton}
                            <button
                                type="button"
                                class="btn btn-sm btn-primary js-open-detail"
                                data-seller-id="${escAttr(seller.id)}"
                                data-seller-name="${escAttr(seller.nickname || '')}"
                            >
                                Detalhes
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        elements.tableLoading.classList.add('d-none');
        elements.tableEmpty.classList.add('d-none');
        elements.tableWrapper.classList.remove('d-none');

        const firstRow = ((pagination.page - 1) * pagination.per_page) + 1;
        const lastRow = Math.min(firstRow + sellers.length - 1, pagination.total);
        elements.resultsSummary.textContent = `Mostrando ${formatNumber(firstRow)}-${formatNumber(lastRow)} de ${formatNumber(pagination.total)} lojas.`;
        elements.paginationSummary.textContent = `Página ${pagination.page} de ${pagination.last_page}`;
        renderPagination(pagination);
    }

    function renderPagination(pagination) {
        const current = Number(pagination.page || 1);
        const lastPage = Number(pagination.last_page || 1);

        if (lastPage <= 1) {
            elements.pagination.innerHTML = '';
            return;
        }

        const pages = [];
        pages.push({ label: '&laquo;', page: current - 1, disabled: current <= 1, active: false });

        for (let page = Math.max(1, current - 2); page <= Math.min(lastPage, current + 2); page += 1) {
            pages.push({ label: String(page), page, disabled: false, active: page === current });
        }

        pages.push({ label: '&raquo;', page: current + 1, disabled: current >= lastPage, active: false });

        elements.pagination.innerHTML = pages.map((item) => `
            <li class="page-item${item.disabled ? ' disabled' : ''}${item.active ? ' active' : ''}">
                <button type="button" class="page-link js-pagination-link" data-page="${item.page}" ${item.disabled ? 'disabled' : ''}>${item.label}</button>
            </li>
        `).join('');
    }

    async function runScan() {
        clearFeedback();
        elements.scanButton.disabled = true;
        elements.scanButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Executando...';

        try {
            const categories = elements.scanCategories.value
                .split(',')
                .map((value) => value.trim())
                .filter((value) => value !== '');

            const payload = {
                max_results: Number(elements.scanMaxResults.value || 500),
            };

            if (categories.length > 0) {
                payload.categories = categories;
            }

            const response = await requestJson(endpoints.scan, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            const data = response.data || {};

            setFeedback('success', `Scan concluído: ${formatNumber(data.sellers_found)} sellers e ${formatNumber(data.items_found)} anúncios processados.`);
            await refreshDashboard();
        } catch (error) {
            setFeedback('danger', error.message);
        } finally {
            elements.scanButton.disabled = false;
            elements.scanButton.innerHTML = '<i class="bi bi-broadcast-pin me-1"></i> Executar Scan';
        }
    }

    async function reloadSellerItems(sellerId) {
        const response = await requestJson(`${endpoints.sellers}/${sellerId}/items?per_page=25&page=1`);
        return {
            items: response.data || [],
            pagination: response.pagination || { total: 0 },
        };
    }

    async function reloadSellerDetail(sellerId) {
        const response = await requestJson(`${endpoints.sellers}/${sellerId}`);
        return response.data || {};
    }

    async function reloadIdentificationAudit(sellerId, limit = 10) {
        const response = await requestJson(buildIdentificationHistoryUrl(sellerId, limit));
        return Array.isArray(response.data?.history) ? response.data.history : [];
    }

    function renderIdentificationAudit(history) {
        const entries = Array.isArray(history) ? history : [];
        elements.identificationAuditCount.textContent = `${formatNumber(entries.length)} eventos`;

        elements.identificationAuditList.innerHTML = entries.length > 0
            ? entries.map((entry) => {
                const changes = Array.isArray(entry.changes) ? entry.changes : [];
                const metadata = entry.metadata && typeof entry.metadata === 'object' ? entry.metadata : {};
                const details = [
                    entry.actor ? escHtml(entry.actor) : '',
                    metadata.source_type ? `Fonte: ${escHtml(metadata.source_type)}` : '',
                    entry.created_at ? escHtml(formatDate(entry.created_at)) : '',
                ].filter((part) => part !== '');

                return `
                    <div class="list-group-item px-3 py-3">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                            <div>
                                <div class="fw-semibold small">${escHtml(entry.label || 'Atualização manual')}</div>
                                <div class="small text-muted">${escHtml(entry.summary || 'Alteração registrada na identificação.')}</div>
                            </div>
                            <span class="badge text-bg-light">${escHtml(entry.label || 'Histórico')}</span>
                        </div>
                        <div class="small text-muted mb-2">${details.length > 0 ? details.join(' · ') : 'Origem interna'}</div>
                        ${changes.length > 0
                            ? `<ul class="small ps-3 mb-0">${changes.slice(0, 4).map((change) => `
                                <li>
                                    <strong>${escHtml(change.label || change.field || 'Campo')}</strong>:
                                    <span class="text-muted">${formatAuditValue(change.before)} → ${formatAuditValue(change.after)}</span>
                                </li>
                            `).join('')}</ul>`
                            : '<div class="small text-muted mb-0">Sem diff detalhado disponível para este evento.</div>'}
                    </div>
                `;
            }).join('')
            : '<div class="list-group-item px-3 py-4 text-muted">Nenhuma alteração manual registrada para esta loja.</div>';
    }

    async function openSellerDetail(sellerId, sellerName = '') {
        state.currentSellerId = sellerId;
        if (!state.detailOffcanvas) {
            state.detailOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(elements.detailOffcanvas);
        }

        elements.detailTitle.textContent = sellerName ? `Loja ${sellerName}` : 'Detalhes da loja';
        elements.detailSubtitle.textContent = `Carregando seller interno #${sellerId}...`;
        elements.detailLoading.classList.remove('d-none');
        elements.detailLoading.innerHTML = `
            <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
            <p class="text-muted mb-0">Carregando detalhes da loja...</p>
        `;
        elements.detailContent.classList.add('d-none');
        elements.identificationAuditCount.textContent = '--';
        elements.identificationAuditList.innerHTML = '<div class="list-group-item px-3 py-4 text-muted">Carregando auditoria...</div>';
        state.detailOffcanvas.show();

        try {
            const [sellerResponse, itemsResponse, auditHistory] = await Promise.all([
                requestJson(`${endpoints.sellers}/${sellerId}`),
                requestJson(`${endpoints.sellers}/${sellerId}/items?per_page=25&page=1`),
                reloadIdentificationAudit(sellerId),
            ]);

            renderSellerDetail(
                sellerResponse.data || {},
                itemsResponse.data || [],
                itemsResponse.pagination || { total: 0 },
                auditHistory
            );
        } catch (error) {
            elements.detailLoading.innerHTML = `
                <div class="alert alert-danger mb-0" role="alert">
                    <strong>Erro ao carregar a loja.</strong><br>${escHtml(error.message)}
                </div>
            `;
        }
    }

    function renderSellerDetail(seller, items, pagination, auditHistory = []) {
        const categories = parseCategories(seller.categories);
        const identificationStatus = normalizeIdStatus(seller.id_status);

        elements.detailNickname.textContent = seller.nickname || 'Sem nickname';
        elements.detailMeta.textContent = `seller_id ${seller.seller_id || '—'} · primeira detecção ${formatDate(seller.first_seen_at)}`;
        elements.detailLocation.textContent = [seller.city || 'Sem cidade', seller.state || 'Sem UF'].join(' / ');
        elements.detailReputation.textContent = seller.reputation_level || 'Sem reputação';
        elements.detailItemsCount.textContent = formatNumber(seller.items_count || 0);
        elements.detailCategories.textContent = categories.length > 0 ? categories.join(', ') : 'Sem categorias';
        elements.detailIdentificationStatus.className = `badge ${getStatusBadgeClass(identificationStatus)}`;
        elements.detailIdentificationStatus.textContent = identificationStatus;
        elements.detailItemsSummary.textContent = `${formatNumber(pagination.total || items.length)} itens observados nesta loja.`;
        elements.detailSubtitle.textContent = `Última observação em ${formatDate(seller.last_seen_at)}`;

        if (seller.permalink) {
            elements.detailPermalink.href = seller.permalink;
            elements.detailPermalink.classList.remove('d-none');
        } else {
            elements.detailPermalink.classList.add('d-none');
        }

        elements.identificationCnpj.value = seller.cnpj || '';
        elements.identificationCompanyName.value = seller.razao_social || '';
        elements.identificationSourceType.value = seller.source_type || 'manual';
        elements.identificationStatus.value = identificationStatus;
        elements.identificationConfidence.value = String(seller.confidence_score || 50);
        elements.identificationSourceReference.value = seller.source_reference || '';
        elements.identificationNotes.value = seller.id_notes || seller.notes || '';
        elements.identificationFeedback.textContent = seller.cnpj
            ? `Última origem registrada: ${seller.source_type || 'manual'}`
            : 'Sem identificação registrada para esta loja.';
        elements.verifyIdentificationButton.disabled = identificationStatus === 'verified';
        renderIdentificationAudit(auditHistory);

        elements.detailItemsTableBody.innerHTML = Array.isArray(items) && items.length > 0
            ? items.map((item) => `
                <tr>
                    <td>
                        <div class="fw-semibold">${escHtml(item.title || item.ml_item_id || 'Item sem título')}</div>
                        <div class="small text-muted">${escHtml(item.ml_item_id || '—')} · ${escHtml(item.category_id || 'sem categoria')}</div>
                    </td>
                    <td class="text-center">
                        <span class="badge ${getMatchBadgeClass(item.brand_match_type)}">${escHtml(item.brand_match_type || 'unclassified')}</span>
                    </td>
                    <td class="text-center">${item.price !== null && item.price !== undefined ? formatCurrency(item.price) : '—'}</td>
                    <td class="text-center">
                        <span class="badge ${getStatusBadgeClass(item.status || 'pending')}">${escHtml(item.status || 'sem status')}</span>
                    </td>
                </tr>
            `).join('')
            : '<tr><td colspan="4" class="text-center text-muted py-4">Nenhum item encontrado para esta loja.</td></tr>';

        elements.detailLoading.classList.add('d-none');
        elements.detailContent.classList.remove('d-none');
    }

    async function saveIdentification(event) {
        event.preventDefault();

        if (!state.currentSellerId) {
            return;
        }

        const payload = {
            cnpj: elements.identificationCnpj.value.trim(),
            razao_social: elements.identificationCompanyName.value.trim(),
            source_type: elements.identificationSourceType.value,
            source_reference: elements.identificationSourceReference.value.trim(),
            confidence_score: Number(elements.identificationConfidence.value || 50),
            verification_status: elements.identificationStatus.value,
            notes: elements.identificationNotes.value.trim(),
        };

        elements.identificationFeedback.textContent = 'Salvando identificação...';

        try {
            await requestJson(`${endpoints.sellers}/${state.currentSellerId}/identification`, {
                method: 'PUT',
                body: JSON.stringify(payload),
            });

            const [seller, itemsState, auditHistory] = await Promise.all([
                reloadSellerDetail(state.currentSellerId),
                reloadSellerItems(state.currentSellerId),
                reloadIdentificationAudit(state.currentSellerId),
            ]);
            renderSellerDetail(seller, itemsState.items, itemsState.pagination, auditHistory);
            elements.identificationFeedback.textContent = 'Identificação salva com sucesso.';
            await Promise.all([loadMetrics(), loadSellers(state.page), loadIdentificationSummary()]);
        } catch (error) {
            elements.identificationFeedback.textContent = error.message;
        }
    }

    async function verifyIdentification() {
        if (!state.currentSellerId) {
            return;
        }

        elements.verifyIdentificationButton.disabled = true;
        elements.identificationFeedback.textContent = 'Marcando identificação como verificada...';

        try {
            await requestJson(`${endpoints.sellers}/${state.currentSellerId}/identification/verify`, {
                method: 'POST',
                body: JSON.stringify({ verified_by: 'dashboard_awa_sellers' }),
            });

            const [seller, itemsState, auditHistory] = await Promise.all([
                reloadSellerDetail(state.currentSellerId),
                reloadSellerItems(state.currentSellerId),
                reloadIdentificationAudit(state.currentSellerId),
            ]);
            renderSellerDetail(seller, itemsState.items, itemsState.pagination, auditHistory);
            elements.identificationFeedback.textContent = 'Identificação verificada com sucesso.';
            await Promise.all([loadMetrics(), loadSellers(state.page), loadIdentificationSummary()]);
        } catch (error) {
            elements.identificationFeedback.textContent = error.message;
            elements.verifyIdentificationButton.disabled = false;
        }
    }

    function clearFilters() {
        elements.filtersForm.reset();
        state.page = 1;
        loadFilterOptions()
            .then(() => loadSellers(1))
            .catch((error) => setFeedback('danger', error.message));
    }

    async function refreshDashboard() {
        await Promise.all([
            loadMetrics(),
            loadIdentificationSummary(),
            loadHistory(),
            loadAlerts(),
            loadFilterOptions(),
            loadSellers(state.page),
        ]);
    }

    document.addEventListener('click', (event) => {
        const detailButton = event.target.closest('.js-open-detail');
        if (detailButton) {
            const sellerId = detailButton.dataset.sellerId || '';
            const sellerName = detailButton.dataset.sellerName || '';
            openSellerDetail(sellerId, sellerName);
            return;
        }

        const paginationLink = event.target.closest('.js-pagination-link');
        if (paginationLink) {
            const page = Number(paginationLink.dataset.page || '1');
            loadSellers(page).catch((error) => setFeedback('danger', error.message));
        }
    });

    elements.filtersForm.addEventListener('submit', (event) => {
        event.preventDefault();
        state.page = 1;
        loadSellers(1).catch((error) => setFeedback('danger', error.message));
    });

    elements.clearFilters.addEventListener('click', clearFilters);
    elements.refreshButton.addEventListener('click', () => {
        refreshDashboard().catch((error) => setFeedback('danger', error.message));
    });
    elements.exportButton.addEventListener('click', () => {
        window.location.href = `${endpoints.exportCsv}?${buildQuery(getCurrentFilters())}`;
    });
    elements.scanButton.addEventListener('click', runScan);
    elements.identificationForm.addEventListener('submit', saveIdentification);
    elements.verifyIdentificationButton.addEventListener('click', () => {
        verifyIdentification().catch((error) => {
            elements.identificationFeedback.textContent = error.message;
        });
    });
    elements.perPage.addEventListener('change', () => {
        state.page = 1;
        loadSellers(1).catch((error) => setFeedback('danger', error.message));
    });

    refreshDashboard().catch((error) => {
        setFeedback('danger', error.message);
        setLoadingState(false);
        elements.tableEmpty.classList.remove('d-none');
    });
})();
</script>
