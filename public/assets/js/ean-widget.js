/**
 * Widget de EAN para integração em outras páginas
 * Mostra status do saldo de EANs e permite ações rápidas
 */

class EanWidget {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.options = {
            showBalance: true,
            showAlert: true,
            showPreview: false,
            compact: false,
            autoRefresh: true,
            refreshInterval: 60000, // 1 minuto
            onEanUsed: null,
            onLowStock: null,
            ...options
        };
        
        this.data = null;
        this.refreshTimer = null;
        
        if (this.container) {
            this.init();
        }
    }
    
    async init() {
        await this.loadData();
        this.render();
        
        if (this.options.autoRefresh) {
            this.startAutoRefresh();
        }
    }
    
    async loadData() {
        try {
            const response = await fetch('/api/ean/widget');
            const result = await response.json();
            
            if (result.success) {
                this.data = result.widget;
                
                // Callback de estoque baixo
                if (this.data.alert_level !== 'ok' && this.options.onLowStock) {
                    this.options.onLowStock(this.data);
                }
            }
        } catch (error) {
            console.error('Erro ao carregar widget de EAN:', error);
        }
    }
    
    render() {
        if (!this.container || !this.data) return;
        
        const html = this.options.compact ? this.renderCompact() : this.renderFull();
        this.container.innerHTML = html;
        
        // Bind eventos
        this.bindEvents();
    }
    
    renderCompact() {
        const alertClass = this.getAlertClass();
        
        return `
            <div class="ean-widget-compact d-flex align-items-center gap-2">
                <span class="badge ${alertClass}" title="EANs disponíveis">
                    <i class="bi bi-upc me-1"></i>${this.data.available}
                </span>
                ${this.data.alert_level !== 'ok' ? `
                    <a href="${this.data.purchase_url}" class="btn btn-sm btn-warning py-0 px-2">
                        <i class="bi bi-bag-plus"></i>
                    </a>
                ` : ''}
            </div>
        `;
    }
    
    renderFull() {
        const alertClass = this.getAlertClass();
        const bgClass = this.getBackgroundClass();
        
        return `
            <div class="ean-widget card ${bgClass}">
                <div class="card-body p-3">
                    ${this.options.showAlert && this.data.alert_level !== 'ok' ? `
                        <div class="alert alert-${this.data.alert_level === 'danger' ? 'danger' : 'warning'} py-2 px-3 mb-3 d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <small class="flex-grow-1">${this.data.alert_message}</small>
                        </div>
                    ` : ''}
                    
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-upc fs-4 me-2 text-primary"></i>
                                <div>
                                    <div class="fw-bold fs-5">${this.data.available}</div>
                                    <small class="text-muted">EANs disponíveis</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            ${this.data.can_use_ean ? `
                                <button class="btn btn-primary btn-sm" onclick="eanWidget.getPreview()">
                                    <i class="bi bi-eye me-1"></i>Preview
                                </button>
                            ` : `
                                <a href="${this.data.purchase_url}" class="btn btn-warning btn-sm">
                                    <i class="bi bi-bag-plus me-1"></i>Comprar
                                </a>
                            `}
                        </div>
                    </div>
                    
                    ${this.options.showPreview && this.previewEan ? `
                        <div class="mt-3 p-2 bg-dark rounded">
                            <small class="text-muted d-block mb-1">Próximo EAN:</small>
                            <code class="text-success fs-6">${this.previewEan}</code>
                            <button class="btn btn-sm btn-outline-light float-end" onclick="eanWidget.copyPreview()">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    ` : ''}
                    
                    <div class="mt-2 text-muted small">
                        <span>${this.data.total_used} usados</span>
                        <span class="mx-1">|</span>
                        <span>${this.data.total_purchased} total</span>
                    </div>
                </div>
            </div>
        `;
    }
    
    getAlertClass() {
        switch (this.data.alert_level) {
            case 'danger': return 'bg-danger text-white';
            case 'warning': return 'bg-warning text-dark';
            default: return 'bg-success text-white';
        }
    }
    
    getBackgroundClass() {
        if (this.data.alert_level === 'danger') return 'border-danger';
        if (this.data.alert_level === 'warning') return 'border-warning';
        return '';
    }
    
    bindEvents() {
        // Eventos específicos podem ser adicionados aqui
    }
    
    async getPreview() {
        try {
            const response = await fetch('/api/ean/preview');
            const result = await response.json();
            
            if (result.success) {
                this.previewEan = result.preview.ean;
                this.render();
            } else {
                alert(result.error || 'Não foi possível obter preview');
            }
        } catch (error) {
            console.error('Erro ao obter preview:', error);
        }
    }
    
    copyPreview() {
        if (this.previewEan) {
            navigator.clipboard.writeText(this.previewEan);
            // Mostrar feedback
            const btn = this.container.querySelector('.btn-outline-light');
            if (btn) {
                btn.innerHTML = '<i class="bi bi-check"></i>';
                setTimeout(() => {
                    btn.innerHTML = '<i class="bi bi-clipboard"></i>';
                }, 1500);
            }
        }
    }
    
    async autoAssign(mlItemId, title = null) {
        try {
            const response = await fetch('/api/ean/auto-assign', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ml_item_id: mlItemId, title })
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (this.options.onEanUsed) {
                    this.options.onEanUsed(result.ean, result.assignment_id);
                }
                await this.refresh();
                return result.ean;
            } else {
                throw new Error(result.error || 'Erro ao atribuir EAN');
            }
        } catch (error) {
            console.error('Erro ao auto-atribuir EAN:', error);
            throw error;
        }
    }
    
    async refresh() {
        await this.loadData();
        this.render();
    }
    
    startAutoRefresh() {
        this.stopAutoRefresh();
        this.refreshTimer = setInterval(() => this.refresh(), this.options.refreshInterval);
    }
    
    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }
    
    destroy() {
        this.stopAutoRefresh();
        if (this.container) {
            this.container.innerHTML = '';
        }
    }
    
    // Getters úteis
    get available() {
        return this.data?.available ?? 0;
    }
    
    get hasEan() {
        return this.data?.can_use_ean ?? false;
    }
    
    get isLowStock() {
        return this.data?.alert_level !== 'ok';
    }
}

// Instância global para uso direto
let eanWidget = null;

// Auto-inicialização se container existir
document.addEventListener('DOMContentLoaded', () => {
    const autoContainer = document.getElementById('ean-widget-auto');
    if (autoContainer) {
        eanWidget = new EanWidget('ean-widget-auto');
    }
});
