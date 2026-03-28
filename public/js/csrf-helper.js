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
            let headers;
            if (options.headers instanceof Headers) {
                headers = options.headers;
            } else {
                headers = new Headers(options.headers || {});
            }

            if (!headers.has('X-CSRF-TOKEN') && !headers.has('x-csrf-token')) {
                headers.set('X-CSRF-TOKEN', getCsrfToken());
            }

            options.headers = headers;
        }

        return originalFetch(url, options);
    };
})();

// Interceptar XMLHttpRequest também
(function () {
    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader;
    const originalSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url, ...args) {
        this._method = method.toUpperCase();
        this._csrfHeaderSet = false;
        return originalOpen.call(this, method, url, ...args);
    };

    XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
        if (typeof name === 'string' && name.toLowerCase() === 'x-csrf-token') {
            this._csrfHeaderSet = true;
        }
        return originalSetRequestHeader.call(this, name, value);
    };

    XMLHttpRequest.prototype.send = function (...args) {
        const needsCsrf = ['POST', 'PUT', 'DELETE', 'PATCH'].includes(this._method);

        if (needsCsrf && this._csrfHeaderSet !== true) {
            this.setRequestHeader('X-CSRF-TOKEN', getCsrfToken());
        }

        return originalSend.call(this, ...args);
    };
})();

// Exportar função para uso manual se necessário
window.getCsrfToken = getCsrfToken;

console.log('[CSRF Helper] CSRF token helper carregado. Tokens serão adicionados automaticamente.');
