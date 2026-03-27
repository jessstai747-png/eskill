<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análise de Marca AWA - Mercado Livre Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #FFE600;
            --primary-dark: #E6CF00;
            --secondary: #3483FA;
            --dark: #333333;
            --light-bg: #F5F5F5;
            --success: #00A650;
            --warning: #FF7733;
            --danger: #F23D4F;
        }

        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .brand-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .brand-title {
            color: var(--dark);
            font-weight: 800;
            font-size: 2.5rem;
        }

        .brand-subtitle {
            color: rgba(0, 0, 0, 0.7);
            font-size: 1.1rem;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dark-card {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0.5rem;
        }

        .health-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
        }

        .health-excellent {
            background: var(--success);
            color: white;
        }

        .health-good {
            background: #4CAF50;
            color: white;
        }

        .health-fair {
            background: var(--warning);
            color: white;
        }

        .health-poor {
            background: #FF5722;
            color: white;
        }

        .health-critical {
            background: var(--danger);
            color: white;
        }

        .score-ring {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: conic-gradient(var(--primary) calc(var(--score) * 1%), rgba(255, 255, 255, 0.1) 0);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        .score-ring-inner {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .score-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .score-label {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
        }

        .gap-item,
        .inconsistency-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid var(--warning);
        }

        .inconsistency-item {
            border-left-color: var(--danger);
        }

        .recommendation-card {
            background: linear-gradient(135deg, rgba(52, 131, 250, 0.2) 0%, rgba(52, 131, 250, 0.1) 100%);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--secondary);
        }

        .priority-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-weight: 600;
        }

        .priority-1 {
            background: var(--danger);
            color: white;
        }

        .priority-2 {
            background: var(--warning);
            color: white;
        }

        .priority-3 {
            background: var(--secondary);
            color: white;
        }

        .priority-4 {
            background: #6c757d;
            color: white;
        }

        .seller-row {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .seller-rank {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--primary);
            color: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 1rem;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .spinner-brand {
            width: 80px;
            height: 80px;
            border: 5px solid rgba(255, 230, 0, 0.2);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .loading-text {
            color: white;
            margin-top: 1.5rem;
            font-size: 1.2rem;
        }

        .btn-awa {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--dark);
            font-weight: 600;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-awa:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 230, 0, 0.4);
            color: var(--dark);
        }

        .table-dark-custom {
            background: transparent;
        }

        .table-dark-custom thead th {
            background: rgba(0, 0, 0, 0.3);
            color: var(--primary);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .table-dark-custom tbody td {
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.9);
            border-color: rgba(255, 255, 255, 0.05);
        }

        .nav-pills .nav-link {
            color: rgba(255, 255, 255, 0.7);
            border-radius: 8px;
        }

        .nav-pills .nav-link.active {
            background: var(--primary);
            color: var(--dark);
        }

        .export-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .export-btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="spinner-brand"></div>
        <div class="loading-text">Analisando marca AWA...</div>
        <div class="text-muted small mt-2" id="loadingProgress">Coletando dados do Mercado Livre</div>
    </div>

    <!-- Header -->
    <header class="brand-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="brand-title">
                        <i class="fas fa-motorcycle me-2"></i>
                        Análise de Marca AWA
                    </h1>
                    <p class="brand-subtitle mb-0">
                        Análise completa de anúncios, lacunas de dados e consistência de marca no Mercado Livre
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="/dashboard" class="btn btn-outline-dark me-2">
                        <i class="fas fa-arrow-left me-1"></i> Dashboard
                    </a>
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-download me-1"></i> Exportar
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" onclick="exportCSV()"><i class="fas fa-file-csv me-2"></i>CSV</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportJSON()"><i class="fas fa-file-code me-2"></i>JSON</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportPDF()"><i class="fas fa-file-pdf me-2"></i>PDF</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="#" onclick="exportFixList()"><i class="fas fa-wrench me-2"></i>Lista de Correções</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container pb-5">
        <!-- Filtros -->
        <div class="dark-card mb-4">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-light">Categorias</label>
                    <select id="categoryFilter" class="form-select" multiple>
                        <option value="MLB1051">Motos</option>
                        <option value="MLB214858" selected>Acessórios para Motos</option>
                        <option value="MLB5750">Peças de Motos</option>
                        <option value="MLB1747">Acessórios para Veículos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-light">Máximo de Resultados</label>
                    <select id="maxResults" class="form-select">
                        <option value="100">100 itens</option>
                        <option value="250">250 itens</option>
                        <option value="500" selected>500 itens</option>
                        <option value="1000">1000 itens</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-light">Tipo de Análise</label>
                    <select id="analysisType" class="form-select">
                        <option value="full">Completa</option>
                        <option value="quick">Rápida</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button id="runAnalysis" class="btn btn-awa w-100">
                        <i class="fas fa-search me-1"></i> Analisar
                    </button>
                </div>
            </div>
        </div>

        <!-- Resultados -->
        <div id="resultsSection" style="display: none;">
            <!-- KPIs -->
            <div class="row mb-4" id="kpiSection">
                <div class="col-md-3">
                    <div class="stat-card dark-card">
                        <div class="stat-value" id="totalListings">0</div>
                        <div class="stat-label">Total de Anúncios</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card dark-card">
                        <div class="stat-value text-success" id="withBrand">0</div>
                        <div class="stat-label">Com Marca</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card dark-card">
                        <div class="stat-value text-warning" id="withoutBrand">0</div>
                        <div class="stat-label">Sem Marca</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card dark-card">
                        <div class="stat-value text-danger" id="wrongBrand">0</div>
                        <div class="stat-label">Marca Incorreta</div>
                    </div>
                </div>
            </div>

            <!-- Score e Saúde -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="dark-card text-center h-100">
                        <h5 class="mb-4">Score de Consistência</h5>
                        <div class="score-ring" id="consistencyRing" style="--score: 0">
                            <div class="score-ring-inner">
                                <div class="score-value" id="consistencyScore">0%</div>
                                <div class="score-label">Consistência</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dark-card text-center h-100">
                        <h5 class="mb-4">Saúde da Marca</h5>
                        <div class="score-ring" id="healthRing" style="--score: 0">
                            <div class="score-ring-inner">
                                <div class="score-value" id="healthScore">0</div>
                                <div class="score-label">Pontos</div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span id="healthBadge" class="health-badge health-fair">Calculando...</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dark-card h-100">
                        <h5 class="mb-3">Problemas Detectados</h5>
                        <div id="issuesList">
                            <div class="text-muted">Aguardando análise...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs de Conteúdo -->
            <ul class="nav nav-pills mb-4" id="resultTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="gaps-tab" data-bs-toggle="pill" data-bs-target="#gaps" type="button">
                        <i class="fas fa-exclamation-triangle me-1"></i> Lacunas <span class="badge bg-warning text-dark" id="gapsCount">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="inconsistencies-tab" data-bs-toggle="pill" data-bs-target="#inconsistencies" type="button">
                        <i class="fas fa-times-circle me-1"></i> Inconsistências <span class="badge bg-danger" id="inconsistenciesCount">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="alerts-tab" data-bs-toggle="pill" data-bs-target="#alerts" type="button">
                        <i class="fas fa-bell me-1"></i> Alertas <span class="badge bg-info" id="alertsCount">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sellers-tab" data-bs-toggle="pill" data-bs-target="#sellers" type="button">
                        <i class="fas fa-store me-1"></i> Vendedores <span class="badge bg-secondary" id="sellersCount">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pricing-tab" data-bs-toggle="pill" data-bs-target="#pricing" type="button">
                        <i class="fas fa-dollar-sign me-1"></i> Preços
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="shipping-tab" data-bs-toggle="pill" data-bs-target="#shipping" type="button">
                        <i class="fas fa-truck me-1"></i> Frete
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="competitors-tab" data-bs-toggle="pill" data-bs-target="#competitors" type="button">
                        <i class="fas fa-balance-scale me-1"></i> Concorrência
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="trends-tab" data-bs-toggle="pill" data-bs-target="#trends" type="button">
                        <i class="fas fa-chart-line me-1"></i> Tendências
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="opportunities-tab" data-bs-toggle="pill" data-bs-target="#opportunities" type="button">
                        <i class="fas fa-rocket me-1"></i> Oportunidades
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="recommendations-tab" data-bs-toggle="pill" data-bs-target="#recommendations" type="button">
                        <i class="fas fa-lightbulb me-1"></i> Recomendações
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="resultTabsContent">
                <!-- Lacunas -->
                <div class="tab-pane fade show active" id="gaps" role="tabpanel">
                    <div class="dark-card">
                        <h5 class="mb-3">Anúncios com Lacunas de Dados</h5>
                        <div id="gapsList">
                            <div class="text-muted">Nenhuma lacuna encontrada</div>
                        </div>
                    </div>
                </div>

                <!-- Inconsistências -->
                <div class="tab-pane fade" id="inconsistencies" role="tabpanel">
                    <div class="dark-card">
                        <h5 class="mb-3">Inconsistências na Marca</h5>
                        <div id="inconsistenciesList">
                            <div class="text-muted">Nenhuma inconsistência encontrada</div>
                        </div>
                    </div>
                </div>

                <!-- Alertas -->
                <div class="tab-pane fade" id="alerts" role="tabpanel">
                    <div class="dark-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Alertas e Notificações</h5>
                            <button class="btn btn-sm btn-outline-light" onclick="loadAlerts()">
                                <i class="fas fa-sync-alt me-1"></i> Atualizar
                            </button>
                        </div>
                        <div id="alertsList">
                            <div class="text-muted">Carregue os alertas para ver notificações importantes</div>
                        </div>
                    </div>
                </div>

                <!-- Vendedores -->
                <div class="tab-pane fade" id="sellers" role="tabpanel">
                    <div class="dark-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Top Vendedores AWA</h5>
                            <button class="btn btn-sm btn-outline-light" onclick="loadSellerStats()">
                                <i class="fas fa-chart-bar me-1"></i> Estatísticas Detalhadas
                            </button>
                        </div>
                        <div id="sellersList">
                            <div class="text-muted">Aguardando análise...</div>
                        </div>
                        <div id="sellerStatsDetail" style="display:none;" class="mt-4">
                            <h6 class="text-primary"><i class="fas fa-chart-pie me-2"></i>Estatísticas por Vendedor</h6>
                            <div id="sellerStatsContent"></div>
                        </div>
                    </div>
                </div>

                <!-- Preços -->
                <div class="tab-pane fade" id="pricing" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="dark-card">
                                <h5 class="mb-3">Estatísticas de Preços</h5>
                                <div id="priceStats">
                                    <div class="text-muted">Aguardando análise...</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="dark-card">
                                <h5 class="mb-3">Distribuição por Faixa</h5>
                                <canvas id="priceChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Frete -->
                <div class="tab-pane fade" id="shipping" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="dark-card">
                                <h5 class="mb-3">Análise de Frete</h5>
                                <div id="shippingStats">
                                    <div class="text-muted">Aguardando análise...</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="dark-card">
                                <h5 class="mb-3">Distribuição de Frete</h5>
                                <canvas id="shippingChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Concorrência -->
                <div class="tab-pane fade" id="competitors" role="tabpanel">
                    <div class="dark-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Comparação com Concorrentes</h5>
                            <button class="btn btn-sm btn-outline-light" onclick="loadCompetitors()">
                                <i class="fas fa-sync-alt me-1"></i> Carregar Comparação
                            </button>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-light">Marcas Concorrentes (opcional)</label>
                            <input type="text" id="competitorBrands" class="form-control"
                                placeholder="Ex: PRO TORK, MOTO X, RIFFEL (separadas por vírgula)">
                        </div>
                        <div id="competitorsList">
                            <div class="text-muted">Clique em "Carregar Comparação" para ver análise comparativa</div>
                        </div>
                        <canvas id="competitorsChart" height="250" style="display:none;"></canvas>
                    </div>
                </div>

                <!-- Tendências -->
                <div class="tab-pane fade" id="trends" role="tabpanel">
                    <div class="dark-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Tendências e Histórico</h5>
                            <div>
                                <select id="trendsPeriod" class="form-select form-select-sm d-inline-block w-auto me-2">
                                    <option value="7">7 dias</option>
                                    <option value="15">15 dias</option>
                                    <option value="30" selected>30 dias</option>
                                    <option value="60">60 dias</option>
                                </select>
                                <button class="btn btn-sm btn-outline-light" onclick="loadTrends()">
                                    <i class="fas fa-sync-alt me-1"></i> Carregar
                                </button>
                            </div>
                        </div>
                        <div id="trendsList">
                            <div class="text-muted">Clique em "Carregar" para ver histórico e tendências</div>
                        </div>
                        <canvas id="trendsChart" height="250" style="display:none;"></canvas>
                    </div>
                </div>

                <!-- Oportunidades -->
                <div class="tab-pane fade" id="opportunities" role="tabpanel">
                    <div class="dark-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Oportunidades de Mercado</h5>
                            <button class="btn btn-sm btn-outline-light" onclick="loadOpportunities()">
                                <i class="fas fa-sync-alt me-1"></i> Atualizar
                            </button>
                        </div>
                        <div id="opportunitiesList">
                            <div class="text-muted">Clique em "Atualizar" para ver oportunidades identificadas</div>
                        </div>
                    </div>
                </div>

                <!-- Recomendações -->
                <div class="tab-pane fade" id="recommendations" role="tabpanel">
                    <div class="dark-card">
                        <h5 class="mb-3">Recomendações de Melhoria</h5>
                        <div id="recommendationsList">
                            <div class="text-muted">Aguardando análise...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Placeholder inicial -->
        <div id="placeholderSection" class="dark-card text-center py-5">
            <i class="fas fa-motorcycle fa-4x mb-4" style="color: var(--primary);"></i>
            <h4 class="text-light mb-3">Análise de Marca AWA</h4>
            <p class="text-muted mb-4">
                Clique em "Analisar" para iniciar a análise completa dos anúncios da marca AWA no Mercado Livre.
                <br>A análise inclui identificação de lacunas, inconsistências e recomendações de melhoria.
            </p>
            <button onclick="document.getElementById('runAnalysis').click()" class="btn btn-awa btn-lg">
                <i class="fas fa-play me-2"></i> Iniciar Análise
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?= CSP_NONCE ?>">

        // Estado global
        let analysisData = null;
        let priceChart = null;
        let shippingChart = null;
        let competitorsChart = null;
        let trendsChart = null;

        // Elementos DOM
        const loadingOverlay = document.getElementById('loadingOverlay');
        const resultsSection = document.getElementById('resultsSection');
        const placeholderSection = document.getElementById('placeholderSection');

        // Event listeners
        document.getElementById('runAnalysis').addEventListener('click', runAnalysis);

        // Funções principais
        async function runAnalysis() {
            const categories = Array.from(document.getElementById('categoryFilter').selectedOptions).map(o => o.value);
            const maxResults = document.getElementById('maxResults').value;
            const analysisType = document.getElementById('analysisType').value;

            showLoading('Iniciando análise...');

            try {
                let url;
                if (analysisType === 'quick') {
                    url = `/api/brand/awa/quick?max_results=${maxResults}`;
                    if (categories.length > 0) {
                        url += `&category=${categories[0]}`;
                    }
                } else {
                    url = `/api/brand/awa/analyze?max_results=${maxResults}&include_details=true`;
                    if (categories.length > 0) {
                        url += `&categories=${categories.join(',')}`;
                    }
                }

                updateLoadingProgress('Coletando dados do Mercado Livre...');

                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    analysisData = result.data;
                    displayResults(analysisData);
                } else {
                    throw new Error(result.error || 'Erro desconhecido');
                }
            } catch (error) {
                console.error('Erro na análise:', error);
                alert('Erro ao executar análise: ' + error.message);
            } finally {
                hideLoading();
            }
        }

        function displayResults(data) {
            placeholderSection.style.display = 'none';
            resultsSection.style.display = 'block';

            // KPIs
            document.getElementById('totalListings').textContent = formatNumber(data.total_listings || data.total || 0);
            document.getElementById('withBrand').textContent = formatNumber(data.listings_with_brand || data.with_brand || 0);
            document.getElementById('withoutBrand').textContent = formatNumber(data.listings_without_brand || data.without_brand || 0);
            document.getElementById('wrongBrand').textContent = formatNumber(data.listings_with_wrong_brand || 0);

            // Score de consistência
            const consistencyScore = data.brand_consistency_score || data.consistency_score || 0;
            document.getElementById('consistencyScore').textContent = consistencyScore + '%';
            document.getElementById('consistencyRing').style.setProperty('--score', consistencyScore);

            // Saúde da marca
            const summary = data.summary || {};
            const health = summary.health_status || {};
            const healthScore = health.score || 0;
            document.getElementById('healthScore').textContent = healthScore;
            document.getElementById('healthRing').style.setProperty('--score', healthScore);

            const healthBadge = document.getElementById('healthBadge');
            healthBadge.textContent = translateHealthStatus(health.status || 'unknown');
            healthBadge.className = 'health-badge health-' + (health.status || 'fair');

            // Problemas detectados
            displayIssues(health.issues || []);

            // Badges de contagem
            const gaps = data.gaps_detected || [];
            const inconsistencies = data.inconsistencies || [];
            const sellers = data.sellers || {};

            document.getElementById('gapsCount').textContent = gaps.length;
            document.getElementById('inconsistenciesCount').textContent = inconsistencies.length;
            document.getElementById('sellersCount').textContent = Object.keys(sellers).length;

            // Lacunas
            displayGaps(gaps);

            // Inconsistências
            displayInconsistencies(inconsistencies);

            // Vendedores
            displaySellers(sellers, summary.top_sellers || []);

            // Preços
            displayPricing(data.price_analysis || {});

            // Frete
            displayShipping(data.shipping_analysis || {});

            // Recomendações
            displayRecommendations(summary.recommendations || []);
        }

        function displayIssues(issues) {
            const container = document.getElementById('issuesList');

            if (issues.length === 0) {
                container.innerHTML = '<div class="text-success"><i class="fas fa-check-circle me-2"></i>Nenhum problema crítico</div>';
                return;
            }

            container.innerHTML = issues.map(issue => `
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-exclamation-circle text-warning me-2"></i>
                    <span>${issue}</span>
                </div>
            `).join('');
        }

        function displayGaps(gaps) {
            const container = document.getElementById('gapsList');

            if (gaps.length === 0) {
                container.innerHTML = '<div class="text-success"><i class="fas fa-check-circle me-2"></i>Nenhuma lacuna encontrada</div>';
                return;
            }

            container.innerHTML = gaps.slice(0, 50).map(gap => `
                <div class="gap-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>${gap.item_id}</strong>
                            <span class="badge bg-warning text-dark ms-2">${translateGapType(gap.type)}</span>
                        </div>
                        <a href="https://www.mercadolibre.com.br/p/${gap.item_id}" target="_blank" class="btn btn-sm btn-outline-light">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                    <div class="text-muted small mt-1">${gap.title || 'Sem título'}</div>
                </div>
            `).join('');

            if (gaps.length > 50) {
                container.innerHTML += `<div class="text-center text-muted mt-3">... e mais ${gaps.length - 50} itens</div>`;
            }
        }

        function displayInconsistencies(inconsistencies) {
            const container = document.getElementById('inconsistenciesList');

            if (inconsistencies.length === 0) {
                container.innerHTML = '<div class="text-success"><i class="fas fa-check-circle me-2"></i>Nenhuma inconsistência encontrada</div>';
                return;
            }

            container.innerHTML = inconsistencies.map(item => `
                <div class="inconsistency-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>${item.item_id}</strong>
                            <span class="badge bg-danger ms-2">${translateInconsistencyType(item.type)}</span>
                        </div>
                        <a href="https://www.mercadolibre.com.br/p/${item.item_id}" target="_blank" class="btn btn-sm btn-outline-light">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                    <div class="mt-2">
                        <span class="text-danger">Atual: ${item.current_value || 'N/A'}</span>
                        <i class="fas fa-arrow-right mx-2"></i>
                        <span class="text-success">Esperado: ${item.expected_value || 'AWA'}</span>
                    </div>
                    <div class="text-muted small mt-1">${item.title || ''}</div>
                </div>
            `).join('');
        }

        function displaySellers(sellers, topSellers) {
            const container = document.getElementById('sellersList');
            const sellersArray = topSellers.length > 0 ? topSellers : Object.values(sellers);

            if (sellersArray.length === 0) {
                container.innerHTML = '<div class="text-muted">Nenhum vendedor encontrado</div>';
                return;
            }

            container.innerHTML = sellersArray.slice(0, 10).map((seller, index) => `
                <div class="seller-row">
                    <div class="d-flex align-items-center">
                        <div class="seller-rank">${index + 1}</div>
                        <div>
                            <strong>${seller.nickname || 'Desconhecido'}</strong>
                            <div class="text-muted small">ID: ${seller.id}</div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-primary fw-bold">${seller.items_count || 0} anúncios</div>
                    </div>
                </div>
            `).join('');
        }

        function displayPricing(priceAnalysis) {
            const container = document.getElementById('priceStats');

            if (!priceAnalysis.count) {
                container.innerHTML = '<div class="text-muted">Dados de preço não disponíveis</div>';
                return;
            }

            container.innerHTML = `
                <div class="row g-3">
                    <div class="col-6">
                        <div class="stat-card">
                            <div class="stat-value" style="font-size: 1.5rem;">${formatCurrency(priceAnalysis.min)}</div>
                            <div class="stat-label">Mínimo</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card">
                            <div class="stat-value" style="font-size: 1.5rem;">${formatCurrency(priceAnalysis.max)}</div>
                            <div class="stat-label">Máximo</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card">
                            <div class="stat-value" style="font-size: 1.5rem;">${formatCurrency(priceAnalysis.avg)}</div>
                            <div class="stat-label">Médio</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card">
                            <div class="stat-value" style="font-size: 1.5rem;">${formatCurrency(priceAnalysis.median)}</div>
                            <div class="stat-label">Mediana</div>
                        </div>
                    </div>
                </div>
            `;

            // Gráfico de preços
            if (priceAnalysis.price_ranges) {
                updatePriceChart(priceAnalysis.price_ranges);
            }
        }

        function displayShipping(shippingAnalysis) {
            const container = document.getElementById('shippingStats');

            if (!shippingAnalysis.total_items) {
                container.innerHTML = '<div class="text-muted">Dados de frete não disponíveis</div>';
                return;
            }

            const free = shippingAnalysis.free_shipping || {};
            const paid = shippingAnalysis.paid_shipping || {};
            const full = shippingAnalysis.full_shipping || {};

            container.innerHTML = `
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Frete Grátis</span>
                        <span>${free.count || 0} (${free.percentage || 0}%)</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: ${free.percentage || 0}%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Frete Pago</span>
                        <span>${paid.count || 0} (${paid.percentage || 0}%)</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-warning" style="width: ${paid.percentage || 0}%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Mercado Envios Full</span>
                        <span>${full.count || 0} (${full.percentage || 0}%)</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-primary" style="width: ${full.percentage || 0}%"></div>
                    </div>
                </div>
            `;

            // Gráfico de frete
            updateShippingChart({
                'Frete Grátis': free.count || 0,
                'Frete Pago': paid.count || 0,
                'Full': full.count || 0
            });
        }

        function displayRecommendations(recommendations) {
            const container = document.getElementById('recommendationsList');

            if (recommendations.length === 0) {
                container.innerHTML = '<div class="text-success"><i class="fas fa-check-circle me-2"></i>Nenhuma recomendação no momento</div>';
                return;
            }

            container.innerHTML = recommendations.map(rec => `
                <div class="recommendation-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <strong>${rec.action}</strong>
                        <span class="priority-badge priority-${rec.priority}">Prioridade ${rec.priority}</span>
                    </div>
                    <p class="mb-2">${rec.description}</p>
                    <div class="small text-muted">
                        <i class="fas fa-chart-line me-1"></i> Impacto: ${rec.impact}
                    </div>
                </div>
            `).join('');
        }

        function updatePriceChart(ranges) {
            const ctx = document.getElementById('priceChart').getContext('2d');

            if (priceChart) {
                priceChart.destroy();
            }

            priceChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: Object.keys(ranges).map(k => 'R$ ' + k),
                    datasets: [{
                        label: 'Quantidade',
                        data: Object.values(ranges),
                        backgroundColor: 'rgba(255, 230, 0, 0.8)',
                        borderColor: 'rgba(255, 230, 0, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: 'rgba(255,255,255,0.7)'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        },
                        x: {
                            ticks: {
                                color: 'rgba(255,255,255,0.7)'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        }
                    }
                }
            });
        }

        function updateShippingChart(data) {
            const ctx = document.getElementById('shippingChart').getContext('2d');

            if (shippingChart) {
                shippingChart.destroy();
            }

            shippingChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(data),
                    datasets: [{
                        data: Object.values(data),
                        backgroundColor: [
                            'rgba(0, 166, 80, 0.8)',
                            'rgba(255, 119, 51, 0.8)',
                            'rgba(52, 131, 250, 0.8)'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: 'rgba(255,255,255,0.7)'
                            }
                        }
                    }
                }
            });
        }

        // Funções auxiliares
        function showLoading(text = 'Carregando...') {
            document.querySelector('.loading-text').textContent = text;
            loadingOverlay.style.display = 'flex';
        }

        function hideLoading() {
            loadingOverlay.style.display = 'none';
        }

        function updateLoadingProgress(text) {
            document.getElementById('loadingProgress').textContent = text;
        }

        function formatNumber(num) {
            return new Intl.NumberFormat('pt-BR').format(num);
        }

        function formatCurrency(value) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        }

        function translateHealthStatus(status) {
            const translations = {
                'excellent': 'Excelente',
                'good': 'Bom',
                'fair': 'Regular',
                'poor': 'Ruim',
                'critical': 'Crítico',
                'unknown': 'Desconhecido'
            };
            return translations[status] || status;
        }

        function translateGapType(type) {
            const translations = {
                'missing_brand': 'Marca ausente',
                'brand_in_title_not_attribute': 'Marca só no título',
                'other': 'Outro'
            };
            return translations[type] || type;
        }

        function translateInconsistencyType(type) {
            const translations = {
                'wrong_brand': 'Marca incorreta',
                'misspelled_brand': 'Erro de digitação',
                'other': 'Outro'
            };
            return translations[type] || type;
        }

        // Exportações
        function exportCSV() {
            window.location.href = '/api/brand/awa/export/csv?' + getFilterParams();
        }

        function exportJSON() {
            window.location.href = '/api/brand/awa/export/json?' + getFilterParams();
        }

        function exportPDF() {
            window.location.href = '/api/pdf/brand/awa?' + getFilterParams();
        }

        function exportFixList() {
            window.location.href = '/api/brand/awa/export/fix-list?' + getFilterParams();
        }

        function getFilterParams() {
            const categories = Array.from(document.getElementById('categoryFilter').selectedOptions).map(o => o.value);
            const maxResults = document.getElementById('maxResults').value;

            let params = `max_results=${maxResults}`;
            if (categories.length > 0) {
                params += `&categories=${categories.join(',')}`;
            }
            return params;
        }

        // Carregar estatísticas detalhadas de vendedores
        async function loadSellerStats() {
            showLoading('Carregando estatísticas de vendedores...');
            try {
                const result = await requestJson('/api/brand/awa/seller-stats');

                if (result.success) {
                    displaySellerStats(result.data);
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                console.error('Erro ao carregar estatísticas:', error);
                document.getElementById('sellerStatsContent').innerHTML = `<div class="text-danger"><i class="fas fa-exclamation-circle me-2"></i>${error.message}</div>`;
            } finally {
                hideLoading();
            }
        }

        function displaySellerStats(data) {
            const container = document.getElementById('sellerStatsContent');
            const detailSection = document.getElementById('sellerStatsDetail');

            detailSection.style.display = 'block';

            if (!data.sellers || data.sellers.length === 0) {
                container.innerHTML = '<div class="text-muted">Nenhuma estatística disponível</div>';
                return;
            }

            container.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-value" style="font-size:1.5rem">${data.total_sellers || 0}</div>
                            <div class="stat-label">Total Vendedores</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-value text-primary" style="font-size:1.5rem">${data.summary?.avg_brand_compliance || 0}%</div>
                            <div class="stat-label">Compliance Médio</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-value text-success" style="font-size:1.5rem">${formatNumber(data.summary?.total_sales || 0)}</div>
                            <div class="stat-label">Total Vendas</div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive mt-3">
                    <table class="table table-dark-custom table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Vendedor</th>
                                <th class="text-center">Itens</th>
                                <th class="text-center">Com Marca</th>
                                <th class="text-center">Compliance</th>
                                <th class="text-center">Vendas</th>
                                <th class="text-end">Preço Médio</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.sellers.slice(0, 15).map((s, i) => `
                                <tr>
                                    <td>${i + 1}</td>
                                    <td><strong>${s.nickname}</strong></td>
                                    <td class="text-center">${s.total_items}</td>
                                    <td class="text-center">${s.items_with_brand}</td>
                                    <td class="text-center">
                                        <span class="badge bg-${s.brand_compliance >= 80 ? 'success' : s.brand_compliance >= 50 ? 'warning' : 'danger'}">
                                            ${s.brand_compliance}%
                                        </span>
                                    </td>
                                    <td class="text-center">${formatNumber(s.total_sales)}</td>
                                    <td class="text-end">${formatCurrency(s.avg_price)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        // Carregar alertas
        async function loadAlerts() {
            showLoading('Carregando alertas...');
            try {
                const result = await requestJson('/api/brand/awa/alerts?' + getFilterParams());

                if (result.success) {
                    displayAlerts(result.data);
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                console.error('Erro ao carregar alertas:', error);
                document.getElementById('alertsList').innerHTML = `<div class="text-danger"><i class="fas fa-exclamation-circle me-2"></i>${error.message}</div>`;
            } finally {
                hideLoading();
            }
        }

        function displayAlerts(data) {
            const container = document.getElementById('alertsList');
            document.getElementById('alertsCount').textContent = data.total_alerts || 0;

            if (!data.alerts || data.alerts.length === 0) {
                container.innerHTML = '<div class="text-success"><i class="fas fa-check-circle me-2"></i>Nenhum alerta no momento</div>';
                return;
            }

            const alertColors = {
                'critical': 'danger',
                'warning': 'warning',
                'info': 'info'
            };

            const alertIcons = {
                'critical': 'fa-exclamation-circle',
                'warning': 'fa-exclamation-triangle',
                'info': 'fa-info-circle'
            };

            container.innerHTML = `
                <div class="row mb-3">
                    <div class="col-4">
                        <div class="stat-card">
                            <div class="stat-value text-danger" style="font-size:1.5rem">${data.critical}</div>
                            <div class="stat-label">Críticos</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-card">
                            <div class="stat-value text-warning" style="font-size:1.5rem">${data.warning}</div>
                            <div class="stat-label">Avisos</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-card">
                            <div class="stat-value text-info" style="font-size:1.5rem">${data.info}</div>
                            <div class="stat-label">Info</div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    ${data.alerts.map(alert => `
                        <div class="alert alert-${alertColors[alert.type] || 'secondary'} d-flex align-items-start mb-2" role="alert">
                            <i class="fas ${alertIcons[alert.type] || 'fa-bell'} me-2 mt-1"></i>
                            <div>
                                <strong>${alert.title || 'Alerta'}</strong>
                                <p class="mb-0 small">${alert.message}</p>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        // Carregar comparação com concorrentes
        async function loadCompetitors() {
            showLoading('Analisando concorrência...');
            try {
                const competitors = document.getElementById('competitorBrands').value;
                const category = Array.from(document.getElementById('categoryFilter').selectedOptions).map(o => o.value)[0] || 'MLB214858';

                let url = `/api/brand/awa/compare?category=${category}`;
                if (competitors) {
                    url += `&competitors=${encodeURIComponent(competitors)}`;
                }

                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    displayCompetitors(result.data);
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                console.error('Erro ao carregar concorrência:', error);
                document.getElementById('competitorsList').innerHTML = `<div class="text-danger"><i class="fas fa-exclamation-circle me-2"></i>${error.message}</div>`;
            } finally {
                hideLoading();
            }
        }

        function displayCompetitors(data) {
            const container = document.getElementById('competitorsList');
            const chartCanvas = document.getElementById('competitorsChart');

            if (!data.competitors || data.competitors.length === 0) {
                container.innerHTML = '<div class="text-muted">Nenhum dado de concorrência disponível</div>';
                return;
            }

            // Tabela de comparação
            container.innerHTML = `
                <div class="table-responsive">
                    <table class="table table-dark-custom">
                        <thead>
                            <tr>
                                <th>Marca</th>
                                <th class="text-center">Anúncios</th>
                                <th class="text-center">Preço Médio</th>
                                <th class="text-center">Frete Grátis</th>
                                <th class="text-center">Full</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-primary">
                                <td><strong>AWA</strong></td>
                                <td class="text-center">${data.awa_metrics?.total_listings || 0}</td>
                                <td class="text-center">${formatCurrency(data.awa_metrics?.avg_price || 0)}</td>
                                <td class="text-center">${data.awa_metrics?.free_shipping_pct || 0}%</td>
                                <td class="text-center">${data.awa_metrics?.full_shipping_pct || 0}%</td>
                            </tr>
                            ${data.competitors.map(comp => `
                                <tr>
                                    <td>${comp.brand}</td>
                                    <td class="text-center">${comp.total_listings || 0}</td>
                                    <td class="text-center">${formatCurrency(comp.avg_price || 0)}</td>
                                    <td class="text-center">${comp.free_shipping_pct || 0}%</td>
                                    <td class="text-center">${comp.full_shipping_pct || 0}%</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                ${data.insights ? `
                    <div class="mt-3">
                        <h6 class="text-primary"><i class="fas fa-lightbulb me-2"></i>Insights</h6>
                        <ul class="small">
                            ${data.insights.map(i => `<li>${i}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}
            `;

            // Gráfico de comparação
            chartCanvas.style.display = 'block';
            updateCompetitorsChart(data);
        }

        function updateCompetitorsChart(data) {
            const ctx = document.getElementById('competitorsChart').getContext('2d');

            if (competitorsChart) {
                competitorsChart.destroy();
            }

            const labels = ['AWA', ...data.competitors.map(c => c.brand)];
            const listings = [data.awa_metrics?.total_listings || 0, ...data.competitors.map(c => c.total_listings || 0)];

            competitorsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Quantidade de Anúncios',
                        data: listings,
                        backgroundColor: labels.map((l, i) => i === 0 ? 'rgba(255, 230, 0, 0.8)' : 'rgba(100, 100, 100, 0.6)'),
                        borderColor: labels.map((l, i) => i === 0 ? 'rgba(255, 230, 0, 1)' : 'rgba(100, 100, 100, 1)'),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: 'rgba(255,255,255,0.7)'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        },
                        x: {
                            ticks: {
                                color: 'rgba(255,255,255,0.7)'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        }
                    }
                }
            });
        }

        // Carregar oportunidades
        async function loadOpportunities() {
            showLoading('Identificando oportunidades...');
            try {
                const category = Array.from(document.getElementById('categoryFilter').selectedOptions).map(o => o.value)[0] || 'MLB214858';
                const result = await requestJson(`/api/brand/awa/opportunities?category=${category}`);

                if (result.success) {
                    displayOpportunities(result.data);
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                console.error('Erro ao carregar oportunidades:', error);
                document.getElementById('opportunitiesList').innerHTML = `<div class="text-danger"><i class="fas fa-exclamation-circle me-2"></i>${error.message}</div>`;
            } finally {
                hideLoading();
            }
        }

        function displayOpportunities(data) {
            const container = document.getElementById('opportunitiesList');

            if (!data.opportunities || data.opportunities.length === 0) {
                container.innerHTML = '<div class="text-muted">Nenhuma oportunidade identificada no momento</div>';
                return;
            }

            const impactColors = {
                'high': 'success',
                'medium': 'warning',
                'low': 'secondary'
            };

            const impactLabels = {
                'high': 'Alto Impacto',
                'medium': 'Médio Impacto',
                'low': 'Baixo Impacto'
            };

            container.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-value text-success" style="font-size:1.5rem">${data.market_size || 'N/A'}</div>
                            <div class="stat-label">Tamanho do Mercado</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-value text-primary" style="font-size:1.5rem">${data.growth_potential || 'N/A'}</div>
                            <div class="stat-label">Potencial de Crescimento</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-value text-warning" style="font-size:1.5rem">${data.opportunities.length}</div>
                            <div class="stat-label">Oportunidades</div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    ${data.opportunities.map(opp => `
                        <div class="recommendation-card" style="border-left-color: var(--${impactColors[opp.impact] || 'secondary'});">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <strong><i class="fas fa-rocket me-2"></i>${opp.title}</strong>
                                <span class="badge bg-${impactColors[opp.impact] || 'secondary'}">${impactLabels[opp.impact] || opp.impact}</span>
                            </div>
                            <p class="mb-2">${opp.description}</p>
                            ${opp.action ? `<div class="small text-info"><i class="fas fa-arrow-right me-1"></i>${opp.action}</div>` : ''}
                        </div>
                    `).join('')}
                </div>
            `;
        }

        // Carregar tendências
        async function loadTrends() {
            showLoading('Carregando tendências...');
            try {
                const days = document.getElementById('trendsPeriod').value || 30;
                const result = await requestJson(`/api/brand/awa/trends?days=${days}`);

                if (result.success) {
                    displayTrends(result.data);
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                console.error('Erro ao carregar tendências:', error);
                document.getElementById('trendsList').innerHTML = `<div class="text-danger"><i class="fas fa-exclamation-circle me-2"></i>${error.message}</div>`;
            } finally {
                hideLoading();
            }
        }

        function displayTrends(data) {
            const container = document.getElementById('trendsList');
            const chartCanvas = document.getElementById('trendsChart');

            if (!data.history || data.history.length === 0) {
                container.innerHTML = '<div class="text-muted">Nenhum histórico disponível para este período</div>';
                chartCanvas.style.display = 'none';
                return;
            }

            const trendColors = {
                'up': 'success',
                'down': 'danger',
                'stable': 'warning'
            };

            const trendIcons = {
                'up': 'fa-arrow-up',
                'down': 'fa-arrow-down',
                'stable': 'fa-minus'
            };

            const trendLabels = {
                'up': 'Em Alta',
                'down': 'Em Baixa',
                'stable': 'Estável'
            };

            container.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value text-${trendColors[data.trend?.direction] || 'warning'}" style="font-size:1.5rem">
                                <i class="fas ${trendIcons[data.trend?.direction] || 'fa-minus'} me-1"></i>
                                ${data.trend?.change > 0 ? '+' : ''}${data.trend?.change || 0}%
                            </div>
                            <div class="stat-label">Variação Score</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value" style="font-size:1.5rem">${data.history.length}</div>
                            <div class="stat-label">Análises</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value text-primary" style="font-size:1.5rem">${data.avg_score || 0}%</div>
                            <div class="stat-label">Score Médio</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <span class="badge bg-${trendColors[data.trend?.direction] || 'warning'} fs-6">${trendLabels[data.trend?.direction] || 'Estável'}</span>
                            <div class="stat-label mt-2">Tendência</div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <h6 class="text-light mb-3"><i class="fas fa-history me-2"></i>Histórico de Análises</h6>
                    <div class="table-responsive">
                        <table class="table table-dark-custom table-sm">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th class="text-center">Anúncios</th>
                                    <th class="text-center">Score</th>
                                    <th class="text-center">Lacunas</th>
                                    <th class="text-center">Inconsistências</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.history.slice(0, 10).map(h => `
                                    <tr>
                                        <td>${new Date(h.analysis_date).toLocaleDateString('pt-BR')}</td>
                                        <td class="text-center">${h.total_listings || 0}</td>
                                        <td class="text-center"><span class="badge bg-primary">${h.consistency_score || 0}%</span></td>
                                        <td class="text-center"><span class="badge bg-warning text-dark">${h.gaps_count || 0}</span></td>
                                        <td class="text-center"><span class="badge bg-danger">${h.inconsistencies_count || 0}</span></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;

            // Atualizar gráfico
            chartCanvas.style.display = 'block';
            updateTrendsChart(data.history);
        }

        function updateTrendsChart(history) {
            const ctx = document.getElementById('trendsChart').getContext('2d');

            if (trendsChart) {
                trendsChart.destroy();
            }

            const labels = history.map(h => new Date(h.analysis_date).toLocaleDateString('pt-BR')).reverse();
            const scores = history.map(h => h.consistency_score || 0).reverse();
            const gaps = history.map(h => h.gaps_count || 0).reverse();

            trendsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Score de Consistência',
                        data: scores,
                        borderColor: 'rgba(255, 230, 0, 1)',
                        backgroundColor: 'rgba(255, 230, 0, 0.1)',
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Lacunas',
                        data: gaps,
                        borderColor: 'rgba(255, 119, 51, 1)',
                        backgroundColor: 'rgba(255, 119, 51, 0.1)',
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: 'rgba(255,255,255,0.7)'
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                color: 'rgba(255,255,255,0.7)'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            ticks: {
                                color: 'rgba(255,255,255,0.7)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        },
                        x: {
                            ticks: {
                                color: 'rgba(255,255,255,0.7)'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>

</html>