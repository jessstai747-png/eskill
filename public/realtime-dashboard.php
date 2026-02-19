<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mercado Livre Real-Time Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #fff159;
            --secondary-color: #3483fa;
            --success-color: #00a650;
            --warning-color: #ffa500;
            --danger-color: #ff5252;
            --dark-bg: #1a1a1a;
            --card-bg: #2a2a2a;
            --text-light: #ffffff;
            --text-muted: #b0b0b0;
        }

        body {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: var(--text-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .dashboard-header {
            background: linear-gradient(90deg, var(--secondary-color), var(--primary-color));
            padding: 1.5rem 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.3);
            margin-bottom: 2rem;
        }

        .metric-card {
            background: var(--card-bg);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }

        .metric-card:hover::before {
            left: 100%;
        }

        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0.5rem 0;
        }

        .metric-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .metric-change {
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 5px;
        }

        .metric-change.positive {
            background: rgba(0, 165, 80, 0.2);
            color: var(--success-color);
        }

        .metric-change.negative {
            background: rgba(255, 82, 82, 0.2);
            color: var(--danger-color);
        }

        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(0, 255, 0, 0.1);
            border: 1px solid rgba(0, 255, 0, 0.3);
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background: var(--success-color);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
            100% { opacity: 1; transform: scale(1); }
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .status-online { background: var(--success-color); }
        .status-offline { background: var(--danger-color); }
        .status-warning { background: var(--warning-color); }

        .data-table {
            background: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
        }

        .data-table table {
            margin: 0;
            color: var(--text-light);
        }

        .data-table th {
            background: rgba(255,255,255,0.05);
            border-bottom: 2px solid var(--primary-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            padding: 1rem;
        }

        .data-table td {
            border-color: rgba(255,255,255,0.05);
            padding: 0.75rem 1rem;
            vertical-align: middle;
        }

        .data-table tbody tr:hover {
            background: rgba(255,255,255,0.05);
        }

        .refresh-btn {
            background: var(--secondary-color);
            border: none;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .refresh-btn:hover {
            background: #2873e8;
            transform: translateY(-2px);
        }

        .refresh-btn.spinning {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .chart-container {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            height: 300px;
            position: relative;
        }

        .websocket-status {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .websocket-status.connected {
            background: rgba(0, 165, 80, 0.2);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .websocket-status.disconnected {
            background: rgba(255, 82, 82, 0.2);
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
        }

        .websocket-status.connecting {
            background: rgba(255, 165, 0, 0.2);
            border: 1px solid var(--warning-color);
            color: var(--warning-color);
        }

        .notification-toast {
            position: fixed;
            top: 80px;
            right: 20px;
            min-width: 300px;
            z-index: 1001;
        }

        .subscription-panel {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .subscription-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 0.75rem;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .btn-subscribe {
            background: var(--primary-color);
            color: var(--dark-bg);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-subscribe:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 241, 89, 0.3);
        }

        .btn-subscribe.active {
            background: var(--success-color);
            color: white;
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            z-index: 10;
        }

        .last-updated {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: right;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            .metric-value {
                font-size: 1.8rem;
            }
            
            .websocket-status {
                position: relative;
                top: auto;
                right: auto;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- WebSocket Status -->
    <div id="websocketStatus" class="websocket-status connecting">
        <i class="fas fa-circle"></i>
        <span id="statusText">Conectando...</span>
    </div>

    <!-- Header -->
    <header class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="h2 mb-0">
                        <i class="fab fa-deezer" style="color: var(--primary-color);"></i>
                        Mercado Livre Real-Time Dashboard
                    </h1>
                </div>
                <div class="col-md-6 text-end">
                    <div class="live-indicator">
                        <span class="live-dot"></span>
                        <span>TEMPO REAL</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container-fluid">
        <!-- Subscription Panel -->
        <div class="row">
            <div class="col-12">
                <div class="subscription-panel">
                    <h5 class="mb-3">
                        <i class="fas fa-bell"></i>
                        Assinaturas de Dados em Tempo Real
                    </h5>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="subscription-item">
                                <span><i class="fas fa-box"></i> Meus Itens</span>
                                <button class="btn btn-sm btn-subscribe" data-channel="items">
                                    Assinar
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="subscription-item">
                                <span><i class="fas fa-users"></i> Concorrentes</span>
                                <button class="btn btn-sm btn-subscribe" data-channel="competitors">
                                    Assinar
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="subscription-item">
                                <span><i class="fas fa-chart-line"></i> SEO</span>
                                <button class="btn btn-sm btn-subscribe" data-channel="seo_monitoring">
                                    Assinar
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="subscription-item">
                                <span><i class="fas fa-shopping-cart"></i> Pedidos</span>
                                <button class="btn btn-sm btn-subscribe" data-channel="orders">
                                    Assinar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metrics Row -->
        <div class="row">
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-label">
                        <i class="fas fa-box"></i> Total de Itens
                    </div>
                    <div class="metric-value" id="totalItems">-</div>
                    <div class="metric-change positive" id="itemsChange">
                        <i class="fas fa-arrow-up"></i> 0%
                    </div>
                    <div class="last-updated" id="itemsLastUpdate">
                        Atualizado agora
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-label">
                        <i class="fas fa-eye"></i> Visualizações
                    </div>
                    <div class="metric-value" id="totalViews">-</div>
                    <div class="metric-change positive" id="viewsChange">
                        <i class="fas fa-arrow-up"></i> 0%
                    </div>
                    <div class="last-updated" id="viewsLastUpdate">
                        Atualizado agora
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-label">
                        <i class="fas fa-shopping-cart"></i> Vendas
                    </div>
                    <div class="metric-value" id="totalSales">-</div>
                    <div class="metric-change positive" id="salesChange">
                        <i class="fas fa-arrow-up"></i> 0%
                    </div>
                    <div class="last-updated" id="salesLastUpdate">
                        Atualizado agora
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-label">
                        <i class="fas fa-tachometer-alt"></i> Score SEO
                    </div>
                    <div class="metric-value" id="avgSEOScore">-</div>
                    <div class="metric-change positive" id="seoChange">
                        <i class="fas fa-arrow-up"></i> 0%
                    </div>
                    <div class="last-updated" id="seoLastUpdate">
                        Atualizado agora
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-line"></i> Performance em Tempo Real
                    </h5>
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-pie"></i> Distribuição de Status
                    </h5>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Data Tables Row -->
        <div class="row">
            <div class="col-md-6">
                <div class="data-table">
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-box"></i> Itens Ativos
                            </h5>
                            <button class="btn btn-sm refresh-btn" id="refreshItems">
                                <i class="fas fa-sync-alt"></i> Atualizar
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Título</th>
                                    <th>Preço</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="itemsTableBody">
                                <tr>
                                    <td colspan="4" class="text-center">
                                        <i class="fas fa-spinner fa-spin"></i> Carregando...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="data-table">
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-users"></i> Concorrentes
                            </h5>
                            <button class="btn btn-sm refresh-btn" id="refreshCompetitors">
                                <i class="fas fa-sync-alt"></i> Atualizar
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Preço</th>
                                    <th>Vendas</th>
                                    <th>Score</th>
                                </tr>
                            </thead>
                            <tbody id="competitorsTableBody">
                                <tr>
                                    <td colspan="4" class="text-center">
                                        <i class="fas fa-spinner fa-spin"></i> Carregando...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Notification Container -->
    <div id="notificationContainer"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/realtime-dashboard.js"></script>
</body>
</html>