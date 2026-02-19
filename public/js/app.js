/**
 * Mercado Livre Manager - JavaScript Principal
 * @version 1.0.0
 */

// ========================================
// CONFIGURAÇÃO GLOBAL
// ========================================
const App = {
    config: {
        apiUrl: '/api',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',
        debug: false
    },
    
    // Estado global
    state: {
        loading: false,
        user: null,
        account: null
    }
};

// ========================================
// UTILITÁRIOS
// ========================================
const Utils = {
    /**
     * Formata valor como moeda BRL
     */
    formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    },
    
    /**
     * Formata data
     */
    formatDate(date, options = {}) {
        const d = new Date(date);
        return d.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            ...options
        });
    },
    
    /**
     * Formata data e hora
     */
    formatDateTime(date) {
        return this.formatDate(date, {
            hour: '2-digit',
            minute: '2-digit'
        });
    },
    
    /**
     * Formata número
     */
    formatNumber(num) {
        return new Intl.NumberFormat('pt-BR').format(num);
    },
    
    /**
     * Trunca texto
     */
    truncate(str, length = 50) {
        if (!str) return '';
        return str.length > length ? str.substring(0, length) + '...' : str;
    },
    
    /**
     * Debounce
     */
    debounce(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    /**
     * Throttle
     */
    throttle(func, limit = 300) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },
    
    /**
     * Copia texto para clipboard
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            Notifications.success('Copiado!');
            return true;
        } catch (err) {
            console.error('Erro ao copiar:', err);
            return false;
        }
    },
    
    /**
     * Gera ID único
     */
    generateId() {
        return 'id_' + Math.random().toString(36).substr(2, 9);
    },
    
    /**
     * Verifica se é mobile
     */
    isMobile() {
        return window.innerWidth < 768;
    },
    
    /**
     * Escape HTML
     */
    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

// ========================================
// API CLIENT
// ========================================
const Api = {
    /**
     * Requisição base
     */
    async request(endpoint, options = {}) {
        const url = endpoint.startsWith('http') ? endpoint : `${App.config.apiUrl}${endpoint}`;
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': App.config.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        const config = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };
        
        try {
            const response = await fetch(url, config);
            
            // Token expirado
            if (response.status === 401) {
                window.location.href = '/auth/login';
                return null;
            }
            
            // CSRF token inválido
            if (response.status === 403) {
                Notifications.error('Sessão expirada. Recarregue a página.');
                return null;
            }
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || data.message || 'Erro na requisição');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },
    
    /**
     * GET request
     */
    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
    },
    
    /**
     * POST request
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },
    
    /**
     * PUT request
     */
    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },
    
    /**
     * DELETE request
     */
    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
};

// ========================================
// NOTIFICAÇÕES
// ========================================
const Notifications = {
    container: null,
    
    /**
     * Inicializa container
     */
    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            this.container.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:9999;';
            document.body.appendChild(this.container);
        }
    },
    
    /**
     * Mostra notificação
     */
    show(message, type = 'info', duration = 4000) {
        this.init();
        
        const icons = {
            success: 'check-circle',
            error: 'x-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        
        const colors = {
            success: '#00A650',
            error: '#F23D4F',
            warning: '#FF7733',
            info: '#3483FA'
        };
        
        const toast = document.createElement('div');
        toast.className = 'toast show animate-slide-up';
        toast.style.cssText = `
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            margin-bottom: 0.5rem;
            min-width: 300px;
            border-left: 4px solid ${colors[type]};
        `;
        
        toast.innerHTML = `
            <div class="toast-body d-flex align-items-center gap-2 p-3">
                <i class="bi bi-${icons[type]}" style="color:${colors[type]};font-size:1.25rem;"></i>
                <span>${Utils.escapeHtml(message)}</span>
                <button type="button" class="btn-close ms-auto" style="font-size:0.75rem;"></button>
            </div>
        `;
        
        const closeBtn = toast.querySelector('.btn-close');
        closeBtn.addEventListener('click', () => this.remove(toast));
        
        this.container.appendChild(toast);
        
        if (duration > 0) {
            setTimeout(() => this.remove(toast), duration);
        }
        
        return toast;
    },
    
    /**
     * Remove toast
     */
    remove(toast) {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    },
    
    // Atalhos
    success(message) { return this.show(message, 'success'); },
    error(message) { return this.show(message, 'error', 6000); },
    warning(message) { return this.show(message, 'warning'); },
    info(message) { return this.show(message, 'info'); }
};

// ========================================
// LOADING
// ========================================
const Loading = {
    overlay: null,
    
    /**
     * Mostra loading
     */
    show(message = 'Carregando...') {
        if (!this.overlay) {
            this.overlay = document.createElement('div');
            this.overlay.className = 'loading-overlay';
            this.overlay.innerHTML = `
                <div class="text-center">
                    <div class="spinner-ml mb-3"></div>
                    <div class="loading-message text-muted">${message}</div>
                </div>
            `;
            document.body.appendChild(this.overlay);
        }
        
        this.overlay.querySelector('.loading-message').textContent = message;
        this.overlay.style.display = 'flex';
        App.state.loading = true;
    },
    
    /**
     * Esconde loading
     */
    hide() {
        if (this.overlay) {
            this.overlay.style.display = 'none';
        }
        App.state.loading = false;
    },
    
    /**
     * Loading em botão
     */
    button(btn, loading = true) {
        if (loading) {
            btn.dataset.originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Aguarde...';
        } else {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalText || btn.innerHTML;
        }
    }
};

// ========================================
// MODAIS
// ========================================
const Modal = {
    /**
     * Confirmar ação
     */
    confirm(message, title = 'Confirmar') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>${message}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary btn-confirm">Confirmar</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            
            modal.querySelector('.btn-confirm').addEventListener('click', () => {
                bsModal.hide();
                resolve(true);
            });
            
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
                resolve(false);
            });
            
            bsModal.show();
        });
    },
    
    /**
     * Alert
     */
    alert(message, title = 'Aviso') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>${message}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
                resolve();
            });
            
            bsModal.show();
        });
    }
};

// ========================================
// FORMULÁRIOS
// ========================================
const Forms = {
    /**
     * Serializa formulário para objeto
     */
    serialize(form) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            if (data[key]) {
                if (!Array.isArray(data[key])) {
                    data[key] = [data[key]];
                }
                data[key].push(value);
            } else {
                data[key] = value;
            }
        }
        
        return data;
    },
    
    /**
     * Valida formulário
     */
    validate(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('[required]');
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        return isValid;
    },
    
    /**
     * Limpa erros
     */
    clearErrors(form) {
        form.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.invalid-feedback').forEach(el => {
            el.textContent = '';
        });
    },
    
    /**
     * Mostra erros
     */
    showErrors(form, errors) {
        this.clearErrors(form);
        
        Object.entries(errors).forEach(([field, message]) => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input) {
                input.classList.add('is-invalid');
                const feedback = input.nextElementSibling;
                if (feedback?.classList.contains('invalid-feedback')) {
                    feedback.textContent = Array.isArray(message) ? message[0] : message;
                }
            }
        });
    }
};

// ========================================
// TABELAS
// ========================================
const Tables = {
    /**
     * Inicializa ordenação
     */
    initSorting(table) {
        const headers = table.querySelectorAll('th[data-sort]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                const field = header.dataset.sort;
                const order = header.dataset.order === 'asc' ? 'desc' : 'asc';
                
                // Reset outros headers
                headers.forEach(h => {
                    h.dataset.order = '';
                    h.querySelector('.sort-icon')?.remove();
                });
                
                header.dataset.order = order;
                const icon = document.createElement('i');
                icon.className = `bi bi-chevron-${order === 'asc' ? 'up' : 'down'} sort-icon ms-1`;
                header.appendChild(icon);
                
                // Dispara evento
                table.dispatchEvent(new CustomEvent('sort', {
                    detail: { field, order }
                }));
            });
        });
    }
};

// ========================================
// LOCAL STORAGE
// ========================================
const Storage = {
    /**
     * Salva item
     */
    set(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (e) {
            console.error('Storage error:', e);
            return false;
        }
    },
    
    /**
     * Recupera item
     */
    get(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (e) {
            return defaultValue;
        }
    },
    
    /**
     * Remove item
     */
    remove(key) {
        localStorage.removeItem(key);
    },
    
    /**
     * Limpa storage
     */
    clear() {
        localStorage.clear();
    }
};

// ========================================
// EVENT HANDLERS GLOBAIS
// ========================================
document.addEventListener('DOMContentLoaded', () => {
    // Auto-hide alerts
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Tooltips Bootstrap
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));
    
    // Popover Bootstrap
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    popoverTriggerList.forEach(el => new bootstrap.Popover(el));
    
    // Confirmação de exclusão
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', async (e) => {
            e.preventDefault();
            const message = el.dataset.confirm || 'Tem certeza?';
            const confirmed = await Modal.confirm(message);
            if (confirmed) {
                if (el.tagName === 'A') {
                    window.location.href = el.href;
                } else if (el.form) {
                    el.form.submit();
                }
            }
        });
    });
    
    // Sidebar toggle mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            document.querySelector('.sidebar')?.classList.toggle('show');
        });
    }
    
    // Log de debug
    if (App.config.debug) {
        console.log('App initialized:', App);
    }
});

// ========================================
// EXPORTA PARA WINDOW
// ========================================
window.App = App;
window.Utils = Utils;
window.Api = Api;
window.Notifications = Notifications;
window.Loading = Loading;
window.Modal = Modal;
window.Forms = Forms;
window.Tables = Tables;
window.Storage = Storage;
