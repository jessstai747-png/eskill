<?php

/**
 * Dashboard Avançado com Gráficos Interativos
 *
 * Exibe métricas em tempo real com Chart.js:
 * - Vendas e receita
 * - Performance de anúncios
 * - Análise de concorrência
 * - Métricas de sistema
 *
 * @uses layouts/modern/app.php
 */

$pageTitle = 'Dashboard Avançado';
$title = 'Analytics Avançado';
$subtitle = 'Métricas em tempo real e análise de performance';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Analytics Avançado']
];

// Account Selector
$requireAccountSelection = true;
$showAccountBanner = true;
include __DIR__ . '/../components/account-selector.php';

ob_start();
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>

<style>
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }

    .metric-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .metric-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    .metric-value {
        font-size: 2rem;
        font-weight: 700;
    }

    .metric-change {
        font-size: 0.875rem;
    }

    .metric-change.positive {
        color: #28a745;
    }

    .metric-change.negative {
        color: #dc3545;
    }

    .filter-bar {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
    }

    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
    }
</style>

<!-- Page Header -->
<?php include __DIR__ . '/../layouts/modern/partials/page-header.php'; ?>

<div class="container-fluid py-0">
    <!-- Action Buttons -->
    <div class="d-flex justify-content-end mb-4">
        <button class="btn btn-outline-primary me-2" onclick="refreshAllData()">
            <i class="bi bi-arrow-clockwise"></i> Atualizar
        </button>
        <div class="btn-group">
            <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-download"></i> Exportar
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="exportData('csv')">CSV</a></li>
                <li><a class="dropdown-item" href="#" onclick="exportData('json')">JSON</a></li>
                <li><a class="dropdown-item" href="#" onclick="exportData('pdf')">PDF</a></li>
            </ul>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filter-bar">
        <div class="row g-3 align-items-center">
            <div class="col-auto">
                <label class="col-form-label"><i class="fas fa-calendar me-1"></i>Período:</label>
            </div>
            <div class="col-auto">
                <select id="period-filter" class="form-select" onchange="changePeriod(this.value)">
                    <option value="7">Últimos 7 dias</option>
                    <option value="30" selected>Últimos 30 dias</option>
                    <option value="90">Últimos 90 dias</option>
                    <option value="365">Último ano</option>
                    <option value="custom">Personalizado</option>
                </select>
            </div>
            <div class="col-auto" id="custom-dates" style="display: none;">
                <input type="date" id="date-start" class="form-control d-inline-block" style="width: auto;">
                <span class="mx-2">até</span>
                <input type="date" id="date-end" class="form-control d-inline-block" style="width: auto;">
            </div>
            <div class="col-auto">
                <label class="col-form-label"><i class="fas fa-store me-1"></i>Conta:</label>
            </div>
            <div class="col-auto">
                <select id="account-filter" class="form-select" onchange="changeAccount(this.value)">
                    <option value="all">Todas as contas</option>
                    <!-- Preenchido via JS -->
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" onclick="applyFilters()">
                    <i class="fas fa-filter"></i> Aplicar
                </button>
            </div>
        </div>
    </div>

    <!-- KPIs Principais -->
    <div class="row mb-4" id="kpi-cards">
        <div class="col-md-3">
            <div class="card metric-card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title opacity-75">Vendas</h6>
                            <div class="metric-value" id="kpi-sales">-</div>
                            <div class="metric-change" id="kpi-sales-change">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <i class="fas fa-shopping-cart fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title opacity-75">Receita</h6>
                            <div class="metric-value" id="kpi-revenue">-</div>
                            <div class="metric-change" id="kpi-revenue-change">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title opacity-75">Anúncios Ativos</h6>
                            <div class="metric-value" id="kpi-listings">-</div>
                            <div class="metric-change" id="kpi-listings-change">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <i class="fas fa-list fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title opacity-75">Ticket Médio</h6>
                            <div class="metric-value" id="kpi-ticket">-</div>
                            <div class="metric-change" id="kpi-ticket-change">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </div>
                        <i class="fas fa-receipt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos Principais -->
    <div class="row mb-4">
        <!-- Gráfico de Vendas -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-chart-area me-2"></i>Vendas e Receita</h5>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary active" data-chart-type="line">Linha</button>
                        <button class="btn btn-outline-secondary" data-chart-type="bar">Barras</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Produtos -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top 5 Produtos</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="topProductsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Categorias -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Vendas por Categoria</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="categoriesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status dos Anúncios -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Status dos Anúncios</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="listingsStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance por Hora -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Vendas por Hora</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="hourlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de Produtos -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-table me-2"></i>Detalhamento de Produtos</h5>
                    <div class="input-group" style="width: 300px;">
                        <input type="text" class="form-control" placeholder="Buscar produto..." id="product-search">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="products-table">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>SKU</th>
                                    <th>Vendas</th>
                                    <th>Receita</th>
                                    <th>Estoque</th>
                                    <th>Score SEO</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <i class="fas fa-spinner fa-spin"></i> Carregando...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <nav id="pagination-nav" class="mt-3">
                        <!-- Paginação -->
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

    function normalizeExternalUrl(url) {
        if (!url || typeof url !== 'string') return '';
        const trimmed = url.trim();
        if (!trimmed) return '';
        if (/^(data:|blob:)/i.test(trimmed)) return trimmed;
        if (trimmed.startsWith('//')) return window.location.protocol + trimmed;
        if (/^http:\/\//i.test(trimmed)) return trimmed.replace(/^http:\/\//i, 'https://');
        return trimmed;
    }

    // Configuração global
    const CONFIG = {
        period: 30,
        account: 'all',
        dateStart: null,
        dateEnd: null
    };

    // Instâncias dos gráficos
    let charts = {};

    // Inicialização
    document.addEventListener('DOMContentLoaded', function() {
        initCharts();
        loadAccounts();
        loadDashboardData();

        // Auto-refresh a cada 5 minutos
        setInterval(refreshAllData, 300000);
    });

    // Inicializar gráficos vazios
    function initCharts() {
        // Gráfico de Vendas e Receita
        charts.sales = new Chart(document.getElementById('salesChart'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                        label: 'Vendas',
                        data: [],
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Receita (R$)',
                        data: [],
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        type: 'linear',
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Vendas'
                        }
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Receita (R$)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 1) {
                                    return 'Receita: R$ ' + context.raw.toLocaleString('pt-BR');
                                }
                                return context.dataset.label + ': ' + context.raw;
                            }
                        }
                    }
                }
            }
        });

        // Top Produtos (Horizontal Bar)
        charts.topProducts = new Chart(document.getElementById('topProductsChart'), {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Vendas',
                    data: [],
                    backgroundColor: '#0d6efd'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Categorias (Doughnut)
        charts.categories = new Chart(document.getElementById('categoriesChart'), {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#20c997']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // Status dos Anúncios (Pie)
        charts.listingsStatus = new Chart(document.getElementById('listingsStatusChart'), {
            type: 'pie',
            data: {
                labels: ['Ativos', 'Pausados', 'Finalizados'],
                datasets: [{
                    data: [0, 0, 0],
                    backgroundColor: ['#198754', '#ffc107', '#6c757d']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // Vendas por Hora (Radar)
        charts.hourly = new Chart(document.getElementById('hourlyChart'), {
            type: 'bar',
            data: {
                labels: Array.from({
                    length: 24
                }, (_, i) => i + 'h'),
                datasets: [{
                    label: 'Vendas',
                    data: Array(24).fill(0),
                    backgroundColor: '#0d6efd'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Carregar contas do ML
    function loadAccounts() {
        requestJson('/api/auth/accounts')
            .then(data => {
                const select = document.getElementById('account-filter');
                if (data.accounts) {
                    data.accounts.forEach(acc => {
                        const option = document.createElement('option');
                        option.value = acc.id;
                        option.textContent = acc.nickname || acc.email;
                        select.appendChild(option);
                    });
                }
            })
            .catch(console.error);
    }

    // Carregar dados do dashboard
    function loadDashboardData() {
        const params = new URLSearchParams({
            period: CONFIG.period,
            account: CONFIG.account
        });

        if (CONFIG.dateStart && CONFIG.dateEnd) {
            params.set('date_start', CONFIG.dateStart);
            params.set('date_end', CONFIG.dateEnd);
        }

        // Carregar KPIs
        requestJson('/api/reports/consolidated?' + params)
            .then(data => updateKPIs(data))
            .catch(err => console.error('Erro ao carregar KPIs:', err));

        // Carregar dados de vendas para gráfico
        requestJson('/api/reports/sales-timeline?' + params)
            .then(data => updateSalesChart(data))
            .catch(err => console.error('Erro ao carregar vendas:', err));

        // Carregar top produtos
        requestJson('/api/reports/top-products?' + params)
            .then(data => updateTopProductsChart(data))
            .catch(err => console.error('Erro ao carregar top produtos:', err));

        // Carregar categorias
        requestJson('/api/reports/by-category?' + params)
            .then(data => updateCategoriesChart(data))
            .catch(err => console.error('Erro ao carregar categorias:', err));

        // Carregar status de anúncios
        requestJson('/api/items/stats?' + params)
            .then(data => updateListingsStatusChart(data))
            .catch(err => console.error('Erro ao carregar status:', err));

        // Carregar vendas por hora
        requestJson('/api/reports/hourly?' + params)
            .then(data => updateHourlyChart(data))
            .catch(err => console.error('Erro ao carregar vendas por hora:', err));

        // Carregar tabela de produtos
        loadProductsTable();
    }

    // Atualizar KPIs
    function updateKPIs(data) {
        if (!data || data.error) return;

        const d = data.data || data;

        document.getElementById('kpi-sales').textContent = (d.total_sales || 0).toLocaleString('pt-BR');
        document.getElementById('kpi-revenue').textContent = 'R$ ' + (d.total_revenue || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2
        });
        document.getElementById('kpi-listings').textContent = (d.active_listings || 0).toLocaleString('pt-BR');
        document.getElementById('kpi-ticket').textContent = 'R$ ' + (d.avg_ticket || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2
        });

        // Calcular variação
        const changes = d.changes || {};
        updateKPIChange('kpi-sales-change', changes.sales);
        updateKPIChange('kpi-revenue-change', changes.revenue);
        updateKPIChange('kpi-listings-change', changes.listings);
        updateKPIChange('kpi-ticket-change', changes.ticket);
    }

    function updateKPIChange(elementId, change) {
        const el = document.getElementById(elementId);
        if (!change && change !== 0) {
            el.innerHTML = '-';
            return;
        }

        const icon = change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
        const cls = change >= 0 ? 'positive' : 'negative';
        el.innerHTML = `<span class="${cls}"><i class="fas ${icon}"></i> ${Math.abs(change).toFixed(1)}%</span> vs período anterior`;
    }

    // Atualizar gráfico de vendas
    function updateSalesChart(data) {
        if (!data || !data.data) return;

        charts.sales.data.labels = data.data.map(d => d.date);
        charts.sales.data.datasets[0].data = data.data.map(d => d.sales);
        charts.sales.data.datasets[1].data = data.data.map(d => d.revenue);
        charts.sales.update();
    }

    // Atualizar top produtos
    function updateTopProductsChart(data) {
        if (!data || !data.data) return;

        const top5 = data.data.slice(0, 5);
        charts.topProducts.data.labels = top5.map(p => p.title?.substring(0, 30) + '...');
        charts.topProducts.data.datasets[0].data = top5.map(p => p.sales);
        charts.topProducts.update();
    }

    // Atualizar categorias
    function updateCategoriesChart(data) {
        if (!data || !data.data) return;

        const top6 = data.data.slice(0, 6);
        charts.categories.data.labels = top6.map(c => c.category_name || c.category_id);
        charts.categories.data.datasets[0].data = top6.map(c => c.total_sales || c.count);
        charts.categories.update();
    }

    // Atualizar status dos anúncios
    function updateListingsStatusChart(data) {
        if (!data) return;

        const d = data.data || data;
        charts.listingsStatus.data.datasets[0].data = [
            d.active || 0,
            d.paused || 0,
            d.closed || 0
        ];
        charts.listingsStatus.update();
    }

    // Atualizar vendas por hora
    function updateHourlyChart(data) {
        if (!data || !data.data) return;

        const hourlyData = Array(24).fill(0);
        data.data.forEach(d => {
            const hour = parseInt(d.hour);
            if (hour >= 0 && hour < 24) {
                hourlyData[hour] = d.sales || 0;
            }
        });

        charts.hourly.data.datasets[0].data = hourlyData;
        charts.hourly.update();
    }

    // Carregar tabela de produtos
    function loadProductsTable(page = 1) {
        const tbody = document.querySelector('#products-table tbody');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin"></i> Carregando...</td></tr>';

        const params = new URLSearchParams({
            page: page,
            limit: 10,
            account: CONFIG.account
        });

        requestJson('/api/items?' + params)
            .then(data => {
                if (!data || !data.data) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhum produto encontrado</td></tr>';
                    return;
                }

                tbody.innerHTML = data.data.map(item => `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                               <img src="${normalizeExternalUrl(item.thumbnail) || '/img/no-image.png'}" 
                                 class="rounded me-2" width="40" height="40" 
                                 onerror="this.src='/img/no-image.png'">
                            <div>
                                <div class="fw-medium">${(item.title || '').substring(0, 50)}${item.title?.length > 50 ? '...' : ''}</div>
                                <small class="text-muted">${item.ml_item_id || ''}</small>
                            </div>
                        </div>
                    </td>
                    <td>${item.sku || '-'}</td>
                    <td>${item.sold_quantity || 0}</td>
                    <td>R$ ${(item.price || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                    <td>
                        <span class="badge ${item.available_quantity > 10 ? 'bg-success' : item.available_quantity > 0 ? 'bg-warning' : 'bg-danger'}">
                            ${item.available_quantity || 0}
                        </span>
                    </td>
                    <td>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar ${item.seo_score >= 80 ? 'bg-success' : item.seo_score >= 50 ? 'bg-warning' : 'bg-danger'}" 
                                 style="width: ${item.seo_score || 0}%">
                                ${item.seo_score || 0}%
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="/items/${item.id}" class="btn btn-outline-primary" title="Ver">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="/api/seo/analyze/${item.ml_item_id}" class="btn btn-outline-info" title="Analisar SEO">
                                <i class="fas fa-search"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            `).join('');

                // Paginação
                updatePagination(data.pagination || {
                    page: 1,
                    pages: 1
                }, page);
            })
            .catch(err => {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Erro ao carregar produtos</td></tr>';
                console.error(err);
            });
    }

    function updatePagination(pagination, currentPage) {
        const nav = document.getElementById('pagination-nav');
        if (!pagination || pagination.pages <= 1) {
            nav.innerHTML = '';
            return;
        }

        let html = '<ul class="pagination justify-content-center">';
        html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="loadProductsTable(${currentPage - 1})">Anterior</a>
             </li>`;

        for (let i = 1; i <= pagination.pages; i++) {
            if (i === 1 || i === pagination.pages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="loadProductsTable(${i})">${i}</a>
                     </li>`;
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        html += `<li class="page-item ${currentPage === pagination.pages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="loadProductsTable(${currentPage + 1})">Próximo</a>
             </li>`;
        html += '</ul>';

        nav.innerHTML = html;
    }

    // Funções de filtro
    function changePeriod(value) {
        CONFIG.period = value;

        const customDates = document.getElementById('custom-dates');
        if (value === 'custom') {
            customDates.style.display = 'block';
        } else {
            customDates.style.display = 'none';
            CONFIG.dateStart = null;
            CONFIG.dateEnd = null;
        }
    }

    function changeAccount(value) {
        CONFIG.account = value;
    }

    function applyFilters() {
        if (CONFIG.period === 'custom') {
            CONFIG.dateStart = document.getElementById('date-start').value;
            CONFIG.dateEnd = document.getElementById('date-end').value;
        }

        loadDashboardData();
    }

    function refreshAllData() {
        loadDashboardData();
    }

    // Exportar dados
    function exportData(format) {
        const params = new URLSearchParams({
            format: format,
            period: CONFIG.period,
            account: CONFIG.account
        });

        window.location.href = '/api/export/dashboard?' + params;
    }

    // Busca de produtos
    document.getElementById('product-search')?.addEventListener('input', debounce(function(e) {
        const search = e.target.value;
        // Implementar busca filtrada
        console.log('Buscar:', search);
    }, 500));

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Alternar tipo de gráfico
    document.querySelectorAll('[data-chart-type]').forEach(btn => {
        btn.addEventListener('click', function() {
            const type = this.dataset.chartType;

            // Atualizar botões
            this.parentElement.querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            // Atualizar gráfico
            charts.sales.config.type = type;
            charts.sales.update();
        });
    });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/modern/app.php';
