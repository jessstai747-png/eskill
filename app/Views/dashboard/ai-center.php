<?php

declare(strict_types=1);

// Validar sessão
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Center | Eskill</title>
    
    <!-- Core CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Dashboard CSS -->
    <link href="/public/css/dashboard-modern.css" rel="stylesheet">
    <link href="/public/css/ai-center.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../components/sidebar.php'; ?>

        <!-- Page Content -->
        <div id="page-content-wrapper" class="w-100">
            <!-- Topbar -->
            <?php include __DIR__ . '/../components/topbar.php'; ?>

            <!-- Main Content -->
            <div class="container-fluid p-4">
                
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1 fw-bold text-gradient">AI Center <span class="badge bg-primary-subtle text-primary ms-2 fs-6">V9.0</span></h1>
                        <p class="text-muted mb-0">Central de Inteligência Artificial e Automação</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-white border shadow-sm" onclick="new bootstrap.Modal(document.getElementById('aiConfigModal')).show()">
                            <i class="bi bi-gear-fill text-muted"></i>
                        </button>
                        <button class="btn btn-white border shadow-sm" onclick="AICenter.refreshApps()">
                            <i class="bi bi-arrow-clockwise"></i> Atualizar
                        </button>
                    </div>
                </div>

                <!-- Status Cards (Harness Health) -->
                <div class="row g-4 mb-4" id="harness-status-container">
                    <!-- Loading Skeletons -->
                    <div class="col-md-3"><div class="card h-100 border-0 shadow-sm skeleton-card"></div></div>
                    <div class="col-md-3"><div class="card h-100 border-0 shadow-sm skeleton-card"></div></div>
                    <div class="col-md-3"><div class="card h-100 border-0 shadow-sm skeleton-card"></div></div>
                    <div class="col-md-3"><div class="card h-100 border-0 shadow-sm skeleton-card"></div></div>
                </div>

                <!-- Main Grid -->
                <div class="row g-4">
                    
                    <!-- Left Column: Insights & Stats -->
                    <div class="col-lg-8">
                        
                        <!-- Decision Engine Stats -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-transparent border-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 fw-bold"><i class="bi bi-cpu text-primary me-2"></i>Decision Engine</h5>
                                    <span class="badge bg-success-subtle text-success">Active</span>
                                </div>
                            </div>
                            <div class="card-body" id="decision-stats-body">
                                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
                            </div>
                        </div>

                        <!-- Predictive Analytics Section -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-transparent border-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 fw-bold"><i class="bi bi-graph-up-arrow text-info me-2"></i>Predictive Analytics</h5>
                                    <select class="form-select form-select-sm w-auto">
                                        <option>Próximos 7 Dias</option>
                                        <option>Próximos 30 Dias</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body">
                                <canvas id="predictionChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Controls & Configuration -->
                    <div class="col-lg-4">
                        
                        <!-- AutoPilot Control -->
                        <div class="card border-0 shadow-sm mb-4 bg-gradient-primary text-white">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-4">
                                    <div>
                                        <h5 class="fw-bold mb-1"><i class="bi bi-robot me-2"></i>AutoPilot</h5>
                                        <p class="mb-0 opacity-75 small">Otimização Contínua</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="autopilotToggle" style="width: 3em; height: 1.5em; cursor:pointer;">
                                    </div>
                                </div>
                                <div id="autopilot-status">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="opacity-75">Status:</span>
                                        <span class="fw-bold" id="ap-status-text">Desativado</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="opacity-75">Mode:</span>
                                        <span class="fw-bold" id="ap-mode-text">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="opacity-75">Itens Ativos:</span>
                                        <span class="fw-bold" id="ap-items-text">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- System Health / Agent Harness -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-transparent border-0 py-3">
                                <h5 class="mb-0 fw-bold"><i class="bi bi-activity text-danger me-2"></i>System Health</h5>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush" id="health-list">
                                    <!-- Populated by JS -->
                                </ul>
                                <!-- Activity Feed -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Últimas Atividades</h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="activity-feed-container" class="list-group list-group-flush">
                            <div class="p-4 text-center text-muted">Carregando atividades...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
                            <div class="card-footer bg-transparent border-0 text-center py-3">
                                <small class="text-muted"><i class="bi bi-clock me-1"></i> Uptime: <span id="uptime-display">--</span></small>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- AI Config Wizard -->
    <?php include __DIR__ . '/ai-setup-wizard.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="/public/js/dashboard-modern.js"></script>
    <script src="/public/js/ai-center.js"></script>
</body>
</html>
