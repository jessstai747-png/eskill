<?php
/**
 * Clone Items Management View
 * 
 * Dashboard de gerenciamento de itens clonados com filtros,
 * operações em lote e sincronização.
 */

$title = 'Gerenciar Itens Clonados';
$pageTitle = 'Gerenciar Clones';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-copy text-primary me-2"></i>
                Gerenciar Itens Clonados
            </h1>
            <p class="text-muted mb-0">Visualize, sincronize e gerencie seus clones</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" data-action="syncall">
                <i class="fas fa-sync-alt me-1"></i> Sincronizar Todos
            </button>
            <button class="btn btn-outline-success" data-action="exportitems">
                <i class="fas fa-file-export me-1"></i> Exportar CSV
            </button>
            <a href="/dashboard/clone" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Novo Clone
            </a>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div id="statTotal" class="h3 text-primary mb-1">-</div>
                    <small class="text-muted">Total Clonados</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div id="statActive" class="h3 text-success mb-1">-</div>
                    <small class="text-muted">Ativos</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div id="statVisits" class="h3 text-info mb-1">-</div>
                    <small class="text-muted">Visitas Total</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div id="statSales" class="h3 text-warning mb-1">-</div>
                    <small class="text-muted">Vendas</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div id="statRevenue" class="h3 text-success mb-1">-</div>
                    <small class="text-muted">Faturamento</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div id="statConversion" class="h3 text-purple mb-1">-</div>
                    <small class="text-muted">Conversão Média</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" placeholder="Título ou ID...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">Todos</option>
                        <option value="completed">Ativo</option>
                        <option value="paused">Pausado</option>
                        <option value="closed">Encerrado</option>
                        <option value="failed">Com Erro</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ordenar por</label>
                    <select class="form-select" id="sortBy">
                        <option value="created_at">Data Criação</option>
                        <option value="sales">Vendas</option>
                        <option value="visits">Visitas</option>
                        <option value="price">Preço</option>
                        <option value="title">Título</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ordem</label>
                    <select class="form-select" id="sortOrder">
                        <option value="DESC">Decrescente</option>
                        <option value="ASC">Crescente</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary flex-grow-1" data-action="loaditems">
                            <i class="fas fa-search me-1"></i> Filtrar
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-action="clearfilters">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Batch Actions -->
    <div id="batchActions" class="alert alert-info d-none mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <span><strong id="selectedCount">0</strong> itens selecionados</span>
            <div class="btn-group">
                <button class="btn btn-sm btn-success" onclick="batchOperation('activate')">
                    <i class="fas fa-play me-1"></i> Ativar
                </button>
                <button class="btn btn-sm btn-warning" onclick="batchOperation('pause')">
                    <i class="fas fa-pause me-1"></i> Pausar
                </button>
                <button class="btn btn-sm btn-info" onclick="batchOperation('sync')">
                    <i class="fas fa-sync me-1"></i> Sincronizar
                </button>
                <button class="btn btn-sm btn-danger" onclick="batchOperation('close')">
                    <i class="fas fa-times me-1"></i> Encerrar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Items Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="40">
                                <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll()">
                            </th>
                            <th>Item</th>
                            <th width="100">Preço</th>
                            <th width="80">Visitas</th>
                            <th width="80">Vendas</th>
                            <th width="80">Conv.</th>
                            <th width="100">Status</th>
                            <th width="120">Última Sync</th>
                            <th width="150">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTable">
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-top">
            <div class="d-flex justify-content-between align-items-center">
                <div id="paginationInfo" class="text-muted">
                    Mostrando 0 de 0 itens
                </div>
                <nav>
                    <ul id="pagination" class="pagination pagination-sm mb-0">
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Item Detail Modal -->
<div class="modal fade" id="itemDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="itemDetailContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" data-action="synccurrentitem">
                    <i class="fas fa-sync-alt me-1"></i> Sincronizar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Price Modal -->
<div class="modal fade" id="editPriceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Preço</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editPriceItemId">
                <div class="mb-3">
                    <label class="form-label">Preço Atual</label>
                    <input type="text" class="form-control" id="currentPrice" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Novo Preço</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="number" class="form-control" id="newPrice" step="0.01" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" data-action="saveprice">
                    <i class="fas fa-save me-1"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
let currentPage = 1;
let selectedItems = [];
let currentItemId = null;

async function requestJson(url, options = {}) {
    if (window.ApiClient && typeof window.ApiClient.json === 'function') {
        return window.ApiClient.json(url, options);
    }

    const response = await fetch(url, options);
    const data = await response.json();
    return { response, data };
}

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadItems();
});

async function loadStats() {
    try {
        const { data: stats } = await requestJson('/api/clone/items/stats');
        
        document.getElementById('statTotal').textContent = formatNumber(stats.total_items || 0);
        document.getElementById('statActive').textContent = formatNumber(stats.active_items || 0);
        document.getElementById('statVisits').textContent = formatNumber(stats.total_visits || 0);
        document.getElementById('statSales').textContent = formatNumber(stats.total_sales || 0);
        document.getElementById('statRevenue').textContent = formatCurrency(stats.total_revenue || 0);
        document.getElementById('statConversion').textContent = (stats.avg_conversion || 0).toFixed(2) + '%';
    } catch (error) {
        console.error('Erro ao carregar stats:', error);
    }
}

async function loadItems(page = 1) {
    currentPage = page;
    
    const params = new URLSearchParams({
        page: page,
        limit: 20,
        search: document.getElementById('search').value || '',
        status: document.getElementById('filterStatus').value || '',
        sort: document.getElementById('sortBy').value || 'created_at',
        order: document.getElementById('sortOrder').value || 'DESC'
    });
    
    document.getElementById('itemsTable').innerHTML = `
        <tr>
            <td colspan="9" class="text-center py-5">
                <div class="spinner-border text-primary"></div>
            </td>
        </tr>
    `;
    
    try {
        const { data } = await requestJson('/api/clone/items?' + params);
        
        renderItems(data.items || []);
        renderPagination(data.pagination || {});
        
    } catch (error) {
        console.error('Erro ao carregar itens:', error);
        document.getElementById('itemsTable').innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-5 text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Erro ao carregar itens
                </td>
            </tr>
        `;
    }
}

function renderItems(items) {
    if (!items.length) {
        document.getElementById('itemsTable').innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                    Nenhum item clonado encontrado
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    items.forEach(item => {
        const statusClass = getStatusClass(item.status);
        const statusText = getStatusText(item.status);
        const syncDate = item.last_synced_at ? formatDate(item.last_synced_at) : 'Nunca';
        
        html += `
            <tr>
                <td>
                    <input type="checkbox" class="form-check-input item-checkbox" 
                           value="${item.target_item_id}" onchange="updateSelection()">
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <div>
                            <div class="fw-medium text-truncate" style="max-width: 300px;">
                                ${escapeHtml(item.title || 'Sem título')}
                            </div>
                            <small class="text-muted">
                                ${item.target_item_id}
                                ${item.source_seller_id ? `• Seller: ${item.source_seller_id}` : ''}
                            </small>
                        </div>
                    </div>
                </td>
                <td class="fw-medium">${formatCurrency(item.price || 0)}</td>
                <td>${formatNumber(item.visits || 0)}</td>
                <td>${formatNumber(item.sales || 0)}</td>
                <td>${(item.conversion_rate || 0).toFixed(2)}%</td>
                <td>
                    <span class="badge ${statusClass}">${statusText}</span>
                </td>
                <td>
                    <small class="text-muted">${syncDate}</small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" data-action="showitemdetails" data-param="${item.target_item_id}" title="Ver detalhes">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="showEditPrice('${item.target_item_id}', ${item.price || 0})" title="Editar preço">
                            <i class="fas fa-tag"></i>
                        </button>
                        <button class="btn btn-outline-info" data-action="syncitem" data-param="${item.target_item_id}" title="Sincronizar">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <a href="https://www.mercadolivre.com.br/p/${item.target_item_id}" 
                           target="_blank" class="btn btn-outline-warning" title="Ver no ML">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </td>
            </tr>
        `;
    });
    
    document.getElementById('itemsTable').innerHTML = html;
}

function renderPagination(pagination) {
    const { page = 1, pages = 1, total = 0, limit = 20 } = pagination;
    const start = ((page - 1) * limit) + 1;
    const end = Math.min(page * limit, total);
    
    document.getElementById('paginationInfo').textContent = 
        `Mostrando ${start}-${end} de ${total} itens`;
    
    let html = '';
    
    if (pages > 1) {
        html += `
            <li class="page-item ${page <= 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="loadItems(${page - 1}); return false;">«</a>
            </li>
        `;
        
        for (let i = Math.max(1, page - 2); i <= Math.min(pages, page + 2); i++) {
            html += `
                <li class="page-item ${i === page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadItems(${i}); return false;">${i}</a>
                </li>
            `;
        }
        
        html += `
            <li class="page-item ${page >= pages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="loadItems(${page + 1}); return false;">»</a>
            </li>
        `;
    }
    
    document.getElementById('pagination').innerHTML = html;
}

function toggleSelectAll() {
    const checked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = checked);
    updateSelection();
}

function updateSelection() {
    selectedItems = Array.from(document.querySelectorAll('.item-checkbox:checked'))
        .map(cb => cb.value);
    
    const batchActions = document.getElementById('batchActions');
    const selectedCount = document.getElementById('selectedCount');
    
    if (selectedItems.length > 0) {
        batchActions.classList.remove('d-none');
        selectedCount.textContent = selectedItems.length;
    } else {
        batchActions.classList.add('d-none');
    }
}

async function batchOperation(operation) {
    if (!selectedItems.length) return;
    
    const confirmMsg = {
        'activate': 'Deseja ativar os itens selecionados?',
        'pause': 'Deseja pausar os itens selecionados?',
        'sync': 'Deseja sincronizar os itens selecionados?',
        'close': 'Deseja encerrar os itens selecionados? Esta ação é irreversível!'
    };
    
    if (!confirm(confirmMsg[operation])) return;
    
    try {
        const { data: result } = await requestJson('/api/clone/items/batch', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                operation: operation,
                item_ids: selectedItems
            })
        });

        
        alert(`Operação concluída!\nSucesso: ${result.success}\nErros: ${result.errors}`);
        
        loadItems(currentPage);
        loadStats();
        
    } catch (error) {
        alert('Erro ao executar operação: ' + error.message);
    }
}

async function showItemDetails(itemId) {
    currentItemId = itemId;
    const modal = new bootstrap.Modal(document.getElementById('itemDetailModal'));
    modal.show();
    
    document.getElementById('itemDetailContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary"></div>
        </div>
    `;
    
    try {
        const { data: item } = await requestJson(`/api/clone/items/${itemId}`);
        
        document.getElementById('itemDetailContent').innerHTML = renderItemDetails(item);
        
    } catch (error) {
        document.getElementById('itemDetailContent').innerHTML = `
            <div class="alert alert-danger">Erro ao carregar detalhes</div>
        `;
    }
}

function renderItemDetails(item) {
    return `
        <div class="row">
            <div class="col-md-6">
                <h6 class="fw-bold mb-3">Informações do Clone</h6>
                <table class="table table-sm">
                    <tr>
                        <td class="text-muted">ID Clone</td>
                        <td class="fw-medium">${item.target_item_id}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">ID Original</td>
                        <td>${item.source_item_id || '-'}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Seller Origem</td>
                        <td>${item.source_seller_id || '-'}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Categoria</td>
                        <td>${item.category_id || '-'}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status</td>
                        <td><span class="badge ${getStatusClass(item.status)}">${getStatusText(item.status)}</span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Criado em</td>
                        <td>${formatDate(item.created_at)}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold mb-3">Performance</h6>
                <table class="table table-sm">
                    <tr>
                        <td class="text-muted">Preço</td>
                        <td class="fw-medium">${formatCurrency(item.price || 0)}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Visitas</td>
                        <td>${formatNumber(item.visits || 0)}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Vendas</td>
                        <td>${formatNumber(item.sales || 0)}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Faturamento</td>
                        <td>${formatCurrency(item.revenue || 0)}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Taxa Conversão</td>
                        <td>${(item.conversion_rate || 0).toFixed(2)}%</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Última Sync</td>
                        <td>${item.last_synced_at ? formatDate(item.last_synced_at) : 'Nunca'}</td>
                    </tr>
                </table>
            </div>
        </div>
        <hr>
        <h6 class="fw-bold mb-3">Título</h6>
        <p class="mb-0">${escapeHtml(item.title || 'Sem título')}</p>
    `;
}

function showEditPrice(itemId, currentPrice) {
    document.getElementById('editPriceItemId').value = itemId;
    document.getElementById('currentPrice').value = formatCurrency(currentPrice);
    document.getElementById('newPrice').value = currentPrice.toFixed(2);
    
    new bootstrap.Modal(document.getElementById('editPriceModal')).show();
}

async function savePrice() {
    const itemId = document.getElementById('editPriceItemId').value;
    const newPrice = parseFloat(document.getElementById('newPrice').value);
    
    if (isNaN(newPrice) || newPrice <= 0) {
        alert('Preço inválido');
        return;
    }
    
    try {
        const { data: result } = await requestJson(`/api/clone/sync/price/${itemId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ price: newPrice })
        });

        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('editPriceModal')).hide();
            loadItems(currentPage);
            alert('Preço atualizado com sucesso!');
        } else {
            alert('Erro: ' + (result.error || 'Falha ao atualizar'));
        }
    } catch (error) {
        alert('Erro ao salvar: ' + error.message);
    }
}

async function syncItem(itemId) {
    try {
        const { data: result } = await requestJson(`/api/clone/sync/item/${itemId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        
        if (result.success) {
            alert('Item sincronizado com sucesso!');
            loadItems(currentPage);
        } else {
            alert('Erro: ' + (result.error || 'Falha ao sincronizar'));
        }
    } catch (error) {
        alert('Erro ao sincronizar: ' + error.message);
    }
}

function syncCurrentItem() {
    if (currentItemId) {
        syncItem(currentItemId);
    }
}

async function syncAll() {
    if (!confirm('Deseja sincronizar todos os itens? Isso pode demorar alguns minutos.')) {
        return;
    }
    
    try {
        const { data: result } = await requestJson('/api/clone/sync/all', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ limit: 100 })
        });

        
        alert(`Sincronização concluída!\nTotal: ${result.total}\nSincronizados: ${result.synced}\nErros: ${result.errors}`);
        
        loadItems(currentPage);
        loadStats();
        
    } catch (error) {
        alert('Erro ao sincronizar: ' + error.message);
    }
}

function exportItems() {
    const params = new URLSearchParams({
        status: document.getElementById('filterStatus').value || '',
    });
    
    window.open('/api/clone/items/export?' + params, '_blank');
}

function clearFilters() {
    document.getElementById('search').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('sortBy').value = 'created_at';
    document.getElementById('sortOrder').value = 'DESC';
    loadItems(1);
}

// Utility functions
function formatNumber(n) {
    return new Intl.NumberFormat('pt-BR').format(n || 0);
}

function formatCurrency(n) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(n || 0);
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getStatusClass(status) {
    const classes = {
        'completed': 'bg-success',
        'active': 'bg-success',
        'paused': 'bg-warning',
        'closed': 'bg-secondary',
        'failed': 'bg-danger',
        'pending': 'bg-info'
    };
    return classes[status] || 'bg-secondary';
}

function getStatusText(status) {
    const texts = {
        'completed': 'Ativo',
        'active': 'Ativo',
        'paused': 'Pausado',
        'closed': 'Encerrado',
        'failed': 'Erro',
        'pending': 'Pendente'
    };
    return texts[status] || status;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
// CSP Event Delegation
document.addEventListener('click', e => {
    const t = e.target.closest('[data-action]');
    if (!t) return;
    const action = t.dataset.action;
    const fn = window[action] || window[action.replace(/-([a-z])/g, (m,c) => c.toUpperCase())];
    if (fn) { e.preventDefault(); fn(t.dataset.param || t.dataset.id); }
});
</script>
