<?php
$title = 'Clone de Catálogo';
$subtitle = 'Replique produtos entre contas do Mercado Livre';
include __DIR__ . '/../layouts/modern/partials/page-header.php';
?>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                            <i class="bi bi-files text-primary fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Total Clonados</div>
                        <h3 class="mb-0" id="stat-total">-</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3">
                            <i class="bi bi-check-circle text-success fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Sucesso</div>
                        <h3 class="mb-0" id="stat-success">-</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                            <i class="bi bi-clock-history text-warning fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Pendentes</div>
                        <h3 class="mb-0" id="stat-pending">-</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                            <i class="bi bi-x-circle text-danger fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Falhas</div>
                        <h3 class="mb-0" id="stat-failed">-</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Card -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-files"></i> Clone de Produtos</h5>
                <small class="text-muted">Clone produtos rapidamente entre suas contas do Mercado Livre</small>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Como funciona:</strong> Selecione um produto de uma conta origem e replique para uma ou mais contas destino com ajustes de preço e estoque automáticos.
                </div>

                <form id="clone-form">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Conta Origem</label>
                            <select class="form-select" id="source-account" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contas Destino</label>
                            <select class="form-select" id="target-accounts" multiple required>
                                <option value="">Carregue uma conta origem primeiro</option>
                            </select>
                            <small class="form-text text-muted">Segure Ctrl/Cmd para selecionar múltiplas contas</small>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-12">
                            <label class="form-label">Produto a Clonar</label>
                            <input type="text" class="form-control" id="item-search" placeholder="Digite o ID ou busque pelo nome..." required>
                            <div id="item-results" class="list-group mt-2" style="display: none;"></div>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Estratégia de Preço</label>
                            <select class="form-select" id="price-strategy">
                                <option value="same">Mesmo preço</option>
                                <option value="increase">Aumentar</option>
                                <option value="decrease">Reduzir</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="price-adjustment-container" style="display: none;">
                            <label class="form-label">Ajuste (%)</label>
                            <input type="number" class="form-control" id="price-adjustment" min="0" max="100" value="0" step="0.1">
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-12">
                            <label class="form-label">Estratégia de Estoque</label>
                            <select class="form-select" id="stock-strategy">
                                <option value="same">Mesmo estoque</option>
                                <option value="custom">Definir quantidade</option>
                                <option value="percentage">Porcentagem do original</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-play-circle"></i> Iniciar Clonagem
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-lg" onclick="catalogClone.reset()">
                            <i class="bi bi-arrow-clockwise"></i> Limpar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Recent Clones Table -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Histórico Recente</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Data</th>
                                <th>Produto</th>
                                <th>Origem</th>
                                <th>Destino</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="history-list">
                            <tr><td colspan="6" class="text-center py-4">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.badge-status { font-size: 0.75rem; padding: 0.35rem 0.75rem; }
#item-results { max-height: 300px; overflow-y: auto; }
.list-group-item:hover { background-color: rgba(13, 110, 253, 0.05); cursor: pointer; }
</style>

<script src="/js/catalog-clone.js"></script>
<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
async function requestJson(url, options = {}) {
    if (window.ApiClient) return window.ApiClient.request(url, options);
    const resp = await fetch(url, { credentials: 'include', ...options });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}

function normalizeExternalUrl(url) {
    if (!url || typeof url !== 'string') return '';
    const trimmed = url.trim();
    if (!trimmed) return '';
    if (/^(data:|blob:)/i.test(trimmed)) return trimmed;
    if (trimmed.startsWith('//')) return window.location.protocol + trimmed;
    if (/^http:\/\//i.test(trimmed)) return trimmed.replace(/^http:\/\//i, 'https://');
    return trimmed;
}

const catalogClone = {
    init: function() {
        this.loadAccounts();
        this.loadMetrics();
        this.loadHistory();
        this.setupEventListeners();
    },
    
    setupEventListeners: function() {
        document.getElementById('clone-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitClone();
        });

        document.getElementById('price-strategy').addEventListener('change', (e) => {
            const container = document.getElementById('price-adjustment-container');
            container.style.display = (e.target.value !== 'same') ? 'block' : 'none';
        });

        document.getElementById('item-search').addEventListener('input', (e) => {
            if (e.target.value.length >= 3) {
                this.searchItems(e.target.value);
            } else {
                document.getElementById('item-results').style.display = 'none';
            }
        });
    },

    loadAccounts: async function() {
        try {
            const data = await requestJson('/api/auth/accounts');
            if (data.accounts) {
                const sourceSelect = document.getElementById('source-account');
                const targetSelect = document.getElementById('target-accounts');
                
                data.accounts.forEach(account => {
                    sourceSelect.innerHTML += `<option value="${account.id}">${account.nickname}</option>`;
                    targetSelect.innerHTML += `<option value="${account.id}">${account.nickname}</option>`;
                });
            }
        } catch (e) {
            console.error('Error loading accounts:', e);
        }
    },

    loadMetrics: async function() {
        try {
            const data = await requestJson('/api/catalog/clone/metrics');
            
            document.getElementById('stat-total').textContent = data.total || 0;
            document.getElementById('stat-success').textContent = data.success || 0;
            document.getElementById('stat-pending').textContent = data.pending || 0;
            document.getElementById('stat-failed').textContent = data.failed || 0;
        } catch (e) {
            console.error('Error loading metrics:', e);
        }
    },

    loadHistory: async function() {
        try {
            const data = await requestJson('/api/catalog/clone/history');
            
            const tbody = document.getElementById('history-list');
            if (data.history && data.history.length > 0) {
                tbody.innerHTML = '';
                data.history.forEach(item => {
                    const statusBadge = this.getStatusBadge(item.status);
                    tbody.innerHTML += `
                        <tr>
                            <td>${new Date(item.created_at).toLocaleDateString('pt-BR')}</td>
                            <td>${item.item_title}</td>
                            <td>${item.source_account}</td>
                            <td>${item.target_account}</td>
                            <td>${statusBadge}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="catalogClone.viewDetails('${item.id}')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Nenhum histórico encontrado</td></tr>';
            }
        } catch (e) {
            console.error('Error loading history:', e);
        }
    },

    getStatusBadge: function(status) {
        const badges = {
            'success': '<span class="badge bg-success">Sucesso</span>',
            'pending': '<span class="badge bg-warning">Pendente</span>',
            'failed': '<span class="badge bg-danger">Falha</span>',
            'processing': '<span class="badge bg-info">Processando</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">Desconhecido</span>';
    },

    searchItems: async function(query) {
        const sourceAccount = document.getElementById('source-account').value;
        if (!sourceAccount) {
            alert('Selecione primeiro uma conta origem');
            return;
        }

        try {
            const data = await requestJson(`/api/catalog/search?account=${sourceAccount}&q=${encodeURIComponent(query)}`);
            
            const resultsDiv = document.getElementById('item-results');
            if (data.items && data.items.length > 0) {
                resultsDiv.innerHTML = '';
                data.items.forEach(item => {
                    resultsDiv.innerHTML += `
                        <div class="list-group-item list-group-item-action" onclick="catalogClone.selectItem('${item.id}', '${item.title}')">
                            <div class="d-flex">
                                <img src="${normalizeExternalUrl(item.thumbnail) || ''}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;" class="me-3">
                                <div>
                                    <div class="fw-bold">${item.title}</div>
                                    <small class="text-muted">${item.id}</small>
                                </div>
                            </div>
                        </div>
                    `;
                });
                resultsDiv.style.display = 'block';
            } else {
                resultsDiv.innerHTML = '<div class="list-group-item">Nenhum item encontrado</div>';
                resultsDiv.style.display = 'block';
            }
        } catch (e) {
            console.error('Error searching items:', e);
        }
    },

    selectItem: function(id, title) {
        document.getElementById('item-search').value = title;
        document.getElementById('item-search').dataset.itemId = id;
        document.getElementById('item-results').style.display = 'none';
    },

    submitClone: async function() {
        const sourceAccount = document.getElementById('source-account').value;
        const targetAccounts = Array.from(document.getElementById('target-accounts').selectedOptions).map(o => o.value);
        const itemId = document.getElementById('item-search').dataset.itemId;
        const priceStrategy = document.getElementById('price-strategy').value;
        const stockStrategy = document.getElementById('stock-strategy').value;

        if (!itemId) {
            alert('Selecione um produto primeiro');
            return;
        }

        if (targetAccounts.length === 0) {
            alert('Selecione pelo menos uma conta destino');
            return;
        }

        const payload = {
            source_account_id: sourceAccount,
            source_item_id: itemId,
            target_account_ids: targetAccounts,
            pricing_strategy: { type: priceStrategy },
            stock_strategy: { type: stockStrategy }
        };

        try {
            const data = await requestJson('/api/catalog/clone/batch', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            if (data.status === 'accepted' || data.status === 'success') {
                alert(`Clonagem iniciada com sucesso! ${data.jobs_count || 1} jobs criados.`);
                this.reset();
                this.loadMetrics();
                this.loadHistory();
            } else {
                alert('Erro ao iniciar clonagem: ' + (data.message || 'Erro desconhecido'));
            }
        } catch (e) {
            console.error('Error:', e);
            alert('Erro ao processar requisição');
        }
    },

    reset: function() {
        document.getElementById('clone-form').reset();
        document.getElementById('item-search').dataset.itemId = '';
        document.getElementById('item-results').style.display = 'none';
        document.getElementById('price-adjustment-container').style.display ='none';
    },

    viewDetails: function(id) {
        alert('Ver detalhes do clone ID: ' + id);
        // In production, open modal with clone details
    }
};

document.addEventListener('DOMContentLoaded', () => catalogClone.init());
</script>
