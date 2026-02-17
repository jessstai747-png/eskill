<!-- 📊 Performance Analytics Dashboard - Enhanced Version -->
<div class="card mb-4">
    <div class="card-header bg-gradient-success text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-graph-up-arrow"></i> Analytics de Performance
            </h5>
            <div class="btn-group">
                <button class="btn btn-sm btn-light" onclick="SEOKiller.loadPerformanceAnalytics()">
                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                </button>
                <button class="btn btn-sm btn-light" onclick="SEOKiller.exportPerformanceReport()">
                    <i class="bi bi-download"></i> Exportar
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Loading State -->
        <div id="performanceAnalyticsLoading" class="text-center py-5">
            <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="mt-3 text-muted">Analisando dados de performance...</p>
        </div>

        <!-- Content -->
        <div id="performanceAnalyticsContent" style="display: none;">
            <!-- KPI Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-box-seam text-primary fs-3"></i>
                            <h6 class="text-muted mt-2">Itens Otimizados</h6>
                            <h3 id="kpiItemsOptimized">0</h3>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-graph-up text-success fs-3"></i>
                            <h6 class="text-muted mt-2">Melhoria Média</h6>
                            <h3 id="kpiAvgImprovement">+0</h3>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-currency-dollar text-warning fs-3"></i>
                            <h6 class="text-muted mt-2">Impacto em Receita</h6>
                            <h3 id="kpiRevenueImpact">R$ 0</h3>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-trophy text-danger fs-3"></i>
                            <h6 class="text-muted mt-2">ROI Médio</h6>
                            <h3 id="kpiAvgROI">0%</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <!-- Evolution Chart -->
                <div class="col-md-8 mb-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">
                                <i class="bi bi-graph-up"></i> Evolução de Métricas (30 dias)
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="metricsEvolutionChart" height="250"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Category Performance -->
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">
                                <i class="bi bi-pie-chart"></i> Top Categorias
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryPerformanceChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Performers Table -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0">
                        <i class="bi bi-trophy-fill text-warning"></i> Top 10 Performers
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Item</th>
                                    <th>Score</th>
                                    <th>Vendas</th>
                                    <th>Receita</th>
                                    <th>ROI</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="topPerformersTableBody">
                                <!-- Items will be inserted here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <strong>📊 Resumo dos últimos 30 dias:</strong><br>
                        <span id="summaryViews">0</span> visualizações/dia •
                        <span id="summarySales">0</span> vendas/dia •
                        R$ <span id="summaryRevenue">0</span> em receita
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-success">
                        <strong>✅ Performance positiva:</strong>
                        <span id="positiveImpactCount">0</span> itens com ROI positivo
                        (<span id="positiveImpactPercentage">0</span>% do total)
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-gradient-success {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .performance-badge-up {
        background: #d4edda;
        color: #155724;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: bold;
    }

    .performance-badge-down {
        background: #f8d7da;
        color: #721c24;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: bold;
    }
</style>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
    // Performance Analytics Functions
    if (!window.SEOKiller) window.SEOKiller = {};

    // Chart instances
    SEOKiller.charts = {
        evolution: null,
        categories: null
    };

    SEOKiller.loadPerformanceAnalytics = async function() {
        const loading = document.getElementById('performanceAnalyticsLoading');
        const content = document.getElementById('performanceAnalyticsContent');

        loading.style.display = 'block';
        content.style.display = 'none';

        try {
            // Load all data in parallel
            const [{
                    data: consolidated
                },
                {
                    data: evolution
                },
                {
                    data: topPerformers
                },
                {
                    data: categories
                }
            ] = await Promise.all([
                requestJson('/api/seo-killer/performance/consolidated'),
                requestJson('/api/seo-killer/performance/evolution?days=30'),
                requestJson('/api/seo-killer/performance/top?limit=10'),
                requestJson('/api/seo-killer/performance/categories')
            ]);

            // Update KPIs
            document.getElementById('kpiItemsOptimized').textContent = consolidated.total_items_optimized || 0;
            document.getElementById('kpiAvgImprovement').textContent = '+' + (consolidated.avg_score_improvement || 0);
            document.getElementById('kpiRevenueImpact').textContent = 'R$ ' + (consolidated.total_revenue_impact || 0).toFixed(2);
            document.getElementById('kpiAvgROI').textContent = (consolidated.avg_roi || 0) + '%';

            // Update summary
            document.getElementById('summaryViews').textContent = evolution.summary.avg_daily_views || 0;
            document.getElementById('summarySales').textContent = evolution.summary.avg_daily_sales || 0;
            document.getElementById('summaryRevenue').textContent = (evolution.summary.total_revenue || 0).toFixed(2);

            document.getElementById('positiveImpactCount').textContent = consolidated.items_with_positive_impact || 0;
            const positivePercentage = consolidated.total_items_optimized > 0 ?
                Math.round((consolidated.items_with_positive_impact / consolidated.total_items_optimized) * 100) :
                0;
            document.getElementById('positiveImpactPercentage').textContent = positivePercentage;

            // Render charts
            this.renderEvolutionChart(evolution);
            this.renderCategoryChart(categories);

            // Render top performers table
            this.renderTopPerformersTable(topPerformers);

            loading.style.display = 'none';
            content.style.display = 'block';

        } catch (error) {
            console.error('Erro ao carregar analytics:', error);
            loading.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                Erro ao carregar dados: ${error.message}
            </div>
        `;
        }
    };

    SEOKiller.renderEvolutionChart = function(data) {
        const ctx = document.getElementById('metricsEvolutionChart');

        // Destroy previous chart
        if (this.charts.evolution) {
            this.charts.evolution.destroy();
        }

        this.charts.evolution = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                        label: 'Visualizações',
                        data: data.datasets.views,
                        borderColor: '#0066FF',
                        backgroundColor: 'rgba(0, 102, 255, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Vendas',
                        data: data.datasets.sales,
                        borderColor: '#00A650',
                        backgroundColor: 'rgba(0, 166, 80, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Receita (R$)',
                        data: data.datasets.revenue,
                        borderColor: '#FFA500',
                        backgroundColor: 'rgba(255, 165, 0, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.datasetIndex === 2) {
                                    label += 'R$ ' + context.parsed.y.toFixed(2);
                                } else {
                                    label += context.parsed.y;
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Views / Vendas'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Receita (R$)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    };

    SEOKiller.renderCategoryChart = function(categories) {
        const ctx = document.getElementById('categoryPerformanceChart');

        // Destroy previous chart
        if (this.charts.categories) {
            this.charts.categories.destroy();
        }

        // Take top 5 categories
        const topCategories = categories.slice(0, 5);

        this.charts.categories = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: topCategories.map(c => c.category_name || 'Desconhecida'),
                datasets: [{
                    data: topCategories.map(c => c.total_revenue || 0),
                    backgroundColor: [
                        '#0066FF',
                        '#00A650',
                        '#FFA500',
                        '#FF3333',
                        '#9C27B0'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': R$ ' + context.parsed.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
    };

    SEOKiller.renderTopPerformersTable = function(performers) {
        const tbody = document.getElementById('topPerformersTableBody');

        if (!performers || performers.length === 0) {
            tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-4">
                    Nenhum dado de performance ainda
                </td>
            </tr>
        `;
            return;
        }

        tbody.innerHTML = performers.map((item, index) => {
            const scoreImprovement = (item.score_after || 0) - (item.score_before || 0);
            const badgeClass = scoreImprovement > 0 ? 'performance-badge-up' : 'performance-badge-down';
            const icon = scoreImprovement > 0 ? '↑' : '↓';

            return `
            <tr>
                <td><strong>${index + 1}</strong></td>
                <td>
                    <div><strong>${item.title || item.item_id}</strong></div>
                    <small class="text-muted">${item.item_id}</small>
                </td>
                <td>
                    <span class="${badgeClass}">
                        ${item.score_before || 0} ${icon} ${item.score_after || 0}
                    </span>
                </td>
                <td>${item.total_sales || 0}</td>
                <td>R$ ${(item.revenue_increase || 0).toFixed(2)}</td>
                <td>
                    <strong class="${item.roi_percentage > 0 ? 'text-success' : 'text-danger'}">
                        ${item.roi_percentage || 0}%
                    </strong>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" 
                            onclick="SEOKiller.viewItemDetails('${item.item_id}')">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>
        `;
        }).join('');
    };

    SEOKiller.viewItemDetails = async function(itemId) {
        try {
            const {
                data
            } = await requestJson(`/api/seo-killer/performance/compare/${itemId}`);

            // Simple alert for now - can be improved to modal
            alert(`Performance de ${itemId}\n\n` +
                `Score: ${data.before?.score || 0} → ${data.after?.score || 0}\n` +
                `Vendas: ${data.before?.total_sales || 0} → ${data.after?.total_sales || 0}\n` +
                `ROI: ${data.roi?.roi_percentage || 0}%`);
        } catch (error) {
            alert('Erro ao carregar detalhes: ' + error.message);
        }
    };

    SEOKiller.exportPerformanceReport = async function() {
        const format = confirm('Exportar em CSV?\n\nOK = CSV\nCancelar = JSON') ? 'csv' : 'json';

        try {
            const url = `/api/seo-killer/performance/export?format=${format}`;
            window.open(url, '_blank');

            Toastify({
                text: "Relatório exportado com sucesso!",
                duration: 3000,
                backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
            }).showToast();
        } catch (error) {
            Toastify({
                text: "Erro ao exportar: " + error.message,
                duration: 3000,
                backgroundColor: "linear-gradient(to right, #ff5f6d, #ffc371)"
            }).showToast();
        }
    };

    // Auto-load on page ready
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we're on the SEO Killer dashboard
        if (document.getElementById('performanceAnalyticsContent')) {
            SEOKiller.loadPerformanceAnalytics();
        }
    });
</script>