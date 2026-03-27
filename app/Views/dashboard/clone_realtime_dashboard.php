<?php

declare(strict_types=1);

/**
 * Clone Real-Time Dashboard View
 * 
 * Dashboard com atualização em tempo real via SSE
 */

$pageTitle = 'Dashboard em Tempo Real';
$extraCss = [];
$extraJs = [];

ob_start();
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-activity text-primary"></i>
                        Dashboard em Tempo Real
                    </h1>
                    <p class="text-muted mb-0">
                        <span id="connectionStatus" class="badge bg-warning">Conectando...</span>
                        <span id="lastUpdate" class="ms-2">-</span>
                    </p>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-primary" onclick="toggleConnection()">
                        <i class="bi bi-arrow-repeat me-1"></i>
                        <span id="toggleBtnText">Pausar</span>
                    </button>
                    <a href="/dashboard/catalog/clone-batch" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>
                        Nova Clonagem
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- System Health -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="systemHealthCard">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div id="healthIndicator" class="rounded-circle me-3" 
                                 style="width: 12px; height: 12px; background-color: #ffc107;"></div>
                            <span class="fw-medium">Sistema: </span>
                            <span id="healthStatus" class="ms-1">Verificando...</span>
                        </div>
                        <div id="healthIssues" class="text-muted small"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4" id="statsRow">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">Jobs Ativos</h6>
                            <h2 class="mb-0" id="statActiveJobs">-</h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-play-circle text-primary fs-4"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted" id="statActiveJobsDetail">-</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">Clonados (24h)</h6>
                            <h2 class="mb-0" id="statCloned24h">-</h2>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-files text-success fs-4"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-success" id="statCloned24hRate">-</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">Taxa de Sucesso</h6>
                            <h2 class="mb-0" id="statSuccessRate">-</h2>
                        </div>
                        <div class="bg-info bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-graph-up text-info fs-4"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted" id="statSuccessRateDetail">-</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">Alertas Ativos</h6>
                            <h2 class="mb-0" id="statAlerts">-</h2>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-warning" id="statAlertsDetail">-</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Active Jobs -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-collection-play text-primary me-2"></i>
                        Jobs em Execução
                    </h5>
                    <span class="badge bg-primary" id="activeJobsCount">0</span>
                </div>
                <div class="card-body p-0">
                    <div id="activeJobsList">
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-hourglass-split fs-1 d-block mb-2"></i>
                            Aguardando dados...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts & Recent Activity -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#alertsTab">
                                <i class="bi bi-bell me-1"></i>
                                Alertas
                                <span class="badge bg-danger ms-1" id="alertsBadge">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#rateTab">
                                <i class="bi bi-speedometer2 me-1"></i>
                                Taxa
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="alertsTab">
                            <div id="alertsList" class="list-group list-group-flush">
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
                                    Nenhum alerta ativo
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="rateTab">
                            <div class="p-4">
                                <div class="mb-4">
                                    <label class="text-muted small">Jobs por Minuto</label>
                                    <h3 class="mb-0" id="rateJobs">0.0</h3>
                                </div>
                                <div class="mb-4">
                                    <label class="text-muted small">Itens por Minuto</label>
                                    <h3 class="mb-0" id="rateItems">0.0</h3>
                                </div>
                                <div>
                                    <label class="text-muted small">Última Hora</label>
                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <div class="text-center p-3 bg-light rounded">
                                                <div class="fs-4 fw-bold text-success" id="hourJobs">0</div>
                                                <small class="text-muted">Jobs</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center p-3 bg-light rounded">
                                                <div class="fs-4 fw-bold text-primary" id="hourItems">0</div>
                                                <small class="text-muted">Itens</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Completed Jobs -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history text-secondary me-2"></i>
                        Jobs Recentes
                    </h5>
                    <a href="/dashboard/catalog/clone-metrics" class="btn btn-sm btn-outline-primary">
                        Ver Todos
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Job ID</th>
                                    <th>Status</th>
                                    <th>Itens</th>
                                    <th>Sucesso</th>
                                    <th>Duração</th>
                                    <th>Concluído</th>
                                </tr>
                            </thead>
                            <tbody id="recentJobsTable">
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        Carregando...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.job-progress-bar {
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
    background-color: #e9ecef;
}
.job-progress-bar .progress-bar {
    transition: width 0.5s ease-in-out;
}
.pulse-dot {
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}
.alert-item {
    border-left: 4px solid;
    transition: background-color 0.2s;
}
.alert-item:hover {
    background-color: #f8f9fa;
}
.alert-critical { border-left-color: #dc3545; }
.alert-warning { border-left-color: #ffc107; }
.alert-info { border-left-color: #0dcaf0; }
</style>

<script nonce="<?= CSP_NONCE ?>">

let eventSource = null;
let isConnected = false;
let reconnectAttempts = 0;
const maxReconnectAttempts = 5;

document.addEventListener('DOMContentLoaded', function() {
    connectSSE();
    loadRecentJobs();
});

function connectSSE() {
    if (eventSource) {
        eventSource.close();
    }

    updateConnectionStatus('connecting');

    eventSource = new EventSource('/api/catalog/clone/dashboard/stream');

    eventSource.onopen = function() {
        isConnected = true;
        reconnectAttempts = 0;
        updateConnectionStatus('connected');
    };

    eventSource.onmessage = function(event) {
        try {
            const data = JSON.parse(event.data);
            updateDashboard(data);
            document.getElementById('lastUpdate').textContent = 
                'Atualizado: ' + new Date().toLocaleTimeString('pt-BR');
        } catch (e) {
            console.error('Error parsing SSE data:', e);
        }
    };

    eventSource.onerror = function() {
        isConnected = false;
        updateConnectionStatus('disconnected');
        
        if (reconnectAttempts < maxReconnectAttempts) {
            reconnectAttempts++;
            setTimeout(connectSSE, 3000 * reconnectAttempts);
        }
    };
}

function toggleConnection() {
    const btn = document.getElementById('toggleBtnText');
    
    if (isConnected && eventSource) {
        eventSource.close();
        isConnected = false;
        updateConnectionStatus('paused');
        btn.textContent = 'Retomar';
    } else {
        reconnectAttempts = 0;
        connectSSE();
        btn.textContent = 'Pausar';
    }
}

function updateConnectionStatus(status) {
    const badge = document.getElementById('connectionStatus');
    const statuses = {
        'connecting': { class: 'bg-warning', text: 'Conectando...' },
        'connected': { class: 'bg-success', text: '● Conectado' },
        'disconnected': { class: 'bg-danger', text: 'Desconectado' },
        'paused': { class: 'bg-secondary', text: 'Pausado' },
    };
    
    const s = statuses[status] || statuses['disconnected'];
    badge.className = 'badge ' + s.class;
    badge.textContent = s.text;
}

function updateDashboard(data) {
    // System Health
    updateSystemHealth(data.system_health);
    
    // Metrics
    updateMetrics(data.metrics);
    
    // Active Jobs
    updateActiveJobs(data.active_jobs);
    
    // Alerts
    updateAlerts(data.alerts);
}

function updateSystemHealth(health) {
    if (!health) return;
    
    const indicator = document.getElementById('healthIndicator');
    const status = document.getElementById('healthStatus');
    const issues = document.getElementById('healthIssues');
    const card = document.getElementById('systemHealthCard');
    
    const colors = {
        'healthy': '#198754',
        'degraded': '#ffc107',
        'critical': '#dc3545',
    };
    
    const labels = {
        'healthy': 'Saudável',
        'degraded': 'Degradado',
        'critical': 'Crítico',
    };
    
    indicator.style.backgroundColor = colors[health.status] || colors['degraded'];
    status.textContent = labels[health.status] || 'Desconhecido';
    
    if (health.issues && health.issues.length > 0) {
        issues.textContent = health.issues.slice(0, 2).join(' | ');
    } else {
        issues.textContent = '';
    }
}

function updateMetrics(metrics) {
    if (!metrics) return;
    
    const m24h = metrics.last_24h || {};
    const m1h = metrics.last_hour || {};
    const rate = metrics.current_rate || {};
    
    // Stats cards
    document.getElementById('statActiveJobs').textContent = 
        (metrics.active_jobs_count || 0);
    document.getElementById('statActiveJobsDetail').textContent = 
        `${m24h.total_jobs || 0} jobs nas últimas 24h`;
    
    document.getElementById('statCloned24h').textContent = 
        formatNumber(m24h.items_cloned || 0);
    document.getElementById('statCloned24hRate').textContent = 
        `+${formatNumber(m1h.items_cloned || 0)} na última hora`;
    
    const successRate = m24h.total_items > 0 
        ? ((m24h.items_cloned / m24h.total_items) * 100).toFixed(1)
        : 0;
    document.getElementById('statSuccessRate').textContent = successRate + '%';
    document.getElementById('statSuccessRateDetail').textContent = 
        `${m24h.items_failed || 0} falhas`;
    
    // Rate tab
    document.getElementById('rateJobs').textContent = 
        (rate.jobs_per_minute || 0).toFixed(1);
    document.getElementById('rateItems').textContent = 
        (rate.items_per_minute || 0).toFixed(1);
    document.getElementById('hourJobs').textContent = m1h.total_jobs || 0;
    document.getElementById('hourItems').textContent = m1h.items_cloned || 0;
}

function updateActiveJobs(jobs) {
    const container = document.getElementById('activeJobsList');
    const count = document.getElementById('activeJobsCount');
    
    if (!jobs || jobs.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
                Nenhum job em execução
            </div>
        `;
        count.textContent = '0';
        document.getElementById('statActiveJobs').textContent = '0';
        return;
    }
    
    count.textContent = jobs.length;
    document.getElementById('statActiveJobs').textContent = jobs.length;
    
    container.innerHTML = jobs.map(job => {
        const progress = job.total_items > 0 
            ? ((job.processed_items / job.total_items) * 100).toFixed(1)
            : 0;
        
        const etaText = job.eta_seconds 
            ? formatDuration(job.eta_seconds)
            : 'Calculando...';
        
        return `
            <div class="p-3 border-bottom">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <strong>Job #${job.job_id}</strong>
                        <span class="badge bg-primary ms-2">${job.status}</span>
                    </div>
                    <small class="text-muted">ETA: ${etaText}</small>
                </div>
                <div class="job-progress-bar mb-2">
                    <div class="progress-bar bg-primary" style="width: ${progress}%"></div>
                </div>
                <div class="d-flex justify-content-between small text-muted">
                    <span>
                        <i class="bi bi-check text-success"></i> ${job.successful_items || 0}
                        <i class="bi bi-x text-danger ms-2"></i> ${job.failed_items || 0}
                    </span>
                    <span>${job.processed_items || 0} / ${job.total_items || 0} (${progress}%)</span>
                </div>
                ${job.current_phase ? `
                    <div class="mt-2">
                        <small class="text-primary">
                            <i class="bi bi-arrow-right-circle pulse-dot"></i>
                            ${getPhaseLabel(job.current_phase)}
                        </small>
                    </div>
                ` : ''}
            </div>
        `;
    }).join('');
}

function updateAlerts(alerts) {
    const container = document.getElementById('alertsList');
    const badge = document.getElementById('alertsBadge');
    const statAlerts = document.getElementById('statAlerts');
    const statDetail = document.getElementById('statAlertsDetail');
    
    if (!alerts || alerts.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4 text-muted">
                <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
                Nenhum alerta ativo
            </div>
        `;
        badge.textContent = '0';
        badge.className = 'badge bg-secondary ms-1';
        statAlerts.textContent = '0';
        statDetail.textContent = 'Sistema saudável';
        return;
    }
    
    const criticalCount = alerts.filter(a => a.severity === 'critical').length;
    
    badge.textContent = alerts.length;
    badge.className = criticalCount > 0 ? 'badge bg-danger ms-1' : 'badge bg-warning ms-1';
    
    statAlerts.textContent = alerts.length;
    statDetail.textContent = criticalCount > 0 
        ? `${criticalCount} crítico(s)` 
        : 'Atenção necessária';
    
    container.innerHTML = alerts.map(alert => {
        const severityClass = `alert-${alert.severity || 'info'}`;
        const icon = alert.severity === 'critical' ? 'exclamation-circle' : 'exclamation-triangle';
        
        return `
            <div class="alert-item ${severityClass} list-group-item border-0 py-3">
                <div class="d-flex">
                    <i class="bi bi-${icon} text-${getSeverityColor(alert.severity)} me-2 mt-1"></i>
                    <div>
                        <div class="fw-medium">${escapeHtml(alert.title || alert.type)}</div>
                        <small class="text-muted">${escapeHtml(alert.message || '')}</small>
                        <div class="mt-1">
                            <small class="text-muted">${formatRelativeTime(alert.created_at)}</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

async function loadRecentJobs() {
    try {
        const data = await requestJson('/api/catalog/clone/jobs?limit=5&status=completed,failed');
        
        const tbody = document.getElementById('recentJobsTable');
        
        if (!data.jobs || data.jobs.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        Nenhum job recente
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = data.jobs.map(job => {
            const successRate = job.total_items > 0 
                ? ((job.successful_items / job.total_items) * 100).toFixed(0)
                : 0;
            
            return `
                <tr>
                    <td><a href="/dashboard/catalog/clone-job/${job.job_id}">#${job.job_id}</a></td>
                    <td>${getStatusBadge(job.status)}</td>
                    <td>${job.total_items || 0}</td>
                    <td>
                        <span class="text-success">${job.successful_items || 0}</span>
                        /
                        <span class="text-danger">${job.failed_items || 0}</span>
                        (${successRate}%)
                    </td>
                    <td>${job.duration || '-'}</td>
                    <td>${formatDate(job.completed_at)}</td>
                </tr>
            `;
        }).join('');
        
    } catch (e) {
        console.error('Error loading recent jobs:', e);
    }
}

// Helpers
function formatNumber(num) {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
}

function formatDuration(seconds) {
    if (seconds < 60) return seconds + 's';
    if (seconds < 3600) return Math.floor(seconds / 60) + 'm ' + (seconds % 60) + 's';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return h + 'h ' + m + 'm';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatRelativeTime(dateStr) {
    if (!dateStr) return '';
    const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
    if (diff < 60) return 'agora';
    if (diff < 3600) return Math.floor(diff / 60) + 'min atrás';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h atrás';
    return Math.floor(diff / 86400) + 'd atrás';
}

function getPhaseLabel(phase) {
    const labels = {
        'validation': 'Validando itens...',
        'preparation': 'Preparando dados...',
        'publication': 'Publicando anúncios...',
        'post_actions': 'Aplicando ações pós-clone...',
    };
    return labels[phase] || phase;
}

function getStatusBadge(status) {
    const badges = {
        'completed': '<span class="badge bg-success">Concluído</span>',
        'failed': '<span class="badge bg-danger">Falhou</span>',
        'processing': '<span class="badge bg-primary">Processando</span>',
        'pending': '<span class="badge bg-secondary">Pendente</span>',
    };
    return badges[status] || status;
}

function getSeverityColor(severity) {
    const colors = {
        'critical': 'danger',
        'warning': 'warning',
        'info': 'info',
    };
    return colors[severity] || 'secondary';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (eventSource) {
        eventSource.close();
    }
});
</script>

<?php
$content = ob_get_clean();

include __DIR__ . '/../layouts/dashboard.php';
