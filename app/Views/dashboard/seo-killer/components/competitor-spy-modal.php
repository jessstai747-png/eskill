<!-- Modal Espião de Concorrentes -->
<div class="modal fade" id="competitorSpyModal" tabindex="-1" aria-labelledby="competitorSpyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #2C3E50 0%, #34495E 100%); color: white;">
                <h5 class="modal-title" id="competitorSpyModalLabel">
                    <i class="bi bi-binoculars"></i> Espião de Concorrentes
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Busca -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Modo de Busca</label>
                                <select class="form-select" id="spy-mode" onchange="CompetitorSpy.toggleMode()">
                                    <option value="search">Por Termo de Busca</option>
                                    <option value="product">Baseado no Meu Produto</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <!-- Busca por Termo -->
                                <div id="spy-search-mode">
                                    <label class="form-label fw-bold">Termo de Busca</label>
                                    <input type="text" class="form-control" id="spy-search-term" placeholder="Ex: notebook gamer, tênis corrida...">
                                </div>
                                <!-- Busca por Produto -->
                                <div id="spy-product-mode" style="display: none;">
                                    <label class="form-label fw-bold">Seu Produto</label>
                                    <select class="form-select" id="spy-product-select">
                                        <option value="">Escolha um produto...</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-3">
                                <label class="form-label">Quantidade</label>
                                <select class="form-select form-select-sm" id="spy-limit">
                                    <option value="5">5 concorrentes</option>
                                    <option value="10" selected>10 concorrentes</option>
                                    <option value="20">20 concorrentes</option>
                                    <option value="50">50 concorrentes</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Faixa de Preço</label>
                                <select class="form-select form-select-sm" id="spy-price-range">
                                    <option value="">Todas</option>
                                    <option value="low">Baixo (até R$100)</option>
                                    <option value="medium">Médio (R$100-500)</option>
                                    <option value="high">Alto (R$500+)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Vendas Mínimas</label>
                                <select class="form-select form-select-sm" id="spy-min-sales">
                                    <option value="0">Todas</option>
                                    <option value="10">10+ vendas</option>
                                    <option value="50" selected>50+ vendas</option>
                                    <option value="100">100+ vendas</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button class="btn btn-primary w-100" onclick="CompetitorSpy.search()">
                                    <i class="bi bi-search"></i> Espionar
                                </button>
                            </div>
                        </div>
                        <!-- E13 Strategy Analysis Button (for Product Mode) -->
                        <div class="row mt-3" id="e13-analysis-section" style="display: none;">
                            <div class="col-12">
                                <div class="alert alert-info d-flex justify-content-between align-items-center mb-0">
                                    <span><i class="bi bi-robot me-2"></i> Análise competitiva avançada (Estratégia E13)</span>
                                    <button class="btn btn-sm btn-outline-primary" onclick="CompetitorSpy.runE13Analysis()">
                                        <i class="bi bi-cpu"></i> Analisar com IA
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- E13 Results Container -->
                        <div class="row mt-3" id="e13-results-container" style="display: none;">
                            <div class="col-12" id="competitorAnalysisResults">
                                <!-- Populated by SEOKiller.renderCompetitorAnalysisResults() -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resultados -->
                <div id="spy-results-section" style="display: none;">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-spy-grid" data-bs-toggle="tab" href="#content-spy-grid" role="tab">
                                <i class="bi bi-grid"></i> Grid View
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-spy-watchlist" data-bs-toggle="tab" href="#content-spy-watchlist" role="tab">
                                <i class="bi bi-bookmark-star"></i> Watchlist
                                <span class="badge bg-primary" id="watchlist-count">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-spy-comparison" data-bs-toggle="tab" href="#content-spy-comparison" role="tab">
                                <i class="bi bi-bar-chart"></i> Comparação
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-spy-insights" data-bs-toggle="tab" href="#content-spy-insights" role="tab">
                                <i class="bi bi-lightbulb"></i> Insights
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-spy-alerts" data-bs-toggle="tab" href="#content-spy-alerts" role="tab">
                                <i class="bi bi-bell"></i> Alertas
                                <span class="badge bg-danger" id="alerts-count">0</span>
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Grid View -->
                        <div class="tab-pane fade show active" id="content-spy-grid" role="tabpanel">
                            <div id="spy-grid" class="row">
                                <!-- Preenchido dinamicamente -->
                            </div>
                        </div>

                        <!-- Watchlist -->
                        <div class="tab-pane fade" id="content-spy-watchlist" role="tabpanel">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <i class="bi bi-bookmark-star"></i> Concorrentes Monitorados
                                        </h6>
                                        <button class="btn btn-sm btn-light" onclick="CompetitorSpy.refreshWatchlist()">
                                            <i class="bi bi-arrow-clockwise"></i> Atualizar Todos
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="watchlist-container">
                                        <!-- Preenchido dinamicamente -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Comparação -->
                        <div class="tab-pane fade" id="content-spy-comparison" role="tabpanel">
                            <div class="card">
                                <div class="card-body">
                                    <div id="spy-comparison-table">
                                        <!-- Tabela comparativa -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Insights -->
                        <div class="tab-pane fade" id="content-spy-insights" role="tabpanel">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="insight-card">
                                        <div class="insight-icon">🔑</div>
                                        <h6>Keywords Vencedoras</h6>
                                        <div id="insights-keywords">Carregando...</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="insight-card">
                                        <div class="insight-icon">💰</div>
                                        <h6>Estratégia de Preço</h6>
                                        <div id="insights-pricing">Carregando...</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="insight-card">
                                        <div class="insight-icon">📦</div>
                                        <h6>Diferencias Competitivos</h6>
                                        <div id="insights-differentials">Carregando...</div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mt-3">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">💡 Ações Recomendadas</h6>
                                </div>
                                <div class="card-body">
                                    <div id="insights-actions">
                                        <!-- Lista de ações -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alertas -->
                        <div class="tab-pane fade" id="content-spy-alerts" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-primary active" onclick="CompetitorSpy.filterAlerts('all')">
                                            Todos
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="CompetitorSpy.filterAlerts('unread')">
                                            Não Lidos
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="CompetitorSpy.filterAlerts('high')">
                                            Alta Prioridade
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="alerts-container">
                                        <!-- Alertas dinâmicos -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div id="spy-empty-state" class="text-center py-5">
                    <i class="bi bi-binoculars" style="font-size: 64px; color: #ccc;"></i>
                    <h4 class="mt-3 text-muted">Pronto para Espionar?</h4>
                    <p class="text-muted">Digite um termo de busca ou escolha um produto para começar.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-outline-primary" onclick="CompetitorSpy.exportReport()" id="spy-export-btn" disabled>
                    <i class="bi bi-download"></i> Exportar Relatório
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Modal de Detalhes do Concorrente -->
<div class="modal fade" id="competitorDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Análise Detalhada do Concorrente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="competitor-detail-content">
                <!-- Preenchido dinamicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" onclick="CompetitorSpy.copyBestPractices()">
                    <i class="bi bi-clipboard-check"></i> Copiar Melhores Práticas
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .competitor-card {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 20px;
        transition: all 0.3s;
        cursor: pointer;
    }

    .competitor-card:hover {
        border-color: #2C3E50;
        box-shadow: 0 4px 12px rgba(44, 62, 80, 0.2);
        transform: translateY(-4px);
    }

    .competitor-card.top-performer {
        border-color: #F39C12;
        background: linear-gradient(135deg, #FFF9E6 0%, #FFFFFF 100%);
    }

    .competitor-card .badge-top {
        position: absolute;
        top: 10px;
        right: 10px;
        background: linear-gradient(135deg, #F39C12 0%, #E67E22 100%);
        color: white;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }

    .competitor-thumbnail {
        width: 100%;
        height: 180px;
        object-fit: contain;
        border-radius: 8px;
        background: #f8f9fa;
        padding: 10px;
    }

    .competitor-title {
        font-size: 14px;
        font-weight: 600;
        color: #333;
        margin: 10px 0;
        min-height: 42px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .competitor-stats {
        display: flex;
        justify-content: space-between;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #e0e0e0;
    }

    .stat-item {
        text-align: center;
        flex: 1;
    }

    .stat-label {
        font-size: 11px;
        color: #666;
        text-transform: uppercase;
    }

    .stat-value {
        font-size: 16px;
        font-weight: bold;
        color: #333;
    }

    .seo-score-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }

    .seo-score-badge.high {
        background: #E8F5E9;
        color: #27AE60;
    }

    .seo-score-badge.medium {
        background: #FFF3CD;
        color: #F39C12;
    }

    .seo-score-badge.low {
        background: #FADBD8;
        color: #E74C3C;
    }

    .comparison-table {
        width: 100%;
        border-collapse: collapse;
    }

    .comparison-table th {
        background: #f8f9fa;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
    }

    .comparison-table td {
        padding: 12px;
        border-bottom: 1px solid #e0e0e0;
    }

    .comparison-table .highlight-best {
        background: #E8F5E9;
        font-weight: 600;
    }

    .comparison-table .highlight-worst {
        background: #FADBD8;
    }

    .insight-card {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        text-align: center;
    }

    .insight-icon {
        font-size: 48px;
        margin-bottom: 10px;
    }

    .insight-card h6 {
        font-weight: 600;
        margin-bottom: 15px;
    }

    .action-item {
        background: #E8F5E9;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #27AE60;
        margin-bottom: 10px;
    }

    .action-item strong {
        color: #27AE60;
    }

    .keyword-chip {
        display: inline-block;
        background: #E3F2FD;
        color: #1976D2;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        margin: 3px;
    }

    .watchlist-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s;
        z-index: 10;
    }

    .watchlist-badge:hover {
        background: #FFF9E6;
        border-color: #F39C12;
        transform: scale(1.1);
    }

    .watchlist-badge.watched {
        background: #FFF9E6;
        border-color: #F39C12;
    }

    .watchlist-badge.watched i {
        color: #F39C12;
    }

    .timeline {
        position: relative;
        padding: 20px 0;
    }

    .timeline-item {
        position: relative;
        padding-left: 50px;
        padding-bottom: 20px;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: 18px;
        top: 0;
        bottom: -20px;
        width: 2px;
        background: #e0e0e0;
    }

    .timeline-item:last-child::before {
        display: none;
    }

    .timeline-badge {
        position: absolute;
        left: 0;
        top: 0;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .timeline-content {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 3px solid #0066FF;
    }

    .timeline-content h6 {
        text-transform: capitalize;
        color: #333;
        margin-bottom: 8px;
    }
</style>

<script nonce="<?= CSP_NONCE ?>">
    function normalizeExternalUrl(url) {
        if (!url || typeof url !== 'string') return '';
        const trimmed = url.trim();
        if (!trimmed) return '';
        if (trimmed.startsWith('data:') || trimmed.startsWith('blob:') || trimmed.startsWith('#')) return trimmed;
        if (trimmed.startsWith('//')) return `${window.location.protocol}${trimmed}`;
        if (trimmed.startsWith('http://')) return `https://${trimmed.slice('http://'.length)}`;
        return trimmed;
    }

    const CompetitorSpy = {
        competitors: [],
        myProduct: null,
        watchlist: [],
        alerts: [],

        init() {
            console.log('Competitor Spy initialized');
            this.loadProducts();
            this.loadWatchlist();
            this.loadAlerts();
        },

        async loadProducts() {
            try {
                const {
                    data
                } = await requestJson('/api/items?limit=100');

                if (data.results) {
                    const select = document.getElementById('spy-product-select');
                    select.innerHTML = '<option value="">Escolha um produto...</option>' +
                        data.results.map(item =>
                            `<option value="${item.id}">${item.title}</option>`
                        ).join('');
                }
            } catch (error) {
                console.error('Erro ao carregar produtos:', error);
            }
        },

        toggleMode() {
            const mode = document.getElementById('spy-mode').value;
            const e13Section = document.getElementById('e13-analysis-section');
            const e13Results = document.getElementById('e13-results-container');

            if (mode === 'search') {
                document.getElementById('spy-search-mode').style.display = 'block';
                document.getElementById('spy-product-mode').style.display = 'none';
                if (e13Section) e13Section.style.display = 'none';
                if (e13Results) e13Results.style.display = 'none';
            } else {
                document.getElementById('spy-search-mode').style.display = 'none';
                document.getElementById('spy-product-mode').style.display = 'block';
                if (e13Section) e13Section.style.display = 'block'; // Show E13 analysis option
            }
        },

        // Run E13 Competitor Benchmark Analysis
        async runE13Analysis() {
            const productId = document.getElementById('spy-product-select').value;
            if (!productId) {
                SEOKiller.showError('Selecione um produto para analisar');
                return;
            }

            const resultsContainer = document.getElementById('e13-results-container');
            const resultsDiv = document.getElementById('competitorAnalysisResults');

            if (resultsContainer) resultsContainer.style.display = 'block';
            if (resultsDiv) SEOKiller.showLoading(resultsDiv, 'Executando análise competitiva avançada...');

            // Call the SEOKiller function
            const result = await SEOKiller.analyzeCompetitors(productId);

            if (!result && resultsDiv) {
                resultsDiv.innerHTML = '<div class="alert alert-warning">Não foi possível obter dados de concorrência.</div>';
            }
        },

        async search() {
            const mode = document.getElementById('spy-mode').value;
            let searchTerm = '';
            let productId = '';

            if (mode === 'search') {
                searchTerm = document.getElementById('spy-search-term').value.trim();
                if (!searchTerm) {
                    SEOKiller.showError('Digite um termo de busca');
                    return;
                }
            } else {
                productId = document.getElementById('spy-product-select').value;
                if (!productId) {
                    SEOKiller.showError('Selecione um produto');
                    return;
                }
            }

            const limit = document.getElementById('spy-limit').value;
            const priceRange = document.getElementById('spy-price-range').value;
            const minSales = document.getElementById('spy-min-sales').value;

            document.getElementById('spy-empty-state').style.display = 'none';
            document.getElementById('spy-results-section').style.display = 'block';

            const gridContainer = document.getElementById('spy-grid');
            SEOKiller.showLoading(gridContainer, 'Espiando concorrentes...');

            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/spy', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        search_term: searchTerm,
                        product_id: productId,
                        limit: parseInt(limit),
                        price_range: priceRange,
                        min_sales: parseInt(minSales)
                    })
                });

                if (data.success && data.competitors) {
                    this.competitors = data.competitors;
                    this.myProduct = data.my_product || null;

                    this.renderGrid();
                    this.renderComparison();
                    this.renderInsights(data.insights || {});

                    document.getElementById('spy-export-btn').disabled = false;
                    SEOKiller.showSuccess(`${this.competitors.length} concorrentes encontrados!`);
                } else {
                    throw new Error(data.error || 'Nenhum concorrente encontrado');
                }
            } catch (error) {
                gridContainer.innerHTML = `
                <div class="col-12 text-center py-5">
                    <i class="bi bi-exclamation-triangle" style="font-size: 48px; color: #E74C3C;"></i>
                    <h5 class="mt-3">Erro ao buscar concorrentes</h5>
                    <p class="text-muted">${error.message}</p>
                </div>
            `;
            }
        },

        renderGrid() {
            const container = document.getElementById('spy-grid');

            if (this.competitors.length === 0) {
                container.innerHTML = '<div class="col-12"><p class="text-muted text-center">Nenhum concorrente encontrado.</p></div>';
                return;
            }

            container.innerHTML = this.competitors.map((comp, index) => {
                const isTop = index < 3;
                const scoreClass = comp.seo_score >= 80 ? 'high' : comp.seo_score >= 50 ? 'medium' : 'low';
                const isWatched = this.isInWatchlist(comp.id);

                return `
                <div class="col-md-3">
                    <div class="competitor-card ${isTop ? 'top-performer' : ''}" onclick="CompetitorSpy.showDetail('${comp.id}')">
                        ${isTop ? '<div class="badge-top">🏆 Top ${index + 1}</div>' : ''}
                        <div class="watchlist-badge ${isWatched ? 'watched' : ''}" onclick="event.stopPropagation(); CompetitorSpy.toggleWatchlist('${comp.id}')" title="${isWatched ? 'Remover da watchlist' : 'Adicionar à watchlist'}">
                            <i class="bi bi-bookmark${isWatched ? '-fill' : ''}"></i>
                        </div>
                        <img src="${normalizeExternalUrl(comp.thumbnail)}" alt="${comp.title}" class="competitor-thumbnail">
                        <div class="competitor-title">${comp.title}</div>
                        <div class="competitor-stats">
                            <div class="stat-item">
                                <div class="stat-label">Preço</div>
                                <div class="stat-value">R$ ${comp.price}</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Vendas</div>
                                <div class="stat-value">${comp.sold_quantity || 0}</div>
                            </div>
                        </div>
                        <div class="text-center mt-2">
                            <span class="seo-score-badge ${scoreClass}">SEO: ${comp.seo_score}/100</span>
                        </div>
                    </div>
                </div>
            `;
            }).join('');
        },

        renderComparison() {
            const container = document.getElementById('spy-comparison-table');

            if (this.competitors.length === 0) return;

            const avgCompetitor = this.calculateAverage();
            const topCompetitor = this.competitors[0];

            const metrics = [{
                    label: 'Título (chars)',
                    key: 'title_length'
                },
                {
                    label: 'Score SEO',
                    key: 'seo_score'
                },
                {
                    label: 'Nº de Imagens',
                    key: 'pictures_count'
                },
                {
                    label: 'Atributos Preenchidos',
                    key: 'attributes_filled'
                },
                {
                    label: 'Preço (R$)',
                    key: 'price'
                },
                {
                    label: 'Vendas',
                    key: 'sold_quantity'
                }
            ];

            let html = '<table class="comparison-table"><thead><tr>';
            html += '<th>Métrica</th>';
            if (this.myProduct) html += '<th>Seu Produto</th>';
            html += '<th>Média Concorrentes</th>';
            html += '<th>Top Concorrente</th>';
            html += '</tr></thead><tbody>';

            metrics.forEach(metric => {
                html += '<tr>';
                html += `<td><strong>${metric.label}</strong></td>`;

                if (this.myProduct) {
                    const myValue = this.myProduct[metric.key] || 0;
                    const isMyBest = this.isMyValueBest(metric.key, myValue);
                    html += `<td class="${isMyBest ? 'highlight-best' : ''}">${myValue}</td>`;
                }

                html += `<td>${avgCompetitor[metric.key]}</td>`;
                html += `<td class="highlight-best">${topCompetitor[metric.key] || 0}</td>`;
                html += '</tr>';
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        },

        calculateAverage() {
            const avg = {};
            const keys = ['title_length', 'seo_score', 'pictures_count', 'attributes_filled', 'price', 'sold_quantity'];

            keys.forEach(key => {
                const sum = this.competitors.reduce((acc, comp) => acc + (parseFloat(comp[key]) || 0), 0);
                avg[key] = Math.round(sum / this.competitors.length);
            });

            return avg;
        },

        isMyValueBest(key, myValue) {
            if (!this.myProduct) return false;

            const better = ['title_length', 'seo_score', 'pictures_count', 'attributes_filled', 'sold_quantity'];
            const worse = ['price'];

            const avg = this.calculateAverage()[key];

            if (better.includes(key)) {
                return myValue >= avg;
            } else if (worse.includes(key)) {
                return myValue <= avg;
            }

            return false;
        },

        renderInsights(insights) {
            // Keywords
            if (insights.top_keywords) {
                document.getElementById('insights-keywords').innerHTML =
                    insights.top_keywords.slice(0, 10).map(kw =>
                        `<span class="keyword-chip">${kw}</span>`
                    ).join('');
            }

            // Pricing
            if (insights.pricing_strategy) {
                document.getElementById('insights-pricing').innerHTML = `
                <p>Preço Médio: <strong>R$ ${insights.pricing_strategy.avg_price}</strong></p>
                <p>Faixa: R$ ${insights.pricing_strategy.min_price} - R$ ${insights.pricing_strategy.max_price}</p>
            `;
            }

            // Differentials
            if (insights.common_differentials) {
                document.getElementById('insights-differentials').innerHTML =
                    insights.common_differentials.slice(0, 5).map(diff =>
                        `<p>• ${diff}</p>`
                    ).join('');
            }

            // Actions
            if (insights.recommended_actions) {
                document.getElementById('insights-actions').innerHTML =
                    insights.recommended_actions.map(action => `
                    <div class="action-item">
                        <strong>${action.title}</strong>
                        <p class="mb-0">${action.description}</p>
                    </div>
                `).join('');
            }
        },

        currentDetailId: null,

        showDetail(competitorId) {
            this.currentDetailId = competitorId;
            const competitor = this.competitors.find(c => c.id === competitorId);
            if (!competitor) return;

            const modal = new bootstrap.Modal(document.getElementById('competitorDetailModal'));
            const content = document.getElementById('competitor-detail-content');

            content.innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <img src="${normalizeExternalUrl(competitor.thumbnail)}" class="img-fluid rounded mb-3">
                    <h5>${competitor.title}</h5>
                    <p class="text-muted">${competitor.sold_quantity || 0} vendas</p>
                </div>
                <div class="col-md-8">
                    <h6>📊 Análise SEO</h6>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Score SEO:</strong> ${competitor.seo_score}/100
                        </div>
                        <div class="col-6">
                            <strong>Título:</strong> ${competitor.title_length} chars
                        </div>
                        <div class="col-6">
                            <strong>Imagens:</strong> ${competitor.pictures_count}
                        </div>
                        <div class="col-6">
                            <strong>Atributos:</strong> ${competitor.attributes_filled}
                        </div>
                    </div>

                    <h6>🔑 Keywords Identificadas</h6>
                    <div class="mb-3">
                        ${competitor.keywords ? competitor.keywords.map(kw => `<span class="keyword-chip">${kw}</span>`).join('') : 'N/A'}
                    </div>

                    <h6>✨ Diferenciais</h6>
                    <ul>
                        ${competitor.differentials ? competitor.differentials.map(d => `<li>${d}</li>`).join('') : '<li>Nenhum diferencial detectado</li>'}
                    </ul>

                    <h6>💡 O que você pode copiar</h6>
                    <div class="alert alert-success">
                        <ul class="mb-0">
                            <li>Estrutura do título: ${competitor.title_structure || 'N/A'}</li>
                            <li>Keywords principais: ${competitor.main_keywords || 'N/A'}</li>
                            <li>Tipo de frete: ${competitor.shipping_type || 'N/A'}</li>
                        </ul>
                    </div>
                </div>
            </div>
        `;

            modal.show();
        },

        async copyBestPractices() {
            if (!this.currentDetailId || !this.myProduct?.id) {
                SEOKiller.showError('Erro: Item de origem ou destino não identificado');
                return;
            }

            const btn = document.querySelector('#competitorDetailModal .btn-primary');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Analisando...';

            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/spy/copy-strategy', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        competitor_id: this.currentDetailId,
                        my_item_id: this.myProduct.id // Needs to be populated by search()
                    })
                });

                if (data.error) throw new Error(data.error);

                // Show Preview Modal (reuse history modal logic or generic modal)
                this.showCopyPreview(data);

            } catch (error) {
                SEOKiller.showError('Erro ao analisar: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        },

        showCopyPreview(data) {
            // Close detail modal slightly to show this on top or just stack
            // Bootstrap modals stack fine if configured.

            const html = `
            <div class="modal fade" id="copyPreviewModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">📋 Aplicar Estratégia</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Selecione o que deseja copiar deste concorrente:</p>
                            
                            <form id="copyStrategyForm">
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="copy-title" checked>
                                    <label class="form-check-label" for="copy-title">
                                        <strong>Título Sugerido</strong><br>
                                        <small class="text-muted">De: ${data.my_current_title}</small><br>
                                        <span class="text-success">Para: ${data.suggested_title}</span>
                                    </label>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><strong>Keywords Exclusivas (${data.keywords_to_copy.length})</strong></label>
                                    <div class="border rounded p-2 bg-light">
                                        ${data.keywords_to_copy.map(kw => `<span class="badge bg-secondary me-1">${kw}</span>`).join('')}
                                    </div>
                                    <div class="form-check mt-2">
                                        <input type="checkbox" class="form-check-input" id="copy-keywords" checked>
                                        <label class="form-check-label" for="copy-keywords">
                                            Adicionar estas keywords onde possível (Título/Ficha)
                                        </label>
                                    </div>
                                </div>
                            </form>
                            
                            <div class="alert alert-warning small">
                                <i class="bi bi-exclamation-triangle"></i> Atenção: Isso alterará seu anúncio no Mercado Livre imediatamente após confirmação.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-success" onclick="CompetitorSpy.applyStrategy('${data.suggested_title}')">
                                <i class="bi bi-check-lg"></i> Aplicar Selecionados
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

            document.body.insertAdjacentHTML('beforeend', html);
            const modal = new bootstrap.Modal(document.getElementById('copyPreviewModal'));
            modal.show();

            document.getElementById('copyPreviewModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        },

        async applyStrategy(newTitle) {
            // This relies on existing logic or new one.
            // For title, we can call SEOKiller.updateItem?
            // Let's assume there is a generic update or we implement one here.
            // Actually, AttributeFiller updates via API call too.

            const copyTitle = document.getElementById('copy-title').checked;
            // Keywords logic: in real app, we'd append to attributes or title logic.
            // For MVP, we will update Title if selected.

            if (!copyTitle) {
                alert('Nenhuma alteração selecionada.');
                return;
            }

            const btn = document.querySelector('#copyPreviewModal .btn-success');
            btn.disabled = true;
            btn.innerHTML = 'Aplicando...';

            try {
                const {
                    data: resData
                } = await requestJson('/api/seo-killer/item/update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        item_id: this.myProduct.id,
                        title: newTitle
                    })
                });
                if (resData.success) {
                    SEOKiller.showSuccess('Título atualizado com sucesso!');
                    bootstrap.Modal.getInstance(document.getElementById('copyPreviewModal')).hide();
                    bootstrap.Modal.getInstance(document.getElementById('competitorDetailModal')).hide();
                } else {
                    throw new Error(resData.error || 'Falha ao atualizar');
                }

            } catch (e) {
                SEOKiller.showError('Erro: ' + e.message);
            } finally {
                // cleanup
            }
        },

        exportReport() {
            if (this.competitors.length === 0) return;

            const csv = this.generateCSV();
            const blob = new Blob([csv], {
                type: 'text/csv'
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `competitor_analysis_${Date.now()}.csv`;
            a.click();

            SEOKiller.showSuccess('Relatório exportado!');
        },

        generateCSV() {
            let csv = 'Título,Preço,Vendas,Score SEO,Imagens,Atributos\n';

            this.competitors.forEach(comp => {
                csv += `"${comp.title}",${comp.price},${comp.sold_quantity || 0},${comp.seo_score},${comp.pictures_count},${comp.attributes_filled}\n`;
            });

            return csv;
        },

        // ==========================================
        // 🔖 WATCHLIST METHODS
        // ==========================================

        async loadWatchlist() {
            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/watchlist');

                if (data.success) {
                    this.watchlist = data.watchlist || [];
                    document.getElementById('watchlist-count').textContent = this.watchlist.length;
                    this.renderWatchlist();
                }
            } catch (error) {
                console.error('Erro ao carregar watchlist:', error);
            }
        },

        async toggleWatchlist(competitorItemId) {
            const isWatched = this.isInWatchlist(competitorItemId);

            try {
                if (isWatched) {
                    // Remove from watchlist
                    const watchlistItem = this.watchlist.find(w => w.competitor_item_id === competitorItemId);
                    if (!watchlistItem) return;

                    const {
                        data
                    } = await requestJson(`/api/seo-killer/watchlist/${watchlistItem.id}`, {
                        method: 'DELETE'
                    });

                    if (data.success) {
                        SEOKiller.showSuccess('Removido da watchlist');
                        this.loadWatchlist();
                        this.renderGrid();
                    }
                } else {
                    // Add to watchlist
                    const {
                        data
                    } = await requestJson('/api/seo-killer/watchlist', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            competitor_item_id: competitorItemId,
                            alert_on_changes: true
                        })
                    });

                    if (data.success) {
                        SEOKiller.showSuccess('Adicionado à watchlist');
                        this.loadWatchlist();
                        this.renderGrid();
                    }
                }
            } catch (error) {
                SEOKiller.showError('Erro ao atualizar watchlist');
            }
        },

        isInWatchlist(competitorItemId) {
            return this.watchlist.some(w => w.competitor_item_id === competitorItemId);
        },

        renderWatchlist() {
            const container = document.getElementById('watchlist-container');

            if (this.watchlist.length === 0) {
                container.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bookmark" style="font-size: 48px;"></i>
                    <p class="mt-3">Nenhum concorrente na watchlist</p>
                    <p class="small">Adicione concorrentes clicando no ícone de bookmark na busca</p>
                </div>
            `;
                return;
            }

            container.innerHTML = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Preço</th>
                            <th>Vendas</th>
                            <th>Score SEO</th>
                            <th>Última Verificação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${this.watchlist.map(item => {
                            const scoreClass = item.seo_score >= 80 ? 'success' : item.seo_score >= 50 ? 'warning' : 'danger';
                            const lastChecked = item.last_checked_at ? new Date(item.last_checked_at).toLocaleString('pt-BR') : 'Nunca';
                            
                            return `
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="ms-2">
                                                <div class="fw-bold text-truncate" style="max-width: 300px;" title="${item.title}">
                                                    ${item.title}
                                                </div>
                                                <small class="text-muted">${item.competitor_item_id}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><strong>R$ ${item.price}</strong></td>
                                    <td>${item.sold_quantity}</td>
                                    <td>
                                        <span class="badge bg-${scoreClass}">${item.seo_score}/100</span>
                                    </td>
                                    <td>
                                        <small>${lastChecked}</small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="CompetitorSpy.updateWatchlistItem(${item.id})" title="Atualizar">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="CompetitorSpy.viewHistory(${item.id})" title="Histórico">
                                                <i class="bi bi-clock-history"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="CompetitorSpy.removeFromWatchlist(${item.id})" title="Remover">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
        },

        async updateWatchlistItem(watchlistId) {
            try {
                SEOKiller.showLoading(document.getElementById('watchlist-container'), 'Atualizando...');

                const {
                    data
                } = await requestJson(`/api/seo-killer/watchlist/${watchlistId}/update`, {
                    method: 'POST'
                });

                if (data.success) {
                    SEOKiller.showSuccess(`${data.changes_detected} mudança(s) detectada(s)`);
                    this.loadWatchlist();
                    this.loadAlerts();
                } else {
                    throw new Error(data.error);
                }
            } catch (error) {
                SEOKiller.showError('Erro ao atualizar: ' + error.message);
                this.renderWatchlist();
            }
        },

        async refreshWatchlist() {
            if (this.watchlist.length === 0) {
                SEOKiller.showError('Nenhum item na watchlist');
                return;
            }

            SEOKiller.showLoading(document.getElementById('watchlist-container'), 'Atualizando todos os itens...');

            let updated = 0;
            for (const item of this.watchlist) {
                try {
                    await this.updateWatchlistItem(item.id);
                    updated++;
                } catch (error) {
                    console.error(`Erro ao atualizar ${item.id}:`, error);
                }
            }

            SEOKiller.showSuccess(`${updated} itens atualizados`);
        },

        async removeFromWatchlist(watchlistId) {
            if (!confirm('Remover este concorrente da watchlist?')) return;

            try {
                const {
                    data
                } = await requestJson(`/api/seo-killer/watchlist/${watchlistId}`, {
                    method: 'DELETE'
                });

                if (data.success) {
                    SEOKiller.showSuccess('Removido da watchlist');
                    this.loadWatchlist();
                }
            } catch (error) {
                SEOKiller.showError('Erro ao remover');
            }
        },

        async viewHistory(watchlistId) {
            try {
                const {
                    data
                } = await requestJson(`/api/seo-killer/watchlist/${watchlistId}/history?days=30`);

                if (data.success && data.history) {
                    this.showHistoryModal(data.history);
                }
            } catch (error) {
                SEOKiller.showError('Erro ao carregar histórico');
            }
        },

        showHistoryModal(history) {
            if (history.length === 0) {
                alert('Nenhuma mudança detectada nos últimos 30 dias');
                return;
            }

            const html = `
            <div class="modal fade" id="historyModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Histórico de Mudanças</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="timeline">
                                ${history.map(h => `
                                    <div class="timeline-item">
                                        <div class="timeline-badge bg-primary">
                                            <i class="bi bi-arrow-left-right"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h6 class="mb-1">${h.field_changed}</h6>
                                            <p class="mb-1">
                                                <span class="text-danger">${h.old_value}</span>
                                                <i class="bi bi-arrow-right"></i>
                                                <span class="text-success">${h.new_value}</span>
                                            </p>
                                            <small class="text-muted">${new Date(h.detected_at).toLocaleString('pt-BR')}</small>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

            document.body.insertAdjacentHTML('beforeend', html);
            const modal = new bootstrap.Modal(document.getElementById('historyModal'));
            modal.show();

            document.getElementById('historyModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        },

        // ==========================================
        // 🔔 ALERTS METHODS
        // ==========================================

        async loadAlerts() {
            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/alerts?status=unread&limit=50');

                if (data.success) {
                    this.alerts = data.alerts || [];
                    const unreadCount = this.alerts.filter(a => a.status === 'unread').length;
                    document.getElementById('alerts-count').textContent = unreadCount;
                    this.renderAlerts();
                }
            } catch (error) {
                console.error('Erro ao carregar alertas:', error);
            }
        },

        async filterAlerts(filter) {
            let filtered = this.alerts;

            if (filter === 'unread') {
                filtered = this.alerts.filter(a => a.status === 'unread');
            } else if (filter === 'high') {
                filtered = this.alerts.filter(a => a.priority === 'high');
            }

            this.renderAlerts(filtered);
        },

        renderAlerts(alerts = this.alerts) {
            const container = document.getElementById('alerts-container');

            if (alerts.length === 0) {
                container.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bell-slash" style="font-size: 48px;"></i>
                    <p class="mt-3">Nenhum alerta</p>
                </div>
            `;
                return;
            }

            container.innerHTML = alerts.map(alert => {
                const priorityClass = {
                    'high': 'danger',
                    'medium': 'warning',
                    'low': 'info'
                } [alert.priority] || 'secondary';

                const priorityIcon = {
                    'high': 'exclamation-triangle-fill',
                    'medium': 'exclamation-circle',
                    'low': 'info-circle'
                } [alert.priority] || 'bell';

                return `
                <div class="alert alert-${priorityClass} alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-${priorityIcon} me-2" style="font-size: 24px;"></i>
                        <div class="flex-grow-1">
                            <h6 class="alert-heading mb-1">${alert.title}</h6>
                            <p class="mb-1">${alert.message}</p>
                            <small class="text-muted">
                                ${new Date(alert.created_at).toLocaleString('pt-BR')}
                            </small>
                        </div>
                        ${alert.status === 'unread' ? `
                            <button type="button" class="btn btn-sm btn-outline-${priorityClass}" onclick="CompetitorSpy.markAlertAsRead(${alert.id})">
                                <i class="bi bi-check"></i> Marcar Lido
                            </button>
                        ` : ''}
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            }).join('');
        },

        async markAlertAsRead(alertId) {
            try {
                const {
                    data
                } = await requestJson(`/api/seo-killer/alerts/${alertId}/read`, {
                    method: 'POST'
                });

                if (data.success) {
                    this.loadAlerts();
                }
            } catch (error) {
                console.error('Erro ao marcar alerta:', error);
            }
        }
    };

    // Função global para abrir o modal
    window.openCompetitorSpy = function() {
        const modal = new bootstrap.Modal(document.getElementById('competitorSpyModal'));
        modal.show();
        CompetitorSpy.init();
    };
</script>