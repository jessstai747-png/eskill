/* eslint-env browser */
/**
 * Ads Manager — Dashboard humanizado para leigos
 * Gerencia: diagnóstico, KPIs, campanhas, ações rápidas, glossário
 */
const AdsManager = {
    data: null,
    _loading: false,

    init() {
        this.bindEvents();
        this.loadDashboard();
        this.initTooltips();
    },

    bindEvents() {
        const refreshBtn = document.getElementById('btn-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.loadDashboard());
        }

        document.querySelectorAll('.quick-action-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const action = btn.dataset.action;
                this.executeQuickAction(action, btn);
            });
        });

        // Budget modal sync
        const budgetSlider = document.getElementById('budget-slider');
        const budgetInput = document.getElementById('budget-input');
        if (budgetSlider && budgetInput) {
            budgetSlider.addEventListener('input', () => {
                budgetInput.value = budgetSlider.value;
            });
            budgetInput.addEventListener('input', () => {
                budgetSlider.value = budgetInput.value;
            });
        }

        const saveBudgetBtn = document.getElementById('btn-save-budget');
        if (saveBudgetBtn) {
            saveBudgetBtn.addEventListener('click', () => this.saveBudget());
        }
    },

    initTooltips() {
        const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach((el) => {
            if (typeof bootstrap !== 'undefined') {
                new bootstrap.Tooltip(el);
            }
        });
    },

    money(val) {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0);
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

    async loadDashboard() {
        if (this._loading) {
            return;
        }
        this._loading = true;

        try {
            const data = await this.apiFetch('/api/ads/dashboard');

            if (!data.success) {
                this.showError(data.error || 'Erro ao carregar dados');
                return;
            }

            this.data = data;
            this.renderHealth(data.diagnostic);
            this.renderKPIs(data.diagnostic);
            this.renderDiagnostic(data.diagnostic);
            this.renderCampaigns(data.campaigns);
            this.renderGlossary(data.glossary);
        } catch (error) {
            this.showLoadError(error.message || 'Erro ao carregar dashboard');
        } finally {
            this._loading = false;
        }
    },

    showLoadError(message) {
        const loading = document.getElementById('campaigns-loading');
        if (loading) {
            loading.innerHTML = '<div class="text-center py-4">'
                + '<i class="bi bi-exclamation-triangle text-warning" style="font-size:2rem"></i>'
                + '<p class="text-muted mt-2">' + message + '</p>'
                + '<button class="btn btn-sm btn-outline-primary" onclick="AdsManager.loadDashboard()">'
                + '<i class="bi bi-arrow-clockwise me-1"></i>Tentar novamente</button></div>';
        }
    },

    renderHealth(diagnostic) {
        const health = diagnostic?.health;
        if (!health) {
            return;
        }

        const banner = document.getElementById('health-banner');
        banner.style.display = '';
        banner.style.removeProperty('display');
        banner.className = 'alert alert-' + health.color + ' d-flex align-items-center mb-4';

        document.getElementById('health-emoji').textContent = health.emoji;
        document.getElementById('health-label').textContent = health.label;
        document.getElementById('health-message').textContent = health.message;
    },

    renderKPIs(diagnostic) {
        const m = diagnostic?.metrics_simple;
        if (!m) {
            return;
        }

        document.getElementById('kpi-investiu').textContent = this.money(m.investiu);
        document.getElementById('kpi-vendeu').textContent = this.money(m.vendeu_com_ads);

        const lucro = m.lucro_estimado || 0;
        const lucroEl = document.getElementById('kpi-lucro');
        lucroEl.textContent = this.money(lucro);
        lucroEl.className = 'fw-bold mb-0 ' + (lucro >= 0 ? 'text-success' : 'text-danger');

        const acos = diagnostic?.raw_metrics?.acos || 0;
        const acosEl = document.getElementById('kpi-custo-venda');
        acosEl.textContent = acos.toFixed(1) + '%';

        if (acos > 20) {
            acosEl.className = 'fw-bold mb-0 text-danger';
        } else if (acos > 10) {
            acosEl.className = 'fw-bold mb-0 text-warning';
        } else {
            acosEl.className = 'fw-bold mb-0 text-success';
        }
    },

    renderDiagnostic(diagnostic) {
        const items = diagnostic?.summary;
        const card = document.getElementById('diagnostico-card');
        const body = document.getElementById('diagnostico-body');

        if (!items || items.length === 0) {
            card.style.display = 'none';
            return;
        }

        card.style.display = '';
        let html = '';
        items.forEach((item) => {
            html += '<div class="d-flex align-items-start mb-3">';
            html += '<i class="bi ' + item.icon + ' text-' + item.color + ' me-3 mt-1 fs-5"></i>';
            html += '<p class="mb-0">' + this.escapeHtml(item.text) + '</p>';
            html += '</div>';
        });

        body.innerHTML = html;
    },

    renderCampaigns(campaignsData) {
        const campaigns = campaignsData?.campaigns || [];
        const loading = document.getElementById('campaigns-loading');
        const empty = document.getElementById('campaigns-empty');
        const tableWrap = document.getElementById('campaigns-table-wrap');
        const list = document.getElementById('campaigns-list');

        if (loading) {
            loading.style.display = 'none';
        }

        if (!Array.isArray(campaigns) || campaigns.length === 0) {
            if (empty) {
                empty.style.display = '';
            }
            if (tableWrap) {
                tableWrap.style.display = 'none';
            }
            return;
        }

        if (empty) {
            empty.style.display = 'none';
        }
        if (tableWrap) {
            tableWrap.style.display = '';
        }

        list.innerHTML = campaigns.map((c) => this.renderCampaignRow(c)).join('');
    },

    parseCampaign(c) {
        const id = c.id || c.campaign_id || '';
        const budget = parseFloat(c.budget?.daily_budget || c.budget || c.daily_budget || 0);
        const status = c.status || 'unknown';
        const isActive = status === 'active';
        const type = (c.type === 'product_ads' || c.type === 'product_ad') ? 'Product Ads' : (c.type || 'Anúncio');
        return { id, budget, isActive, type, name: c.name || 'Campanha sem nome' };
    },

    renderCampaignRow(c) {
        const { id, budget, isActive, type, name } = this.parseCampaign(c);
        const safeId = this.escapeAttr(id);
        const statusBadge = isActive
            ? '<span class="badge bg-success">Ativa</span>'
            : '<span class="badge bg-secondary">Pausada</span>';
        const toggle = isActive
            ? { icon: 'bi-pause-fill', title: 'Pausar', color: 'warning', next: 'paused' }
            : { icon: 'bi-play-fill', title: 'Ativar', color: 'success', next: 'active' };

        let row = '<tr>';
        row += '<td class="ps-4"><strong>' + this.escapeHtml(name) + '</strong>';
        if (id) {
            row += '<br><small class="text-muted">' + this.escapeHtml(id) + '</small>';
        }
        row += '</td>';
        row += '<td>' + type + '</td>';
        row += '<td>' + this.money(budget) + '</td>';
        row += '<td>' + statusBadge + '</td>';
        row += '<td class="text-end pe-4">';
        row += '<button class="btn btn-sm btn-outline-' + toggle.color + ' me-1" title="' + toggle.title + '" onclick="AdsManager.toggleCampaign(\'' + safeId + '\', \'' + toggle.next + '\')">';
        row += '<i class="bi ' + toggle.icon + '"></i></button>';
        row += '<button class="btn btn-sm btn-outline-primary" title="Alterar orçamento" onclick="AdsManager.openBudgetModal(\'' + safeId + '\', ' + budget + ')">';
        row += '<i class="bi bi-wallet2"></i></button>';
        row += '</td></tr>';
        return row;
    },

    renderGlossary(glossary) {
        const container = document.getElementById('glossario-content');
        if (!container || !glossary) {
            return;
        }

        let html = '<div class="row g-3">';
        Object.entries(glossary).forEach(([key, item]) => {
            html += '<div class="col-md-6">';
            html += '<div class="border rounded p-3 h-100">';
            html += '<h6 class="mb-1"><strong>' + this.escapeHtml(key) + '</strong> — ' + this.escapeHtml(item.nome) + '</h6>';
            html += '<p class="small mb-1">' + this.escapeHtml(item.descricao) + '</p>';
            html += '<p class="small text-muted mb-1"><i class="bi bi-chat-quote me-1"></i>' + this.escapeHtml(item.exemplo) + '</p>';
            html += '<span class="badge bg-light text-dark small">' + this.escapeHtml(item.meta) + '</span>';
            html += '</div></div>';
        });
        html += '</div>';
        container.innerHTML = html;
    },

    async toggleCampaign(campaignId, newStatus) {
        if (!campaignId) {
            return;
        }

        const label = newStatus === 'active' ? 'ativar' : 'pausar';

        try {
            const data = await this.apiFetch('/api/ads/toggle/' + encodeURIComponent(campaignId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: newStatus }),
            });

            this.showToast(data.message || 'Campanha atualizada!', 'success');
            this.loadDashboard();
        } catch (error) {
            this.showToast('Erro ao ' + label + ': ' + error.message, 'danger');
        }
    },

    _editingCampaignId: null,

    openBudgetModal(campaignId, currentBudget) {
        this._editingCampaignId = campaignId;
        const input = document.getElementById('budget-input');
        const slider = document.getElementById('budget-slider');
        if (input) {
            input.value = currentBudget || 20;
        }
        if (slider) {
            slider.value = currentBudget || 20;
        }

        if (typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(document.getElementById('budgetModal'));
            modal.show();
        }
    },

    async saveBudget() {
        const campaignId = this._editingCampaignId;
        const budget = parseFloat(document.getElementById('budget-input')?.value || 0);

        if (!campaignId || budget < 5) {
            this.showToast('Orçamento mínimo: R$ 5,00/dia', 'warning');
            return;
        }

        try {
            const data = await this.apiFetch('/api/ads/budget/' + encodeURIComponent(campaignId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ budget: budget }),
            });

            // Close modal
            if (typeof bootstrap !== 'undefined') {
                const modal = bootstrap.Modal.getInstance(document.getElementById('budgetModal'));
                if (modal) {
                    modal.hide();
                }
            }

            this.showToast(data.message || 'Orçamento atualizado!', 'success');
            this.loadDashboard();
        } catch (error) {
            this.showToast('Erro: ' + error.message, 'danger');
        }
    },

    async executeQuickAction(action, btn) {
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner-border spinner-border-sm mb-1"></div><strong>Processando...</strong><small class="text-muted">Aguarde</small>';

        try {
            const data = await this.apiFetch('/api/ads/quick-action', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: action }),
            });

            this.showToast(data.message || 'Ação concluída!', data.success ? 'success' : 'warning');

            if (data.success) {
                this.loadDashboard();
            }
        } catch (error) {
            this.showToast('Erro: ' + error.message, 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    },

    /**
     * Escape HTML para prevenir XSS ao inserir dados da API no DOM
     */
    escapeHtml(str) {
        if (!str) {
            return '';
        }
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    },

    escapeAttr(str) {
        return String(str || '').replace(/['"&<>]/g, (c) => ({
            '\'': '&#39;', '"': '&quot;', '&': '&amp;', '<': '&lt;', '>': '&gt;'
        }[c]));
    },

    showToast(message, type) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type === 'danger' ? 'error' : type,
                title: message,
                showConfirmButton: false,
                timer: 4000,
            });
            return;
        }

        const toast = document.createElement('div');
        toast.className = 'alert alert-' + type + ' position-fixed shadow';
        toast.style.cssText = 'top:20px;right:20px;z-index:9999;max-width:400px;animation:fadeIn 0.3s';
        toast.innerHTML = '<i class="bi bi-info-circle me-2"></i>' + this.escapeHtml(message);
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    },
};

document.addEventListener('DOMContentLoaded', () => AdsManager.init());
