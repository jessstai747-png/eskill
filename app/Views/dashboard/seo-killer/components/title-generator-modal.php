<!-- Modal Gerador de Títulos -->
<div class="modal fade" id="titleGeneratorModal" tabindex="-1" aria-labelledby="titleGeneratorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%); color: white;">
                <h5 class="modal-title" id="titleGeneratorModalLabel">
                    <i class="bi bi-magic"></i> Gerador de Títulos Matadores
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Formulário de Entrada -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-3">Selecione o produto ou insira manualmente</h6>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Produto</label>
                                <select class="form-select" id="title-product-select" onchange="TitleGenerator.loadProductData()">
                                    <option value="">Selecionar produto...</option>
                                </select>
                                <small class="text-muted">Ou preencha os campos manualmente abaixo</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Título Atual</label>
                                <input type="text" class="form-control" id="title-current" placeholder="Digite o título atual...">
                                <div class="mt-2">
                                    <small>Caracteres: <span id="title-char-count">0</span>/60</small>
                                    <small class="ms-3 text-muted">Ideal: 45-60 caracteres</small>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Marca</label>
                                <input type="text" class="form-control" id="title-brand" placeholder="Ex: Samsung">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Modelo</label>
                                <input type="text" class="form-control" id="title-model" placeholder="Ex: Galaxy S21">
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Keywords Importantes (separadas por vírgula)</label>
                                <input type="text" class="form-control" id="title-keywords" placeholder="Ex: novo, original, garantia">
                            </div>
                        </div>

                        <div class="text-center mt-3">
                            <button class="btn btn-lg btn-primary" onclick="TitleGenerator.generate()">
                                <i class="bi bi-stars"></i> Gerar Títulos Matadores
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Área de Resultados -->
                <div id="title-suggestions-area" style="display: none;">
                    <h5 class="mb-3">
                        <i class="bi bi-lightbulb"></i> Sugestões Geradas
                        <span class="badge bg-primary ms-2" id="title-suggestions-count">0</span>
                    </h5>

                    <!-- Sugestões -->
                    <div id="title-suggestions-list" class="mb-4">
                        <!-- As sugestões aparecerão aqui -->
                    </div>

                    <!-- Comparação -->
                    <div id="title-comparison-area" style="display: none;">
                        <h6 class="mb-3">Comparação Lado a Lado</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Título</th>
                                        <th>Score</th>
                                        <th>Tamanho</th>
                                        <th>Keywords</th>
                                    </tr>
                                </thead>
                                <tbody id="title-comparison-tbody">
                                    <!-- Comparação aparecerá aqui -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-success" id="title-apply-btn" style="display: none;" onclick="TitleGenerator.apply()">
                    <i class="bi bi-check-circle"></i> Aplicar Título Selecionado
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .title-suggestion-card {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        transition: all 0.3s;
        cursor: pointer;
        position: relative;
    }

    .title-suggestion-card:hover {
        border-color: #3498DB;
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);
        transform: translateY(-2px);
    }

    .title-suggestion-card.selected {
        border-color: #27AE60;
        background: #E8F8F5;
        box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
    }

    .title-suggestion-card .score-circle {
        position: absolute;
        top: -15px;
        right: 20px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: white;
        border: 3px solid;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        font-weight: bold;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .title-suggestion-card .score-circle.high {
        border-color: #27AE60;
        color: #27AE60;
    }

    .title-suggestion-card .score-circle.medium {
        border-color: #F39C12;
        color: #F39C12;
    }

    .title-suggestion-card .score-circle.low {
        border-color: #E74C3C;
        color: #E74C3C;
    }

    .title-suggestion-card .title-text {
        font-size: 18px;
        font-weight: 600;
        color: #2C3E50;
        margin-bottom: 10px;
        line-height: 1.4;
    }

    .title-suggestion-card .title-text .keyword-highlight {
        background: #FFF3CD;
        padding: 2px 4px;
        border-radius: 3px;
        font-weight: 700;
    }

    .title-suggestion-card .title-explanation {
        font-size: 14px;
        color: #666;
        margin-bottom: 10px;
    }

    .title-suggestion-card .title-actions {
        display: flex;
        gap: 10px;
        margin-top: 10px;
    }

    .title-preview-box {
        background: #F8F9FA;
        border: 1px dashed #CCC;
        border-radius: 8px;
        padding: 15px;
        margin-top: 10px;
    }

    .title-preview-box h6 {
        font-size: 12px;
        color: #666;
        margin-bottom: 10px;
        text-transform: uppercase;
    }

    .title-preview-box .preview-content {
        font-size: 16px;
        color: #1976D2;
        font-weight: 500;
    }
</style>

<script nonce="<?= CSP_NONCE ?>">
    const TitleGenerator = {
        currentProduct: null,
        suggestions: [],
        selectedTitle: null,

        init() {
            console.log('Title Generator initialized');
            this.loadProducts();
            this.bindEvents();
        },

        bindEvents() {
            // Contador de caracteres
            document.getElementById('title-current')?.addEventListener('input', (e) => {
                document.getElementById('title-char-count').textContent = e.target.value.length;
            });
        },

        async loadProducts() {
            const select = document.getElementById('title-product-select');

            try {
                // Carregar lista de produtos
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

        async loadProductData() {
            const select = document.getElementById('title-product-select');
            const productId = select.value;

            if (!productId) return;

            try {
                const {
                    data: item
                } = await requestJson(`/api/items/${productId}`);

                this.currentProduct = item;

                // Preencher campos
                document.getElementById('title-current').value = item.title || '';
                document.getElementById('title-brand').value = this.extractAttribute(item, 'BRAND') || '';
                document.getElementById('title-model').value = this.extractAttribute(item, 'MODEL') || '';

                // Atualizar contador
                document.getElementById('title-char-count').textContent = (item.title || '').length;
            } catch (error) {
                SEOKiller.showError('Erro ao carregar dados do produto');
            }
        },

        extractAttribute(item, attrId) {
            const attr = item.attributes?.find(a => a.id === attrId);
            return attr?.value_name || '';
        },

        async generate() {
            const title = document.getElementById('title-current').value.trim();
            const brand = document.getElementById('title-brand').value.trim();
            const model = document.getElementById('title-model').value.trim();
            const keywords = document.getElementById('title-keywords').value.trim();

            if (!title && !this.currentProduct) {
                SEOKiller.showError('Preencha pelo menos o título ou selecione um produto');
                return;
            }

            const suggestionsArea = document.getElementById('title-suggestions-area');
            const suggestionsList = document.getElementById('title-suggestions-list');

            suggestionsArea.style.display = 'block';
            SEOKiller.showLoading(suggestionsList, 'Gerando títulos matadores...');

            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/title', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        title: title,
                        brand: brand,
                        model: model,
                        keywords: keywords.split(',').map(k => k.trim()).filter(k => k),
                        item_id: this.currentProduct?.id
                    })
                });

                if (data.success && data.suggestions) {
                    this.suggestions = data.suggestions;
                    this.renderSuggestions(data.suggestions);
                    document.getElementById('title-suggestions-count').textContent = data.suggestions.length;
                    SEOKiller.showSuccess('Títulos gerados com sucesso!');
                } else {
                    throw new Error(data.error || 'Erro ao gerar títulos');
                }
            } catch (error) {
                suggestionsList.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
                SEOKiller.showError('Erro ao gerar títulos');
            }
        },

        renderSuggestions(suggestions) {
            const list = document.getElementById('title-suggestions-list');

            list.innerHTML = suggestions.map((suggestion, index) => {
                const scoreClass = SEOKiller.utils.getScoreBadgeClass(suggestion.score);
                const keywords = suggestion.keywords || [];

                let titleHtml = suggestion.title;
                keywords.forEach(keyword => {
                    const regex = new RegExp(`(${keyword})`, 'gi');
                    titleHtml = titleHtml.replace(regex, '<span class="keyword-highlight">$1</span>');
                });

                return `
                <div class="title-suggestion-card" data-suggestion-index="${index}" onclick="TitleGenerator.selectSuggestion(${index})">
                    <div class="score-circle ${scoreClass}">
                        <div style="font-size: 20px;">${suggestion.score}</div>
                        <div style="font-size: 10px;">/100</div>
                    </div>
                    
                    <div class="title-text">${titleHtml}</div>
                    
                    <div class="title-explanation">
                        <i class="bi bi-info-circle text-info"></i> ${suggestion.explanation || 'Título otimizado para SEO'}
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-secondary me-1">${suggestion.title.length} chars</span>
                            <span class="badge bg-info">${keywords.length} keywords</span>
                        </div>
                        <div class="title-actions">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="compare-${index}" 
                                       onchange="TitleGenerator.toggleComparison(${index})" onclick="event.stopPropagation()">
                                <label class="form-check-label" for="compare-${index}">Comparar</label>
                            </div>
                        </div>
                    </div>

                    <div class="title-preview-box">
                        <h6>Preview na Busca do ML:</h6>
                        <div class="preview-content">${suggestion.title}</div>
                    </div>
                </div>
            `;
            }).join('');
        },

        selectSuggestion(index) {
            // Remove seleção anterior
            document.querySelectorAll('.title-suggestion-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Adiciona nova seleção
            const card = document.querySelector(`[data-suggestion-index="${index}"]`);
            card.classList.add('selected');

            this.selectedTitle = this.suggestions[index];
            document.getElementById('title-apply-btn').style.display = 'inline-block';

            SEOKiller.showInfo(`Título selecionado: ${this.selectedTitle.title}`);
        },

        toggleComparison(index) {
            const compareArea = document.getElementById('title-comparison-area');
            const tbody = document.getElementById('title-comparison-tbody');

            // Coletar títulos marcados
            const checked = [];
            document.querySelectorAll('[id^="compare-"]:checked').forEach(checkbox => {
                const idx = parseInt(checkbox.id.split('-')[1]);
                checked.push(this.suggestions[idx]);
            });

            if (checked.length > 0) {
                compareArea.style.display = 'block';
                tbody.innerHTML = checked.map(title => `
                <tr>
                    <td>${title.title}</td>
                    <td><span class="badge bg-${SEOKiller.utils.getScoreColor(title.score)}">${title.score}/100</span></td>
                    <td>${title.title.length} chars</td>
                    <td>${(title.keywords || []).join(', ')}</td>
                </tr>
            `).join('');
            } else {
                compareArea.style.display = 'none';
            }
        },

        async apply() {
            if (!this.selectedTitle) {
                SEOKiller.showError('Selecione um título primeiro');
                return;
            }

            if (!this.currentProduct) {
                SEOKiller.showError('Selecione um produto para aplicar o título');
                return;
            }

            const confirmed = confirm(`Tem certeza que deseja aplicar este título?\n\n"${this.selectedTitle.title}"\n\nEsta ação irá atualizar o anúncio no Mercado Livre.`);

            if (!confirmed) return;

            try {
                const {
                    data
                } = await requestJson(`/api/items/${this.currentProduct.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        title: this.selectedTitle.title
                    })
                });

                if (data.success) {
                    SEOKiller.showSuccess('Título aplicado com sucesso!');
                    bootstrap.Modal.getInstance(document.getElementById('titleGeneratorModal')).hide();
                    SEOKiller.loadDashboardData(); // Reload dashboard
                } else {
                    throw new Error(data.error || 'Erro ao aplicar título');
                }
            } catch (error) {
                SEOKiller.showError(`Erro ao aplicar título: ${error.message}`);
            }
        }
    };

    // Função global para abrir o modal
    window.openTitleOptimizer = function() {
        const modal = new bootstrap.Modal(document.getElementById('titleGeneratorModal'));
        modal.show();
        TitleGenerator.init();
    };
</script>