<?php

declare(strict_types=1);

/**
 * Painel Admin de Gestão de EANs - Versão Moderna
 * Integrado com layouts/modern/app.php
 */

$isAdmin = $_SESSION['is_admin'] ?? false;
$userRole = $_SESSION['user_role'] ?? '';

if (!$isAdmin && $userRole !== 'admin') {
    // Redirect handled by controller/filter ideally, but keeping check here
    header('Location: /dashboard');
    exit;
}
?>

<!-- Custom Styles for Admin EAN -->
<style>
    .admin-ean-wrapper {
        --admin-primary: #FFE600;
        --admin-danger: #dc3545;
        --admin-success: #28a745;
        --admin-card-bg: #16213e;
        --admin-text: #fff;
    }
    
    /* Scoped dark theme for Admin EAN to match EAN Manager look */
    .admin-ean-wrapper .card {
        background: var(--admin-card-bg);
        color: var(--admin-text);
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }

    .admin-ean-wrapper .table {
        color: var(--admin-text);
        border-color: rgba(255,255,255,0.1);
    }
    .admin-ean-wrapper .table thead th {
        border-color: rgba(255,255,255,0.1);
        color: rgba(255,255,255,0.7);
    }
    .admin-ean-wrapper .table td {
        border-color: rgba(255,255,255,0.05);
        vertical-align: middle;
    }
    
    .admin-ean-wrapper .form-control, 
    .admin-ean-wrapper .form-select {
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.2);
        color: #fff;
    }
    .admin-ean-wrapper .form-control:focus, 
    .admin-ean-wrapper .form-select:focus {
        background: rgba(255,255,255,0.15);
        border-color: var(--admin-primary);
        color: #fff;
        box-shadow: 0 0 0 0.25rem rgba(255, 230, 0, 0.25);
    }

    .admin-ean-wrapper .stat-card {
        background: linear-gradient(135deg, var(--admin-card-bg) 0%, #1e3a5f 100%);
        border-radius: 15px;
        padding: 1.5rem;
        text-align: center;
        height: 100%;
    }
    
    .admin-ean-wrapper .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
    }
    .admin-ean-wrapper .stat-card.success .stat-number { color: var(--admin-success); }
    .admin-ean-wrapper .stat-card.warning .stat-number { color: var(--admin-primary); }
    .admin-ean-wrapper .stat-card.danger .stat-number { color: var(--admin-danger); }
    .admin-ean-wrapper .stat-card.info .stat-number { color: #3483FA; }

    .admin-ean-wrapper .import-zone {
        border: 2px dashed rgba(255,255,255,0.3);
        border-radius: 15px;
        padding: 2rem;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .admin-ean-wrapper .import-zone:hover {
        border-color: var(--admin-primary);
        background: rgba(255,230,0,0.1);
    }
    
    .admin-ean-wrapper .nav-pills .nav-link {
        color: var(--bs-body-color);
        border-radius: 10px;
    }
    .admin-ean-wrapper .nav-pills .nav-link.active {
        background: #3483FA;
        color: white;
    }
</style>

<div class="admin-ean-wrapper">
    <!-- Header -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="bi bi-gear text-primary"></i>
            Admin EAN
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="/dashboard/ean" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye"></i> Ver Loja
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card success">
                <i class="bi bi-box-seam fs-3 mb-2 d-block"></i>
                <div class="stat-number" id="stat-available">-</div>
                <div class="small opacity-75">EANs Disponíveis</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card warning">
                <i class="bi bi-clock-history fs-3 mb-2 d-block"></i>
                <div class="stat-number" id="stat-reserved">-</div>
                <div class="small opacity-75">Reservados</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card info">
                <i class="bi bi-check-circle fs-3 mb-2 d-block"></i>
                <div class="stat-number" id="stat-sold">-</div>
                <div class="small opacity-75">Vendidos</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <i class="bi bi-currency-dollar fs-3 mb-2 d-block"></i>
                <div class="stat-number text-success" id="stat-revenue">R$ -</div>
                <div class="small opacity-75">Receita Total</div>
            </div>
        </div>
    </div>

    <!-- Navegação -->
    <ul class="nav nav-pills mb-4" id="adminTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="dashboard-tab-link" data-bs-toggle="pill" href="#dashboard-tab" role="tab">
                <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="inventory-tab-link" data-bs-toggle="pill" href="#inventory-tab" role="tab">
                <i class="bi bi-box-seam me-1"></i>Inventário
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="purchases-tab-link" data-bs-toggle="pill" href="#purchases-tab" role="tab">
                <i class="bi bi-cart me-1"></i>Vendas
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="import-tab-link" data-bs-toggle="pill" href="#import-tab" role="tab">
                <i class="bi bi-upload me-1"></i>Importar
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="config-tab-link" data-bs-toggle="pill" href="#config-tab" role="tab">
                <i class="bi bi-gear me-1"></i>Config
            </a>
        </li>
    </ul>

    <!-- Conteúdo -->
    <div class="tab-content">
        <!-- Dashboard -->
        <div class="tab-pane fade show active" id="dashboard-tab" role="tabpanel">
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-transparent border-bottom border-light">
                            <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Vendas Recentes</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="salesChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-transparent border-bottom border-light">
                            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Últimas Vendas</h5>
                        </div>
                        <div class="card-body">
                            <div id="recent-sales">
                                <div class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventário -->
        <div class="tab-pane fade" id="inventory-tab" role="tabpanel">
            <div class="card">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Inventário de EANs</h5>
                    <select class="form-select form-select-sm w-auto" id="inventory-filter">
                        <option value="">Todos</option>
                        <option value="available">Disponíveis</option>
                        <option value="reserved">Reservados</option>
                        <option value="sold">Vendidos</option>
                    </select>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>EAN</th>
                                    <th>Status</th>
                                    <th>Lote</th>
                                    <th>Custo</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody id="inventory-table">
                                <tr><td colspan="5" class="text-center py-3">Carregando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Importar -->
        <div class="tab-pane fade" id="import-tab" role="tabpanel">
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Importar Arquivo</h5>
                        </div>
                        <div class="card-body">
                            <div class="import-zone" id="import-zone" onclick="document.getElementById('import-file').click()">
                                <i class="bi bi-cloud-upload fs-1 mb-3 d-block opacity-50"></i>
                                <p class="mb-1">Clique para selecionar arquivo</p>
                                <input type="file" id="import-file" class="d-none" accept=".txt,.csv">
                            </div>
                            <!-- Simple inputs for batch import details -->
                            <button class="btn btn-primary w-100 mt-3" onclick="alert('Funcionalidade de importação simulada')">
                                <i class="bi bi-upload"></i> Importar
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Manual Add -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                         <div class="card-header bg-transparent">
                            <h5 class="mb-0">Adicionar Manualmente</h5>
                        </div>
                        <div class="card-body">
                             <textarea class="form-control mb-3" rows="5" placeholder="EANs por linha..."></textarea>
                             <button class="btn btn-primary w-100">Adicionar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Config -->
        <div class="tab-pane fade" id="config-tab" role="tabpanel">
             <div class="row">
                 <div class="col-lg-6">
                     <div class="card">
                         <div class="card-header bg-transparent"><h5 class="mb-0">Mercado Pago</h5></div>
                         <div class="card-body">
                             <div class="mb-3">
                                <label>Access Token</label>
                                <input type="password" class="form-control">
                             </div>
                             <button class="btn btn-primary">Salvar</button>
                         </div>
                     </div>
                 </div>
             </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script nonce="<?= CSP_NONCE ?>">
    document.addEventListener('DOMContentLoaded', function() {
        loadDashboard();
        
        // Tabs events
        const tabs = document.querySelectorAll('#adminTabs button[data-bs-toggle="pill"]');
        tabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => {
                const target = e.target.getAttribute('href');
                if (target === '#inventory-tab') loadInventory();
            });
        });
        
        document.getElementById('inventory-filter').addEventListener('change', () => loadInventory());
    });

    async function loadDashboard() {
        try {
            // Mock data or fetch from API
            document.getElementById('stat-available').textContent = '142';
            document.getElementById('stat-reserved').textContent = '5';
            document.getElementById('stat-sold').textContent = '890';
            document.getElementById('stat-revenue').textContent = 'R$ 12.450,00';
            
            // Recent sales mock
            document.getElementById('recent-sales').innerHTML = `
                <div class="d-flex justify-content-between py-2 border-bottom border-light">
                    <div><strong>Seller A</strong><br><small class="opacity-75">50 EANs</small></div>
                    <div class="text-end text-success">R$ 150,00</div>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom border-light">
                    <div><strong>Seller B</strong><br><small class="opacity-75">10 EANs</small></div>
                    <div class="text-end text-success">R$ 45,00</div>
                </div>
            `;
            
            renderChart();
        } catch (e) {
            console.error(e);
        }
    }

    function renderChart() {
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai'],
                datasets: [{
                    label: 'Receita',
                    data: [1200, 1900, 3000, 5000, 4500],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#fff' } } },
                scales: {
                    x: { ticks: { color: '#adb5bd' }, grid: { color: 'rgba(255,255,255,0.1)' } },
                    y: { ticks: { color: '#adb5bd' }, grid: { color: 'rgba(255,255,255,0.1)' } }
                }
            }
        });
    }

    async function loadInventory() {
        const tbody = document.getElementById('inventory-table');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Carregando...</td></tr>';
        
        // Mock inventory loading
        setTimeout(() => {
            tbody.innerHTML = `
                <tr>
                    <td><code>789123456001</code></td>
                    <td><span class="badge bg-success">Disponível</span></td>
                    <td>Lote 001</td>
                    <td>R$ 0.15</td>
                    <td>20/12/2023</td>
                </tr>
                <tr>
                    <td><code>789123456002</code></td>
                    <td><span class="badge bg-secondary">Vendido</span></td>
                    <td>Lote 001</td>
                    <td>R$ 0.15</td>
                    <td>21/12/2023</td>
                </tr>
            `;
        }, 500);
    }
</script>
