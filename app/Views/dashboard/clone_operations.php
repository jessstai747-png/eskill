<?php
/**
 * Clone Operations Dashboard
 * 
 * Dashboard avançado com:
 * - Health status
 * - Batch operations
 * - Export controls
 * - SEO analytics
 */

$pageTitle = 'Clone Operations | Dashboard';
ob_start();
?>

<style>
.ops-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.ops-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border-color);
}

.ops-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border-color);
}

.ops-card-title {
    font-size: 1.1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.health-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.health-indicator.healthy { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
.health-indicator.warning { background: rgba(234, 179, 8, 0.15); color: #eab308; }
.health-indicator.critical { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

.health-check-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border-color-light);
}

.health-check-item:last-child { border-bottom: none; }

.health-check-name {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.health-check-value {
    font-size: 0.9rem;
    font-weight: 600;
}

.batch-action-btn {
    width: 100%;
    padding: 0.75rem 1rem;
    margin-bottom: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--card-bg);
    color: var(--text-primary);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.2s;
}

.batch-action-btn:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.batch-action-btn i { font-size: 1.2rem; }

.export-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.export-btn {
    padding: 1rem;
    border: 2px dashed var(--border-color);
    border-radius: 10px;
    background: transparent;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
}

.export-btn:hover {
    border-color: var(--primary-color);
    background: rgba(var(--primary-rgb), 0.05);
}

.export-btn i {
    font-size: 1.5rem;
    display: block;
    margin-bottom: 0.5rem;
    color: var(--primary-color);
}

.seo-score-ring {
    width: 120px;
    height: 120px;
    margin: 0 auto 1rem;
    position: relative;
}

.seo-score-value {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 2rem;
    font-weight: 700;
}

.seo-breakdown {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

.seo-breakdown-item {
    padding: 0.5rem;
    background: var(--bg-secondary);
    border-radius: 6px;
    text-align: center;
}

.seo-breakdown-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.seo-breakdown-value {
    font-size: 1.1rem;
    font-weight: 600;
}

.history-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border-color-light);
}

.history-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(var(--primary-rgb), 0.1);
    color: var(--primary-color);
}

.history-info { flex: 1; }
.history-type { font-weight: 500; font-size: 0.9rem; }
.history-meta { font-size: 0.8rem; color: var(--text-secondary); }

.history-result {
    text-align: right;
    font-size: 0.85rem;
}

.ops-full-width {
    grid-column: 1 / -1;
}

.quick-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.quick-stat {
    background: var(--card-bg);
    border-radius: 10px;
    padding: 1rem;
    text-align: center;
    border: 1px solid var(--border-color);
}

.quick-stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--primary-color);
}

.quick-stat-label {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .quick-stats { grid-template-columns: repeat(2, 1fr); }
    .export-options { grid-template-columns: 1fr; }
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Clone Operations</h1>
            <p class="text-muted mb-0">Monitoramento, operações em lote e exportação</p>
        </div>
        <button class="btn btn-outline-primary" onclick="refreshDashboard()">
            <i class="bi bi-arrow-clockwise"></i> Atualizar
        </button>
    </div>
    
    <!-- Quick Stats -->
    <div class="quick-stats" id="quickStats">
        <div class="quick-stat">
            <div class="quick-stat-value" id="statTotal">-</div>
            <div class="quick-stat-label">Total Clones</div>
        </div>
        <div class="quick-stat">
            <div class="quick-stat-value" id="statActive">-</div>
            <div class="quick-stat-label">Ativos</div>
        </div>
        <div class="quick-stat">
            <div class="quick-stat-value" id="statSales">-</div>
            <div class="quick-stat-label">Vendas (30d)</div>
        </div>
        <div class="quick-stat">
            <div class="quick-stat-value" id="statRevenue">-</div>
            <div class="quick-stat-label">Receita (30d)</div>
        </div>
    </div>
    
    <div class="ops-dashboard">
        <!-- Health Status Card -->
        <div class="ops-card">
            <div class="ops-card-header">
                <div class="ops-card-title">
                    <i class="bi bi-heart-pulse"></i> Health Status
                </div>
                <span class="health-indicator" id="overallHealth">
                    <i class="bi bi-circle-fill"></i>
                    <span>Verificando...</span>
                </span>
            </div>
            <div id="healthChecks">
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                </div>
            </div>
        </div>
        
        <!-- Batch Operations Card -->
        <div class="ops-card">
            <div class="ops-card-header">
                <div class="ops-card-title">
                    <i class="bi bi-collection"></i> Operações em Lote
                </div>
            </div>
            <div class="batch-actions">
                <button class="batch-action-btn" onclick="openBatchModal('repricing')">
                    <i class="bi bi-currency-dollar"></i>
                    <span>Repricing em Lote</span>
                </button>
                <button class="batch-action-btn" onclick="openBatchModal('stock')">
                    <i class="bi bi-box-seam"></i>
                    <span>Atualizar Estoque</span>
                </button>
                <button class="batch-action-btn" onclick="openBatchModal('seo')">
                    <i class="bi bi-search-heart"></i>
                    <span>Otimização SEO</span>
                </button>
                <button class="batch-action-btn" onclick="openBatchModal('status')">
                    <i class="bi bi-toggle-on"></i>
                    <span>Alterar Status</span>
                </button>
                <button class="batch-action-btn" onclick="openBatchModal('stale')">
                    <i class="bi bi-trash3"></i>
                    <span>Encerrar Inativos</span>
                </button>
            </div>
        </div>
        
        <!-- Export Card -->
        <div class="ops-card">
            <div class="ops-card-header">
                <div class="ops-card-title">
                    <i class="bi bi-download"></i> Exportar Dados
                </div>
            </div>
            <div class="export-options">
                <button class="export-btn" onclick="exportData('csv')">
                    <i class="bi bi-filetype-csv"></i>
                    <span>CSV</span>
                </button>
                <button class="export-btn" onclick="exportData('json')">
                    <i class="bi bi-filetype-json"></i>
                    <span>JSON</span>
                </button>
                <button class="export-btn" onclick="exportData('metrics')">
                    <i class="bi bi-graph-up"></i>
                    <span>Métricas</span>
                </button>
                <button class="export-btn" onclick="exportData('report')">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Relatório</span>
                </button>
            </div>
            <div class="mt-3">
                <small class="text-muted">
                    <a href="#" data-action="show-exporthistory">Ver histórico de exports</a>
                </small>
            </div>
        </div>
        
        <!-- SEO Score Card -->
        <div class="ops-card">
            <div class="ops-card-header">
                <div class="ops-card-title">
                    <i class="bi bi-search-heart"></i> Score SEO Médio
                </div>
            </div>
            <div class="seo-score-ring">
                <canvas id="seoScoreChart"></canvas>
                <div class="seo-score-value" id="seoScoreValue">-</div>
            </div>
            <div class="seo-breakdown" id="seoBreakdown">
                <div class="seo-breakdown-item">
                    <div class="seo-breakdown-label">Título</div>
                    <div class="seo-breakdown-value" id="seoTitle">-</div>
                </div>
                <div class="seo-breakdown-item">
                    <div class="seo-breakdown-label">Descrição</div>
                    <div class="seo-breakdown-value" id="seoDesc">-</div>
                </div>
                <div class="seo-breakdown-item">
                    <div class="seo-breakdown-label">Atributos</div>
                    <div class="seo-breakdown-value" id="seoAttr">-</div>
                </div>
                <div class="seo-breakdown-item">
                    <div class="seo-breakdown-label">Imagens</div>
                    <div class="seo-breakdown-value" id="seoImg">-</div>
                </div>
            </div>
        </div>
        
        <!-- History Card -->
        <div class="ops-card ops-full-width">
            <div class="ops-card-header">
                <div class="ops-card-title">
                    <i class="bi bi-clock-history"></i> Histórico de Operações
                </div>
                <a href="#" class="btn btn-sm btn-outline-secondary">Ver Todos</a>
            </div>
            <div id="operationsHistory">
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Batch Operation Modal -->
<div class="modal fade" id="batchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="batchModalTitle">Operação em Lote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="batchModalBody">
                <!-- Dynamic content -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="batchExecuteBtn" onclick="executeBatch()">
                    <i class="bi bi-play-fill"></i> Executar
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

let seoChart = null;
let currentBatchType = null;

document.addEventListener('DOMContentLoaded', function() {
    loadHealthStatus();
    loadQuickStats();
    loadOperationsHistory();
    initSeoChart();
});

async function loadHealthStatus() {
    try {
        const data = await requestJson('/api/clone/health');
        
        const healthEl = document.getElementById('overallHealth');
        healthEl.className = `health-indicator ${data.status}`;
        healthEl.innerHTML = `
            <i class="bi bi-circle-fill"></i>
            <span>${data.status === 'healthy' ? 'Saudável' : data.status === 'warning' ? 'Atenção' : 'Crítico'}</span>
        `;
        
        let checksHtml = '';
        for (const [key, check] of Object.entries(data.checks || {})) {
            checksHtml += `
                <div class="health-check-item">
                    <span class="health-check-name">${formatCheckName(key)}</span>
                    <span class="health-check-value health-indicator ${check.status}">${check.value}</span>
                </div>
            `;
        }
        document.getElementById('healthChecks').innerHTML = checksHtml || '<p class="text-muted">Sem dados</p>';
        
    } catch (error) {
        console.error('Error loading health:', error);
    }
}

async function loadQuickStats() {
    try {
        const data = await requestJson('/api/clone/analytics/summary');
        
        const stats = data.stats || {};
        document.getElementById('statTotal').textContent = formatNumber(stats.total || 0);
        document.getElementById('statActive').textContent = formatNumber(stats.active || 0);
        document.getElementById('statSales').textContent = formatNumber(stats.total_sales || 0);
        document.getElementById('statRevenue').textContent = formatCurrency(stats.total_revenue || 0);
        
        // Update SEO if available
        if (stats.avg_seo_score) {
            updateSeoChart(stats.avg_seo_score);
        }
        
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadOperationsHistory() {
    try {
        const data = await requestJson('/api/clone/batch/history?limit=5');
        
        const operations = data.operations || [];
        
        if (operations.length === 0) {
            document.getElementById('operationsHistory').innerHTML = 
                '<p class="text-muted text-center py-3">Nenhuma operação recente</p>';
            return;
        }
        
        let html = '';
        for (const op of operations) {
            const icon = getOperationIcon(op.operation_type);
            html += `
                <div class="history-item">
                    <div class="history-icon">
                        <i class="bi bi-${icon}"></i>
                    </div>
                    <div class="history-info">
                        <div class="history-type">${formatOperationType(op.operation_type)}</div>
                        <div class="history-meta">${formatDate(op.created_at)}</div>
                    </div>
                    <div class="history-result">
                        <div class="text-success">${op.success_count} sucesso</div>
                        ${op.error_count > 0 ? `<div class="text-danger">${op.error_count} erros</div>` : ''}
                    </div>
                </div>
            `;
        }
        
        document.getElementById('operationsHistory').innerHTML = html;
        
    } catch (error) {
        console.error('Error loading history:', error);
    }
}

function initSeoChart() {
    const ctx = document.getElementById('seoScoreChart').getContext('2d');
    seoChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [0, 100],
                backgroundColor: ['#3b82f6', '#e5e7eb'],
                borderWidth: 0
            }]
        },
        options: {
            cutout: '80%',
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: false } }
        }
    });
}

function updateSeoChart(score) {
    if (seoChart) {
        seoChart.data.datasets[0].data = [score, 100 - score];
        seoChart.update();
    }
    document.getElementById('seoScoreValue').textContent = score;
}

function openBatchModal(type) {
    currentBatchType = type;
    const modal = new bootstrap.Modal(document.getElementById('batchModal'));
    
    const titles = {
        repricing: 'Repricing em Lote',
        stock: 'Atualização de Estoque',
        seo: 'Otimização SEO em Lote',
        status: 'Alteração de Status',
        stale: 'Encerrar Itens Inativos'
    };
    
    document.getElementById('batchModalTitle').textContent = titles[type] || 'Operação';
    document.getElementById('batchModalBody').innerHTML = getBatchForm(type);
    
    modal.show();
}

function getBatchForm(type) {
    switch(type) {
        case 'repricing':
            return `
                <div class="mb-3">
                    <label class="form-label">Tipo de Ajuste</label>
                    <select class="form-select" id="repricingType">
                        <option value="percentage">Percentual</option>
                        <option value="fixed_increase">Aumento Fixo</option>
                        <option value="fixed_decrease">Desconto Fixo</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Valor</label>
                    <input type="number" class="form-control" id="repricingValue" step="0.01" placeholder="Ex: 5 para 5%">
                </div>
                <div class="mb-3">
                    <label class="form-label">Categoria (opcional)</label>
                    <input type="text" class="form-control" id="repricingCategory" placeholder="MLB1234">
                </div>
            `;
            
        case 'stock':
            return `
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Informe os item IDs e quantidades (um por linha): MLB123456789:10
                </div>
                <div class="mb-3">
                    <textarea class="form-control" id="stockUpdates" rows="6" placeholder="MLB123456789:10&#10;MLB987654321:5"></textarea>
                </div>
            `;
            
        case 'seo':
            return `
                <div class="mb-3">
                    <label class="form-label">Nível de Otimização</label>
                    <select class="form-select" id="seoLevel">
                        <option value="basic">Básico</option>
                        <option value="standard" selected>Padrão</option>
                        <option value="advanced">Avançado</option>
                        <option value="aggressive">Agressivo</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Item IDs (um por linha)</label>
                    <textarea class="form-control" id="seoItems" rows="4" placeholder="MLB123456789&#10;MLB987654321"></textarea>
                </div>
            `;
            
        case 'status':
            return `
                <div class="mb-3">
                    <label class="form-label">Novo Status</label>
                    <select class="form-select" id="newStatus">
                        <option value="active">Ativar</option>
                        <option value="paused">Pausar</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Item IDs (um por linha)</label>
                    <textarea class="form-control" id="statusItems" rows="4" placeholder="MLB123456789&#10;MLB987654321"></textarea>
                </div>
            `;
            
        case 'stale':
            return `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    Esta ação irá pausar itens que não venderam no período especificado.
                </div>
                <div class="mb-3">
                    <label class="form-label">Dias sem Vendas</label>
                    <input type="number" class="form-control" id="staleDays" value="60" min="30">
                </div>
            `;
            
        default:
            return '<p>Formulário não disponível</p>';
    }
}

async function executeBatch() {
    const btn = document.getElementById('batchExecuteBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Executando...';
    
    try {
        let endpoint, body;
        
        switch(currentBatchType) {
            case 'repricing':
                endpoint = '/api/clone/batch/repricing';
                body = {
                    type: document.getElementById('repricingType').value,
                    value: parseFloat(document.getElementById('repricingValue').value),
                    category_id: document.getElementById('repricingCategory').value || null
                };
                break;
                
            case 'stock':
                endpoint = '/api/clone/batch/stock';
                const stockLines = document.getElementById('stockUpdates').value.split('\n');
                body = { updates: stockLines.map(line => {
                    const [item_id, quantity] = line.split(':');
                    return { item_id: item_id.trim(), quantity: parseInt(quantity) };
                }).filter(u => u.item_id && !isNaN(u.quantity)) };
                break;
                
            case 'seo':
                endpoint = '/api/clone/batch/seo-optimize';
                body = {
                    item_ids: document.getElementById('seoItems').value.split('\n').map(s => s.trim()).filter(Boolean),
                    level: document.getElementById('seoLevel').value
                };
                break;
                
            case 'status':
                endpoint = '/api/clone/batch/status';
                body = {
                    item_ids: document.getElementById('statusItems').value.split('\n').map(s => s.trim()).filter(Boolean),
                    status: document.getElementById('newStatus').value
                };
                break;
                
            case 'stale':
                endpoint = '/api/clone/batch/close-stale';
                body = { days: parseInt(document.getElementById('staleDays').value) };
                break;
        }
        
        const result = await requestJson(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        
        if (result.error) {
            throw new Error(result.error);
        }
        
        alert(`Operação concluída!\n${result.updated || result.optimized || 0} itens processados\n${result.errors || 0} erros`);
        bootstrap.Modal.getInstance(document.getElementById('batchModal')).hide();
        loadOperationsHistory();
        
    } catch (error) {
        alert('Erro: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-fill"></i> Executar';
    }
}

async function exportData(type) {
    try {
        let endpoint;
        switch(type) {
            case 'csv': endpoint = '/api/clone/export/items/csv'; break;
            case 'json': endpoint = '/api/clone/export/items/json'; break;
            case 'metrics': endpoint = '/api/clone/export/metrics'; break;
            case 'report': endpoint = '/api/clone/export/report'; break;
        }
        
        const result = await requestJson(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ filters: {} })
        });
        
        if (result.success && result.file) {
            window.location.href = `/api/clone/export/download/${result.file}`;
        } else {
            alert('Export criado: ' + (result.file || 'Verifique a lista de exports'));
        }
        
    } catch (error) {
        alert('Erro ao exportar: ' + error.message);
    }
}

async function showExportHistory() {
    try {
        const data = await requestJson('/api/clone/export/list');
        console.log('Exports:', data.exports);
        alert('Ver console para lista de exports');
    } catch (error) {
        console.error(error);
    }
}

function refreshDashboard() {
    loadHealthStatus();
    loadQuickStats();
    loadOperationsHistory();
}

// Helpers
function formatNumber(n) {
    return new Intl.NumberFormat('pt-BR').format(n);
}

function formatCurrency(n) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleString('pt-BR');
}

function formatCheckName(name) {
    const names = {
        active_jobs: 'Jobs Ativos',
        stuck_jobs: 'Jobs Travados',
        error_rate: 'Taxa de Erro',
        queue_size: 'Fila',
        workers_active: 'Workers',
        api_connectivity: 'Conectividade API'
    };
    return names[name] || name;
}

function formatOperationType(type) {
    const types = {
        repricing: 'Repricing',
        stock_update: 'Atualização de Estoque',
        status_change: 'Alteração de Status',
        title_update: 'Atualização de Títulos',
        price_update: 'Atualização de Preços',
        sync_metrics: 'Sincronização de Métricas',
        seo_optimization: 'Otimização SEO'
    };
    return types[type] || type;
}

function getOperationIcon(type) {
    const icons = {
        repricing: 'currency-dollar',
        stock_update: 'box-seam',
        status_change: 'toggle-on',
        title_update: 'fonts',
        price_update: 'tag',
        sync_metrics: 'arrow-repeat',
        seo_optimization: 'search-heart'
    };
    return icons[type] || 'gear';
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard.php';
?>
