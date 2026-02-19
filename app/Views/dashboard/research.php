<?php
$title = 'Deep Research';
$subtitle = 'Pesquisa avançada de mercado e inteligência de marcas';
$breadcrumbs = [
    ['label' => 'Ferramentas', 'url' => ''],
    ['label' => 'Deep Research', 'url' => '']
];
?>

<?php include __DIR__ . '/../layouts/modern/partials/page-header.php'; ?>

<div class="container-fluid">
    <div class="row g-3 align-items-center mb-3">
        <div class="col-md-8">
            <div class="alert alert-primary d-flex align-items-center mb-0">
                <i class="bi bi-binoculars me-3" style="font-size: 1.6rem;"></i>
                <div>
                    <div class="fw-semibold">Mapeie marcas, preços e vendedores em qualquer categoria.</div>
                    <small class="text-muted">Use filtros para refinar a busca e priorizar oportunidades.</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 d-flex gap-2 justify-content-md-end">
            <button class="btn btn-outline-secondary btn-sm" type="button" id="btnReset">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Limpar filtros
            </button>
            <button class="btn btn-primary" form="research-form" type="submit">
                <i class="bi bi-search me-1"></i> Pesquisar
            </button>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white border-0 pb-0">
            <h5 class="mb-1">Filtros de Pesquisa</h5>
            <small class="text-muted">Categoria e marca são obrigatórias; demais filtros são opcionais.</small>
        </div>
        <div class="card-body pt-3">
            <form id="research-form" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Categoria (MLB*)</label>
                    <input type="text" id="category" class="form-control" placeholder="Ex: MLB1055" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Marca</label>
                    <input type="text" id="brand" class="form-control" placeholder="Ex: Apple" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Faixa de preço (R$)</label>
                    <div class="input-group">
                        <input type="number" min="0" step="0.01" id="price-min" class="form-control" placeholder="Mín"> 
                        <input type="number" min="0" step="0.01" id="price-max" class="form-control" placeholder="Máx">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Condição</label>
                    <select id="condition" class="form-select">
                        <option value="">Todas</option>
                        <option value="new">Novo</option>
                        <option value="used">Usado</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Envio</label>
                    <select id="shipping" class="form-select">
                        <option value="">Todos</option>
                        <option value="free_shipping">Frete grátis</option>
                        <option value="fulfillment">Full</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo de anúncio</label>
                    <select id="listing-type" class="form-select">
                        <option value="">Todos</option>
                        <option value="gold_pro">Gold Pro</option>
                        <option value="gold_special">Gold Premium</option>
                        <option value="gold">Gold</option>
                        <option value="silver">Clássico</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Reputação do vendedor</label>
                    <select id="seller-reputation" class="form-select">
                        <option value="">Todas</option>
                        <option value="5_green">Verde</option>
                        <option value="4_light_green">Verde Claro</option>
                        <option value="3_yellow">Amarelo</option>
                        <option value="2_orange">Laranja</option>
                        <option value="1_red">Vermelho</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ordenação</label>
                    <select id="sort" class="form-select">
                        <option value="">Relevância</option>
                        <option value="price_asc">Preço crescente</option>
                        <option value="price_desc">Preço decrescente</option>
                        <option value="sold_quantity_desc">Mais vendidos</option>
                        <option value="recent_desc">Mais recentes</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Máx. anúncios</label>
                    <input type="number" id="max-items" class="form-control" min="50" max="2000" step="50" value="500">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Incluir detalhes de sellers</label>
                    <select id="include-sellers" class="form-select">
                        <option value="true" selected>Sim</option>
                        <option value="false">Não</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Analisar frete</label>
                    <select id="analyze-shipping" class="form-select">
                        <option value="true" selected>Sim</option>
                        <option value="false">Não</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Calcular comissões</label>
                    <select id="calc-commission" class="form-select">
                        <option value="true" selected>Sim</option>
                        <option value="false">Não</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h6 class="mb-1">Comparar duas marcas</h6>
                    <small class="text-muted">Usa o endpoint /api/research/compare para confronto lado a lado.</small>
                </div>
                <div class="card-body">
                    <form id="compare-form" class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Categoria (MLB*)</label>
                            <input type="text" id="compare-category" class="form-control" placeholder="MLB1055" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Marca A</label>
                            <input type="text" id="compare-brand-a" class="form-control" placeholder="Marca A" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Marca B</label>
                            <input type="text" id="compare-brand-b" class="form-control" placeholder="Marca B" required>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="bi bi-arrow-left-right me-1"></i> Comparar
                            </button>
                            <div id="compare-status" class="text-muted small d-none">Comparando...</div>
                        </div>
                    </form>
                    <div id="compare-results" class="mt-3 text-muted small">Preencha os campos e clique em comparar.</div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <h6 class="mb-1">Top sellers da categoria</h6>
                    <small class="text-muted">Consulta rápida usando /api/research/sellers/{category}.</small>
                </div>
                <div class="card-body">
                    <form id="sellers-form" class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Categoria (MLB*)</label>
                            <input type="text" id="sellers-category" class="form-control" placeholder="MLB1055" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Máx. sellers</label>
                            <input type="number" id="sellers-limit" class="form-control" min="5" max="50" step="5" value="20">
                        </div>
                        <div class="col-md-3 d-grid">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="bi bi-people me-1"></i> Listar
                            </button>
                        </div>
                    </form>
                    <div id="sellers-quick" class="mt-3 text-muted small">Informe a categoria para ver os principais sellers.</div>
                </div>
            </div>
        </div>
    </div>

    <div id="results-container" class="d-none">
        <div class="row g-3" id="summary-cards">
            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total de anúncios</div>
                        <h4 class="fw-bold mb-1" id="stat-total">--</h4>
                        <div class="small text-muted" id="stat-catalog">Catalogados / Comuns</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Preço médio</div>
                        <h4 class="fw-bold mb-1" id="stat-avg-price">--</h4>
                        <div class="small text-muted" id="stat-range">--</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Frete</div>
                        <h4 class="fw-bold mb-1" id="stat-free">--</h4>
                        <div class="small text-muted" id="stat-full">--</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">Oportunidades</div>
                        <h4 class="fw-bold mb-1" id="stat-opp">--</h4>
                        <div class="small text-muted" id="stat-opp-high">--</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">Top Vendedores</h6>
                    <small class="text-muted">Participação de mercado</small>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <button class="btn btn-sm btn-outline-secondary" id="btn-export-sellers" type="button">
                        <i class="bi bi-download me-1"></i> Exportar CSV
                    </button>
                    <span class="badge bg-light text-dark" id="stat-total-sellers">--</span>
                </div>
            </div>
            <div class="card-body" id="top-sellers">
                <div class="text-center text-muted" id="sellers-empty">Nenhum vendedor encontrado.</div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">Resumo Executivo</h6>
                    <small class="text-muted">Dados consolidados da pesquisa</small>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <button class="btn btn-sm btn-outline-secondary" id="btn-export-summary" type="button">
                        <i class="bi bi-download me-1"></i> Exportar CSV
                    </button>
                    <span class="badge bg-primary" id="research-id">--</span>
                </div>
            </div>
            <div class="card-body" id="summary-content">
                <div class="text-center text-muted" id="summary-empty">Carregue uma pesquisa para ver o resumo.</div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('research-form');
    const compareForm = document.getElementById('compare-form');
    const sellersForm = document.getElementById('sellers-form');
    const summaryContent = document.getElementById('summary-content');
    const summaryEmpty = document.getElementById('summary-empty');
    const sellersContainer = document.getElementById('top-sellers');
    const sellersEmpty = document.getElementById('sellers-empty');
    const researchIdBadge = document.getElementById('research-id');
    const resultsContainer = document.getElementById('results-container');
    const btnSearch = form.querySelector('button[type="submit"]');
    const btnCompareSubmit = compareForm.querySelector('button[type="submit"]');
    const btnSellersSubmit = sellersForm.querySelector('button[type="submit"]');
    const compareStatus = document.getElementById('compare-status');
    const sellersQuick = document.getElementById('sellers-quick');
    const btnExportSummary = document.getElementById('btn-export-summary');
    const btnExportSellers = document.getElementById('btn-export-sellers');

    const statTotal = document.getElementById('stat-total');
    const statCatalog = document.getElementById('stat-catalog');
    const statAvgPrice = document.getElementById('stat-avg-price');
    const statRange = document.getElementById('stat-range');
    const statFree = document.getElementById('stat-free');
    const statFull = document.getElementById('stat-full');
    const statOpp = document.getElementById('stat-opp');
    const statOppHigh = document.getElementById('stat-opp-high');
    const statTotalSellers = document.getElementById('stat-total-sellers');

    let lastResearchData = null;
    let lastSellersData = null;
    const STORAGE_KEY = 'mlm_research_filters_v1';

    loadSavedFilters();

    document.getElementById('btnReset').addEventListener('click', () => {
        form.reset();
        document.getElementById('max-items').value = 500;
        document.getElementById('include-sellers').value = 'true';
        document.getElementById('analyze-shipping').value = 'true';
        document.getElementById('calc-commission').value = 'true';
        localStorage.removeItem(STORAGE_KEY);
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const category = document.getElementById('category').value.trim();
        const brand = document.getElementById('brand').value.trim();
        if (!category || !brand) {
            alert('Por favor, preencha a Categoria e a Marca.');
            return;
        }

        const params = new URLSearchParams();
        const priceMin = document.getElementById('price-min').value;
        const priceMax = document.getElementById('price-max').value;
        const condition = document.getElementById('condition').value;
        const shipping = document.getElementById('shipping').value;
        const listingType = document.getElementById('listing-type').value;
        const sellerRep = document.getElementById('seller-reputation').value;
        const sort = document.getElementById('sort').value;
        const maxItems = document.getElementById('max-items').value;
        const includeSellers = document.getElementById('include-sellers').value;
        const analyzeShipping = document.getElementById('analyze-shipping').value;
        const calcCommission = document.getElementById('calc-commission').value;

        saveFilters({
            category,
            brand,
            priceMin,
            priceMax,
            condition,
            shipping,
            listingType,
            sellerRep,
            sort,
            maxItems,
            includeSellers,
            analyzeShipping,
            calcCommission
        });

        if (priceMin) params.append('price_min', priceMin);
        if (priceMax) params.append('price_max', priceMax);
            toggleButton(btnSearch, false);
        if (condition) params.append('condition', condition);
        if (shipping) params.append('shipping', shipping);
        if (listingType) params.append('listing_type', listingType);
        if (sellerRep) params.append('seller_reputation', sellerRep);
        if (sort) params.append('sort', sort);
        if (maxItems) params.append('max_items', maxItems);
        params.append('include_sellers', includeSellers);
        params.append('analyze_shipping', analyzeShipping);
        params.append('calculate_commissions', calcCommission);

        const url = `/api/research/brand/${encodeURIComponent(category)}/${encodeURIComponent(brand)}?${params.toString()}`;

        toggleButton(btnSearch, true);
        setLoadingState(true);
        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const result = await response.json();

            if (result.success && result.data) {
                lastResearchData = result.data;
                renderSummary(result.data);
                renderSellers(result.data);
                resultsContainer.classList.remove('d-none');
            } else {
                showError(result.error || 'Erro desconhecido.');
            }
        } catch (error) {
            showError(error.message);
        } finally {
            setLoadingState(false);
            toggleButton(btnSearch, false);
        }
    });

    compareForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const category = document.getElementById('compare-category').value.trim();
        const brandA = document.getElementById('compare-brand-a').value.trim();
        const brandB = document.getElementById('compare-brand-b').value.trim();
        if (!category || !brandA || !brandB) {
            alert('Preencha categoria e as duas marcas.');
            return;
        }
        toggleButton(btnCompareSubmit, true);
        compareStatus.classList.remove('d-none');
        compareStatus.textContent = 'Comparando...';
        compareResults.innerHTML = '';
        try {
            const url = `/api/research/compare/${encodeURIComponent(category)}/${encodeURIComponent(brandA)}/${encodeURIComponent(brandB)}`;
            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const result = await response.json();
            if (result.success && result.data) {
                renderCompare(result.data);
                compareStatus.classList.add('d-none');
                toggleButton(btnCompareSubmit, false);
            } else {
                compareResults.innerHTML = `<div class="alert alert-danger">${escapeHtml(result.error || 'Erro ao comparar.')}</div>`;
                compareStatus.classList.add('d-none');
                toggleButton(btnCompareSubmit, false);
            }
        } catch (err) {
            compareResults.innerHTML = `<div class="alert alert-danger">${escapeHtml(err.message)}</div>`;
            compareStatus.classList.add('d-none');
            toggleButton(btnCompareSubmit, false);
        }
    });

    sellersForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const category = document.getElementById('sellers-category').value.trim();
        const limit = document.getElementById('sellers-limit').value || 20;
        if (!category) {
            alert('Informe a categoria.');
            return;
        }
        toggleButton(btnSellersSubmit, true);
        sellersQuick.textContent = 'Carregando sellers...';
        try {
            const url = `/api/research/sellers/${encodeURIComponent(category)}?max_items=${encodeURIComponent(limit)}`;
            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const result = await response.json();
            if (result.success && result.data) {
                lastSellersData = result.data;
                renderSellersQuick(result.data);
            } else {
                sellersQuick.innerHTML = `<div class="alert alert-danger">${escapeHtml(result.error || 'Erro ao listar sellers.')}</div>`;
            }
        } catch (err) {
            sellersQuick.innerHTML = `<div class="alert alert-danger">${escapeHtml(err.message)}</div>`;
        } finally {
            toggleButton(btnSellersSubmit, false);
        }
    });

    btnExportSummary.addEventListener('click', () => {
        if (!lastResearchData) {
            alert('Realize uma pesquisa antes de exportar.');
            return;
        }
        const rows = buildSummaryCsv(lastResearchData);
        downloadCsv(rows, 'deep-research-summary.csv');
    });

    btnExportSellers.addEventListener('click', () => {
        const sellersData = lastSellersData?.top_sellers || lastResearchData?.sellers?.sellers;
        if (!sellersData || !sellersData.length) {
            alert('Nenhum seller carregado para exportar.');
            return;
        }
        const rows = buildSellersCsv(sellersData);
        downloadCsv(rows, 'deep-research-sellers.csv');
    });

    function setLoadingState(isLoading) {
        summaryEmpty.classList.toggle('d-none', isLoading);
        sellersEmpty.classList.toggle('d-none', isLoading);
        summaryContent.innerHTML = isLoading ? '<div class="text-center text-muted">Carregando...</div>' : '';
        sellersContainer.innerHTML = isLoading ? '<div class="text-center text-muted">Carregando...</div>' : '';
    }

    function renderSummary(data) {
        const s = data.summary || {};
        researchIdBadge.textContent = data.research_id || '---';

        statTotal.textContent = formatNumber(s.total_listings ?? 0);
        const catalog = s.catalog_listings ?? 0;
        const common = s.common_listings ?? 0;
        statCatalog.textContent = `${formatNumber(catalog)} catalogados / ${formatNumber(common)} comuns`;

        const avgPrice = s.avg_price ?? 0;
        statAvgPrice.textContent = `R$ ${formatCurrency(avgPrice)}`;
        const range = s.price_range || {};
        statRange.textContent = `Faixa: R$ ${formatCurrency(range.min ?? 0)} - R$ ${formatCurrency(range.max ?? 0)}`;

        const freeRate = s.free_shipping_rate ?? 0;
        const fullRate = s.full_rate ?? 0;
        statFree.textContent = `${freeRate}% com frete grátis`;
        statFull.textContent = `${fullRate}% Full`;

        const totalOpp = s.total_opportunities ?? 0;
        const highOpp = s.high_priority_opportunities ?? 0;
        statOpp.textContent = formatNumber(totalOpp);
        statOppHigh.textContent = `${highOpp} alta prioridade`;

        statTotalSellers.textContent = `${s.total_sellers ?? 0} sellers`;

        const leader = s.market_leader || 'N/A';
        const leaderShare = s.market_leader_share || 0;

        summaryContent.innerHTML = `
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                        <div class="text-muted small">Líder</div>
                        <div class="fw-semibold">${escapeHtml(leader)}</div>
                        <small class="text-muted">Participação: ${leaderShare}%</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                        <div class="text-muted small">Processamento</div>
                        <div class="fw-semibold">${data.processing_time_seconds ?? '--'}s</div>
                        <small class="text-muted">Status: ${data.status ?? '—'}</small>
                    </div>
                </div>
            </div>
        `;

        summaryEmpty.classList.add('d-none');
    }

    function renderSellers(data) {
        const sellers = data.sellers?.sellers || [];
        if (!sellers.length) {
            sellersContainer.innerHTML = '';
            sellersEmpty.classList.remove('d-none');
            return;
        }
        sellersEmpty.classList.add('d-none');
        sellersContainer.innerHTML = sellers.slice(0, 10).map(s => `
            <div class="mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">${escapeHtml(s.nickname || '---')}</div>
                        <small class="text-muted">ID ${s.id || '-'} · ${s.items_count ?? 0} anúncios</small>
                    </div>
                    <span class="badge bg-light text-dark">${s.market_share ?? 0}%</span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: ${(s.market_share ?? 0)}%;"></div>
                </div>
            </div>
        `).join('');
    }

    function renderSellersQuick(data) {
        const sellers = data.top_sellers || [];
        if (!sellers.length) {
            sellersQuick.textContent = 'Nenhum seller encontrado para a categoria.';
            return;
        }
        const total = data.total_sellers ?? sellers.length;
        sellersQuick.innerHTML = `
            <div class="mb-2 text-muted">${total} sellers no total (top ${sellers.length} exibidos)</div>
            ${sellers.slice(0, 10).map(s => `
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">${escapeHtml(s.nickname || '---')}</div>
                            <small class="text-muted">ID ${s.id || '-'} · ${s.items_count ?? 0} anúncios</small>
                        </div>
                        <span class="badge bg-light text-dark">${s.market_share ?? 0}%</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-secondary" role="progressbar" style="width: ${(s.market_share ?? 0)}%;"></div>
                    </div>
                </div>
            `).join('')}
        `;
    }

    function renderCompare(data) {
        const b1 = data.brand_1?.summary || {};
        const b2 = data.brand_2?.summary || {};
        compareResults.innerHTML = `
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded h-100">
                        <div class="fw-semibold">${escapeHtml(data.brand_1?.name || 'Marca A')}</div>
                        <div class="small text-muted">Anúncios: ${formatNumber(b1.total_listings ?? 0)}</div>
                        <div class="small text-muted">Preço médio: R$ ${formatCurrency(b1.avg_price ?? 0)}</div>
                        <div class="small text-muted">Líder: ${escapeHtml(b1.market_leader || 'N/A')}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded h-100">
                        <div class="fw-semibold">${escapeHtml(data.brand_2?.name || 'Marca B')}</div>
                        <div class="small text-muted">Anúncios: ${formatNumber(b2.total_listings ?? 0)}</div>
                        <div class="small text-muted">Preço médio: R$ ${formatCurrency(b2.avg_price ?? 0)}</div>
                        <div class="small text-muted">Líder: ${escapeHtml(b2.market_leader || 'N/A')}</div>
                    </div>
                </div>
            </div>
        `;
    }

    function showError(message) {
        summaryContent.innerHTML = `<div class="alert alert-danger">${escapeHtml(message)}</div>`;
        summaryEmpty.classList.add('d-none');
        sellersContainer.innerHTML = '';
        sellersEmpty.classList.add('d-none');
    }

    function formatCurrency(value) {
        return Number(value || 0).toFixed(2);
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('pt-BR');
    }

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));
    }

    function buildSummaryCsv(data) {
        const s = data.summary || {};
        const rows = [
            ['research_id', data.research_id || ''],
            ['status', data.status || ''],
            ['total_listings', s.total_listings ?? 0],
            ['catalog_listings', s.catalog_listings ?? 0],
            ['common_listings', s.common_listings ?? 0],
            ['total_sellers', s.total_sellers ?? 0],
            ['market_leader', s.market_leader || ''],
            ['market_leader_share', s.market_leader_share ?? 0],
            ['avg_price', s.avg_price ?? 0],
            ['price_min', s.price_range?.min ?? 0],
            ['price_max', s.price_range?.max ?? 0],
            ['free_shipping_rate', s.free_shipping_rate ?? 0],
            ['full_rate', s.full_rate ?? 0],
            ['total_opportunities', s.total_opportunities ?? 0],
            ['high_priority_opportunities', s.high_priority_opportunities ?? 0]
        ];
        if (data.sellers?.sellers?.length) {
            rows.push([]);
            rows.push(['top_sellers']);
            rows.push(['id','nickname','items_count','market_share']);
            data.sellers.sellers.slice(0, 20).forEach(seller => {
                rows.push([seller.id, seller.nickname, seller.items_count, seller.market_share]);
            });
        }
        return rows;
    }

    function downloadCsv(rows, filename) {
        const csv = rows.map(r => r.map(v => {
            const val = v === undefined || v === null ? '' : String(v);
            return /[",\n]/.test(val) ? '"' + val.replace(/"/g, '""') + '"' : val;
        }).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
        URL.revokeObjectURL(link.href);
    }

    function buildSellersCsv(sellers) {
        const rows = [['id','nickname','items_count','market_share']];
        sellers.forEach(s => rows.push([s.id, s.nickname, s.items_count, s.market_share]));
        return rows;
    }

    function saveFilters(data) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        } catch (_) {}
    }

    function loadSavedFilters() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return;
            const data = JSON.parse(raw);
            if (data.category) document.getElementById('category').value = data.category;
            if (data.brand) document.getElementById('brand').value = data.brand;
            if (data.priceMin) document.getElementById('price-min').value = data.priceMin;
            if (data.priceMax) document.getElementById('price-max').value = data.priceMax;
            if (data.condition) document.getElementById('condition').value = data.condition;
            if (data.shipping) document.getElementById('shipping').value = data.shipping;
            if (data.listingType) document.getElementById('listing-type').value = data.listingType;
            if (data.sellerRep) document.getElementById('seller-reputation').value = data.sellerRep;
            if (data.sort) document.getElementById('sort').value = data.sort;
            if (data.maxItems) document.getElementById('max-items').value = data.maxItems;
            if (data.includeSellers) document.getElementById('include-sellers').value = data.includeSellers;
            if (data.analyzeShipping) document.getElementById('analyze-shipping').value = data.analyzeShipping;
            if (data.calcCommission) document.getElementById('calc-commission').value = data.calcCommission;
        } catch (_) {}
    }
});
</script>
