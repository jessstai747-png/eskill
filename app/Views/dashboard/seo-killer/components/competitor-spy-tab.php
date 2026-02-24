<!-- Competitor Spy Tab -->
<div class="tab-pane fade" id="competitor-spy" role="tabpanel" aria-labelledby="competitor-spy-tab">
    <div class="container-fluid py-4">
        <div class="row mb-4 align-items-center">
            <div class="col-lg-8">
                <h2 class="mb-1">
                    <i class="bi bi-binoculars text-primary me-2"></i>
                    Espião de Concorrentes
                </h2>
                <p class="text-muted mb-0">Compare seu anúncio com os líderes da categoria e descubra oportunidades de melhoria imediata.</p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <div class="btn-group me-2" role="group">
                    <button class="btn btn-outline-secondary" id="spy-export-csv-btn" onclick="CompetitorSpy.exportReport('csv')" disabled>
                        <i class="bi bi-download"></i> Exportar CSV
                    </button>
                    <button class="btn btn-outline-secondary" id="spy-export-pdf-btn" onclick="CompetitorSpy.exportReport('pdf')" disabled>
                        <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
                    </button>
                </div>
                <button class="btn btn-success" id="spy-copy-btn" onclick="CompetitorSpy.copyBestPractices()" disabled>
                    <i class="bi bi-stars"></i> Aplicar Melhores Práticas
                </button>
            </div>
        </div>

        <!-- Busca -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Modo de Busca</label>
                        <select class="form-select" id="spy-mode" onchange="CompetitorSpy.toggleMode()">
                            <option value="search">Por Termo de Busca</option>
                            <option value="product">Baseado no Meu Produto</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <div id="spy-search-mode">
                            <label class="form-label fw-bold">Termo de Busca</label>
                            <input type="text" class="form-control" id="spy-search-term" placeholder="Ex: notebook gamer, tênis corrida...">
                        </div>
                        <div id="spy-product-mode" style="display: none;">
                            <label class="form-label fw-bold">Seu Produto</label>
                            <select class="form-select" id="spy-product-select">
                                <option value="">Escolha um produto...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row mt-3 g-3">
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
                    <div class="col-md-3 text-md-end">
                        <button class="btn btn-primary w-100" onclick="CompetitorSpy.search()">
                            <i class="bi bi-search"></i> Espionar Agora
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resultados -->
        <div id="spy-results-section" style="display: none;">
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="tab-spy-grid" data-bs-toggle="tab" href="#content-spy-grid" role="tab">
                        <i class="bi bi-grid"></i> Grid View
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-spy-watchlist" data-bs-toggle="tab" href="#content-spy-watchlist" role="tab">
                        <i class="bi bi-bookmark-star"></i> Watchlist
                        <span class="badge bg-primary ms-1" id="watchlist-count">0</span>
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
                        <span class="badge bg-danger ms-1" id="alerts-count">0</span>
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="content-spy-grid" role="tabpanel">
                    <div id="spy-grid" class="row"></div>
                </div>
                <div class="tab-pane fade" id="content-spy-watchlist" role="tabpanel">
                    <div class="card">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-bookmark-star me-2"></i> Concorrentes Monitorados</h6>
                            <button class="btn btn-sm btn-light" onclick="CompetitorSpy.refreshWatchlist()">
                                <i class="bi bi-arrow-clockwise"></i> Atualizar Todos
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="watchlist-container"></div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="content-spy-comparison" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <div id="spy-comparison-table"></div>
                        </div>
                    </div>
                </div>
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
                                <h6>Diferenciais</h6>
                                <div id="insights-differentials">Carregando...</div>
                            </div>
                        </div>
                    </div>
                    <div class="card mt-3">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">💡 Ações Recomendadas</h6>
                        </div>
                        <div class="card-body">
                            <div id="insights-actions"></div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="content-spy-alerts" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary active" data-filter="all" onclick="CompetitorSpy.filterAlerts('all')">Todos</button>
                                <button type="button" class="btn btn-outline-danger" data-filter="unread" onclick="CompetitorSpy.filterAlerts('unread')">Não Lidos</button>
                                <button type="button" class="btn btn-outline-warning" data-filter="high" onclick="CompetitorSpy.filterAlerts('high')">Alta Prioridade</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="alerts-container"></div>
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
</div>

<!-- Modal de Detalhes do Concorrente -->
<div class="modal fade" id="competitorDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Análise Detalhada do Concorrente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="competitor-detail-content"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" onclick="CompetitorSpy.copyBestPractices()">
                    <i class="bi bi-stars"></i> Copiar Melhores Práticas
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
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
        color: #27AE60;
    }

    .comparison-table .highlight-avg {
        background: #FFF3E0;
        font-weight: 500;
        color: #F39C12;
    }

    .comparison-table .highlight-worst {
        background: #FFEBEE;
        font-weight: 500;
        color: #E74C3C;
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

    .action-item {
        background: #E8F5E9;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #27AE60;
        margin-bottom: 10px;
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

    .reputation-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #27AE60;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
        z-index: 10;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
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

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
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
        initialized: false,
        alertFilter: 'all',

        init() {
            if (!this.initialized) {
                this.initialized = true;
                this.loadProducts();
            }

            this.toggleMode();
            this.loadWatchlist();
            this.loadAlerts();
        },

        async loadProducts() {
            try {
                const {
                    data
                } = await requestJson('/api/items?limit=100');
                const select = document.getElementById('spy-product-select');
                if (!select) return;

                if (data.results) {
                    select.innerHTML = '<option value="">Escolha um produto...</option>' +
                        data.results.map(item => `<option value="${item.id}">${item.title}</option>`).join('');
                }
            } catch (error) {
                console.error('Erro ao carregar produtos:', error);
            }
        },

        toggleMode() {
            const modeSelect = document.getElementById('spy-mode');
            if (!modeSelect) return;
            const mode = modeSelect.value;
            const searchMode = document.getElementById('spy-search-mode');
            const productMode = document.getElementById('spy-product-mode');

            if (mode === 'search') {
                if (searchMode) searchMode.style.display = 'block';
                if (productMode) productMode.style.display = 'none';
            } else {
                if (searchMode) searchMode.style.display = 'none';
                if (productMode) productMode.style.display = 'block';
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
            this.updateResultActionState(false);

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

                    const hasResults = this.competitors.length > 0;
                    this.updateResultActionState(hasResults);

                    SEOKiller.showSuccess(`${this.competitors.length} concorrentes encontrados!`);
                } else {
                    throw new Error(data.error || 'Nenhum concorrente encontrado');
                }
            } catch (error) {
                this.updateResultActionState(false);
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
                const reputation = comp.seller_reputation || 0;
                const reputationInfo = this.getReputationInfo(reputation);

                return `
                <div class="col-md-3">
                    <div class="competitor-card ${isTop ? 'top-performer' : ''}" onclick="CompetitorSpy.showDetail('${comp.id}')">
                        ${isTop ? '<div class="badge-top">🏆 Top ' + (index + 1) + '</div>' : ''}
                        <div class="watchlist-badge ${isWatched ? 'watched' : ''}" onclick="event.stopPropagation(); CompetitorSpy.toggleWatchlist('${comp.id}')" title="${isWatched ? 'Remover da watchlist' : 'Adicionar à watchlist'}">
                            <i class="bi bi-bookmark${isWatched ? '-fill' : ''}"></i>
                        </div>
                        <div class="reputation-badge" style="background: ${reputationInfo.color};" title="Reputação: ${reputation}%">
                            <i class="bi ${reputationInfo.icon}"></i>
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
                            <div class="stat-item">
                                <div class="stat-label">Frete</div>
                                <div class="stat-value">${comp.free_shipping ? '<span class="text-success">Grátis</span>' : 'Pago'}</div>
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

        getReputationInfo(reputation) {
            if (reputation >= 90) return {
                color: '#27AE60',
                icon: 'bi-star-fill',
                label: 'Excelente'
            };
            if (reputation >= 70) return {
                color: '#F39C12',
                icon: 'bi-star-half',
                label: 'Bom'
            };
            if (reputation >= 50) return {
                color: '#E67E22',
                icon: 'bi-star',
                label: 'Regular'
            };
            return {
                color: '#E74C3C',
                icon: 'bi-star',
                label: 'Baixa'
            };
        },

        renderComparison() {
            const container = document.getElementById('spy-comparison-table');
            if (this.competitors.length === 0) return;

            const avgCompetitor = this.calculateAverage();
            const topCompetitor = this.competitors[0];

            // higherIsBetter: true = maior é melhor, false = menor é melhor
            const metrics = [{
                    label: 'Título (chars)',
                    key: 'title_length',
                    higherIsBetter: true,
                    ideal: {
                        min: 45,
                        max: 60
                    }
                },
                {
                    label: 'Score SEO',
                    key: 'seo_score',
                    higherIsBetter: true
                },
                {
                    label: 'Nº de Imagens',
                    key: 'pictures_count',
                    higherIsBetter: true,
                    ideal: {
                        min: 6
                    }
                },
                {
                    label: 'Atributos Preenchidos',
                    key: 'attributes_filled',
                    higherIsBetter: true
                },
                {
                    label: 'Preço (R$)',
                    key: 'price',
                    higherIsBetter: false
                },
                {
                    label: 'Vendas',
                    key: 'sold_quantity',
                    higherIsBetter: true
                },
                {
                    label: 'Reputação Vendedor',
                    key: 'seller_reputation',
                    higherIsBetter: true
                },
                {
                    label: 'Frete Grátis',
                    key: 'free_shipping',
                    higherIsBetter: true,
                    isBoolean: true
                }
            ];

            let html = '<table class="comparison-table"><thead><tr>';
            html += '<th>Métrica</th>';
            if (this.myProduct) html += '<th>Seu Produto</th>';
            html += '<th>Média Concorrentes</th><th>Top Concorrente</th></tr></thead><tbody>';

            metrics.forEach(metric => {
                html += '<tr>';
                html += `<td><strong>${metric.label}</strong></td>`;

                const avgValue = avgCompetitor[metric.key] || 0;
                const topValue = topCompetitor[metric.key] || 0;

                if (this.myProduct) {
                    const myValue = this.myProduct[metric.key] || 0;
                    const displayValue = metric.isBoolean ? (myValue ? 'Sim' : 'Não') : myValue;
                    const status = this.getComparisonStatus(myValue, avgValue, topValue, metric);
                    html += `<td class="${status.class}">${displayValue} ${status.icon}</td>`;
                }

                const avgDisplay = metric.isBoolean ? (avgValue >= 0.5 ? 'Sim' : 'Não') : avgValue;
                const topDisplay = metric.isBoolean ? (topValue ? 'Sim' : 'Não') : topValue;

                html += `<td>${avgDisplay}</td>`;
                html += `<td class="highlight-best">${topDisplay}</td>`;
                html += '</tr>';
            });

            html += '</tbody></table>';

            // Legenda
            html += `
            <div class="mt-3 d-flex gap-3 text-muted small">
                <span><span class="text-success">✅</span> Acima da média</span>
                <span><span class="text-warning">⚠️</span> Na média</span>
                <span><span class="text-danger">❌</span> Abaixo da média</span>
            </div>
        `;

            container.innerHTML = html;
        },

        getComparisonStatus(myValue, avgValue, topValue, metric) {
            const higherIsBetter = metric.higherIsBetter !== false;

            if (higherIsBetter) {
                if (myValue >= topValue) {
                    return {
                        class: 'highlight-best',
                        icon: '✅'
                    };
                } else if (myValue >= avgValue) {
                    return {
                        class: 'highlight-avg',
                        icon: '⚠️'
                    };
                } else {
                    return {
                        class: 'highlight-worst',
                        icon: '❌'
                    };
                }
            } else {
                // Para preço, menor é melhor
                if (myValue <= topValue) {
                    return {
                        class: 'highlight-best',
                        icon: '✅'
                    };
                } else if (myValue <= avgValue) {
                    return {
                        class: 'highlight-avg',
                        icon: '⚠️'
                    };
                } else {
                    return {
                        class: 'highlight-worst',
                        icon: '❌'
                    };
                }
            }
        },

        calculateAverage() {
            const avg = {};
            const keys = ['title_length', 'seo_score', 'pictures_count', 'attributes_filled', 'price', 'sold_quantity'];

            keys.forEach(key => {
                const sum = this.competitors.reduce((acc, comp) => acc + (parseFloat(comp[key]) || 0), 0);
                avg[key] = this.competitors.length ? Math.round(sum / this.competitors.length) : 0;
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
            if (insights.top_keywords) {
                document.getElementById('insights-keywords').innerHTML =
                    insights.top_keywords.slice(0, 10).map(kw => `<span class="keyword-chip">${kw}</span>`).join('');
            }

            if (insights.pricing_strategy) {
                document.getElementById('insights-pricing').innerHTML = `
                <p>Preço Médio: <strong>R$ ${insights.pricing_strategy.avg_price}</strong></p>
                <p>Faixa: R$ ${insights.pricing_strategy.min_price} - R$ ${insights.pricing_strategy.max_price}</p>
            `;
            }

            if (insights.common_differentials) {
                document.getElementById('insights-differentials').innerHTML =
                    insights.common_differentials.slice(0, 5).map(diff => `<p>• ${diff}</p>`).join('');
            }

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

        async showDetail(competitorId) {
            const competitor = this.competitors.find(c => c.id === competitorId);
            if (!competitor) return;

            const modal = new bootstrap.Modal(document.getElementById('competitorDetailModal'));
            const content = document.getElementById('competitor-detail-content');
            const reputationInfo = this.getReputationInfo(competitor.seller_reputation || 0);

            // Calcular keywords gap com meu produto (se existir)
            let keywordsGapHtml = '';
            if (this.myProduct && competitor.keywords) {
                const myKeywords = this.myProduct.keywords || [];
                const compKeywords = competitor.keywords || [];
                const missingKeywords = compKeywords.filter(kw => !myKeywords.includes(kw));
                const commonKeywords = compKeywords.filter(kw => myKeywords.includes(kw));

                keywordsGapHtml = `
                <h6 class="mt-3">🔍 Gap de Keywords</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="p-2 rounded" style="background: #FFEBEE;">
                            <strong class="text-danger">Keywords que você não tem (${missingKeywords.length}):</strong>
                            <div class="mt-2">
                                ${missingKeywords.length > 0 ? missingKeywords.map(kw => `<span class="keyword-chip" style="background: #FFCDD2; color: #C62828;">${kw}</span>`).join('') : '<em class="text-muted">Nenhuma</em>'}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-2 rounded" style="background: #E8F5E9;">
                            <strong class="text-success">Keywords em comum (${commonKeywords.length}):</strong>
                            <div class="mt-2">
                                ${commonKeywords.length > 0 ? commonKeywords.map(kw => `<span class="keyword-chip" style="background: #C8E6C9; color: #2E7D32;">${kw}</span>`).join('') : '<em class="text-muted">Nenhuma</em>'}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            }

            content.innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <img src="${normalizeExternalUrl(competitor.thumbnail)}" class="img-fluid rounded mb-3">
                    <h5>${competitor.title}</h5>
                    <p class="text-muted">${competitor.sold_quantity || 0} vendas</p>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge" style="background: ${reputationInfo.color};">
                            <i class="bi ${reputationInfo.icon}"></i> ${reputationInfo.label}
                        </span>
                        <small class="text-muted">${competitor.seller_reputation || 0}% reputação</small>
                    </div>
                </div>
                <div class="col-md-8">
                    <h6>📊 Análise SEO</h6>
                    <div class="row mb-3">
                        <div class="col-6"><strong>Score SEO:</strong> ${competitor.seo_score}/100</div>
                        <div class="col-6"><strong>Título:</strong> ${competitor.title_length} chars</div>
                        <div class="col-6"><strong>Imagens:</strong> ${competitor.pictures_count}</div>
                        <div class="col-6"><strong>Atributos:</strong> ${competitor.attributes_filled}</div>
                    </div>

                    <h6>🚚 Informações de Envio</h6>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Frete Grátis:</strong>
                            ${competitor.free_shipping ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Sim</span>' : '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Não</span>'}
                        </div>
                        <div class="col-6">
                            <strong>Tipo:</strong>
                            <span class="badge bg-${competitor.shipping_type === 'fulfillment' ? 'success' : 'secondary'}">${competitor.shipping_type === 'fulfillment' ? 'Full' : competitor.shipping_type || 'Padrão'}</span>
                        </div>
                        <div class="col-6"><strong>Localização:</strong> ${competitor.seller_city || 'N/A'}, ${competitor.seller_state || 'N/A'}</div>
                        <div class="col-6"><strong>Prazo médio:</strong> ${competitor.shipping_days || 'N/A'} dias</div>
                    </div>

                    <h6>👤 Informações do Vendedor</h6>
                    <div class="row mb-3">
                        <div class="col-6"><strong>Nível:</strong> ${competitor.seller_level || 'N/A'}</div>
                        <div class="col-6"><strong>MercadoLíder:</strong> ${competitor.mercado_lider ? '<span class="text-success"><i class="bi bi-patch-check-fill"></i> Sim</span>' : 'Não'}</div>
                        <div class="col-6"><strong>Vendas totais:</strong> ${competitor.seller_total_sales || 'N/A'}</div>
                        <div class="col-6"><strong>Avaliações:</strong> ${competitor.seller_ratings || 'N/A'}</div>
                    </div>

                    <h6>🔑 Keywords Identificadas</h6>
                    <div class="mb-3">
                        ${competitor.keywords ? competitor.keywords.map(kw => `<span class="keyword-chip">${kw}</span>`).join('') : 'N/A'}
                    </div>

                    ${keywordsGapHtml}

                    <h6 class="mt-3">✨ Diferenciais</h6>
                    <ul>
                        ${competitor.differentials ? competitor.differentials.map(d => `<li>${d}</li>`).join('') : '<li>Nenhum diferencial detectado</li>'}
                    </ul>

                    <h6>💡 O que você pode copiar</h6>
                    <div class="alert alert-success">
                        <ul class="mb-0">
                            <li>Estrutura do título: ${competitor.title_structure || 'N/A'}</li>
                            <li>Keywords principais: ${competitor.main_keywords || 'N/A'}</li>
                            <li>Tipo de frete: ${competitor.shipping_type || 'N/A'}</li>
                            ${competitor.free_shipping && !this.myProduct?.free_shipping ? '<li class="text-warning"><strong>Considere oferecer frete grátis!</strong></li>' : ''}
                            ${competitor.mercado_lider && !this.myProduct?.mercado_lider ? '<li class="text-info">Vendedor é MercadoLíder - maior visibilidade</li>' : ''}
                        </ul>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <a href="${ML.itemUrl(competitor.id)}" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-box-arrow-up-right"></i> Ver no ML
                        </a>
                        <button class="btn btn-outline-success btn-sm" onclick="CompetitorSpy.toggleWatchlist('${competitor.id}')">
                            <i class="bi bi-bookmark${this.isInWatchlist(competitor.id) ? '-fill' : ''}"></i>
                            ${this.isInWatchlist(competitor.id) ? 'Na Watchlist' : 'Adicionar à Watchlist'}
                        </button>
                    </div>
                </div>
            </div>
        `;

            modal.show();
        },

        copyBestPractices() {
            if (this.competitors.length === 0) {
                SEOKiller.showInfo('Execute uma busca primeiro!');
                return;
            }

            if (!this.myProduct) {
                SEOKiller.showInfo('Selecione um produto seu para comparar.');
                return;
            }

            this.showBestPracticesModal();
        },

        showBestPracticesModal() {
            const top = this.competitors[0];
            const avgCompetitor = this.calculateAverage();
            const myProduct = this.myProduct;

            // Identificar gaps e oportunidades
            const opportunities = [];

            // Análise de título
            if ((myProduct.title_length || 0) < (avgCompetitor.title_length || 50)) {
                opportunities.push({
                    type: 'title',
                    icon: 'bi-fonts',
                    priority: 'high',
                    title: 'Otimizar Título',
                    detail: `Seu título tem ${myProduct.title_length || 0} caracteres. A média é ${avgCompetitor.title_length} e o top tem ${top.title_length}.`,
                    action: 'openTitleGenerator',
                    actionLabel: 'Gerar Título Otimizado'
                });
            }

            // Análise de imagens
            if ((myProduct.pictures_count || 0) < 6) {
                opportunities.push({
                    type: 'images',
                    icon: 'bi-images',
                    priority: 'high',
                    title: 'Adicionar Mais Imagens',
                    detail: `Você tem ${myProduct.pictures_count || 0} imagens. O recomendado é pelo menos 6. O top tem ${top.pictures_count || 0}.`,
                    action: 'openImageAnalyzer',
                    actionLabel: 'Analisar Imagens'
                });
            }

            // Análise de atributos
            if ((myProduct.attributes_filled || 0) < (avgCompetitor.attributes_filled || 10)) {
                opportunities.push({
                    type: 'attributes',
                    icon: 'bi-list-check',
                    priority: 'medium',
                    title: 'Preencher Mais Atributos',
                    detail: `Você tem ${myProduct.attributes_filled || 0} atributos. A média é ${avgCompetitor.attributes_filled}. Atributos melhoram o ranking!`,
                    action: 'openAttributeFiller',
                    actionLabel: 'Preencher Atributos'
                });
            }

            // Análise de frete
            if (!myProduct.free_shipping && (avgCompetitor.free_shipping || 0) > 0.5) {
                opportunities.push({
                    type: 'shipping',
                    icon: 'bi-truck',
                    priority: 'high',
                    title: 'Oferecer Frete Grátis',
                    detail: `${Math.round((avgCompetitor.free_shipping || 0) * 100)}% dos concorrentes oferecem frete grátis. Isso impacta muito o ranking!`,
                    action: null,
                    actionLabel: null
                });
            }

            // Análise de preço
            if ((myProduct.price || 0) > (avgCompetitor.price || 0) * 1.2) {
                opportunities.push({
                    type: 'price',
                    icon: 'bi-currency-dollar',
                    priority: 'medium',
                    title: 'Revisar Estratégia de Preço',
                    detail: `Seu preço (R$ ${myProduct.price}) está ${Math.round(((myProduct.price / avgCompetitor.price) - 1) * 100)}% acima da média (R$ ${avgCompetitor.price}).`,
                    action: 'openPricingOptimizer',
                    actionLabel: 'Otimizar Preço'
                });
            }

            // Análise de keywords gap
            if (top.keywords && top.keywords.length > 0) {
                const myKeywords = myProduct.keywords || [];
                const missingKeywords = top.keywords.filter(kw => !myKeywords.includes(kw));
                if (missingKeywords.length > 0) {
                    opportunities.push({
                        type: 'keywords',
                        icon: 'bi-key',
                        priority: 'medium',
                        title: `Adicionar ${missingKeywords.length} Keywords`,
                        detail: `O top usa keywords que você não tem: ${missingKeywords.slice(0, 3).join(', ')}${missingKeywords.length > 3 ? '...' : ''}`,
                        action: 'openKeywordResearch',
                        actionLabel: 'Pesquisar Keywords'
                    });
                }
            }

            // Renderizar modal
            const modal = new bootstrap.Modal(document.getElementById('competitorDetailModal'));
            const content = document.getElementById('competitor-detail-content');

            const priorityOrder = {
                high: 1,
                medium: 2,
                low: 3
            };
            opportunities.sort((a, b) => priorityOrder[a.priority] - priorityOrder[b.priority]);

            content.innerHTML = `
            <div class="mb-4">
                <h5 class="mb-3">
                    <i class="bi bi-stars text-warning me-2"></i>
                    Oportunidades de Melhoria
                </h5>
                <p class="text-muted">Baseado na análise de ${this.competitors.length} concorrentes, identificamos ${opportunities.length} oportunidades:</p>
            </div>

            ${opportunities.length === 0 ? `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>Parabéns!</strong> Seu anúncio está bem otimizado em comparação aos concorrentes.
                </div>
            ` : opportunities.map(opp => `
                <div class="card mb-3 border-${opp.priority === 'high' ? 'danger' : 'warning'}">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="me-3">
                                <div class="rounded-circle bg-${opp.priority === 'high' ? 'danger' : 'warning'} bg-opacity-10 p-2">
                                    <i class="bi ${opp.icon} fs-4 text-${opp.priority === 'high' ? 'danger' : 'warning'}"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0">${opp.title}</h6>
                                    <span class="badge bg-${opp.priority === 'high' ? 'danger' : opp.priority === 'medium' ? 'warning' : 'secondary'}">
                                        ${opp.priority === 'high' ? 'Alta' : opp.priority === 'medium' ? 'Média' : 'Baixa'}
                                    </span>
                                </div>
                                <p class="text-muted small mb-2">${opp.detail}</p>
                                ${opp.action ? `
                                    <button class="btn btn-sm btn-outline-primary" onclick="CompetitorSpy.applyOpportunity('${opp.action}')">
                                        <i class="bi bi-arrow-right me-1"></i> ${opp.actionLabel}
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `).join('')}

            <hr class="my-4">

            <div class="mb-3">
                <h6><i class="bi bi-clipboard me-2"></i>Resumo para Copiar</h6>
            </div>
            <div class="bg-light p-3 rounded mb-3" style="font-family: monospace; font-size: 13px;">
                <strong>Top Concorrente:</strong> ${top.title}<br>
                <strong>Preço:</strong> R$ ${top.price} | <strong>Vendas:</strong> ${top.sold_quantity || 0}<br>
                <strong>Score SEO:</strong> ${top.seo_score}/100<br>
                <strong>Keywords:</strong> ${(top.keywords || []).slice(0, 5).join(', ') || 'N/A'}<br>
                <strong>Diferenciais:</strong> ${(top.differentials || []).slice(0, 3).join(', ') || 'N/A'}
            </div>
            <button class="btn btn-outline-secondary btn-sm" onclick="CompetitorSpy.copyTextSummary()">
                <i class="bi bi-clipboard me-1"></i> Copiar Resumo
            </button>
        `;

            modal.show();
        },

        applyOpportunity(action) {
            // Fechar modal atual
            const modal = bootstrap.Modal.getInstance(document.getElementById('competitorDetailModal'));
            if (modal) modal.hide();

            // Executar ação correspondente
            setTimeout(() => {
                switch (action) {
                    case 'openTitleGenerator':
                        SEOKiller.openTitleGenerator(this.myProduct?.id);
                        break;
                    case 'openDescriptionGenerator':
                        SEOKiller.openDescriptionGenerator(this.myProduct?.id);
                        break;
                    case 'openAttributeFiller':
                        SEOKiller.openAttributeFiller(this.myProduct?.id);
                        break;
                    case 'openImageAnalyzer':
                        SEOKiller.openImageAnalyzer(this.myProduct?.id);
                        break;
                    case 'openKeywordResearch':
                        SEOKiller.openKeywordResearch(this.myProduct?.id);
                        break;
                    case 'openPricingOptimizer':
                        SEOKiller.openPricingOptimizer?.(this.myProduct?.id);
                        break;
                    default:
                        console.warn('Ação desconhecida:', action);
                }
            }, 300);
        },

        copyTextSummary() {
            const top = this.competitors[0];
            const summary = [
                `Top concorrente: ${top.title}`,
                `Preço: R$ ${top.price} | Vendas: ${top.sold_quantity || 0}`,
                `Score SEO: ${top.seo_score}/100`,
                `Keywords-chave: ${(top.keywords || []).slice(0, 5).join(', ') || 'N/A'}`,
                `Diferenciais: ${(top.differentials || []).join(', ') || 'N/A'}`
            ].join('\n');

            this.copyToClipboard(summary)
                .then(() => SEOKiller.showSuccess('Resumo copiado!'))
                .catch(() => alert(summary));
        },

        async copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.top = '-1000px';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
        },

        exportReport(format = 'csv') {
            if (this.competitors.length === 0) {
                SEOKiller.showInfo('Execute uma busca primeiro!');
                return;
            }

            if (format === 'pdf') {
                this.exportPdfReport();
                return;
            }

            this.exportCsvReport();
        },

        exportCsvReport() {
            const csv = this.generateCSV();
            const blob = new Blob([csv], {
                type: 'text/csv'
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `competitor_analysis_${Date.now()}.csv`;
            a.click();

            SEOKiller.showSuccess('Relatório CSV exportado!');
        },

        async exportPdfReport() {
            const itemId = this.myProduct ? (this.myProduct.id || this.myProduct.item_id) : null;
            if (!itemId) {
                SEOKiller.showError('Selecione um produto próprio para gerar o PDF.');
                return;
            }

            const pdfButton = document.getElementById('spy-export-pdf-btn');
            const originalLabel = pdfButton ? pdfButton.innerHTML : '';
            if (pdfButton) {
                pdfButton.disabled = true;
                pdfButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Gerando...';
            }

            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/export/competitor', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        item_id: itemId,
                        competitors: this.competitors,
                        options: {
                            include_insights: true,
                            include_comparison: true,
                            generated_at: new Date().toISOString()
                        }
                    })
                });

                if (!data.success || !data.url) {
                    throw new Error(data.error || 'Falha ao gerar PDF');
                }

                window.open(data.url, '_blank');
                SEOKiller.showSuccess('Relatório em PDF gerado!');
            } catch (error) {
                SEOKiller.showError(error.message || 'Erro ao exportar PDF');
            } finally {
                if (pdfButton) {
                    pdfButton.disabled = false;
                    pdfButton.innerHTML = originalLabel || '<i class="bi bi-file-earmark-pdf"></i> Exportar PDF';
                }
            }
        },

        generateCSV() {
            let csv = 'Título,Preço,Vendas,Score SEO,Imagens,Atributos\n';
            this.competitors.forEach(comp => {
                csv += `"${comp.title}",${comp.price},${comp.sold_quantity || 0},${comp.seo_score},${comp.pictures_count},${comp.attributes_filled}\n`;
            });
            return csv;
        },

        updateResultActionState(enabled) {
            ['spy-export-csv-btn', 'spy-export-pdf-btn', 'spy-copy-btn'].forEach(id => {
                const btn = document.getElementById(id);
                if (btn) btn.disabled = !enabled;
            });
        },

        // ==========================================
        // 🔖 Watchlist Features
        // ==========================================

        async loadWatchlist() {
            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/watchlist');

                if (data.success) {
                    this.watchlist = data.watchlist || [];
                    const badge = document.getElementById('watchlist-count');
                    if (badge) badge.textContent = this.watchlist.length;
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
                    const watchlistItem = this.watchlist.find(w => String(w.competitor_item_id) === String(competitorItemId));
                    if (!watchlistItem) return;

                    const {
                        data
                    } = await requestJson(`/api/seo-killer/watchlist/${watchlistItem.id}`, {
                        method: 'DELETE'
                    });

                    if (data.success) {
                        SEOKiller.showSuccess('Removido da watchlist');
                        await this.loadWatchlist();
                        this.renderGrid();
                    } else {
                        throw new Error(data.error || 'Erro ao remover da watchlist');
                    }
                } else {
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
                        await this.loadWatchlist();
                        this.renderGrid();
                    } else {
                        throw new Error(data.error || 'Erro ao adicionar à watchlist');
                    }
                }
            } catch (error) {
                SEOKiller.showError(error.message || 'Erro ao atualizar watchlist');
            }
        },

        isInWatchlist(competitorItemId) {
            if (!competitorItemId || !Array.isArray(this.watchlist)) return false;
            return this.watchlist.some(w => String(w.competitor_item_id) === String(competitorItemId));
        },

        renderWatchlist() {
            const container = document.getElementById('watchlist-container');
            if (!container) return;

            if (!this.watchlist.length) {
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
                <table class="table table-hover align-middle">
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
                                        <div class="fw-bold text-truncate" style="max-width: 300px;" title="${item.title}">
                                            ${item.title}
                                        </div>
                                        <small class="text-muted">${item.competitor_item_id}</small>
                                    </td>
                                    <td><strong>R$ ${item.price}</strong></td>
                                    <td>${item.sold_quantity}</td>
                                    <td><span class="badge bg-${scoreClass}">${item.seo_score}/100</span></td>
                                    <td><small>${lastChecked}</small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="CompetitorSpy.updateWatchlistItem(${item.id})" title="Atualizar">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <button class="btn btn-outline-success" onclick="CompetitorSpy.exportWatchlistPdf(${item.id})" title="Exportar PDF">
                                                <i class="bi bi-file-earmark-pdf"></i>
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
            const container = document.getElementById('watchlist-container');
            if (container) {
                SEOKiller.showLoading(container, 'Atualizando...');
            }

            try {
                const {
                    data
                } = await requestJson(`/api/seo-killer/watchlist/${watchlistId}/update`, {
                    method: 'POST'
                });

                if (data.success) {
                    SEOKiller.showSuccess(`${data.changes_detected} mudança(s) detectada(s)`);
                    await this.loadWatchlist();
                    await this.loadAlerts();
                } else {
                    throw new Error(data.error || 'Erro ao atualizar item');
                }
            } catch (error) {
                SEOKiller.showError('Erro ao atualizar: ' + error.message);
                this.renderWatchlist();
            }
        },

        async refreshWatchlist() {
            if (!this.watchlist.length) {
                SEOKiller.showError('Nenhum item na watchlist');
                return;
            }

            const container = document.getElementById('watchlist-container');
            if (container) {
                SEOKiller.showLoading(container, 'Atualizando todos os itens...');
            }

            let updated = 0;
            let changesDetected = 0;

            for (const item of this.watchlist) {
                try {
                    const {
                        data
                    } = await requestJson(`/api/seo-killer/watchlist/${item.id}/update`, {
                        method: 'POST'
                    });

                    if (data.success) {
                        updated++;
                        changesDetected += data.changes_detected || 0;
                    } else {
                        console.error(`Erro ao atualizar ${item.id}:`, data.error);
                    }
                } catch (error) {
                    console.error(`Erro ao atualizar ${item.id}:`, error);
                }
            }

            await this.loadWatchlist();
            await this.loadAlerts();
            SEOKiller.showSuccess(`${updated} itens atualizados (${changesDetected} mudanças)`);
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
                    await this.loadWatchlist();
                    this.renderGrid();
                } else {
                    throw new Error(data.error || 'Erro ao remover');
                }
            } catch (error) {
                SEOKiller.showError(error.message || 'Erro ao remover');
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

        async exportWatchlistPdf(watchlistId) {
            try {
                const {
                    data
                } = await requestJson(`/api/seo-killer/export/watchlist/${watchlistId}?days=30`);

                if (!data.success || !data.url) {
                    throw new Error(data.error || 'Falha ao gerar PDF');
                }

                window.open(data.url, '_blank');
                SEOKiller.showSuccess('PDF da watchlist gerado!');
            } catch (error) {
                SEOKiller.showError(error.message || 'Erro ao exportar watchlist');
            }
        },

        showHistoryModal(history) {
            if (!history.length) {
                alert('Nenhuma mudança detectada nos últimos 30 dias');
                return;
            }

            const modalId = 'watchlistHistoryModal';
            const existingModal = document.getElementById(modalId);
            if (existingModal) existingModal.remove();

            const html = `
            <div class="modal fade" id="${modalId}" tabindex="-1">
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
            const modal = new bootstrap.Modal(document.getElementById(modalId));
            modal.show();

            document.getElementById(modalId).addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        },

        // ==========================================
        // 🔔 Alerts
        // ==========================================

        async loadAlerts() {
            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/alerts?status=unread&limit=50');

                if (data.success) {
                    this.alerts = data.alerts || [];
                    const unreadCount = this.alerts.filter(a => a.status === 'unread').length;
                    const badge = document.getElementById('alerts-count');
                    if (badge) badge.textContent = unreadCount;
                    this.filterAlerts(this.alertFilter);
                }
            } catch (error) {
                console.error('Erro ao carregar alertas:', error);
            }
        },

        filterAlerts(filter) {
            this.alertFilter = filter;
            this.updateAlertFilterButtons(filter);

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
            if (!container) return;

            if (!alerts.length) {
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
                            <small class="text-muted">${new Date(alert.created_at).toLocaleString('pt-BR')}</small>
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

        updateAlertFilterButtons(filter) {
            const buttons = document.querySelectorAll('#content-spy-alerts .btn-group button');
            buttons.forEach(btn => {
                const btnFilter = btn.getAttribute('data-filter');
                btn.classList.toggle('active', btnFilter === filter);
            });
        },

        async markAlertAsRead(alertId) {
            try {
                const {
                    data
                } = await requestJson(`/api/seo-killer/alerts/${alertId}/read`, {
                    method: 'POST'
                });

                if (data.success) {
                    await this.loadAlerts();
                }
            } catch (error) {
                console.error('Erro ao marcar alerta como lido:', error);
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        const spyTabTrigger = document.getElementById('competitor-spy-tab');
        if (spyTabTrigger) {
            spyTabTrigger.addEventListener('shown.bs.tab', () => CompetitorSpy.init());
            if (spyTabTrigger.classList.contains('active')) {
                CompetitorSpy.init();
            }
        }
    });
</script>
