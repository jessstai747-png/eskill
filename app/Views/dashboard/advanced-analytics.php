<?php
$pageTitle = '📊 Analytics Avançado';
$activePage = 'advanced-analytics';

// Page Header
$title = '📊 Analytics Avançado';
$subtitle = 'Business Intelligence e Análise de Performance';
include __DIR__ . '/../layouts/modern/partials/page-header.php';
?>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- Toastify for Notifications -->
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<style>
.analytics-dashboard {
    padding: 20px;
}

.metric-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
    height: 100%;
}

.metric-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.metric-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin-bottom: 16px;
}

.metric-icon.blue {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.metric-icon.green {
    background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
    color: white;
}

.metric-icon.orange {
    background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);
    color: white;
}

.metric-icon.red {
    background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    color: white;
}

.metric-value {
    font-size: 32px;
    font-weight: bold;
    color: #2c3e50;
    margin: 8px 0;
}

.metric-label {
    color: #7f8c8d;
    font-size: 14px;
    font-weight: 500;
}

.metric-change {
    font-size: 14px;
    font-weight: 600;
    margin-top: 8px;
}

.metric-change.positive {
    color: #27ae60;
}

.metric-change.negative {
    color: #e74c3c;
}

.chart-container {
    position: relative;
    height: 300px;
    margin-top: 20px;
}

.period-selector {
    background: white;
    border-radius: 8px;
    padding: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.period-btn {
    padding: 8px 20px;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
    font-weight: 500;
}

.period-btn:hover {
    background: #f8f9fa;
}

.period-btn.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: #667eea;
}

.insight-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
}

.insight-card h5 {
    color: white;
    margin-bottom: 16px;
}

.insight-item {
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
}

.insight-item:last-child {
    margin-bottom: 0;
}

.top-products-table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.top-products-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
    padding: 16px;
    border-bottom: 2px solid #dee2e6;
}

.top-products-table td {
    padding: 16px;
    vertical-align: middle;
}

.product-thumbnail {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 8px;
}

.trend-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.trend-badge.up {
    background: #d4edda;
    color: #155724;
}

.trend-badge.down {
    background: #f8d7da;
    color: #721c24;
}

.loading-skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s ease-in-out infinite;
    border-radius: 8px;
}

@keyframes loading {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
</style>

<div class="analytics-dashboard">
    
    <!-- Period Selector -->
    <div class="period-selector mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>Período de Análise:</strong>
            </div>
            <div class="btn-group" role="group">
                <button class="period-btn" onclick="changePeriod('7days')">7 dias</button>
                <button class="period-btn active" onclick="changePeriod('30days')">30 dias</button>
                <button class="period-btn" onclick="changePeriod('90days')">90 dias</button>
                <button class="period-btn" onclick="changePeriod('1year')">1 ano</button>
            </div>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="metric-card">
                <div class="metric-icon blue">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="metric-value" id="total-revenue">R$ 0</div>
                <div class="metric-label">Receita Total</div>
                <div class="metric-change positive" id="revenue-change">+0%</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <div class="metric-icon green">
                    <i class="bi bi-cart-check"></i>
                </div>
                <div class="metric-value" id="total-orders">0</div>
                <div class="metric-label">Vendas Realizadas</div>
                <div class="metric-change positive" id="orders-change">+0%</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <div class="metric-icon orange">
                    <i class="bi bi-eye"></i>
                </div>
                <div class="metric-value" id="total-visits">0</div>
                <div class="metric-label">Visualizações</div>
                <div class="metric-change positive" id="visits-change">+0%</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <div class="metric-icon red">
                    <i class="bi bi-percent"></i>
                </div>
                <div class="metric-value" id="conversion-rate">0%</div>
                <div class="metric-label">Taxa de Conversão</div>
                <div class="metric-change positive" id="conversion-change">+0%</div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="metric-card">
                <h5>Tendência de Vendas</h5>
                <div class="chart-container">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="metric-card">
                <h5>Distribuição de Vendas</h5>
                <div class="chart-container">
                    <canvas id="salesDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Insights & Top Products -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="insight-card">
                <h5><i class="bi bi-lightbulb"></i> Insights Estratégicos</h5>
                <div id="insights-container">
                    <!-- Dynamic insights -->
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="top-products-table">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Produto</th>
                            <th>Vendas</th>
                            <th>Receita</th>
                            <th>Taxa Conv.</th>
                            <th>Tendência</th>
                        </tr>
                    </thead>
                    <tbody id="top-products-tbody">
                        <!-- Dynamic products -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="metric-card">
                <h5>Performance por Categoria</h5>
                <div class="chart-container">
                    <canvas id="categoryPerformanceChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="metric-card">
                <h5>Funil de Conversão</h5>
                <div class="chart-container">
                    <canvas id="conversionFunnelChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Usage Stats -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="metric-card">
                <h5><i class="bi bi-robot"></i> Estatísticas de Uso de IA</h5>
                <div class="row mt-3">
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="metric-value" style="font-size: 24px;" id="ai-optimizations">0</div>
                            <div class="metric-label">Otimizações por IA</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="metric-value" style="font-size: 24px;" id="ai-cost">R$ 0</div>
                            <div class="metric-label">Custo Total IA</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="metric-value" style="font-size: 24px;" id="ai-roi">0x</div>
                            <div class="metric-label">ROI da IA</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <div class="metric-value" style="font-size: 24px;" id="ai-time-saved">0h</div>
                            <div class="metric-label">Tempo Economizado</div>
                        </div>
                    </div>
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

// Analytics State
const analyticsState = {
    period: '30days',
    charts: {},
    data: null
};

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    loadAnalyticsData();
});

// Change period
function changePeriod(period) {
    analyticsState.period = period;
    
    // Update button states
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Reload data
    loadAnalyticsData();
}

// Load analytics data
async function loadAnalyticsData() {
    try {
        // Show loading
        showLoadingState();
        
        const {data} = await requestJson(`/api/analytics/dashboard?period=${analyticsState.period}`);
        
        analyticsState.data = data;
        
        // Update all sections
        updateKeyMetrics(data.metrics);
        updateCharts(data.charts);
        updateInsights(data.insights);
        updateTopProducts(data.top_products);
        updateAIStats(data.ai_stats);
        
        Toastify({
            text: 'Dados atualizados!',
            duration: 2000,
            backgroundColor: 'linear-gradient(to right, #00b09b, #96c93d)'
        }).showToast();
        
    } catch (error) {
        console.error('Error loading analytics:', error);
        Toastify({
            text: 'Erro ao carregar analytics',
            duration: 3000,
            backgroundColor: 'linear-gradient(to right, #ff5f6d, #ffc371)'
        }).showToast();
    }
}

// Update key metrics
function updateKeyMetrics(metrics) {
    document.getElementById('total-revenue').textContent = `R$ ${formatNumber(metrics.revenue || 0)}`;
    document.getElementById('total-orders').textContent = formatNumber(metrics.orders || 0);
    document.getElementById('total-visits').textContent = formatNumber(metrics.visits || 0);
    document.getElementById('conversion-rate').textContent = `${(metrics.conversion_rate || 0).toFixed(2)}%`;
    
    // Changes
    updateChange('revenue-change', metrics.revenue_change || 0);
    updateChange('orders-change', metrics.orders_change || 0);
    updateChange('visits-change', metrics.visits_change || 0);
    updateChange('conversion-change', metrics.conversion_change || 0);
}

// Update change indicator
function updateChange(elementId, change) {
    const el = document.getElementById(elementId);
    const isPositive = change >= 0;
    
    el.textContent = `${isPositive ? '+' : ''}${change.toFixed(1)}%`;
    el.className = `metric-change ${isPositive ? 'positive' : 'negative'}`;
}

// Update charts
function updateCharts(chartsData) {
    // Destroy existing charts
    Object.values(analyticsState.charts).forEach(chart => chart.destroy());
    analyticsState.charts = {};
    
    // Sales Trend Chart
    const salesCtx = document.getElementById('salesTrendChart').getContext('2d');
    analyticsState.charts.salesTrend = new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: chartsData.sales_trend.labels || [],
            datasets: [{
                label: 'Receita',
                data: chartsData.sales_trend.data || [],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
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
    
    // Sales Distribution Chart
    const distCtx = document.getElementById('salesDistributionChart').getContext('2d');
    analyticsState.charts.salesDistribution = new Chart(distCtx, {
        type: 'doughnut',
        data: {
            labels: chartsData.distribution.labels || [],
            datasets: [{
                data: chartsData.distribution.data || [],
                backgroundColor: [
                    '#667eea',
                    '#764ba2',
                    '#f2994a',
                    '#27ae60',
                    '#e74c3c'
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
    
    // Category Performance Chart
    const catCtx = document.getElementById('categoryPerformanceChart').getContext('2d');
    analyticsState.charts.categoryPerformance = new Chart(catCtx, {
        type: 'bar',
        data: {
            labels: chartsData.categories.labels || [],
            datasets: [{
                label: 'Vendas',
                data: chartsData.categories.data || [],
                backgroundColor: '#667eea'
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
    
    // Conversion Funnel Chart
    const funnelCtx = document.getElementById('conversionFunnelChart').getContext('2d');
    analyticsState.charts.conversionFunnel = new Chart(funnelCtx, {
        type: 'bar',
        data: {
            labels: ['Visualizações', 'Cliques', 'Carrinhos', 'Vendas'],
            datasets: [{
                label: 'Funil',
                data: chartsData.funnel.data || [],
                backgroundColor: [
                    '#667eea',
                    '#764ba2',
                    '#f2994a',
                    '#27ae60'
                ]
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
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Update insights
function updateInsights(insights) {
    const container = document.getElementById('insights-container');
    
    if (!insights || insights.length === 0) {
        container.innerHTML = '<div class="insight-item">Nenhum insight disponível no momento.</div>';
        return;
    }
    
    container.innerHTML = insights.map(insight => `
        <div class="insight-item">
            <strong><i class="bi bi-${insight.icon || 'lightbulb'}"></i> ${insight.title}</strong>
            <p class="mb-0 mt-2">${insight.description}</p>
        </div>
    `).join('');
}

// Update top products
function updateTopProducts(products) {
    const tbody = document.getElementById('top-products-tbody');
    
    if (!products || products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Nenhum produto encontrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = products.map((product, index) => `
        <tr>
            <td><strong>${index + 1}</strong></td>
            <td>
                <div class="d-flex align-items-center">
                    <img src="${normalizeExternalUrl(product.thumbnail) || '/placeholder.png'}" alt="${product.title}" class="product-thumbnail me-3">
                    <div>
                        <strong>${truncate(product.title, 40)}</strong><br>
                        <small class="text-muted">${product.id}</small>
                    </div>
                </div>
            </td>
            <td><strong>${product.sales || 0}</strong></td>
            <td><strong>R$ ${formatNumber(product.revenue || 0)}</strong></td>
            <td><strong>${(product.conversion_rate || 0).toFixed(2)}%</strong></td>
            <td>
                <span class="trend-badge ${product.trend >= 0 ? 'up' : 'down'}">
                    <i class="bi bi-arrow-${product.trend >= 0 ? 'up' : 'down'}"></i>
                    ${Math.abs(product.trend || 0).toFixed(1)}%
                </span>
            </td>
        </tr>
    `).join('');
}

// Update AI stats
function updateAIStats(stats) {
    if (!stats) return;
    
    document.getElementById('ai-optimizations').textContent = formatNumber(stats.optimizations || 0);
    document.getElementById('ai-cost').textContent = `R$ ${formatNumber(stats.cost || 0)}`;
    document.getElementById('ai-roi').textContent = `${(stats.roi || 0).toFixed(1)}x`;
    document.getElementById('ai-time-saved').textContent = `${formatNumber(stats.time_saved || 0)}h`;
}

// Show loading state
function showLoadingState() {
    // Add loading class to metric values
    document.querySelectorAll('.metric-value').forEach(el => {
        el.classList.add('loading-skeleton');
    });
}

// Helper: Format number
function formatNumber(num) {
    return new Intl.NumberFormat('pt-BR').format(num);
}

// Helper: Truncate text
function truncate(text, length) {
    return text.length > length ? text.substring(0, length) + '...' : text;
}
</script>

<!-- Load Chatbot Widget -->
<?php include __DIR__ . '/seo-killer/components/ai-chatbot-widget.php'; ?>
