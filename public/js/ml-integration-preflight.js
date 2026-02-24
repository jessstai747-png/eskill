/**
 * Mercado Livre Integration Preflight Helper
 *
 * Checks if ML integration is ready for frontend actions by calling:
 * - GET /api/health/ml
 * - GET /api/tokens/accounts?status=active&sort=expires_at&order=asc
 */
(function(global) {
    const DEFAULT_STATE = {
        checked: false,
        ready: false,
        message: 'Validação da integração ML pendente...',
        activeAccounts: 0,
        accounts: [],
        healthyAccounts: [],
        health: null,
        checkedAt: null,
        lastError: null
    };

    let state = {
        ...DEFAULT_STATE
    };
    let checkPromise = null;

    async function requestJsonCompat(url, options = {}) {
        if (typeof global.requestJson === 'function') {
            return global.requestJson(url, options);
        }

        const response = await fetch(url, {
            credentials: 'include',
            ...options
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    }

    async function check(force = false) {
        if (checkPromise && !force) {
            return checkPromise;
        }

        if (state.checked && !force) {
            return state;
        }

        checkPromise = (async() => {
            try {
                const [health, accountsPayload] = await Promise.all([
                    requestJsonCompat('/api/health/ml'),
                    requestJsonCompat('/api/tokens/accounts?status=active&sort=expires_at&order=asc')
                ]);

                const credentialsOk = health?.result?.credentials === 'ok';
                const pingStatus = health?.result?.ping ?? 'skipped';
                const pingOk = pingStatus === 'ok' || pingStatus === 'skipped';

                const accounts = Array.isArray(accountsPayload?.data) ? accountsPayload.data : [];
                const healthyAccounts = accounts.filter((account) => {
                    const apiValidationStatus = account?.api_validation_status ?? 'ok';
                    return account?.status === 'active' && apiValidationStatus === 'ok';
                });

                let message = '';
                let ready = false;

                if (!credentialsOk) {
                    message = 'Credenciais do Mercado Livre não configuradas no servidor.';
                } else if (accounts.length === 0) {
                    message = 'Nenhuma conta ativa do Mercado Livre encontrada para operação.';
                } else if (healthyAccounts.length === 0) {
                    message = 'Contas ativas encontradas, mas sem validação de token na API do Mercado Livre.';
                } else {
                    ready = true;
                    message = pingOk
                        ? `Integração ML ativa (${healthyAccounts.length} conta(s) válida(s)).`
                        : `Integração ML ativa, porém com ping instável (${healthyAccounts.length} conta(s) válida(s)).`;
                }

                state = {
                    checked: true,
                    ready,
                    message,
                    activeAccounts: accounts.length,
                    accounts,
                    healthyAccounts,
                    health,
                    checkedAt: Date.now(),
                    lastError: null
                };
            } catch (error) {
                state = {
                    ...DEFAULT_STATE,
                    checked: true,
                    ready: false,
                    message: 'Não foi possível validar a integração com a API do Mercado Livre.',
                    checkedAt: Date.now(),
                    lastError: error?.message || String(error)
                };
            }

            return state;
        })();

        try {
            return await checkPromise;
        } finally {
            checkPromise = null;
        }
    }

    async function ensureReady(actionLabel = 'executar esta ação', options = {}) {
        const currentState = await check(Boolean(options.force));

        if (currentState.ready) {
            return currentState;
        }

        if (!options.silent && global.Toast && typeof global.Toast.error === 'function') {
            global.Toast.error(`Não foi possível ${actionLabel}: ${currentState.message}`);
        }

        return currentState;
    }

    global.MercadoLivreIntegration = {
        check,
        ensureReady,
        getState: () => ({
            ...state
        })
    };
})(window);
