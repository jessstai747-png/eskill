<!-- 🤖 AutoPilot Stats Dashboard Component -->
<div class="card mb-4">
    <div class="card-header bg-gradient-primary text-white">
        <h5 class="mb-0">
            <i class="bi bi-robot"></i> AutoPilot - Estatísticas & Histórico
        </h5>
    </div>
    <div class="card-body">
        <!-- Loading State -->
        <div id="autopilotStatsLoading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="mt-3 text-muted">Carregando estatísticas do AutoPilot...</p>
        </div>

        <!-- Content -->
        <div id="autopilotStatsContent" style="display: none;">
            <!-- Overview Stats -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Total de Execuções</h6>
                            <h2 id="apTotalRuns">0</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Itens Otimizados</h6>
                            <h2 id="apTotalOptimizations">0</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Melhoria Média</h6>
                            <h2 id="apAvgImprovement">+0</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Score Atual</h6>
                            <h2 id="apCurrentScore">0</h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Last 30 Days Stats -->
            <div class="alert alert-info">
                <strong>Últimos 30 dias:</strong>
                <span id="apRuns30d">0</span> execuções •
                <span id="apItems30d">0</span> itens otimizados •
                <span id="apFailures30d">0</span> falhas
            </div>

            <!-- Next Run Info -->
            <div class="alert alert-primary" id="apNextRunAlert">
                <i class="bi bi-clock"></i>
                <strong>Próxima Execução:</strong> <span id="apNextRun">N/A</span>
            </div>

            <!-- Last Run Details -->
            <div class="card mb-4" id="apLastRunCard" style="display: none;">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-clock-history"></i> Última Execução
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Status:</strong> <span id="apLastStatus"></span></p>
                            <p><strong>Itens Otimizados:</strong> <span id="apLastItems">0</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Score Antes:</strong> <span id="apLastScoreBefore">0</span></p>
                            <p><strong>Score Depois:</strong> <span id="apLastScoreAfter">0</span></p>
                        </div>
                    </div>
                    <p><strong>Concluída em:</strong> <span id="apLastCompleted">N/A</span></p>
                </div>
            </div>

            <!-- History Table -->
            <h6 class="mb-3">
                <i class="bi bi-list-ul"></i> Histórico de Execuções
            </h6>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Itens</th>
                            <th>Score Médio</th>
                            <th>Melhoria</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody id="autopilotHistoryTableBody">
                        <!-- History will be inserted here -->
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div id="apEmptyState" style="display: none;" class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p class="mt-3">Nenhuma execução do AutoPilot ainda</p>
                <button class="btn btn-primary" onclick="SEOKiller.openAutopilotConfig()">
                    Configurar AutoPilot
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .autopilot-improvement-positive {
        color: #198754;
        font-weight: bold;
    }

    .autopilot-improvement-negative {
        color: #dc3545;
        font-weight: bold;
    }
</style>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
    // AutoPilot Stats Functions
    if (!window.SEOKiller) window.SEOKiller = {};

    SEOKiller.loadAutopilotStats = async function() {
        const loading = document.getElementById('autopilotStatsLoading');
        const content = document.getElementById('autopilotStatsContent');

        loading.style.display = 'block';
        content.style.display = 'none';

        try {
            // Load stats
            const {
                data: stats
            } = await requestJson('/api/seo-killer/autopilot/stats');

            if (stats.error) {
                throw new Error(stats.error);
            }

            // Load history
            const {
                data: history
            } = await requestJson('/api/seo-killer/autopilot/history?limit=20');

            // Update overview stats
            document.getElementById('apTotalRuns').textContent = stats.total_runs || 0;
            document.getElementById('apTotalOptimizations').textContent = stats.total_optimizations || 0;

            const improvement = stats.avg_improvement || 0;
            const improvementEl = document.getElementById('apAvgImprovement');
            improvementEl.textContent = (improvement > 0 ? '+' : '') + improvement;
            improvementEl.className = improvement > 0 ? 'autopilot-improvement-positive' : '';

            document.getElementById('apCurrentScore').textContent = stats.current_avg_score || 0;

            // Update 30 days stats
            document.getElementById('apRuns30d').textContent = stats.runs_last_30_days || 0;
            document.getElementById('apItems30d').textContent = stats.items_optimized_30d || 0;
            document.getElementById('apFailures30d').textContent = stats.total_failures || 0;

            // Update next run
            if (stats.next_run) {
                document.getElementById('apNextRun').textContent = this.formatDateTime(stats.next_run);
                document.getElementById('apNextRunAlert').style.display = 'block';
            } else {
                document.getElementById('apNextRunAlert').style.display = 'none';
            }

            // Update last run details
            if (stats.last_run_details) {
                const lastRun = stats.last_run_details;
                document.getElementById('apLastStatus').innerHTML = this.getAutopilotStatusBadge(lastRun.status);
                document.getElementById('apLastItems').textContent = lastRun.items_optimized || 0;
                document.getElementById('apLastScoreBefore').textContent = lastRun.avg_score_before || 0;
                document.getElementById('apLastScoreAfter').textContent = lastRun.avg_score_after || 0;
                document.getElementById('apLastCompleted').textContent = this.formatDateTime(lastRun.completed_at);
                document.getElementById('apLastRunCard').style.display = 'block';
            } else {
                document.getElementById('apLastRunCard').style.display = 'none';
            }

            // Render history table
            if (history.length > 0) {
                this.renderAutopilotHistoryTable(history);
                document.getElementById('apEmptyState').style.display = 'none';
            } else {
                document.getElementById('autopilotHistoryTableBody').innerHTML = '';
                document.getElementById('apEmptyState').style.display = 'block';
            }

            loading.style.display = 'none';
            content.style.display = 'block';

        } catch (error) {
            console.error('Erro ao carregar stats do AutoPilot:', error);
            loading.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                Erro ao carregar dados: ${error.message}
            </div>
        `;
        }
    };

    SEOKiller.renderAutopilotHistoryTable = function(history) {
        const tbody = document.getElementById('autopilotHistoryTableBody');

        tbody.innerHTML = history.map(run => {
            const improvement = (run.avg_score_after || 0) - (run.avg_score_before || 0);
            const improvementClass = improvement > 0 ? 'text-success' : improvement < 0 ? 'text-danger' : 'text-muted';
            const improvementIcon = improvement > 0 ? '↑' : improvement < 0 ? '↓' : '→';

            return `
            <tr>
                <td><strong>#${run.id}</strong></td>
                <td>${this.getAutopilotStatusBadge(run.status)}</td>
                <td>
                    <div>
                        <strong>${run.items_optimized || 0}</strong> otimizados
                    </div>
                    <small class="text-muted">
                        ${run.items_analyzed || 0} analisados
                    </small>
                </td>
                <td>
                    <div>
                        <strong>${run.avg_score_before || 0}</strong> → 
                        <strong>${run.avg_score_after || 0}</strong>
                    </div>
                </td>
                <td class="${improvementClass}">
                    <strong>${improvementIcon} ${improvement > 0 ? '+' : ''}${improvement.toFixed(1)}</strong>
                </td>
                <td>
                    <small>${this.formatDateTime(run.created_at)}</small>
                </td>
            </tr>
        `;
        }).join('');
    };

    SEOKiller.getAutopilotStatusBadge = function(status) {
        const badges = {
            'scheduled': '<span class="badge bg-secondary">Agendado</span>',
            'running': '<span class="badge bg-primary">Executando</span>',
            'completed': '<span class="badge bg-success">Concluído</span>',
            'failed': '<span class="badge bg-danger">Falhou</span>'
        };
        return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
    };

    // Auto-load on page ready
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we're on the SEO Killer dashboard
        if (document.getElementById('autopilotStatsContent')) {
            SEOKiller.loadAutopilotStats();
        }
    });
</script>