<?php
$title = 'Métricas de Clonagem';
$subtitle = 'Observabilidade e análise de jobs de clonagem em lote';
include __DIR__ . '/../layouts/modern/partials/page-header.php';
?>

<style>
.metric-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 1.5rem; height: 100%; }
.metric-value { font-size: 2rem; font-weight: 700; line-height: 1.2; }
.metric-label { font-size: 0.875rem; color: #6c757d; margin-top: 0.25rem; }
.metric-change { font-size: 0.75rem; margin-top: 0.5rem; }
.metric-change.up { color: #198754; }
.metric-change.down { color: #dc3545; }
.metric-change.stable { color: #6c757d; }
.chart-container { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 1.5rem; }
.chart-title { font-weight: 600; margin-bottom: 1rem; }
.job-row { padding: 0.75rem 1rem; border-bottom: 1px solid #f0f0f0; }
.job-row:hover { background: #f8f9fa; }
.error-item { padding: 0.75rem; border-left: 3px solid #dc3545; background: #f8f9fa; margin-bottom: 0.5rem; border-radius: 0 4px 4px 0; }
.error-count { background: #dc3545; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; }
.template-stat { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #f0f0f0; }
.template-stat:last-child { border-bottom: none; }
.template-bar { height: 8px; background: #e9ecef; border-radius: 4px; flex: 1; margin: 0 1rem; overflow: hidden; }
.template-bar-fill { height: 100%; background: linear-gradient(90deg, #0d6efd, #6610f2); border-radius: 4px; }
</style>

<!-- Period Selector -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/dashboard/catalog/clone/batch" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar ao Clonador
        </a>
    </div>
    <div class="btn-group" role="group" id="period-selector">
        <button type="button" class="btn btn-outline-primary btn-sm active" data-days="7">7 dias</button>
        <button type="button" class="btn btn-outline-primary btn-sm" data-days="30">30 dias</button>
        <button type="button" class="btn btn-outline-primary btn-sm" data-days="90">90 dias</button>
    </div>
</div>

<!-- Main Metrics -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="metric-card">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-files fs-4 text-primary me-2"></i>
                <span class="text-muted">Total Clonados</span>
            </div>
            <div class="metric-value text-primary" id="metric-total">-</div>
            <div class="metric-change" id="metric-total-change"></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-briefcase fs-4 text-info me-2"></i>
                <span class="text-muted">Jobs Executados</span>
            </div>
            <div class="metric-value text-info" id="metric-jobs">-</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-check-circle fs-4 text-success me-2"></i>
                <span class="text-muted">Taxa de Sucesso</span>
            </div>
            <div class="metric-value text-success" id="metric-success-rate">-</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-speedometer2 fs-4 text-warning me-2"></i>
                <span class="text-muted">Velocidade Média</span>
            </div>
            <div class="metric-value text-warning" id="metric-speed">-</div>
            <div class="metric-label">itens/min</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="chart-container">
            <h6 class="chart-title"><i class="bi bi-graph-up me-2"></i>Clonagens por Dia</h6>
            <canvas id="chart-timeline" height="250"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="chart-container h-100">
            <h6 class="chart-title"><i class="bi bi-pie-chart me-2"></i>Por Template</h6>
            <div id="template-stats">
                <div class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm"></div>
                    <p class="mb-0 mt-2">Carregando...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Jobs & Errors Row -->
<div class="row g-4">
    <div class="col-lg-7">
        <div class="chart-container">
            <h6 class="chart-title"><i class="bi bi-clock-history me-2"></i>Jobs Recentes</h6>
            <div id="recent-jobs" style="max-height: 350px; overflow-y: auto;">
                <div class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="chart-container">
            <h6 class="chart-title"><i class="bi bi-exclamation-triangle me-2"></i>Erros Mais Frequentes</h6>
            <div id="top-errors" style="max-height: 350px; overflow-y: auto;">
                <div class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
async function requestJson(url, options = {}) {
    if (window.ApiClient) return window.ApiClient.request(url, options);
    const resp = await fetch(url, { credentials: 'include', ...options });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}

const CloneMetrics = {
    chart: null,
    days: 30,

    init() {
        this.bindEvents();
        this.loadDashboard();
    },

    bindEvents() {
        document.querySelectorAll('#period-selector button').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#period-selector button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.days = parseInt(btn.dataset.days);
                this.loadDashboard();
            });
        });
    },

    async loadDashboard() {
        try {
            const [dashboard, jobs, errors, weekly] = await Promise.all([
                requestJson(`/api/catalog/clone/metrics/dashboard?days=${this.days}`),
                requestJson(`/api/catalog/clone/metrics/jobs?limit=10`),
                requestJson(`/api/catalog/clone/metrics/errors?days=${this.days}&limit=10`),
                requestJson(`/api/catalog/clone/metrics/weekly`)
            ]);

            if (dashboard.status === 'success') {
                this.renderMainMetrics(dashboard.dashboard, weekly);
                this.renderTimeline(dashboard.dashboard.time_series);
                this.renderTemplateStats(dashboard.dashboard.by_template);
            }

            if (jobs.status === 'success') {
                this.renderRecentJobs(jobs.jobs);
            }

            if (errors.status === 'success') {
                this.renderTopErrors(errors.errors);
            }

        } catch (err) {
            console.error('Erro ao carregar métricas:', err);
        }
    },

    renderMainMetrics(data, weekly) {
        const totals = data.totals || {};
        
        document.getElementById('metric-total').textContent = this.formatNumber(totals.total_cloned || 0);
        document.getElementById('metric-jobs').textContent = this.formatNumber(totals.total_jobs || 0);
        document.getElementById('metric-success-rate').textContent = (totals.avg_success_rate || 0).toFixed(1) + '%';
        document.getElementById('metric-speed').textContent = (totals.avg_items_per_minute || 0).toFixed(1);

        // Weekly comparison
        if (weekly.status === 'success') {
            const comp = weekly.comparison || {};
            const changeEl = document.getElementById('metric-total-change');
            const change = comp.change_percent || 0;
            
            if (change > 0) {
                changeEl.innerHTML = `<i class="bi bi-arrow-up"></i> +${change}% vs semana passada`;
                changeEl.className = 'metric-change up';
            } else if (change < 0) {
                changeEl.innerHTML = `<i class="bi bi-arrow-down"></i> ${change}% vs semana passada`;
                changeEl.className = 'metric-change down';
            } else {
                changeEl.innerHTML = `<i class="bi bi-dash"></i> Estável vs semana passada`;
                changeEl.className = 'metric-change stable';
            }
        }
    },

    renderTimeline(data) {
        const ctx = document.getElementById('chart-timeline').getContext('2d');

        if (this.chart) {
            this.chart.destroy();
        }

        const labels = (data || []).map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
        });
        const values = (data || []).map(d => parseInt(d.count) || 0);

        this.chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Clonagens',
                    data: values,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    },

    renderTemplateStats(data) {
        const container = document.getElementById('template-stats');
        
        if (!data || data.length === 0) {
            container.innerHTML = '<p class="text-muted text-center py-3">Nenhum dado de template</p>';
            return;
        }

        const total = data.reduce((sum, t) => sum + parseInt(t.count), 0);
        
        let html = '';
        data.forEach(t => {
            const percent = total > 0 ? ((parseInt(t.count) / total) * 100).toFixed(1) : 0;
            const name = t.template === 'sem_template' ? 'Sem template' : t.template;
            
            html += `
                <div class="template-stat">
                    <span class="text-truncate" style="max-width: 100px;">${name}</span>
                    <div class="template-bar">
                        <div class="template-bar-fill" style="width: ${percent}%"></div>
                    </div>
                    <span class="badge bg-light text-dark">${t.count}</span>
                </div>
            `;
        });

        container.innerHTML = html;
    },

    renderRecentJobs(jobs) {
        const container = document.getElementById('recent-jobs');

        if (!jobs || jobs.length === 0) {
            container.innerHTML = '<p class="text-muted text-center py-3">Nenhum job recente</p>';
            return;
        }

        let html = '';
        jobs.forEach(job => {
            const statusBadge = this.getStatusBadge(job.status);
            const successRate = job.success_rate !== null ? job.success_rate + '%' : '-';
            const duration = job.duration_seconds ? this.formatDuration(job.duration_seconds) : '-';
            const date = job.completed_at ? new Date(job.completed_at).toLocaleDateString('pt-BR') : '-';

            html += `
                <div class="job-row d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-semibold">${job.job_id}</div>
                        <small class="text-muted">Conta ${job.target_account_id} | ${date}</small>
                    </div>
                    <div class="text-end">
                        <div>${statusBadge}</div>
                        <small class="text-muted">${job.successful_items || 0}/${job.total_items || 0} itens | ${duration}</small>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    },

    renderTopErrors(errors) {
        const container = document.getElementById('top-errors');

        if (!errors || errors.length === 0) {
            container.innerHTML = '<p class="text-success text-center py-3"><i class="bi bi-check-circle fs-4"></i><br>Nenhum erro registrado!</p>';
            return;
        }

        let html = '';
        errors.forEach(err => {
            const message = err.error_message || 'Erro desconhecido';
            const truncated = message.length > 100 ? message.substring(0, 100) + '...' : message;
            
            html += `
                <div class="error-item">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <small class="text-muted">Ocorrências:</small>
                        <span class="error-count">${err.count}</span>
                    </div>
                    <div class="text-truncate" title="${message}">${truncated}</div>
                </div>
            `;
        });

        container.innerHTML = html;
    },

    getStatusBadge(status) {
        const badges = {
            'completed': '<span class="badge bg-success">Concluído</span>',
            'completed_with_errors': '<span class="badge bg-warning text-dark">Com erros</span>',
            'failed': '<span class="badge bg-danger">Falhou</span>',
            'processing': '<span class="badge bg-info">Em andamento</span>',
            'pending': '<span class="badge bg-secondary">Pendente</span>'
        };
        return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
    },

    formatNumber(num) {
        if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
        if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
        return num.toString();
    },

    formatDuration(seconds) {
        if (seconds < 60) return seconds + 's';
        if (seconds < 3600) return Math.floor(seconds / 60) + 'm ' + (seconds % 60) + 's';
        return Math.floor(seconds / 3600) + 'h ' + Math.floor((seconds % 3600) / 60) + 'm';
    }
};

document.addEventListener('DOMContentLoaded', () => CloneMetrics.init());
</script>
