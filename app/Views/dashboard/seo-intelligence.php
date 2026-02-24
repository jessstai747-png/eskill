<?php
/**
 * @deprecated Use /dashboard/seo-killer instead. This view is no longer routed directly.
 */
$pageTitle = '🎯 SEO Intelligence';
$activePage = 'seo-intelligence';

// Account Selector
$requireAccountSelection = true;
$showAccountBanner = true;
include __DIR__ . '/../components/account-selector.php';

// Page Header
$title = '🎯 SEO Intelligence';
$subtitle = 'Auditoria Avançada e Análise Competitiva';
include __DIR__ . '/../layouts/modern/partials/page-header.php';
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- Toastify -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<style>
.score-badge {
    font-size: 2rem;
    font-weight: bold;
    padding: 1rem;
    border-radius: 50%;
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.score-excellent { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
.score-good { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
.score-fair { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #333; }
.score-poor { background: linear-gradient(135deg, #ff6b6b 0%, #c92a2a 100%); color: white; }

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.opportunity-card {
    background: white;
    border-left: 4px solid #ffc107;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.opportunity-card:hover {
    background: #f8f9fa;
    border-left-color: #ff9800;
}

.priority-high { border-left-color: #dc3545; }
.priority-medium { border-left-color: #ffc107; }
.priority-low { border-left-color: #28a745; }

.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(0,0,0,.1);
    border-radius: 50%;
    border-top-color: #007bff;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<div class="container-fluid">
    
    <!-- Dashboard Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="text-muted mb-2">Anúncios Auditados</div>
                <h2 id="total-audited" class="mb-0">-</h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="text-muted mb-2">Score Médio</div>
                <h2 id="avg-score" class="mb-0">-</h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="text-muted mb-2">Excelentes (80+)</div>
                <h2 id="excellent-count" class="mb-0 text-success">-</h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="text-muted mb-2">Necessitam Atenção</div>
                <h2 id="poor-count" class="mb-0 text-danger">-</h2>
            </div>
        </div>
    </div>

    <!-- Score Distribution Chart -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">📊 Distribuição de Scores</h5>
                </div>
                <div class="card-body">
                    <canvas id="scoreDistributionChart" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">🎯 Top Oportunidades</h5>
                    <button class="btn btn-sm btn-primary" onclick="refreshOpportunities()">
                        <i class="bi bi-arrow-clockwise"></i> Atualizar
                    </button>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <div id="opportunities-list">
                        <div class="text-center text-muted">
                            <div class="loading-spinner"></div>
                            <p class="mt-2">Carregando...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Listings Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">📋 Todos os Anúncios</h5>
            <div>
                <button class="btn btn-sm btn-success" onclick="auditAllListings()">
                    <i class="bi bi-lightning-charge"></i> Auditar Todos
                </button>
                <button class="btn btn-sm btn-primary" onclick="loadListings()">
                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">Score Mínimo</label>
                    <input type="number" class="form-control" id="filter-min-score" value="0" min="0" max="100">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Score Máximo</label>
                    <input type="number" class="form-control" id="filter-max-score" value="100" min="0" max="100">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filter-status">
                        <option value="">Todos</option>
                        <option value="active">Ativos</option>
                        <option value="paused">Pausados</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-primary w-100" onclick="applyFilters()">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Score</th>
                            <th>Título</th>
                            <th>Preço</th>
                            <th>Status</th>
                            <th>Última Auditoria</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="listings-table-body">
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                <div class="loading-spinner"></div>
                                <p class="mt-2">Carregando anúncios...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div id="pagination" class="d-flex justify-content-center mt-3"></div>
        </div>
    </div>

</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

let currentPage = 0;
let currentFilters = {};
let scoreChart = null;

// Load dashboard on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDashboard();
    loadListings();
});

async function loadDashboard() {
    try {
        const data = await requestJson('/api/seo/intelligence/dashboard');
        
        if (data.success) {
            const stats = data.data.statistics;
            
            // Update stats
            document.getElementById('total-audited').textContent = stats.total_audited || 0;
            document.getElementById('avg-score').textContent = stats.avg_score ? Math.round(stats.avg_score) : '-';
            document.getElementById('excellent-count').textContent = stats.excellent_count || 0;
            document.getElementById('poor-count').textContent = (stats.fair_count || 0) + (stats.poor_count || 0);
            
            // Update chart
            updateScoreChart(stats);
            
            // Update opportunities
            updateOpportunities(data.data.top_opportunities || []);
        }
    } catch (error) {
        console.error('Erro ao carregar dashboard:', error);
        showToast('Erro ao carregar dashboard', 'error');
    }
}

function updateScoreChart(stats) {
    const ctx = document.getElementById('scoreDistributionChart');
    
    if (scoreChart) {
        scoreChart.destroy();
    }
    
    scoreChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Excelente (80-100)', 'Bom (60-79)', 'Regular (40-59)', 'Ruim (0-39)'],
            datasets: [{
                data: [
                    stats.excellent_count || 0,
                    stats.good_count || 0,
                    stats.fair_count || 0,
                    stats.poor_count || 0
                ],
                backgroundColor: [
                    '#28a745',
                    '#17a2b8',
                    '#ffc107',
                    '#dc3545'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

function updateOpportunities(opportunities) {
    const container = document.getElementById('opportunities-list');
    
    if (opportunities.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">Nenhuma oportunidade encontrada</p>';
        return;
    }
    
    container.innerHTML = opportunities.map(opp => {
        const recommendations = JSON.parse(opp.recommendations || '[]');
        const topRec = recommendations[0] || {};
        const priorityClass = topRec.priority === 'high' ? 'priority-high' : 
                             topRec.priority === 'medium' ? 'priority-medium' : 'priority-low';
        
        return `
            <div class="opportunity-card ${priorityClass}" onclick="viewListingDetail('${opp.item_id}')">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${opp.title}</h6>
                        <small class="text-muted">${topRec.message || 'Necessita otimização'}</small>
                    </div>
                    <span class="badge bg-danger">${opp.overall_score}</span>
                </div>
            </div>
        `;
    }).join('');
}

async function loadListings() {
    try {
        const params = new URLSearchParams({
            limit: 50,
            offset: currentPage * 50,
            ...currentFilters
        });
        
        const data = await requestJson(`/api/seo/intelligence/listings?${params}`);
        
        if (data.success) {
            updateListingsTable(data.data.listings);
            updatePagination(data.data.total, data.data.limit, data.data.offset);
        }
    } catch (error) {
        console.error('Erro ao carregar anúncios:', error);
        showToast('Erro ao carregar anúncios', 'error');
    }
}

function updateListingsTable(listings) {
    const tbody = document.getElementById('listings-table-body');
    
    if (listings.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Nenhum anúncio encontrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = listings.map(listing => {
        const score = listing.overall_score || 0;
        const scoreClass = score >= 80 ? 'success' : score >= 60 ? 'info' : score >= 40 ? 'warning' : 'danger';
        const statusBadge = listing.status === 'active' ? 'success' : 'secondary';
        
        return `
            <tr>
                <td>
                    <span class="badge bg-${scoreClass}" style="font-size: 1.1rem; padding: 0.5rem;">
                        ${score || '-'}
                    </span>
                </td>
                <td>${listing.title}</td>
                <td>R$ ${parseFloat(listing.price || 0).toFixed(2)}</td>
                <td><span class="badge bg-${statusBadge}">${listing.status || 'N/A'}</span></td>
                <td>${listing.audit_date ? new Date(listing.audit_date).toLocaleDateString('pt-BR') : 'Nunca'}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="viewListingDetail('${listing.item_id}')">
                        <i class="bi bi-eye"></i> Ver
                    </button>
                    <button class="btn btn-sm btn-success" onclick="auditListing('${listing.item_id}')">
                        <i class="bi bi-lightning-charge"></i> Auditar
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function updatePagination(total, limit, offset) {
    const totalPages = Math.ceil(total / limit);
    const currentPage = Math.floor(offset / limit);
    const pagination = document.getElementById('pagination');
    
    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }
    
    let html = '<nav><ul class="pagination">';
    
    // Previous
    html += `<li class="page-item ${currentPage === 0 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;">Anterior</a>
    </li>`;
    
    // Pages
    for (let i = 0; i < Math.min(totalPages, 5); i++) {
        const page = i;
        html += `<li class="page-item ${page === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" onclick="changePage(${page}); return false;">${page + 1}</a>
        </li>`;
    }
    
    // Next
    html += `<li class="page-item ${currentPage >= totalPages - 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="changePage(${currentPage + 1}); return false;">Próximo</a>
    </li>`;
    
    html += '</ul></nav>';
    pagination.innerHTML = html;
}

function changePage(page) {
    currentPage = page;
    loadListings();
}

function applyFilters() {
    currentFilters = {
        min_score: document.getElementById('filter-min-score').value,
        max_score: document.getElementById('filter-max-score').value,
        status: document.getElementById('filter-status').value
    };
    currentPage = 0;
    loadListings();
}

async function auditListing(itemId) {
    try {
        showToast('Auditando anúncio...', 'info');
        
        const data = await requestJson(`/api/seo/intelligence/audit/${itemId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ force_refresh: true })
        });
        
        if (data.success) {
            showToast('Auditoria concluída!', 'success');
            loadDashboard();
            loadListings();
        } else {
            showToast('Erro na auditoria: ' + (data.error || 'Erro desconhecido'), 'error');
        }
    } catch (error) {
        console.error('Erro ao auditar:', error);
        showToast('Erro ao auditar anúncio', 'error');
    }
}

async function auditAllListings() {
    if (!confirm('Deseja auditar todos os anúncios? Isso pode levar alguns minutos.')) {
        return;
    }
    
    showToast('Iniciando auditoria em lote...', 'info');
    
    try {
        // Get all visible item IDs from the current table
        const itemRows = document.querySelectorAll('.listing-row[data-item-id]');
        const itemIds = Array.from(itemRows).map(row => row.dataset.itemId).filter(Boolean);
        
        if (itemIds.length === 0) {
            showToast('Nenhum anúncio encontrado para auditar', 'warning');
            return;
        }
        
        // Start batch audit via API
        const result = await requestJson('/api/seo/intelligence/audit/batch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                item_ids: itemIds,
                options: {
                    deep_analysis: true,
                    include_suggestions: true
                }
            })
        });
        
        if (result.success) {
            showToast(`Auditoria iniciada para ${result.total_items} anúncios. Job ID: ${result.job_id}`, 'success');
            
            // Poll for job status if job_id returned
            if (result.job_id) {
                pollAuditJobStatus(result.job_id);
            } else {
                // Direct results available
                showToast(`Auditados: ${result.audited || 0} | Problemas: ${result.issues_found || 0}`, 'info');
                setTimeout(() => loadDashboard(), 2000);
            }
        } else {
            showToast('Erro na auditoria: ' + (result.error || 'Erro desconhecido'), 'error');
        }
    } catch (error) {
        console.error('Batch audit error:', error);
        showToast('Erro ao processar auditoria em lote', 'error');
    }
}

async function pollAuditJobStatus(jobId) {
    let attempts = 0;
    const maxAttempts = 30; // 5 minutes max (10s interval)
    
    const poll = async () => {
        try {
            const status = await requestJson(`/api/seo/intelligence/audit/status/${jobId}`);
            
            if (status.status === 'completed') {
                showToast(`Auditoria concluída! ${status.audited} itens processados.`, 'success');
                loadDashboard();
                return;
            } else if (status.status === 'failed') {
                showToast('Auditoria falhou: ' + (status.error || 'Erro desconhecido'), 'error');
                return;
            }
            
            attempts++;
            if (attempts < maxAttempts) {
                showToast(`Processando... ${status.progress || 0}%`, 'info');
                setTimeout(poll, 10000); // Poll every 10 seconds
            } else {
                showToast('Tempo limite excedido. Verifique o status manualmente.', 'warning');
            }
        } catch (error) {
            console.error('Poll error:', error);
        }
    };
    
    setTimeout(poll, 5000); // Start polling after 5 seconds
}

function viewListingDetail(itemId) {
    window.location.href = `/dashboard/seo-intelligence/listing?item_id=${itemId}`;
}

function refreshOpportunities() {
    loadDashboard();
}

function showToast(message, type = 'info') {
    const bgColors = {
        success: 'linear-gradient(to right, #00b09b, #96c93d)',
        error: 'linear-gradient(to right, #ff5f6d, #ffc371)',
        warning: 'linear-gradient(to right, #f2994a, #f2c94c)',
        info: 'linear-gradient(to right, #4facfe, #00f2fe)'
    };
    
    Toastify({
        text: message,
        duration: 3000,
        gravity: 'top',
        position: 'right',
        style: {
            background: bgColors[type] || bgColors.info
        }
    }).showToast();
}
</script>
