<?php
$title = 'Business Intelligence';
$subtitle = 'Analytics avançados e insights preditivos';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<style>
.analytics-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    height: 100%;
}
.metric-big {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary-color, #6f42c1);
}
.metric-label {
    font-size: 0.9rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.trend-up { color: #28a745; }
.trend-down { color: #dc3545; }
.chart-container {
    position: relative;
    height: 300px;
}
</style>

<!-- Real-Time Summary Cards -->
<div class="row g-4 mb-4" id="summary-cards">
    <div class="col-md-3">
        <div class="analytics-card">
            <div class="metric-label">Receita Hoje</div>
            <div class="metric-big" id="revenue-today">R$ 0</div>
            <small class="trend-up" id="growth-rate">+0%</small> vs ontem
        </div>
    </div>
    <div class="col-md-3">
        <div class="analytics-card">
            <div class="metric-label">Perguntas Pendentes</div>
            <div class="metric-big text-warning" id="pending-questions">0</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="analytics-card">
            <div class="metric-label">Itens Ativos</div>
            <div class="metric-big text-info" id="active-items">0</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="analytics-card">
            <div class="metric-label">Taxa Conversão</div>
            <div class="metric-big text-success" id="conversion-rate">0%</div>
        </div>
    </div>
</div>

<!-- Interactive Charts -->
<div class="row g-4 mb-4">
    <!-- Revenue Trend -->
    <div class="col-lg-8">
        <div class="analytics-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">📈 Evolução de Receita</h5>
                <select class="form-select form-select-sm w-auto" id="period-selector">
                    <option value="7">Últimos 7 dias</option>
                    <option value="30" selected>Últimos 30 dias</option>
                    <option value="90">Últimos 90 dias</option>
                </select>
            </div>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Customer Segments -->
    <div class="col-lg-4">
        <div class="analytics-card">
            <h5 class="mb-3">👥 Segmentos de Clientes (LTV)</h5>
            <div class="chart-container">
                <canvas id="ltvChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Profit Margins -->
    <div class="col-lg-6">
        <div class="analytics-card">
            <h5 class="mb-3">💰 Margens de Lucro por Tipo</h5>
            <div class="chart-container">
                <canvas id="marginChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Inventory Turnover -->
    <div class="col-lg-6">
        <div class="analytics-card">
            <h5 class="mb-3">🔄 Giro de Estoque (Top Categorias)</h5>
            <div id="turnover-table"></div>
        </div>
    </div>
</div>

<!-- Forecast Section -->
<div class="row g-4">
    <div class="col-12">
        <div class="analytics-card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h5 class="mb-3">🔮 Previsão de Receita (Próximos 7 Dias)</h5>
            <div class="chart-container">
                <canvas id="forecastChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

const Analytics = {
    charts: {},

    async init() {
        await this.loadSummary();
        await this.loadRevenueTrend();
        await this.loadCustomerLTV();
        await this.loadProfitMargins();
        await this.loadInventoryTurnover();
        await this.loadForecast();
        
        // Auto-refresh every 60 seconds
        setInterval(() => this.loadSummary(), 60000);
    },

    async loadSummary() {
        const json = await requestJson('/api/analytics/summary');
        const data = json.data;
        
        document.getElementById('revenue-today').textContent = 'R$ ' + data.revenue_today.toFixed(2);
        document.getElementById('pending-questions').textContent = data.pending_questions;
        document.getElementById('active-items').textContent = data.active_items;
        
        const growthEl = document.getElementById('growth-rate');
        growthEl.textContent = (data.growth_rate >= 0 ? '+' : '') + data.growth_rate + '%';
        growthEl.className = data.growth_rate >= 0 ? 'trend-up' : 'trend-down';
    },

    async loadRevenueTrend() {
        const days = document.getElementById('period-selector').value;
        const start = new Date();
        start.setDate(start.getDate() - days);
        
        const json = await requestJson(`/api/analytics/revenue-trend?start=${start.toISOString().split('T')[0]}&end=${new Date().toISOString().split('T')[0]}`);
        
        const ctx = document.getElementById('revenueChart').getContext('2d');
        
        if (this.charts.revenue) this.charts.revenue.destroy();
        
        this.charts.revenue = new Chart(ctx, {
            type: 'line',
            data: {
                labels: json.data.map(d => d.period),
                datasets: [{
                    label: 'Receita (R$)',
                    data: json.data.map(d => parseFloat(d.revenue)),
                    borderColor: '#6f42c1',
                    backgroundColor: 'rgba(111, 66, 193, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    },

    async loadCustomerLTV() {
        const json = await requestJson('/api/analytics/customer-ltv');
        
        const ctx = document.getElementById('ltvChart').getContext('2d');
        
        if (this.charts.ltv) this.charts.ltv.destroy();
        
        this.charts.ltv = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: json.data.map(d => d.segment),
                datasets: [{
                    data: json.data.map(d => d.customer_count),
                    backgroundColor: ['#6f42c1', '#4CAF50', '#FFC107', '#FF5722']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    },

    async loadProfitMargins() {
        const json = await requestJson('/api/analytics/profit-margins');
        
        const ctx = document.getElementById('marginChart').getContext('2d');
        
        if (this.charts.margin) this.charts.margin.destroy();
        
        this.charts.margin = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: json.data.map(d => d.listing_type || 'N/A'),
                datasets: [{
                    label: 'Margem Média (%)',
                    data: json.data.map(d => parseFloat(d.avg_margin)),
                    backgroundColor: '#28a745'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    },

    async loadInventoryTurnover() {
        const json = await requestJson('/api/analytics/inventory-turnover');
        
        let html = '<table class="table table-sm"><thead><tr><th>Categoria</th><th>Taxa</th></tr></thead><tbody>';
        json.data.forEach(d => {
            html += `<tr><td>ID ${d.category_id}</td><td><span class="badge bg-success">${d.turnover_rate}%</span></td></tr>`;
        });
        html += '</tbody></table>';
        
        document.getElementById('turnover-table').innerHTML = html;
    },

    async loadForecast() {
        const json = await requestJson('/api/analytics/forecast?days=7');
        
        const ctx = document.getElementById('forecastChart').getContext('2d');
        
        if (this.charts.forecast) this.charts.forecast.destroy();
        
        this.charts.forecast = new Chart(ctx, {
            type: 'line',
            data: {
                labels: json.data.map(d => d.date),
                datasets: [{
                    label: 'Previsão (R$)',
                    data: json.data.map(d => d.predicted_revenue),
                    borderColor: '#fff',
                    backgroundColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { 
                        labels: { color: '#fff' }
                    }
                },
                scales: {
                    y: { ticks: { color: '#fff' } },
                    x: { ticks: { color: '#fff' } }
                }
            }
        });
    }
};

// Period selector change
document.getElementById('period-selector').addEventListener('change', () => Analytics.loadRevenueTrend());

// Initialize on load
Analytics.init();
</script>
