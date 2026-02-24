/**
 * CatalogClone Module
 * Handles single/batch cloning, simulation, and scheduling.
 * Improved UI with account cards, drag & drop, and item preview.
 */

// requestJson is defined globally in <head> via the layout

class CatalogClone {
    constructor() {
        this.searchMode = 'single';
        this.selectedTargets = new Set();
        this.initElements();
        this.initEvents();
        this.initAccountCards();
        this.initDragDrop();
        this.initMetrics();
        this.initSchedules();
    }

    initElements() {
        // Forms & Tabs
        this.form = document.getElementById('cloneForm');
        this.singleTab = document.getElementById('single-tab');
        this.batchTab = document.getElementById('batch-tab');

        // Selects
        this.sourceSelect = document.getElementById('source_account_id');
        this.batchSourceSelect = document.getElementById('batch_source_account_id');

        // Strategy Inputs
        this.pricingType = document.getElementById('pricing_type');
        this.markupContainer = document.getElementById('markup_container');
        this.stockType = document.getElementById('stock_type');
        this.stockContainer = document.getElementById('stock_container');

        // Errors & Results
        this.sameAccountError = document.getElementById('sameAccountError');
        this.resultArea = document.getElementById('resultArea');
        this.btnClone = document.getElementById('btnClone');

        // Progress
        this.progressSection = document.getElementById('progressSection');
        this.progressBar = document.getElementById('progressBar');
        this.progressLabel = document.getElementById('progressLabel');
        this.progressPercent = document.getElementById('progressPercent');

        // Item Preview
        this.itemPreview = document.getElementById('itemPreview');
        this.btnPreviewItem = document.getElementById('btnPreviewItem');

        // Batch
        this.batchItems = document.getElementById('batch_items');
        this.itemCount = document.getElementById('itemCount');
        this.dropZone = document.getElementById('dropZone');

        // Simulation
        this.btnSimulate = document.getElementById('btnSimulate');
        this.simulationModal = document.getElementById('simulationModal') 
            ? new bootstrap.Modal(document.getElementById('simulationModal')) : null;
        this.simulationBody = document.getElementById('simulationBody');
        this.btnConfirmCloneFromSim = document.getElementById('btnConfirmCloneFromSim');

        // Search Modal Elements
        this.itemSearchModal = document.getElementById('itemSearchModal') 
            ? new bootstrap.Modal(document.getElementById('itemSearchModal')) : null;
        this.btnSearchSingle = document.getElementById('btnSearchSingle');
        this.btnSearchBatch = document.getElementById('btnSearchBatch');
        this.btnDoSearch = document.getElementById('btnDoSearch');
        this.btnSelectItems = document.getElementById('btnSelectItems');
        this.searchQuery = document.getElementById('searchQuery');
        this.searchResultsBody = document.getElementById('searchResultsBody');
        this.checkAllItems = document.getElementById('checkAllItems');
        this.searchStatus = document.getElementById('searchStatus');

        // Schedule Elements
        this.btnScheduleClone = document.getElementById('btnScheduleClone');
        this.btnClearBatch = document.getElementById('btnClearBatch');
    }

    initEvents() {
        // Validation
        if (this.sourceSelect) {
            this.sourceSelect.addEventListener('change', () => this.validateAccounts());
        }
        if (this.batchSourceSelect) {
            this.batchSourceSelect.addEventListener('change', () => this.validateAccounts());
        }
        if (this.singleTab) {
            this.singleTab.addEventListener('shown.bs.tab', () => this.validateAccounts());
        }
        if (this.batchTab) {
            this.batchTab.addEventListener('shown.bs.tab', () => this.validateAccounts());
        }

        // Strategy Toggles
        if (this.pricingType) {
            this.pricingType.addEventListener('change', (e) => {
                if (e.target.value === 'markup_percent') {
                    this.markupContainer.classList.remove('d-none');
                } else {
                    this.markupContainer.classList.add('d-none');
                }
            });
        }

        if (this.stockType) {
            this.stockType.addEventListener('change', (e) => {
                if (e.target.value === 'fixed') {
                    this.stockContainer.classList.remove('d-none');
                } else {
                    this.stockContainer.classList.add('d-none');
                }
            });
        }

        // Main Actions
        if (this.btnSimulate) {
            this.btnSimulate.addEventListener('click', () => this.simulate());
        }
        if (this.form) {
            this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        }

        // Item Preview
        if (this.btnPreviewItem) {
            this.btnPreviewItem.addEventListener('click', () => this.previewItem());
        }

        // Search Modal
        if (this.btnSearchSingle) {
            this.btnSearchSingle.addEventListener('click', () => this.openSearchModal('single'));
        }
        if (this.btnSearchBatch) {
            this.btnSearchBatch.addEventListener('click', () => this.openSearchModal('batch'));
        }
        if (this.btnDoSearch) {
            this.btnDoSearch.addEventListener('click', () => this.performSearch());
        }
        if (this.searchQuery) {
            this.searchQuery.addEventListener('keypress', (e) => { 
                if (e.key === 'Enter' && this.btnDoSearch) this.btnDoSearch.click(); 
            });
        }
        if (this.checkAllItems) {
            this.checkAllItems.addEventListener('change', (e) => {
                document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = e.target.checked);
            });
        }
        if (this.btnSelectItems) {
            this.btnSelectItems.addEventListener('click', () => this.handleItemsSelection());
        }

        // Batch Clear
        if (this.btnClearBatch) {
            this.btnClearBatch.addEventListener('click', () => {
                if (this.batchItems) {
                    this.batchItems.value = '';
                    this.updateItemCount();
                }
            });
        }

        // Batch Items count
        if (this.batchItems) {
            this.batchItems.addEventListener('input', () => this.updateItemCount());
        }

        // Schedule
        if (this.btnScheduleClone) {
            this.btnScheduleClone.addEventListener('click', () => this.createSchedule());
        }

        // Global Cancel Schedule
        window.cancelSchedule = (id) => this.cancelSchedule(id);
    }

    // Account Cards Selection
    initAccountCards() {
        const cards = document.querySelectorAll('.account-card');
        cards.forEach(card => {
            card.addEventListener('click', (e) => {
                // Don't trigger if clicking the checkbox itself
                if (e.target.type === 'checkbox') return;
                
                const checkbox = card.querySelector('.target-checkbox');
                if (!checkbox || card.classList.contains('disabled')) return;
                
                checkbox.checked = !checkbox.checked;
                card.classList.toggle('selected', checkbox.checked);
                this.updateSelectedTargets();
                this.validateAccounts();
            });

            // Also handle direct checkbox changes
            const checkbox = card.querySelector('.target-checkbox');
            if (checkbox) {
                checkbox.addEventListener('change', () => {
                    card.classList.toggle('selected', checkbox.checked);
                    this.updateSelectedTargets();
                    this.validateAccounts();
                });
            }
        });

        // Listen for source account changes to disable matching target
        [this.sourceSelect, this.batchSourceSelect].forEach(select => {
            if (select) {
                select.addEventListener('change', () => this.updateDisabledTargets());
            }
        });
    }

    updateSelectedTargets() {
        this.selectedTargets.clear();
        document.querySelectorAll('.target-checkbox:checked').forEach(cb => {
            this.selectedTargets.add(cb.value);
        });
    }

    updateDisabledTargets() {
        const isBatch = this.batchTab && this.batchTab.classList.contains('active');
        const sourceId = isBatch ? this.batchSourceSelect?.value : this.sourceSelect?.value;

        document.querySelectorAll('.account-card').forEach(card => {
            const accountId = card.dataset.accountId;
            const checkbox = card.querySelector('.target-checkbox');
            
            if (accountId === sourceId) {
                card.classList.add('disabled');
                if (checkbox) {
                    checkbox.checked = false;
                    checkbox.disabled = true;
                }
                card.classList.remove('selected');
            } else {
                card.classList.remove('disabled');
                if (checkbox) checkbox.disabled = false;
            }
        });
        this.updateSelectedTargets();
    }

    // Drag and Drop for batch import
    initDragDrop() {
        if (!this.dropZone) return;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, () => {
                this.dropZone.classList.add('drag-over');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            this.dropZone.addEventListener(eventName, () => {
                this.dropZone.classList.remove('drag-over');
            });
        });

        this.dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.handleFileUpload(files[0]);
            }
        });

        this.dropZone.addEventListener('click', () => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.txt,.csv';
            input.onchange = (e) => {
                if (e.target.files.length > 0) {
                    this.handleFileUpload(e.target.files[0]);
                }
            };
            input.click();
        });
    }

    handleFileUpload(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const content = e.target.result;
            const ids = content.split(/[\n,;\s]+/)
                .map(id => id.trim())
                .filter(id => id.match(/^MLB\d+$/i));
            
            if (ids.length > 0 && this.batchItems) {
                const current = this.batchItems.value.trim();
                this.batchItems.value = current ? current + '\n' + ids.join('\n') : ids.join('\n');
                this.updateItemCount();
                Toast.success(`${ids.length} IDs importados!`);
            } else {
                Toast.warning('Nenhum ID válido encontrado no arquivo.');
            }
        };
        reader.readAsText(file);
    }

    updateItemCount() {
        if (!this.batchItems || !this.itemCount) return;
        const ids = this.batchItems.value.split('\n')
            .map(id => id.trim())
            .filter(id => id !== '');
        this.itemCount.textContent = ids.length;
    }

    // Item Preview
    async previewItem() {
        const itemId = document.getElementById('source_item_id')?.value?.trim();
        const accountId = this.sourceSelect?.value;

        if (!itemId || !accountId) {
            Toast.warning('Preencha a conta e o ID do item.');
            return;
        }

        if (!this.itemPreview) return;

        this.itemPreview.innerHTML = `
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-primary"></div>
                <small class="d-block mt-1">Carregando...</small>
            </div>
        `;

        try {
            const data = await requestJson(`/api/items/${itemId}?account_id=${accountId}`);

            if (data.status === 'error') {
                this.itemPreview.innerHTML = `
                    <div class="text-center text-danger py-3">
                        <i class="bi bi-exclamation-circle fs-3 d-block mb-2"></i>
                        <small>${data.message || 'Item não encontrado'}</small>
                    </div>
                `;
                return;
            }

            const item = data.item || data;
            const thumb = item.thumbnail || item.pictures?.[0]?.url || 'https://http2.mlstatic.com/resources/frontend/statics/lo-view/no-image.png';
            const price = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: item.currency_id || 'BRL' }).format(item.price || 0);
            const isCatalog = item.catalog_product_id ? '<span class="badge bg-success preview-badge">Catálogo</span>' : '<span class="badge bg-secondary preview-badge">Normal</span>';
            const condition = item.condition === 'new' ? '<span class="badge bg-info preview-badge">Novo</span>' : '<span class="badge bg-warning preview-badge">Usado</span>';

            this.itemPreview.innerHTML = `
                <div class="d-flex gap-3">
                    <img src="${thumb}" class="preview-image" alt="Preview">
                    <div class="flex-grow-1">
                        <div class="mb-1">${isCatalog} ${condition}</div>
                        <p class="preview-title mb-1">${item.title || 'Sem título'}</p>
                        <div class="preview-price">${price}</div>
                        <small class="text-muted">Estoque: ${item.available_quantity || 0}</small>
                    </div>
                </div>
            `;
        } catch (error) {
            this.itemPreview.innerHTML = `
                <div class="text-center text-danger py-3">
                    <i class="bi bi-exclamation-circle fs-3 d-block mb-2"></i>
                    <small>Erro ao carregar item</small>
                </div>
            `;
        }
    }

    validateAccounts() {
        const isBatch = this.batchTab && this.batchTab.classList.contains('active');
        const activeSource = isBatch ? this.batchSourceSelect : this.sourceSelect;
        const sourceValue = activeSource?.value;

        // Update disabled targets
        this.updateDisabledTargets();

        // Check if source is selected as target
        if (sourceValue && this.selectedTargets.has(sourceValue)) {
            if (this.sameAccountError) this.sameAccountError.classList.remove('d-none');
            if (this.btnClone) this.btnClone.disabled = true;
            if (this.btnSimulate) this.btnSimulate.disabled = true;
        } else {
            if (this.sameAccountError) this.sameAccountError.classList.add('d-none');
            if (this.btnClone) this.btnClone.disabled = false;
            if (this.btnSimulate) this.btnSimulate.disabled = false;
        }
    }

    getSelectedTargetIds() {
        return Array.from(document.querySelectorAll('.target-checkbox:checked')).map(cb => cb.value);
    }

    async simulate() {
        const isBatch = this.batchTab && this.batchTab.classList.contains('active');
        const activeSource = isBatch ? this.batchSourceSelect : this.sourceSelect;
        const sourceItemId = document.getElementById('source_item_id')?.value;
        const targetIds = this.getSelectedTargetIds();

        if (!activeSource?.value || !sourceItemId || targetIds.length === 0) {
            Toast.warning('Preencha Conta Origem, Item Origem e Conta(s) Destino.');
            return;
        }

        if (this.simulationModal) this.simulationModal.show();
        if (this.simulationBody) {
            this.simulationBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2 small">Calculando preços e verificando...</p></div>';
        }
        if (this.btnConfirmCloneFromSim) this.btnConfirmCloneFromSim.style.display = 'none';

        try {
            const formData = new FormData(this.form);
            const data = {
                source_account_id: activeSource.value,
                source_item_id: sourceItemId,
                target_account_id: targetIds[0], // Simule first target
                pricing_strategy: {
                    type: document.getElementById('pricing_type').value,
                    value: document.getElementById('pricing_value').value
                }
            };

            const result = await requestJson('/api/catalog/clone/simulate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            if (result.status === 'success') {
                let dupAlert = '';
                if (result.is_duplicate) {
                    dupAlert = `<div class="alert alert-warning small mb-2"><i class="bi bi-exclamation-triangle"></i> Atenção: Este item já existe na conta destino!</div>`;
                }

                this.simulationBody.innerHTML = `
                    <div class="px-2">
                        <h6 class="fw-bold text-success">${result.item_title}</h6>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted d-block">Preço Original</small>
                                <span class="fw-bold">R$ ${result.original_price}</span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Preço Final (${result.strategy_applied})</small>
                                <span class="fw-bold fs-5 text-primary">R$ ${result.final_price}</span>
                            </div>
                        </div>
                        <div class="mt-3">
                             ${dupAlert}
                             <p class="text-muted small mb-0"><i class="bi bi-info-circle"></i> Clone será replicado para <strong>${targetIds.length}</strong> conta(s).</p>
                        </div>
                    </div>
                `;
                if (this.btnConfirmCloneFromSim) {
                    this.btnConfirmCloneFromSim.style.display = 'block';
                    this.btnConfirmCloneFromSim.onclick = () => {
                        if (this.simulationModal) this.simulationModal.hide();
                        this.form.dispatchEvent(new Event('submit'));
                    };
                }

            } else {
                if (this.simulationBody) {
                    this.simulationBody.innerHTML = `<div class="alert alert-danger small">${result.message}</div>`;
                }
            }

        } catch (e) {
            if (this.simulationBody) {
                this.simulationBody.innerHTML = `<div class="alert alert-danger small">Erro na simulação: ${e.message}</div>`;
            }
        }
    }

    async handleSubmit(e) {
        e.preventDefault();

        const isBatch = this.batchTab && this.batchTab.classList.contains('active');
        const activeSource = isBatch ? this.batchSourceSelect : this.sourceSelect;
        const targetIds = this.getSelectedTargetIds();

        if (targetIds.includes(activeSource?.value)) {
            Toast.error('A conta origem não pode ser uma das contas destino.');
            return;
        }

        if (targetIds.length === 0) {
            Toast.warning('Selecione ao menos uma conta destino.');
            return;
        }

        const formData = new FormData(this.form);
        let data = {
            target_account_ids: targetIds,
            pricing_strategy: {
                type: formData.get('pricing_strategy[type]'),
                value: formData.get('pricing_strategy[value]')
            },
            stock_strategy: {
                type: formData.get('stock_strategy[type]'),
                value: formData.get('stock_strategy[value]')
            }
        };

        let endpoint = '/api/catalog/clone';

        if (isBatch) {
            endpoint = '/api/catalog/clone/batch';
            data.source_account_id = formData.get('batch_source_account_id');
            const itemsText = this.batchItems?.value || '';
            data.items = itemsText.split('\n').map(item => item.trim()).filter(item => item !== '');

            if (data.items.length === 0) {
                Toast.warning('Informe pelo menos um ID de anúncio.');
                return;
            }
        } else {
            data.source_account_id = formData.get('source_account_id');
            data.source_item_id = formData.get('source_item_id');

            if (!data.source_item_id) {
                Toast.warning('Informe o ID do anúncio origem.');
                return;
            }
        }

        // Show progress
        this.showProgress(true);
        this.setCloneButtonLoading(true);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (response.ok || response.status === 201 || response.status === 202) {
                if (isBatch) {
                    // Start Polling for Batch
                    if (result.job_ids && result.job_ids.length > 0) {
                        this.startPolling(result.job_ids, result.jobs_count);
                    } else {
                        // Fallback message
                        this.showBatchSuccess(result);
                        this.showProgress(false);
                        this.setCloneButtonLoading(false);
                    }
                } else {
                    // Single Item Result
                    this.showSingleResult(result);
                    this.showProgress(false);
                    this.setCloneButtonLoading(false);
                }
                this.form.reset();
                this.resetAccountCards();
            } else {
                this.showError(result.message || 'Erro desconhecido');
                this.showProgress(false);
                this.setCloneButtonLoading(false);
            }

        } catch (error) {
            console.error('Error:', error);
            this.showError('Erro de sistema ao processar requisição.');
            this.showProgress(false);
            this.setCloneButtonLoading(false);
        }
    }

    showProgress(show) {
        if (this.progressSection) {
            this.progressSection.classList.toggle('d-none', !show);
        }
        if (show && this.progressBar) {
            this.progressBar.style.width = '0%';
        }
    }

    setCloneButtonLoading(loading) {
        if (!this.btnClone) return;
        if (loading) {
            this.btnClone.disabled = true;
            this.btnClone.innerHTML = '<span class="spinner-border spinner-border-sm spin"></span> Processando...';
        } else {
            this.btnClone.disabled = false;
            this.btnClone.innerHTML = '<i class="bi bi-files me-1"></i> Clonar';
        }
    }

    resetAccountCards() {
        document.querySelectorAll('.account-card').forEach(card => {
            card.classList.remove('selected');
            const cb = card.querySelector('.target-checkbox');
            if (cb) cb.checked = false;
        });
        this.selectedTargets.clear();
        if (this.itemPreview) {
            this.itemPreview.innerHTML = `
                <div class="text-center text-muted py-2">
                    <i class="bi bi-image display-6 d-block mb-2 opacity-50"></i>
                    <small>Clique em <i class="bi bi-eye"></i> para ver o preview do item</small>
                </div>
            `;
        }
    }

    updateProgress(percent, label) {
        if (this.progressBar) this.progressBar.style.width = percent + '%';
        if (this.progressPercent) this.progressPercent.textContent = percent + '%';
        if (this.progressLabel && label) this.progressLabel.textContent = label;
    }

    showBatchSuccess(result) {
        if (this.resultArea) {
            this.resultArea.innerHTML = `
                <div class="text-center">
                    <div class="text-success mb-3">
                        <i class="bi bi-check-circle-fill display-4"></i>
                    </div>
                    <h6 class="fw-bold text-success">Processamento Iniciado!</h6>
                    <p class="small text-muted mb-2">${result.message || 'Jobs criados com sucesso'}</p>
                    <span class="badge bg-primary fs-6">${result.jobs_count || 0} jobs</span>
                </div>
            `;
        }
        setTimeout(() => {
            this.setCloneButtonLoading(false);
            this.initMetrics();
        }, 2000);
    }

    showSingleResult(result) {
        if (!this.resultArea) return;

        if (result.results) {
            // Multi-account result
            let successCount = 0;
            let errorCount = 0;
            let details = '';
            
            for (const [tid, res] of Object.entries(result.results)) {
                const isSuccess = res.status === 'success';
                if (isSuccess) successCount++;
                else errorCount++;
                
                const icon = isSuccess ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>';
                details += `
                    <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                        <span class="small">${icon} Conta ${tid}</span>
                        <span class="small text-muted text-truncate ms-2" style="max-width:120px">${res.message || ''}</span>
                    </div>
                `;
            }

            this.resultArea.innerHTML = `
                <div class="text-center mb-3">
                    <i class="bi bi-check-all display-4 text-success"></i>
                    <h6 class="fw-bold mt-2">Concluído!</h6>
                    <div class="d-flex justify-content-center gap-3 small">
                        <span class="text-success">${successCount} ok</span>
                        <span class="text-danger">${errorCount} erros</span>
                    </div>
                </div>
                <div class="small">${details}</div>
            `;
        } else {
            const itemId = result.target_item_id || '';
            const mlLink = itemId ? `https://produto.mercadolivre.com.br/MLB-${itemId.replace('MLB', '')}` : '#';
            
            this.resultArea.innerHTML = `
                <div class="text-center">
                    <div class="text-success mb-3">
                        <i class="bi bi-check-circle-fill display-4"></i>
                    </div>
                    <h6 class="fw-bold text-success">Sucesso!</h6>
                    <p class="small text-muted mb-2">${result.message || 'Item clonado'}</p>
                    ${itemId ? `
                        <div class="mb-2">
                            <code class="bg-light px-2 py-1 rounded">${itemId}</code>
                        </div>
                        <a href="${mlLink}" target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Ver no ML
                        </a>
                    ` : ''}
                </div>
            `;
        }
    }

    showError(msg) {
        if (this.resultArea) {
            this.resultArea.innerHTML = `
                <div class="text-center">
                    <div class="text-warning mb-3">
                        <i class="bi bi-exclamation-triangle-fill display-4"></i>
                    </div>
                    <h6 class="fw-bold text-warning">Atenção</h6>
                    <p class="small text-muted">${msg}</p>
                </div>
            `;
        }
    }

    // --- Search Modal ---
    openSearchModal(mode) {
        this.searchMode = mode;
        const activeSource = mode === 'single' ? this.sourceSelect : this.batchSourceSelect;

        if (!activeSource?.value) {
            Toast.warning('Selecione uma conta de origem primeiro.');
            return;
        }

        if (this.searchResultsBody) {
            this.searchResultsBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Digite algo para buscar.</td></tr>';
        }
        if (this.searchQuery) this.searchQuery.value = '';
        if (this.searchStatus) this.searchStatus.textContent = '';
        if (this.itemSearchModal) {
            this.itemSearchModal.show();
            setTimeout(() => this.searchQuery?.focus(), 500);
        }
    }

    async performSearch() {
        const activeSource = this.searchMode === 'single' ? this.sourceSelect : this.batchSourceSelect;
        const query = this.searchQuery?.value?.trim();

        if (!query) return;

        if (this.btnDoSearch) {
            this.btnDoSearch.disabled = true;
            this.btnDoSearch.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        }

        try {
            const data = await requestJson(`/api/items?account_id=${activeSource?.value}&q=${encodeURIComponent(query)}&limit=20`);

            if (this.searchResultsBody) this.searchResultsBody.innerHTML = '';

            if (data.items && data.items.length > 0) {
                data.items.forEach(item => {
                    const row = document.createElement('tr');
                    const price = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: item.currency_id || 'BRL' }).format(item.price || 0);
                    const thumb = item.thumbnail || 'https://http2.mlstatic.com/resources/frontend/statics/lo-view/no-image.png';

                    row.innerHTML = `
                        <td><input type="checkbox" class="item-checkbox form-check-input" value="${item.id}"></td>
                        <td><img src="${thumb}" style="width: 40px; height: 40px; object-fit: contain; border-radius: 4px;"></td>
                        <td><small class="text-truncate d-block" style="max-width: 200px">${item.title}</small></td>
                        <td><small class="fw-semibold">${price}</small></td>
                        <td><code class="small">${item.id}</code></td>
                    `;
                    if (this.searchResultsBody) this.searchResultsBody.appendChild(row);
                });
                if (this.searchStatus) this.searchStatus.textContent = `${data.items.length} itens encontrados.`;
            } else {
                if (this.searchResultsBody) {
                    this.searchResultsBody.innerHTML = '<tr><td colspan="5" class="text-center py-3">Nenhum item encontrado.</td></tr>';
                }
            }
        } catch (error) {
            console.error(error);
            if (this.searchResultsBody) {
                this.searchResultsBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Erro ao buscar itens.</td></tr>';
            }
        } finally {
            if (this.btnDoSearch) {
                this.btnDoSearch.disabled = false;
                this.btnDoSearch.innerHTML = '<i class="bi bi-search"></i>';
            }
        }
    }

    handleItemsSelection() {
        const selected = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.value);

        if (selected.length === 0) {
            Toast.warning('Selecione pelo menos um item.');
            return;
        }

        if (this.searchMode === 'single') {
            if (selected.length > 1) {
                Toast.warning('No modo individual, selecione apenas um item.');
                return;
            }
            const input = document.getElementById('source_item_id');
            if (input) input.value = selected[0];
            // Trigger preview
            this.previewItem();
        } else {
            if (this.batchItems) {
                const currentVal = this.batchItems.value.trim();
                const newIds = selected.join('\n');
                this.batchItems.value = currentVal ? currentVal + '\n' + newIds : newIds;
                this.updateItemCount();
            }
        }

        if (this.itemSearchModal) this.itemSearchModal.hide();
    }

    // --- Polling ---
    startPolling(jobIds, total) {
        let interval = setInterval(async () => {
            try {
                const data = await requestJson('/api/jobs/status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ job_ids: jobIds })
                });

                let completed = 0;
                let failed = 0;

                if (data.details) {
                    for (const [id, job] of Object.entries(data.details)) {
                        if (job.status === 'completed') completed++;
                        else if (job.status === 'failed') failed++;
                    }
                }

                const totalProcessed = completed + failed;
                const percent = total > 0 ? Math.round((totalProcessed / total) * 100) : 0;

                this.updateProgress(percent, `${totalProcessed}/${total} processados`);
                this.updatePollUI(percent, completed, failed, total - totalProcessed);

                if (totalProcessed >= total) {
                    clearInterval(interval);
                    this.setCloneButtonLoading(false);
                    this.showProgress(false);
                    Toast.success('Processamento concluído!');
                    this.initMetrics();
                }
            } catch (e) {
                console.error("Polling error", e);
            }
        }, 2000);

        this.updatePollUI(0, 0, 0, total); // Initial render
    }

    updatePollUI(percent, completed, failed, pending) {
        if (!this.resultArea) return;
        
        this.resultArea.innerHTML = `
            <div class="text-center">
                <div class="position-relative d-inline-block mb-3">
                    <svg width="80" height="80" viewBox="0 0 80 80">
                        <circle cx="40" cy="40" r="35" fill="none" stroke="#e9ecef" stroke-width="6"/>
                        <circle cx="40" cy="40" r="35" fill="none" stroke="#667eea" stroke-width="6"
                            stroke-dasharray="${2 * Math.PI * 35}" 
                            stroke-dashoffset="${2 * Math.PI * 35 * (1 - percent / 100)}"
                            transform="rotate(-90 40 40)"
                            style="transition: stroke-dashoffset 0.5s ease"/>
                    </svg>
                    <div class="position-absolute top-50 start-50 translate-middle fw-bold">${percent}%</div>
                </div>
                <h6 class="fw-bold mb-3">Processando...</h6>
                <div class="d-flex justify-content-center gap-4 small">
                    <div><span class="badge bg-success">${completed}</span> OK</div>
                    <div><span class="badge bg-danger">${failed}</span> Erro</div>
                    <div><span class="badge bg-secondary">${pending}</span> Pend.</div>
                </div>
            </div>
        `;
    }

    // --- Metrics & Schedules ---
    initMetrics() {
        if (!document.getElementById('todayClones')) return;
        
        requestJson('/api/catalog/clone/metrics')
            .then(data => {
                const el = (id, val) => {
                    const elem = document.getElementById(id);
                    if (elem) elem.textContent = val;
                };
                el('todayClones', data.today || 0);
                el('successRate', (data.success_rate || 0) + '%');
                el('totalClones', data.total || 0);
                el('avgPerHour', data.avg_per_hour || 0);
                el('pendingJobs', data.pending || 0);
                el('errorCount', data.errors || 0);
            })
            .catch(e => console.warn('Metrics fetch failed:', e));
    }

    initSchedules() {
        const container = document.getElementById('activeSchedules');
        if (!container) return;

        requestJson('/api/catalog/clone/schedules')
            .then(data => {
                if (data.schedules && data.schedules.length > 0) {
                    container.innerHTML = data.schedules.map(s => `
                        <div class="d-flex align-items-center justify-content-between p-2 mb-2 bg-white rounded shadow-sm">
                            <div class="small">
                                <strong>${s.source_account}</strong> → <strong>${s.target_account}</strong>
                                <br><span class="text-muted">${s.scheduled_at || ''}</span>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="cancelSchedule(${s.id})">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-calendar-x fs-1 d-block mb-2 opacity-50"></i>
                            <small>Nenhum agendamento ativo</small>
                        </div>
                    `;
                }
            })
            .catch(e => console.warn('Schedules fetch failed:', e));
    }

    async createSchedule() {
        const sourceAccount = document.getElementById('schedule_source_account')?.value;
        const targetAccount = document.getElementById('schedule_target_account')?.value;
        const date = document.getElementById('schedule_date')?.value;
        const time = document.getElementById('schedule_time')?.value;
        const frequency = document.getElementById('schedule_frequency')?.value;

        if (!sourceAccount || !targetAccount || !date || !time) {
            Toast.warning('Preencha todos os campos do agendamento.');
            return;
        }

        try {
            const result = await requestJson('/api/catalog/clone/schedules', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    source_account_id: sourceAccount,
                    target_account_id: targetAccount,
                    scheduled_date: date,
                    scheduled_time: time,
                    frequency: frequency
                })
            });
            
            if (result.status === 'success') {
                Toast.success('Agendamento criado!');
                this.initSchedules();
            } else {
                Toast.error(result.message || 'Erro ao criar agendamento');
            }
        } catch (e) {
            Toast.error('Erro ao criar agendamento');
        }
    }

    cancelSchedule(id) {
        if (!confirm('Cancelar este agendamento?')) return;
        
        requestJson(`/api/catalog/clone/schedules/${id}`, { method: 'DELETE' })
            .then(d => {
                if (d.status === 'success') {
                    Toast.success('Agendamento cancelado');
                    this.initSchedules();
                } else {
                    Toast.error('Erro ao cancelar: ' + (d.message || ''));
                }
            })
            .catch(() => Toast.error('Erro ao cancelar agendamento'));
    }
}

// Toast helper (fallback if not defined)
window.Toast = window.Toast || {
    success: (msg) => console.log('✓', msg),
    error: (msg) => console.error('✗', msg),
    warning: (msg) => console.warn('⚠', msg),
    info: (msg) => console.info('ℹ', msg)
};

document.addEventListener('DOMContentLoaded', () => {
    window.catalogClone = new CatalogClone();

    // Periodic refresh
    setInterval(() => window.catalogClone.initMetrics(), 30000);
});
