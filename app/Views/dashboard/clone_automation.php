<?php
/**
 * Clone Automation View
 * 
 * Gerenciamento de regras de auto-clonagem
 */

$pageTitle = 'Automação de Clonagem';
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
                        <i class="bi bi-robot text-primary"></i>
                        Automação de Clonagem
                    </h1>
                    <p class="text-muted mb-0">Configure regras para clonar automaticamente anúncios</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newRuleModal">
                    <i class="bi bi-plus-lg me-1"></i>
                    Nova Regra
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4" id="statsCards">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total de Regras</h6>
                            <h3 class="mb-0" id="statTotalRules">-</h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-list-check text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Regras Ativas</h6>
                            <h3 class="mb-0" id="statActiveRules">-</h3>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-play-circle text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Execuções</h6>
                            <h3 class="mb-0" id="statExecutions">-</h3>
                        </div>
                        <div class="bg-info bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-arrow-repeat text-info fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Itens Clonados</h6>
                            <h3 class="mb-0" id="statCloned">-</h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-files text-warning fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rules List -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Regras de Automação</h5>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary active" data-filter="all">Todas</button>
                <button class="btn btn-outline-success" data-filter="active">Ativas</button>
                <button class="btn btn-outline-warning" data-filter="paused">Pausadas</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Status</th>
                            <th>Nome</th>
                            <th>Trigger</th>
                            <th>Fonte</th>
                            <th>Última Execução</th>
                            <th>Clonados</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="rulesTableBody">
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- New Rule Modal -->
<div class="modal fade" id="newRuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Regra de Automação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newRuleForm">
                    <!-- Basic Info -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Informações Básicas
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Nome da Regra *</label>
                                <input type="text" class="form-control" name="name" required 
                                       placeholder="Ex: Clonar eletrônicos do Seller X">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Trigger</label>
                                <select class="form-select" name="trigger_type" id="triggerType">
                                    <option value="schedule">Agendado</option>
                                    <option value="new_item">Novo Item</option>
                                    <option value="manual">Manual</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Source Config -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-box-arrow-in-right me-1"></i>
                            Fonte dos Anúncios
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tipo de Fonte</label>
                                <select class="form-select" name="source_type">
                                    <option value="seller">Seller ID</option>
                                    <option value="category">Categoria</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">ID da Fonte *</label>
                                <input type="text" class="form-control" name="source_id" required
                                       placeholder="Ex: 123456789 (Seller ID)">
                            </div>
                        </div>
                    </div>

                    <!-- Schedule Config (conditional) -->
                    <div class="mb-4" id="scheduleConfig">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-clock me-1"></i>
                            Agendamento
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Frequência</label>
                                <select class="form-select" name="frequency">
                                    <option value="daily">Diariamente</option>
                                    <option value="weekly">Semanalmente</option>
                                    <option value="hourly">A cada hora</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Horário</label>
                                <input type="time" class="form-control" name="run_at" value="03:00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cooldown (horas)</label>
                                <input type="number" class="form-control" name="cooldown_hours" value="24" min="1">
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-funnel me-1"></i>
                            Filtros
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Preço Mínimo</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" name="min_price" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Preço Máximo</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" name="max_price" step="0.01">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Excluir Keywords</label>
                                <input type="text" class="form-control" name="exclude_keywords"
                                       placeholder="Separe por vírgula: usado, defeito, peças">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="only_catalog" id="onlyCatalog">
                                    <label class="form-check-label" for="onlyCatalog">
                                        Apenas itens com catálogo
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="only_available" id="onlyAvailable" checked>
                                    <label class="form-check-label" for="onlyAvailable">
                                        Apenas itens ativos
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Limits -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-speedometer2 me-1"></i>
                            Limites
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Máximo por execução</label>
                                <input type="number" class="form-control" name="max_items_per_run" value="50" min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Máximo por dia</label>
                                <input type="number" class="form-control" name="max_items_per_day" value="200" min="1">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="createRule()">
                    <i class="bi bi-check-lg me-1"></i>
                    Criar Regra
                </button>
            </div>
        </div>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Histórico de Execuções</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="historyContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
async function requestJson(url, options = {}) {
    if (window.ApiClient) return window.ApiClient.request(url, options);
    const resp = await fetch(url, { credentials: 'include', ...options });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}

const API_BASE = '/api/clone/automation';
let currentFilter = 'all';

// Load on page ready
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadRules();

    // Trigger type change
    document.getElementById('triggerType').addEventListener('change', function() {
        document.getElementById('scheduleConfig').style.display = 
            this.value === 'schedule' ? 'block' : 'none';
    });

    // Filter buttons
    document.querySelectorAll('[data-filter]').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.dataset.filter;
            loadRules();
        });
    });
});

async function loadStats() {
    try {
        const data = await requestJson(`${API_BASE}/stats`);

        if (data.success && data.stats) {
            document.getElementById('statTotalRules').textContent = data.stats.total_rules || 0;
            document.getElementById('statActiveRules').textContent = data.stats.active_rules || 0;
            document.getElementById('statExecutions').textContent = data.stats.total_executions || 0;
            document.getElementById('statCloned').textContent = data.stats.total_items_cloned || 0;
        }
    } catch (e) {
        console.error('Error loading stats:', e);
    }
}

async function loadRules() {
    const tbody = document.getElementById('rulesTableBody');
    
    try {
        let url = `${API_BASE}/rules`;
        if (currentFilter !== 'all') {
            url += `?status=${currentFilter}`;
        }

        const data = await requestJson(url);

        if (!data.success || !data.rules || data.rules.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-robot fs-1 d-block mb-2"></i>
                        Nenhuma regra de automação configurada
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = data.rules.map(rule => `
            <tr data-rule-id="${rule.id}">
                <td>
                    ${getStatusBadge(rule.status)}
                </td>
                <td>
                    <strong>${escapeHtml(rule.name)}</strong>
                    <br>
                    <small class="text-muted">ID: ${rule.id}</small>
                </td>
                <td>
                    ${getTriggerBadge(rule.trigger_type)}
                </td>
                <td>
                    <small class="text-muted">${rule.source_type}:</small><br>
                    <code>${rule.source_id || '-'}</code>
                </td>
                <td>
                    ${rule.last_run ? formatDate(rule.last_run) : '<span class="text-muted">Nunca</span>'}
                </td>
                <td>
                    <strong>${rule.total_cloned || 0}</strong>
                    <small class="text-muted">/ ${rule.total_runs || 0} runs</small>
                </td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="previewRule(${rule.id})" title="Preview">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="executeRule(${rule.id})" title="Executar">
                            <i class="bi bi-play"></i>
                        </button>
                        ${rule.status === 'active' ? 
                            `<button class="btn btn-outline-warning" onclick="pauseRule(${rule.id})" title="Pausar">
                                <i class="bi bi-pause"></i>
                            </button>` :
                            `<button class="btn btn-outline-success" onclick="enableRule(${rule.id})" title="Ativar">
                                <i class="bi bi-play-circle"></i>
                            </button>`
                        }
                        <button class="btn btn-outline-info" onclick="showHistory(${rule.id})" title="Histórico">
                            <i class="bi bi-clock-history"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteRule(${rule.id})" title="Excluir">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

    } catch (e) {
        console.error('Error loading rules:', e);
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5 text-danger">
                    Erro ao carregar regras
                </td>
            </tr>
        `;
    }
}

async function createRule() {
    const form = document.getElementById('newRuleForm');
    const formData = new FormData(form);

    const data = {
        name: formData.get('name'),
        trigger_type: formData.get('trigger_type'),
        source_type: formData.get('source_type'),
        source_id: formData.get('source_id'),
        frequency: formData.get('frequency'),
        run_at: formData.get('run_at'),
        cooldown_hours: parseInt(formData.get('cooldown_hours')) || 24,
        min_price: parseFloat(formData.get('min_price')) || null,
        max_price: parseFloat(formData.get('max_price')) || null,
        exclude_keywords: formData.get('exclude_keywords') ? 
            formData.get('exclude_keywords').split(',').map(k => k.trim()) : [],
        only_catalog: formData.get('only_catalog') === 'on',
        only_available: formData.get('only_available') === 'on',
        max_items_per_run: parseInt(formData.get('max_items_per_run')) || 50,
        max_items_per_day: parseInt(formData.get('max_items_per_day')) || 200,
    };

    try {
        const result = await requestJson(`${API_BASE}/rules`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('newRuleModal')).hide();
            form.reset();
            loadStats();
            loadRules();
            showToast('Regra criada com sucesso!', 'success');
        } else {
            showToast(result.error || 'Erro ao criar regra', 'danger');
        }
    } catch (e) {
        console.error('Error creating rule:', e);
        showToast('Erro ao criar regra', 'danger');
    }
}

async function previewRule(id) {
    try {
        showToast('Gerando preview...', 'info');

        const result = await requestJson(`${API_BASE}/rules/${id}/preview`, {
            method: 'POST'
        });

        if (result.success && result.preview) {
            const items = result.preview.items || [];
            alert(`Preview da Regra:\n\n${result.preview.items_found} item(s) encontrado(s)\n\nPrimeiros 5:\n${items.slice(0, 5).map(i => `- ${i.title} (R$ ${i.price})`).join('\n')}`);
        } else {
            showToast(result.error || 'Erro ao gerar preview', 'danger');
        }
    } catch (e) {
        console.error('Error previewing:', e);
        showToast('Erro ao gerar preview', 'danger');
    }
}

async function executeRule(id) {
    if (!confirm('Executar esta regra agora?')) return;

    try {
        showToast('Executando regra...', 'info');

        const result = await requestJson(`${API_BASE}/rules/${id}/execute`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        });

        if (result.success && result.results) {
            const r = result.results;
            showToast(`Executado! ${r.items_cloned} clonado(s), ${r.items_failed} falha(s)`, 
                      r.items_failed > 0 ? 'warning' : 'success');
            loadStats();
            loadRules();
        } else {
            showToast(result.error || 'Erro ao executar', 'danger');
        }
    } catch (e) {
        console.error('Error executing:', e);
        showToast('Erro ao executar regra', 'danger');
    }
}

async function enableRule(id) {
    try {
        const result = await requestJson(`${API_BASE}/rules/${id}/enable`, { method: 'POST' });

        if (result.success) {
            loadRules();
            showToast('Regra ativada', 'success');
        } else {
            showToast(result.error || 'Erro', 'danger');
        }
    } catch (e) {
        showToast('Erro ao ativar', 'danger');
    }
}

async function pauseRule(id) {
    try {
        const result = await requestJson(`${API_BASE}/rules/${id}/pause`, { method: 'POST' });

        if (result.success) {
            loadRules();
            showToast('Regra pausada', 'warning');
        } else {
            showToast(result.error || 'Erro', 'danger');
        }
    } catch (e) {
        showToast('Erro ao pausar', 'danger');
    }
}

async function deleteRule(id) {
    if (!confirm('Excluir esta regra? Esta ação não pode ser desfeita.')) return;

    try {
        const result = await requestJson(`${API_BASE}/rules/${id}`, { method: 'DELETE' });

        if (result.success) {
            loadStats();
            loadRules();
            showToast('Regra excluída', 'success');
        } else {
            showToast(result.error || 'Erro ao excluir', 'danger');
        }
    } catch (e) {
        showToast('Erro ao excluir', 'danger');
    }
}

async function showHistory(id) {
    const modal = new bootstrap.Modal(document.getElementById('historyModal'));
    modal.show();

    const content = document.getElementById('historyContent');
    content.innerHTML = '<div class="text-center py-4"><div class="spinner-border"></div></div>';

    try {
        const data = await requestJson(`${API_BASE}/rules/${id}/history`);

        if (!data.success || !data.history || data.history.length === 0) {
            content.innerHTML = '<p class="text-center text-muted py-4">Nenhuma execução registrada</p>';
            return;
        }

        content.innerHTML = `
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Encontrados</th>
                        <th>Clonados</th>
                        <th>Falhas</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.history.map(h => `
                        <tr>
                            <td>${formatDate(h.executed_at)}</td>
                            <td>${h.items_found}</td>
                            <td class="text-success">${h.items_cloned}</td>
                            <td class="text-danger">${h.items_failed}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } catch (e) {
        content.innerHTML = '<p class="text-center text-danger py-4">Erro ao carregar histórico</p>';
    }
}

// Helpers
function getStatusBadge(status) {
    const badges = {
        'active': '<span class="badge bg-success">Ativa</span>',
        'paused': '<span class="badge bg-warning">Pausada</span>',
        'disabled': '<span class="badge bg-secondary">Desativada</span>'
    };
    return badges[status] || status;
}

function getTriggerBadge(type) {
    const badges = {
        'schedule': '<span class="badge bg-primary"><i class="bi bi-clock"></i> Agendado</span>',
        'new_item': '<span class="badge bg-info"><i class="bi bi-plus"></i> Novo Item</span>',
        'manual': '<span class="badge bg-secondary"><i class="bi bi-hand-index"></i> Manual</span>'
    };
    return badges[type] || type;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showToast(message, type = 'info') {
    // Simple alert fallback if no toast system
    if (typeof Toastify !== 'undefined') {
        Toastify({
            text: message,
            duration: 3000,
            className: `bg-${type}`,
        }).showToast();
    } else {
        console.log(`[${type}] ${message}`);
    }
}
</script>

<?php
$content = ob_get_clean();

include __DIR__ . '/../layouts/dashboard.php';
