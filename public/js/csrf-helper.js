/**
 * Helper global para gerenciar CSRF tokens em requisições AJAX
 * Adiciona automaticamente o token CSRF em todas as requisições fetch
 */

// Função para obter o token CSRF da meta tag
function getCsrfToken() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!token) {
        console.warn('CSRF token não encontrado na página. Adicione <meta name="csrf-token"> no head.');
    }
    return token || '';
}

// Interceptar todas as requisições fetch para adicionar CSRF token automaticamente
(function () {
    const originalFetch = window.fetch;

    window.fetch = function (url, options = {}) {
        // Apenas adicionar token em requisições que modificam dados
        const method = (options.method || 'GET').toUpperCase();
        const needsCsrf = ['POST', 'PUT', 'DELETE', 'PATCH'].includes(method);

        if (needsCsrf) {
            options.headers = options.headers || {};

            // Se headers for um objeto Headers, converter para objeto simples
            if (options.headers instanceof Headers) {
                const headersObj = {};
                options.headers.forEach((value, key) => {
                    headersObj[key] = value;
                });
                options.headers = headersObj;
            }

            // Adicionar CSRF token se não existir
            if (!options.headers['X-CSRF-TOKEN'] && !options.headers['x-csrf-token']) {
                options.headers['X-CSRF-TOKEN'] = getCsrfToken();
            }
        }

        return originalFetch(url, options);
    };
})();

// Interceptar XMLHttpRequest também
(function () {
    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url, ...args) {
        this._method = method.toUpperCase();
        return originalOpen.call(this, method, url, ...args);
    };

    XMLHttpRequest.prototype.send = function (...args) {
        const needsCsrf = ['POST', 'PUT', 'DELETE', 'PATCH'].includes(this._method);

        if (needsCsrf && !this.getRequestHeader('X-CSRF-TOKEN')) {
            this.setRequestHeader('X-CSRF-TOKEN', getCsrfToken());
        }

        return originalSend.call(this, ...args);
    };
})();

// Exportar função para uso manual se necessário
window.getCsrfToken = getCsrfToken;

console.log('[CSRF Helper] CSRF token helper carregado. Tokens serão adicionados automaticamente.');
