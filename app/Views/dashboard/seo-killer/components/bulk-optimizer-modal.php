<!-- Modal de Otimização em Lote -->
<div class="modal fade" id="bulkOptimizerModal" tabindex="-1" aria-labelledby="bulkOptimizerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-custom modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-gradient" style="background: linear-gradient(135deg, #FF4500 0%, #FF6347 100%);">
                <h5 class="modal-title text-white" id="bulkOptimizerModalLabel">
                    <i class="bi bi-lightning-charge-fill"></i> Otimização em Lote
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body seo-scrollbar">
                <!-- Steps Indicator -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="step-item active" data-step="1">
                                <div class="step-circle">1</div>
                                <div class="step-label">Selecionar</div>
                            </div>
                            <div class="step-line"></div>
                            <div class="step-item" data-step="2">
                                <div class="step-circle">2</div>
                                <div class="step-label">Configurar</div>
                            </div>
                            <div class="step-line"></div>
                            <div class="step-item" data-step="3">
                                <div class="step-circle">3</div>
                                <div class="step-label">Processar</div>
                            </div>
                            <div class="step-line"></div>
                            <div class="step-item" data-step="4">
                                <div class="step-circle">4</div>
                                <div class="step-label">Resultados</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 1: Seleção de Itens -->
                <div id="bulk-step-1" class="bulk-step active">
                    <h5 class="mb-3">Selecione os anúncios para otimizar</h5>
                    
                    <!-- Filtros -->
                    <div class="seo-filters">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="filter-group">
                                    <label>Categoria</label>
                                    <select class="form-select form-select-sm" id="bulk-filter-category">
                                        <option value="">Todas</option>
                                        <option value="electronics">Eletrônicos</option>
                                        <option value="fashion">Moda</option>
                                        <option value="home">Casa</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="filter-group">
                                    <label>Score SEO</label>
                                    <select class="form-select form-select-sm" id="bulk-filter-score">
                                        <option value="">Todos</option>
                                        <option value="0-30">Crítico (&lt; 30)</option>
                                        <option value="30-50">Baixo (30-50)</option>
                                        <option value="50-70">Médio (50-70)</option>
                                        <option value="70-100">Alto (70+)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="filter-group">
                                    <label>Status</label>
                                    <select class="form-select form-select-sm" id="bulk-filter-status">
                                        <option value="">Todos</option>
                                        <option value="no-title">Sem título otimizado</option>
                                        <option value="no-desc">Sem descrição</option>
                                        <option value="no-attrs">Faltam atributos</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="filter-group">
                                    <label>Buscar</label>
                                    <input type="text" class="form-control form-control-sm" id="bulk-search" placeholder="ID ou nome...">
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <button class="btn btn-sm btn-primary" onclick="BulkOptimizer.applyFilters()">
                                    <i class="bi bi-funnel"></i> Aplicar Filtros
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="BulkOptimizer.clearFilters()">
                                    <i class="bi bi-x-circle"></i> Limpar
                                </button>
                                <span class="ms-3 text-muted" id="bulk-items-count">0 anúncios encontrados</span>
                            </div>
                        </div>
                    </div>

                    <!-- Seleção Rápida -->
                    <div class="mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="bulk-select-all">
                            <label class="form-check-label fw-bold" for="bulk-select-all">
                                Selecionar Todos
                            </label>
                        </div>
                        <span class="ms-3 badge bg-primary" id="bulk-selected-count">0 selecionados</span>
                    </div>

                    <!-- Lista de Itens -->
                    <div id="bulk-items-list" class="mb-3" style="max-height: 400px; overflow-y: auto;">
                        <!-- Skeleton loading -->
                        <div class="skeleton skeleton-card"></div>
                        <div class="skeleton skeleton-card"></div>
                        <div class="skeleton skeleton-card"></div>
                    </div>
                </div>

                <!-- Step 2: Configuração -->
                <div id="bulk-step-2" class="bulk-step" style="display: none;">
                    <h5 class="mb-3">Configure as otimizações</h5>
                    
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-3 text-muted">O que deseja otimizar?</h6>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="opt-title" checked>
                                <label class="form-check-label" for="opt-title">
                                    <strong>Otimizar Títulos</strong>
                                    <p class="mb-0 small text-muted">Gera títulos otimizados com keywords relevantes (45-60 chars)</p>
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="opt-description" checked>
                                <label class="form-check-label" for="opt-description">
                                    <strong>Otimizar Descrições</strong>
                                    <p class="mb-0 small text-muted">Cria descrições persuasivas e bem estruturadas (mín. 500 chars)</p>
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="opt-attributes" checked>
                                <label class="form-check-label" for="opt-attributes">
                                    <strong>Preencher Atributos</strong>
                                    <p class="mb-0 small text-muted">Completa atributos faltantes e ocultos da categoria</p>
                                </label>
                            </div>

                            <hr class="my-4">

                            <h6 class="card-subtitle mb-3 text-muted">Opções Avançadas</h6>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="opt-auto-apply">
                                <label class="form-check-label" for="opt-auto-apply">
                                    <strong>Aplicar Automaticamente</strong>
                                    <p class="mb-0 small text-muted">Aplica mudanças direto no ML (sem revisão prévia)</p>
                                    <div class="alert alert-warning alert-sm mt-2 mb-0" style="font-size: 12px;">
                                        <i class="bi bi-exclamation-triangle"></i> Use com cuidado! Recomendamos revisar antes de aplicar.
                                    </div>
                                </label>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><strong>Limite de itens por vez</strong></label>
                                <input type="range" class="form-range" id="opt-batch-size" min="5" max="50" value="20" step="5">
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">5</small>
                                    <span class="badge bg-primary" id="opt-batch-size-value">20</span>
                                    <small class="text-muted">50</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Processamento -->
                <div id="bulk-step-3" class="bulk-step" style="display: none;">
                    <h5 class="mb-3">Otimizando anúncios...</h5>
                    
                    <!-- Progress Geral -->
                    <div class="seo-progress-container">
                        <div class="d-flex justify-content-between mb-2">
                            <span><strong>Progresso Geral</strong></span>
                            <span id="bulk-progress-text">0 / 0 (0%)</span>
                        </div>
                        <div class="progress" style="height: 30px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                 id="bulk-progress-bar" 
                                 role="progressbar" 
                                 style="width: 0%"
                                 aria-valuenow="0" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                0%
                            </div>
                        </div>
                    </div>

                    <!-- Status Individual -->
                    <div id="bulk-processing-list" class="mt-4">
                        <!-- Items sendo processados aparecerão aqui -->
                    </div>

                    <!-- Controles -->
                    <div class="mt-4 text-center">
                        <button class="btn btn-warning" id="bulk-pause-btn" onclick="BulkOptimizer.pause()">
                            <i class="bi bi-pause-circle"></i> Pausar
                        </button>
                        <button class="btn btn-danger" id="bulk-stop-btn" onclick="BulkOptimizer.stop()" style="display: none;">
                            <i class="bi bi-stop-circle"></i> Parar
                        </button>
                    </div>
                </div>

                <!-- Step 4: Resultados -->
                <div id="bulk-step-4" class="bulk-step" style="display: none;">
                    <h5 class="mb-3">Otimização Concluída! 🎉</h5>
                    
                    <!-- Resumo -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-success" id="result-success-count">0</h3>
                                    <p class="mb-0">Sucesso</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-danger" id="result-error-count">0</h3>
                                    <p class="mb-0">Erros</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-info" id="result-avg-improvement">+0</h3>
                                    <p class="mb-0">Melhoria Média</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3 class="text-primary" id="result-time">0s</h3>
                                    <p class="mb-0">Tempo Total</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela de Resultados -->
                    <div class="table-responsive">
                        <table class="table seo-results-table">
                            <thead>
                                <tr>
                                    <th>Anúncio</th>
                                    <th>Score Antes</th>
                                    <th>Score Depois</th>
                                    <th>Melhoria</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="bulk-results-tbody">
                                <!-- Resultados aparecerão aqui -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Ações -->
                    <div class="mt-4 text-center">
                        <button class="btn btn-primary" onclick="BulkOptimizer.exportResults()">
                            <i class="bi bi-download"></i> Exportar Relatório (CSV)
                        </button>
                        <button class="btn btn-outline-primary" onclick="BulkOptimizer.restart()">
                            <i class="bi bi-arrow-clockwise"></i> Nova Otimização
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="bulk-prev-btn" onclick="BulkOptimizer.prevStep()" style="display: none;">
                    <i class="bi bi-arrow-left"></i> Anterior
                </button>
                <button type="button" class="btn btn-seo-killer" id="bulk-next-btn" onclick="BulkOptimizer.nextStep()">
                    Próximo <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Steps Indicator */
.step-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
}

.step-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e0e0e0;
    color: #999;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-bottom: 8px;
    transition: all 0.3s;
}

.step-item.active .step-circle {
    background: #FF4500;
    color: white;
    box-shadow: 0 0 0 4px rgba(255, 69, 0, 0.2);
}

.step-item.completed .step-circle {
    background: #00A650;
    color: white;
}

.step-label {
    font-size: 12px;
    color: #666;
    font-weight: 500;
}

.step-item.active .step-label {
    color: #FF4500;
    font-weight: 600;
}

.step-line {
    flex: 1;
    height: 2px;
    background: #e0e0e0;
    margin: 0 10px;
    align-self: flex-start;
    margin-top: 20px;
}

.bulk-step {
    min-height: 400px;
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

const BulkOptimizer = {
    currentStep: 1,
    selectedItems: new Set(),
    allItems: [],
    config: {},
    results: [],
    jobId: null,
    startTime: null,

    init() {
        console.log('Bulk Optimizer initialized');
        this.loadItems();
        this.bindEvents();
    },

    bindEvents() {
        // Select all checkbox
        document.getElementById('bulk-select-all')?.addEventListener('change', (e) => {
            this.toggleSelectAll(e.target.checked);
        });

        // Batch size slider
        document.getElementById('opt-batch-size')?.addEventListener('input', (e) => {
            document.getElementById('opt-batch-size-value').textContent = e.target.value;
        });

        // Search debounce
        const searchInput = document.getElementById('bulk-search');
        if (searchInput) {
            searchInput.addEventListener('input', SEOKiller.utils.debounce(() => {
                this.applyFilters();
            }, 500));
        }
    },

    async loadItems() {
        const list = document.getElementById('bulk-items-list');
        
        try {
            const data = await SEOKiller.utils.fetchAPI('/api/seo-killer/bulk/select?limit=50');
            
            if (data.success && data.items) {
                this.allItems = data.items;
                this.renderItems(data.items);
                document.getElementById('bulk-items-count').textContent = `${data.items.length} anúncios encontrados`;
            }
        } catch (error) {
            list.innerHTML = '<div class="alert alert-danger">Erro ao carregar anúncios</div>';
        }
    },

    renderItems(items) {
        const list = document.getElementById('bulk-items-list');
        
        if (!items || items.length === 0) {
            list.innerHTML = '<div class="alert alert-info">Nenhum anúncio encontrado com os filtros selecionados</div>';
            return;
        }

        list.innerHTML = items.map(item => this.renderItemCard(item)).join('');
    },

    renderItemCard(item) {
        const scoreClass = SEOKiller.utils.getScoreBadgeClass(item.score || 0);
        const isSelected = this.selectedItems.has(item.id);
        
        return `
            <div class="seo-item-card ${isSelected ? 'selected' : ''}" data-item-id="${item.id}" onclick="BulkOptimizer.toggleItem('${item.id}')">
                <div class="d-flex align-items-center">
                    <div class="form-check me-3">
                        <input class="form-check-input" type="checkbox" ${isSelected ? 'checked' : ''} onclick="event.stopPropagation()">
                    </div>
                    <img src="${normalizeExternalUrl(item.thumbnail) || '/assets/no-image.png'}" alt="${item.title}" class="item-thumbnail me-3">
                    <div class="flex-grow-1">
                        <p class="item-title">${item.title}</p>
                        <small class="text-muted">ID: ${item.id}</small>
                    </div>
                    <div class="text-end">
                        <span class="score-badge ${scoreClass}">${item.score || 0}/100</span>
                    </div>
                </div>
            </div>
        `;
    },

    toggleItem(itemId) {
        if (this.selectedItems.has(itemId)) {
            this.selectedItems.delete(itemId);
        } else {
            this.selectedItems.add(itemId);
        }
        this.updateSelectedCount();
        this.renderItems(this.allItems);
    },

    toggleSelectAll(checked) {
        if (checked) {
            this.allItems.forEach(item => this.selectedItems.add(item.id));
        } else {
            this.selectedItems.clear();
        }
        this.updateSelectedCount();
        this.renderItems(this.allItems);
    },

    updateSelectedCount() {
        document.getElementById('bulk-selected-count').textContent = `${this.selectedItems.size} selecionados`;
    },

    applyFilters() {
        // Implementar lógica de filtros
        SEOKiller.showInfo('Aplicando filtros...');
        this.loadItems();
    },

    clearFilters() {
        document.getElementById('bulk-filter-category').value = '';
        document.getElementById('bulk-filter-score').value = '';
        document.getElementById('bulk-filter-status').value = '';
        document.getElementById('bulk-search').value = '';
        this.applyFilters();
    },

    nextStep() {
        if (this.currentStep === 1 && this.selectedItems.size === 0) {
            SEOKiller.showError('Selecione pelo menos um anúncio');
            return;
        }

        if (this.currentStep === 2) {
            this.startOptimization();
            return;
        }

        this.goToStep(this.currentStep + 1);
    },

    prevStep() {
        this.goToStep(this.currentStep - 1);
    },

    goToStep(step) {
        // Esconder step atual
        document.getElementById(`bulk-step-${this.currentStep}`).style.display = 'none';
        document.querySelector(`[data-step="${this.currentStep}"]`).classList.remove('active');
        
        // Mostrar novo step
        this.currentStep = step;
        document.getElementById(`bulk-step-${step}`).style.display = 'block';
        document.querySelector(`[data-step="${step}"]`).classList.add('active');
        
        // Atualizar botões
        document.getElementById('bulk-prev-btn').style.display = step > 1 && step < 4 ? 'inline-block' : 'none';
        document.getElementById('bulk-next-btn').style.display = step < 3 ? 'inline-block' : 'none';
        
        if (step === 2) {
            document.getElementById('bulk-next-btn').innerHTML = '<i class="bi bi-play-fill"></i> Iniciar Otimização';
        }
    },

    async startOptimization() {
        this.goToStep(3);
        this.startTime = Date.now();
        
        this.config = {
            optimize_title: document.getElementById('opt-title').checked,
            optimize_description: document.getElementById('opt-description').checked,
            fill_attributes: document.getElementById('opt-attributes').checked,
            auto_apply: document.getElementById('opt-auto-apply').checked,
            batch_size: parseInt(document.getElementById('opt-batch-size').value)
        };

        try {
            const response = await SEOKiller.utils.fetchAPI('/api/seo-killer/bulk/start', {
                method: 'POST',
                body: JSON.stringify({
                    item_ids: Array.from(this.selectedItems),
                    options: this.config
                })
            });

            if (response.success) {
                this.jobId = response.job_id;
                this.monitorProgress();
            }
        } catch (error) {
            SEOKiller.showError('Erro ao iniciar otimização');
            this.goToStep(2);
        }
    },

    async monitorProgress() {
        const interval = setInterval(async () => {
            try {
                const data = await SEOKiller.utils.fetchAPI(`/api/seo-killer/bulk/status/${this.jobId}`);
                
                if (data.success) {
                    this.updateProgress(data);
                    
                    if (data.status === 'completed' || data.status === 'failed') {
                        clearInterval(interval);
                        this.showResults(data);
                    }
                }
            } catch (error) {
                clearInterval(interval);
                SEOKiller.showError('Erro ao monitorar progresso');
            }
        }, 2000);
    },

    updateProgress(data) {
        const percent = Math.round((data.processed / data.total) * 100);
        const bar = document.getElementById('bulk-progress-bar');
        bar.style.width = `${percent}%`;
        bar.textContent = `${percent}%`;
        bar.setAttribute('aria-valuenow', percent);
        
        document.getElementById('bulk-progress-text').textContent = 
            `${data.processed} / ${data.total} (${percent}%)`;
    },

    showResults(data) {
        this.goToStep(4);
        
        const elapsed = Math.round((Date.now() - this.startTime) / 1000);
        
        document.getElementById('result-success-count').textContent = data.success_count || 0;
        document.getElementById('result-error-count').textContent = data.error_count || 0;
        document.getElementById('result-avg-improvement').textContent = `+${data.avg_improvement || 0}`;
        document.getElementById('result-time').textContent = `${elapsed}s`;
        
        // Renderizar tabela de resultados
        const tbody = document.getElementById('bulk-results-tbody');
        if (data.results) {
            tbody.innerHTML = data.results.map(result => `
                <tr>
                    <td>${result.title}</td>
                    <td><span class="badge bg-secondary">${result.score_before}</span></td>
                    <td><span class="badge bg-success">${result.score_after}</span></td>
                    <td><span class="badge bg-info">+${result.improvement}</span></td>
                    <td>
                        <span class="status-badge ${result.status}">
                            ${result.status === 'success' ? '✓' : '✗'} ${result.status}
                        </span>
                    </td>
                </tr>
            `).join('');
        }
    },

    exportResults() {
        SEOKiller.showInfo('Exportando relatório...');
        // Implementar export CSV
    },

    restart() {
        this.currentStep = 1;
        this.selectedItems.clear();
        this.results = [];
        this.goToStep(1);
        this.loadItems();
    },

    pause() {
        SEOKiller.showInfo('Pausando processamento...');
    },

    stop() {
        SEOKiller.showInfo('Parando processamento...');
    }
};

// Função global para abrir o modal
window.showBulkOptimizer = function() {
    const modal = new bootstrap.Modal(document.getElementById('bulkOptimizerModal'));
    modal.show();
    BulkOptimizer.init();
};
</script>
