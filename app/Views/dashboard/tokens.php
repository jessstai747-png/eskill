<?php
/**
 * Dashboard de Tokens ML
 * 
 * Painel de gerenciamento e monitoramento de tokens OAuth do Mercado Livre
 */

$pageTitle = 'Dashboard de Tokens';
$pageDescription = 'Monitoramento e gerenciamento de tokens OAuth das contas Mercado Livre';
$activePage = 'tokens';

ob_start();
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-key-fill text-primary"></i>
                        Dashboard de Tokens ML
                    </h1>
                    <p class="text-muted mb-0">
                        Monitore a saúde e status dos tokens OAuth das suas contas
                    </p>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-primary" id="refreshAllBtn">
                        <i class="bi bi-arrow-clockwise"></i>
                        Renovar Todas
                    </button>
                    <button type="button" class="btn btn-primary" id="refreshDataBtn">
                        <i class="bi bi-arrow-repeat"></i>
                        Atualizar Dados
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Health Status Alert -->
    <div id="healthAlert" class="alert d-none" role="alert"></div>

    <!-- Metrics Cards -->
    <div class="row g-3 mb-4" id="metricsCards">
        <!-- Será preenchido via JavaScript -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 rounded p-3">
                                <i class="bi bi-collection fs-3 text-primary"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">Total de Contas</div>
                            <div class="h4 mb-0" id="metricTotalAccounts">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-opacity-10 rounded p-3">
                                <i class="bi bi-check-circle-fill fs-3 text-success"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">Contas Ativas</div>
                            <div class="h4 mb-0" id="metricActiveAccounts">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-danger bg-opacity-10 rounded p-3">
                                <i class="bi bi-x-circle-fill fs-3 text-danger"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">Contas Expiradas</div>
                            <div class="h4 mb-0" id="metricExpiredAccounts">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-warning bg-opacity-10 rounded p-3">
                                <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">Taxa de Falha 24h</div>
                            <div class="h4 mb-0" id="metricFailureRate">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-info bg-opacity-10 rounded p-3">
                                <i class="bi bi-shield-exclamation fs-3 text-info"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">Falhas de Validação API</div>
                            <div class="h4 mb-0" id="metricApiValidationFailures">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                            </div>
                            <small class="text-muted" id="metricIdentityMismatch">-</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up"></i>
                        Histórico de Renovações
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="btn-group btn-group-sm" role="group">
                            <input type="radio" class="btn-check" name="chartPeriod" id="period24h" value="24h" checked>
                            <label class="btn btn-outline-primary" for="period24h">24h</label>
                            
                            <input type="radio" class="btn-check" name="chartPeriod" id="period7d" value="7d">
                            <label class="btn btn-outline-primary" for="period7d">7 dias</label>
                            
                            <input type="radio" class="btn-check" name="chartPeriod" id="period30d" value="30d">
                            <label class="btn btn-outline-primary" for="period30d">30 dias</label>
                        </div>
                    </div>
                    <canvas id="renewalChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">
                        <i class="bi bi-pie-chart-fill"></i>
                        Status das Contas
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Accounts Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul"></i>
                    Contas Cadastradas
                </h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="filterStatus" style="width: auto;">
                        <option value="all">Todos Status</option>
                        <option value="active">Ativas</option>
                        <option value="expired">Expiradas</option>
                        <option value="expiring">Expirando</option>
                    </select>
                    <select class="form-select form-select-sm" id="sortBy" style="width: auto;">
                        <option value="expires_at">Expira em</option>
                        <option value="last_refresh">Última renovação</option>
                        <option value="name">Nome</option>
                        <option value="failure_count">Falhas</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Conta</th>
                            <th>Status</th>
                            <th>Expira em</th>
                            <th>Última Renovação</th>
                            <th>Falhas</th>
                            <th>Diagnóstico API</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="accountsTableBody">
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando contas...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal - Audit History -->
<div class="modal fade" id="auditModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-clock-history"></i>
                    Histórico de Auditoria
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="auditModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Estilos adicionais -->
<style>
.badge-active {
    background-color: #28a745;
    color: white;
}
.badge-expired {
    background-color: #dc3545;
    color: white;
}
.badge-expiring {
    background-color: #ffc107;
    color: #000;
}
.badge-inactive {
    background-color: #6c757d;
    color: white;
}
</style>

<!-- JavaScript -->
<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

// Estado global
const TokenDashboard = {
    charts: {
        renewal: null,
        status: null
    },
    currentPeriod: '7d',
    currentFilters: {
        status: 'all',
        sort: 'expires_at',
        order: 'asc'
    }
};

// Carregar dados ao iniciar
document.addEventListener('DOMContentLoaded', () => {
    loadDashboardData();
    setupEventListeners();
    
    // Auto-refresh a cada 2 minutos
    setInterval(loadDashboardData, 120000);
});

// Event Listeners
function setupEventListeners() {
    // Botão atualizar
    document.getElementById('refreshDataBtn').addEventListener('click', () => {
        loadDashboardData();
    });
    
    // Botão renovar todas
    document.getElementById('refreshAllBtn').addEventListener('click', () => {
        refreshAllAccounts();
    });
    
    // Mudança de período do gráfico
    document.querySelectorAll('input[name="chartPeriod"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            TokenDashboard.currentPeriod = e.target.value;
            loadChartData();
        });
    });
    
    // Filtros da tabela
    document.getElementById('filterStatus').addEventListener('change', (e) => {
        TokenDashboard.currentFilters.status = e.target.value;
        loadAccountsList();
    });
    
    document.getElementById('sortBy').addEventListener('change', (e) => {
        TokenDashboard.currentFilters.sort = e.target.value;
        loadAccountsList();
    });
}

// Carregar todos os dados do dashboard
async function loadDashboardData() {
    try {
        await Promise.all([
            loadMetrics(),
            loadChartData(),
            loadAccountsList()
        ]);
    } catch (error) {
        console.error('Erro ao carregar dashboard:', error);
        showAlert('Erro ao carregar dados do dashboard', 'danger');
    }
}

// Carregar métricas
async function loadMetrics() {
    try {
        const result = await requestJson('/api/tokens/dashboard');
        
        if (result.success) {
            const data = result.data;
            
            // Atualizar cards
            document.getElementById('metricTotalAccounts').textContent = data.total_accounts;
            document.getElementById('metricActiveAccounts').textContent = data.active_accounts;
            document.getElementById('metricExpiredAccounts').textContent = data.expired_accounts;
            document.getElementById('metricFailureRate').textContent = data.failure_rate_24h + '%';

            const apiFailuresElement = document.getElementById('metricApiValidationFailures');
            if (apiFailuresElement) {
                apiFailuresElement.textContent = data.accounts_with_api_validation_failures ?? 0;
            }

            const identityMismatchElement = document.getElementById('metricIdentityMismatch');
            if (identityMismatchElement) {
                const mismatchCount = data.accounts_with_identity_mismatch ?? 0;
                identityMismatchElement.textContent = `Mismatch: ${mismatchCount}`;
            }
            
            // Atualizar alerta de saúde
            updateHealthAlert(data.health_status, data);
        }
    } catch (error) {
        console.error('Erro ao carregar métricas:', error);
    }
}

// Atualizar alerta de saúde
function updateHealthAlert(status, data) {
    const alertDiv = document.getElementById('healthAlert');
    alertDiv.classList.remove('d-none', 'alert-success', 'alert-warning', 'alert-danger');
    
    let icon, alertClass, message;
    
    switch(status) {
        case 'critical':
            icon = '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
            alertClass = 'alert-danger';
            message = `<strong>Status Crítico!</strong> ${data.expired_accounts} contas expiradas, ${data.accounts_with_identity_mismatch ?? 0} mismatches de identidade ML e taxa de falha de ${data.failure_rate_24h}%.`;
            break;
        case 'warning':
            icon = '<i class="bi bi-exclamation-circle-fill me-2"></i>';
            alertClass = 'alert-warning';
            message = `<strong>Atenção necessária.</strong> ${data.expiring_24h} contas expirando em 24h e ${data.accounts_with_api_validation_failures ?? 0} falhas recentes de validação na API.`;
            break;
        case 'attention':
            icon = '<i class="bi bi-exclamation-circle-fill me-2"></i>';
            alertClass = 'alert-warning';
            message = `<strong>Monitoramento recomendado.</strong> Existem ${data.accounts_with_api_validation_failures ?? 0} contas com erros recentes de validação.`;
            break;
        case 'healthy':
        case 'ok':
            icon = '<i class="bi bi-check-circle-fill me-2"></i>';
            alertClass = 'alert-success';
            message = '<strong>Tudo funcionando bem!</strong> Todas as contas estão ativas e tokens renovando corretamente.';
            break;
        default:
            return;
    }
    
    alertDiv.className = `alert ${alertClass} d-flex align-items-center`;
    alertDiv.innerHTML = icon + message;
}

// Carregar dados dos gráficos
async function loadChartData() {
    try {
        const result = await requestJson(`/api/tokens/stats?period=${TokenDashboard.currentPeriod}`);
        
        if (result.success) {
            const data = result.data;
            updateRenewalChart(data);
            updateStatusChart(data);
        }
    } catch (error) {
        console.error('Erro ao carregar gráficos:', error);
    }
}

// Atualizar gráfico de renovações
function updateRenewalChart(data) {
    const ctx = document.getElementById('renewalChart');
    
    if (TokenDashboard.charts.renewal) {
        TokenDashboard.charts.renewal.destroy();
    }
    
    const labels = data.timeline.map(t => {
        const date = new Date(t.hour);
        return data.period === '24h' 
            ? date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
            : date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    });
    
    TokenDashboard.charts.renewal = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Sucessos',
                    data: data.timeline.map(t => parseInt(t.successes)),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Falhas',
                    data: data.timeline.map(t => parseInt(t.failures)),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Atualizar gráfico de status
function updateStatusChart(data) {
    const ctx = document.getElementById('statusChart');
    
    if (TokenDashboard.charts.status) {
        TokenDashboard.charts.status.destroy();
    }
    
    // Buscar dados de contas atuais
    requestJson('/api/tokens/accounts')
        .then(result => {
            if (result.success) {
                const accounts = result.data;
                const expiringCount = accounts.filter(a => {
                    const hoursUntil = Number(a.hours_until_expiration);
                    return a.status === 'active' && Number.isFinite(hoursUntil) && hoursUntil >= 0 && hoursUntil < 24;
                }).length;

                const statusCount = {
                    active: accounts.filter(a => a.status === 'active').length - expiringCount,
                    expired: accounts.filter(a => a.status === 'expired').length,
                    expiring: expiringCount,
                    inactive: accounts.filter(a => a.status === 'inactive').length
                };
                
                TokenDashboard.charts.status = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Ativas', 'Expiradas', 'Expirando', 'Inativas'],
                        datasets: [{
                            data: [statusCount.active, statusCount.expired, statusCount.expiring, statusCount.inactive],
                            backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#6c757d']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        });
}

// Carregar lista de contas
async function loadAccountsList() {
    try {
        const params = new URLSearchParams({
            status: TokenDashboard.currentFilters.status,
            sort: TokenDashboard.currentFilters.sort,
            order: TokenDashboard.currentFilters.order
        });
        
        const result = await requestJson(`/api/tokens/accounts?${params}`);
        
        if (result.success) {
            renderAccountsTable(result.data);
        }
    } catch (error) {
        console.error('Erro ao carregar contas:', error);
    }
}

// Renderizar tabela de contas
function renderAccountsTable(accounts) {
    const tbody = document.getElementById('accountsTableBody');
    
    if (accounts.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Nenhuma conta encontrada
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = accounts.map(account => `
        <tr class="${account.requires_attention ? 'table-warning' : ''}">
            <td>
                <div class="d-flex align-items-center">
                    <div>
                        <strong>${escapeHtml(account.nickname)}</strong>
                        <small class="d-block text-muted">ID: ${account.id}</small>
                    </div>
                </div>
            </td>
            <td>
                <span class="badge badge-${account.status}">${getStatusLabel(account.status)}</span>
            </td>
            <td>
                <span class="${account.hours_until_expiration < 24 ? 'text-danger fw-bold' : ''}">
                    ${account.expires_at_formatted}
                </span>
                ${account.hours_until_expiration >= 0 
                    ? `<small class="d-block text-muted">${Math.floor(account.hours_until_expiration)}h</small>`
                    : '<small class="d-block text-danger">Expirado</small>'}
            </td>
            <td>
                ${account.last_refresh_formatted}
            </td>
            <td>
                ${account.refresh_failure_count > 0 
                    ? `<span class="badge bg-danger">${account.refresh_failure_count}</span>`
                    : '<span class="text-muted">0</span>'}
            </td>
            <td>
                ${renderDiagnosticCell(account)}
            </td>
            <td class="text-end">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="refreshAccount(${account.id})" title="Renovar token">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <button class="btn btn-outline-secondary" onclick="showAuditHistory(${account.id})" title="Ver histórico">
                        <i class="bi bi-clock-history"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Renovar conta individual
async function refreshAccount(accountId) {
    try {
        showAlert('Renovando token...', 'info');
        
        const result = await requestJson(`/api/tokens/refresh/${accountId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (result.success) {
            showAlert('Token renovado com sucesso!', 'success');
            loadDashboardData();
        } else {
            showAlert(result.error || 'Erro ao renovar token', 'danger');
        }
    } catch (error) {
        console.error('Erro ao renovar token:', error);
        showAlert('Erro ao renovar token', 'danger');
    }
}

// Renovar todas as contas
async function refreshAllAccounts() {
    if (!confirm('Deseja realmente renovar os tokens de todas as contas?')) {
        return;
    }
    
    try {
        showAlert('Renovando tokens de todas as contas...', 'info');
        
        const result = await requestJson('/api/tokens/refresh-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ mode: 'expiring_only' })
        });
        
        if (result.success) {
            const data = result.data;
            showAlert(`Renovação concluída! ${data.refreshed} tokens renovados, ${data.failed} falhas.`, 'success');
            loadDashboardData();
        } else {
            showAlert(result.error || 'Erro ao renovar tokens', 'danger');
        }
    } catch (error) {
        console.error('Erro ao renovar tokens:', error);
        showAlert('Erro ao renovar tokens', 'danger');
    }
}

// Mostrar histórico de auditoria
async function showAuditHistory(accountId) {
    const modal = new bootstrap.Modal(document.getElementById('auditModal'));
    const modalBody = document.getElementById('auditModalBody');
    
    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
    modal.show();
    
    try {
        const result = await requestJson(`/api/tokens/audit/${accountId}?limit=50`);
        
        if (result.success) {
            renderAuditHistory(result.data, modalBody);
        } else {
            modalBody.innerHTML = '<div class="alert alert-danger">Erro ao carregar histórico</div>';
        }
    } catch (error) {
        console.error('Erro ao carregar auditoria:', error);
        modalBody.innerHTML = '<div class="alert alert-danger">Erro ao carregar histórico</div>';
    }
}

// Renderizar histórico de auditoria
function renderAuditHistory(events, container) {
    if (events.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-5">Nenhum evento registrado</div>';
        return;
    }
    
    container.innerHTML = `
        <div class="timeline">
            ${events.map(event => {
                const icon = getActionIcon(event.action);
                const variant = getActionVariant(event.action);
                const date = new Date(event.created_at);
                
                return `
                    <div class="timeline-item">
                        <div class="timeline-marker bg-${variant}">
                            <i class="bi ${icon}"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between">
                                <strong>${getActionLabel(event.action)}</strong>
                                <small class="text-muted">${date.toLocaleString('pt-BR')}</small>
                            </div>
                            ${event.error_message ? `<div class="text-danger small">${escapeHtml(event.error_message)}</div>` : ''}
                            ${event.execution_time_ms ? `<div class="text-muted small">Tempo: ${event.execution_time_ms}ms</div>` : ''}
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

// Helpers
function getStatusLabel(status) {
    const labels = {
        'active': 'Ativo',
        'expired': 'Expirado',
        'expiring': 'Expirando',
        'inactive': 'Inativo'
    };
    return labels[status] || status;
}

function getActionIcon(action) {
    const icons = {
        'refresh_attempt': 'bi-arrow-clockwise',
        'refresh_success': 'bi-check-circle-fill',
        'refresh_failed': 'bi-x-circle-fill',
        'authorization_granted': 'bi-shield-check',
        'token_expired': 'bi-exclamation-triangle',
        'lock_acquired': 'bi-lock-fill',
        'lock_timeout': 'bi-clock'
    };
    return icons[action] || 'bi-circle-fill';
}

function getActionVariant(action) {
    const variants = {
        'refresh_success': 'success',
        'authorization_granted': 'success',
        'refresh_failed': 'danger',
        'token_expired': 'warning',
        'refresh_attempt': 'primary',
        'lock_acquired': 'info',
        'lock_timeout': 'warning'
    };
    return variants[action] || 'secondary';
}

function getActionLabel(action) {
    const labels = {
        'refresh_attempt': 'Tentativa de Renovação',
        'refresh_success': 'Renovação Bem-sucedida',
        'refresh_failed': 'Falha na Renovação',
        'authorization_granted': 'Autorização Concedida',
        'token_expired': 'Token Expirado',
        'lock_acquired': 'Lock Adquirido',
        'lock_timeout': 'Timeout de Lock'
    };
    return labels[action] || action;
}

function showAlert(message, type = 'info') {
    // Usar toasts do Bootstrap ou criar um sistema de notificações
    console.log(`[${type.toUpperCase()}] ${message}`);
    
    // Criar toast temporário
    const toastHtml = `
        <div class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    // Implementar sistema de toasts se necessário
}

function renderDiagnosticCell(account) {
    const status = account.api_validation_status || 'ok';
    const label = account.diagnostic_label || getValidationLabel(status);
    const variant = getValidationVariant(status);
    const diagnosticMessage = account.diagnostic_message
        ? `<small class="d-block text-muted" title="${escapeHtml(account.diagnostic_message)}">${escapeHtml(truncateText(account.diagnostic_message, 60))}</small>`
        : '<small class="d-block text-muted">Sem erros recentes</small>';

    return `
        <span class="badge bg-${variant}">${escapeHtml(label)}</span>
        ${diagnosticMessage}
    `;
}

function getValidationLabel(status) {
    const labels = {
        ok: 'API OK',
        identity_mismatch: 'Mismatch ML User',
        auth_error: 'Erro de Auth',
        api_error: 'Falha API'
    };

    return labels[status] || 'Sem diagnóstico';
}

function getValidationVariant(status) {
    const variants = {
        ok: 'success',
        identity_mismatch: 'danger',
        auth_error: 'warning',
        api_error: 'secondary'
    };

    return variants[status] || 'secondary';
}

function truncateText(text, maxLength = 60) {
    if (typeof text !== 'string') {
        return '';
    }

    if (text.length <= maxLength) {
        return text;
    }

    return `${text.slice(0, maxLength - 1)}…`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<style>
/* Timeline de auditoria */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding-bottom: 20px;
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: -19px;
    top: 30px;
    width: 2px;
    height: calc(100% - 10px);
    background: #e9ecef;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    top: 0;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
}

.timeline-content {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 8px;
    border-left: 3px solid #667eea;
}
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
?>
