<!-- Dashboard Statistics View -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Estatísticas</h4>
        <p class="text-muted mb-0">Análise detalhada do seu desempenho</p>
    </div>
    <div class="btn-group btn-group-sm">
        <button class="btn btn-outline-primary active" data-period="7">7 dias</button>
        <button class="btn btn-outline-primary" data-period="30">30 dias</button>
        <button class="btn btn-outline-primary" data-period="90">90 dias</button>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-currency-dollar fs-1 text-success"></i>
                <h3 class="mt-2 mb-1" id="totalRevenue">R$ 0</h3>
                <p class="text-muted mb-0">Faturamento</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-bag-check fs-1 text-primary"></i>
                <h3 class="mt-2 mb-1" id="totalOrders">0</h3>
                <p class="text-muted mb-0">Pedidos</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-box-seam fs-1 text-info"></i>
                <h3 class="mt-2 mb-1" id="totalUnits">0</h3>
                <p class="text-muted mb-0">Unidades Vendidas</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-receipt fs-1 text-warning"></i>
                <h3 class="mt-2 mb-1" id="avgTicket">R$ 0</h3>
                <p class="text-muted mb-0">Ticket Médio</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Vendas por Período</h6>
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="300"></canvas>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Produtos</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th class="text-center">Vendas</th>
                                <th class="text-end">Receita</th>
                            </tr>
                        </thead>
                        <tbody id="topProductsTable">
                            <tr><td colspan="3" class="text-center py-4 text-muted">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Vendas por Categoria</h6>
            </div>
            <div class="card-body">
                <canvas id="categoryChart" height="250"></canvas>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Horários de Pico</h6>
            </div>
            <div class="card-body">
                <canvas id="hoursChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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

let salesChart, categoryChart, hoursChart;
let currentPeriod = 7;

document.querySelectorAll('[data-period]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('[data-period]').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentPeriod = parseInt(this.dataset.period);
        loadStatistics();
    });
});

async function loadStatistics() {
    try {
        const data = await requestJson(`/api/statistics?period=${currentPeriod}`);
        
        document.getElementById('totalRevenue').textContent = formatCurrency(data.total_revenue || 0);
        document.getElementById('totalOrders').textContent = data.total_orders || 0;
        document.getElementById('totalUnits').textContent = data.total_units || 0;
        document.getElementById('avgTicket').textContent = formatCurrency(data.avg_ticket || 0);
        
        updateSalesChart(data.sales_by_day || []);
        updateCategoryChart(data.sales_by_category || []);
        updateHoursChart(data.sales_by_hour || []);
        updateTopProducts(data.top_products || []);
    } catch (e) {
        console.error('Error loading statistics:', e);
    }
}

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

function updateSalesChart(data) {
    const ctx = document.getElementById('salesChart').getContext('2d');
    if (salesChart) salesChart.destroy();
    salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(d => d.date),
            datasets: [{
                label: 'Vendas',
                data: data.map(d => d.total),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
}

function updateCategoryChart(data) {
    const ctx = document.getElementById('categoryChart').getContext('2d');
    if (categoryChart) categoryChart.destroy();
    categoryChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(d => d.category),
            datasets: [{
                data: data.map(d => d.total),
                backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#20c997']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
}

function updateHoursChart(data) {
    const ctx = document.getElementById('hoursChart').getContext('2d');
    if (hoursChart) hoursChart.destroy();
    hoursChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(d => `${d.hour}h`),
            datasets: [{
                label: 'Vendas',
                data: data.map(d => d.count),
                backgroundColor: '#198754'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });
}

function updateTopProducts(products) {
    const tbody = document.getElementById('topProductsTable');
    if (products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">Sem dados</td></tr>';
        return;
    }
    tbody.innerHTML = products.slice(0, 10).map(p => `
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <img src="${normalizeExternalUrl(p.thumbnail) || '/icons/icon-72x72.png'}" class="rounded me-2" width="40" height="40" style="object-fit:cover">
                    <span class="text-truncate" style="max-width:200px">${p.title}</span>
                </div>
            </td>
            <td class="text-center">${p.quantity}</td>
            <td class="text-end">${formatCurrency(p.revenue)}</td>
        </tr>
    `).join('');
}

loadStatistics();
</script>
