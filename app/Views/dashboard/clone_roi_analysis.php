<?php

declare(strict_types=1);

/**
 * Clone ROI Analysis Dashboard
 * Interface para análise de ROI das clonagens
 */
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1">
                    <i class="bi bi-graph-up-arrow text-success me-2"></i>
                    Análise de ROI
                </h1>
                <p class="text-muted mb-0">Compare o desempenho original vs clonado</p>
            </div>
            <div class="btn-group">
                <button class="btn btn-outline-primary" onclick="syncMetrics()">
                    <i class="bi bi-arrow-repeat me-1"></i> Sincronizar Métricas
                </button>
                <button class="btn btn-outline-secondary" onclick="exportAnalysis()">
                    <i class="bi bi-download me-1"></i> Exportar
                </button>
            </div>
        </div>
    </div>

    <!-- Period Selector -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Período</label>
                    <select class="form-select" id="filterPeriod" onchange="loadAnalysis()">
                        <option value="7">Últimos 7 dias</option>
                        <option value="30" selected>Últimos 30 dias</option>
                        <option value="60">Últimos 60 dias</option>
                        <option value="90">Últimos 90 dias</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Categoria</label>
                    <select class="form-select" id="filterCategory" onchange="loadAnalysis()">
                        <option value="">Todas as categorias</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ordenar por</label>
                    <select class="form-select" id="filterSort" onchange="loadAnalysis()">
                        <option value="roi_desc">Maior ROI</option>
                        <option value="roi_asc">Menor ROI</option>
                        <option value="revenue_desc">Maior Receita</option>
                        <option value="improvement_desc">Maior Melhoria</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Exibir</label>
                    <select class="form-select" id="filterLimit" onchange="loadAnalysis()">
                        <option value="25">25 itens</option>
                        <option value="50" selected>50 itens</option>
                        <option value="100">100 itens</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 bg-success bg-opacity-10">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">ROI Médio</h6>
                            <h2 class="mb-0 text-success" id="avgRoi">-</h2>
                        </div>
                        <div class="bg-success bg-opacity-25 rounded-3 p-3 align-self-center">
                            <i class="bi bi-percent text-success fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted" id="avgRoiTrend"></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Receita Total Clones</h6>
                            <h2 class="mb-0" id="totalRevenue">-</h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-3 p-3 align-self-center">
                            <i class="bi bi-currency-dollar text-primary fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted" id="revenueTrend"></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Itens com ROI Positivo</h6>
                            <h2 class="mb-0" id="positiveRoi">-</h2>
                        </div>
                        <div class="bg-info bg-opacity-10 rounded-3 p-3 align-self-center">
                            <i class="bi bi-arrow-up-circle text-info fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted" id="positiveRoiPercent"></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Melhoria Média Vendas</h6>
                            <h2 class="mb-0" id="avgImprovement">-</h2>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded-3 p-3 align-self-center">
                            <i class="bi bi-graph-up text-warning fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted" id="improvementTrend"></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">Evolução de ROI no Período</h5>
                </div>
                <div class="card-body">
                    <canvas id="roiTimelineChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">Distribuição de ROI</h5>
                </div>
                <div class="card-body">
                    <canvas id="roiDistributionChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparison Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Comparativo Original vs Clone</h5>
            <span class="badge bg-secondary" id="itemCount">0 itens</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Item</th>
                            <th class="text-center" colspan="3">Original</th>
                            <th class="text-center" colspan="3">Clone</th>
                            <th class="text-center">ROI</th>
                            <th>Ações</th>
                        </tr>
                        <tr class="small text-muted">
                            <th></th>
                            <th class="text-center">Visitas</th>
                            <th class="text-center">Vendas</th>
                            <th class="text-center">Receita</th>
                            <th class="text-center">Visitas</th>
                            <th class="text-center">Vendas</th>
                            <th class="text-center">Receita</th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="comparisonTable">
                        <tr>
                            <td colspan="9" class="text-center py-4">
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

<!-- Item Details Modal -->
<div class="modal fade" id="itemDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="itemDetailsBody">
                <!-- Dynamic content -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script nonce="<?= CSP_NONCE ?>">

function normalizeExternalUrl(url) {
    if (!url || typeof url !== 'string') return '';
    const trimmed = url.trim();
    if (!trimmed) return '';
    if (/^(data:|blob:)/i.test(trimmed)) return trimmed;
    if (trimmed.startsWith('//')) return window.location.protocol + trimmed;
    if (/^http:\/\//i.test(trimmed)) return trimmed.replace(/^http:\/\//i, 'https://');
    return trimmed;
}

let roiTimelineChart = null;
let roiDistributionChart = null;

document.addEventListener('DOMContentLoaded', function() {
    initCharts();
    loadAnalysis();
});

function initCharts() {
    // Timeline Chart
    const timelineCtx = document.getElementById('roiTimelineChart').getContext('2d');
    roiTimelineChart = new Chart(timelineCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'ROI (%)',
                    data: [],
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Receita Clone (R$)',
                    data: [],
                    borderColor: '#0d6efd',
                    backgroundColor: 'transparent',
                    yAxisID: 'revenue',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'ROI (%)' }
                },
                revenue: {
                    position: 'right',
                    beginAtZero: true,
                    title: { display: true, text: 'Receita (R$)' },
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
    
    // Distribution Chart
    const distCtx = document.getElementById('roiDistributionChart').getContext('2d');
    roiDistributionChart = new Chart(distCtx, {
        type: 'doughnut',
        data: {
            labels: ['ROI > 100%', 'ROI 50-100%', 'ROI 0-50%', 'ROI Negativo'],
            datasets: [{
                data: [0, 0, 0, 0],
                backgroundColor: ['#198754', '#20c997', '#ffc107', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}

async function loadAnalysis() {
    try {
        const period = document.getElementById('filterPeriod').value;
        const category = document.getElementById('filterCategory').value;
        const sort = document.getElementById('filterSort').value;
        const limit = document.getElementById('filterLimit').value;
        
        // Load main analysis
        let url = `/api/clone/roi/analysis?period=${period}&limit=${limit}`;
        if (category) url += `&category=${category}`;
        if (sort) url += `&sort=${sort}`;
        
        const result = await requestJson(url);
        
        if (result.success) {
            updateSummary(result.data.summary);
            updateCharts(result.data);
            updateTable(result.data.items);
        }
        
        // Load timeline
        const timelineData = await requestJson(`/api/clone/roi/timeline?period=${period}`);
        
        if (timelineData.success) {
            updateTimelineChart(timelineData.data);
        }
    } catch (e) {
        console.error('Erro ao carregar análise:', e);
    }
}

function updateSummary(summary) {
    if (!summary) return;
    
    document.getElementById('avgRoi').textContent = 
        formatPercent(summary.avg_roi || 0);
    document.getElementById('totalRevenue').textContent = 
        formatCurrency(summary.total_clone_revenue || 0);
    document.getElementById('positiveRoi').textContent = 
        formatNumber(summary.positive_roi_count || 0);
    document.getElementById('avgImprovement').textContent = 
        formatPercent(summary.avg_sales_improvement || 0);
    
    document.getElementById('positiveRoiPercent').textContent = 
        `${Math.round((summary.positive_roi_count / Math.max(summary.total_items, 1)) * 100)}% do total`;
    
    // Trends
    if (summary.roi_trend) {
        const trend = summary.roi_trend > 0 ? '↑' : summary.roi_trend < 0 ? '↓' : '→';
        document.getElementById('avgRoiTrend').innerHTML = 
            `<span class="${summary.roi_trend > 0 ? 'text-success' : 'text-danger'}">${trend} ${Math.abs(summary.roi_trend)}% vs período anterior</span>`;
    }
}

function updateCharts(data) {
    // Distribution chart
    if (data.distribution) {
        roiDistributionChart.data.datasets[0].data = [
            data.distribution.excellent || 0,
            data.distribution.good || 0,
            data.distribution.moderate || 0,
            data.distribution.negative || 0
        ];
        roiDistributionChart.update();
    }
}

function updateTimelineChart(timeline) {
    if (!timeline || !timeline.length) return;
    
    roiTimelineChart.data.labels = timeline.map(t => formatDate(t.date));
    roiTimelineChart.data.datasets[0].data = timeline.map(t => t.avg_roi || 0);
    roiTimelineChart.data.datasets[1].data = timeline.map(t => t.total_revenue || 0);
    roiTimelineChart.update();
}

function updateTable(items) {
    const tbody = document.getElementById('comparisonTable');
    document.getElementById('itemCount').textContent = `${items?.length || 0} itens`;
    
    if (!items || !items.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Nenhum dado de ROI disponível
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = items.map(item => {
        const roiClass = item.roi >= 50 ? 'text-success' : 
                         item.roi >= 0 ? 'text-warning' : 'text-danger';
        const roiBg = item.roi >= 50 ? 'bg-success' : 
                      item.roi >= 0 ? 'bg-warning' : 'bg-danger';
        
        return `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        ${item.thumbnail ? `
                            <img src="${normalizeExternalUrl(item.thumbnail) || ''}" class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">
                        ` : ''}
                        <div>
                            <a href="${normalizeExternalUrl(item.clone_permalink) || '#'}" target="_blank" 
                               class="text-decoration-none fw-medium">
                                ${escapeHtml((item.title || '').substring(0, 50))}...
                            </a>
                            <br>
                            <small class="text-muted">${item.clone_id || '-'}</small>
                        </div>
                    </div>
                </td>
                <td class="text-center">${formatNumber(item.original_visits || 0)}</td>
                <td class="text-center">${formatNumber(item.original_sales || 0)}</td>
                <td class="text-center">${formatCurrency(item.original_revenue || 0)}</td>
                <td class="text-center">${formatNumber(item.clone_visits || 0)}</td>
                <td class="text-center">${formatNumber(item.clone_sales || 0)}</td>
                <td class="text-center">${formatCurrency(item.clone_revenue || 0)}</td>
                <td class="text-center">
                    <span class="badge ${roiBg} bg-opacity-25 ${roiClass} fs-6">
                        ${item.roi >= 0 ? '+' : ''}${formatPercent(item.roi || 0)}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewItemDetails('${item.clone_id}')">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

async function viewItemDetails(itemId) {
    try {
        const result = await requestJson(`/api/clone/roi/items/${itemId}`);
        
        if (!result.success) {
            showToast('Item não encontrado', 'danger');
            return;
        }
        
        const item = result.data;
        
        document.getElementById('itemDetailsBody').innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    ${item.thumbnail ? `
                        <img src="${normalizeExternalUrl(item.thumbnail) || ''}" class="img-fluid rounded mb-3">
                    ` : ''}
                    <h6>${escapeHtml(item.title || '')}</h6>
                    <p class="text-muted small">${item.clone_id}</p>
                </div>
                <div class="col-md-8">
                    <h6>Comparativo de Performance</h6>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Métrica</th>
                                <th class="text-center">Original</th>
                                <th class="text-center">Clone</th>
                                <th class="text-center">Diferença</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Visitas</td>
                                <td class="text-center">${formatNumber(item.original_visits || 0)}</td>
                                <td class="text-center">${formatNumber(item.clone_visits || 0)}</td>
                                <td class="text-center ${getDiffClass(item.clone_visits, item.original_visits)}">
                                    ${formatDiff(item.clone_visits, item.original_visits)}
                                </td>
                            </tr>
                            <tr>
                                <td>Vendas</td>
                                <td class="text-center">${formatNumber(item.original_sales || 0)}</td>
                                <td class="text-center">${formatNumber(item.clone_sales || 0)}</td>
                                <td class="text-center ${getDiffClass(item.clone_sales, item.original_sales)}">
                                    ${formatDiff(item.clone_sales, item.original_sales)}
                                </td>
                            </tr>
                            <tr>
                                <td>Receita</td>
                                <td class="text-center">${formatCurrency(item.original_revenue || 0)}</td>
                                <td class="text-center">${formatCurrency(item.clone_revenue || 0)}</td>
                                <td class="text-center ${getDiffClass(item.clone_revenue, item.original_revenue)}">
                                    ${formatDiff(item.clone_revenue, item.original_revenue)}
                                </td>
                            </tr>
                            <tr>
                                <td>Taxa Conversão</td>
                                <td class="text-center">${formatPercent(item.original_conversion || 0)}</td>
                                <td class="text-center">${formatPercent(item.clone_conversion || 0)}</td>
                                <td class="text-center ${getDiffClass(item.clone_conversion, item.original_conversion)}">
                                    ${formatDiff(item.clone_conversion, item.original_conversion)}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="alert ${item.roi >= 0 ? 'alert-success' : 'alert-danger'} mt-3">
                        <strong>ROI do Clone:</strong> ${item.roi >= 0 ? '+' : ''}${formatPercent(item.roi || 0)}
                    </div>
                </div>
            </div>
        `;
        
        new bootstrap.Modal(document.getElementById('itemDetailsModal')).show();
    } catch (e) {
        showToast('Erro ao carregar detalhes', 'danger');
    }
}

async function syncMetrics() {
    showToast('Sincronizando métricas...', 'info');
    
    try {
        const result = await requestJson('/api/clone/roi/sync', { method: 'POST' });
        
        if (result.success) {
            showToast(`${result.synced || 0} itens sincronizados!`, 'success');
            loadAnalysis();
        } else {
            showToast(result.error || 'Erro na sincronização', 'danger');
        }
    } catch (e) {
        showToast('Erro ao sincronizar', 'danger');
    }
}

async function exportAnalysis() {
    const period = document.getElementById('filterPeriod').value;
    window.location.href = `/api/clone/roi/analysis?period=${period}&format=csv`;
}

function formatPercent(value) {
    return new Intl.NumberFormat('pt-BR', { 
        minimumFractionDigits: 1, 
        maximumFractionDigits: 1 
    }).format(value) + '%';
}

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', { 
        style: 'currency', 
        currency: 'BRL' 
    }).format(value);
}

function formatNumber(value) {
    return new Intl.NumberFormat('pt-BR').format(value);
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('pt-BR', { 
        day: '2-digit', 
        month: 'short' 
    });
}

function getDiffClass(newVal, oldVal) {
    const diff = (newVal || 0) - (oldVal || 0);
    return diff > 0 ? 'text-success' : diff < 0 ? 'text-danger' : '';
}

function formatDiff(newVal, oldVal) {
    const diff = (newVal || 0) - (oldVal || 0);
    const percent = oldVal > 0 ? ((diff / oldVal) * 100).toFixed(1) : 0;
    const arrow = diff > 0 ? '↑' : diff < 0 ? '↓' : '→';
    return `${arrow} ${Math.abs(percent)}%`;
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
