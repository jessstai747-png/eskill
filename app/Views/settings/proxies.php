<?php

/**
 * Página de Gerenciamento de Proxies
 * 
 * Permite configurar e gerenciar proxies para contornar bloqueios da API do ML
 */

$pageTitle = 'Gerenciamento de Proxies';
$pageDescription = 'Configure proxies para acessar a API do Mercado Livre';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - ML Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #0a0a0a;
            --bg-secondary: #141414;
            --bg-card: #1a1a1a;
            --text-primary: #ffffff;
            --text-secondary: #888;
            --accent: #FFE600;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --info: #3B82F6;
        }

        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
        }

        .card-header {
            background: rgba(255, 230, 0, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .table {
            color: var(--text-primary);
        }

        .table th {
            border-color: rgba(255, 255, 255, 0.1);
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .table td {
            border-color: rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }

        .btn-accent {
            background: var(--accent);
            color: #000;
            font-weight: 600;
        }

        .btn-accent:hover {
            background: #e6cf00;
            color: #000;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning);
        }

        .form-control,
        .form-select {
            background: var(--bg-secondary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
        }

        .form-control:focus,
        .form-select:focus {
            background: var(--bg-secondary);
            border-color: var(--accent);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem rgba(255, 230, 0, 0.25);
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .status-active {
            background: var(--success);
        }

        .status-blacklisted {
            background: var(--danger);
        }

        .status-testing {
            background: var(--warning);
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(255, 230, 0, 0.1) 0%, rgba(255, 230, 0, 0.05) 100%);
            border: 1px solid rgba(255, 230, 0, 0.2);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">
                    <i class="fas fa-network-wired me-2 text-warning"></i>
                    <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
                </h1>
                <p class="text-secondary mb-0"><?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div>
                <a href="/research" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-2"></i> Voltar
                </a>
                <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#addProxyModal">
                    <i class="fas fa-plus me-2"></i> Adicionar Proxy
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number" id="totalProxies">-</div>
                    <div class="stat-label">Total de Proxies</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-success" id="availableProxies">-</div>
                    <div class="stat-label">Disponíveis</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-danger" id="blacklistedProxies">-</div>
                    <div class="stat-label">Na Blacklist</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-info" id="proxyEnabled">-</div>
                    <div class="stat-label">Sistema de Proxy</div>
                </div>
            </div>
        </div>

        <!-- Info Alert -->
        <div class="alert alert-info border-0 mb-4" style="background: rgba(59, 130, 246, 0.1);">
            <h6 class="alert-heading">
                <i class="fas fa-info-circle me-2"></i>
                Por que usar proxies?
            </h6>
            <p class="mb-2">
                O Mercado Livre bloqueia requisições de busca vindas de servidores de data center para prevenir scraping.
                Configurar um proxy residencial permite contornar essa restrição.
            </p>
            <hr>
            <p class="mb-0 small">
                <strong>Recomendação:</strong> Use serviços de proxy residencial como
                <a href="https://brightdata.com" target="_blank" class="text-info">BrightData</a>,
                <a href="https://oxylabs.io" target="_blank" class="text-info">Oxylabs</a> ou
                <a href="https://smartproxy.com" target="_blank" class="text-info">SmartProxy</a>
                para melhores resultados.
            </p>
        </div>

        <!-- Proxy Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Proxies Configurados
                </h5>
                <div>
                    <button class="btn btn-sm btn-outline-warning me-2" onclick="testAllProxies()">
                        <i class="fas fa-vial me-1"></i> Testar Todos
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="clearBlacklist()">
                        <i class="fas fa-eraser me-1"></i> Limpar Blacklist
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Tipo</th>
                                <th>Host</th>
                                <th>Porta</th>
                                <th>País</th>
                                <th>Taxa de Sucesso</th>
                                <th>Requisições</th>
                                <th>Origem</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="proxyTableBody">
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm text-warning" role="status"></div>
                                    <span class="ms-2">Carregando...</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ENV Configuration Info -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-cog me-2"></i>
                    Configuração via .env
                </h5>
            </div>
            <div class="card-body">
                <p class="text-secondary">
                    Você também pode configurar o proxy principal diretamente no arquivo <code>.env</code>:
                </p>
                <pre class="bg-dark p-3 rounded"><code># Proxy Configuration for Mercado Livre API
ML_PROXY_ENABLED=true
ML_PROXY_TYPE=http
ML_PROXY_HOST=proxy.exemplo.com
ML_PROXY_PORT=8080
ML_PROXY_USER=seu_usuario
ML_PROXY_PASS=sua_senha</code></pre>
            </div>
        </div>
    </div>

    <!-- Add Proxy Modal -->
    <div class="modal fade" id="addProxyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>
                        Adicionar Proxy
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="addProxyForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo</label>
                                <select class="form-select" name="type" required>
                                    <option value="http">HTTP</option>
                                    <option value="https">HTTPS</option>
                                    <option value="socks4">SOCKS4</option>
                                    <option value="socks5">SOCKS5</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Prioridade</label>
                                <input type="number" class="form-control" name="priority" value="50" min="1" max="100">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Host *</label>
                                <input type="text" class="form-control" name="host" placeholder="proxy.exemplo.com" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Porta *</label>
                                <input type="text" class="form-control" name="port" placeholder="8080" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Usuário</label>
                                <input type="text" class="form-control" name="username" placeholder="(opcional)">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Senha</label>
                                <input type="password" class="form-control" name="password" placeholder="(opcional)">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-accent">
                            <i class="fas fa-plus me-2"></i>
                            Adicionar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
        // Load proxies on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadProxies();
            loadStatus();
        });

        async function loadProxies() {
            try {
                const result = await requestJson('/api/proxies');

                if (result.success) {
                    renderProxyTable(result.data.proxies);
                    updateStats(result.data.stats);
                } else {
                    showToast('Erro ao carregar proxies', 'danger');
                }
            } catch (error) {
                console.error(error);
                renderProxyTable([]);
            }
        }

        async function loadStatus() {
            try {
                const result = await requestJson('/api/proxies/status');

                if (result.success) {
                    document.getElementById('proxyEnabled').textContent =
                        result.data.enabled ? 'Ativado' : 'Desativado';
                    document.getElementById('proxyEnabled').className =
                        'stat-number ' + (result.data.enabled ? 'text-success' : 'text-warning');
                }
            } catch (error) {
                console.error(error);
            }
        }

        function renderProxyTable(proxies) {
            const tbody = document.getElementById('proxyTableBody');

            if (proxies.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <i class="fas fa-network-wired fa-3x text-secondary mb-3 d-block"></i>
                            <p class="text-secondary mb-0">Nenhum proxy configurado</p>
                            <small class="text-secondary">Adicione um proxy para contornar bloqueios da API</small>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = proxies.map(proxy => `
                <tr>
                    <td>
                        <span class="status-indicator status-${proxy.status}"></span>
                        ${proxy.status === 'active' ? 'Ativo' : proxy.status === 'blacklisted' ? 'Bloqueado' : 'Testando'}
                    </td>
                    <td><span class="badge bg-secondary">${proxy.type.toUpperCase()}</span></td>
                    <td><code>${proxy.host}</code></td>
                    <td>${proxy.port}</td>
                    <td><span class="badge badge-${proxy.country === 'BR' ? 'success' : 'warning'}">${proxy.country}</span></td>
                    <td>
                        <div class="progress" style="height: 6px; width: 80px;">
                            <div class="progress-bar ${proxy.success_rate >= 80 ? 'bg-success' : proxy.success_rate >= 50 ? 'bg-warning' : 'bg-danger'}" 
                                 style="width: ${proxy.success_rate}%"></div>
                        </div>
                        <small class="text-secondary">${proxy.success_rate}%</small>
                    </td>
                    <td>${proxy.total_requests}</td>
                    <td><span class="badge bg-dark">${proxy.source}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-info me-1" onclick="testProxy('${proxy.id}')" title="Testar">
                            <i class="fas fa-vial"></i>
                        </button>
                        ${proxy.source !== 'env' ? `
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteProxy('${proxy.id}')" title="Remover">
                            <i class="fas fa-trash"></i>
                        </button>
                        ` : ''}
                    </td>
                </tr>
            `).join('');
        }

        function updateStats(stats) {
            document.getElementById('totalProxies').textContent = stats.total_proxies;
            document.getElementById('availableProxies').textContent = stats.available_proxies;
            document.getElementById('blacklistedProxies').textContent = stats.blacklisted;
        }

        async function testProxy(id) {
            showToast('Testando proxy...', 'info');

            try {
                const result = await requestJson(`/api/proxies/${id}/test`, {
                    method: 'POST'
                });

                if (result.success && result.data.success) {
                    showToast(`Proxy funcionando! Tempo: ${result.data.response_time}ms`, 'success');
                } else {
                    showToast(`Proxy falhou: ${result.data?.message || result.error}`, 'danger');
                }

                loadProxies();
            } catch (error) {
                showToast('Erro ao testar proxy', 'danger');
            }
        }

        async function testAllProxies() {
            showToast('Testando todos os proxies...', 'info');

            try {
                const result = await requestJson('/api/proxies/test-all', {
                    method: 'POST'
                });

                if (result.success) {
                    const working = Object.values(result.data).filter(r => r.success).length;
                    const total = Object.keys(result.data).length;
                    showToast(`${working}/${total} proxies funcionando`, working === total ? 'success' : 'warning');
                }

                loadProxies();
            } catch (error) {
                showToast('Erro ao testar proxies', 'danger');
            }
        }

        async function deleteProxy(id) {
            if (!confirm('Tem certeza que deseja remover este proxy?')) return;

            try {
                const result = await requestJson(`/api/proxies/${id}`, {
                    method: 'DELETE'
                });

                if (result.success) {
                    showToast('Proxy removido', 'success');
                    loadProxies();
                } else {
                    showToast(result.error, 'danger');
                }
            } catch (error) {
                showToast('Erro ao remover proxy', 'danger');
            }
        }

        async function clearBlacklist() {
            try {
                const result = await requestJson('/api/proxies/clear-blacklist', {
                    method: 'POST'
                });

                if (result.success) {
                    showToast('Blacklist limpa', 'success');
                    loadProxies();
                }
            } catch (error) {
                showToast('Erro ao limpar blacklist', 'danger');
            }
        }

        // Add proxy form
        document.getElementById('addProxyForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            try {
                const result = await requestJson('/api/proxies', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                if (result.success) {
                    showToast('Proxy adicionado', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addProxyModal')).hide();
                    this.reset();
                    loadProxies();
                } else {
                    showToast(result.error, 'danger');
                }
            } catch (error) {
                showToast('Erro ao adicionar proxy', 'danger');
            }
        });

        function showToast(message, type = 'info') {
            const container = document.querySelector('.toast-container');
            const id = 'toast-' + Date.now();

            const toast = document.createElement('div');
            toast.className = `toast show`;
            toast.innerHTML = `
                <div class="toast-header bg-${type} text-white">
                    <i class="fas fa-${type === 'success' ? 'check' : type === 'danger' ? 'times' : 'info'} me-2"></i>
                    <strong class="me-auto">${type === 'success' ? 'Sucesso' : type === 'danger' ? 'Erro' : 'Info'}</strong>
                    <button type="button" class="btn-close btn-close-white" onclick="this.closest('.toast').remove()"></button>
                </div>
                <div class="toast-body bg-dark text-white">${message}</div>
            `;

            container.appendChild(toast);

            setTimeout(() => toast.remove(), 5000);
        }
    </script>
</body>

</html>