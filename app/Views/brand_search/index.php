<?php
/** @var string $content  — injected by layout */
$cspNonce = defined('CSP_NONCE') ? CSP_NONCE : ($_SESSION['csp_nonce'] ?? '');
?>
<div class="page-header mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h1 class="page-title mb-1">
                <i class="bi bi-search me-2 text-primary"></i>Busca por Marca
            </h1>
            <p class="text-muted mb-0">Encontre todos os anúncios de qualquer marca no Mercado Livre</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/dashboard/brand-analysis" class="btn btn-outline-secondary">
                <i class="bi bi-bar-chart me-1"></i>Brand Analyzer AWA
            </a>
            <button class="btn btn-outline-secondary" id="btnHistory" onclick="loadHistory()">
                <i class="bi bi-clock-history me-1"></i>Histórico
            </button>
        </div>
    </div>
</div>

<!-- Search Form -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0"><i class="bi bi-funnel me-2"></i>Parâmetros da Busca</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Brand Value ID <span class="text-muted fw-normal">(recomendado)</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-hash"></i></span>
                    <input type="text" class="form-control" id="brandValueId" placeholder="ex.: 7297804 (AWA)">
                </div>
                <div class="form-text">ID numérico do atributo BRAND no ML. Filtro preciso.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Nome da Marca <span class="text-muted fw-normal">(texto)</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-tag"></i></span>
                    <input type="text" class="form-control" id="brandName" placeholder="ex.: AWA, Honda, Yamaha">
                </div>
                <div class="form-text">Busca textual. Use se não souber o ID.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Máx. Anúncios</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-list-ol"></i></span>
                    <input type="number" class="form-control" id="maxResults" value="500" min="50" max="2000" step="50">
                </div>
                <div class="form-text">Limite 50–2000. Default: 500.</div>
            </div>
            <div class="col-md-8">
                <label class="form-label fw-semibold">Filtrar por Categorias <span class="text-muted fw-normal">(opcional)</span></label>
                <input type="text" class="form-control" id="categories"
                    placeholder="ex.: MLB214858, MLB5750, MLB1051 (separadas por vírgula)">
                <div class="form-text">IDs de categorias ML. Vazio = todas as categorias.</div>
            </div>
            <div class="col-md-4 d-flex flex-column justify-content-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="includeDetails" checked>
                    <label class="form-check-label" for="includeDetails">
                        Buscar detalhes de vendedores
                    </label>
                </div>
                <button class="btn btn-primary w-100" id="btnSearch" onclick="startSearch()">
                    <i class="bi bi-search me-2"></i>Iniciar Busca
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Progress / Status -->
<div id="searchStatus" class="d-none mb-4">
    <div class="card border-primary shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3">
                <div class="spinner-border text-primary" id="searchSpinner"></div>
                <div>
                    <div class="fw-semibold" id="searchStatusTitle">Executando busca…</div>
                    <div class="text-muted small" id="searchStatusMsg">Aguarde enquanto buscamos os anúncios no Mercado Livre.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Run Summary (pós-busca) -->
<div id="runSummary" class="d-none mb-4">
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-primary" id="summaryTotal">—</div>
                    <div class="text-muted small mt-1">Anúncios encontrados</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-success" id="summarySellers">—</div>
                    <div class="text-muted small mt-1">Vendedores únicos</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-info" id="summaryRunId">—</div>
                    <div class="text-muted small mt-1">ID do Run</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="display-6 fw-bold text-warning" id="summaryTime">—</div>
                    <div class="text-muted small mt-1">Tempo de execução</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export buttons -->
    <div class="d-flex gap-2 mt-3 flex-wrap">
        <button class="btn btn-outline-success" id="btnExportCsv" onclick="exportResult('csv')">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar CSV
        </button>
        <button class="btn btn-outline-info" id="btnExportJson" onclick="exportResult('json')">
            <i class="bi bi-file-earmark-code me-1"></i>Exportar JSON
        </button>
        <button class="btn btn-outline-primary" onclick="loadItems()">
            <i class="bi bi-table me-1"></i>Ver Anúncios
        </button>
        <button class="btn btn-outline-secondary" onclick="loadSellers()">
            <i class="bi bi-people me-1"></i>Ver Vendedores
        </button>
    </div>
</div>

<!-- Items table -->
<div id="itemsSection" class="d-none mb-4">
    <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h6 class="mb-0"><i class="bi bi-table me-2"></i>Anúncios <span class="badge bg-secondary ms-1" id="itemsBadge">0</span></h6>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="loadItems(currentRunId, currentItemOffset - 100)" id="btnItemsPrev">
                    <i class="bi bi-chevron-left"></i> Anterior
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="loadItems(currentRunId, currentItemOffset + 100)" id="btnItemsNext">
                    Próxima <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th class="text-end">Preço</th>
                            <th>Condição</th>
                            <th class="text-end">Qtd</th>
                            <th>Vendedor</th>
                            <th>Link</th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Sellers table -->
<div id="sellersSection" class="d-none mb-4">
    <div class="card shadow-sm">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-people me-2"></i>Vendedores <span class="badge bg-secondary ms-1" id="sellersBadge">0</span></h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Nickname</th>
                            <th class="text-end">Anúncios</th>
                            <th>Reputação</th>
                            <th>Power Seller</th>
                            <th>Perfil</th>
                        </tr>
                    </thead>
                    <tbody id="sellersBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- History panel -->
<div id="historySection" class="d-none mb-4">
    <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Histórico de Buscas</h6>
            <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('historySection').classList.add('d-none')">
                <i class="bi bi-x"></i>
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Marca</th>
                            <th class="text-end">Anúncios</th>
                            <th>Status</th>
                            <th>Iniciado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="historyBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= htmlspecialchars($cspNonce) ?>">
(function () {
    'use strict';

    // -------------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------------
    window.currentRunId       = null;
    window.currentItemOffset  = 0;
    window.currentItemTotal   = 0;

    // -------------------------------------------------------------------------
    // Start Search
    // -------------------------------------------------------------------------
    window.startSearch = function () {
        const brandValueId   = document.getElementById('brandValueId').value.trim();
        const brandName      = document.getElementById('brandName').value.trim();
        const maxResults     = parseInt(document.getElementById('maxResults').value, 10) || 500;
        const includeDetails = document.getElementById('includeDetails').checked;
        const categoriesRaw  = document.getElementById('categories').value.trim();

        if (!brandValueId && !brandName) {
            alert('Informe o Brand Value ID ou o Nome da Marca antes de buscar.');
            return;
        }

        const body = { max_results: maxResults, include_details: includeDetails };
        if (brandValueId) body.brand_value_id = brandValueId;
        else              body.brand_name      = brandName;
        if (categoriesRaw) body.categories     = categoriesRaw.split(',').map(s => s.trim()).filter(Boolean);

        setSearching(true);
        hideSections();

        requestJson('POST', '/api/brand-search/search', body)
            .then(data => {
                setSearching(false);
                if (data.success) {
                    window.currentRunId      = data.run_id;
                    window.currentItemOffset = 0;
                    showSummary(data);
                } else {
                    showError(data.error || 'Erro desconhecido');
                }
            })
            .catch(err => {
                setSearching(false);
                showError(err.message || 'Erro de comunicação');
            });
    };

    // -------------------------------------------------------------------------
    // Load items
    // -------------------------------------------------------------------------
    window.loadItems = function (runId, offset) {
        runId  = runId  ?? window.currentRunId;
        offset = offset ?? 0;
        if (!runId) return;
        offset = Math.max(0, offset);
        window.currentItemOffset = offset;

        requestJson('GET', `/api/brand-search/${runId}/items?limit=100&offset=${offset}`)
            .then(data => {
                if (!data.success) return;
                window.currentItemTotal = data.total ?? 0;
                renderItems(data.items ?? []);
                document.getElementById('itemsBadge').textContent = data.total ?? 0;
                document.getElementById('btnItemsPrev').disabled = offset === 0;
                document.getElementById('btnItemsNext').disabled = (offset + 100) >= (data.total ?? 0);
                document.getElementById('itemsSection').classList.remove('d-none');
            });
    };

    // -------------------------------------------------------------------------
    // Load sellers
    // -------------------------------------------------------------------------
    window.loadSellers = function (runId) {
        runId = runId ?? window.currentRunId;
        if (!runId) return;

        requestJson('GET', `/api/brand-search/${runId}/sellers`)
            .then(data => {
                if (!data.success) return;
                const sellers = data.sellers ?? [];
                renderSellers(sellers);
                document.getElementById('sellersBadge').textContent = sellers.length;
                document.getElementById('sellersSection').classList.remove('d-none');
            });
    };

    // -------------------------------------------------------------------------
    // Load history
    // -------------------------------------------------------------------------
    window.loadHistory = function () {
        requestJson('GET', '/api/brand-search/history?limit=20')
            .then(data => {
                if (!data.success) return;
                renderHistory(data.runs ?? []);
                document.getElementById('historySection').classList.remove('d-none');
            });
    };

    // -------------------------------------------------------------------------
    // Export
    // -------------------------------------------------------------------------
    window.exportResult = function (format) {
        if (!window.currentRunId) return;
        window.open(`/api/brand-search/${window.currentRunId}/export/${format}`, '_blank');
    };

    // -------------------------------------------------------------------------
    // Renderers
    // -------------------------------------------------------------------------
    function renderItems(items) {
        const tbody = document.getElementById('itemsBody');
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Nenhum item encontrado.</td></tr>';
            return;
        }
        tbody.innerHTML = items.map(it => {
            const price     = it.price != null ? 'R$ ' + parseFloat(it.price).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : '—';
            const condition = { new: '<span class="badge bg-success">Novo</span>', used: '<span class="badge bg-warning text-dark">Usado</span>', not_specified: '<span class="badge bg-secondary">N/A</span>' }[it.condition] ?? '—';
            const link      = it.permalink ? `<a href="${escHtml(it.permalink)}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary btn-sm py-0 px-1"><i class="bi bi-box-arrow-up-right"></i></a>` : '—';
            return `<tr>
                <td class="font-monospace small">${escHtml(it.item_id)}</td>
                <td style="max-width:280px" class="text-truncate" title="${escHtml(it.title)}">${escHtml(it.title)}</td>
                <td class="text-end fw-semibold">${price}</td>
                <td>${condition}</td>
                <td class="text-end">${it.available_quantity ?? 0}</td>
                <td class="small">${escHtml(it.seller_nickname || String(it.seller_id || ''))}</td>
                <td>${link}</td>
            </tr>`;
        }).join('');
    }

    function renderSellers(sellers) {
        const tbody = document.getElementById('sellersBody');
        if (!sellers.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Nenhum vendedor encontrado.</td></tr>';
            return;
        }
        tbody.innerHTML = sellers.map((s, i) => {
            const repBadge = repLabel(s.reputation_level);
            const ps       = s.power_seller_status && s.power_seller_status !== '' ? `<span class="badge bg-warning text-dark">${escHtml(s.power_seller_status)}</span>` : '—';
            const link     = s.profile_url ? `<a href="${escHtml(s.profile_url)}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary py-0 px-1"><i class="bi bi-box-arrow-up-right"></i></a>` : '—';
            return `<tr>
                <td class="text-muted">${i + 1}</td>
                <td class="fw-semibold">${escHtml(s.nickname || String(s.seller_id))}</td>
                <td class="text-end fw-bold">${s.items_in_run ?? 0}</td>
                <td>${repBadge}</td>
                <td>${ps}</td>
                <td>${link}</td>
            </tr>`;
        }).join('');
    }

    function renderHistory(runs) {
        const tbody = document.getElementById('historyBody');
        if (!runs.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Nenhum run registrado.</td></tr>';
            return;
        }
        tbody.innerHTML = runs.map(r => {
            const statusBadge = { done: '<span class="badge bg-success">Concluído</span>', running: '<span class="badge bg-primary">Rodando</span>', error: '<span class="badge bg-danger">Erro</span>' }[r.status] ?? r.status;
            const actions = `
                <button class="btn btn-xs btn-sm btn-outline-primary py-0 px-1 me-1" onclick="restoreRun(${r.id})" title="Ver resultados">
                    <i class="bi bi-eye"></i>
                </button>
                <button class="btn btn-xs btn-sm btn-outline-success py-0 px-1 me-1" onclick="window.open('/api/brand-search/${r.id}/export/csv','_blank')" title="CSV">
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                </button>
                <button class="btn btn-xs btn-sm btn-outline-info py-0 px-1" onclick="window.open('/api/brand-search/${r.id}/export/json','_blank')" title="JSON">
                    <i class="bi bi-file-earmark-code"></i>
                </button>`;
            return `<tr>
                <td class="font-monospace">${r.id}</td>
                <td class="fw-semibold">${escHtml(r.brand_query)}</td>
                <td class="text-end">${r.total_found ?? 0}</td>
                <td>${statusBadge}</td>
                <td class="small text-muted">${r.started_at ?? ''}</td>
                <td>${actions}</td>
            </tr>`;
        }).join('');
    }

    window.restoreRun = function (runId) {
        window.currentRunId      = runId;
        window.currentItemOffset = 0;
        requestJson('GET', `/api/brand-search/${runId}/run`)
            .then(data => {
                if (!data.success || !data.run) return;
                const r = data.run;
                document.getElementById('summaryTotal').textContent   = r.total_found ?? '—';
                document.getElementById('summarySellers').textContent = '—';
                document.getElementById('summaryRunId').textContent   = runId;
                document.getElementById('summaryTime').textContent    = '—';
                document.getElementById('runSummary').classList.remove('d-none');
            });
    };

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------
    function showSummary(data) {
        document.getElementById('summaryTotal').textContent   = (data.total_found   ?? '—').toLocaleString?.() ?? data.total_found;
        document.getElementById('summarySellers').textContent = (data.unique_sellers ?? '—').toLocaleString?.() ?? data.unique_sellers;
        document.getElementById('summaryRunId').textContent   = data.run_id ?? '—';
        document.getElementById('summaryTime').textContent    = data.execution_time ?? '—';
        document.getElementById('runSummary').classList.remove('d-none');
    }

    function setSearching(state) {
        const el  = document.getElementById('searchStatus');
        const btn = document.getElementById('btnSearch');
        if (state) {
            el.classList.remove('d-none');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Buscando…';
        } else {
            el.classList.add('d-none');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-2"></i>Iniciar Busca';
        }
    }

    function showError(msg) {
        const el = document.getElementById('searchStatus');
        el.classList.remove('d-none');
        el.querySelector('.spinner-border')?.classList.add('d-none');
        document.getElementById('searchStatusTitle').textContent = 'Erro na busca';
        document.getElementById('searchStatusMsg').textContent   = msg;
        el.querySelector('.card').classList.replace('border-primary', 'border-danger');
    }

    function hideSections() {
        ['runSummary', 'itemsSection', 'sellersSection'].forEach(id => {
            document.getElementById(id).classList.add('d-none');
        });
    }

    function repLabel(level) {
        const map = {
            '1_red':    '<span class="badge bg-danger">Vermelho</span>',
            '2_orange': '<span class="badge bg-warning text-dark">Laranja</span>',
            '3_yellow': '<span class="badge bg-warning text-dark">Amarelo</span>',
            '4_light_green': '<span class="badge bg-success">Verde Claro</span>',
            '5_green': '<span class="badge bg-success">Verde</span>',
        };
        return map[level] ?? (level ? `<span class="badge bg-secondary">${escHtml(level)}</span>` : '—');
    }

    function escHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Usa o helper global requestJson (api-client.js já carregado pelo layout)
    function requestJson(method, url, body) {
        const opts = {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
        };
        if (body) opts.body = JSON.stringify(body);
        return fetch(url, opts).then(r => r.json());
    }
})();
</script>
