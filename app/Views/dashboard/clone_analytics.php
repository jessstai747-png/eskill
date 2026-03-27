<?php

declare(strict_types=1);

/**
 * Clone Analytics Dashboard View
 * 
 * Dashboard analítico com métricas avançadas e gráficos Chart.js
 */

$pageTitle = 'Analytics de Clonagem';
$extraCss = [];
$extraJs = [];

ob_start();
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-graph-up text-primary"></i>
                        Analytics de Clonagem
                    </h1>
                    <p class="text-muted mb-0">Métricas avançadas e análise de tendências</p>
                </div>
                <div class="d-flex gap-2">
                    <select class="form-select" id="periodSelector" style="width: auto;">
                        <option value="7d">Últimos 7 dias</option>
                        <option value="30d" selected>Últimos 30 dias</option>
                        <option value="90d">Últimos 90 dias</option>
                        <option value="1y">Último ano</option>
                    </select>
                    <button type="button" class="btn btn-outline-primary" onclick="refreshData()">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row mb-4" id="kpiCards">
        <div class="col-md-3 col-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">Total de Jobs</h6>
                            <h2 class="mb-0" id="kpiTotalJobs">-</h2>
                            <small id="kpiJobsGrowth" class="text-success">-</small>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-collection text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">Itens Clonados</h6>
                            <h2 class="mb-0" id="kpiTotalItems">-</h2>
                            <small class="text-muted" id="kpiSuccessRate">-</small>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-files text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">Tempo Médio</h6>
                            <h2 class="mb-0" id="kpiAvgTime">-</h2>
                            <small class="text-muted">por item</small>
                        </div>
                        <div class="bg-info bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-clock text-info fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">Falhas</h6>
                            <h2 class="mb-0 text-danger" id="kpiFailedItems">-</h2>
                            <small class="text-muted" id="kpiFailRate">-</small>
                        </div>
                        <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-x-circle text-danger fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row mb-4">
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up me-2"></i>
                        Tendência de Clonagens
                    </h5>
                    <div class="btn-group btn-group-sm" id="trendToggle">
                        <button type="button" class="btn btn-outline-primary active" data-view="daily">Diário</button>
                        <button type="button" class="btn btn-outline-primary" data-view="weekly">Semanal</button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="trendChart" height="280"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-pie-chart me-2"></i>
                        Status dos Jobs
                    </h5>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="statusChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history me-2"></i>
                        Distribuição de Tempo de Processamento
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="timeDistributionChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-bar-chart me-2"></i>
                        Atividade por Hora
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="hourlyChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 3 -->
    <div class="row mb-4">
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-tags me-2"></i>
                        Top Categorias
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="topCategoriesList">
                        <div class="text-center py-4">
                            <div class="spinner-border spinner-border-sm"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-shop me-2"></i>
                        Top Sellers Origem
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="topSellersList">
                        <div class="text-center py-4">
                            <div class="spinner-border spinner-border-sm"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-people me-2"></i>
                        Top Usuários
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="topUsersList">
                        <div class="text-center py-4">
                            <div class="spinner-border spinner-border-sm"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance & Projection -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-speedometer2 me-2"></i>
                        Percentis de Performance
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="border rounded p-3">
                                <h2 class="mb-0" id="percentileP50">-</h2>
                                <small class="text-muted">P50</small>
                                <p class="text-muted mb-0 small">Mediano</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-3">
                                <h2 class="mb-0" id="percentileP90">-</h2>
                                <small class="text-muted">P90</small>
                                <p class="text-muted mb-0 small">90% abaixo</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-3">
                                <h2 class="mb-0" id="percentileP99">-</h2>
                                <small class="text-muted">P99</small>
                                <p class="text-muted mb-0 small">99% abaixo</p>
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="mt-4 mb-3">Erros por Tipo</h6>
                    <div id="errorsByType">
                        <div class="text-center py-3">
                            <div class="spinner-border spinner-border-sm"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up-arrow me-2"></i>
                        Projeção (7 dias)
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="projectionChart" height="200"></canvas>
                    <div class="row mt-3 text-center" id="projectionStats">
                        <div class="col-4">
                            <strong id="projTrend">-</strong>
                            <small class="d-block text-muted">Tendência</small>
                        </div>
                        <div class="col-4">
                            <strong id="projAvg">-</strong>
                            <small class="d-block text-muted">Média Histórica</small>
                        </div>
                        <div class="col-4">
                            <strong id="projTotal">-</strong>
                            <small class="d-block text-muted">Projetado</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Period Comparison -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-arrow-left-right me-2"></i>
                        Comparação de Períodos
                    </h5>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm" id="comparePeriod1" style="width: auto;">
                            <option value="7d" selected>7 dias</option>
                            <option value="30d">30 dias</option>
                            <option value="90d">90 dias</option>
                        </select>
                        <span class="align-self-center">vs</span>
                        <select class="form-select form-select-sm" id="comparePeriod2" style="width: auto;">
                            <option value="7d">7 dias</option>
                            <option value="30d" selected>30 dias</option>
                            <option value="90d">90 dias</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" onclick="loadComparison()">
                            Comparar
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row" id="comparisonData">
                        <div class="col-12 text-center py-4 text-muted">
                            Selecione os períodos e clique em "Comparar"
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
.comparison-metric {
    padding: 1rem;
    border-radius: 0.5rem;
    background: #f8f9fa;
}
.trend-up { color: #198754; }
.trend-down { color: #dc3545; }
.trend-stable { color: #6c757d; }
</style>

<script nonce="<?= CSP_NONCE ?>">

let charts = {};
let currentPeriod = '30d';

document.addEventListener('DOMContentLoaded', function() {
    initCharts();
    loadAllData();

    // Period selector change
    document.getElementById('periodSelector').addEventListener('change', function() {
        currentPeriod = this.value;
        loadAllData();
    });

    // Trend toggle
    document.querySelectorAll('#trendToggle button').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#trendToggle button').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            loadTrends();
        });
    });
});

function initCharts() {
    // Trend Chart
    charts.trend = new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Sucesso',
                    data: [],
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    fill: true,
                    tension: 0.4,
                },
                {
                    label: 'Falhas',
                    data: [],
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    fill: true,
                    tension: 0.4,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true },
            },
        },
    });

    // Status Chart
    charts.status = new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Completos', 'Processando', 'Pendentes', 'Falhos'],
            datasets: [{
                data: [0, 0, 0, 0],
                backgroundColor: ['#198754', '#0dcaf0', '#6c757d', '#dc3545'],
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
        },
    });

    // Time Distribution Chart
    charts.timeDistribution = new Chart(document.getElementById('timeDistributionChart'), {
        type: 'bar',
        data: {
            labels: ['0-5s', '5-15s', '15-30s', '30-60s', '60s+'],
            datasets: [{
                label: 'Itens',
                data: [0, 0, 0, 0, 0],
                backgroundColor: ['#198754', '#20c997', '#ffc107', '#fd7e14', '#dc3545'],
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true },
            },
        },
    });

    // Hourly Chart
    charts.hourly = new Chart(document.getElementById('hourlyChart'), {
        type: 'bar',
        data: {
            labels: Array.from({length: 24}, (_, i) => `${i}h`),
            datasets: [{
                label: 'Itens',
                data: new Array(24).fill(0),
                backgroundColor: '#0d6efd',
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true },
            },
        },
    });

    // Projection Chart
    charts.projection = new Chart(document.getElementById('projectionChart'), {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Projeção',
                data: [],
                borderColor: '#0d6efd',
                borderDash: [5, 5],
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true },
            },
        },
    });
}

async function loadAllData() {
    await Promise.all([
        loadKPIs(),
        loadTrends(),
        loadPerformance(),
        loadBreakdown(),
        loadProjection(),
    ]);
}

function refreshData() {
    loadAllData();
}

async function loadKPIs() {
    try {
        const data = await requestJson(`/api/clone/analytics/kpis?period=${currentPeriod}`);

        if (data.success) {
            const kpis = data.data;
            
            document.getElementById('kpiTotalJobs').textContent = formatNumber(kpis.total_jobs);
            document.getElementById('kpiTotalItems').textContent = formatNumber(kpis.successful_items);
            document.getElementById('kpiSuccessRate').textContent = `${kpis.success_rate}% taxa de sucesso`;
            document.getElementById('kpiAvgTime').textContent = `${kpis.avg_processing_time}s`;
            document.getElementById('kpiFailedItems').textContent = formatNumber(kpis.failed_items);
            document.getElementById('kpiFailRate').textContent = `${(100 - kpis.success_rate).toFixed(1)}% taxa de falha`;

            // Growth indicator
            const growth = kpis.growth_vs_previous;
            const growthEl = document.getElementById('kpiJobsGrowth');
            growthEl.textContent = `${growth >= 0 ? '+' : ''}${growth}% vs período anterior`;
            growthEl.className = growth >= 0 ? 'text-success' : 'text-danger';

            // Update status chart
            const jobs = kpis.jobs_by_status || {};
            charts.status.data.datasets[0].data = [
                jobs['completed'] || 0,
                jobs['processing'] || 0,
                jobs['pending'] || 0,
                jobs['failed'] || 0,
            ];
            charts.status.update();
        }
    } catch (e) {
        console.error('Error loading KPIs:', e);
    }
}

async function loadTrends() {
    try {
        const viewType = document.querySelector('#trendToggle button.active')?.dataset.view || 'daily';
        const data = await requestJson(`/api/clone/analytics/trends?period=${currentPeriod}`);

        if (data.success) {
            const trends = data.data;
            const trendData = viewType === 'weekly' ? trends.weekly : trends.daily;

            charts.trend.data.labels = trendData.map(d => {
                const date = d.date || d.week_start;
                return new Date(date).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
            });
            charts.trend.data.datasets[0].data = trendData.map(d => d.successful || d.total);
            charts.trend.data.datasets[1].data = trendData.map(d => d.failed || 0);
            charts.trend.update();

            // Update hourly chart
            const hourly = trends.hourly_distribution || {};
            charts.hourly.data.datasets[0].data = Array.from({length: 24}, (_, i) => hourly[i] || 0);
            charts.hourly.update();
        }
    } catch (e) {
        console.error('Error loading trends:', e);
    }
}

async function loadPerformance() {
    try {
        const data = await requestJson(`/api/clone/analytics/performance?period=${currentPeriod}`);

        if (data.success) {
            const perf = data.data;

            // Percentiles
            document.getElementById('percentileP50').textContent = `${perf.percentiles.p50}s`;
            document.getElementById('percentileP90').textContent = `${perf.percentiles.p90}s`;
            document.getElementById('percentileP99').textContent = `${perf.percentiles.p99}s`;

            // Time distribution chart
            const timeDist = perf.time_distribution || {};
            charts.timeDistribution.data.datasets[0].data = [
                timeDist['0-5s'] || 0,
                timeDist['5-15s'] || 0,
                timeDist['15-30s'] || 0,
                timeDist['30-60s'] || 0,
                timeDist['60s+'] || 0,
            ];
            charts.timeDistribution.update();

            // Errors by type
            const errors = perf.errors_by_type || [];
            if (errors.length > 0) {
                document.getElementById('errorsByType').innerHTML = errors.slice(0, 5).map(e => `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-truncate" style="max-width: 200px;" title="${escapeHtml(e.error_type)}">${escapeHtml(e.error_type)}</span>
                        <span class="badge bg-danger">${e.count}</span>
                    </div>
                `).join('');
            } else {
                document.getElementById('errorsByType').innerHTML = '<p class="text-muted text-center mb-0">Sem erros no período</p>';
            }
        }
    } catch (e) {
        console.error('Error loading performance:', e);
    }
}

async function loadBreakdown() {
    try {
        const data = await requestJson(`/api/clone/analytics/breakdown?period=${currentPeriod}`);

        if (data.success) {
            const breakdown = data.data;

            // Categories
            renderTopList('topCategoriesList', breakdown.by_category, 'category', 'total');

            // Sellers
            renderTopList('topSellersList', breakdown.by_seller, 'seller_id', 'total');

            // Users
            renderTopList('topUsersList', breakdown.by_user, 'user_name', 'items', 'user_id');
        }
    } catch (e) {
        console.error('Error loading breakdown:', e);
    }
}

async function loadProjection() {
    try {
        const data = await requestJson('/api/clone/analytics/projection?days=7');

        if (data.success && !data.data.error) {
            const proj = data.data;

            charts.projection.data.labels = proj.projections.map(p => {
                return new Date(p.date).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
            });
            charts.projection.data.datasets[0].data = proj.projections.map(p => p.projected_items);
            charts.projection.update();

            // Stats
            const trendIcon = proj.trend === 'growing' ? '📈' : (proj.trend === 'declining' ? '📉' : '➡️');
            document.getElementById('projTrend').textContent = `${trendIcon} ${proj.trend}`;
            document.getElementById('projAvg').textContent = `${proj.historical_avg_daily}/dia`;
            document.getElementById('projTotal').textContent = formatNumber(proj.projected_total);
        } else {
            document.getElementById('projectionChart').parentElement.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-hourglass fs-1 d-block mb-2"></i>
                    ${data.data?.error || 'Dados insuficientes para projeção'}
                </div>
            `;
        }
    } catch (e) {
        console.error('Error loading projection:', e);
    }
}

async function loadComparison() {
    const period1 = document.getElementById('comparePeriod1').value;
    const period2 = document.getElementById('comparePeriod2').value;

    try {
        const data = await requestJson(`/api/clone/analytics/compare?period1=${period1}&period2=${period2}`);

        if (data.success) {
            const comp = data.data.comparison;
            
            document.getElementById('comparisonData').innerHTML = Object.entries(comp).map(([key, val]) => `
                <div class="col-md-4 col-6 mb-3">
                    <div class="comparison-metric">
                        <h6 class="text-muted">${formatMetricName(key)}</h6>
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>${formatNumber(val.period1)}</strong>
                                <small class="text-muted d-block">${period1}</small>
                            </div>
                            <div class="align-self-center">
                                <span class="trend-${val.trend}">
                                    ${val.trend === 'up' ? '↑' : (val.trend === 'down' ? '↓' : '→')}
                                    ${val.percent_change >= 0 ? '+' : ''}${val.percent_change}%
                                </span>
                            </div>
                            <div class="text-end">
                                <strong>${formatNumber(val.period2)}</strong>
                                <small class="text-muted d-block">${period2}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }
    } catch (e) {
        console.error('Error loading comparison:', e);
    }
}

function renderTopList(elementId, items, labelKey, valueKey, altLabelKey = null) {
    const el = document.getElementById(elementId);
    
    if (!items || items.length === 0) {
        el.innerHTML = '<div class="text-center py-4 text-muted">Sem dados</div>';
        return;
    }

    el.innerHTML = items.slice(0, 5).map((item, i) => {
        const label = item[labelKey] || (altLabelKey ? item[altLabelKey] : 'N/A');
        const value = item[valueKey] || 0;
        const successRate = item.total > 0 ? Math.round((item.successful / item.total) * 100) : 0;
        
        return `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <span class="badge bg-secondary me-2">${i + 1}</span>
                    <span class="text-truncate" style="max-width: 150px;" title="${escapeHtml(label)}">${escapeHtml(label)}</span>
                </div>
                <div class="text-end">
                    <strong>${formatNumber(value)}</strong>
                    ${item.successful !== undefined ? `<small class="text-success d-block">${successRate}% ok</small>` : ''}
                </div>
            </div>
        `;
    }).join('');
}

function formatNumber(num) {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num?.toString() || '0';
}

function formatMetricName(key) {
    const names = {
        'total_jobs': 'Total de Jobs',
        'total_items': 'Total de Itens',
        'successful_items': 'Itens com Sucesso',
        'success_rate': 'Taxa de Sucesso (%)',
        'avg_processing_time': 'Tempo Médio (s)',
    };
    return names[key] || key;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php
$content = ob_get_clean();

include __DIR__ . '/../layouts/dashboard.php';
