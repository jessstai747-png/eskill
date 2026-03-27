<!-- Developer Hub Tab -->
<div class="row">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">🔑 Developer Hub</h4>
                        <p class="text-muted mb-0">Gerencie chaves de API para integrações externas.</p>
                    </div>
                    <button class="btn btn-primary" onclick="DevHub.createKey()">
                        <i class="bi bi-plus-lg"></i> Nova Chave API
                    </button>
                </div>

                <!-- API Keys Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Nome</th>
                                <th>Client ID</th>
                                <th>Status</th>
                                <th>Criado em</th>
                                <th>Último Uso</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="api-keys-list">
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Carregando chaves...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- API Docs Preview -->
                <div class="bg-light p-4 rounded-3 border">
                    <h5 class="mb-3"><i class="bi bi-book"></i> Como usar a API</h5>

                    <div class="mb-3">
                        <label class="form-label fw-bold">1. Autenticação (Header)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">X-API-KEY</span>
                            <input type="text" class="form-control" value="seu_client_id" readonly>
                            <span class="input-group-text bg-white">X-API-SECRET</span>
                            <input type="text" class="form-control" value="seu_client_secret" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">2. Exemplo de Requisição (Curl)</label>
                        <pre class="bg-dark text-white p-3 rounded">curl -X GET 'https://eskill.com.br/api/items' \
  -H 'X-API-KEY: seu_client_id' \
  -H 'X-API-SECRET: seu_client_secret'</pre>
                    </div>

                    <a href="/docs/api" class="btn btn-outline-secondary btn-sm">Ver Documentação Completa</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Key Modal -->
<div class="modal fade" id="createKeyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Chave de API</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nome da Aplicação/Integração</label>
                    <input type="text" class="form-control" id="key-name" placeholder="Ex: Integração ERP">
                </div>
                <div id="new-key-display" class="alert alert-success" style="display: none;">
                    <p class="mb-2"><strong>Chave Criada com Sucesso!</strong></p>
                    <p class="mb-1 text-danger small">Copie o Client Secret agora. Ele não será mostrado novamente.</p>

                    <label class="small fw-bold mt-2">Client ID</label>
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" class="form-control" id="new-client-id" readonly>
                        <button class="btn btn-outline-secondary" onclick="DevHub.copy('new-client-id')"><i class="bi bi-clipboard"></i></button>
                    </div>

                    <label class="small fw-bold">Client Secret</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="new-client-secret" readonly>
                        <button class="btn btn-outline-secondary" onclick="DevHub.copy('new-client-secret')"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="btn-save-key" onclick="DevHub.saveKey()">Criar Chave</button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
    const DevHub = {
        init() {
            this.loadKeys();
        },

        async loadKeys() {
            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/api-keys');

                const tbody = document.getElementById('api-keys-list');
                if (data.success && data.keys.length > 0) {
                    tbody.innerHTML = data.keys.map(key => `
                    <tr>
                        <td><strong>${key.name}</strong></td>
                        <td><code class="text-primary">${key.client_id}</code></td>
                        <td><span class="badge bg-${key.status === 'active' ? 'success' : 'danger'}">${key.status}</span></td>
                        <td>${new Date(key.created_at).toLocaleDateString()}</td>
                        <td>${key.last_used_at ? new Date(key.last_used_at).toLocaleDateString() : '-'}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-danger" onclick="DevHub.revoke('${key.client_id}')">
                                <i class="bi bi-trash"></i> Revogar
                            </button>
                        </td>
                    </tr>
                `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Nenhuma chave de API encontrada.</td></tr>';
                }
            } catch (error) {
                console.error('Erro ao carregar chaves:', error);
            }
        },

        createKey() {
            document.getElementById('key-name').value = '';
            document.getElementById('new-key-display').style.display = 'none';
            document.getElementById('btn-save-key').style.display = 'block';
            new bootstrap.Modal(document.getElementById('createKeyModal')).show();
        },

        async saveKey() {
            const name = document.getElementById('key-name').value;
            if (!name) return alert('Digite um nome para a chave');

            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/api-keys', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        name: name
                    })
                });

                if (data.success) {
                    document.getElementById('new-client-id').value = data.key.client_id;
                    document.getElementById('new-client-secret').value = data.key.client_secret;
                    document.getElementById('new-key-display').style.display = 'block';
                    document.getElementById('btn-save-key').style.display = 'none';
                    this.loadKeys(); // Refresh list background
                } else {
                    alert('Erro: ' + data.error);
                }
            } catch (error) {
                alert('Erro de conexão');
            }
        },

        async revoke(clientId) {
            if (!confirm('Tem certeza? Essa ação não pode ser desfeita e a integração parará de funcionar.')) return;

            try {
                await requestJson(`/api/seo-killer/api-keys/${clientId}`, {
                    method: 'DELETE'
                });
                this.loadKeys();
            } catch (error) {
                alert('Erro ao revogar chave');
            }
        },

        copy(id) {
            const el = document.getElementById(id);
            el.select();
            document.execCommand('copy');
            SEOKiller.showSuccess('Copiado!');
        }
    };

    // Auto init when tab shown
    document.addEventListener('DOMContentLoaded', () => {
        const tabEl = document.querySelector('button[data-bs-target="#developer-hub"]');
        if (tabEl) {
            tabEl.addEventListener('shown.bs.tab', event => {
                DevHub.init();
            });
        }
    });
</script>