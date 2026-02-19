/**
 * Widget de EAN para integração com criação de anúncios
 * 
 * Uso: 
 *   <div id="ean-widget"></div>
 *   <script src="/js/ean-widget.js"></script>
 *   <script>EanWidget.init('#ean-widget', { onSelect: (ean) => console.log(ean) });</script>
 */

async function requestJson(url, options = {}) {
    if (window.ApiClient) return window.ApiClient.request(url, options);
    const resp = await fetch(url, { credentials: 'include', ...options });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}

const EanWidget = {
    container: null,
    options: {
        onSelect: null,
        autoUse: false,
        showBalance: true,
    },
    state: {
        balance: null,
        suggestedEan: null,
        loading: false,
        error: null,
    },

    /**
     * Inicializar widget
     */
    init(selector, options = {}) {
        this.container = document.querySelector(selector);
        if (!this.container) {
            console.error('EanWidget: Container não encontrado');
            return;
        }

        this.options = { ...this.options, ...options };
        this.render();
        this.loadBalance();
    },

    /**
     * Carregar saldo e sugestão de EAN
     */
    async loadBalance() {
        this.state.loading = true;
        this.render();

        try {
            const data = await requestJson('/api/ean/suggest');

            if (data.success) {
                this.state.balance = data.balance;
                this.state.suggestedEan = data.has_ean ? data.suggested_ean : null;
                this.state.error = null;
            } else {
                this.state.error = data.error || 'Erro ao carregar EANs';
            }
        } catch (error) {
            this.state.error = 'Erro de conexão';
            console.error('EanWidget Error:', error);
        }

        this.state.loading = false;
        this.render();
    },

    /**
     * Usar EAN para um item
     */
    async useEan(mlItemId, title = '') {
        if (!this.state.suggestedEan) {
            this.showToast('Nenhum EAN disponível', 'error');
            return null;
        }

        this.state.loading = true;
        this.render();

        try {
            const data = await requestJson('/api/ean/use-for-item', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ml_item_id: mlItemId, title }),
            });

            if (data.success) {
                this.showToast(`EAN ${data.ean} vinculado!`, 'success');
                
                // Recarregar saldo
                await this.loadBalance();
                
                if (this.options.onSelect) {
                    this.options.onSelect(data.ean);
                }
                
                return data.ean;
            } else {
                this.showToast(data.error || 'Erro ao usar EAN', 'error');
                return null;
            }
        } catch (error) {
            this.showToast('Erro de conexão', 'error');
            console.error('EanWidget Error:', error);
            return null;
        } finally {
            this.state.loading = false;
            this.render();
        }
    },

    /**
     * Selecionar EAN (sem usar, apenas retornar)
     */
    selectEan() {
        if (this.state.suggestedEan && this.options.onSelect) {
            this.options.onSelect(this.state.suggestedEan);
        }
        return this.state.suggestedEan;
    },

    /**
     * Renderizar widget
     */
    render() {
        if (!this.container) return;

        const { balance, suggestedEan, loading, error } = this.state;
        const { showBalance } = this.options;

        let html = `
            <div class="ean-widget" style="
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                border-radius: 12px;
                padding: 16px;
                color: #fff;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            ">
                <div style="display: flex; align-items: center; margin-bottom: 12px;">
                    <svg width="24" height="24" fill="none" stroke="#FFE600" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="4" width="18" height="16" rx="2"/>
                        <line x1="7" y1="8" x2="7" y2="16"/>
                        <line x1="11" y1="8" x2="11" y2="16"/>
                        <line x1="15" y1="8" x2="15" y2="16"/>
                    </svg>
                    <span style="margin-left: 8px; font-weight: 600;">Código EAN</span>
                </div>
        `;

        if (loading) {
            html += `
                <div style="text-align: center; padding: 20px;">
                    <div style="
                        width: 30px; height: 30px;
                        border: 3px solid rgba(255,230,0,0.3);
                        border-top-color: #FFE600;
                        border-radius: 50%;
                        animation: ean-spin 1s linear infinite;
                        margin: 0 auto;
                    "></div>
                    <style>@keyframes ean-spin { to { transform: rotate(360deg); } }</style>
                </div>
            `;
        } else if (error) {
            html += `
                <div style="
                    background: rgba(220,53,69,0.2);
                    border: 1px solid rgba(220,53,69,0.5);
                    border-radius: 8px;
                    padding: 12px;
                    color: #ff6b6b;
                ">
                    <strong>⚠️ ${error}</strong>
                </div>
            `;
        } else if (!suggestedEan) {
            html += `
                <div style="text-align: center; padding: 16px;">
                    <p style="margin: 0 0 12px; color: #adb5bd;">
                        Você não tem EANs disponíveis
                    </p>
                    <a href="/dashboard/ean" style="
                        display: inline-block;
                        background: #FFE600;
                        color: #000;
                        padding: 10px 20px;
                        border-radius: 8px;
                        text-decoration: none;
                        font-weight: 600;
                    ">
                        Comprar EANs
                    </a>
                </div>
            `;
        } else {
            html += `
                <div style="
                    background: rgba(255,255,255,0.1);
                    border-radius: 8px;
                    padding: 12px;
                    margin-bottom: 12px;
                ">
                    <div style="font-size: 12px; color: #adb5bd; margin-bottom: 4px;">
                        Próximo EAN disponível:
                    </div>
                    <div style="
                        font-family: 'Courier New', monospace;
                        font-size: 18px;
                        font-weight: bold;
                        letter-spacing: 2px;
                        color: #FFE600;
                    ">
                        ${suggestedEan}
                    </div>
                </div>
                
                <button onclick="EanWidget.selectEan()" style="
                    width: 100%;
                    background: #FFE600;
                    color: #000;
                    border: none;
                    padding: 12px;
                    border-radius: 8px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#e6cf00'" onmouseout="this.style.background='#FFE600'">
                    Usar este EAN
                </button>
            `;
            
            if (showBalance && balance) {
                html += `
                    <div style="
                        margin-top: 12px;
                        padding-top: 12px;
                        border-top: 1px solid rgba(255,255,255,0.1);
                        display: flex;
                        justify-content: space-between;
                        font-size: 13px;
                        color: #adb5bd;
                    ">
                        <span>Saldo disponível:</span>
                        <span style="color: #28a745; font-weight: 600;">
                            ${balance.available} EANs
                        </span>
                    </div>
                `;
            }
        }

        html += '</div>';
        this.container.innerHTML = html;
    },

    /**
     * Mostrar toast de notificação
     */
    showToast(message, type = 'info') {
        const colors = {
            success: '#28a745',
            error: '#dc3545',
            info: '#3483FA',
        };

        const toast = document.createElement('div');
        toast.innerHTML = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: ${colors[type] || colors.info};
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            z-index: 10000;
            animation: ean-toast-in 0.3s ease;
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'ean-toast-out 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);

        // Adicionar keyframes se não existirem
        if (!document.getElementById('ean-toast-styles')) {
            const style = document.createElement('style');
            style.id = 'ean-toast-styles';
            style.textContent = `
                @keyframes ean-toast-in { from { opacity: 0; transform: translateY(20px); } }
                @keyframes ean-toast-out { to { opacity: 0; transform: translateY(20px); } }
            `;
            document.head.appendChild(style);
        }
    },

    /**
     * Obter EAN atual sem usar
     */
    getCurrentEan() {
        return this.state.suggestedEan;
    },

    /**
     * Verificar se tem EAN disponível
     */
    hasEan() {
        return !!this.state.suggestedEan;
    },

    /**
     * Obter saldo atual
     */
    getBalance() {
        return this.state.balance;
    },
};

// Exportar para uso global
window.EanWidget = EanWidget;
