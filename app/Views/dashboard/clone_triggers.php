<?php
/**
 * Clone Event Triggers Dashboard
 * 
 * Interface para gerenciar triggers de eventos e visualizar gráficos
 */

$pageTitle = 'Triggers de Eventos';
ob_start();
?>

<style>
.trigger-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
}

.trigger-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.25rem;
    border: 1px solid var(--border-color);
    transition: all 0.2s;
}

.trigger-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.trigger-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.trigger-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.trigger-icon.new_items { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.trigger-icon.price_drop { background: rgba(234, 179, 8, 0.15); color: #eab308; }
.trigger-icon.stock_available { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
.trigger-icon.competitor_out { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; }

.trigger-title {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
}

.trigger-badge {
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.trigger-badge.active { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
.trigger-badge.inactive { background: rgba(156, 163, 175, 0.15); color: #9ca3af; }

.trigger-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
    margin: 1rem 0;
    padding: 0.75rem;
    background: var(--bg-secondary);
    border-radius: 8px;
}

.trigger-stat {
    text-align: center;
}

.trigger-stat-value {
    font-size: 1.25rem;
    font-weight: 700;
}

.trigger-stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.trigger-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.trigger-actions button {
    flex: 1;
    padding: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: transparent;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.85rem;
}

.trigger-actions button:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.chart-container {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.25rem;
    border: 1px solid var(--border-color);
    margin-bottom: 1.5rem;
}

.chart-container canvas {
    max-height: 300px;
}

.charts-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

@media (max-width: 992px) {
    .charts-grid { grid-template-columns: 1fr; }
}

.event-type-selector {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.event-type-btn {
    padding: 0.5rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 20px;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
}

.event-type-btn:hover, .event-type-btn.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Triggers de Eventos</h1>
            <p class="text-muted mb-0">Monitore sellers e dispare clonagens automaticamente</p>
        </div>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <i class="bi bi-plus-lg"></i> Novo Trigger
        </button>
    </div>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center p-3">
                <div class="h2 mb-1" id="statTotal">-</div>
                <div class="text-muted small">Total Triggers</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center p-3">
                <div class="h2 mb-1 text-success" id="statActive">-</div>
                <div class="text-muted small">Ativos</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center p-3">
                <div class="h2 mb-1 text-primary" id="statEvents">-</div>
                <div class="text-muted small">Eventos (30d)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center p-3">
                <div class="h2 mb-1 text-info" id="statActions">-</div>
                <div class="text-muted small">Ações Executadas</div>
            </div>
        </div>
    </div>
    
    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#triggersTab">
                <i class="bi bi-lightning"></i> Triggers
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#chartsTab">
                <i class="bi bi-graph-up"></i> Gráficos
            </button>
        </li>
    </ul>
    
    <div class="tab-content">
        <!-- Triggers Tab -->
        <div class="tab-pane fade show active" id="triggersTab">
            <div class="trigger-grid" id="triggersGrid">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
        </div>
        
        <!-- Charts Tab -->
        <div class="tab-pane fade" id="chartsTab">
            <div class="charts-grid">
                <div class="chart-container">
                    <h6>Clonagens por Dia</h6>
                    <canvas id="chartClonesPerDay"></canvas>
                </div>
                <div class="chart-container">
                    <h6>Taxa de Sucesso por Hora</h6>
                    <canvas id="chartSuccessByHour"></canvas>
                </div>
                <div class="chart-container">
                    <h6>Por Categoria</h6>
                    <canvas id="chartByCategory"></canvas>
                </div>
                <div class="chart-container">
                    <h6>Eventos por Tipo</h6>
                    <canvas id="chartEventsByType"></canvas>
                </div>
                <div class="chart-container">
                    <h6>Métricas de Qualidade</h6>
                    <canvas id="chartQualityMetrics"></canvas>
                </div>
                <div class="chart-container">
                    <h6>Distribuição de Status</h6>
                    <canvas id="chartStatusDistribution"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="triggerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Trigger</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="triggerForm">
                    <input type="hidden" id="triggerId">
                    
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Nome *</label>
                            <input type="text" class="form-control" id="triggerName" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo de Evento *</label>
                            <select class="form-select" id="triggerEventType" required>
                                <option value="new_items">🆕 Novos Itens</option>
                                <option value="price_drop">📉 Queda de Preço</option>
                                <option value="stock_available">📦 Estoque Disponível</option>
                                <option value="competitor_out">🎯 Concorrente Sem Estoque</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipo de Origem *</label>
                            <select class="form-select" id="triggerSourceType">
                                <option value="seller_id">Seller ID</option>
                                <option value="category_id">Categoria</option>
                                <option value="search_query">Busca</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Valor *</label>
                            <input type="text" class="form-control" id="triggerSourceValue" 
                                   placeholder="Ex: 123456789" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Intervalo de Verificação (min)</label>
                            <input type="number" class="form-control" id="triggerInterval" 
                                   value="30" min="5" max="1440">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Threshold de Queda (%)</label>
                            <input type="number" class="form-control" id="triggerThreshold" 
                                   value="10" min="1" max="90">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ações</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="actionClone" checked>
                            <label class="form-check-label" for="actionClone">
                                Clonar automaticamente
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="actionNotify" checked>
                            <label class="form-check-label" for="actionNotify">
                                Enviar notificação (Slack/Discord)
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" id="triggerDescription" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveTrigger()">
                    <i class="bi bi-check-lg"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
async function requestJson(url, options = {}) {
    if (window.ApiClient) {
        return window.ApiClient.request(url, options);
    }
    const resp = await fetch(url, { credentials: 'include', ...options });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}

let triggerModal = null;
let charts = {};

document.addEventListener('DOMContentLoaded', function() {
    triggerModal = new bootstrap.Modal(document.getElementById('triggerModal'));
    loadStats();
    loadTriggers();
    
    // Carregar gráficos quando a tab for ativada
    document.querySelector('[data-bs-target="#chartsTab"]').addEventListener('shown.bs.tab', loadCharts);
});

async function loadStats() {
    try {
        const stats = await requestJson('/api/clone/triggers/stats');
        
        document.getElementById('statTotal').textContent = stats.total_triggers || 0;
        document.getElementById('statActive').textContent = stats.active_triggers || 0;
        document.getElementById('statEvents').textContent = stats.total_events || 0;
        document.getElementById('statActions').textContent = stats.total_actions || 0;
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadTriggers() {
    try {
        const data = await requestJson('/api/clone/triggers');
        
        const triggers = data.triggers || [];
        
        if (triggers.length === 0) {
            document.getElementById('triggersGrid').innerHTML = `
                <div class="text-center py-5" style="grid-column: 1/-1">
                    <i class="bi bi-lightning" style="font-size: 3rem; color: var(--text-secondary);"></i>
                    <p class="text-muted mt-2">Nenhum trigger configurado</p>
                    <button class="btn btn-primary" onclick="openCreateModal()">
                        <i class="bi bi-plus-lg"></i> Criar Primeiro Trigger
                    </button>
                </div>
            `;
            return;
        }
        
        let html = '';
        for (const trigger of triggers) {
            html += renderTriggerCard(trigger);
        }
        
        document.getElementById('triggersGrid').innerHTML = html;
    } catch (error) {
        console.error('Error loading triggers:', error);
    }
}

function renderTriggerCard(trigger) {
    const icons = {
        'new_items': '🆕',
        'price_drop': '📉',
        'stock_available': '📦',
        'competitor_out': '🎯'
    };
    
    const labels = {
        'new_items': 'Novos Itens',
        'price_drop': 'Queda de Preço',
        'stock_available': 'Estoque Disponível',
        'competitor_out': 'Concorrente Sem Estoque'
    };
    
    const status = trigger.is_active ? 'active' : 'inactive';
    const statusText = status === 'active' ? 'Ativo' : 'Inativo';
    
    return `
        <div class="trigger-card">
            <div class="trigger-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="trigger-icon ${trigger.event_type}">
                        ${icons[trigger.event_type] || '⚡'}
                    </div>
                    <div>
                        <h6 class="trigger-title">${trigger.name}</h6>
                        <small class="text-muted">${labels[trigger.event_type] || trigger.event_type}</small>
                    </div>
                </div>
                <span class="trigger-badge ${status}">${statusText}</span>
            </div>
            
            <div class="trigger-stats">
                <div class="trigger-stat">
                    <div class="trigger-stat-value">${trigger.total_events_detected || 0}</div>
                    <div class="trigger-stat-label">Eventos</div>
                </div>
                <div class="trigger-stat">
                    <div class="trigger-stat-value">${trigger.total_actions_executed || 0}</div>
                    <div class="trigger-stat-label">Ações</div>
                </div>
            </div>
            
            <div class="text-muted small mb-2">
                <i class="bi bi-clock"></i> A cada ${trigger.check_interval_minutes} min
            </div>
            
            <div class="trigger-actions">
                ${trigger.is_active 
                    ? `<button onclick="deactivateTrigger('${trigger.trigger_id}')" title="Desativar"><i class="bi bi-pause-fill"></i></button>`
                    : `<button onclick="activateTrigger('${trigger.trigger_id}')" title="Ativar"><i class="bi bi-play-fill"></i></button>`
                }
                <button onclick="testTrigger('${trigger.trigger_id}')" title="Testar"><i class="bi bi-bug"></i></button>
                <button onclick="viewHistory('${trigger.trigger_id}')" title="Histórico"><i class="bi bi-clock-history"></i></button>
                <button onclick="deleteTrigger('${trigger.trigger_id}')" title="Excluir"><i class="bi bi-trash"></i></button>
            </div>
        </div>
    `;
}

function openCreateModal() {
    document.getElementById('triggerId').value = '';
    document.getElementById('triggerForm').reset();
    document.querySelector('#triggerModal .modal-title').textContent = 'Novo Trigger';
    triggerModal.show();
}

async function saveTrigger() {
    const id = document.getElementById('triggerId').value;
    
    const actions = [];
    if (document.getElementById('actionClone').checked) {
        actions.push({ type: 'clone', seo_optimization: true, seo_level: 'basic' });
    }
    if (document.getElementById('actionNotify').checked) {
        actions.push({ type: 'notify', channels: ['slack'] });
    }
    
    const data = {
        name: document.getElementById('triggerName').value,
        event_type: document.getElementById('triggerEventType').value,
        source_type: document.getElementById('triggerSourceType').value,
        source_value: document.getElementById('triggerSourceValue').value,
        check_interval_minutes: parseInt(document.getElementById('triggerInterval').value),
        conditions: {
            price_drop_threshold: parseInt(document.getElementById('triggerThreshold').value)
        },
        actions: actions,
        description: document.getElementById('triggerDescription').value,
    };
    
    try {
        const url = id ? `/api/clone/triggers/${id}` : '/api/clone/triggers';
        const method = id ? 'PUT' : 'POST';
        
        const result = await requestJson(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (result.error) {
            throw new Error(result.error);
        }
        
        triggerModal.hide();
        loadTriggers();
        loadStats();
        
    } catch (error) {
        alert('Erro: ' + error.message);
    }
}

async function activateTrigger(id) {
    await requestJson(`/api/clone/triggers/${id}/activate`, { method: 'POST' });
    loadTriggers();
    loadStats();
}

async function deactivateTrigger(id) {
    if (!confirm('Desativar este trigger?')) return;
    await requestJson(`/api/clone/triggers/${id}/deactivate`, { method: 'POST' });
    loadTriggers();
    loadStats();
}

async function testTrigger(id) {
    try {
        const result = await requestJson(`/api/clone/triggers/${id}/test`, { method: 'POST' });
        
        alert(`Teste concluído!\nEventos detectados: ${result.events_detected || 0}`);
    } catch (error) {
        alert('Erro no teste: ' + error.message);
    }
}

async function viewHistory(id) {
    // Poderia abrir modal com histórico
    window.location.href = `/dashboard/catalog/clone-analytics?trigger=${id}`;
}

async function deleteTrigger(id) {
    if (!confirm('Excluir este trigger?')) return;
    await requestJson(`/api/clone/triggers/${id}`, { method: 'DELETE' });
    loadTriggers();
    loadStats();
}

async function loadCharts() {
    try {
        const data = await requestJson('/api/clone/charts/dashboard');
        
        // Renderizar cada gráfico
        if (data.clones_per_day) {
            renderChart('chartClonesPerDay', data.clones_per_day);
        }
        if (data.success_by_hour) {
            renderChart('chartSuccessByHour', data.success_by_hour);
        }
        if (data.clones_by_category) {
            renderChart('chartByCategory', data.clones_by_category);
        }
        if (data.events_by_type) {
            renderChart('chartEventsByType', data.events_by_type);
        }
        if (data.quality_metrics) {
            renderChart('chartQualityMetrics', data.quality_metrics);
        }
        if (data.status_distribution) {
            renderChart('chartStatusDistribution', data.status_distribution);
        }
        
    } catch (error) {
        console.error('Error loading charts:', error);
    }
}

function renderChart(canvasId, config) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    // Destruir gráfico existente
    if (charts[canvasId]) {
        charts[canvasId].destroy();
    }
    
    charts[canvasId] = new Chart(ctx, {
        type: config.type,
        data: config.data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            ...config.options
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard.php';
?>
