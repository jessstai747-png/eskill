/* eslint-env browser */
/**
 * Ads Wizard — Criação de campanha em 3 passos para leigos
 */
const AdsWizard = {
    currentStep: 1,
    selectedItems: [],
    products: [],
    budget: 20,

    init() {
        this.loadProducts();
        this.bindEvents();
    },

    /**
     * Wrapper seguro para fetch com tratamento de erros HTTP
     */
    async apiFetch(url, options = {}) {
        const requester = window.ApiClient && typeof window.ApiClient.json === 'function'
            ? window.ApiClient.json
            : async (requestUrl, requestOptions = {}) => {
                const response = await fetch(requestUrl, requestOptions);
                const data = await response.json();
                return { response, data };
            };

        const { response, data } = await requester(url, options);

        if (response.status === 401 || response.status === 403) {
            window.location.href = '/login';
            throw new Error('Sessão expirada');
        }

        if (!response.ok && !data.success) {
            throw new Error(data.error || 'Erro do servidor (' + response.status + ')');
        }

        return data;
    },

    escapeHtml(str) {
        if (!str) {
            return '';
        }
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    },

    bindEvents() {
        // Navigation
        document.getElementById('btn-step-2')?.addEventListener('click', () => this.goToStep(2));
        document.getElementById('btn-step-3')?.addEventListener('click', () => this.goToStep(3));
        document.getElementById('btn-back-1')?.addEventListener('click', () => this.goToStep(1));
        document.getElementById('btn-back-2')?.addEventListener('click', () => this.goToStep(2));
        document.getElementById('btn-create')?.addEventListener('click', () => this.createCampaign());

        // Budget sync
        const budgetInput = document.getElementById('wizard-budget');
        const budgetSlider = document.getElementById('wizard-budget-slider');
        if (budgetInput && budgetSlider) {
            budgetInput.addEventListener('input', () => {
                budgetSlider.value = budgetInput.value;
                this.budget = parseFloat(budgetInput.value) || 20;
                this.updateEstimates();
            });
            budgetSlider.addEventListener('input', () => {
                budgetInput.value = budgetSlider.value;
                this.budget = parseFloat(budgetSlider.value) || 20;
                this.updateEstimates();
            });
        }

        // Product search
        document.getElementById('product-search')?.addEventListener('input', (e) => {
            this.filterProducts(e.target.value);
        });
    },

    goToStep(step) {
        if (step === 2 && this.selectedItems.length === 0) {
            this.showError('Selecione pelo menos um produto antes de avançar.');
            return;
        }

        this.currentStep = step;

        document.querySelectorAll('.wizard-panel').forEach((p) => {
            p.style.display = 'none';
        });

        const panel = document.getElementById('step-' + step);
        if (panel) {
            panel.style.display = '';
        }

        for (let i = 1; i <= 3; i++) {
            const indicator = document.getElementById('step-indicator-' + i);
            if (!indicator) {
                continue;
            }
            indicator.classList.remove('active', 'completed');
            if (i < step) {
                indicator.classList.add('completed');
            } else if (i === step) {
                indicator.classList.add('active');
            }
        }

        if (step === 2) {
            this.loadBudgetSuggestion();
        }
        if (step === 3) {
            this.renderSummary();
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    async loadProducts() {
        try {
            const data = await this.apiFetch('/api/ads/products');

            const loading = document.getElementById('products-loading');
            const grid = document.getElementById('products-grid');
            const empty = document.getElementById('products-empty');

            if (loading) {
                loading.style.display = 'none';
            }

            if (!data.success || !data.products || data.products.length === 0) {
                if (empty) {
                    empty.style.display = '';
                }
                return;
            }

            this.products = data.products;
            this.renderProducts(data.products);

            if (grid) {
                grid.style.display = '';
            }
        } catch (error) {
            const loading = document.getElementById('products-loading');
            if (loading) {
                loading.innerHTML = '<div class="text-center py-4">'
                    + '<i class="bi bi-exclamation-triangle text-warning" style="font-size:2rem"></i>'
                    + '<p class="text-muted mt-2">' + this.escapeHtml(error.message) + '</p>'
                    + '<button class="btn btn-sm btn-outline-primary" onclick="AdsWizard.loadProducts()">'
                    + '<i class="bi bi-arrow-clockwise me-1"></i>Tentar novamente</button></div>';
            }
        }
    },

    renderProducts(products) {
        const grid = document.getElementById('products-grid');
        if (!grid) {
            return;
        }

        const money = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0);

        let html = '';
        products.forEach((p) => {
            const hasThumb = p.thumbnail && p.thumbnail.length > 5;
            const imgSrc = hasThumb ? p.thumbnail.replace('-I.jpg', '-O.jpg') : '';
            const title = this.escapeHtml(p.title || 'Sem título');
            const imgHtml = hasThumb
                ? '<img src="' + imgSrc + '" alt="" class="rounded mb-2" style="width:80px;height:80px;object-fit:contain" loading="lazy">'
                : '<div class="bg-light rounded d-flex align-items-center justify-content-center mb-2" style="width:80px;height:80px"><i class="bi bi-image text-muted fs-3"></i></div>';

            html += '<div class="col-6 col-md-4 col-lg-3">';
            html += '<div class="card border shadow-sm h-100 product-card position-relative" data-item-id="' + this.escapeHtml(p.item_id) + '">';
            html += '<div class="product-check"><i class="bi bi-check"></i></div>';
            html += '<div class="card-body text-center p-3">';
            html += imgHtml;
            html += '<p class="small fw-bold mb-1 text-truncate" title="' + title + '">' + title + '</p>';
            html += '<p class="text-primary fw-bold mb-1">' + money(p.price) + '</p>';
            html += '<div class="d-flex justify-content-center gap-2 small text-muted">';
            html += '<span><i class="bi bi-box me-1"></i>' + (p.stock || 0) + '</span>';
            html += '<span><i class="bi bi-cart-check me-1"></i>' + (p.sold || 0) + ' vendidos</span>';
            html += '</div>';
            if (p.suggestion) {
                html += '<p class="small text-success mt-2 mb-0"><i class="bi bi-lightbulb me-1"></i>' + this.escapeHtml(p.suggestion) + '</p>';
            }
            html += '</div></div></div>';
        });

        grid.innerHTML = html;

        // Click handlers
        grid.querySelectorAll('.product-card').forEach((card) => {
            card.addEventListener('click', () => {
                const itemId = card.dataset.itemId;
                this.toggleProduct(itemId, card);
            });
        });
    },

    toggleProduct(itemId, card) {
        const idx = this.selectedItems.indexOf(itemId);
        if (idx >= 0) {
            this.selectedItems.splice(idx, 1);
            card.classList.remove('selected');
        } else {
            this.selectedItems.push(itemId);
            card.classList.add('selected');
        }

        const countEl = document.getElementById('selected-count');
        const nextBtn = document.getElementById('btn-step-2');
        if (countEl) {
            countEl.textContent = this.selectedItems.length;
        }
        if (nextBtn) {
            nextBtn.disabled = this.selectedItems.length === 0;
        }
    },

    filterProducts(query) {
        const filtered = query
            ? this.products.filter((p) => (p.title || '').toLowerCase().includes(query.toLowerCase()))
            : this.products;
        this.renderProducts(filtered);

        // Re-apply selections
        filtered.forEach((p) => {
            if (this.selectedItems.includes(p.item_id)) {
                const card = document.querySelector('[data-item-id="' + p.item_id + '"]');
                if (card) {
                    card.classList.add('selected');
                }
            }
        });
    },

    async loadBudgetSuggestion() {
        const explanation = document.getElementById('budget-explanation');
        if (explanation) {
            explanation.textContent = 'Calculando a melhor sugestão...';
        }

        try {
            const data = await this.apiFetch('/api/ads/suggest-budget', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items: this.selectedItems }),
            });

            if (data.success && data.budget) {
                const suggestion = data.budget;
                this.budget = suggestion.suggested;

                const budgetInput = document.getElementById('wizard-budget');
                const budgetSlider = document.getElementById('wizard-budget-slider');
                if (budgetInput) {
                    budgetInput.value = suggestion.suggested;
                }
                if (budgetSlider) {
                    budgetSlider.value = Math.min(suggestion.suggested, 500);
                }

                if (explanation) {
                    explanation.textContent = suggestion.explanation;
                }

                this.updateEstimates();
            }
        } catch (error) {
            if (explanation) {
                explanation.textContent = 'Sugestão padrão: R$ 20/dia. Ajuste conforme desejar.';
            }
        }
    },

    updateEstimates() {
        const weekly = document.getElementById('est-weekly');
        const monthly = document.getElementById('est-monthly');
        const risk = document.getElementById('est-risk');
        const money = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);

        if (weekly) {
            weekly.textContent = money(this.budget * 7);
        }
        if (monthly) {
            monthly.textContent = money(this.budget * 30);
        }
        if (risk) {
            if (this.budget <= 30) {
                risk.textContent = 'Baixo';
                risk.className = 'text-success';
            } else if (this.budget <= 100) {
                risk.textContent = 'Médio';
                risk.className = 'text-warning';
            } else {
                risk.textContent = 'Alto';
                risk.className = 'text-danger';
            }
        }
    },

    renderSummary() {
        const money = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);

        const productsList = document.getElementById('summary-products');
        if (productsList) {
            let html = '';
            this.selectedItems.forEach((itemId) => {
                const product = this.products.find((p) => p.item_id === itemId);
                const title = product ? this.escapeHtml(product.title) : this.escapeHtml(itemId);
                const price = product ? money(product.price) : '';
                html += '<li class="mb-1">';
                html += '<i class="bi bi-check-circle-fill text-success me-1"></i>';
                html += '<span class="small">' + title + '</span>';
                if (price) {
                    html += ' <span class="small text-muted">(' + price + ')</span>';
                }
                html += '</li>';
            });
            productsList.innerHTML = html;
        }

        const budgetSummary = document.getElementById('summary-budget');
        if (budgetSummary) {
            budgetSummary.textContent = money(this.budget) + '/dia';
        }
    },

    async createCampaign() {
        const createBtn = document.getElementById('btn-create');
        if (createBtn) {
            createBtn.disabled = true;
            createBtn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div>Criando...';
        }

        try {
            const name = document.getElementById('campaign-name')?.value?.trim() || '';

            const data = await this.apiFetch('/api/ads/create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    items: this.selectedItems,
                    budget: this.budget,
                    name: name || undefined,
                }),
            });

            if (data.success) {
                this.showSuccess(data);
            } else {
                this.resetCreateBtn(createBtn);
                this.showError(data.error || 'Erro ao criar campanha. Tente novamente.');
            }
        } catch (error) {
            this.resetCreateBtn(createBtn);
            this.showError(error.message || 'Erro de conexão. Verifique sua internet.');
        }
    },

    resetCreateBtn(btn) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-rocket-takeoff me-1"></i> Criar Anúncio';
        }
    },

    showSuccess(data) {
        document.querySelectorAll('.wizard-panel').forEach((p) => {
            p.style.display = 'none';
        });
        const successPanel = document.getElementById('step-success');
        if (successPanel) {
            successPanel.style.display = '';
        }

        const msgEl = document.getElementById('success-message');
        if (msgEl && data.message) {
            msgEl.textContent = data.message;
        }

        const tipsEl = document.getElementById('success-tips');
        if (tipsEl && data.tips) {
            let html = '';
            data.tips.forEach((tip) => {
                html += '<li>' + this.escapeHtml(tip) + '</li>';
            });
            tipsEl.innerHTML = html;
        }

        for (let i = 1; i <= 3; i++) {
            const indicator = document.getElementById('step-indicator-' + i);
            if (indicator) {
                indicator.classList.remove('active');
                indicator.classList.add('completed');
            }
        }
    },

    showError(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Ops!',
                text: message,
                confirmButtonColor: '#3483fa',
            });
        } else {
            const toast = document.createElement('div');
            toast.className = 'alert alert-danger position-fixed shadow';
            toast.style.cssText = 'top:20px;right:20px;z-index:9999;max-width:400px';
            toast.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>' + this.escapeHtml(message);
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }
    },
};

document.addEventListener('DOMContentLoaded', () => AdsWizard.init());
