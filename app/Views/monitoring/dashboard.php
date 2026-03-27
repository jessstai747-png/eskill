<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Monitoramento - Sistema de Clonagem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .metric-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .metric-card.warning {
            border-left-color: #ffc107;
        }
        .metric-card.error {
            border-left-color: #dc3545;
        }
        .metric-card.success {
            border-left-color: #28a745;
        }
        .alert-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .log-entry {
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
        }
        .log-debug { color: #6c757d; }
        .log-info { color: #0dcaf0; }
        .log-warning { color: #ffc107; }
        .log-error { color: #dc3545; }
        .log-critical { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row bg-dark text-white py-3 mb-4">
            <div class="col">
                <h1 class="h3 mb-0">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard de Monitoramento
                </h1>
                <p class="mb-0">Sistema de Clonagem de Catálogo - Mercado Livre</p>
            </div>
            <div class="col-auto d-flex align-items-center">
                <span id="last-update" class="badge bg-secondary me-3">Carregando...</span>
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-light" onclick="refreshDashboard()">
                        <i class="bi bi-arrow-clockwise"></i> Atualizar
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="emergencyShutdown()" title="Desabilitar Sistema">
                        <i class="bi bi-exclamation-triangle"></i> Emergência
                    </button>
                </div>
            </div>
        </div>

        <!-- Alertas -->
        <div id="alerts-container" class="mb-4"></div>

        <!-- Métricas Principais -->
        <div class="row mb-4" id="main-metrics">
            <!-- Cards de métricas serão inseridos aqui via JavaScript -->
        </div>

        <!-- Gráficos e Tabelas -->
        <div class="row">
            <!-- Performance Chart -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Performance dos Últimos 7 Dias</h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary active" onclick="updateChart(1)">24h</button>
                            <button class="btn btn-outline-secondary" onclick="updateChart(7)">7d</button>
                            <button class="btn btn-outline-secondary" onclick="updateChart(30)">30d</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Feature Flags -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Feature Flags</h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="feature-flags-container" class="list-group list-group-flush">
                            <!-- Feature flags serão inseridas aqui -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jobs e Logs -->
        <div class="row">
            <!-- Jobs Recentes -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Estatísticas de Jobs</h5>
                    </div>
                    <div class="card-body">
                        <div id="job-stats-container">
                            <!-- Estatísticas de jobs serão inseridas aqui -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logs do Sistema -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Logs Recentes</h5>
                        <div class="btn-group btn-group-sm">
                            <select class="form-select form-select-sm" id="log-level-filter" onchange="filterLogs()">
                                <option value="">Todos os níveis</option>
                                <option value="CRITICAL">Critical</option>
                                <option value="ERROR">Error</option>
                                <option value="WARNING">Warning</option>
                                <option value="INFO">Info</option>
                                <option value="DEBUG">Debug</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                        <div id="logs-container" class="p-3">
                            <!-- Logs serão inseridos aqui -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Health Check -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Health Check</h5>
                    </div>
                    <div class="card-body">
                        <div id="health-check-container">
                            <!-- Health check será inserido aqui -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Emergência -->
    <div class="modal fade" id="emergencyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i>
                        Confirmação de Emergência
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>ATENÇÃO:</strong> Esta ação irá desabilitar completamente o sistema de clonagem de catálogo.</p>
                    <p>Use apenas em situações de emergência. Todos os jobs pendentes serão interrompidos.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="confirmEmergencyShutdown()">
                        Desabilitar Sistema
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script nonce="<?= CSP_NONCE ?>">

        let performanceChart = null;
        let refreshInterval = null;

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            initializeDashboard();
            startAutoRefresh();
        });

        function initializeDashboard() {
            loadRealTimeMetrics();
            loadAlerts();
            loadPerformanceChart(7);
            loadFeatureFlags();
            loadJobStats();
            loadSystemLogs();
            loadHealthCheck();
        }

        function startAutoRefresh() {
            refreshInterval = setInterval(() => {
                loadRealTimeMetrics();
                loadAlerts();
                loadJobStats();
                loadSystemLogs();
                updateLastUpdate();
            }, 30000); // Atualiza a cada 30 segundos
        }

        function refreshDashboard() {
            clearInterval(refreshInterval);
            initializeDashboard();
            startAutoRefresh();
        }

        async function loadRealTimeMetrics() {
            try {
                const result = await requestJson('/api/monitoring/realtime-metrics');
                
                if (result.success) {
                    renderMetrics(result.data);
                    updateLastUpdate();
                }
            } catch (error) {
                console.error('Erro ao carregar métricas:', error);
            }
        }

        function renderMetrics(metrics) {
            const container = document.getElementById('main-metrics');
            const stats = metrics.basic_stats || {};
            const mlOps = metrics.ml_operations || {};
            const pendingJobs = Number(stats.pending_jobs || 0);
            const completedJobs = Number(stats.completed_jobs || 0);
            const failedJobs = Number(stats.failed_jobs || 0);
            const successRate = Number(
                stats.success_rate ??
                ((completedJobs / (completedJobs + failedJobs)) * 100 || 100)
            ).toFixed(1);
            
            container.innerHTML = `
                <div class="col-md-3 mb-3">
                    <div class="card metric-card ${pendingJobs > 50 ? 'warning' : 'success'}">
                        <div class="card-body text-center">
                            <h3 class="mb-0">${pendingJobs}</h3>
                            <p class="mb-0">Jobs Pendentes</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card metric-card success">
                        <div class="card-body text-center">
                            <h3 class="mb-0">${completedJobs}</h3>
                            <p class="mb-0">Jobs Completos</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card metric-card ${failedJobs > 10 ? 'error' : 'success'}">
                        <div class="card-body text-center">
                            <h3 class="mb-0">${failedJobs}</h3>
                            <p class="mb-0">Jobs Falhados</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card metric-card">
                        <div class="card-body text-center">
                            <h3 class="mb-0">${successRate}%</h3>
                            <p class="mb-0">Taxa de Sucesso</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card metric-card ${(mlOps.failed_webhook_backlog || 0) > 0 ? 'warning' : 'success'}">
                        <div class="card-body text-center">
                            <h3 class="mb-0">${mlOps.failed_webhook_backlog || 0}</h3>
                            <p class="mb-0">Webhooks ML Falhos</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card metric-card ${(mlOps.oldest_failed_webhook_minutes || 0) >= 30 ? 'warning' : 'success'}">
                        <div class="card-body text-center">
                            <h3 class="mb-0">${mlOps.oldest_failed_webhook_minutes || 0}m</h3>
                            <p class="mb-0">Falha ML Mais Antiga</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card metric-card ${(mlOps.jobs_retry_pending || 0) > 0 ? 'warning' : 'success'}">
                        <div class="card-body text-center">
                            <h3 class="mb-0">${mlOps.jobs_retry_pending || 0}</h3>
                            <p class="mb-0">Jobs em Retry</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card metric-card ${(mlOps.jobs_processing_stale || 0) > 0 ? 'error' : 'success'}">
                        <div class="card-body text-center">
                            <h3 class="mb-0">${mlOps.jobs_processing_stale || 0}</h3>
                            <p class="mb-0">Jobs Stale (processing)</p>
                        </div>
                    </div>
                </div>
            `;
        }

        async function loadAlerts() {
            try {
                const result = await requestJson('/api/monitoring/alerts');
                
                if (result.success) {
                    renderAlerts(result.alerts);
                }
            } catch (error) {
                console.error('Erro ao carregar alertas:', error);
            }
        }

        function renderAlerts(alerts) {
            const container = document.getElementById('alerts-container');
            
            if (alerts.length === 0) {
                container.innerHTML = '';
                return;
            }
            
            const alertsHtml = alerts.map(alert => {
                const severity = String(alert.severity || 'WARNING').toUpperCase();
                const alertClass = severity === 'CRITICAL' ? 'alert-danger' :
                                  severity === 'HIGH' || severity === 'WARNING' ? 'alert-warning' : 'alert-info';
                
                return `
                    <div class="alert ${alertClass} alert-dismissible fade show alert-badge" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>${alert.type}:</strong> ${alert.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            }).join('');
            
            container.innerHTML = alertsHtml;
        }

        async function loadPerformanceChart(days) {
            try {
                const result = await requestJson(`/api/monitoring/performance-report?days=${days}`);
                
                if (result.success) {
                    renderPerformanceChart(result.data);
                }
            } catch (error) {
                console.error('Erro ao carregar gráfico de performance:', error);
            }
        }

        function renderPerformanceChart(data) {
            const ctx = document.getElementById('performanceChart').getContext('2d');
            
            if (performanceChart) {
                performanceChart.destroy();
            }
            
            performanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.hourly_stats.map(stat => stat.hour_label),
                    datasets: [{
                        label: 'Jobs Completos',
                        data: data.hourly_stats.map(stat => stat.completed_jobs),
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    }, {
                        label: 'Jobs Falhados',
                        data: data.hourly_stats.map(stat => stat.failed_jobs),
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        function updateChart(days) {
            // Atualizar botões ativos
            document.querySelectorAll('.btn-group .btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            loadPerformanceChart(days);
        }

        async function loadFeatureFlags() {
            try {
                const result = await requestJson('/api/monitoring/feature-flags');
                
                if (result.success) {
                    renderFeatureFlags(result.data);
                }
            } catch (error) {
                console.error('Erro ao carregar feature flags:', error);
            }
        }

        function renderFeatureFlags(flags) {
            const container = document.getElementById('feature-flags-container');
            
            const flagsHtml = flags.map(flag => {
                const statusClass = flag.is_enabled ? 'text-success' : 'text-danger';
                const icon = flag.is_enabled ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
                
                return `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${flag.flag_name}</strong>
                            <br>
                            <small class="text-muted">${flag.description}</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" 
                                   ${flag.is_enabled ? 'checked' : ''} 
                                   onchange="toggleFeatureFlag('${flag.flag_name}', this.checked)">
                        </div>
                    </div>
                `;
            }).join('');
            
            container.innerHTML = flagsHtml;
        }

        async function toggleFeatureFlag(flagName, enabled) {
            try {
                const result = await requestJson('/api/monitoring/feature-flags', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        flag_name: flagName,
                        enabled: enabled
                    })
                });
                
                if (!result.success) {
                    alert('Erro ao atualizar feature flag');
                    loadFeatureFlags(); // Recarregar para reverter UI
                }
            } catch (error) {
                console.error('Erro ao atualizar feature flag:', error);
                loadFeatureFlags();
            }
        }

        async function loadJobStats() {
            try {
                const result = await requestJson('/api/monitoring/job-stats?days=7');
                
                if (result.success) {
                    renderJobStats(result.data);
                }
            } catch (error) {
                console.error('Erro ao carregar estatísticas de jobs:', error);
            }
        }

        function renderJobStats(stats) {
            const container = document.getElementById('job-stats-container');
            
            const statsHtml = `
                <div class="row">
                    <div class="col-6">
                        <h6>Total Processados</h6>
                        <h4>${stats.total_jobs || 0}</h4>
                    </div>
                    <div class="col-6">
                        <h6>Taxa de Sucesso</h6>
                        <h4>${stats.success_rate || 100}%</h4>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-6">
                        <h6>Tempo Médio</h6>
                        <p>${stats.avg_processing_time || 0}s</p>
                    </div>
                    <div class="col-6">
                        <h6>Por Hora</h6>
                        <p>${stats.jobs_per_hour || 0}</p>
                    </div>
                </div>
            `;
            
            container.innerHTML = statsHtml;
        }

        async function loadSystemLogs() {
            try {
                const level = document.getElementById('log-level-filter').value;
                const url = `/api/monitoring/system-logs?limit=20${level ? '&level=' + level : ''}`;
                const result = await requestJson(url);
                
                if (result.success) {
                    renderSystemLogs(result.data);
                }
            } catch (error) {
                console.error('Erro ao carregar logs:', error);
            }
        }

        function renderSystemLogs(logs) {
            const container = document.getElementById('logs-container');
            
            if (logs.length === 0) {
                container.innerHTML = '<p class="text-muted">Nenhum log encontrado</p>';
                return;
            }
            
            const logsHtml = logs.map(log => {
                const levelClass = `log-${log.level.toLowerCase()}`;
                const time = new Date(log.created_at).toLocaleString('pt-BR');
                
                return `
                    <div class="log-entry mb-2 pb-2 border-bottom">
                        <div class="d-flex justify-content-between">
                            <span class="${levelClass}">[${log.level}]</span>
                            <small class="text-muted">${time}</small>
                        </div>
                        <div><strong>${log.category}:</strong> ${log.message}</div>
                    </div>
                `;
            }).join('');
            
            container.innerHTML = logsHtml;
        }

        async function loadHealthCheck() {
            try {
                const result = await requestJson('/api/monitoring/health');
                
                renderHealthCheck(result);
            } catch (error) {
                console.error('Erro ao carregar health check:', error);
            }
        }

        function renderHealthCheck(health) {
            const container = document.getElementById('health-check-container');
            
            const statusClass = health.status === 'ok' ? 'text-success' : 
                               health.status === 'degraded' ? 'text-warning' : 'text-danger';
            
            let checksHtml = '';
            if (health.checks) {
                checksHtml = Object.entries(health.checks).map(([key, check]) => {
                    const checkClass = check.status === 'ok' ? 'text-success' : 
                                      check.status === 'warning' ? 'text-warning' : 'text-danger';
                    const icon = check.status === 'ok' ? 'bi-check-circle' : 
                                check.status === 'warning' ? 'bi-exclamation-triangle' : 'bi-x-circle';
                    
                    return `
                        <div class="col-md-4 mb-2">
                            <i class="bi ${icon} ${checkClass}"></i>
                            <strong>${key}:</strong> 
                            <span class="${checkClass}">${check.status}</span>
                            ${check.error ? `<br><small class="text-danger">${check.error}</small>` : ''}
                        </div>
                    `;
                }).join('');
            }
            
            container.innerHTML = `
                <div class="row">
                    <div class="col-12 mb-3">
                        <h5>Status Geral: <span class="${statusClass}">${health.status.toUpperCase()}</span></h5>
                    </div>
                    ${checksHtml}
                </div>
            `;
        }

        function filterLogs() {
            loadSystemLogs();
        }

        function updateLastUpdate() {
            document.getElementById('last-update').textContent = 
                'Última atualização: ' + new Date().toLocaleTimeString('pt-BR');
        }

        function emergencyShutdown() {
            const modal = new bootstrap.Modal(document.getElementById('emergencyModal'));
            modal.show();
        }

        async function confirmEmergencyShutdown() {
            try {
                const result = await requestJson('/api/monitoring/emergency-shutdown', {
                    method: 'POST'
                });
                
                if (result.success) {
                    alert('Sistema desabilitado com sucesso');
                    refreshDashboard();
                } else {
                    alert('Erro ao desabilitar sistema: ' + result.error);
                }
                
                bootstrap.Modal.getInstance(document.getElementById('emergencyModal')).hide();
            } catch (error) {
                console.error('Erro na operação de emergência:', error);
                alert('Erro de comunicação com o servidor');
            }
        }

        // Limpar interval quando a página for fechada
        window.addEventListener('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
</body>
</html>
