<!-- GSC Dashboard Tab -->
<?php


// Check connection status (simulated or real call)
// Ideally this would be passed from controller, but for include we might need an AJAX check on load
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm" style="background: white;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold mb-0">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/c/c7/Google_Search_Console_icon.svg" width="32" height="32" class="me-2">
                        Google Search Console
                    </h4>
                    <div id="gsc-connection-status">
                        <span class="badge bg-secondary p-2">Verificando conexão...</span>
                    </div>
                </div>

                <!-- Connect Section (Hidden if connected) -->
                <div id="gsc-connect-section" style="display: none;" class="text-center py-5">
                    <img src="https://lh3.googleusercontent.com/FwFqJ1bN6Jz7eM2W3G-kX-3-X4X4X4X4X4X4X4X4" width="120" class="mb-3 opacity-50">
                    <h5>Conecte sua conta do Google</h5>
                    <p class="text-muted mb-4">Obtenha dados reais de cliques, impressões e posicionamento direto do Google.</p>
                    <button class="btn btn-lg btn-outline-primary" onclick="GSCManager.connect()">
                        <i class="bi bi-google me-2"></i> Conectar Search Console
                    </button>
                </div>

                <!-- Data Dashboard (Hidden if disconnected) -->
                <div id="gsc-data-dashboard" style="display: none;">

                    <!-- KPI Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted text-uppercase fw-bold">Cliques Totais</small>
                                <h2 class="mb-0 fw-bold text-primary mt-1" id="gsc-total-clicks">-</h2>
                                <small class="text-success"><i class="bi bi-graph-up"></i> Últimos 28 dias</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted text-uppercase fw-bold">Impressões</small>
                                <h2 class="mb-0 fw-bold text-dark mt-1" id="gsc-total-impressions">-</h2>
                                <small class="text-muted">Visibilidade na busca</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted text-uppercase fw-bold">CTR Médio</small>
                                <h2 class="mb-0 fw-bold text-dark mt-1" id="gsc-avg-ctr">-</h2>
                                <small class="text-muted">Taxa de cliques</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted text-uppercase fw-bold">Posição Média</small>
                                <h2 class="mb-0 fw-bold text-warning mt-1" id="gsc-avg-position">-</h2>
                                <small class="text-muted">Ranking médio</small>
                            </div>
                        </div>
                    </div>

                    <!-- Chart -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h6 class="card-title">Performance na Pesquisa (Últimos 30 Dias)</h6>
                            <canvas id="gscPerformanceChart" height="100"></canvas>
                        </div>
                    </div>

                    <!-- Top Queries -->
                    <h5 class="mb-3">🔥 Principais Termos de Pesquisa</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Query</th>
                                    <th>Cliques</th>
                                    <th>Impressões</th>
                                    <th>CTR</th>
                                    <th>Posição</th>
                                </tr>
                            </thead>
                            <tbody id="gsc-top-queries">
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Carregando dados...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
    const GSCManager = {
        init() {
            this.checkStatus();
        },

        async checkStatus() {
            try {
                const data = await requestJson('/api/seo-killer/gsc/status');

                const statusDiv = document.getElementById('gsc-connection-status');
                const connectSection = document.getElementById('gsc-connect-section');
                const dashboardSection = document.getElementById('gsc-data-dashboard');

                if (data.success && data.status.connected) {
                    statusDiv.innerHTML = '<span class="badge bg-success p-2"><i class="bi bi-check-circle"></i> Conectado</span>';
                    connectSection.style.display = 'none';
                    dashboardSection.style.display = 'block';
                    this.loadData();
                } else {
                    statusDiv.innerHTML = '<span class="badge bg-secondary p-2">Desconectado</span>';
                    connectSection.style.display = 'block';
                    dashboardSection.style.display = 'none';
                }
            } catch (error) {
                console.error('Erro ao verificar status GSC:', error);
            }
        },

        async connect() {
            try {
                const data = await requestJson('/api/seo-killer/gsc/auth-url', {
                    method: 'POST'
                });

                if (data.success && data.url) {
                    window.location.href = data.url;
                } else {
                    alert('Erro ao iniciar conexão: ' + (data.error || 'Erro desconhecido'));
                }
            } catch (error) {
                alert('Erro ao conectar: ' + error.message);
            }
        },

        async loadData() {
            try {
                const payload = await requestJson('/api/seo-killer/gsc/data');

                if (!payload.success || !payload.data) {
                    console.warn('Nenhum dado GSC disponível:', payload.error || '');
                    return;
                }

                const data = payload.data;

                // Render KPI
                document.getElementById('gsc-total-clicks').textContent = data.clicks ?? '-';
                document.getElementById('gsc-total-impressions').textContent = (data.impressions ?? 0).toLocaleString();
                document.getElementById('gsc-avg-ctr').textContent = data.ctr ?? '0%';
                document.getElementById('gsc-avg-position').textContent = data.position ?? '-';

                // Render Chart
                const ctx = document.getElementById('gscPerformanceChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.chartLabels || [],
                        datasets: [{
                                label: 'Cliques',
                                data: data.chartClicks || [],
                                borderColor: '#4285F4',
                                backgroundColor: 'rgba(66, 133, 244, 0.1)',
                                yAxisID: 'y',
                                fill: true
                            },
                            {
                                label: 'Impressões',
                                data: data.chartImpressions || [],
                                borderColor: '#6610f2',
                                backgroundColor: 'rgba(102, 16, 242, 0.0)',
                                borderDash: [5, 5],
                                yAxisID: 'y1',
                                fill: false
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left'
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        }
                    }
                });

                // Render Table
                const tbody = document.getElementById('gsc-top-queries');
                const queries = Array.isArray(data.queries) ? data.queries : [];

                if (!queries.length) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Sem dados para o período selecionado.</td></tr>';
                    return;
                }

                tbody.innerHTML = queries.map(q => `
                <tr>
                    <td>${q.query}</td>
                    <td>${q.clicks}</td>
                    <td>${q.impressions}</td>
                    <td>${q.ctr}</td>
                    <td>${q.position}</td>
                </tr>
            `).join('');
            } catch (error) {
                console.error('Erro ao carregar dados GSC:', error);
            }
        }
    };

    // Initialize when tab is shown
    document.addEventListener('DOMContentLoaded', () => {
        const tabEl = document.querySelector('button[data-bs-target="#gsc-tab"]');
        if (tabEl) {
            tabEl.addEventListener('shown.bs.tab', event => {
                GSCManager.init();
            });
        }
    });
</script>