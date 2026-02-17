<!-- Modal Pesquisa de Keywords -->
<div class="modal fade" id="keywordResearchModal" tabindex="-1" aria-labelledby="keywordResearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #9B59B6 0%, #8E44AD 100%); color: white;">
                <h5 class="modal-title" id="keywordResearchModalLabel">
                    <i class="bi bi-search"></i> Pesquisa de Keywords
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Formulário de Pesquisa -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Produto ou Termo de Busca</label>
                                <select class="form-select" id="kw-product-select">
                                    <option value="">Selecionar produto...</option>
                                </select>
                                <input type="text" class="form-control mt-2" id="kw-search-term" placeholder="Ou digite um termo de busca...">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Categoria</label>
                                <select class="form-select" id="kw-category">
                                    <option value="">Todas</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="kw-long-tail" checked>
                                    <label class="form-check-label" for="kw-long-tail">
                                        Incluir Long-Tail
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="kw-competitors" checked>
                                    <label class="form-check-label" for="kw-competitors">
                                        Analisar Concorrentes
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <button class="btn btn-lg btn-primary" onclick="KeywordResearch.search()">
                                <i class="bi bi-search"></i> Pesquisar Keywords
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Resultados -->
                <div id="kw-results-area" style="display: none;">
                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs mb-3" id="kwTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="kw-main-tab" data-bs-toggle="tab" data-bs-target="#kw-main" type="button">
                                <i class="bi bi-star-fill"></i> Principais
                                <span class="badge bg-primary ms-1" id="kw-main-count">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="kw-longtail-tab" data-bs-toggle="tab" data-bs-target="#kw-longtail" type="button">
                                <i class="bi bi-list-ul"></i> Long-Tail
                                <span class="badge bg-primary ms-1" id="kw-longtail-count">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="kw-trends-tab" data-bs-toggle="tab" data-bs-target="#kw-trends" type="button">
                                <i class="bi bi-graph-up-arrow"></i> Tendências
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="kw-gaps-tab" data-bs-toggle="tab" data-bs-target="#kw-gaps" type="button">
                                <i class="bi bi-exclamation-triangle"></i> Gaps
                                <span class="badge bg-warning ms-1" id="kw-gaps-count">0</span>
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="kwTabsContent">
                        <!-- Keywords Principais -->
                        <div class="tab-pane fade show active" id="kw-main" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6>Keywords com melhor potencial</h6>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary" onclick="KeywordResearch.selectAll('main')">
                                        <i class="bi bi-check-all"></i> Selecionar Todas
                                    </button>
                                    <button class="btn btn-sm btn-primary" onclick="KeywordResearch.copySelected()">
                                        <i class="bi bi-clipboard"></i> Copiar Selecionadas
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table keyword-table">
                                    <thead>
                                        <tr>
                                            <th width="50"></th>
                                            <th>Keyword</th>
                                            <th>Volume</th>
                                            <th>Competição</th>
                                            <th>Relevância</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="kw-main-tbody">
                                        <!-- Será preenchido dinamicamente -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Long-Tail Keywords -->
                        <div class="tab-pane fade" id="kw-longtail" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6>Frases de cauda longa com potencial</h6>
                                <button class="btn btn-sm btn-primary" onclick="KeywordResearch.copySelected()">
                                    <i class="bi bi-clipboard"></i> Copiar Selecionadas
                                </button>
                            </div>
                            <div id="kw-longtail-list">
                                <!-- Será preenchido dinamicamente -->
                            </div>
                        </div>

                        <!-- Tendências -->
                        <div class="tab-pane fade" id="kw-trends" role="tabpanel">
                            <h6 class="mb-3">Keywords em Alta no Mercado Livre</h6>
                            <canvas id="kw-trends-chart" height="100"></canvas>
                            <div id="kw-trends-list" class="mt-4">
                                <!-- Será preenchido dinamicamente -->
                            </div>
                        </div>

                        <!-- Gaps (Keywords de Concorrentes) -->
                        <div class="tab-pane fade" id="kw-gaps" role="tabpanel">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Estas keywords são usadas por seus concorrentes, mas não por você. Considerá-las pode melhorar seu posicionamento!
                            </div>
                            <div class="table-responsive">
                                <table class="table keyword-table">
                                    <thead>
                                        <tr>
                                            <th width="50"></th>
                                            <th>Keyword</th>
                                            <th>Usado por</th>
                                            <th>Impacto Estimado</th>
                                            <th>Ação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="kw-gaps-tbody">
                                        <!-- Será preenchido dinamicamente -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-outline-primary" onclick="KeywordResearch.exportCSV()">
                    <i class="bi bi-download"></i> Exportar CSV
                </button>
                <button type="button" class="btn btn-success" onclick="KeywordResearch.applyToTitle()">
                    <i class="bi bi-magic"></i> Aplicar ao Título
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .keyword-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        transition: all 0.3s;
    }

    .keyword-card:hover {
        border-color: #9B59B6;
        box-shadow: 0 2px 8px rgba(155, 89, 182, 0.15);
    }

    .keyword-card.selected {
        border-color: #27AE60;
        background: #E8F8F5;
    }

    .volume-bar {
        height: 20px;
        background: #e0e0e0;
        border-radius: 10px;
        overflow: hidden;
        position: relative;
    }

    .volume-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #27AE60 0%, #2ECC71 100%);
        transition: width 0.5s ease;
    }

    .competition-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 3px;
    }

    .competition-low {
        background: #27AE60;
    }

    .competition-medium {
        background: #F39C12;
    }

    .competition-high {
        background: #E74C3C;
    }
</style>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
    const KeywordResearch = {
        data: null,
        selectedKeywords: new Set(),

        init() {
            console.log('Keyword Research initialized');
            this.loadProducts();
            this.loadCategories();
        },

        async loadProducts() {
            const select = document.getElementById('kw-product-select');

            try {
                const {
                    data
                } = await requestJson('/api/items?limit=100');

                if (data.results) {
                    select.innerHTML = '<option value="">Selecionar produto...</option>' +
                        data.results.map(item =>
                            `<option value="${item.id}">${item.title}</option>`
                        ).join('');
                }
            } catch (error) {
                console.error('Erro ao carregar produtos:', error);
            }
        },

        async loadCategories() {
            const select = document.getElementById('kw-category');

            try {
                const {
                    data
                } = await requestJson('/api/categories');

                if (data.categories) {
                    select.innerHTML = '<option value="">Todas</option>' +
                        data.categories.map(cat =>
                            `<option value="${cat.id}">${cat.name}</option>`
                        ).join('');
                }
            } catch (error) {
                console.error('Erro ao carregar categorias:', error);
            }
        },

        async search() {
            const productId = document.getElementById('kw-product-select').value;
            const searchTerm = document.getElementById('kw-search-term').value.trim();
            const category = document.getElementById('kw-category').value;
            const includeLongTail = document.getElementById('kw-long-tail').checked;
            const includeCompetitors = document.getElementById('kw-competitors').checked;

            if (!productId && !searchTerm) {
                SEOKiller.showError('Selecione um produto ou digite um termo de busca');
                return;
            }

            const resultsArea = document.getElementById('kw-results-area');
            resultsArea.style.display = 'block';

            SEOKiller.showLoading('kw-main-tbody', 'Pesquisando keywords...');

            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/keywords', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        item_id: productId || undefined,
                        title: searchTerm || undefined,
                        category_id: category || undefined,
                        include_long_tail: includeLongTail,
                        analyze_competitors: includeCompetitors
                    })
                });

                if (data.success) {
                    this.data = data;
                    this.renderResults(data);
                    SEOKiller.showSuccess('Keywords encontradas!');
                } else {
                    throw new Error(data.error || 'Erro ao pesquisar keywords');
                }
            } catch (error) {
                SEOKiller.showError(`Erro: ${error.message}`);
                document.getElementById('kw-main-tbody').innerHTML =
                    `<tr><td colspan="6" class="text-center text-danger">${error.message}</td></tr>`;
            }
        },

        renderResults(data) {
            // Keywords Principais
            if (data.main_keywords) {
                this.renderMainKeywords(data.main_keywords);
                document.getElementById('kw-main-count').textContent = data.main_keywords.length;
            }

            // Long-Tail Keywords
            if (data.long_tail) {
                this.renderLongTail(data.long_tail);
                document.getElementById('kw-longtail-count').textContent = data.long_tail.length;
            }

            // Tendências
            if (data.trends) {
                this.renderTrends(data.trends);
            }

            // Gaps
            if (data.competitor_gaps) {
                this.renderGaps(data.competitor_gaps);
                document.getElementById('kw-gaps-count').textContent = data.competitor_gaps.length;
            }
        },

        renderMainKeywords(keywords) {
            const tbody = document.getElementById('kw-main-tbody');

            tbody.innerHTML = keywords.map((kw, index) => {
                const volumePercent = Math.min(100, (kw.volume / 10000) * 100);
                const competitionClass = kw.competition < 30 ? 'low' : kw.competition < 70 ? 'medium' : 'high';
                const relevanceColor = kw.relevance > 80 ? 'success' : kw.relevance > 50 ? 'warning' : 'secondary';

                return `
                <tr>
                    <td>
                        <input type="checkbox" class="form-check-input kw-checkbox" data-keyword="${kw.keyword}" data-type="main">
                    </td>
                    <td><strong>${kw.keyword}</strong></td>
                    <td>
                        <div class="volume-bar">
                            <div class="volume-bar-fill" style="width: ${volumePercent}%"></div>
                        </div>
                        <small class="text-muted">${SEOKiller.utils.formatNumber(kw.volume)} buscas/mês</small>
                    </td>
                    <td>
                        <span class="competition-indicator competition-${competitionClass}"></span>
                        ${kw.competition < 30 ? 'Baixa' : kw.competition < 70 ? 'Média' : 'Alta'}
                    </td>
                    <td>
                        <span class="badge bg-${relevanceColor}">${kw.relevance}%</span>
                    </td>
                    <td>
                        ${kw.competition < 40 && kw.volume > 1000 
                            ? '<span class="keyword-badge low-competition">💎 Oportunidade</span>' 
                            : kw.volume > 5000 
                                ? '<span class="keyword-badge high-volume">🔥 Alta Demanda</span>' 
                                : ''}
                    </td>
                </tr>
            `;
            }).join('');
        },

        renderLongTail(keywords) {
            const list = document.getElementById('kw-longtail-list');

            list.innerHTML = keywords.map((kw, index) => `
            <div class="keyword-card">
                <div class="d-flex align-items-center">
                    <input type="checkbox" class="form-check-input me-3 kw-checkbox" data-keyword="${kw.phrase}" data-type="longtail">
                    <div class="flex-grow-1">
                        <strong>${kw.phrase}</strong>
                        <div class="mt-1">
                            <span class="badge bg-secondary me-1">${SEOKiller.utils.formatNumber(kw.volume)} buscas</span>
                            <span class="badge bg-info">Score: ${kw.opportunity_score}/100</span>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
        },

        renderTrends(trends) {
            const list = document.getElementById('kw-trends-list');

            list.innerHTML = `
            <div class="row">
                ${trends.map(trend => `
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">${trend.keyword}</h6>
                                <div class="d-flex justify-content-between">
                                    <span>Crescimento:</span>
                                    <span class="badge bg-success">+${trend.growth}%</span>
                                </div>
                                <div class="progress mt-2" style="height: 5px;">
                                    <div class="progress-bar bg-success" style="width: ${trend.growth}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
        },

        renderGaps(gaps) {
            const tbody = document.getElementById('kw-gaps-tbody');

            tbody.innerHTML = gaps.map(gap => `
            <tr>
                <td>
                    <input type="checkbox" class="form-check-input kw-checkbox" data-keyword="${gap.keyword}" data-type="gap">
                </td>
                <td><strong>${gap.keyword}</strong></td>
                <td>${gap.used_by} concorrentes</td>
                <td>
                    <span class="badge bg-${gap.impact > 70 ? 'danger' : gap.impact > 40 ? 'warning' : 'info'}">
                        ${gap.impact > 70 ? 'Alto' : gap.impact > 40 ? 'Médio' : 'Baixo'}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="KeywordResearch.addToTitle('${gap.keyword}')">
                        Adicionar ao Título
                    </button>
                </td>
            </tr>
        `).join('');
        },

        selectAll(type) {
            document.querySelectorAll(`.kw-checkbox[data-type="${type}"]`).forEach(checkbox => {
                checkbox.checked = true;
                this.selectedKeywords.add(checkbox.dataset.keyword);
            });
            SEOKiller.showInfo('Todas as keywords foram selecionadas');
        },

        copySelected() {
            const keywords = Array.from(this.selectedKeywords).join(', ');

            if (!keywords) {
                SEOKiller.showError('Selecione pelo menos uma keyword');
                return;
            }

            navigator.clipboard.writeText(keywords).then(() => {
                SEOKiller.showSuccess('Keywords copiadas para a área de transferência!');
            });
        },

        applyToTitle() {
            const keywords = Array.from(this.selectedKeywords);

            if (keywords.length === 0) {
                SEOKiller.showError('Selecione pelo menos uma keyword');
                return;
            }

            // Fechar modal atual
            bootstrap.Modal.getInstance(document.getElementById('keywordResearchModal')).hide();

            // Abrir modal de títulos
            setTimeout(() => {
                window.openTitleOptimizer();

                // Preencher keywords
                setTimeout(() => {
                    document.getElementById('title-keywords').value = keywords.join(', ');
                }, 500);
            }, 300);
        },

        addToTitle(keyword) {
            this.selectedKeywords.add(keyword);
            this.applyToTitle();
        },

        exportCSV() {
            if (!this.data) return;

            let csv = 'Keyword,Volume,Competição,Relevância,Tipo\n';

            if (this.data.main_keywords) {
                this.data.main_keywords.forEach(kw => {
                    csv += `"${kw.keyword}",${kw.volume},${kw.competition},${kw.relevance},Principal\n`;
                });
            }

            if (this.data.long_tail) {
                this.data.long_tail.forEach(kw => {
                    csv += `"${kw.phrase}",${kw.volume},0,${kw.opportunity_score},Long-Tail\n`;
                });
            }

            const blob = new Blob([csv], {
                type: 'text/csv'
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `keywords_${Date.now()}.csv`;
            a.click();

            SEOKiller.showSuccess('CSV exportado com sucesso!');
        }
    };

    // Capturar mudanças nos checkboxes
    document.addEventListener('change', (e) => {
        if (e.target.matches('.kw-checkbox')) {
            const keyword = e.target.dataset.keyword;
            if (e.target.checked) {
                KeywordResearch.selectedKeywords.add(keyword);
            } else {
                KeywordResearch.selectedKeywords.delete(keyword);
            }
        }
    });

    // Função global para abrir o modal
    window.showKeywordResearch = function() {
        const modal = new bootstrap.Modal(document.getElementById('keywordResearchModal'));
        modal.show();
        KeywordResearch.init();
    };
</script>