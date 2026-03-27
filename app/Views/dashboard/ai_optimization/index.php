<?php

declare(strict_types=1);

// AI Optimization Dashboard View
$title = 'Otimização IA';
$subtitle = 'Otimize seus anúncios com Inteligência Artificial';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<div id="ai-optimization-dashboard">
    <!-- Stats Cards -->
    <div class="stats-grid mb-4">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-boxes"></i>
            </div>
            <div class="stat-info">
                <h3 id="total-items">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </h3>
                <p>Anúncios Totais</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3 id="optimized-items">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </h3>
                <p>Otimizados</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div class="stat-info">
                <h3 id="optimization-rate">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </h3>
                <p>Taxa de Otimização</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-star"></i>
            </div>
            <div class="stat-info">
                <h3 id="avg-score">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </h3>
                <p>Score Médio</p>
            </div>
        </div>
    </div>

    <!-- Performance Chart -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-primary">
                        <i class="bi bi-graph-up"></i> Performance Após Otimização
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="performance-chart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Needing Optimization -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-danger">
                        <i class="bi bi-exclamation-triangle"></i> Anúncios para Otimizar
                    </h5>
                    <a href="#" class="btn btn-sm btn-primary" id="optimize-all-btn">
                        Otimizar Todos
                    </a>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-danger rounded-circle me-2">●</span>
                                <strong>Crítico (Score < 50)</strong>
                            </div>
                            <div>
                                <span class="badge bg-danger" id="critical-count">0</span>
                                <button class="btn btn-sm btn-outline-danger ms-2" onclick="optimizeCritical()">
                                    Otimizar
                                </button>
                            </div>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-warning rounded-circle me-2">●</span>
                                <strong>Médio (Score 50-70)</strong>
                            </div>
                            <div>
                                <span class="badge bg-warning" id="medium-count">0</span>
                                <button class="btn btn-sm btn-outline-warning ms-2" onclick="optimizeMedium()">
                                    Otimizar
                                </button>
                            </div>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-info rounded-circle me-2">●</span>
                                <strong>Melhorar (Score 70-85)</strong>
                            </div>
                            <div>
                                <span class="badge bg-info" id="low-count">0</span>
                                <button class="btn btn-sm btn-outline-info ms-2" onclick="optimizeLow()">
                                    Otimizar
                                </button>
                            </div>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-success rounded-circle me-2">●</span>
                                <strong>Bom (Score > 85)</strong>
                            </div>
                            <span class="badge bg-success" id="good-count">0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Performers -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-success">
                        <i class="bi bi-trophy"></i> Top Performers
                    </h5>
                </div>
                <div class="card-body">
                    <div id="top-performers-list">
                        <div class="text-center py-3">
                            <div class="spinner-border text-primary" role="status"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Insights and Recommendations -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-primary">
                        <i class="bi bi-lightbulb"></i> Insights e Recomendações
                    </h5>
                </div>
                <div class="card-body">
                    <div id="insights-list">
                        <div class="text-center py-3">
                            <div class="spinner-border text-primary" role="status"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body text-center py-4">
                    <h5 class="mb-3">Ações Rápidas</h5>
                    <button class="btn btn-primary btn-lg me-2" onclick="window.location.href='/dashboard/ai-optimization/batch'">
                        <i class="bi bi-stack"></i> Otimização em Lote
                    </button>
                    <button class="btn btn-outline-primary btn-lg me-2" onclick="window.location.href='/dashboard/ai-optimization/history'">
                        <i class="bi bi-clock-history"></i> Histórico
                    </button>
                    <button class="btn btn-outline-primary btn-lg" onclick="window.location.href='/dashboard/ai-optimization/settings'">
                        <i class="bi bi-gear"></i> Configurações
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script nonce="<?= CSP_NONCE ?>">

    let performanceChart = null;

    // Load dashboard data
    async function loadDashboardData() {
        try {
            const data = await requestJson('/api/ai/analytics/dashboard?days=30');

            // Update stats cards
            document.getElementById('total-items').textContent = data.optimizations?.total || 0;
            document.getElementById('optimized-items').textContent = data.optimizations?.applied || 0;
            document.getElementById('optimization-rate').textContent =
                (data.optimizations?.success_rate || 0).toFixed(1) + '%';
            document.getElementById('avg-score').textContent = data.performance?.avg_score_after || 0;

            // Update pending counts
            if (data.pending) {
                document.getElementById('critical-count').textContent = data.pending.critical || 0;
                document.getElementById('medium-count').textContent = data.pending.medium || 0;
                document.getElementById('low-count').textContent = data.pending.low || 0;
                document.getElementById('good-count').textContent = data.pending.good || 0;
            }

            // Load performance chart
            loadPerformanceChart(data.performance);

            // Load top performers
            loadTopPerformers();

            // Load insights
            loadInsights(data);

        } catch (error) {
            console.error('Error loading dashboard data:', error);
            showError('Erro ao carregar dados do dashboard');
        }
    }

    // Load performance chart
    function loadPerformanceChart(performance) {
        const ctx = document.getElementById('performance-chart');
        if (!ctx) return;

        if (performanceChart) {
            performanceChart.destroy();
        }

        performanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: performance?.dates || [],
                datasets: [{
                        label: 'Views',
                        data: performance?.views || [],
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Visitas',
                        data: performance?.visits || [],
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Vendas',
                        data: performance?.sales || [],
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Load top performers
    // Load top performers
    async function loadTopPerformers() {
        try {
            const data = await requestJson('/api/seo-killer/performance/top?limit=5');

            // Handle Controller's json wrapper if present
            const items = data.results || data;

            if (!Array.isArray(items) || items.length === 0) {
                document.getElementById('top-performers-list').innerHTML =
                    '<div class="text-muted text-center py-3">Nenhum dado de performance ainda</div>';
                return;
            }

            const html = items.map((item, index) => {
                const scoreImp = parseInt(item.score_improvement) || 0;
                const improvementText = scoreImp > 0 ? `+${scoreImp} pts` : 'stable';
                const improvementClass = scoreImp > 0 ? 'text-success' : 'text-muted';

                return `
            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                <div style="max-width: 60%;">
                    <span class="badge bg-success me-2">${index + 1}</span>
                    <strong class="text-truncate d-inline-block align-middle" style="max-width: 80%;" title="${item.title}">
                        ${item.title || item.item_id}
                    </strong>
                </div>
                <div class="text-end">
                    <div><span class="badge bg-primary">Score: ${parseInt(item.current_score) || 0}</span></div>
                    <small class="${improvementClass}">${improvementText}</small>
                </div>
            </div>
            `;
            }).join('');

            document.getElementById('top-performers-list').innerHTML = html;

        } catch (error) {
            console.error('Error loading top performers:', error);
            document.getElementById('top-performers-list').innerHTML =
                '<div class="text-danger text-center py-3">Erro ao carregar dados</div>';
        }
    }

    // Load insights
    function loadInsights(data) {
        const insights = [];

        if (data.pending?.critical > 0) {
            insights.push({
                type: 'danger',
                icon: 'exclamation-triangle',
                text: `${data.pending.critical} anúncios com score crítico - Otimize agora!`
            });
        }

        if (data.pending?.medium > 0) {
            insights.push({
                type: 'warning',
                icon: 'info-circle',
                text: `${data.pending.medium} anúncios podem melhorar com IA`
            });
        }

        if (data.roi?.roi_percentage > 1000) {
            insights.push({
                type: 'success',
                icon: 'graph-up-arrow',
                text: `ROI excelente de ${data.roi.roi_percentage.toFixed(0)}%!`
            });
        }

        insights.push({
            type: 'info',
            icon: 'lightbulb',
            text: 'Títulos otimizados têm +89% CTR em média'
        });

        insights.push({
            type: 'info',
            icon: 'lightbulb',
            text: 'Descrições com IA aumentam conversão em 67%'
        });

        const html = insights.map(insight => `
        <div class="alert alert-${insight.type} d-flex align-items-center mb-2">
            <i class="bi bi-${insight.icon} me-2"></i>
            <span>${insight.text}</span>
        </div>
    `).join('');

        document.getElementById('insights-list').innerHTML = html;
    }

    // Optimize actions
    function optimizeCritical() {
        window.location.href = '/dashboard/ai-optimization/batch?priority=critical';
    }

    function optimizeMedium() {
        window.location.href = '/dashboard/ai-optimization/batch?priority=medium';
    }

    function optimizeLow() {
        window.location.href = '/dashboard/ai-optimization/batch?priority=low';
    }

    function showError(message) {
        const alertHtml = `
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
        document.getElementById('ai-optimization-dashboard').insertAdjacentHTML('afterbegin', alertHtml);
    }

    // Initialize dashboard
    document.addEventListener('DOMContentLoaded', function() {
        loadDashboardData();

        // Refresh every 30 seconds
        setInterval(loadDashboardData, 30000);
    });
</script>

<?php include __DIR__ . '/../../layouts/modern/partials/page-footer.php'; ?>
