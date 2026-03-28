<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Dashboard de Qualidade' ?> - ML Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/dashboard.css">
    <style>
        .quality-card {
            border-radius: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .quality-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .quality-excellent { border-left: 4px solid #28a745; }
        .quality-good { border-left: 4px solid #17a2b8; }
        .quality-fair { border-left: 4px solid #ffc107; }
        .quality-poor { border-left: 4px solid #dc3545; }

        .score-badge {
            font-size: 2rem;
            font-weight: bold;
            padding: 15px 25px;
            border-radius: 50px;
        }
        .score-excellent { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
        .score-good { background: linear-gradient(135deg, #17a2b8, #138496); color: white; }
        .score-fair { background: linear-gradient(135deg, #ffc107, #e0a800); color: black; }
        .score-poor { background: linear-gradient(135deg, #dc3545, #c82333); color: white; }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .filter-bar {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .item-row {
            transition: background 0.2s;
            cursor: pointer;
        }
        .item-row:hover {
            background: #f8f9fa;
        }
        .loading-spinner {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
        }
    </style>
</head>
<body>
    <?php


include __DIR__ . '/../layouts/header.php'; ?>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h1><i class="fas fa-star-half-alt text-warning"></i> Dashboard de Qualidade</h1>
                <p class="text-muted">Monitore a qualidade dos seus anúncios em tempo real</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4" id="stats-container">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Total de Itens</div>
                    <div class="stat-value" id="total-items">-</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card quality-excellent">
                    <div class="stat-label">Excelente (>80)</div>
                    <div class="stat-value text-success" id="excellent-items">-</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card quality-good">
                    <div class="stat-label">Bom (60-80)</div>
                    <div class="stat-value text-info" id="good-items">-</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card quality-poor">
                    <div class="stat-label">Precisa Melhorar (<60)</div>
                    <div class="stat-value text-danger" id="poor-items">-</div>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> Distribuição de Qualidade</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="qualityChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <div class="row">
                <div class="col-md-3">
                    <label>Score Mínimo</label>
                    <input type="number" class="form-control" id="filter-min-score" value="0" min="0" max="100">
                </div>
                <div class="col-md-3">
                    <label>Score Máximo</label>
                    <input type="number" class="form-control" id="filter-max-score" value="100" min="0" max="100">
                </div>
                <div class="col-md-3">
                    <label>Status</label>
                    <select class="form-select" id="filter-status">
                        <option value="active">Ativos</option>
                        <option value="paused">Pausados</option>
                        <option value="closed">Fechados</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>&nbsp;</label>
                    <button class="btn btn-primary w-100" onclick="applyFilters()">
                        <i class="fas fa-filter"></i> Aplicar Filtros
                    </button>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-list"></i> Itens por Qualidade</h5>
                <button class="btn btn-sm btn-outline-primary" onclick="refreshItems()">
                    <i class="fas fa-sync-alt"></i> Atualizar
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Item ID</th>
                                <th>Título</th>
                                <th>Score</th>
                                <th>Qualidade</th>
                                <th>Preço</th>
                                <th>Vendidos</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="items-table-body">
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Carregando...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav id="pagination-container" class="mt-4"></nav>
            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div class="loading-spinner">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="/js/ml-integration-preflight.js"></script>
    <script src="/js/quality-dashboard.js"></script>

    <script nonce="<?= CSP_NONCE ?>">
        // Initialize dashboard on page load
        document.addEventListener('DOMContentLoaded', () => {
            QualityDashboard.init(<?= $accountId ?? 'null' ?>);
        });
    </script>
</body>
</html>
