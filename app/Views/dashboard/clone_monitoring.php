<?php
$title = 'Monitoramento de Clonagem';
$subtitle = 'Alertas, feature flags e métricas de saúde do sistema';
include __DIR__ . '/../layouts/modern/partials/page-header.php';
?>

<!-- Health Status Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100" id="health-status-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle p-3" id="health-icon-container">
                            <i class="bi bi-heart-pulse fs-4" id="health-icon"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Status do Sistema</div>
                        <h4 class="mb-0" id="health-status">Carregando...</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3">
                            <i class="bi bi-graph-up text-info fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Taxa de Erro</div>
                        <h4 class="mb-0"><span id="error-rate">-</span>%</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                            <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Alertas Ativos</div>
                        <h4 class="mb-0" id="unresolved-alerts">-</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                            <i class="bi bi-clock text-primary fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-muted small">Operações (1h)</div>
                        <h4 class="mb-0" id="operations-1h">-</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Tabs -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <ul class="nav nav-tabs card-header-tabs" id="monitoringTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="alerts-tab" data-bs-toggle="tab" data-bs-target="#alerts" type="button" role="tab">
                    <i class="bi bi-bell"></i> Alertas
                    <span class="badge bg-danger ms-1" id="alerts-badge" style="display: none;">0</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="flags-tab" data-bs-toggle="tab" data-bs-target="#flags" type="button" role="tab">
                    <i class="bi bi-toggles"></i> Feature Flags
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="report-tab" data-bs-toggle="tab" data-bs-target="#report" type="button" role="tab">
                    <i class="bi bi-file-earmark-bar-graph"></i> Relatório Diário
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="monitoringTabsContent">
            <!-- Alerts Tab -->
            <div class="tab-pane fade show active" id="alerts" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Alertas do Sistema</h5>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="showAcknowledged">
                            <label class="form-check-label small" for="showAcknowledged">Mostrar reconhecidos</label>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="cloneMonitoring.loadAlerts()">
                            <i class="bi bi-arrow-clockwise"></i> Atualizar
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th width="120">Severidade</th>
                                <th width="150">Tipo</th>
                                <th>Mensagem</th>
                                <th width="150">Data</th>
                                <th width="100">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="alerts-list">
                            <tr><td colspan="5" class="text-center py-4">Carregando alertas...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Feature Flags Tab -->
            <div class="tab-pane fade" id="flags" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Controle de Funcionalidades</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="cloneMonitoring.loadFlags()">
                        <i class="bi bi-arrow-clockwise"></i> Atualizar
                    </button>
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Use os toggles abaixo para habilitar/desabilitar funcionalidades do módulo de clonagem em tempo real.
                </div>
                <div id="flags-list" class="row g-3">
                    <div class="col-12 text-center py-4">Carregando feature flags...</div>
                </div>
            </div>

            <!-- Report Tab -->
            <div class="tab-pane fade" id="report" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Relatório de Operações</h5>
                    <div>
                        <input type="date" class="form-control form-control-sm d-inline-block" id="report-date" style="width: auto;">
                        <button class="btn btn-sm btn-outline-primary ms-2" onclick="cloneMonitoring.loadReport()">
                            <i class="bi bi-file-earmark-bar-graph"></i> Gerar Relatório
                        </button>
                    </div>
                </div>
                <div id="report-content">
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-calendar3 fs-1 d-block mb-2"></i>
                        Selecione uma data para gerar o relatório
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.form-switch .form-check-input {
    width: 3em;
    height: 1.5em;
}
.flag-card {
    transition: all 0.2s ease;
}
.flag-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
}
.severity-critical { color: #dc3545; }
.severity-warning { color: #ffc107; }
.severity-info { color: #0dcaf0; }
</style>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
async function requestJson(url, options = {}) {
    if (window.ApiClient) return window.ApiClient.request(url, options);
    const resp = await fetch(url, { credentials: 'include', ...options });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}

const cloneMonitoring = {
    init: function() {
        this.loadHealth();
        this.loadAlerts();
        this.loadFlags();
        
        // Set today's date as default
        document.getElementById('report-date').valueAsDate = new Date();
        
        // Auto-refresh every 30 seconds
        setInterval(() => this.loadHealth(), 30000);
        
        // Handle show acknowledged checkbox
        document.getElementById('showAcknowledged').addEventListener('change', () => this.loadAlerts());
    },

    loadHealth: async function() {
        try {
            const data = await requestJson('/api/catalog/clone/monitoring/health');
            
            const statusEl = document.getElementById('health-status');
            const iconContainer = document.getElementById('health-icon-container');
            const icon = document.getElementById('health-icon');
            
            // Update status text and styling
            const statusMap = {
                'healthy': { text: 'Saudável', bg: 'bg-success', icon: 'bi-heart-pulse-fill' },
                'degraded': { text: 'Degradado', bg: 'bg-warning', icon: 'bi-exclamation-circle' },
                'critical': { text: 'Crítico', bg: 'bg-danger', icon: 'bi-x-circle-fill' }
            };
            
            const config = statusMap[data.status] || statusMap['healthy'];
            statusEl.textContent = config.text;
            iconContainer.className = `rounded-circle ${config.bg} bg-opacity-10 p-3`;
            icon.className = `bi ${config.icon} fs-4 ${data.status === 'healthy' ? 'text-success' : data.status === 'degraded' ? 'text-warning' : 'text-danger'}`;
            
            // Update metrics
            document.getElementById('error-rate').textContent = (data.error_rate || 0).toFixed(1);
            document.getElementById('unresolved-alerts').textContent = data.unresolved_alerts || 0;
            document.getElementById('operations-1h').textContent = data.operations_1h || 0;
            
            // Update alerts badge
            const badge = document.getElementById('alerts-badge');
            if (data.unresolved_alerts > 0) {
                badge.textContent = data.unresolved_alerts;
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        } catch (error) {
            console.error('Error loading health:', error);
        }
    },

    loadAlerts: async function() {
        try {
            const showAck = document.getElementById('showAcknowledged').checked;
            const data = await requestJson(`/api/catalog/clone/monitoring/alerts?acknowledged=${showAck ? 'all' : 'false'}`);
            
            const tbody = document.getElementById('alerts-list');
            
            if (!data.alerts || data.alerts.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
                            Nenhum alerta ativo
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = data.alerts.map(alert => `
                <tr class="${alert.acknowledged ? 'table-secondary' : ''}">
                    <td>
                        <span class="badge bg-${alert.severity === 'critical' ? 'danger' : alert.severity === 'warning' ? 'warning' : 'info'}">
                            ${alert.severity}
                        </span>
                    </td>
                    <td><code>${alert.alert_type}</code></td>
                    <td>${this.escapeHtml(alert.message)}</td>
                    <td class="small text-muted">${new Date(alert.created_at).toLocaleString('pt-BR')}</td>
                    <td>
                        ${alert.acknowledged 
                            ? '<span class="text-muted small">✓ Reconhecido</span>' 
                            : `<button class="btn btn-sm btn-outline-success" onclick="cloneMonitoring.acknowledgeAlert(${alert.id})">
                                <i class="bi bi-check"></i> Ack
                            </button>`
                        }
                    </td>
                </tr>
            `).join('');
        } catch (error) {
            console.error('Error loading alerts:', error);
        }
    },

    acknowledgeAlert: async function(alertId) {
        try {
            const response = await fetch(`/api/catalog/clone/monitoring/alerts/${alertId}/acknowledge`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (response.ok) {
                this.loadAlerts();
                this.loadHealth();
            }
        } catch (error) {
            console.error('Error acknowledging alert:', error);
        }
    },

    loadFlags: async function() {
        try {
            const data = await requestJson('/api/catalog/clone/monitoring/flags');
            
            const container = document.getElementById('flags-list');
            
            if (!data.flags || data.flags.length === 0) {
                container.innerHTML = '<div class="col-12 text-center py-4 text-muted">Nenhuma feature flag configurada</div>';
                return;
            }
            
            container.innerHTML = data.flags.map(flag => `
                <div class="col-md-6 col-lg-4">
                    <div class="card flag-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">${this.formatFlagName(flag.flag_name)}</h6>
                                    <p class="text-muted small mb-2">${flag.description || 'Sem descrição'}</p>
                                    <small class="text-muted">Atualizado: ${new Date(flag.updated_at).toLocaleString('pt-BR')}</small>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="flag-${flag.flag_name}" 
                                        ${flag.is_enabled ? 'checked' : ''} 
                                        onchange="cloneMonitoring.toggleFlag('${flag.flag_name}', this.checked)">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        } catch (error) {
            console.error('Error loading flags:', error);
        }
    },

    toggleFlag: async function(flagName, enabled) {
        try {
            const response = await fetch(`/api/catalog/clone/monitoring/flags/${flagName}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled })
            });
            
            if (response.ok) {
                // Show toast or feedback
                const toast = document.createElement('div');
                toast.className = 'position-fixed bottom-0 end-0 p-3';
                toast.innerHTML = `
                    <div class="toast show bg-success text-white" role="alert">
                        <div class="toast-body">
                            <i class="bi bi-check-circle me-2"></i>
                            Flag "${this.formatFlagName(flagName)}" ${enabled ? 'habilitada' : 'desabilitada'}
                        </div>
                    </div>
                `;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }
        } catch (error) {
            console.error('Error toggling flag:', error);
        }
    },

    loadReport: async function() {
        const date = document.getElementById('report-date').value;
        if (!date) return;
        
        const container = document.getElementById('report-content');
        container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Gerando relatório...</p></div>';
        
        try {
            const data = await requestJson(`/api/catalog/clone/monitoring/report?date=${date}`);
            
            container.innerHTML = `
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6><i class="bi bi-bar-chart"></i> Métricas do Dia</h6>
                        <table class="table table-sm">
                            <thead class="bg-light">
                                <tr><th>Métrica</th><th>Total</th><th>Média</th></tr>
                            </thead>
                            <tbody>
                                ${data.metrics.map(m => `
                                    <tr>
                                        <td><code>${m.metric_name}</code></td>
                                        <td>${parseFloat(m.total).toFixed(2)}</td>
                                        <td>${parseFloat(m.average).toFixed(2)}</td>
                                    </tr>
                                `).join('') || '<tr><td colspan="3" class="text-center text-muted">Sem dados</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="bi bi-exclamation-triangle"></i> Alertas do Dia</h6>
                        <table class="table table-sm">
                            <thead class="bg-light">
                                <tr><th>Tipo</th><th>Severidade</th><th>Quantidade</th></tr>
                            </thead>
                            <tbody>
                                ${data.alerts.map(a => `
                                    <tr>
                                        <td><code>${a.alert_type}</code></td>
                                        <td><span class="badge bg-${a.severity === 'critical' ? 'danger' : a.severity === 'warning' ? 'warning' : 'info'}">${a.severity}</span></td>
                                        <td>${a.count}</td>
                                    </tr>
                                `).join('') || '<tr><td colspan="3" class="text-center text-muted">Sem alertas</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                    <div class="col-12">
                        <h6><i class="bi bi-files"></i> Clonagens do Dia</h6>
                        <div class="row g-3">
                            ${data.clones.map(c => `
                                <div class="col-md-3">
                                    <div class="card bg-${c.status === 'created' || c.status === 'success' ? 'success' : c.status === 'error' ? 'danger' : 'secondary'} bg-opacity-10 text-center py-3">
                                        <h4 class="mb-0">${c.count}</h4>
                                        <small class="text-muted">${c.status}</small>
                                    </div>
                                </div>
                            `).join('') || '<div class="col-12 text-center text-muted">Sem clonagens</div>'}
                        </div>
                    </div>
                </div>
                <div class="mt-3 text-muted small">
                    <i class="bi bi-clock"></i> Relatório gerado em: ${data.generated_at}
                </div>
            `;
        } catch (error) {
            container.innerHTML = '<div class="alert alert-danger">Erro ao carregar relatório</div>';
            console.error('Error loading report:', error);
        }
    },

    formatFlagName: function(name) {
        return name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    },

    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize on load
document.addEventListener('DOMContentLoaded', () => cloneMonitoring.init());
</script>
