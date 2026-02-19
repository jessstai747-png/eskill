(function () {
    function sleep(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    function getToken() {
        if (typeof window.getCsrfToken === 'function') {
            return window.getCsrfToken();
        }

        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function normalizeHeaders(inputHeaders) {
        if (inputHeaders instanceof Headers) {
            const result = {};
            inputHeaders.forEach((value, key) => {
                result[key] = value;
            });
            return result;
        }

        return inputHeaders ? { ...inputHeaders } : {};
    }

    async function apiFetch(url, options = {}) {
        const {
            retries = 2,
            retryDelayMs = 1000,
            retryOn = [429, 503],
            ...rest
        } = options;

        const request = {
            credentials: 'include',
            ...rest,
            headers: normalizeHeaders(rest.headers)
        };

        const method = (request.method || 'GET').toUpperCase();
        const isWriteMethod = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);

        if (isWriteMethod && !request.headers['X-CSRF-TOKEN'] && !request.headers['X-CSRF-Token'] && !request.headers['x-csrf-token']) {
            request.headers['X-CSRF-TOKEN'] = getToken();
        }

        let attempt = 0;
        while (true) {
            const response = await window.fetch(url, request);

            if (response.status === 401) {
                const error = new Error('Sessão expirada. Faça login novamente.');
                error.code = 'AUTH_EXPIRED';
                error.response = response;
                throw error;
            }

            if (!retryOn.includes(response.status) || attempt >= retries) {
                return response;
            }

            const delay = retryDelayMs * Math.pow(2, attempt);
            await sleep(delay);
            attempt += 1;
        }
    }

    async function apiJson(url, options = {}) {
        const response = await apiFetch(url, options);
        const data = await response.json().catch(() => null);
        return { response, data };
    }

    async function apiRequest(url, options = {}) {
        const response = await apiFetch(url, options);
        if (!response.ok) {
            const error = new Error(`HTTP ${response.status}`);
            error.response = response;
            throw error;
        }
        return response.json().catch(() => null);
    }

    window.ApiClient = {
        fetch: apiFetch,
        json: apiJson,
        request: apiRequest
    };
})();
