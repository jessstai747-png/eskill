<?php
/**
 * Clone Compliance & Audit Dashboard View
 * 
 * Interface para visualização de logs de auditoria e relatórios de compliance
 */

$pageTitle = 'Compliance e Auditoria';
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
                        <i class="bi bi-shield-check text-primary"></i>
                        Compliance e Auditoria
                    </h1>
                    <p class="text-muted mb-0">Trilha de auditoria e relatórios de conformidade</p>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-primary" data-action="exportlogs">
                        <i class="bi bi-download me-1"></i>
                        Exportar CSV
                    </button>
                    <button type="button" class="btn btn-primary" data-action="generatereport">
                        <i class="bi bi-file-earmark-text me-1"></i>
                        Gerar Relatório
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4" id="statsCards">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">Eventos Hoje</h6>
                            <h2 class="mb-0" id="statTodayEvents">-</h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-activity text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">Erros Hoje</h6>
                            <h2 class="mb-0" id="statTodayErrors">-</h2>
                        </div>
                        <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">Violações Pendentes</h6>
                            <h2 class="mb-0" id="statPendingViolations">-</h2>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-shield-exclamation text-warning fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">Score de Compliance</h6>
                            <h2 class="mb-0">
                                <span id="statComplianceScore">-</span>
                                <small class="text-muted fs-6">/100</small>
                            </h2>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-award text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Filters -->
        <div class="col-lg-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-funnel me-2"></i>
                        Filtros
                    </h5>
                </div>
                <div class="card-body">
                    <form id="filterForm">
                        <div class="mb-3">
                            <label class="form-label">Período</label>
                            <select class="form-select" id="filterPeriod" name="period">
                                <option value="24h">Últimas 24 horas</option>
                                <option value="7d" selected>Últimos 7 dias</option>
                                <option value="30d">Últimos 30 dias</option>
                                <option value="90d">Últimos 90 dias</option>
                                <option value="custom">Personalizado</option>
                            </select>
                        </div>

                        <div id="customDateRange" class="mb-3" style="display: none;">
                            <label class="form-label">Data Início</label>
                            <input type="date" class="form-control mb-2" id="filterDateFrom" name="date_from">
                            <label class="form-label">Data Fim</label>
                            <input type="date" class="form-control" id="filterDateTo" name="date_to">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tipo de Evento</label>
                            <select class="form-select" id="filterEventType" name="event_type">
                                <option value="">Todos</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Severidade</label>
                            <select class="form-select" id="filterSeverity" name="severity">
                                <option value="">Todas</option>
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="error">Error</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Job ID</label>
                            <input type="text" class="form-control" id="filterJobId" name="job_id" placeholder="Ex: 123">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Busca</label>
                            <input type="text" class="form-control" id="filterSearch" name="search" placeholder="Buscar...">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i>
                            Aplicar Filtros
                        </button>
                    </form>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning me-2"></i>
                        Ações Rápidas
                    </h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action" onclick="filterBySeverity('critical'); return false;">
                        <i class="bi bi-exclamation-octagon text-danger me-2"></i>
                        Ver Eventos Críticos
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="filterBySeverity('error'); return false;">
                        <i class="bi bi-x-circle text-warning me-2"></i>
                        Ver Erros
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="showViolations(); return false;">
                        <i class="bi bi-shield-x text-danger me-2"></i>
                        Ver Violações
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="showAnomalies(); return false;">
                        <i class="bi bi-graph-up-arrow text-info me-2"></i>
                        Ver Anomalias
                    </a>
                </div>
            </div>
        </div>

        <!-- Audit Logs -->
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-journal-text me-2"></i>
                        Logs de Auditoria
                    </h5>
                    <span class="badge bg-secondary" id="logsCount">0 registros</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="logsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 150px;">Data/Hora</th>
                                    <th>Evento</th>
                                    <th style="width: 100px;">Severidade</th>
                                    <th style="width: 120px;">Usuário</th>
                                    <th style="width: 80px;">Job</th>
                                    <th style="width: 80px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="logsTableBody">
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Carregando...</span>
                                        </div>
                                        <p class="text-muted mt-2 mb-0">Carregando logs...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <nav>
                        <ul class="pagination pagination-sm mb-0 justify-content-center" id="pagination">
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Event Details -->
<div class="modal fade" id="eventDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-info-circle me-2"></i>
                    Detalhes do Evento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventDetailContent">
                <!-- Populated by JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Compliance Report -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Relatório de Compliance
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reportContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2">Gerando relatório...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" data-action="downloadreport">
                    <i class="bi bi-download me-1"></i>
                    Download PDF
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.severity-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}
.severity-info { background-color: #0dcaf0; color: #000; }
.severity-warning { background-color: #ffc107; color: #000; }
.severity-error { background-color: #fd7e14; color: #fff; }
.severity-critical { background-color: #dc3545; color: #fff; }

.event-row:hover {
    background-color: #f8f9fa;
    cursor: pointer;
}

.score-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
}
.score-excellent { background-color: #d4edda; color: #155724; }
.score-good { background-color: #d1ecf1; color: #0c5460; }
.score-fair { background-color: #fff3cd; color: #856404; }
.score-poor { background-color: #f8d7da; color: #721c24; }
</style>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

let currentPage = 0;
let currentFilters = {};
const pageSize = 50;

document.addEventListener('DOMContentLoaded', function() {
    loadEventTypes();
    loadStats();
    loadLogs();

    // Period change handler
    document.getElementById('filterPeriod').addEventListener('change', function() {
        document.getElementById('customDateRange').style.display = 
            this.value === 'custom' ? 'block' : 'none';
    });

    // Form submit handler
    document.getElementById('filterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        currentPage = 0;
        loadLogs();
    });
});

async function loadStats() {
    try {
        const data = await requestJson('/api/clone/compliance/stats');

        if (data.success) {
            document.getElementById('statTodayEvents').textContent = data.data.today_events || 0;
            document.getElementById('statTodayErrors').textContent = data.data.today_errors || 0;
            document.getElementById('statPendingViolations').textContent = data.data.pending_violations || 0;
            document.getElementById('statComplianceScore').textContent = data.data.compliance_score || 0;
        }
    } catch (e) {
        console.error('Error loading stats:', e);
    }
}

async function loadEventTypes() {
    try {
        const data = await requestJson('/api/clone/compliance/event-types');

        if (data.success) {
            const select = document.getElementById('filterEventType');
            for (const [value, label] of Object.entries(data.data)) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                select.appendChild(option);
            }
        }
    } catch (e) {
        console.error('Error loading event types:', e);
    }
}

async function loadLogs() {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    
    // Build query params
    const params = new URLSearchParams();
    for (const [key, value] of formData.entries()) {
        if (value) params.append(key, value);
    }
    params.append('limit', pageSize);
    params.append('offset', currentPage * pageSize);

    // Handle period
    const period = formData.get('period');
    if (period !== 'custom') {
        const now = new Date();
        let dateFrom = new Date();
        
        switch(period) {
            case '24h': dateFrom.setDate(now.getDate() - 1); break;
            case '7d': dateFrom.setDate(now.getDate() - 7); break;
            case '30d': dateFrom.setDate(now.getDate() - 30); break;
            case '90d': dateFrom.setDate(now.getDate() - 90); break;
        }
        
        params.set('date_from', dateFrom.toISOString().split('T')[0]);
        params.delete('period');
    }

    try {
        const data = await requestJson('/api/clone/compliance/logs?' + params.toString());

        if (data.success) {
            renderLogs(data.data.logs);
            renderPagination(data.data.total);
            document.getElementById('logsCount').textContent = `${data.data.total} registros`;
        }
    } catch (e) {
        console.error('Error loading logs:', e);
        document.getElementById('logsTableBody').innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-5 text-danger">
                    <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                    Erro ao carregar logs
                </td>
            </tr>
        `;
    }
}

function renderLogs(logs) {
    const tbody = document.getElementById('logsTableBody');
    
    if (!logs || logs.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Nenhum log encontrado
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = logs.map(log => `
        <tr class="event-row" onclick="showEventDetail(${log.id})">
            <td>
                <small>${formatDate(log.created_at)}</small>
            </td>
            <td>
                <strong>${escapeHtml(log.event_type)}</strong>
                <br>
                <small class="text-muted">${escapeHtml(log.event_description || '')}</small>
            </td>
            <td>
                <span class="badge severity-badge severity-${log.severity}">
                    ${log.severity}
                </span>
            </td>
            <td>
                <small>${escapeHtml(log.user_name || 'Sistema')}</small>
            </td>
            <td>
                ${log.job_id ? `<a href="/dashboard/catalog/clone-job/${log.job_id}" onclick="event.stopPropagation();">#${log.job_id}</a>` : '-'}
            </td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); showEventDetail(${log.id});">
                    <i class="bi bi-eye"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function renderPagination(total) {
    const totalPages = Math.ceil(total / pageSize);
    const pagination = document.getElementById('pagination');
    
    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }

    let html = '';
    
    // Previous
    html += `
        <li class="page-item ${currentPage === 0 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${currentPage - 1}); return false;">«</a>
        </li>
    `;

    // Pages
    const startPage = Math.max(0, currentPage - 2);
    const endPage = Math.min(totalPages - 1, currentPage + 2);

    for (let i = startPage; i <= endPage; i++) {
        html += `
            <li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i + 1}</a>
            </li>
        `;
    }

    // Next
    html += `
        <li class="page-item ${currentPage >= totalPages - 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${currentPage + 1}); return false;">»</a>
        </li>
    `;

    pagination.innerHTML = html;
}

function goToPage(page) {
    if (page < 0) return;
    currentPage = page;
    loadLogs();
}

async function showEventDetail(logId) {
    const modal = new bootstrap.Modal(document.getElementById('eventDetailModal'));
    const content = document.getElementById('eventDetailContent');
    
    content.innerHTML = '<div class="text-center py-4"><div class="spinner-border"></div></div>';
    modal.show();

    try {
        const data = await requestJson(`/api/clone/compliance/logs?id=${logId}&limit=1`);

        if (data.success && data.data.logs.length > 0) {
            const log = data.data.logs[0];
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Informações Gerais</h6>
                        <table class="table table-sm">
                            <tr><th>ID</th><td>${log.id}</td></tr>
                            <tr><th>Data/Hora</th><td>${formatDate(log.created_at)}</td></tr>
                            <tr><th>Tipo</th><td>${escapeHtml(log.event_type)}</td></tr>
                            <tr><th>Descrição</th><td>${escapeHtml(log.event_description || '-')}</td></tr>
                            <tr><th>Severidade</th><td><span class="badge severity-${log.severity}">${log.severity}</span></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Contexto</h6>
                        <table class="table table-sm">
                            <tr><th>Usuário</th><td>${escapeHtml(log.user_name || 'Sistema')}</td></tr>
                            <tr><th>Job ID</th><td>${log.job_id || '-'}</td></tr>
                            <tr><th>Item ID</th><td>${log.item_id || '-'}</td></tr>
                            <tr><th>IP</th><td>${log.ip_address || '-'}</td></tr>
                        </table>
                    </div>
                </div>
                ${log.event_data && Object.keys(log.event_data).length > 0 ? `
                    <h6 class="mt-3">Dados do Evento</h6>
                    <pre class="bg-light p-3 rounded"><code>${JSON.stringify(log.event_data, null, 2)}</code></pre>
                ` : ''}
            `;
        }
    } catch (e) {
        content.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes</div>';
    }
}

async function generateReport() {
    const modal = new bootstrap.Modal(document.getElementById('reportModal'));
    const content = document.getElementById('reportContent');
    
    content.innerHTML = '<div class="text-center py-5"><div class="spinner-border"></div><p class="mt-2">Gerando relatório...</p></div>';
    modal.show();

    try {
        const period = document.getElementById('filterPeriod').value;
        const data = await requestJson(`/api/clone/compliance/report?period=${period}`);

        if (data.success) {
            const report = data.data;
            content.innerHTML = `
                <div class="row mb-4">
                    <div class="col-md-4 text-center">
                        <div class="score-circle mx-auto ${getScoreClass(report.compliance_score)}">
                            ${report.compliance_score}
                        </div>
                        <h5 class="mt-3">Score de Compliance</h5>
                        <p class="text-muted">Período: ${report.period}</p>
                    </div>
                    <div class="col-md-8">
                        <h5>Resumo Estatístico</h5>
                        <div class="row">
                            <div class="col-6 col-md-3 mb-3">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-0">${report.statistics.totals.total_events || 0}</h4>
                                    <small class="text-muted">Total Eventos</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-0">${report.statistics.totals.unique_users || 0}</h4>
                                    <small class="text-muted">Usuários</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-0">${report.statistics.totals.total_jobs || 0}</h4>
                                    <small class="text-muted">Jobs</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <div class="border rounded p-3 text-center">
                                    <h4 class="mb-0">${report.statistics.totals.total_items || 0}</h4>
                                    <small class="text-muted">Itens</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                ${report.policy_violations.length > 0 ? `
                    <h5 class="text-danger"><i class="bi bi-shield-x me-2"></i>Violações de Política (${report.policy_violations.length})</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm">
                            <thead><tr><th>Política</th><th>Mensagem</th><th>Severidade</th><th>Data</th></tr></thead>
                            <tbody>
                                ${report.policy_violations.slice(0, 10).map(v => `
                                    <tr>
                                        <td>${escapeHtml(v.policy_name)}</td>
                                        <td>${escapeHtml(v.violation_message || '')}</td>
                                        <td><span class="badge severity-${v.severity}">${v.severity}</span></td>
                                        <td><small>${formatDate(v.created_at)}</small></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                ` : '<div class="alert alert-success mb-4"><i class="bi bi-check-circle me-2"></i>Nenhuma violação de política detectada!</div>'}

                ${report.anomalies.length > 0 ? `
                    <h5 class="text-warning"><i class="bi bi-graph-up-arrow me-2"></i>Anomalias Detectadas (${report.anomalies.length})</h5>
                    <div class="list-group mb-4">
                        ${report.anomalies.map(a => `
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <strong>${escapeHtml(a.type)}</strong>
                                    <span class="badge severity-${a.severity}">${a.severity}</span>
                                </div>
                                <small class="text-muted">${escapeHtml(a.description)}</small>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}

                <h5><i class="bi bi-people me-2"></i>Top Usuários por Atividade</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Usuário</th><th>Eventos</th><th>Jobs</th><th>Última Atividade</th></tr></thead>
                        <tbody>
                            ${report.top_users.slice(0, 5).map(u => `
                                <tr>
                                    <td>${escapeHtml(u.name || u.email || 'Usuário ' + u.user_id)}</td>
                                    <td>${u.total_events}</td>
                                    <td>${u.jobs_created}</td>
                                    <td><small>${formatDate(u.last_activity)}</small></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }
    } catch (e) {
        content.innerHTML = '<div class="alert alert-danger">Erro ao gerar relatório</div>';
    }
}

async function exportLogs() {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const filters = Object.fromEntries(formData.entries());

    try {
        const data = await requestJson('/api/clone/compliance/export', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(filters),
        });
        
        if (data.success) {
            window.location.href = data.data.download_url;
        } else {
            alert('Erro ao exportar: ' + data.error);
        }
    } catch (e) {
        alert('Erro ao exportar logs');
    }
}

function filterBySeverity(severity) {
    document.getElementById('filterSeverity').value = severity;
    currentPage = 0;
    loadLogs();
}

function showViolations() {
    // Implementar modal de violações
    alert('Funcionalidade em desenvolvimento');
}

function showAnomalies() {
    // Implementar modal de anomalias
    alert('Funcionalidade em desenvolvimento');
}

function getScoreClass(score) {
    if (score >= 90) return 'score-excellent';
    if (score >= 70) return 'score-good';
    if (score >= 50) return 'score-fair';
    return 'score-poor';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
// CSP Event Delegation
document.addEventListener('click', e => {
    const t = e.target.closest('[data-action]');
    if (!t) return;
    const action = t.dataset.action;
    const fn = window[action] || window[action.replace(/-([a-z])/g, (m,c) => c.toUpperCase())];
    if (fn) { e.preventDefault(); fn(t.dataset.param || t.dataset.id); }
});
</script>

<?php
$content = ob_get_clean();

include __DIR__ . '/../layouts/dashboard.php';
