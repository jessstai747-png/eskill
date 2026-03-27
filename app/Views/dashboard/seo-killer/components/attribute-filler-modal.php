<!-- Modal Preenchimento de Atributos -->
<div class="modal fade" id="attributeFillerModal" tabindex="-1" aria-labelledby="attributeFillerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #9B59B6 0%, #8E44AD 100%); color: white;">
                <h5 class="modal-title" id="attributeFillerModalLabel">
                    <i class="bi bi-tags"></i> Preenchimento Inteligente de Atributos
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Coluna Esquerda: Seleção e Atributos -->
                    <div class="col-lg-8">
                        <!-- Seleção de Produto -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <label class="form-label fw-bold">Selecione o Produto</label>
                                <select class="form-select" id="attr-product-select" onchange="AttributeFiller.loadProduct()">
                                    <option value="">Escolha um produto...</option>
                                </select>
                            </div>
                        </div>

                        <!-- Ações Rápidas -->
                        <div class="card mb-3" id="attr-actions-card" style="display: none;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">🎯 Ações Rápidas</h6>
                                        <small class="text-muted">Preencha automaticamente ou analise gaps</small>
                                    </div>
                                    <div class="btn-group">
                                        <button class="btn btn-primary" onclick="AttributeFiller.autoFillAll()">
                                            <i class="bi bi-magic"></i> Auto-preencher Tudo
                                        </button>
                                        <button class="btn btn-outline-info" onclick="AttributeFiller.analyzeGaps()">
                                            <i class="bi bi-search"></i> Analisar Gaps
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs de Atributos -->
                        <div class="card" id="attr-tabs-card" style="display: none;">
                            <div class="card-header">
                                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="tab-all-attrs" data-bs-toggle="tab" href="#content-all-attrs" role="tab">
                                            📋 Todos (<span id="count-all">0</span>)
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="tab-missing-attrs" data-bs-toggle="tab" href="#content-missing-attrs" role="tab">
                                            ❌ Faltando (<span id="count-missing">0</span>)
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="tab-critical-attrs" data-bs-toggle="tab" href="#content-critical-attrs" role="tab">
                                            🔥 Críticos (<span id="count-critical">0</span>)
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="tab-hidden-attrs" data-bs-toggle="tab" href="#content-hidden-attrs" role="tab">
                                            🚀 Hidden SEO (<span id="count-hidden">0</span>)
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content">
                                    <!-- Tab: Todos os Atributos -->
                                    <div class="tab-pane fade show active" id="content-all-attrs" role="tabpanel">
                                        <div id="list-all-attrs" class="attributes-list">
                                            <p class="text-muted">Carregando atributos...</p>
                                        </div>
                                    </div>

                                    <!-- Tab: Atributos Faltando -->
                                    <div class="tab-pane fade" id="content-missing-attrs" role="tabpanel">
                                        <div id="list-missing-attrs" class="attributes-list">
                                            <p class="text-muted">Nenhum atributo faltando.</p>
                                        </div>
                                    </div>

                                    <!-- Tab: Atributos Críticos -->
                                    <div class="tab-pane fade" id="content-critical-attrs" role="tabpanel">
                                        <div id="list-critical-attrs" class="attributes-list">
                                            <p class="text-muted">Nenhum atributo crítico faltando.</p>
                                        </div>
                                    </div>

                                    <!-- Tab: Hidden SEO Boost -->
                                    <div class="tab-pane fade" id="content-hidden-attrs" role="tabpanel">
                                        <div class="alert alert-info mb-3">
                                            <i class="bi bi-lightbulb"></i> <strong>Hidden SEO Boost:</strong> Atributos especiais que impactam significativamente o ranking nos resultados de busca do ML.
                                        </div>
                                        <div id="list-hidden-attrs" class="attributes-list">
                                            <p class="text-muted">Carregando atributos ocultos...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Coluna Direita: Análise e Preview -->
                    <div class="col-lg-4">
                        <div class="card sticky-top" style="top: 20px;">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-graph-up"></i> Análise de Completude</h6>
                            </div>
                            <div class="card-body">
                                <!-- Score de Completude -->
                                <div class="text-center mb-4">
                                    <div class="completion-circle mb-2" id="attr-completion-circle">
                                        <div class="completion-value" id="attr-completion-value">0</div>
                                        <div class="completion-label">%</div>
                                    </div>
                                    <h6 class="text-muted">Completude dos Atributos</h6>
                                </div>

                                <!-- Breakdown -->
                                <div class="completion-breakdown">
                                    <div class="breakdown-item">
                                        <div class="breakdown-label">
                                            <i class="bi bi-check-circle-fill text-success"></i> Preenchidos
                                        </div>
                                        <div class="breakdown-value">
                                            <span id="attr-filled-count">0</span>
                                        </div>
                                    </div>
                                    <div class="breakdown-item">
                                        <div class="breakdown-label">
                                            <i class="bi bi-x-circle-fill text-danger"></i> Faltando
                                        </div>
                                        <div class="breakdown-value">
                                            <span id="attr-missing-count">0</span>
                                        </div>
                                    </div>
                                    <div class="breakdown-item">
                                        <div class="breakdown-label">
                                            <i class="bi bi-exclamation-triangle-fill text-warning"></i> Incompletos
                                        </div>
                                        <div class="breakdown-value">
                                            <span id="attr-incomplete-count">0</span>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <!-- Importância -->
                                <h6 class="mb-3">📊 Por Importância</h6>
                                <div class="importance-bars">
                                    <div class="importance-item">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>🔥 Críticos</span>
                                            <span><span id="critical-filled">0</span>/<span id="critical-total">0</span></span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-danger" id="critical-progress" style="width: 0%"></div>
                                        </div>
                                    </div>
                                    <div class="importance-item">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>⚠️ Importantes</span>
                                            <span><span id="important-filled">0</span>/<span id="important-total">0</span></span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" id="important-progress" style="width: 0%"></div>
                                        </div>
                                    </div>
                                    <div class="importance-item">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>ℹ️ Opcionais</span>
                                            <span><span id="optional-filled">0</span>/<span id="optional-total">0</span></span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-info" id="optional-progress" style="width: 0%"></div>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <!-- Impacto no SEO -->
                                <h6 class="mb-3">🎯 Impacto no SEO</h6>
                                <div class="seo-impact">
                                    <div class="impact-card">
                                        <div class="impact-title">Score Atual</div>
                                        <div class="impact-value" id="seo-score-current">-</div>
                                    </div>
                                    <div class="impact-arrow">→</div>
                                    <div class="impact-card highlight">
                                        <div class="impact-title">Score Potencial</div>
                                        <div class="impact-value" id="seo-score-potential">-</div>
                                    </div>
                                </div>
                                <p class="text-center text-success mt-2 fw-bold" id="seo-improvement">
                                    <i class="bi bi-arrow-up"></i> +<span id="seo-improvement-value">0</span> pontos
                                </p>

                                <hr>

                                <!-- Mudanças Pendentes -->
                                <div id="pending-changes-section" style="display: none;">
                                    <h6 class="mb-3">📝 Mudanças Pendentes</h6>
                                    <div id="pending-changes-list" class="pending-changes">
                                        <!-- Preenchido dinamicamente -->
                                    </div>
                                    <button class="btn btn-sm btn-outline-secondary w-100 mt-2" onclick="AttributeFiller.clearChanges()">
                                        Limpar Mudanças
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-outline-primary" onclick="AttributeFiller.previewChanges()">
                    <i class="bi bi-eye"></i> Preview
                </button>
                <button type="button" class="btn btn-success" onclick="AttributeFiller.applyChanges()" id="attr-apply-btn" disabled>
                    <i class="bi bi-check-circle"></i> Aplicar Mudanças
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .attributes-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-height: 600px;
        overflow-y: auto;
    }

    .attribute-card {
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        transition: all 0.3s;
    }

    .attribute-card:hover {
        border-color: #9B59B6;
        background: #F4ECF7;
    }

    .attribute-card.critical {
        border-left: 4px solid #E74C3C;
    }

    .attribute-card.important {
        border-left: 4px solid #F39C12;
    }

    .attribute-card.optional {
        border-left: 4px solid #3498DB;
    }

    .attribute-card.hidden-seo {
        border: 2px solid #9B59B6;
        background: linear-gradient(135deg, #F8F5FC 0%, #FFFFFF 100%);
    }

    .attribute-header {
        display: flex;
        justify-content: between;
        align-items: center;
        margin-bottom: 10px;
    }

    .attribute-name {
        font-weight: 600;
        color: #333;
        flex: 1;
    }

    .attribute-status {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 13px;
    }

    .attribute-status.filled {
        color: #27AE60;
    }

    .attribute-status.missing {
        color: #E74C3C;
    }

    .attribute-status.incomplete {
        color: #F39C12;
    }

    .attribute-importance {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 8px;
    }

    .attribute-importance.critical {
        background: #FADBD8;
        color: #C0392B;
    }

    .attribute-importance.important {
        background: #FCF3CF;
        color: #D68910;
    }

    .attribute-importance.optional {
        background: #D6EAF8;
        color: #1F618D;
    }

    .hidden-seo-badge {
        background: linear-gradient(135deg, #9B59B6 0%, #8E44AD 100%);
        color: white;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .attribute-body {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .attribute-current {
        font-size: 13px;
    }

    .attribute-current-label {
        color: #666;
        font-weight: 500;
    }

    .attribute-current-value {
        color: #333;
        font-weight: 600;
    }

    .attribute-suggestion {
        background: #E8F5E9;
        padding: 10px;
        border-radius: 6px;
        border: 1px solid #C8E6C9;
    }

    .attribute-suggestion-label {
        font-size: 12px;
        color: #2E7D32;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .attribute-input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
    }

    .attribute-actions {
        display: flex;
        gap: 8px;
    }

    .attribute-explanation {
        font-size: 12px;
        color: #666;
        font-style: italic;
        margin-top: 8px;
        padding-left: 10px;
        border-left: 3px solid #9B59B6;
    }

    .completion-circle {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: white;
        border: 8px solid #e0e0e0;
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        transition: all 0.5s;
    }

    .completion-circle.high {
        border-color: #27AE60;
    }

    .completion-circle.medium {
        border-color: #F39C12;
    }

    .completion-circle.low {
        border-color: #E74C3C;
    }

    .completion-value {
        font-size: 40px;
        font-weight: bold;
        line-height: 1;
    }

    .completion-label {
        font-size: 16px;
        color: #666;
    }

    .completion-breakdown {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .breakdown-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .breakdown-label {
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .breakdown-value {
        font-size: 18px;
        font-weight: 600;
    }

    .importance-bars {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .importance-item span {
        font-size: 13px;
    }

    .seo-impact {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .impact-card {
        flex: 1;
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
    }

    .impact-card.highlight {
        background: #E8F5E9;
        border: 2px solid #27AE60;
    }

    .impact-title {
        font-size: 11px;
        color: #666;
        text-transform: uppercase;
        margin-bottom: 5px;
    }

    .impact-value {
        font-size: 24px;
        font-weight: bold;
        color: #333;
    }

    .impact-arrow {
        font-size: 24px;
        color: #27AE60;
    }

    .pending-changes {
        display: flex;
        flex-direction: column;
        gap: 6px;
        max-height: 200px;
        overflow-y: auto;
    }

    .pending-change-item {
        background: #FFF3CD;
        padding: 8px;
        border-radius: 4px;
        font-size: 12px;
        border-left: 3px solid #F39C12;
    }
</style>

<script nonce="<?= CSP_NONCE ?>">
    const AttributeFiller = {
        currentProduct: null,
        attributes: [],
        hiddenAttributes: [],
        pendingChanges: {},

        init() {
            console.log('Attribute Filler initialized');
            this.loadProducts();
        },

        async loadProducts() {
            const select = document.getElementById('attr-product-select');

            try {
                const data = await requestJson('/api/items?limit=100');

                if (data.results) {
                    select.innerHTML = '<option value="">Escolha um produto...</option>' +
                        data.results.map(item =>
                            `<option value="${item.id}">${item.title}</option>`
                        ).join('');
                }
            } catch (error) {
                console.error('Erro ao carregar produtos:', error);
            }
        },

        async loadProduct() {
            const select = document.getElementById('attr-product-select');
            const productId = select.value;

            if (!productId) {
                document.getElementById('attr-actions-card').style.display = 'none';
                document.getElementById('attr-tabs-card').style.display = 'none';
                return;
            }

            try {
                // Carregar produto e analisar atributos
                const [{
                    data: itemData
                }, {
                    data: analysis
                }] = await Promise.all([
                    requestJson(`/api/items/${productId}`),
                    requestJson(`/api/seo-killer/attributes?item_id=${productId}&analyze_only=true`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            item_id: productId
                        })
                    })
                ]);

                this.currentProduct = itemData;

                if (analysis.success) {
                    this.attributes = analysis.attributes || [];
                    await this.loadHiddenAttributes(this.currentProduct.category_id);
                    this.renderAll();

                    document.getElementById('attr-actions-card').style.display = 'block';
                    document.getElementById('attr-tabs-card').style.display = 'block';

                    SEOKiller.showSuccess('Produto carregado e analisado!');
                } else {
                    throw new Error(analysis.error || 'Erro ao analisar atributos');
                }
            } catch (error) {
                SEOKiller.showError(`Erro: ${error.message}`);
            }
        },

        async loadHiddenAttributes(categoryId) {
            try {
                const data = await requestJson(`/api/seo-killer/hidden-attributes/${categoryId}`);

                if (data.success) {
                    this.hiddenAttributes = data.attributes || [];
                }
            } catch (error) {
                console.error('Erro ao carregar atributos ocultos:', error);
            }
        },

        renderAll() {
            this.renderAttributesList('all');
            this.renderAttributesList('missing');
            this.renderAttributesList('critical');
            this.renderHiddenAttributes();
            this.updateAnalysis();
            this.updateTabCounts();
        },

        renderAttributesList(filter) {
            const listId = `list-${filter}-attrs`;
            const container = document.getElementById(listId);

            let filtered = [...this.attributes];

            if (filter === 'missing') {
                filtered = filtered.filter(a => !a.value || a.value === '');
            } else if (filter === 'critical') {
                filtered = filtered.filter(a => a.importance === 'critical' && (!a.value || a.value === ''));
            }

            if (filtered.length === 0) {
                container.innerHTML = '<p class="text-muted">Nenhum atributo nesta categoria.</p>';
                return;
            }

            container.innerHTML = filtered.map(attr => this.renderAttributeCard(attr)).join('');
        },

        renderAttributeCard(attr) {
            const isFilled = attr.value && attr.value !== '';
            const status = isFilled ? 'filled' : 'missing';
            const statusIcon = isFilled ? '✅' : '❌';
            const statusText = isFilled ? 'Preenchido' : 'Faltando';

            const importanceClass = attr.importance || 'optional';
            const importanceText = {
                critical: '🔥 Crítico',
                important: '⚠️ Importante',
                optional: 'ℹ️ Opcional'
            } [importanceClass];

            const hasSuggestion = attr.suggestion && attr.suggestion !== '';

            return `
            <div class="attribute-card ${importanceClass}">
                <div class="attribute-header">
                    <div class="attribute-name">
                        ${attr.name}
                        <span class="attribute-importance ${importanceClass}">${importanceText}</span>
                    </div>
                    <div class="attribute-status ${status}">
                        ${statusIcon} ${statusText}
                    </div>
                </div>
                <div class="attribute-body">
                    ${isFilled ? `
                        <div class="attribute-current">
                            <span class="attribute-current-label">Valor Atual:</span>
                            <span class="attribute-current-value">${attr.value}</span>
                        </div>
                    ` : ''}

                    ${hasSuggestion ? `
                        <div class="attribute-suggestion">
                            <div class="attribute-suggestion-label">💡 Sugestão da IA:</div>
                            <div>${attr.suggestion}</div>
                        </div>
                    ` : ''}

                    <div>
                        <input type="text"
                               class="attribute-input"
                               id="attr-input-${attr.id}"
                               placeholder="Digite o valor do atributo..."
                               value="${attr.value || ''}"
                               onchange="AttributeFiller.trackChange('${attr.id}', this.value)">
                    </div>

                    <div class="attribute-actions">
                        ${hasSuggestion ? `
                            <button class="btn btn-sm btn-success" onclick="AttributeFiller.applySuggestion('${attr.id}', '${attr.suggestion.replace(/'/g, "\\'")}')">
                                <i class="bi bi-check"></i> Usar Sugestão
                            </button>
                        ` : ''}
                        <button class="btn btn-sm btn-outline-primary" onclick="AttributeFiller.suggestValue('${attr.id}')">
                            <i class="bi bi-magic"></i> Sugerir
                        </button>
                    </div>

                    ${attr.explanation ? `
                        <div class="attribute-explanation">
                            ${attr.explanation}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
        },

        renderHiddenAttributes() {
            const container = document.getElementById('list-hidden-attrs');

            if (this.hiddenAttributes.length === 0) {
                container.innerHTML = '<p class="text-muted">Nenhum atributo oculto para esta categoria.</p>';
                return;
            }

            container.innerHTML = this.hiddenAttributes.map(attr => `
            <div class="attribute-card hidden-seo">
                <div class="attribute-header">
                    <div class="attribute-name">
                        ${attr.name}
                        <span class="hidden-seo-badge">🚀 Hidden SEO Boost</span>
                    </div>
                </div>
                <div class="attribute-body">
                    <div class="attribute-explanation">
                        <strong>Por que é importante:</strong> ${attr.seo_impact || 'Este atributo aumenta significativamente o ranking nos resultados de busca.'}
                    </div>
                    <div>
                        <input type="text"
                               class="attribute-input"
                               id="attr-input-${attr.id}"
                               placeholder="Digite o valor..."
                               onchange="AttributeFiller.trackChange('${attr.id}', this.value)">
                    </div>
                    <div class="attribute-actions">
                        <button class="btn btn-sm btn-outline-primary" onclick="AttributeFiller.suggestValue('${attr.id}')">
                            <i class="bi bi-magic"></i> Sugerir Valor
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
        },

        trackChange(attrId, value) {
            if (value && value.trim() !== '') {
                this.pendingChanges[attrId] = value;
            } else {
                delete this.pendingChanges[attrId];
            }

            this.updatePendingChanges();
            this.updateApplyButton();
        },

        applySuggestion(attrId, suggestion) {
            const input = document.getElementById(`attr-input-${attrId}`);
            input.value = suggestion;
            this.trackChange(attrId, suggestion);
            SEOKiller.showSuccess('Sugestão aplicada!');
        },

        async suggestValue(attrId) {
            if (!this.currentProduct) return;

            const attr = this.attributes.find(a => a.id === attrId) ||
                this.hiddenAttributes.find(a => a.id === attrId);

            if (!attr) return;

            const input = document.getElementById(`attr-input-${attrId}`);
            const originalValue = input.value;
            input.value = 'Gerando sugestão...';
            input.disabled = true;

            try {
                const data = await requestJson('/api/seo-killer/attributes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        item_id: this.currentProduct.id,
                        attribute_id: attrId,
                        suggest_only: true
                    })
                });

                if (data.success && data.suggestion) {
                    input.value = data.suggestion;
                    this.trackChange(attrId, data.suggestion);
                    SEOKiller.showSuccess('Sugestão gerada!');
                } else {
                    throw new Error('Não foi possível gerar sugestão');
                }
            } catch (error) {
                input.value = originalValue;
                SEOKiller.showError('Erro ao gerar sugestão');
            } finally {
                input.disabled = false;
            }
        },

        async autoFillAll() {
            if (!this.currentProduct) return;

            const confirmed = confirm('Deseja preencher automaticamente TODOS os atributos faltantes?');
            if (!confirmed) return;

            SEOKiller.showLoading(document.getElementById('list-all-attrs'), 'Preenchendo atributos...');

            try {
                const data = await requestJson('/api/seo-killer/attributes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        item_id: this.currentProduct.id,
                        auto_fill: true
                    })
                });

                if (data.success) {
                    // Atualizar atributos com valores preenchidos
                    if (data.attributes) {
                        data.attributes.forEach(newAttr => {
                            const existing = this.attributes.find(a => a.id === newAttr.id);
                            if (existing) {
                                existing.value = newAttr.value;
                                existing.suggestion = newAttr.suggestion;
                            }
                        });
                    }

                    this.renderAll();
                    SEOKiller.showSuccess(`${data.filled_count || 0} atributos preenchidos!`);
                } else {
                    throw new Error(data.error || 'Erro ao preencher atributos');
                }
            } catch (error) {
                SEOKiller.showError(`Erro: ${error.message}`);
            }
        },

        async analyzeGaps() {
            if (!this.currentProduct) return;

            SEOKiller.showLoading(document.getElementById('list-missing-attrs'), 'Analisando gaps...');

            try {
                const data = await requestJson('/api/seo-killer/attributes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        item_id: this.currentProduct.id,
                        analyze_only: true,
                        deep_analysis: true
                    })
                });

                if (data.success) {
                    this.attributes = data.attributes || [];
                    this.renderAll();

                    // Abrir tab de faltantes
                    document.getElementById('tab-missing-attrs').click();

                    SEOKiller.showSuccess('Análise de gaps concluída!');
                } else {
                    throw new Error(data.error || 'Erro na análise');
                }
            } catch (error) {
                SEOKiller.showError(`Erro: ${error.message}`);
            }
        },

        updateAnalysis() {
            const total = this.attributes.length;
            const filled = this.attributes.filter(a => a.value && a.value !== '').length;
            const missing = total - filled;

            // Calculate incomplete attributes (filled but might be invalid or too short)
            const incomplete = this.attributes.filter(a => {
                // Check if value exists but might be invalid or incomplete
                return a.value && a.value.length > 0 && a.value.length < (a.min_length || 3);
            }).length;

            const completion = total > 0 ? Math.round((filled / total) * 100) : 0;

            // Atualizar círculo
            document.getElementById('attr-completion-value').textContent = completion;
            const circle = document.getElementById('attr-completion-circle');
            circle.className = 'completion-circle';
            if (completion >= 80) circle.classList.add('high');
            else if (completion >= 50) circle.classList.add('medium');
            else circle.classList.add('low');

            // Atualizar breakdown
            document.getElementById('attr-filled-count').textContent = filled;
            document.getElementById('attr-missing-count').textContent = missing;
            document.getElementById('attr-incomplete-count').textContent = incomplete;

            // Atualizar por importância
            this.updateImportanceStats('critical');
            this.updateImportanceStats('important');
            this.updateImportanceStats('optional');

            // Atualizar impacto SEO - get real score from API
            this.updateSEOScore();
        },

        async updateSEOScore() {
            if (!this.currentProduct) return;

            try {
                const scoreData = await requestJson(`/api/seo-killer/score/${this.currentProduct.id}`);
                const currentScore = scoreData.success !== false ? Math.round(scoreData.overall_score || 60) : 60;

                // Estimate potential score improvement from missing attributes
                const missing = this.attributes.filter(a => !a.value || a.value === '').length;
                const potentialScore = Math.min(100, currentScore + (missing * 2));

                document.getElementById('seo-score-current').textContent = currentScore;
                document.getElementById('seo-score-potential').textContent = potentialScore;
                document.getElementById('seo-improvement-value').textContent = potentialScore - currentScore;
            } catch (error) {
                // Fallback to estimate if API fails
                console.error('Error fetching score:', error);
                const currentScore = 60;
                const missing = this.attributes.filter(a => !a.value || a.value === '').length;
                const potentialScore = Math.min(100, currentScore + (missing * 2));
                document.getElementById('seo-score-current').textContent = currentScore;
                document.getElementById('seo-score-potential').textContent = potentialScore;
                document.getElementById('seo-improvement-value').textContent = potentialScore - currentScore;
            }
        },

        updateImportanceStats(importance) {
            const attrs = this.attributes.filter(a => a.importance === importance);
            const filled = attrs.filter(a => a.value && a.value !== '').length;
            const total = attrs.length;
            const percent = total > 0 ? Math.round((filled / total) * 100) : 0;

            document.getElementById(`${importance}-filled`).textContent = filled;
            document.getElementById(`${importance}-total`).textContent = total;
            document.getElementById(`${importance}-progress`).style.width = `${percent}%`;
        },

        updateTabCounts() {
            const total = this.attributes.length;
            const missing = this.attributes.filter(a => !a.value || a.value === '').length;
            const critical = this.attributes.filter(a => a.importance === 'critical' && (!a.value || a.value === '')).length;
            const hidden = this.hiddenAttributes.length;

            document.getElementById('count-all').textContent = total;
            document.getElementById('count-missing').textContent = missing;
            document.getElementById('count-critical').textContent = critical;
            document.getElementById('count-hidden').textContent = hidden;
        },

        updatePendingChanges() {
            const count = Object.keys(this.pendingChanges).length;
            const section = document.getElementById('pending-changes-section');
            const list = document.getElementById('pending-changes-list');

            if (count === 0) {
                section.style.display = 'none';
                return;
            }

            section.style.display = 'block';
            list.innerHTML = Object.entries(this.pendingChanges).map(([attrId, value]) => {
                const attr = this.attributes.find(a => a.id === attrId) ||
                    this.hiddenAttributes.find(a => a.id === attrId);
                return `
                <div class="pending-change-item">
                    <strong>${attr?.name || attrId}:</strong> ${value}
                </div>
            `;
            }).join('');
        },

        updateApplyButton() {
            const count = Object.keys(this.pendingChanges).length;
            const btn = document.getElementById('attr-apply-btn');
            btn.disabled = count === 0;

            if (count > 0) {
                btn.innerHTML = `<i class="bi bi-check-circle"></i> Aplicar ${count} Mudança${count > 1 ? 's' : ''}`;
            } else {
                btn.innerHTML = `<i class="bi bi-check-circle"></i> Aplicar Mudanças`;
            }
        },

        clearChanges() {
            this.pendingChanges = {};
            this.updatePendingChanges();
            this.updateApplyButton();
            SEOKiller.showInfo('Mudanças limpas');
        },

        previewChanges() {
            if (Object.keys(this.pendingChanges).length === 0) {
                SEOKiller.showError('Nenhuma mudança pendente');
                return;
            }

            const preview = Object.entries(this.pendingChanges).map(([attrId, value]) => {
                const attr = this.attributes.find(a => a.id === attrId) ||
                    this.hiddenAttributes.find(a => a.id === attrId);
                return `• ${attr?.name || attrId}: ${value}`;
            }).join('\n');

            alert(`📝 Preview das Mudanças:\n\n${preview}`);
        },

        async applyChanges() {
            if (!this.currentProduct || Object.keys(this.pendingChanges).length === 0) {
                return;
            }

            const confirmed = confirm(`Aplicar ${Object.keys(this.pendingChanges).length} mudança(s) no Mercado Livre?`);
            if (!confirmed) return;

            try {
                const data = await requestJson('/api/seo-killer/attributes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        item_id: this.currentProduct.id,
                        attributes: this.pendingChanges
                    })
                });

                if (data.success) {
                    SEOKiller.showSuccess('Atributos aplicados com sucesso!');
                    this.pendingChanges = {};
                    this.loadProduct(); // Recarregar
                    bootstrap.Modal.getInstance(document.getElementById('attributeFillerModal')).hide();
                    SEOKiller.loadDashboardData();
                } else {
                    throw new Error(data.error || 'Erro ao aplicar atributos');
                }
            } catch (error) {
                SEOKiller.showError(`Erro: ${error.message}`);
            }
        }
    };

    // Função global para abrir o modal
    window.openAttributeFiller = function() {
        const modal = new bootstrap.Modal(document.getElementById('attributeFillerModal'));
        modal.show();
        AttributeFiller.init();
    };
</script>
