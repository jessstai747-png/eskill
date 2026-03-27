<!-- Modal Gerador de Descrições -->
<div class="modal fade" id="descriptionGeneratorModal" tabindex="-1" aria-labelledby="descriptionGeneratorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width: 90vw;">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #E67E22 0%, #D35400 100%); color: white;">
                <h5 class="modal-title" id="descriptionGeneratorModalLabel">
                    <i class="bi bi-file-text"></i> Gerador de Descrições Matadoras
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Coluna Esquerda: Editor e Templates -->
                    <div class="col-lg-8">
                        <!-- Seleção de Produto -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <label class="form-label fw-bold">Selecione o Produto</label>
                                <select class="form-select" id="desc-product-select" onchange="DescriptionGenerator.loadProduct()">
                                    <option value="">Escolha um produto...</option>
                                </select>
                            </div>
                        </div>

                        <!-- Templates Prontos -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="bi bi-file-earmark-text"></i> Templates Prontos</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <div class="template-card" onclick="DescriptionGenerator.useTemplate('electronics')">
                                            <div class="template-icon">💻</div>
                                            <div class="template-name">Eletrônicos</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="template-card" onclick="DescriptionGenerator.useTemplate('fashion')">
                                            <div class="template-icon">👔</div>
                                            <div class="template-name">Moda/Vestuário</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="template-card" onclick="DescriptionGenerator.useTemplate('home')">
                                            <div class="template-icon">🏠</div>
                                            <div class="template-name">Casa/Decoração</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="template-card" onclick="DescriptionGenerator.useTemplate('sports')">
                                            <div class="template-icon">⚽</div>
                                            <div class="template-name">Esportes</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="template-card" onclick="DescriptionGenerator.useTemplate('beauty')">
                                            <div class="template-icon">💄</div>
                                            <div class="template-name">Beleza</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="template-card" onclick="DescriptionGenerator.useTemplate('generic')">
                                            <div class="template-icon">📦</div>
                                            <div class="template-name">Genérico</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Editor de Descrição -->
                        <div class="card mb-3">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="bi bi-pencil-square"></i> Editor de Descrição</h6>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="DescriptionGenerator.generate()" title="Gerar Automaticamente">
                                        <i class="bi bi-magic"></i> Gerar
                                    </button>
                                    <button class="btn btn-outline-success" onclick="DescriptionGenerator.improve()" title="Melhorar Descrição Atual">
                                        <i class="bi bi-arrow-up-circle"></i> Melhorar
                                    </button>
                                    <button class="btn btn-outline-info" onclick="DescriptionGenerator.analyze()" title="Analisar Qualidade">
                                        <i class="bi bi-graph-up"></i> Analisar
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <!-- Toolbar de Formatação -->
                                <div class="editor-toolbar">
                                    <div class="btn-group btn-group-sm me-2">
                                        <button class="btn btn-light" onclick="DescriptionGenerator.format('bold')" title="Negrito">
                                            <i class="bi bi-type-bold"></i>
                                        </button>
                                        <button class="btn btn-light" onclick="DescriptionGenerator.format('italic')" title="Itálico">
                                            <i class="bi bi-type-italic"></i>
                                        </button>
                                        <button class="btn btn-light" onclick="DescriptionGenerator.format('underline')" title="Sublinhado">
                                            <i class="bi bi-type-underline"></i>
                                        </button>
                                    </div>
                                    <div class="btn-group btn-group-sm me-2">
                                        <button class="btn btn-light" onclick="DescriptionGenerator.format('ul')" title="Lista com Bullets">
                                            <i class="bi bi-list-ul"></i>
                                        </button>
                                        <button class="btn btn-light" onclick="DescriptionGenerator.format('ol')" title="Lista Numerada">
                                            <i class="bi bi-list-ol"></i>
                                        </button>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light" onclick="DescriptionGenerator.insertBlock('features')" title="Adicionar Características">
                                            <i class="bi bi-star"></i> Características
                                        </button>
                                        <button class="btn btn-light" onclick="DescriptionGenerator.insertBlock('specs')" title="Adicionar Especificações">
                                            <i class="bi bi-list-check"></i> Especificações
                                        </button>
                                        <button class="btn btn-light" onclick="DescriptionGenerator.insertBlock('warranty')" title="Adicionar Garantia">
                                            <i class="bi bi-shield-check"></i> Garantia
                                        </button>
                                    </div>
                                </div>

                                <!-- Área de Edição -->
                                <div id="desc-editor" contenteditable="true" class="description-editor"
                                    oninput="DescriptionGenerator.updateMetrics()"
                                    placeholder="Digite ou gere uma descrição aqui...">
                                </div>
                            </div>
                        </div>

                        <!-- Preview -->
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="bi bi-eye"></i> Preview (Como aparece no ML)</h6>
                            </div>
                            <div class="card-body">
                                <div id="desc-preview" class="description-preview">
                                    A descrição aparecerá aqui...
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Coluna Direita: Análise em Tempo Real -->
                    <div class="col-lg-4">
                        <div class="card sticky-top" style="top: 20px;">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-speedometer2"></i> Análise em Tempo Real</h6>
                            </div>
                            <div class="card-body">
                                <!-- Score Geral -->
                                <div class="text-center mb-4">
                                    <div class="score-circle-large mb-2" id="desc-score-circle">
                                        <div class="score-value" id="desc-score-value">0</div>
                                        <div class="score-label">/ 100</div>
                                    </div>
                                    <h6 class="text-muted">Score de Qualidade</h6>
                                </div>

                                <!-- Métricas -->
                                <div class="metrics-list">
                                    <div class="metric-item">
                                        <div class="metric-label">
                                            <i class="bi bi-fonts"></i> Caracteres
                                        </div>
                                        <div class="metric-value">
                                            <span id="desc-char-count">0</span> / 500 mín
                                        </div>
                                        <div class="progress mt-1" style="height: 4px;">
                                            <div class="progress-bar" id="desc-char-progress" style="width: 0%"></div>
                                        </div>
                                    </div>

                                    <div class="metric-item">
                                        <div class="metric-label">
                                            <i class="bi bi-key"></i> Densidade de Keywords
                                        </div>
                                        <div class="metric-value">
                                            <span id="desc-keyword-density">0</span>%
                                        </div>
                                    </div>

                                    <div class="metric-item">
                                        <div class="metric-label">
                                            <i class="bi bi-book"></i> Legibilidade
                                        </div>
                                        <div class="metric-value">
                                            <span id="desc-readability">-</span>
                                        </div>
                                    </div>

                                    <div class="metric-item">
                                        <div class="metric-label">
                                            <i class="bi bi-list-ul"></i> Parágrafos
                                        </div>
                                        <div class="metric-value">
                                            <span id="desc-paragraph-count">0</span>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <!-- Checklist de Qualidade -->
                                <h6 class="mb-3">Checklist de Qualidade</h6>
                                <div class="quality-checklist">
                                    <div class="checklist-item" id="check-min-chars">
                                        <i class="bi bi-circle"></i> Mínimo 500 caracteres
                                    </div>
                                    <div class="checklist-item" id="check-paragraphs">
                                        <i class="bi bi-circle"></i> Múltiplos parágrafos
                                    </div>
                                    <div class="checklist-item" id="check-features">
                                        <i class="bi bi-circle"></i> Lista de características
                                    </div>
                                    <div class="checklist-item" id="check-keywords">
                                        <i class="bi bi-circle"></i> Keywords relevantes
                                    </div>
                                    <div class="checklist-item" id="check-warranty">
                                        <i class="bi bi-circle"></i> Informação de garantia
                                    </div>
                                    <div class="checklist-item" id="check-cta">
                                        <i class="bi bi-circle"></i> Call to Action
                                    </div>
                                </div>

                                <hr>

                                <!-- Sugestões -->
                                <h6 class="mb-3">💡 Sugestões</h6>
                                <div id="desc-suggestions" class="suggestions-list">
                                    <small class="text-muted">Digite para receber sugestões...</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-outline-primary" onclick="DescriptionGenerator.saveDraft()">
                    <i class="bi bi-save"></i> Salvar Rascunho
                </button>
                <button type="button" class="btn btn-success" onclick="DescriptionGenerator.apply()" id="desc-apply-btn" disabled>
                    <i class="bi bi-check-circle"></i> Aplicar no ML
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .template-card {
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
    }

    .template-card:hover {
        border-color: #E67E22;
        background: #FDF2E9;
        transform: translateY(-3px);
        box-shadow: 0 4px 8px rgba(230, 126, 34, 0.2);
    }

    .template-card .template-icon {
        font-size: 32px;
        margin-bottom: 8px;
    }

    .template-card .template-name {
        font-size: 13px;
        font-weight: 600;
        color: #333;
    }

    .editor-toolbar {
        background: #f8f9fa;
        padding: 10px;
        border-bottom: 1px solid #dee2e6;
    }

    .description-editor {
        min-height: 400px;
        padding: 20px;
        font-size: 15px;
        line-height: 1.6;
        outline: none;
        overflow-y: auto;
    }

    .description-editor:empty:before {
        content: attr(placeholder);
        color: #999;
    }

    .description-preview {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        min-height: 200px;
        font-size: 14px;
        line-height: 1.6;
        color: #333;
    }

    .score-circle-large {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: white;
        border: 6px solid #e0e0e0;
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }

    .score-circle-large.high {
        border-color: #27AE60;
    }

    .score-circle-large.medium {
        border-color: #F39C12;
    }

    .score-circle-large.low {
        border-color: #E74C3C;
    }

    .score-value {
        font-size: 36px;
        font-weight: bold;
        line-height: 1;
    }

    .score-label {
        font-size: 14px;
        color: #666;
    }

    .metrics-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .metric-item {
        display: flex;
        flex-direction: column;
    }

    .metric-label {
        font-size: 13px;
        color: #666;
        margin-bottom: 5px;
    }

    .metric-value {
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }

    .quality-checklist {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .checklist-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #666;
    }

    .checklist-item i {
        font-size: 16px;
    }

    .checklist-item.completed {
        color: #27AE60;
    }

    .checklist-item.completed i:before {
        content: "\F26B";
        /* bi-check-circle-fill */
    }

    .suggestions-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .suggestion-item {
        background: #E8F5E9;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 12px;
        color: #2E7D32;
        cursor: pointer;
        transition: all 0.2s;
    }

    .suggestion-item:hover {
        background: #C8E6C9;
    }
</style>

<script nonce="<?= CSP_NONCE ?>">
    const DescriptionGenerator = {
        currentProduct: null,
        templates: {
            electronics: `<h3>🎯 Características Principais</h3>
<ul>
<li>Alta performance e tecnologia de ponta</li>
<li>Design moderno e elegante</li>
<li>Fácil instalação e uso</li>
<li>Compatível com diversos dispositivos</li>
</ul>

<h3>📋 Especificações Técnicas</h3>
<ul>
<li><strong>Marca:</strong> [MARCA]</li>
<li><strong>Modelo:</strong> [MODELO]</li>
<li><strong>Garantia:</strong> 12 meses</li>
</ul>

<h3>📦 Conteúdo da Embalagem</h3>
<ul>
<li>1x Produto</li>
<li>Manual de instruções em português</li>
<li>Nota fiscal</li>
</ul>

<h3>✅ Por que comprar conosco?</h3>
<p>✓ Produto 100% original<br>
✓ Garantia oficial do fabricante<br>
✓ Entrega rápida e segura<br>
✓ Suporte pós-venda especializado</p>`,

            fashion: `<h3>👔 Sobre o Produto</h3>
<p>Peça de alta qualidade, perfeita para compor looks incríveis. Confeccionada com materiais premium que garantem conforto e durabilidade.</p>

<h3>🎯 Características</h3>
<ul>
<li>Tecido de alta qualidade</li>
<li>Acabamento impecável</li>
<li>Cores vivas e resistentes</li>
<li>Caimento perfeito</li>
</ul>

<h3>📏 Guia de Medidas</h3>
<p>Consulte a tabela de medidas nas fotos do anúncio para escolher o tamanho ideal.</p>

<h3>🧺 Cuidados</h3>
<p>• Lavar à mão ou máquina (ciclo delicado)<br>
• Não usar alvejante<br>
• Secar à sombra</p>

<h3>✨ Compre com Confiança</h3>
<p>✓ Produto novo e lacrado<br>
✓ Envio rápido<br>
✓ Embalagem cuidadosa<br>
✓ Satisfação garantida</p>`,

            home: `<h3>🏠 Transforme seu Espaço</h3>
<p>Produto ideal para dar um toque especial à sua casa. Combina funcionalidade com design elegante.</p>

<h3>⭐ Destaques</h3>
<ul>
<li>Design moderno e sofisticado</li>
<li>Materiais de primeira qualidade</li>
<li>Fácil instalação/montagem</li>
<li>Durabilidade comprovada</li>
</ul>

<h3>📐 Dimensões</h3>
<p>[INSERIR MEDIDAS]</p>

<h3>🎨 Disponível em</h3>
<p>[INSERIR CORES/VARIAÇÕES]</p>

<h3>✅ Garantia de Qualidade</h3>
<p>✓ Produto novo<br>
✓ Embalagem original<br>
✓ Garantia do fabricante<br>
✓ Entrega segura</p>`,

            sports: `<h3>⚽ Produto Esportivo de Alta Performance</h3>
<p>Desenvolvido para atletas e entusiastas que buscam o melhor desempenho e durabilidade.</p>

<h3>🎯 Características Principais</h3>
<ul>
<li>Tecnologia avançada</li>
<li>Materiais resistentes e leves</li>
<li>Design ergonômico</li>
<li>Ideal para treinos intensos</li>
</ul>

<h3>📊 Especificações</h3>
<ul>
<li><strong>Marca:</strong> [MARCA]</li>
<li><strong>Modelo:</strong> [MODELO]</li>
<li><strong>Tamanho:</strong> [TAMANHO]</li>
</ul>

<h3>🏆 Por que Escolher Este Produto?</h3>
<p>✓ Qualidade profissional<br>
✓ Aprovado por atletas<br>
✓ Ótimo custo-benefício<br>
✓ Garantia de satisfação</p>`,

            beauty: `<h3>💄 Beleza e Cuidado Premium</h3>
<p>Produto de alta qualidade para cuidar da sua beleza com segurança e eficácia.</p>

<h3>✨ Benefícios</h3>
<ul>
<li>Resultados visíveis</li>
<li>Fórmula dermatologicamente testada</li>
<li>Ingredientes de qualidade</li>
<li>Para todos os tipos de pele/cabelo</li>
</ul>

<h3>🧴 Como Usar</h3>
<p>[INSERIR MODO DE USO]</p>

<h3>📋 Ingredientes Principais</h3>
<p>[INSERIR INGREDIENTES]</p>

<h3>💝 Garantia de Autenticidade</h3>
<p>✓ Produto 100% original<br>
✓ Registro na ANVISA<br>
✓ Validade longa<br>
✓ Lacrado de fábrica</p>`,

            generic: `<h3>📦 Sobre o Produto</h3>
<p>Produto de excelente qualidade, ideal para suas necessidades. [DESCREVER PRODUTO]</p>

<h3>⭐ Características</h3>
<ul>
<li>[CARACTERÍSTICA 1]</li>
<li>[CARACTERÍSTICA 2]</li>
<li>[CARACTERÍSTICA 3]</li>
<li>[CARACTERÍSTICA 4]</li>
</ul>

<h3>📋 Especificações</h3>
<ul>
<li><strong>Marca:</strong> [MARCA]</li>
<li><strong>Modelo:</strong> [MODELO]</li>
<li><strong>Garantia:</strong> [GARANTIA]</li>
</ul>

<h3>✅ Por que Comprar Conosco?</h3>
<p>✓ Produto original<br>
✓ Envio rápido<br>
✓ Garantia<br>
✓ Suporte especializado</p>`
        },

        init() {
            console.log('Description Generator initialized');
            this.loadProducts();
            this.updateMetrics();
        },

        async loadProducts() {
            const select = document.getElementById('desc-product-select');

            try {
                const {
                    data
                } = await requestJson('/api/items?limit=100');

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
            const select = document.getElementById('desc-product-select');
            const productId = select.value;

            if (!productId) return;

            try {
                const {
                    data: item
                } = await requestJson(`/api/items/${productId}`);

                this.currentProduct = item;
                SEOKiller.showInfo('Produto carregado. Gere ou escolha um template.');
            } catch (error) {
                SEOKiller.showError('Erro ao carregar produto');
            }
        },

        useTemplate(type) {
            const editor = document.getElementById('desc-editor');
            let content = this.templates[type] || this.templates.generic;

            // Substituir placeholders se tiver produto carregado
            if (this.currentProduct) {
                const brand = this.extractAttribute('BRAND') || '[MARCA]';
                const model = this.extractAttribute('MODEL') || '[MODELO]';

                content = content.replace(/\[MARCA\]/g, brand);
                content = content.replace(/\[MODELO\]/g, model);
            }

            editor.innerHTML = content;
            this.updateMetrics();
            this.updatePreview();

            SEOKiller.showSuccess(`Template "${type}" aplicado!`);
        },

        extractAttribute(attrId) {
            const attr = this.currentProduct?.attributes?.find(a => a.id === attrId);
            return attr?.value_name || '';
        },

        format(command) {
            document.execCommand(command, false, null);
            this.updatePreview();
        },

        insertBlock(type) {
            const blocks = {
                features: '<h3>🎯 Características Principais</h3>\n<ul>\n<li>Característica 1</li>\n<li>Característica 2</li>\n<li>Característica 3</li>\n</ul>\n',
                specs: '<h3>📋 Especificações Técnicas</h3>\n<ul>\n<li><strong>Item 1:</strong> Valor</li>\n<li><strong>Item 2:</strong> Valor</li>\n</ul>\n',
                warranty: '<h3>🛡️ Garantia</h3>\n<p>• Garantia de 12 meses<br>\n• Suporte técnico especializado<br>\n• Peças de reposição disponíveis</p>\n'
            };

            const editor = document.getElementById('desc-editor');
            editor.innerHTML += blocks[type] || '';
            this.updateMetrics();
            this.updatePreview();
        },

        async generate() {
            if (!this.currentProduct) {
                SEOKiller.showError('Selecione um produto primeiro');
                return;
            }

            const editor = document.getElementById('desc-editor');
            SEOKiller.showLoading(editor, 'Gerando descrição matadora...');

            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/description', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        item_id: this.currentProduct.id,
                        title: this.currentProduct.title
                    })
                });

                if (data.success && data.description) {
                    editor.innerHTML = data.description;
                    this.updateMetrics();
                    this.updatePreview();
                    SEOKiller.showSuccess('Descrição gerada com sucesso!');
                } else {
                    throw new Error(data.error || 'Erro ao gerar descrição');
                }
            } catch (error) {
                editor.innerHTML = '';
                SEOKiller.showError(`Erro: ${error.message}`);
            }
        },

        async improve() {
            const editor = document.getElementById('desc-editor');
            const currentText = editor.innerText.trim();

            if (!currentText) {
                SEOKiller.showError('Digite ou gere uma descrição primeiro');
                return;
            }

            const originalContent = editor.innerHTML;
            SEOKiller.showLoading(editor, 'Melhorando descrição...');

            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/description', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        description: currentText,
                        improve: true
                    })
                });

                if (data.success && data.description) {
                    editor.innerHTML = data.description;
                    this.updateMetrics();
                    this.updatePreview();
                    SEOKiller.showSuccess('Descrição melhorada!');
                } else {
                    throw new Error(data.error || 'Erro ao melhorar descrição');
                }
            } catch (error) {
                editor.innerHTML = originalContent;
                SEOKiller.showError(`Erro: ${error.message}`);
            }
        },

        async analyze() {
            const editor = document.getElementById('desc-editor');
            const text = editor.innerText.trim();

            if (!text) {
                SEOKiller.showError('Digite uma descrição para analisar');
                return;
            }

            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/description/analyze', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        description: text
                    })
                });

                if (data.success) {
                    this.updateMetricsFromAnalysis(data);
                    SEOKiller.showSuccess('Análise concluída!');
                }
            } catch (error) {
                SEOKiller.showError('Erro ao analisar descrição');
            }
        },

        updateMetrics() {
            const editor = document.getElementById('desc-editor');
            const text = editor.innerText.trim();
            const html = editor.innerHTML;

            // Caracteres
            const charCount = text.length;
            document.getElementById('desc-char-count').textContent = charCount;

            const charPercent = Math.min(100, (charCount / 500) * 100);
            document.getElementById('desc-char-progress').style.width = `${charPercent}%`;
            document.getElementById('desc-char-progress').className =
                `progress-bar ${charCount >= 500 ? 'bg-success' : charCount >= 300 ? 'bg-warning' : 'bg-danger'}`;

            // Parágrafos
            const paragraphs = html.split(/<\/p>|<\/h[1-6]>|<br>/).filter(p => p.trim()).length;
            document.getElementById('desc-paragraph-count').textContent = paragraphs;

            // Calcular score
            const score = this.calculateScore(charCount, paragraphs, html);
            this.updateScore(score);

            // Atualizar checklist
            this.updateChecklist(charCount, paragraphs, html, text);

            // Atualizar preview
            this.updatePreview();

            // Habilitar botão aplicar se score >= 70
            document.getElementById('desc-apply-btn').disabled = score < 70;
        },

        calculateScore(charCount, paragraphs, html) {
            let score = 0;

            // Caracteres (30 pontos)
            if (charCount >= 500) score += 30;
            else if (charCount >= 300) score += 20;
            else score += (charCount / 500) * 30;

            // Parágrafos (20 pontos)
            if (paragraphs >= 3) score += 20;
            else score += (paragraphs / 3) * 20;

            // Formatação (20 pontos)
            if (html.includes('<ul>') || html.includes('<ol>')) score += 10;
            if (html.includes('<strong>') || html.includes('<b>')) score += 5;
            if (html.includes('<h')) score += 5;

            // Estrutura (30 pontos)
            if (html.toLowerCase().includes('características') || html.toLowerCase().includes('benefícios')) score += 10;
            if (html.toLowerCase().includes('especificações') || html.toLowerCase().includes('técnica')) score += 10;
            if (html.toLowerCase().includes('garantia')) score += 10;

            return Math.round(score);
        },

        updateScore(score) {
            document.getElementById('desc-score-value').textContent = score;

            const circle = document.getElementById('desc-score-circle');
            circle.className = 'score-circle-large';
            if (score >= 80) circle.classList.add('high');
            else if (score >= 50) circle.classList.add('medium');
            else circle.classList.add('low');
        },

        updateChecklist(charCount, paragraphs, html, text) {
            const checks = {
                'check-min-chars': charCount >= 500,
                'check-paragraphs': paragraphs >= 3,
                'check-features': html.includes('<ul>') || html.includes('<ol>'),
                'check-keywords': text.length > 100,
                'check-warranty': text.toLowerCase().includes('garantia'),
                'check-cta': text.toLowerCase().includes('comprar') || text.toLowerCase().includes('aproveite')
            };

            Object.entries(checks).forEach(([id, completed]) => {
                const element = document.getElementById(id);
                if (completed) {
                    element.classList.add('completed');
                } else {
                    element.classList.remove('completed');
                }
            });
        },

        updatePreview() {
            const editor = document.getElementById('desc-editor');
            const preview = document.getElementById('desc-preview');
            preview.innerHTML = editor.innerHTML || 'A descrição aparecerá aqui...';
        },

        updateMetricsFromAnalysis(data) {
            if (data.score) {
                this.updateScore(data.score);
            }
            if (data.readability) {
                document.getElementById('desc-readability').textContent = data.readability;
            }
            if (data.keyword_density) {
                document.getElementById('desc-keyword-density').textContent = Math.round(data.keyword_density);
            }
        },

        saveDraft() {
            const editor = document.getElementById('desc-editor');
            const content = editor.innerHTML;

            localStorage.setItem('desc_draft_' + (this.currentProduct?.id || 'temp'), content);
            SEOKiller.showSuccess('Rascunho salvo!');
        },

        async apply() {
            if (!this.currentProduct) {
                SEOKiller.showError('Selecione um produto primeiro');
                return;
            }

            const editor = document.getElementById('desc-editor');
            const description = editor.innerText.trim();

            if (!description) {
                SEOKiller.showError('A descrição não pode estar vazia');
                return;
            }

            const confirmed = confirm('Tem certeza que deseja aplicar esta descrição no Mercado Livre?');
            if (!confirmed) return;

            try {
                const {
                    data
                } = await requestJson(`/api/items/${this.currentProduct.id}/description`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        plain_text: description
                    })
                });

                if (data.success) {
                    SEOKiller.showSuccess('Descrição aplicada com sucesso!');
                    bootstrap.Modal.getInstance(document.getElementById('descriptionGeneratorModal')).hide();
                    SEOKiller.loadDashboardData();
                } else {
                    throw new Error(data.error || 'Erro ao aplicar descrição');
                }
            } catch (error) {
                SEOKiller.showError(`Erro: ${error.message}`);
            }
        }
    };

    // Função global para abrir o modal
    window.openDescriptionGenerator = function() {
        const modal = new bootstrap.Modal(document.getElementById('descriptionGeneratorModal'));
        modal.show();
        DescriptionGenerator.init();
    };
</script>