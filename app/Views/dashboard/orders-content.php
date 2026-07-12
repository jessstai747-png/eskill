<style>
    .kpi-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
    }
    .kpi-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .kpi-icon.revenue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .kpi-icon.orders { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .kpi-icon.shipped { background: linear-gradient(135deg, #3483fa 0%, #2196f3 100%); }
    .kpi-icon.delivered { background: linear-gradient(135deg, #00a650 0%, #4caf50 100%); }
    .kpi-icon.pending { background: linear-gradient(135deg, #f7dc6f 0%, #f39c12 100%); }
    .kpi-icon.cancelled { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); }
    .kpi-value { font-size: 1.5rem; font-weight: bold; }
    .kpi-label { color: #666; font-size: 0.85rem; }
    .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .status-paid { background: #d4edda; color: #155724; }
    .status-confirmed { background: #cce5ff; color: #004085; }
    .status-ready-to-ship { background: #fff3cd; color: #856404; }
    .status-shipped { background: #d1ecf1; color: #0c5460; }
    .status-delivered { background: #c3e6cb; color: #155724; }
    .status-cancelled { background: #f8d7da; color: #721c24; }
    .order-row:hover { background-color: #f8f9fa; }
    .order-id { font-weight: 600; color: var(--ml-blue, #3483FA); }
    .chart-container { position: relative; height: 200px; }
    .filters-card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); }
    .filter-btn.active { background-color: var(--ml-blue, #3483FA); color: white; }
    .export-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white; }
    .export-btn:hover { background: linear-gradient(135deg, #5a6fd6 0%, #6a4190 100%); color: white; }
    .modal-order-header { background: linear-gradient(135deg, #3483fa 0%, #2d3748 100%); color: white; }
    .order-detail-section { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
    .order-detail-section h6 { font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
    .product-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; background: #e9ecef; }
</style>

<!-- Page Header -->
<?php


include __DIR__ . '/../layouts/modern/partials/page-header.php'; ?>

<!-- Export Actions -->
<div class="d-flex justify-content-end mb-4">
    <div class="dropdown">
        <button class="btn export-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-download me-1"></i>Exportar
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="#" onclick="exportOrders('csv')"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Excel (CSV)</a></li>
            <li><a class="dropdown-item" href="#" onclick="exportOrders('pdf')"><i class="bi bi-file-earmark-pdf me-2"></i>PDF</a></li>
        </ul>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="kpi-icon revenue text-white me-3"><i class="bi bi-currency-dollar"></i></div>
                    <div>
                        <div class="kpi-value" id="kpi-revenue">R$ 0</div>
                        <div class="kpi-label">Faturamento</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="kpi-icon orders text-white me-3"><i class="bi bi-bag-check"></i></div>
                    <div>
                        <div class="kpi-value" id="kpi-total">0</div>
                        <div class="kpi-label">Total</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="kpi-icon pending text-white me-3"><i class="bi bi-hourglass-split"></i></div>
                    <div>
                        <div class="kpi-value" id="kpi-pending">0</div>
                        <div class="kpi-label">Pendentes</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="kpi-icon shipped text-white me-3"><i class="bi bi-truck"></i></div>
                    <div>
                        <div class="kpi-value" id="kpi-shipped">0</div>
                        <div class="kpi-label">Enviados</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="kpi-icon delivered text-white me-3"><i class="bi bi-check-circle"></i></div>
                    <div>
                        <div class="kpi-value" id="kpi-delivered">0</div>
                        <div class="kpi-label">Entregues</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="kpi-icon cancelled text-white me-3"><i class="bi bi-x-circle"></i></div>
                    <div>
                        <div class="kpi-value" id="kpi-cancelled">0</div>
                        <div class="kpi-label">Cancelados</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card filters-card h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Vendas (7 dias)</h6></div>
            <div class="card-body"><div class="chart-container"><canvas id="salesChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card filters-card h-100">
            <div class="card-header bg-transparent"><h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Status</h6></div>
            <div class="card-body"><div class="chart-container"><canvas id="statusChart"></canvas></div></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card filters-card mb-4">
    <div class="card-body">
        <div class="row align-items-end g-2">
            <div class="col-md-2">
                <label class="form-label small">Conta</label>
                <select class="form-select form-select-sm" id="filter-account"><option value="">Todas</option></select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select class="form-select form-select-sm" id="filter-status">
                    <option value="">Todos</option>
                    <option value="paid">Pago</option>
                    <option value="confirmed">Confirmado</option>
                    <option value="ready_to_ship">Pronto p/ Enviar</option>
                    <option value="shipped">Enviado</option>
                    <option value="delivered">Entregue</option>
                    <option value="cancelled">Cancelado</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Data Inicial</label>
                <input type="date" class="form-control form-control-sm" id="filter-date-from">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Data Final</label>
                <input type="date" class="form-control form-control-sm" id="filter-date-to">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Buscar</label>
                <input type="text" class="form-control form-control-sm" id="filter-search" placeholder="ID ou comprador...">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary btn-sm w-100" onclick="loadOrders()"><i class="bi bi-search me-1"></i>Filtrar</button>
            </div>
        </div>
        <div class="mt-2">
            <span class="me-2 small text-muted">Filtros rápidos:</span>
            <button class="btn btn-sm btn-outline-secondary me-1 filter-btn" data-days="7">7 dias</button>
            <button class="btn btn-sm btn-outline-secondary me-1 filter-btn active" data-days="30">30 dias</button>
            <button class="btn btn-sm btn-outline-secondary me-1 filter-btn" data-days="90">90 dias</button>
            <button class="btn btn-sm btn-outline-secondary filter-btn" data-days="365">1 ano</button>
        </div>
    </div>
</div>

<!-- Orders Table -->
<div class="card filters-card">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>Lista de Pedidos</h6>
        <span class="badge bg-primary" id="orders-count">0 pedidos</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Comprador</th>
                        <th>Itens</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Conta</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="orders-tbody">
                    <tr><td colspan="8" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-transparent">
        <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted small">Mostrando <span id="showing-from">0</span>-<span id="showing-to">0</span> de <span id="total-orders">0</span></div>
            <nav><ul class="pagination pagination-sm mb-0" id="pagination"></ul></nav>
        </div>
    </div>
</div>

<!-- Order Detail Modal -->
<div class="modal fade" id="orderModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header modal-order-header">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Pedido <span id="modal-order-id"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modal-order-body">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="printOrder()"><i class="bi bi-printer me-1"></i>Imprimir</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

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

let allOrders = [], currentPage = 1, ordersPerPage = 20, salesChart = null, statusChart = null;

document.addEventListener('DOMContentLoaded', function() {
    initializeDateFilters();
    loadAccounts();
    loadOrders();
    
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const days = parseInt(this.dataset.days);
            const dateFrom = new Date();
            dateFrom.setDate(dateFrom.getDate() - days);
            document.getElementById('filter-date-from').value = dateFrom.toISOString().split('T')[0];
            document.getElementById('filter-date-to').value = new Date().toISOString().split('T')[0];
            loadOrders();
        });
    });
    
    document.getElementById('filter-search').addEventListener('keypress', e => { if (e.key === 'Enter') loadOrders(); });
});

function initializeDateFilters() {
    const today = new Date(), thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);
    document.getElementById('filter-date-from').value = thirtyDaysAgo.toISOString().split('T')[0];
    document.getElementById('filter-date-to').value = today.toISOString().split('T')[0];
}

function loadAccounts() {
    requestJson('/api/auth/accounts').then(data => {
        // API retorna { accounts: [...], total: N }
        const accounts = Array.isArray(data) ? data : (data.accounts || []);
        const select = document.getElementById('filter-account');
        accounts.forEach(acc => {
            if (acc.status === 'active') select.innerHTML += `<option value="${acc.id}">${acc.nickname || 'Conta ' + acc.id}</option>`;
        });
    }).catch(() => {});
}

function loadOrders() {
    const params = new URLSearchParams({
        limit: 200,
        allow_local_cache: 'true'
    });

    ['account_id:filter-account', 'status:filter-status', 'date_from:filter-date-from', 'date_to:filter-date-to'].forEach(p => {
        const [key, id] = p.split(':');
        const val = document.getElementById(id)?.value;
        if (val) params.append(key, val);
    });

    const search = document.getElementById('filter-search')?.value || '';
    
    const tbody = document.getElementById('orders-tbody');
    if (!tbody) {
        console.error('Elemento orders-tbody não encontrado');
        return;
    }
    
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5"><div class="spinner-border text-primary"></div><div class="mt-2">Carregando pedidos...</div></td></tr>';
    
    requestJson(`/api/orders/all?${params.toString()}`)
        .then(data => {
            console.log('Orders data:', data); // Debug
            
            if (data.error) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4">
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        ${data.error}
                        ${data.debug ? '<div class="small mt-2">Debug: ' + JSON.stringify(data.debug) + '</div>' : ''}
                    </div>
                </td></tr>`;
                return;
            }
            
            allOrders = data.results || [];
            console.log('Total orders loaded:', allOrders.length); // Debug
            
            if (search) {
                const s = search.toLowerCase();
                allOrders = allOrders.filter(o => String(o.id).includes(s) || (o.buyer?.nickname || '').toLowerCase().includes(s));
            }
            
            updateKPIs();
            updateCharts();
            renderOrders();
        })
        .catch(error => {
            console.error('Erro ao carregar pedidos:', error);
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4">
                <div class="alert alert-danger mb-0">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    Erro ao carregar pedidos. Tente novamente.
                    <div class="small mt-2">${error.message}</div>
                </div>
            </td></tr>`;
        });
}

function updateKPIs() {
    let revenue = 0, pending = 0, shipped = 0, delivered = 0, cancelled = 0;
    allOrders.forEach(o => {
        revenue += parseFloat(o.total_amount || 0);
        const s = (o.status || '').toLowerCase();
        if (['paid', 'confirmed', 'ready_to_ship'].includes(s)) pending++;
        else if (s === 'shipped') shipped++;
        else if (s === 'delivered') delivered++;
        else if (s === 'cancelled') cancelled++;
    });
    document.getElementById('kpi-revenue').textContent = formatCurrency(revenue);
    document.getElementById('kpi-total').textContent = allOrders.length;
    document.getElementById('kpi-pending').textContent = pending;
    document.getElementById('kpi-shipped').textContent = shipped;
    document.getElementById('kpi-delivered').textContent = delivered;
    document.getElementById('kpi-cancelled').textContent = cancelled;
}

function updateCharts() {
    const salesByDay = {}, today = new Date();
    for (let i = 6; i >= 0; i--) {
        const d = new Date(today); d.setDate(d.getDate() - i);
        salesByDay[d.toISOString().split('T')[0]] = { orders: 0, revenue: 0 };
    }
    allOrders.forEach(o => {
        const od = new Date(o.date_created).toISOString().split('T')[0];
        if (salesByDay[od]) { salesByDay[od].orders++; salesByDay[od].revenue += parseFloat(o.total_amount || 0); }
    });
    
    const labels = Object.keys(salesByDay).map(d => new Date(d).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }));
    const ordersData = Object.values(salesByDay).map(d => d.orders);
    const revenueData = Object.values(salesByDay).map(d => d.revenue);
    
    if (salesChart) salesChart.destroy();
    salesChart = new Chart(document.getElementById('salesChart').getContext('2d'), {
        type: 'line',
        data: { labels, datasets: [
            { label: 'Pedidos', data: ordersData, borderColor: '#3483fa', backgroundColor: 'rgba(52,131,250,0.1)', fill: true, tension: 0.4, yAxisID: 'y' },
            { label: 'Faturamento', data: revenueData, borderColor: '#00a650', borderDash: [5, 5], tension: 0.4, yAxisID: 'y1' }
        ]},
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true }, y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } } } }
    });
    
    const statusCounts = {};
    allOrders.forEach(o => { const s = o.status || 'unknown'; statusCounts[s] = (statusCounts[s] || 0) + 1; });
    
    if (statusChart) statusChart.destroy();
    statusChart = new Chart(document.getElementById('statusChart').getContext('2d'), {
        type: 'doughnut',
        data: { labels: Object.keys(statusCounts).map(getStatusLabel), datasets: [{ data: Object.values(statusCounts), backgroundColor: Object.keys(statusCounts).map(getStatusColor), borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
    });
}

function renderOrders() {
    const start = (currentPage - 1) * ordersPerPage, end = start + ordersPerPage;
    const page = allOrders.slice(start, end);
    
    if (page.length === 0) {
        document.getElementById('orders-tbody').innerHTML = '<tr><td colspan="8" class="text-center py-5"><i class="bi bi-inbox text-muted" style="font-size:3rem"></i><p class="text-muted mt-2 mb-0">Nenhum pedido</p></td></tr>';
        return;
    }
    
    document.getElementById('orders-tbody').innerHTML = page.map(o => {
        const date = o.date_created ? new Date(o.date_created).toLocaleDateString('pt-BR') : '-';
        const time = o.date_created ? new Date(o.date_created).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
        const buyer = o.buyer?.nickname || o.buyer?.first_name || 'Comprador';
        const status = o.status || 'unknown';
        return `<tr class="order-row">
            <td><span class="order-id">#${o.id}</span></td>
            <td><div>${date}</div><small class="text-muted">${time}</small></td>
            <td><span>${escapeHtml(buyer)}</span></td>
            <td><span class="badge bg-light text-dark">${o.order_items?.length || 0}</span></td>
            <td><strong>${formatCurrency(o.total_amount || 0)}</strong></td>
            <td><span class="status-badge status-${status.toLowerCase().replace('_', '-')}">${getStatusLabel(status)}</span></td>
            <td><small class="text-muted">${escapeHtml(o.account_nickname || '')}</small></td>
            <td><button class="btn btn-sm btn-outline-primary" onclick="viewOrder(${o.id})"><i class="bi bi-eye"></i></button></td>
        </tr>`;
    }).join('');
    
    document.getElementById('showing-from').textContent = start + 1;
    document.getElementById('showing-to').textContent = Math.min(end, allOrders.length);
    document.getElementById('total-orders').textContent = allOrders.length;
    document.getElementById('orders-count').textContent = `${allOrders.length} pedidos`;
    renderPagination();
}

function renderPagination() {
    const totalPages = Math.ceil(allOrders.length / ordersPerPage);
    if (totalPages <= 1) { document.getElementById('pagination').innerHTML = ''; return; }
    
    let html = `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="goToPage(${currentPage - 1})"><i class="bi bi-chevron-left"></i></a></li>`;
    for (let i = 1; i <= Math.min(totalPages, 5); i++) html += `<li class="page-item ${currentPage === i ? 'active' : ''}"><a class="page-link" href="#" onclick="goToPage(${i})">${i}</a></li>`;
    if (totalPages > 5) html += `<li class="page-item disabled"><span class="page-link">...</span></li><li class="page-item"><a class="page-link" href="#" onclick="goToPage(${totalPages})">${totalPages}</a></li>`;
    html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" onclick="goToPage(${currentPage + 1})"><i class="bi bi-chevron-right"></i></a></li>`;
    document.getElementById('pagination').innerHTML = html;
}

function goToPage(page) {
    const totalPages = Math.ceil(allOrders.length / ordersPerPage);
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderOrders();
}

function viewOrder(orderId) {
    document.getElementById('modal-order-id').textContent = '#' + orderId;
    document.getElementById('modal-order-body').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    new bootstrap.Modal(document.getElementById('orderModal')).show();
    
    requestJson(`/api/orders/${orderId}?allow_local_cache=true`).then(order => {
        const buyer = order.buyer || {}, shipping = order.shipping || {}, items = order.order_items || [];
        document.getElementById('modal-order-body').innerHTML = `
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="order-detail-section">
                        <h6><i class="bi bi-info-circle"></i>Informações</h6>
                        <table class="table table-sm mb-0">
                            <tr><td class="text-muted">Status:</td><td><span class="status-badge status-${(order.status || 'unknown').toLowerCase()}">${getStatusLabel(order.status)}</span></td></tr>
                            <tr><td class="text-muted">Data:</td><td>${order.date_created ? new Date(order.date_created).toLocaleString('pt-BR') : '-'}</td></tr>
                            <tr><td class="text-muted">Total:</td><td><strong class="text-primary">${formatCurrency(order.total_amount || 0)}</strong></td></tr>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="order-detail-section">
                        <h6><i class="bi bi-person"></i>Comprador</h6>
                        <table class="table table-sm mb-0">
                            <tr><td class="text-muted">Nome:</td><td>${escapeHtml(buyer.first_name || '')} ${escapeHtml(buyer.last_name || '')}</td></tr>
                            <tr><td class="text-muted">Nickname:</td><td>${escapeHtml(buyer.nickname || '-')}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="order-detail-section">
                <h6><i class="bi bi-box"></i>Itens (${items.length})</h6>
                <table class="table table-sm">
                    <thead><tr><th></th><th>Produto</th><th>Qtd</th><th>Preço</th><th>Total</th></tr></thead>
                    <tbody>${items.map(i => `<tr>
                        <td><img src="${normalizeExternalUrl(i.item?.thumbnail) || ''}" class="product-thumb" onerror="this.style.display='none'"></td>
                        <td><div class="fw-semibold">${escapeHtml(i.item?.title || 'Produto')}</div><small class="text-muted">${i.item?.id || ''}</small></td>
                        <td>${i.quantity || 1}</td>
                        <td>${formatCurrency(i.unit_price || 0)}</td>
                        <td><strong>${formatCurrency((i.unit_price || 0) * (i.quantity || 1))}</strong></td>
                    </tr>`).join('')}</tbody>
                </table>
            </div>
            <div class="order-detail-section">
                <h6><i class="bi bi-truck"></i>Envio</h6>
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted">Status:</td><td>${shipping.status || '-'}</td></tr>
                    <tr><td class="text-muted">Tipo:</td><td>${shipping.shipment_type || '-'}</td></tr>
                </table>
            </div>
        `;
    }).catch(() => {
        document.getElementById('modal-order-body').innerHTML = '<div class="alert alert-danger">Erro ao carregar</div>';
    });
}

function exportOrders(format) {
    const params = new URLSearchParams({
        account_id: document.getElementById('filter-account').value,
        status: document.getElementById('filter-status').value,
        date_from: document.getElementById('filter-date-from').value,
        date_to: document.getElementById('filter-date-to').value
    });
    
    if (format === 'pdf') {
        window.open(`/api/pdf/orders?${params}`, '_blank');
    } else {
        const csv = 'ID;Data;Comprador;Itens;Total;Status;Conta\n' + allOrders.map(o => [o.id, o.date_created ? new Date(o.date_created).toLocaleString('pt-BR') : '', o.buyer?.nickname || '', o.order_items?.length || 0, (o.total_amount || 0).toFixed(2).replace('.', ','), getStatusLabel(o.status), o.account_nickname || ''].map(c => `"${String(c).replace(/"/g, '""')}"`).join(';')).join('\n');
        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `pedidos_${new Date().toISOString().split('T')[0]}.csv`;
        link.click();
    }
}

function printOrder() {
    const w = window.open('', '_blank');
    const orderId = document.getElementById('modal-order-id').textContent;
    const orderBody = document.getElementById('modal-order-body').innerHTML;
    w.document.write('<html><head><title>Pedido ' + orderId + '</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{padding:20px}.order-detail-section{background:#f8f9fa;border-radius:8px;padding:15px;margin-bottom:15px}</style></head><body><h2>Pedido ' + orderId + '</h2>' + orderBody + '<script>window.onload=function(){window.print()}<\/script></body></html>');
    w.document.close();
}

function formatCurrency(v) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v); }
function getStatusLabel(s) { return { paid: 'Pago', confirmed: 'Confirmado', ready_to_ship: 'Pronto p/ Enviar', shipped: 'Enviado', delivered: 'Entregue', cancelled: 'Cancelado' }[(s || '').toLowerCase()] || s; }
function getStatusColor(s) { return { paid: '#28a745', confirmed: '#17a2b8', ready_to_ship: '#ffc107', shipped: '#007bff', delivered: '#28a745', cancelled: '#dc3545' }[(s || '').toLowerCase()] || '#6c757d'; }
function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; }
</script>
