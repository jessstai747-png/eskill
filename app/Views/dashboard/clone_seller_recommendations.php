<?php
/**
 * Clone Seller Recommendations Dashboard
 * Interface para recomendações de vendedores via ML
 */
$this->layout('layouts/dashboard', ['title' => 'Recomendações de Vendedores - Clonador']);
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1">
                    <i class="bi bi-lightbulb text-warning me-2"></i>
                    Recomendações de Vendedores
                </h1>
                <p class="text-muted mb-0">Descubra vendedores potenciais para clonagem baseado em ML</p>
            </div>
            <button class="btn btn-primary" onclick="refreshRecommendations()">
                <i class="bi bi-arrow-repeat me-1"></i> Atualizar Recomendações
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Vendedores Recomendados</h6>
                            <h2 class="mb-0" id="totalRecommended">-</h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-people text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Score Médio</h6>
                            <h2 class="mb-0" id="avgScore">-</h2>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-star text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Itens Disponíveis</h6>
                            <h2 class="mb-0" id="totalItems">-</h2>
                        </div>
                        <div class="bg-info bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-box text-info fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Tendências</h6>
                            <h2 class="mb-0" id="trendingCategories">-</h2>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-graph-up text-warning fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Categoria</label>
                    <select class="form-select" id="filterCategory" onchange="loadRecommendations()">
                        <option value="">Todas as categorias</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Score Mínimo</label>
                    <select class="form-select" id="filterScore" onchange="loadRecommendations()">
                        <option value="0">Todos</option>
                        <option value="70">70+</option>
                        <option value="80" selected>80+</option>
                        <option value="90">90+</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Reputação</label>
                    <select class="form-select" id="filterReputation" onchange="loadRecommendations()">
                        <option value="">Qualquer</option>
                        <option value="5_green">5 Verde</option>
                        <option value="4_green">4+ Verde</option>
                        <option value="3_yellow">3+ Amarelo</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ordenar por</label>
                    <select class="form-select" id="filterSort" onchange="loadRecommendations()">
                        <option value="score_desc">Maior Score</option>
                        <option value="items_desc">Mais Itens</option>
                        <option value="sales_desc">Mais Vendas</option>
                        <option value="conversion_desc">Maior Conversão</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Buscar Vendedor</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="searchSeller" 
                               placeholder="Nome ou ID...">
                        <button class="btn btn-outline-primary" onclick="searchSeller()">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recommendations List -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Vendedores Recomendados</h5>
                    <span class="badge bg-primary" id="sellerCount">0</span>
                </div>
                <div class="card-body p-0">
                    <div id="sellersList">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Trending Categories -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">
                        <i class="bi bi-fire text-danger me-1"></i>
                        Categorias em Alta
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="trendingList">
                        <div class="list-group-item text-center text-muted">
                            Carregando...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">
                        <i class="bi bi-speedometer2 me-1"></i>
                        Estatísticas Rápidas
                    </h6>
                </div>
                <div class="card-body" id="quickStats">
                    <div class="text-center text-muted py-3">
                        Carregando...
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Seller Details Modal -->
<div class="modal fade" id="sellerModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sellerModalTitle">Detalhes do Vendedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="sellerModalBody">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="cloneItemsBtn" onclick="cloneSelectedItems()">
                    <i class="bi bi-copy me-1"></i> Clonar Selecionados
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
async function requestJson(url, options = {}) {
    if (window.ApiClient) {
        return window.ApiClient.request(url, options);
    }
    const resp = await fetch(url, { credentials: 'include', ...options });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}

function normalizeExternalUrl(url) {
    if (!url || typeof url !== 'string') return '';
    const trimmed = url.trim();
    if (!trimmed) return '';
    if (trimmed.startsWith('data:') || trimmed.startsWith('blob:') || trimmed.startsWith('#')) return trimmed;
    if (trimmed.startsWith('//')) return `${window.location.protocol}${trimmed}`;
    if (trimmed.startsWith('http://')) return `https://${trimmed.slice('http://'.length)}`;
    return trimmed;
}

let categories = [];
let currentSellerId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadCategories();
    loadRecommendations();
    loadTrends();
    loadStats();
});

async function loadCategories() {
    try {
        const result = await requestJson('/api/clone/recommendations/sellers/by-category');
        
        if (result.success && result.data) {
            categories = result.data;
            const select = document.getElementById('filterCategory');
            
            result.data.forEach(cat => {
                const option = document.createElement('option');
                option.value = cat.category_id;
                option.textContent = `${cat.category_name} (${cat.seller_count})`;
                select.appendChild(option);
            });
        }
    } catch (e) {
        console.error('Erro ao carregar categorias:', e);
    }
}

async function loadRecommendations() {
    const container = document.getElementById('sellersList');
    
    try {
        const category = document.getElementById('filterCategory').value;
        const minScore = document.getElementById('filterScore').value;
        const reputation = document.getElementById('filterReputation').value;
        const sort = document.getElementById('filterSort').value;
        
        let url = '/api/clone/recommendations/sellers?limit=50';
        if (category) url += `&category=${category}`;
        if (minScore) url += `&min_score=${minScore}`;
        if (reputation) url += `&reputation=${reputation}`;
        if (sort) url += `&sort=${sort}`;
        
        const result = await requestJson(url);
        
        document.getElementById('sellerCount').textContent = result.data?.length || 0;
        
        if (!result.success || !result.data?.length) {
            container.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Nenhuma recomendação encontrada
                </div>
            `;
            return;
        }
        
        container.innerHTML = result.data.map(seller => `
            <div class="seller-card p-3 border-bottom hover-bg" style="cursor: pointer;"
                 onclick="viewSellerDetails(${seller.seller_id})">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="position-relative">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
                                 style="width: 60px; height: 60px;">
                                <span class="h4 text-primary mb-0">${getScoreEmoji(seller.score)}</span>
                            </div>
                            <span class="position-absolute bottom-0 end-0 badge rounded-pill ${getReputationBadge(seller.reputation)}">
                                ${seller.reputation_level || '?'}
                            </span>
                        </div>
                    </div>
                    <div class="col">
                        <h6 class="mb-1">
                            ${escapeHtml(seller.nickname || 'Vendedor #' + seller.seller_id)}
                            ${seller.power_seller ? '<i class="bi bi-patch-check-fill text-primary ms-1" title="Power Seller"></i>' : ''}
                        </h6>
                        <div class="small text-muted">
                            <span class="me-3"><i class="bi bi-box me-1"></i>${formatNumber(seller.total_items || 0)} itens</span>
                            <span class="me-3"><i class="bi bi-cart me-1"></i>${formatNumber(seller.total_sales || 0)} vendas</span>
                            <span><i class="bi bi-percent me-1"></i>${formatPercent(seller.conversion_rate || 0)} conversão</span>
                        </div>
                        ${seller.top_categories ? `
                            <div class="mt-1">
                                ${seller.top_categories.slice(0, 3).map(c => 
                                    `<span class="badge bg-light text-dark me-1">${escapeHtml(c)}</span>`
                                ).join('')}
                            </div>
                        ` : ''}
                    </div>
                    <div class="col-auto text-end">
                        <div class="h4 mb-0 ${getScoreClass(seller.score)}">${seller.score}</div>
                        <small class="text-muted">Score</small>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-chevron-right text-muted"></i>
                    </div>
                </div>
            </div>
        `).join('');
        
    } catch (e) {
        console.error('Erro ao carregar recomendações:', e);
        container.innerHTML = `
            <div class="text-center py-5 text-danger">
                Erro ao carregar recomendações
            </div>
        `;
    }
}

async function loadTrends() {
    try {
        const result = await requestJson('/api/clone/recommendations/trends');
        
        const container = document.getElementById('trendingList');
        document.getElementById('trendingCategories').textContent = result.data?.length || 0;
        
        if (!result.success || !result.data?.length) {
            container.innerHTML = `
                <div class="list-group-item text-center text-muted">
                    Sem tendências
                </div>
            `;
            return;
        }
        
        container.innerHTML = result.data.slice(0, 5).map((trend, idx) => `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge ${idx < 3 ? 'bg-danger' : 'bg-secondary'} me-2">${idx + 1}</span>
                    ${escapeHtml(trend.category_name)}
                </div>
                <div class="text-end">
                    <span class="badge bg-success">${trend.growth >= 0 ? '+' : ''}${trend.growth}%</span>
                    <br>
                    <small class="text-muted">${formatNumber(trend.seller_count)} vendedores</small>
                </div>
            </div>
        `).join('');
        
    } catch (e) {
        console.error('Erro ao carregar tendências:', e);
    }
}

async function loadStats() {
    try {
        const result = await requestJson('/api/clone/recommendations/stats');
        
        if (!result.success) return;
        
        const stats = result.data;
        
        document.getElementById('totalRecommended').textContent = formatNumber(stats.total_sellers || 0);
        document.getElementById('avgScore').textContent = stats.avg_score ? stats.avg_score.toFixed(1) : '-';
        document.getElementById('totalItems').textContent = formatNumber(stats.total_items || 0);
        
        document.getElementById('quickStats').innerHTML = `
            <div class="mb-3">
                <div class="d-flex justify-content-between small mb-1">
                    <span>Score 90+</span>
                    <span class="text-success">${formatNumber(stats.high_score_count || 0)}</span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-success" style="width: ${(stats.high_score_count / stats.total_sellers * 100) || 0}%"></div>
                </div>
            </div>
            <div class="mb-3">
                <div class="d-flex justify-content-between small mb-1">
                    <span>Power Sellers</span>
                    <span class="text-primary">${formatNumber(stats.power_seller_count || 0)}</span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar" style="width: ${(stats.power_seller_count / stats.total_sellers * 100) || 0}%"></div>
                </div>
            </div>
            <div class="mb-3">
                <div class="d-flex justify-content-between small mb-1">
                    <span>Mercado Líder</span>
                    <span class="text-info">${formatNumber(stats.mercado_lider_count || 0)}</span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-info" style="width: ${(stats.mercado_lider_count / stats.total_sellers * 100) || 0}%"></div>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <small class="text-muted">Atualizado: ${formatDate(stats.last_update)}</small>
            </div>
        `;
        
    } catch (e) {
        console.error('Erro ao carregar stats:', e);
    }
}

async function viewSellerDetails(sellerId) {
    currentSellerId = sellerId;
    
    try {
        const [sellerData, itemsData] = await Promise.all([
            requestJson(`/api/clone/recommendations/sellers/${sellerId}/similar`),
            requestJson(`/api/sellers/${sellerId}/items?limit=50`)
        ]);
        
        document.getElementById('sellerModalTitle').textContent = 
            sellerData.data?.nickname || `Vendedor #${sellerId}`;
        
        document.getElementById('sellerModalBody').innerHTML = `
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6>Informações do Vendedor</h6>
                    <table class="table table-sm">
                        <tr>
                            <td class="text-muted">ID:</td>
                            <td>${sellerId}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Nickname:</td>
                            <td>${escapeHtml(sellerData.data?.nickname || '-')}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Reputação:</td>
                            <td>
                                <span class="badge ${getReputationBadge(sellerData.data?.reputation)}">
                                    ${sellerData.data?.reputation_level || '-'}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Total Vendas:</td>
                            <td>${formatNumber(sellerData.data?.total_sales || 0)}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Score:</td>
                            <td class="${getScoreClass(sellerData.data?.score)}">${sellerData.data?.score || '-'}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Vendedores Similares</h6>
                    <div class="list-group list-group-flush">
                        ${sellerData.data?.similar?.slice(0, 5).map(s => `
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>${escapeHtml(s.nickname)}</span>
                                <span class="badge bg-secondary">${s.score}</span>
                            </div>
                        `).join('') || '<div class="text-muted">Nenhum similar encontrado</div>'}
                    </div>
                </div>
            </div>
            
            <h6>Itens do Vendedor <span class="badge bg-secondary">${itemsData.data?.length || 0}</span></h6>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-hover">
                    <thead class="sticky-top bg-white">
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" class="form-check-input" id="selectAll" 
                                       onchange="toggleSelectAll(this)">
                            </th>
                            <th>Item</th>
                            <th>Preço</th>
                            <th>Vendas</th>
                            <th>Estoque</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsData.data?.map(item => `
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input item-checkbox" 
                                           value="${item.id}" data-title="${escapeHtml(item.title)}">
                                </td>
                                <td>
                                    <a href="${normalizeExternalUrl(item.permalink) || '#'}" target="_blank" class="text-decoration-none">
                                        ${escapeHtml((item.title || '').substring(0, 60))}...
                                    </a>
                                    <br>
                                    <small class="text-muted">${item.id}</small>
                                </td>
                                <td>${formatCurrency(item.price || 0)}</td>
                                <td>${formatNumber(item.sold_quantity || 0)}</td>
                                <td>${formatNumber(item.available_quantity || 0)}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="5" class="text-center text-muted">Nenhum item encontrado</td></tr>'}
                    </tbody>
                </table>
            </div>
        `;
        
        new bootstrap.Modal(document.getElementById('sellerModal')).show();
        
    } catch (e) {
        console.error('Erro ao carregar detalhes:', e);
        showToast('Erro ao carregar detalhes do vendedor', 'danger');
    }
}

function toggleSelectAll(checkbox) {
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

async function cloneSelectedItems() {
    const selected = Array.from(document.querySelectorAll('.item-checkbox:checked'))
        .map(cb => cb.value);
    
    if (!selected.length) {
        showToast('Selecione pelo menos um item', 'warning');
        return;
    }
    
    if (!confirm(`Clonar ${selected.length} item(s)?`)) return;
    
    try {
        const result = await requestJson('/api/catalog/clone/batch', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                items: selected,
                source_seller_id: currentSellerId
            })
        });
        
        if (result.success) {
            showToast(`Job de clonagem criado! ID: ${result.job_id}`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('sellerModal')).hide();
        } else {
            showToast(result.error || 'Erro ao clonar', 'danger');
        }
    } catch (e) {
        showToast('Erro ao iniciar clonagem', 'danger');
    }
}

async function refreshRecommendations() {
    showToast('Atualizando recomendações...', 'info');
    
    try {
        const result = await requestJson('/api/clone/recommendations/refresh', { method: 'POST' });
        
        if (result.success) {
            loadRecommendations();
            loadTrends();
            loadStats();
            showToast('Recomendações atualizadas!', 'success');
        } else {
            showToast(result.error || 'Erro ao atualizar', 'danger');
        }
    } catch (e) {
        showToast('Erro ao atualizar recomendações', 'danger');
    }
}

async function searchSeller() {
    const query = document.getElementById('searchSeller').value.trim();
    if (!query) {
        loadRecommendations();
        return;
    }
    
    const container = document.getElementById('sellersList');
    container.innerHTML = '<div class="text-center py-5"><div class="spinner-border"></div></div>';
    
    try {
        const result = await requestJson(`/api/clone/recommendations/sellers?search=${encodeURIComponent(query)}`);
        
        if (result.success && result.data?.length) {
            // Reuse the loadRecommendations display logic
            document.getElementById('sellerCount').textContent = result.data.length;
            container.innerHTML = result.data.map(seller => `
                <div class="seller-card p-3 border-bottom hover-bg" style="cursor: pointer;"
                     onclick="viewSellerDetails(${seller.seller_id})">
                    <div class="row align-items-center">
                        <div class="col">
                            <h6 class="mb-1">${escapeHtml(seller.nickname || 'Vendedor #' + seller.seller_id)}</h6>
                            <small class="text-muted">Score: ${seller.score} | ${formatNumber(seller.total_items)} itens</small>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-chevron-right"></i>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<div class="text-center py-5 text-muted">Nenhum vendedor encontrado</div>';
        }
    } catch (e) {
        container.innerHTML = '<div class="text-center py-5 text-danger">Erro na busca</div>';
    }
}

function getScoreEmoji(score) {
    if (score >= 90) return '🏆';
    if (score >= 80) return '⭐';
    if (score >= 70) return '👍';
    return '📊';
}

function getScoreClass(score) {
    if (score >= 90) return 'text-success';
    if (score >= 80) return 'text-primary';
    if (score >= 70) return 'text-warning';
    return 'text-muted';
}

function getReputationBadge(reputation) {
    if (reputation === '5_green' || reputation === 'green') return 'bg-success';
    if (reputation === '4_green' || reputation === 'light_green') return 'bg-info';
    if (reputation === 'yellow') return 'bg-warning';
    if (reputation === 'orange' || reputation === 'red') return 'bg-danger';
    return 'bg-secondary';
}

function formatNumber(num) {
    return new Intl.NumberFormat('pt-BR').format(num || 0);
}

function formatPercent(value) {
    return (value || 0).toFixed(1) + '%';
}

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('pt-BR', {
        day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

function showToast(message, type = 'info') {
    const container = document.querySelector('.toast-container') || (() => {
        const c = document.createElement('div');
        c.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(c);
        return c;
    })();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    container.appendChild(toast);
    new bootstrap.Toast(toast).show();
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}
</script>

<style>
.hover-bg:hover {
    background-color: #f8f9fa;
}
</style>
