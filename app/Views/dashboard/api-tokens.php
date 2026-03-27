<?php

declare(strict_types=1);

$pageTitle = 'Tokens de API';
$activePage = 'api-tokens';
?>

<div class="container-fluid px-0 px-md-4 py-4">
    <div class="row g-4">
        <div class="col-12">
            <div class="card mb-0">
                <div class="card-body d-flex flex-column flex-md-row gap-3 align-items-md-center">
                    <div class="flex-grow-1">
                        <h4 class="mb-1"><i class="bi bi-key"></i> Tokens de API</h4>
                        <p class="text-muted mb-0">Gere e gerencie tokens para integrações externas com controle granular de escopos.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a class="btn btn-outline-secondary" href="/docs/API_DOCUMENTATION.pdf" target="_blank">
                            <i class="bi bi-book"></i> Documentação
                        </a>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTokenModal">
                            <i class="bi bi-plus-circle"></i> Novo Token
                        </button>
                    </div>
                </div>
            </div>
        </div>

            <!-- Alertas -->
        <div class="col-12">
            <div id="alert-container"></div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Sobre</h5>
                </div>
                <div class="card-body">
                    <p class="mb-3">Tokens permitem integrações seguras com o Mercado Livre Manager, controlando escopos e expirando automaticamente quando necessário.</p>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="bi bi-check2"></i> Escopos personalizáveis</li>
                        <li class="mb-2"><i class="bi bi-check2"></i> Expiração opcional</li>
                        <li class="mb-2"><i class="bi bi-check2"></i> Revogação imediata</li>
                        <li class="mb-0"><i class="bi bi-shield-lock"></i> Token exibido apenas uma vez</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-terminal"></i> Como usar</h5>
                </div>
                <div class="card-body">
                    <p class="fw-semibold">Header de autenticação</p>
                    <pre class="code-block">Authorization: Bearer SEU_TOKEN_AQUI</pre>
                    <p class="fw-semibold mt-3">Exemplo de chamada</p>
                    <pre class="code-block">curl -H "Authorization: Bearer SEU_TOKEN_AQUI" \
https://eskill.com.br/api/v1/orders</pre>
                </div>
            </div>
        </div>

            <!-- Lista de Tokens -->
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-column flex-md-row align-items-md-center gap-2">
                    <div>
                        <h5 class="mb-0"><i class="bi bi-list-check"></i> Seus Tokens</h5>
                        <small class="text-muted">Tokens ativos e revogados em um só lugar.</small>
                    </div>
                    <div class="ms-md-auto badge bg-light text-dark" id="token-count">0 tokens</div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle" id="tokens-table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Token</th>
                                    <th>Escopos</th>
                                    <th>Último uso</th>
                                    <th>Expira</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tokens-tbody">
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Carregando...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Criar Token -->
<div class="modal fade" id="createTokenModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-key"></i> Criar novo token</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="create-token-form">
                    <div class="mb-3">
                        <label for="token-name" class="form-label">Nome do token *</label>
                        <input type="text" class="form-control" id="token-name" required
                            placeholder="Ex: Integração ERP, App Mobile">
                        <small class="text-muted">Use um nome descritivo para identificar o uso do token</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Escopos (permissões)</label>
                        <div id="scopes-container">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="read" id="scope-read">
                                <label class="form-check-label" for="scope-read">Leitura de dados</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="orders:read" id="scope-orders-read">
                                <label class="form-check-label" for="scope-orders-read">Ler pedidos</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="items:read" id="scope-items-read">
                                <label class="form-check-label" for="scope-items-read">Ler anúncios</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="reports:read" id="scope-reports-read">
                                <label class="form-check-label" for="scope-reports-read">Gerar relatórios</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="token-expires" class="form-label">Expiração (opcional)</label>
                        <select class="form-select" id="token-expires">
                            <option value="">Nunca expira</option>
                            <option value="30">30 dias</option>
                            <option value="90">90 dias</option>
                            <option value="180">180 dias</option>
                            <option value="365">1 ano</option>
                        </select>
                    </div>
                </form>

                <div id="new-token-display" class="alert alert-success d-none mt-3">
                    <h6><i class="bi bi-check-circle"></i> Token criado!</h6>
                    <p class="mb-2"><strong>Copie e guarde este token em local seguro:</strong></p>
                    <div class="input-group">
                        <input type="text" class="form-control" id="new-token-value" readonly>
                        <button class="btn btn-outline-secondary" type="button" data-copy-target="new-token-value">
                            <i class="bi bi-clipboard"></i> Copiar
                        </button>
                    </div>
                    <small class="text-danger d-block mt-2">
                        ⚠️ Este token não será exibido novamente!
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="btn-create-token">
                    <i class="bi bi-plus-circle"></i> Criar token
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Confirmar Revogação -->
<div class="modal fade" id="revokeTokenModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Revogar token</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja revogar o token <strong id="revoke-token-name"></strong>?</p>
                <p class="text-danger">Esta ação não pode ser desfeita e aplicações usando este token perderão acesso imediatamente.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btn-confirm-revoke">
                    <i class="bi bi-trash"></i> Revogar token
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">

(() => {
    const alertContainer = document.getElementById('alert-container');
    const tokensTbody = document.getElementById('tokens-tbody');
    const tokenCount = document.getElementById('token-count');
    const createModal = document.getElementById('createTokenModal');
    const revokeModalEl = document.getElementById('revokeTokenModal');
    const createForm = document.getElementById('create-token-form');
    const newTokenDisplay = document.getElementById('new-token-display');
    const newTokenInput = document.getElementById('new-token-value');
    const createBtn = document.getElementById('btn-create-token');
    const revokeTokenName = document.getElementById('revoke-token-name');
    const confirmRevokeBtn = document.getElementById('btn-confirm-revoke');

    const modals = {
        create: new bootstrap.Modal(createModal),
        revoke: new bootstrap.Modal(revokeModalEl)
    };

    let tokens = [];
    let tokenToRevoke = null;

    const showAlert = (message, type = 'info') => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        alertContainer.innerHTML = '';
        alertContainer.appendChild(wrapper);
        setTimeout(() => {
            wrapper.remove();
        }, 5000);
    };

    const escapeHtml = text => {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    };

    const renderScopes = scopes => {
        if (!Array.isArray(scopes) || scopes.length === 0) {
            return '<span class="badge bg-secondary">Nenhum</span>';
        }
        return scopes
            .map(scope => `<span class="badge bg-info-subtle text-info-emphasis me-1">${escapeHtml(scope)}</span>`)
            .join('');
    };

    const renderStatus = (isActive, expiresAt) => {
        if (!isActive) return '<span class="badge bg-secondary">Revogado</span>';
        if (expiresAt && new Date(expiresAt) < new Date()) return '<span class="badge bg-warning text-dark">Expirado</span>';
        return '<span class="badge bg-success">Ativo</span>';
    };

    const renderTokens = () => {
        if (!tokens.length) {
            tokensTbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Nenhum token criado ainda</td></tr>';
            tokenCount.textContent = '0 tokens';
            return;
        }

        tokenCount.textContent = `${tokens.length} token${tokens.length > 1 ? 's' : ''}`;

        tokensTbody.innerHTML = tokens
            .map(token => {
                const lastUsed = token.last_used_at ? new Date(token.last_used_at).toLocaleString('pt-BR') : 'Nunca';
                const expires = token.expires_at ? new Date(token.expires_at).toLocaleDateString('pt-BR') : 'Nunca';
                return `
                    <tr>
                        <td><strong>${escapeHtml(token.name)}</strong></td>
                        <td><code>${token.token_preview}</code></td>
                        <td>${renderScopes(token.scopes)}</td>
                        <td>${lastUsed}</td>
                        <td>${expires}</td>
                        <td>${renderStatus(token.is_active, token.expires_at)}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-danger" data-revoke="${token.id}" ${!token.is_active ? 'disabled' : ''}>
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            })
            .join('');
    };

    const loadTokens = () => {
        tokensTbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>';
        requestJson('/api/tokens')
            .then(data => {
                if (data.success) {
                    tokens = data.tokens || [];
                    renderTokens();
                } else {
                    throw new Error(data.message || 'Erro ao carregar tokens');
                }
            })
            .catch(() => showAlert('Erro ao carregar tokens', 'danger'));
    };

    const resetCreateModal = () => {
        createForm.reset();
        createForm.classList.remove('d-none');
        createBtn.classList.remove('d-none');
        newTokenDisplay.classList.add('d-none');
        newTokenInput.value = '';
    };

    const createToken = () => {
        const formData = new FormData(createForm);
        const name = formData.get('token-name');
        const expires = formData.get('token-expires') || null;
        const scopes = Array.from(document.querySelectorAll('#scopes-container input:checked')).map(input => input.value);

        if (!name) {
            showAlert('Nome do token é obrigatório', 'warning');
            return;
        }

        createBtn.disabled = true;
        createBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        requestJson('/api/tokens', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name,
                scopes,
                expires_in_days: expires
            })
        })
            .then(data => {
                if (data.success && data.token?.token) {
                    newTokenInput.value = data.token.token;
                    createForm.classList.add('d-none');
                    createBtn.classList.add('d-none');
                    newTokenDisplay.classList.remove('d-none');
                    loadTokens();
                } else {
                    throw new Error(data.message || 'Erro ao criar token');
                }
            })
            .catch(() => showAlert('Erro ao criar token', 'danger'))
            .finally(() => {
                createBtn.disabled = false;
                createBtn.innerHTML = '<i class="bi bi-plus-circle"></i> Criar token';
            });
    };

    const confirmRevoke = tokenId => {
        tokenToRevoke = tokens.find(token => token.id === tokenId);
        if (!tokenToRevoke) return;
        revokeTokenName.textContent = tokenToRevoke.name;
        modals.revoke.show();
    };

    const revokeToken = () => {
        if (!tokenToRevoke) return;
        confirmRevokeBtn.disabled = true;
        confirmRevokeBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        requestJson(`/api/tokens/${tokenToRevoke.id}`, { method: 'DELETE' })
            .then(data => {
                if (data.success) {
                    showAlert('Token revogado com sucesso', 'success');
                    modals.revoke.hide();
                    loadTokens();
                } else {
                    throw new Error(data.message || 'Erro ao revogar token');
                }
            })
            .catch(() => showAlert('Erro ao revogar token', 'danger'))
            .finally(() => {
                confirmRevokeBtn.disabled = false;
                confirmRevokeBtn.innerHTML = '<i class="bi bi-trash"></i> Revogar token';
            });
    };

    createBtn.addEventListener('click', createToken);
    confirmRevokeBtn.addEventListener('click', revokeToken);
    createModal.addEventListener('hidden.bs.modal', resetCreateModal);

    document.addEventListener('click', event => {
        if (event.target.closest('[data-revoke]')) {
            const id = Number(event.target.closest('[data-revoke]').dataset.revoke);
            confirmRevoke(id);
        }

        if (event.target.matches('[data-copy-target]')) {
            const inputId = event.target.dataset.copyTarget;
            const input = document.getElementById(inputId);
            input?.select();
            document.execCommand('copy');
            showAlert('Token copiado!', 'success');
        }
    });

    loadTokens();
})();
</script>