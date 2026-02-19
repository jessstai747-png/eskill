<?php
$pageTitle = '🎯 SEO Intelligence - Detalhes';
$activePage = 'seo-intelligence';

// Get item ID from URL
$itemId = $_GET['item_id'] ?? '';

if (empty($itemId)) {
    header('Location: /dashboard/seo-intelligence');
    exit;
}

// Account Selector
$requireAccountSelection = true;
$showAccountBanner = true;
include __DIR__ . '/../../components/account-selector.php';

// Page Header
$title = '🎯 Análise SEO Detalhada';
$subtitle = 'Auditoria Completa e Recomendações';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- Toastify -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<style>
.score-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: bold;
    color: white;
    margin: 0 auto;
}

.score-excellent { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.score-good { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.score-fair { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #333; }
.score-poor { background: linear-gradient(135deg, #ff6b6b 0%, #c92a2a 100%); }

.component-score {
    margin-bottom: 15px;
}

.component-score .progress {
    height: 25px;
}

.recommendation-card {
    border-left: 4px solid;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 8px;
    background: white;
}

.priority-high { border-left-color: #dc3545; }
.priority-medium { border-left-color: #ffc107; }
.priority-low { border-left-color: #28a745; }

.hidden-attr-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}

.impact-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}
</style>

<div class="container-fluid">
    
    <!-- Back Button -->
    <div class="mb-3">
        <a href="/dashboard/seo-killer" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
        <button class="btn btn-primary" onclick="refreshAudit()">
            <i class="bi bi-arrow-clockwise"></i> Atualizar Auditoria
        </button>
    </div>

    <!-- Loading State -->
    <div id="loading-state" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-3">Carregando análise...</p>
    </div>

    <!-- Main Content -->
    <div id="main-content" style="display: none;">
        
        <!-- Overall Score Card -->
        <div class="card mb-4">
            <div class="card-body text-center">
                <h5 class="card-title mb-4">Score Geral SEO</h5>
                <div id="overall-score-circle" class="score-circle mb-3">-</div>
                <p id="item-title" class="text-muted">-</p>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="detailTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#diagnostic">
                    <i class="bi bi-clipboard-pulse"></i> Diagnóstico
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#hidden-attrs">
                    <i class="bi bi-eye"></i> Atributos Ocultos
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#competitors">
                    <i class="bi bi-binoculars"></i> Concorrentes
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#history">
                    <i class="bi bi-clock-history"></i> Histórico
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            
            <!-- Diagnostic Tab -->
            <div class="tab-pane fade show active" id="diagnostic">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Scores por Componente</h5>
                            </div>
                            <div class="card-body" id="component-scores">
                                <p class="text-muted">Carregando...</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Recomendações Prioritárias</h5>
                            </div>
                            <div class="card-body" id="recommendations" style="max-height: 500px; overflow-y: auto;">
                                <p class="text-muted">Carregando...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden Attributes Tab -->
            <div class="tab-pane fade" id="hidden-attrs">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Atributos Ocultos Detectados</h5>
                        <button class="btn btn-sm btn-primary" onclick="detectHiddenAttributes()">
                            <i class="bi bi-search"></i> Detectar Novamente
                        </button>
                    </div>
                    <div class="card-body" id="hidden-attributes-list">
                        <p class="text-muted">Carregando...</p>
                    </div>
                </div>
            </div>

            <!-- Competitors Tab -->
            <div class="tab-pane fade" id="competitors">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Análise de Concorrentes</h5>
                        <button class="btn btn-sm btn-primary" onclick="refreshCompetitors()">
                            <i class="bi bi-arrow-clockwise"></i> Atualizar
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="competitors-list">
                            <p class="text-muted">Carregando...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- History Tab -->
            <div class="tab-pane fade" id="history">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Histórico de Alterações</h5>
                    </div>
                    <div class="card-body">
                        <div id="history-list">
                            <p class="text-muted">Carregando...</p>
                        </div>
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

function normalizeExternalUrl(url) {
    if (!url || typeof url !== 'string') return '';
    const trimmed = url.trim();
    if (!trimmed) return '';
    if (trimmed.startsWith('data:') || trimmed.startsWith('blob:') || trimmed.startsWith('#')) return trimmed;
    if (trimmed.startsWith('//')) return `${window.location.protocol}${trimmed}`;
    if (trimmed.startsWith('http://')) return `https://${trimmed.slice('http://'.length)}`;
    return trimmed;
}

const itemId = '<?= htmlspecialchars($itemId) ?>';
let auditData = null;

document.addEventListener('DOMContentLoaded', function() {
    loadListingDetail();
});

async function loadListingDetail() {
    try {
        const data = await requestJson(`/api/seo/intelligence/listings/${itemId}`);
        
        if (data.success) {
            auditData = data.data;
            renderAudit(auditData.audit);
            renderHiddenAttributes(auditData.hidden_attributes);
            renderCompetitors(auditData.competitors);
            renderHistory(auditData.history);
            
            document.getElementById('loading-state').style.display = 'none';
            document.getElementById('main-content').style.display = 'block';
        } else {
            showToast('Erro ao carregar dados: ' + (data.error || 'Erro desconhecido'), 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showToast('Erro ao carregar análise', 'error');
    }
}

function renderAudit(audit) {
    // Overall score
    const score = audit.overall_score || 0;
    const scoreClass = score >= 80 ? 'score-excellent' : score >= 60 ? 'score-good' : score >= 40 ? 'score-fair' : 'score-poor';
    
    const scoreCircle = document.getElementById('overall-score-circle');
    scoreCircle.textContent = score;
    scoreCircle.className = 'score-circle mb-3 ' + scoreClass;
    
    // Component scores
    const scores = audit.scores || {};
    const components = [
        { key: 'title', label: 'Título', icon: '📝' },
        { key: 'description', label: 'Descrição', icon: '📄' },
        { key: 'attributes', label: 'Atributos', icon: '🏷️' },
        { key: 'images', label: 'Imagens', icon: '📸' },
        { key: 'pricing', label: 'Preço', icon: '💰' },
        { key: 'category', label: 'Categoria', icon: '📁' }
    ];
    
    let html = '';
    components.forEach(comp => {
        const value = scores[comp.key] || 0;
        const barClass = value >= 80 ? 'bg-success' : value >= 60 ? 'bg-info' : value >= 40 ? 'bg-warning' : 'bg-danger';
        
        html += `
            <div class="component-score">
                <div class="d-flex justify-content-between mb-1">
                    <span>${comp.icon} ${comp.label}</span>
                    <strong>${value}/100</strong>
                </div>
                <div class="progress">
                    <div class="progress-bar ${barClass}" style="width: ${value}%"></div>
                </div>
            </div>
        `;
    });
    
    document.getElementById('component-scores').innerHTML = html;
    
    // Recommendations
    const recommendations = audit.recommendations || [];
    if (recommendations.length === 0) {
        document.getElementById('recommendations').innerHTML = '<p class="text-success">✅ Nenhuma recomendação. Seu anúncio está ótimo!</p>';
    } else {
        html = '';
        recommendations.forEach(rec => {
            const priorityClass = `priority-${rec.priority}`;
            const priorityIcon = rec.priority === 'high' ? '🔴' : rec.priority === 'medium' ? '🟡' : '🟢';
            
            html += `
                <div class="recommendation-card ${priorityClass}">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <strong>${priorityIcon} ${rec.type.toUpperCase()}</strong>
                        <span class="badge bg-${rec.priority === 'high' ? 'danger' : rec.priority === 'medium' ? 'warning' : 'success'}">
                            ${rec.priority}
                        </span>
                    </div>
                    <p class="mb-1">${rec.message}</p>
                    <small class="text-muted"><strong>Impacto:</strong> ${rec.impact}</small>
                </div>
            `;
        });
        document.getElementById('recommendations').innerHTML = html;
    }
}

function renderHiddenAttributes(hiddenAttrs) {
    if (!hiddenAttrs || hiddenAttrs.length === 0) {
        document.getElementById('hidden-attributes-list').innerHTML = `
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Nenhum atributo oculto detectado ainda.
                <button class="btn btn-sm btn-primary ms-2" onclick="detectHiddenAttributes()">Detectar Agora</button>
            </div>
        `;
        return;
    }
    
    let html = '';
    hiddenAttrs.forEach(attr => {
        const impactClass = attr.impact === 'high' ? 'danger' : attr.impact === 'medium' ? 'warning' : 'success';
        const suggestedValues = attr.suggested_values || [];
        
        html += `
            <div class="hidden-attr-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="mb-1">${attr.attribute_name}</h6>
                        <small class="text-muted">ID: ${attr.attribute_id}</small>
                    </div>
                    <span class="badge bg-${impactClass} impact-badge">
                        ${attr.impact.toUpperCase()} IMPACT
                    </span>
                </div>
                <p class="mb-2">
                    <strong>Frequência:</strong> ${attr.frequency}% dos concorrentes usam este atributo
                </p>
                ${suggestedValues.length > 0 ? `
                    <div class="mb-2">
                        <strong>Valores Sugeridos:</strong>
                        <select class="form-select form-select-sm mt-1" id="value-${attr.attribute_id}">
                            <option value="">Selecione...</option>
                            ${suggestedValues.map(v => `<option value="${v.value}">${v.value} (${v.count}x)</option>`).join('')}
                        </select>
                    </div>
                    <button class="btn btn-sm btn-success" onclick="applyHiddenAttribute('${attr.attribute_id}')">
                        <i class="bi bi-check-circle"></i> Aplicar
                    </button>
                ` : ''}
            </div>
        `;
    });
    
    document.getElementById('hidden-attributes-list').innerHTML = html;
}

function renderCompetitors(competitors) {
    if (!competitors || competitors.length === 0) {
        document.getElementById('competitors-list').innerHTML = '<p class="text-muted">Nenhum concorrente encontrado.</p>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-hover">';
    html += '<thead><tr><th>Título</th><th>Preço</th><th>Vendidos</th><th>Imagens</th><th>Atributos</th><th>Relevância</th></tr></thead><tbody>';
    
    competitors.forEach(comp => {
        html += `
            <tr>
                <td><a href="${normalizeExternalUrl(comp.permalink) || '#'}" target="_blank">${comp.title}</a></td>
                <td>R$ ${parseFloat(comp.price || 0).toFixed(2)}</td>
                <td>${comp.sold_quantity || 0}</td>
                <td>${comp.image_count || 0}</td>
                <td>${comp.attribute_count || 0}</td>
                <td><span class="badge bg-primary">${Math.round(comp.relevance_score || 0)}</span></td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    document.getElementById('competitors-list').innerHTML = html;
}

function renderHistory(history) {
    if (!history || history.length === 0) {
        document.getElementById('history-list').innerHTML = '<p class="text-muted">Nenhuma alteração registrada.</p>';
        return;
    }
    
    let html = '<div class="timeline">';
    history.forEach(item => {
        const date = new Date(item.applied_at).toLocaleString('pt-BR');
        html += `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${item.change_type}</h6>
                            <small class="text-muted">${date} - ${item.changed_by}</small>
                        </div>
                        ${item.can_rollback ? `
                            <button class="btn btn-sm btn-warning" onclick="rollback(${item.id})">
                                <i class="bi bi-arrow-counterclockwise"></i> Reverter
                            </button>
                        ` : ''}
                    </div>
                    ${item.diff ? `<pre class="mt-2 mb-0"><code>${item.diff}</code></pre>` : ''}
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    document.getElementById('history-list').innerHTML = html;
}

async function refreshAudit() {
    showToast('Atualizando auditoria...', 'info');
    try {
        const data = await requestJson(`/api/seo/intelligence/audit/${itemId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ force_refresh: true })
        });

        if (data.success) {
            showToast('Auditoria atualizada!', 'success');
            loadListingDetail();
        }
    } catch (error) {
        showToast('Erro ao atualizar', 'error');
    }
}

async function detectHiddenAttributes() {
    showToast('Detectando atributos ocultos...', 'info');
    try {
        const data = await requestJson(`/api/seo/intelligence/hidden-attributes/${itemId}/detect`, {
            method: 'POST'
        });

        if (data.success) {
            showToast(`${data.data.total_hidden} atributos detectados!`, 'success');
            loadListingDetail();
        }
    } catch (error) {
        showToast('Erro na detecção', 'error');
    }
}

async function applyHiddenAttribute(attrId) {
    const select = document.getElementById(`value-${attrId}`);
    const value = select.value;
    
    if (!value) {
        showToast('Selecione um valor', 'warning');
        return;
    }
    
    if (!confirm(`Aplicar atributo ${attrId} com valor "${value}"?`)) {
        return;
    }
    
    try {
        const data = await requestJson(`/api/seo/intelligence/hidden-attributes/${itemId}/apply`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ attribute_id: attrId, value: value })
        });

        if (data.success) {
            showToast('Atributo aplicado com sucesso!', 'success');
            loadListingDetail();
        }
    } catch (error) {
        showToast('Erro ao aplicar atributo', 'error');
    }
}

async function refreshCompetitors() {
    showToast('Atualizando concorrentes...', 'info');
    try {
        const data = await requestJson(`/api/seo/intelligence/competitors/${itemId}/refresh`, {
            method: 'POST'
        });

        if (data.success) {
            showToast(`${data.data.competitor_count} concorrentes encontrados!`, 'success');
            loadListingDetail();
        }
    } catch (error) {
        showToast('Erro ao atualizar', 'error');
    }
}

async function rollback(versionId) {
    if (!confirm('Tem certeza que deseja reverter para esta versão?')) {
        return;
    }
    
    const reason = prompt('Motivo do rollback:');
    if (!reason) return;
    
    try {
        const data = await requestJson(`/api/seo/intelligence/rollback/${itemId}/${versionId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reason: reason })
        });

        if (data.success) {
            showToast('Rollback realizado!', 'success');
            loadListingDetail();
        }
    } catch (error) {
        showToast('Erro no rollback', 'error');
    }
}

function showToast(message, type = 'info') {
    const bgColors = {
        success: 'linear-gradient(to right, #00b09b, #96c93d)',
        error: 'linear-gradient(to right, #ff5f6d, #ffc371)',
        warning: 'linear-gradient(to right, #f2994a, #f2c94c)',
        info: 'linear-gradient(to right, #4facfe, #00f2fe)'
    };
    
    Toastify({
        text: message,
        duration: 3000,
        gravity: 'top',
        position: 'right',
        style: {
            background: bgColors[type] || bgColors.info
        }
    }).showToast();
}
</script>
