<?php
/**
 * Clone Auto-Scheduler Dashboard
 * 
 * Interface para gerenciar clonagens automáticas programadas
 */

$pageTitle = 'Auto-Clonagem Programada';
ob_start();
?>

<style>
.scheduler-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.schedule-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border-color);
    transition: all 0.2s;
}

.schedule-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.schedule-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.schedule-name {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0;
}

.schedule-status {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.schedule-status.active { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
.schedule-status.paused { background: rgba(234, 179, 8, 0.15); color: #eab308; }
.schedule-status.running { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.schedule-status.failed { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

.schedule-info {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.schedule-info-item {
    font-size: 0.9rem;
}

.schedule-info-item label {
    display: block;
    color: var(--text-secondary);
    font-size: 0.8rem;
    margin-bottom: 0.25rem;
}

.schedule-actions {
    display: flex;
    gap: 0.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.schedule-actions button {
    flex: 1;
    padding: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: transparent;
    cursor: pointer;
    transition: all 0.2s;
}

.schedule-actions button:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.recommendation-card {
    background: var(--card-bg);
    border-radius: 10px;
    padding: 1rem;
    border: 1px solid var(--border-color);
    margin-bottom: 0.75rem;
}

.recommendation-score {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    font-weight: 700;
    font-size: 0.9rem;
}

.score-high { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
.score-medium { background: rgba(234, 179, 8, 0.15); color: #eab308; }
.score-low { background: rgba(156, 163, 175, 0.15); color: #9ca3af; }

.recommendation-reason {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 0.5rem;
}

.stats-overview {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--card-bg);
    border-radius: 10px;
    padding: 1.25rem;
    text-align: center;
    border: 1px solid var(--border-color);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-color);
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
}

.tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 0.5rem;
}

.tab {
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s;
}

.tab.active {
    background: var(--primary-color);
    color: white;
}

@media (max-width: 768px) {
    .stats-overview { grid-template-columns: repeat(2, 1fr); }
    .scheduler-grid { grid-template-columns: 1fr; }
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Auto-Clonagem Programada</h1>
            <p class="text-muted mb-0">Configure clonagens automáticas baseadas em regras</p>
        </div>
        <button class="btn btn-primary" data-action="opencreatemodal">
            <i class="bi bi-plus-lg"></i> Novo Agendamento
        </button>
    </div>
    
    <!-- Stats Overview -->
    <div class="stats-overview" id="statsOverview">
        <div class="stat-card">
            <div class="stat-value" id="totalSchedules">-</div>
            <div class="stat-label">Agendamentos</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="activeSchedules">-</div>
            <div class="stat-label">Ativos</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="totalRuns">-</div>
            <div class="stat-label">Execuções (30d)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="itemsCloned">-</div>
            <div class="stat-label">Itens Clonados</div>
        </div>
    </div>
    
    <!-- Tabs -->
    <div class="tabs">
        <button class="tab active" onclick="showTab('schedules')">
            <i class="bi bi-calendar-check"></i> Agendamentos
        </button>
        <button class="tab" onclick="showTab('recommendations')">
            <i class="bi bi-lightbulb"></i> Recomendações
        </button>
        <button class="tab" onclick="showTab('trends')">
            <i class="bi bi-graph-up"></i> Tendências
        </button>
    </div>
    
    <!-- Schedules Tab -->
    <div id="schedulesTab" class="tab-content">
        <div class="scheduler-grid" id="schedulesGrid">
            <div class="text-center py-4">
                <div class="spinner-border text-primary"></div>
            </div>
        </div>
    </div>
    
    <!-- Recommendations Tab -->
    <div id="recommendationsTab" class="tab-content" style="display: none;">
        <div class="row">
            <div class="col-md-6">
                <div class="section-header">
                    <h5 class="section-title">Sellers Recomendados</h5>
                    <button class="btn btn-sm btn-outline-secondary" data-action="loadsellerrecommendations">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
                <div id="sellerRecommendations">
                    <p class="text-muted">Carregando...</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="section-header">
                    <h5 class="section-title">Categorias Promissoras</h5>
                    <button class="btn btn-sm btn-outline-secondary" data-action="loadcategoryrecommendations">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
                <div id="categoryRecommendations">
                    <p class="text-muted">Carregando...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Trends Tab -->
    <div id="trendsTab" class="tab-content" style="display: none;">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Melhores Horários para Clonar</h5>
                    </div>
                    <div class="card-body" id="bestTimesContent">
                        <p class="text-muted">Carregando análise...</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Insights</h5>
                    </div>
                    <div class="card-body" id="insightsContent">
                        <p class="text-muted">Carregando...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Agendamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleForm">
                    <input type="hidden" id="scheduleId">
                    
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Nome do Agendamento *</label>
                            <input type="text" class="form-control" id="scheduleName" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Frequência</label>
                            <select class="form-select" id="scheduleFrequency">
                                <option value="daily">Diário</option>
                                <option value="hourly">Por Hora</option>
                                <option value="weekly">Semanal</option>
                                <option value="monthly">Mensal</option>
                                <option value="once">Uma Vez</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipo de Origem *</label>
                            <select class="form-select" id="scheduleSourceType" required>
                                <option value="seller_id">Seller ID</option>
                                <option value="category_id">Categoria</option>
                                <option value="search_query">Busca</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Valor da Origem *</label>
                            <input type="text" class="form-control" id="scheduleSourceValue" required
                                   placeholder="Ex: 123456789 ou MLB1234">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Horário de Execução</label>
                            <input type="time" class="form-control" id="scheduleTime" value="03:00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Máx. Itens por Execução</label>
                            <input type="number" class="form-control" id="scheduleMaxItems" value="50" min="1" max="200">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nível SEO</label>
                            <select class="form-select" id="scheduleSeoLevel">
                                <option value="none">Nenhum</option>
                                <option value="basic" selected>Básico</option>
                                <option value="advanced">Avançado</option>
                                <option value="aggressive">Agressivo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" id="scheduleDescription" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" data-action="saveschedule">
                    <i class="bi bi-check-lg"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

let scheduleModal = null;

document.addEventListener('DOMContentLoaded', function() {
    scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
    loadStats();
    loadSchedules();
});

function showTab(tab) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
    
    event.target.classList.add('active');
    document.getElementById(tab + 'Tab').style.display = 'block';
    
    if (tab === 'recommendations') {
        loadSellerRecommendations();
        loadCategoryRecommendations();
    } else if (tab === 'trends') {
        loadTrends();
    }
}

async function loadStats() {
    try {
        const data = await requestJson('/api/clone/schedules/stats');
        
        const schedules = data.schedules || {};
        const runs = data.runs_last_30_days || {};
        
        document.getElementById('totalSchedules').textContent = schedules.total_schedules || 0;
        document.getElementById('activeSchedules').textContent = schedules.active_schedules || 0;
        document.getElementById('totalRuns').textContent = runs.total_runs || 0;
        document.getElementById('itemsCloned').textContent = runs.total_items_cloned || 0;
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadSchedules() {
    try {
        const data = await requestJson('/api/clone/schedules');
        
        const schedules = data.schedules || [];
        
        if (schedules.length === 0) {
            document.getElementById('schedulesGrid').innerHTML = `
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x" style="font-size: 3rem; color: var(--text-secondary);"></i>
                    <p class="text-muted mt-2">Nenhum agendamento configurado</p>
                    <button class="btn btn-primary" data-action="opencreatemodal">
                        <i class="bi bi-plus-lg"></i> Criar Primeiro Agendamento
                    </button>
                </div>
            `;
            return;
        }
        
        let html = '';
        for (const schedule of schedules) {
            html += renderScheduleCard(schedule);
        }
        
        document.getElementById('schedulesGrid').innerHTML = html;
    } catch (error) {
        console.error('Error loading schedules:', error);
    }
}

function renderScheduleCard(schedule) {
    const status = schedule.is_active ? 'active' : 'paused';
    const statusText = status === 'active' ? 'Ativo' : 'Pausado';
    
    const sourceTypes = {
        'seller_id': 'Seller',
        'category_id': 'Categoria',
        'search_query': 'Busca'
    };
    
    const frequencies = {
        'daily': 'Diário',
        'hourly': 'Por Hora',
        'weekly': 'Semanal',
        'monthly': 'Mensal',
        'once': 'Uma Vez'
    };
    
    return `
        <div class="schedule-card">
            <div class="schedule-header">
                <h5 class="schedule-name">${schedule.name}</h5>
                <span class="schedule-status ${status}">${statusText}</span>
            </div>
            
            <div class="schedule-info">
                <div class="schedule-info-item">
                    <label>Origem</label>
                    <span>${sourceTypes[schedule.source_type] || schedule.source_type}: ${schedule.source_value}</span>
                </div>
                <div class="schedule-info-item">
                    <label>Frequência</label>
                    <span>${frequencies[schedule.frequency] || schedule.frequency}</span>
                </div>
                <div class="schedule-info-item">
                    <label>Próxima Execução</label>
                    <span>${schedule.next_run_at ? new Date(schedule.next_run_at).toLocaleString('pt-BR') : '-'}</span>
                </div>
                <div class="schedule-info-item">
                    <label>Execuções</label>
                    <span>${schedule.successful_runs || 0} / ${schedule.total_runs || 0}</span>
                </div>
            </div>
            
            <div class="schedule-actions">
                ${schedule.is_active 
                    ? `<button onclick="pauseSchedule(${schedule.id})" title="Pausar"><i class="bi bi-pause-fill"></i></button>`
                    : `<button onclick="resumeSchedule(${schedule.id})" title="Resumir"><i class="bi bi-play-fill"></i></button>`
                }
                <button onclick="executeNow(${schedule.id})" title="Executar Agora"><i class="bi bi-lightning"></i></button>
                <button onclick="editSchedule(${schedule.id})" title="Editar"><i class="bi bi-pencil"></i></button>
                <button onclick="viewHistory(${schedule.id})" title="Histórico"><i class="bi bi-clock-history"></i></button>
                <button onclick="deleteSchedule(${schedule.id})" title="Excluir"><i class="bi bi-trash"></i></button>
            </div>
        </div>
    `;
}

function openCreateModal() {
    document.getElementById('scheduleId').value = '';
    document.getElementById('scheduleForm').reset();
    document.querySelector('#scheduleModal .modal-title').textContent = 'Novo Agendamento';
    scheduleModal.show();
}

async function saveSchedule() {
    const id = document.getElementById('scheduleId').value;
    const time = document.getElementById('scheduleTime').value.split(':');
    
    const data = {
        name: document.getElementById('scheduleName').value,
        source_type: document.getElementById('scheduleSourceType').value,
        source_value: document.getElementById('scheduleSourceValue').value,
        frequency: document.getElementById('scheduleFrequency').value,
        run_at_hour: parseInt(time[0]),
        run_at_minute: parseInt(time[1]),
        max_items_per_run: parseInt(document.getElementById('scheduleMaxItems').value),
        seo_level: document.getElementById('scheduleSeoLevel').value,
        description: document.getElementById('scheduleDescription').value,
    };
    
    try {
        const url = id ? `/api/clone/schedules/${id}` : '/api/clone/schedules';
        const method = id ? 'PUT' : 'POST';
        
        const result = await requestJson(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (result.error) {
            throw new Error(result.error);
        }
        
        scheduleModal.hide();
        loadSchedules();
        loadStats();
        
    } catch (error) {
        alert('Erro: ' + error.message);
    }
}

async function pauseSchedule(id) {
    if (!confirm('Pausar este agendamento?')) return;
    
    await requestJson(`/api/clone/schedules/${id}/pause`, { method: 'POST' });
    loadSchedules();
}

async function resumeSchedule(id) {
    await requestJson(`/api/clone/schedules/${id}/resume`, { method: 'POST' });
    loadSchedules();
}

async function executeNow(id) {
    if (!confirm('Executar agora?')) return;
    
    try {
        const result = await requestJson(`/api/clone/schedules/${id}/execute`, { method: 'POST' });
        
        if (result.error) {
            throw new Error(result.error);
        }
        
        alert(`Executado! ${result.items_found || 0} itens encontrados. Job #${result.job_id || 'N/A'}`);
        loadSchedules();
    } catch (error) {
        alert('Erro: ' + error.message);
    }
}

async function deleteSchedule(id) {
    if (!confirm('Excluir este agendamento?')) return;
    
    await requestJson(`/api/clone/schedules/${id}`, { method: 'DELETE' });
    loadSchedules();
    loadStats();
}

async function editSchedule(id) {
    const schedule = await requestJson(`/api/clone/schedules/${id}`);
    
    document.getElementById('scheduleId').value = schedule.id;
    document.getElementById('scheduleName').value = schedule.name;
    document.getElementById('scheduleSourceType').value = schedule.source_type;
    document.getElementById('scheduleSourceValue').value = schedule.source_value;
    document.getElementById('scheduleFrequency').value = schedule.frequency;
    document.getElementById('scheduleTime').value = 
        String(schedule.run_at_hour).padStart(2, '0') + ':' + 
        String(schedule.run_at_minute).padStart(2, '0');
    document.getElementById('scheduleMaxItems').value = schedule.max_items_per_run;
    document.getElementById('scheduleSeoLevel').value = schedule.seo_level;
    document.getElementById('scheduleDescription').value = schedule.description || '';
    
    document.querySelector('#scheduleModal .modal-title').textContent = 'Editar Agendamento';
    scheduleModal.show();
}

function viewHistory(id) {
    window.location.href = `/dashboard/catalog/clone-analytics?schedule=${id}`;
}

async function loadSellerRecommendations() {
    try {
        const data = await requestJson('/api/clone/recommendations/sellers?limit=5');
        
        const recommendations = data.recommendations || [];
        
        if (recommendations.length === 0) {
            document.getElementById('sellerRecommendations').innerHTML = 
                '<p class="text-muted">Nenhuma recomendação disponível</p>';
            return;
        }
        
        let html = '';
        for (const rec of recommendations) {
            const scoreClass = rec.score >= 80 ? 'score-high' : (rec.score >= 60 ? 'score-medium' : 'score-low');
            html += `
                <div class="recommendation-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="recommendation-score ${scoreClass}">${Math.round(rec.score)}</div>
                        <div class="flex-grow-1">
                            <strong>${rec.seller_name || rec.seller_id}</strong>
                            <div class="recommendation-reason">${rec.reason}</div>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" 
                                onclick="createFromRecommendation('seller_id', '${rec.seller_id}')">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                </div>
            `;
        }
        
        document.getElementById('sellerRecommendations').innerHTML = html;
    } catch (error) {
        console.error('Error:', error);
    }
}

async function loadCategoryRecommendations() {
    try {
        const data = await requestJson('/api/clone/recommendations/categories?limit=5');
        
        const recommendations = data.recommendations || [];
        
        if (recommendations.length === 0) {
            document.getElementById('categoryRecommendations').innerHTML = 
                '<p class="text-muted">Nenhuma recomendação disponível</p>';
            return;
        }
        
        let html = '';
        for (const rec of recommendations) {
            const scoreClass = rec.score >= 80 ? 'score-high' : (rec.score >= 60 ? 'score-medium' : 'score-low');
            html += `
                <div class="recommendation-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="recommendation-score ${scoreClass}">${Math.round(rec.score)}</div>
                        <div class="flex-grow-1">
                            <strong>${rec.category_name}</strong>
                            <div class="recommendation-reason">${rec.recommendation}</div>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" 
                                onclick="createFromRecommendation('category_id', '${rec.category_id}')">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                </div>
            `;
        }
        
        document.getElementById('categoryRecommendations').innerHTML = html;
    } catch (error) {
        console.error('Error:', error);
    }
}

async function loadTrends() {
    try {
        const data = await requestJson('/api/clone/recommendations/trends');
        
        // Best times
        const bestTimes = data.best_times || [];
        let timesHtml = '<div class="table-responsive"><table class="table table-sm">';
        timesHtml += '<thead><tr><th>Dia</th><th>Horário</th><th>Score</th></tr></thead><tbody>';
        
        for (const time of bestTimes) {
            timesHtml += `<tr><td>${time.day}</td><td>${time.hour}</td><td>${time.performance_score}</td></tr>`;
        }
        
        timesHtml += '</tbody></table></div>';
        timesHtml += `<div class="alert alert-info mt-3">${data.recommendation || ''}</div>`;
        
        document.getElementById('bestTimesContent').innerHTML = timesHtml;
        
        // Insights
        const insights = data.insights || {};
        let insightsHtml = `
            <p><i class="bi bi-lightbulb text-warning"></i> ${insights.tip || ''}</p>
            <p class="text-muted small">${insights.category_note || ''}</p>
        `;
        
        document.getElementById('insightsContent').innerHTML = insightsHtml;
        
    } catch (error) {
        console.error('Error:', error);
    }
}

function createFromRecommendation(sourceType, sourceValue) {
    document.getElementById('scheduleSourceType').value = sourceType;
    document.getElementById('scheduleSourceValue').value = sourceValue;
    document.getElementById('scheduleName').value = `Auto-clone ${sourceValue}`;
    openCreateModal();
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
?>
