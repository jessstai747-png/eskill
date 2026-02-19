<?php
$pageTitle = '🔍 Monitoramento de Concorrentes';
$activePage = 'competitor-monitor';

// Page Header
$title = '🔍 Monitoramento de Concorrentes';
$subtitle = 'Sistema Automatizado de Rastreamento e Alertas';
include __DIR__ . '/../layouts/modern/partials/page-header.php';
?>

<!-- Toastify for Notifications -->
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
.competitor-monitor {
    padding: 20px;
}

.tracking-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s;
    margin-bottom: 20px;
}

.tracking-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.competitor-item {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
    transition: all 0.2s;
}

.competitor-item:hover {
    border-color: #667eea;
    background: #f8f9fa;
}

.competitor-item.price-drop {
    border-color: #27ae60;
    background: rgba(39, 174, 96, 0.05);
}

.competitor-item.price-increase {
    border-color: #e74c3c;
    background: rgba(231, 76, 60, 0.05);
}

.competitor-item.out-of-stock {
    border-color: #f39c12;
    background: rgba(243, 156, 18, 0.05);
}

.alert-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #e74c3c;
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.1);
        opacity: 0.8;
    }
}

.price-history-chart {
    height: 200px;
    margin-top: 16px;
}

.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.monitoring-status {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 16px;
    font-size: 13px;
    font-weight: 600;
}

.monitoring-status.active {
    background: #d4edda;
    color: #155724;
}

.monitoring-status.paused {
    background: #fff3cd;
    color: #856404;
}

.monitoring-status.stopped {
    background: #f8d7da;
    color: #721c24;
}

.alert-card {
    background: white;
    border-left: 4px solid #667eea;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.alert-card.critical {
    border-left-color: #e74c3c;
}

.alert-card.warning {
    border-left-color: #f39c12;
}

.alert-card.info {
    border-left-color: #3498db;
}

.alert-card.success {
    border-left-color: #27ae60;
}

.filter-chips {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.filter-chip {
    padding: 8px 16px;
    border-radius: 20px;
    border: 1px solid #dee2e6;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
}

.filter-chip:hover {
    background: #f8f9fa;
}

.filter-chip.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: #667eea;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}

.stat-box .value {
    font-size: 32px;
    font-weight: bold;
    margin: 8px 0;
}

.stat-box .label {
    font-size: 14px;
    opacity: 0.9;
}
</style>

<div class="competitor-monitor">
    
    <!-- Header Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <button class="btn btn-primary" data-action="open-add-competitor-modal">
                <i class="bi bi-plus-circle"></i> Adicionar Concorrente
            </button>
            <button class="btn btn-success" data-action="start-monitoring">
                <i class="bi bi-play-circle"></i> Iniciar Monitoramento
            </button>
            <button class="btn btn-warning" data-action="pause-monitoring">
                <i class="bi bi-pause-circle"></i> Pausar
            </button>
        </div>
        <div>
            <button class="btn btn-outline-secondary" data-action="refresh-all">
                <i class="bi bi-arrow-clockwise"></i> Atualizar
            </button>
            <button class="btn btn-outline-primary" data-action="open-settings">
                <i class="bi bi-gear"></i> Configurações
            </button>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="label">Concorrentes Ativos</div>
            <div class="value" id="active-competitors">0</div>
        </div>
        <div class="stat-box">
            <div class="label">Alertas Hoje</div>
            <div class="value" id="todays-alerts">0</div>
        </div>
        <div class="stat-box">
            <div class="label">Mudanças de Preço</div>
            <div class="value" id="price-changes">0</div>
        </div>
        <div class="stat-box">
            <div class="label">Oportunidades</div>
            <div class="value" id="opportunities">0</div>
        </div>
    </div>

    <!-- Filter Chips -->
    <div class="filter-chips">
        <button class="filter-chip active" data-action="filter-competitors" data-filter="all">
            Todos
        </button>
        <button class="filter-chip" data-action="filter-competitors" data-filter="price_drop">
            <i class="bi bi-arrow-down-circle text-success"></i> Preço Caiu
        </button>
        <button class="filter-chip" data-action="filter-competitors" data-filter="price_increase">
            <i class="bi bi-arrow-up-circle text-danger"></i> Preço Subiu
        </button>
        <button class="filter-chip" data-action="filter-competitors" data-filter="out_of_stock">
            <i class="bi bi-exclamation-triangle text-warning"></i> Sem Estoque
        </button>
        <button class="filter-chip" data-action="filter-competitors" data-filter="new_listing">
            <i class="bi bi-star text-primary"></i> Novos
        </button>
    </div>

    <!-- Recent Alerts -->
    <div class="tracking-card">
        <h5 class="mb-3">
            <i class="bi bi-bell"></i> Alertas Recentes
            <span class="badge bg-danger ms-2" id="unread-alerts-badge">0</span>
        </h5>
        <div id="alerts-container">
            <!-- Dynamic alerts -->
        </div>
    </div>

    <!-- Tracked Competitors -->
    <div class="tracking-card">
        <h5 class="mb-3">
            <i class="bi bi-people"></i> Concorrentes Rastreados
        </h5>
        <div id="competitors-container">
            <!-- Dynamic competitors -->
        </div>
    </div>

</div>

<!-- Add Competitor Modal -->
<div class="modal fade" id="addCompetitorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adicionar Concorrente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Meu Produto (ML ID)</label>
                    <input type="text" class="form-control" id="my-item-id" placeholder="MLB123456789">
                </div>
                <div class="mb-3">
                    <label class="form-label">Produto Concorrente (ML ID)</label>
                    <input type="text" class="form-control" id="competitor-item-id" placeholder="MLB987654321">
                </div>
                <div class="mb-3">
                    <label class="form-label">Tipo de Alerta</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="alert-price-drop" checked>
                        <label class="form-check-label" for="alert-price-drop">
                            Queda de preço
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="alert-price-increase" checked>
                        <label class="form-check-label" for="alert-price-increase">
                            Aumento de preço
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="alert-stock" checked>
                        <label class="form-check-label" for="alert-stock">
                            Mudança de estoque
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" data-action="add-competitor">Adicionar</button>
            </div>
        </div>
    </div>
</div>

<!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configurações de Monitoramento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Frequência de Verificação</label>
                    <select class="form-select" id="check-frequency">
                        <option value="15">A cada 15 minutos</option>
                        <option value="30">A cada 30 minutos</option>
                        <option value="60" selected>A cada 1 hora</option>
                        <option value="180">A cada 3 horas</option>
                        <option value="360">A cada 6 horas</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notificações</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="notify-email" checked>
                        <label class="form-check-label" for="notify-email">
                            Email
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="notify-push" checked>
                        <label class="form-check-label" for="notify-push">
                            Push (navegador)
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="notify-whatsapp">
                        <label class="form-check-label" for="notify-whatsapp">
                            WhatsApp
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Limites</label>
                    <div class="input-group mb-2">
                        <span class="input-group-text">Máximo de concorrentes:</span>
                        <input type="number" class="form-control" id="max-competitors" value="50">
                    </div>
                    <div class="input-group">
                        <span class="input-group-text">Alertas não lidos:</span>
                        <input type="number" class="form-control" id="max-unread-alerts" value="100">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" data-action="save-settings">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
async function requestJson(url, options = {}) {
    if (window.ApiClient) {
        return window.ApiClient.request(url, options);
    }
    const resp = await fetch(url, { credentials: 'include', ...options });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}

function normalizeExternalUrl(url) {
    if (!url || typeof url !== 'string') return '';
    const trimmed = url.trim();
    if (!trimmed) return '';
    if (trimmed.startsWith('data:') || trimmed.startsWith('blob:') || trimmed.startsWith('#')) return trimmed;
    if (trimmed.startsWith('//')) return `${window.location.protocol}${trimmed}`;
    if (trimmed.startsWith('http://')) return `https://${trimmed.slice('http://'.length)}`;
    return trimmed;
}

// Competitor Monitor State
const monitorState = {
    competitors: [],
    alerts: [],
    status: 'stopped',
    filter: 'all',
    intervalId: null
};

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadCompetitors();
    loadAlerts();
    loadStats();
    
    // Auto-refresh every 5 minutes
    monitorState.intervalId = setInterval(refreshAll, 5 * 60 * 1000);
});

// Load competitors
async function loadCompetitors() {
    try {
        const {data} = await requestJson('/api/competitor/tracked');
        
        monitorState.competitors = data.competitors || [];
        renderCompetitors();
        
    } catch (error) {
        console.error('Error loading competitors:', error);
    }
}

// Load alerts
async function loadAlerts() {
    try {
        const {data} = await requestJson('/api/competitor/alerts?limit=10');
        
        monitorState.alerts = data.alerts || [];
        renderAlerts();
        
    } catch (error) {
        console.error('Error loading alerts:', error);
    }
}

// Load stats
async function loadStats() {
    try {
        const {data} = await requestJson('/api/competitor/stats');
        
        document.getElementById('active-competitors').textContent = data.active_competitors || 0;
        document.getElementById('todays-alerts').textContent = data.todays_alerts || 0;
        document.getElementById('price-changes').textContent = data.price_changes || 0;
        document.getElementById('opportunities').textContent = data.opportunities || 0;
        
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// Render competitors
function renderCompetitors() {
    const container = document.getElementById('competitors-container');
    
    if (monitorState.competitors.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">Nenhum concorrente rastreado ainda.</p>';
        return;
    }
    
    const filtered = monitorState.filter === 'all' 
        ? monitorState.competitors 
        : monitorState.competitors.filter(c => c.status === monitorState.filter);
    
    container.innerHTML = filtered.map(comp => `
        <div class="competitor-item ${comp.status}" data-id="${comp.id}">
            <div class="row align-items-center">
                <div class="col-md-2">
                    <img src="${normalizeExternalUrl(comp.thumbnail)}" alt="Product" class="img-fluid rounded">
                </div>
                <div class="col-md-4">
                    <strong>${truncate(comp.title, 50)}</strong><br>
                    <small class="text-muted">${comp.competitor_item_id}</small><br>
                    <span class="monitoring-status ${comp.monitoring_active ? 'active' : 'paused'}">
                        <i class="bi bi-circle-fill me-1"></i>
                        ${comp.monitoring_active ? 'Ativo' : 'Pausado'}
                    </span>
                </div>
                <div class="col-md-3">
                    <div class="mb-2">
                        <strong>Preço Atual:</strong> R$ ${comp.competitor_price.toFixed(2)}
                    </div>
                    <div class="mb-2">
                        <strong>Meu Preço:</strong> R$ ${comp.my_price.toFixed(2)}
                    </div>
                    ${comp.price_diff ? `
                        <div>
                            <span class="badge bg-${comp.price_diff > 0 ? 'success' : 'danger'}">
                                ${comp.price_diff > 0 ? '+' : ''}${comp.price_diff.toFixed(2)}%
                            </span>
                        </div>
                    ` : ''}
                </div>
                <div class="col-md-3">
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline-primary" data-action="view-details" data-comp-id="${comp.id}">
                            <i class="bi bi-eye"></i> Detalhes
                        </button>
                        <button class="btn btn-sm btn-outline-warning" data-action="toggle-monitoring" data-comp-id="${comp.id}">
                            <i class="bi bi-${comp.monitoring_active ? 'pause' : 'play'}"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" data-action="remove-competitor" data-comp-id="${comp.id}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

// Render alerts
function renderAlerts() {
    const container = document.getElementById('alerts-container');
    const unreadCount = monitorState.alerts.filter(a => !a.read).length;
    
    document.getElementById('unread-alerts-badge').textContent = unreadCount;
    
    if (monitorState.alerts.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">Nenhum alerta recente.</p>';
        return;
    }
    
    container.innerHTML = monitorState.alerts.slice(0, 5).map(alert => `
        <div class="alert-card ${alert.severity}" data-action="mark-alert-read" data-alert-id="${alert.id}">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <strong>
                        <i class="bi bi-${getAlertIcon(alert.type)}"></i>
                        ${alert.title}
                    </strong>
                    <p class="mb-0 mt-2">${alert.message}</p>
                    <small class="text-muted">${formatTime(alert.created_at)}</small>
                </div>
                ${!alert.read ? '<span class="badge bg-danger">Novo</span>' : ''}
            </div>
        </div>
    `).join('');
}

// Filter competitors
function filterCompetitors(filter) {
    monitorState.filter = filter;
    
    // Update buttons
    document.querySelectorAll('.filter-chip').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    renderCompetitors();
}

// Add competitor
async function addCompetitor() {
    const myItemId = document.getElementById('my-item-id').value;
    const competitorItemId = document.getElementById('competitor-item-id').value;
    
    if (!myItemId || !competitorItemId) {
        Toastify({
            text: 'Preencha todos os campos',
            duration: 3000,
            backgroundColor: 'linear-gradient(to right, #ff5f6d, #ffc371)'
        }).showToast();
        return;
    }
    
    try {
        const response = await requestJson('/api/competitor/track', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                my_item_id: myItemId,
                competitor_item_id: competitorItemId,
                alerts: {
                    price_drop: document.getElementById('alert-price-drop').checked,
                    price_increase: document.getElementById('alert-price-increase').checked,
                    stock_change: document.getElementById('alert-stock').checked
                }
            })
        });
        
        const {success} = response;
        
        if (success) {
            Toastify({
                text: 'Concorrente adicionado com sucesso!',
                duration: 3000,
                backgroundColor: 'linear-gradient(to right, #00b09b, #96c93d)'
            }).showToast();
            
            bootstrap.Modal.getInstance(document.getElementById('addCompetitorModal')).hide();
            refreshAll();
        }
    } catch (error) {
        console.error('Error adding competitor:', error);
        Toastify({
            text: 'Erro ao adicionar concorrente',
            duration: 3000,
            backgroundColor: 'linear-gradient(to right, #ff5f6d, #ffc371)'
        }).showToast();
    }
}

// Start monitoring
async function startMonitoring() {
    try {
        const {success} = await requestJson('/api/competitor/monitoring/start', {method: 'POST'});
        
        if (success) {
            monitorState.status = 'active';
            Toastify({
                text: 'Monitoramento iniciado!',
                duration: 2000,
                backgroundColor: 'linear-gradient(to right, #00b09b, #96c93d)'
            }).showToast();
        }
    } catch (error) {
        console.error('Error starting monitoring:', error);
    }
}

// Pause monitoring
async function pauseMonitoring() {
    try {
        const {success} = await requestJson('/api/competitor/monitoring/pause', {method: 'POST'});
        
        if (success) {
            monitorState.status = 'paused';
            Toastify({
                text: 'Monitoramento pausado',
                duration: 2000,
                backgroundColor: 'linear-gradient(to right, #f2994a, #f2c94c)'
            }).showToast();
        }
    } catch (error) {
        console.error('Error pausing monitoring:', error);
    }
}

// Toggle monitoring for specific competitor
async function toggleMonitoring(competitorId) {
    try {
        const {success} = await requestJson(`/api/competitor/toggle/${competitorId}`, {method: 'POST'});
        
        if (success) {
            await loadCompetitors();
        }
    } catch (error) {
        console.error('Error toggling monitoring:', error);
    }
}

// Remove competitor
async function removeCompetitor(competitorId) {
    if (!confirm('Tem certeza que deseja remover este concorrente do monitoramento?')) {
        return;
    }
    
    try {
        const {success} = await requestJson(`/api/competitor/${competitorId}`, {method: 'DELETE'});
        
        if (success) {
            Toastify({
                text: 'Concorrente removido',
                duration: 2000,
                backgroundColor: 'linear-gradient(to right, #00b09b, #96c93d)'
            }).showToast();
            
            await loadCompetitors();
        }
    } catch (error) {
        console.error('Error removing competitor:', error);
    }
}

// Mark alert as read
async function markAlertRead(alertId) {
    try {
        await requestJson(`/api/competitor/alert/${alertId}/read`, {method: 'POST'});
        await loadAlerts();
    } catch (error) {
        console.error('Error marking alert as read:', error);
    }
}

// Refresh all
async function refreshAll() {
    await Promise.all([
        loadCompetitors(),
        loadAlerts(),
        loadStats()
    ]);
}

// Open modals
function openAddCompetitorModal() {
    new bootstrap.Modal(document.getElementById('addCompetitorModal')).show();
}

function openSettings() {
    new bootstrap.Modal(document.getElementById('settingsModal')).show();
}

// Save settings
async function saveSettings() {
    const settings = {
        check_frequency: document.getElementById('check-frequency').value,
        notifications: {
            email: document.getElementById('notify-email').checked,
            push: document.getElementById('notify-push').checked,
            whatsapp: document.getElementById('notify-whatsapp').checked
        },
        limits: {
            max_competitors: document.getElementById('max-competitors').value,
            max_unread_alerts: document.getElementById('max-unread-alerts').value
        }
    };
    
    try {
        const response = await requestJson('/api/competitor/settings', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(settings)
        });
        
        const {success} = response;
        
        if (success) {
            Toastify({
                text: 'Configurações salvas!',
                duration: 2000,
                backgroundColor: 'linear-gradient(to right, #00b09b, #96c93d)'
            }).showToast();
            
            bootstrap.Modal.getInstance(document.getElementById('settingsModal')).hide();
        }
    } catch (error) {
        console.error('Error saving settings:', error);
    }
}

// Helper functions
function getAlertIcon(type) {
    const icons = {
        'price_drop': 'arrow-down-circle',
        'price_increase': 'arrow-up-circle',
        'out_of_stock': 'exclamation-triangle',
        'back_in_stock': 'check-circle',
        'new_listing': 'star'
    };
    return icons[type] || 'info-circle';
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMinutes = Math.floor((now - date) / 60000);
    
    if (diffMinutes < 1) return 'Agora mesmo';
    if (diffMinutes < 60) return `${diffMinutes}min atrás`;
    if (diffMinutes < 1440) return `${Math.floor(diffMinutes / 60)}h atrás`;
    return `${Math.floor(diffMinutes / 1440)}d atrás`;
}

function truncate(text, length) {
    return text.length > length ? text.substring(0, length) + '...' : text;
}

function viewDetails(competitorId) {
    const competitor = monitorState.competitors.find(c => c.id == competitorId);
    if (!competitor) return;

    // Create modal if not exists
    let modal = document.getElementById('competitorDetailsModal');
    if (!modal) {
        const modalHtml = `
        <div class="modal fade" id="competitorDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detalhes do Concorrente</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="competitorDetailsBody"></div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById('competitorDetailsModal');
    }

    const body = document.getElementById('competitorDetailsBody');
    body.innerHTML = `
        <div class="row">
            <div class="col-md-4">
                <img src="${normalizeExternalUrl(competitor.thumbnail)}" class="img-fluid rounded mb-3">
                <div class="card bg-light">
                    <div class="card-body p-2">
                        <small class="text-muted">ID: ${competitor.competitor_item_id}</small>
                        <br>
                        <strong>R$ ${competitor.competitor_price.toFixed(2)}</strong>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <h6>${competitor.title}</h6>
                <hr>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="small text-muted">Meu Preço</label>
                        <div>R$ ${competitor.my_price.toFixed(2)}</div>
                    </div>
                    <div class="col-6">
                        <label class="small text-muted">Diferença</label>
                        <div class="${competitor.price_diff > 0 ? 'text-success' : 'text-danger'}">
                            ${competitor.price_diff > 0 ? '+' : ''}${competitor.price_diff.toFixed(2)}%
                        </div>
                    </div>
                </div>
                <hr>
                <h6>Histórico Recente</h6>
                <p class="text-muted small">Histórico detalhado disponível na versão completa.</p>
            </div>
        </div>
    `;

    new bootstrap.Modal(modal).show();
}
// ========================================
// EVENT DELEGATION FOR CSP COMPLIANCE
// ========================================
document.addEventListener('click', function(e) {
    const target = e.target.closest('[data-action]');
    if (!target) return;
    e.preventDefault();
    const action = target.dataset.action;
    switch(action) {
        case 'open-add-competitor-modal': openAddCompetitorModal(); break;
        case 'start-monitoring': startMonitoring(); break;
        case 'pause-monitoring': pauseMonitoring(); break;
        case 'refresh-all': refreshAll(); break;
        case 'open-settings': openSettings(); break;
        case 'filter-competitors': filterCompetitors(target.dataset.filter); break;
        case 'add-competitor': addCompetitor(); break;
        case 'save-settings': saveSettings(); break;
        case 'view-details': viewDetails(target.dataset.compId); break;
        case 'toggle-monitoring': toggleMonitoring(target.dataset.compId); break;
        case 'remove-competitor': removeCompetitor(target.dataset.compId); break;
        case 'mark-alert-read': markAlertRead(target.dataset.alertId); break;
    }
});
</script>

<!-- Load Chatbot Widget -->
<?php include __DIR__ . '/seo-killer/components/ai-chatbot-widget.php'; ?>
