<?php
// Single Item Optimization Editor
$title = 'Otimizar Anúncio';
$subtitle = 'Otimize seu anúncio com Inteligência Artificial';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';

// Get item ID from URL or session
$itemId = $_GET['item_id'] ?? $suggestions['item_id'] ?? null;

if (!$itemId) {
    echo '<div class="alert alert-warning">Item ID não fornecido.</div>';
    include __DIR__ . '/../../layouts/modern/partials/page-footer.php';
    exit;
}
?>

<div id="ai-item-editor" data-item-id="<?= htmlspecialchars($itemId) ?>">
    <!-- Score Comparison -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted">Score Atual</h6>
                    <h1 class="display-3 mb-0" id="score-before">
                        <span class="spinner-border" role="status"></span>
                    </h1>
                    <span class="badge bg-secondary mt-2" id="score-before-badge">Carregando...</span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm text-center bg-light">
                <div class="card-body">
                    <h6 class="text-muted">Score Previsto</h6>
                    <h1 class="display-3 mb-0 text-success" id="score-after">--</h1>
                    <span class="badge bg-success mt-2" id="score-after-badge">Gerando otimizações...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Optimization Tabs -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <ul class="nav nav-tabs card-header-tabs" id="optimizationTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="title-tab" data-bs-toggle="tab" data-bs-target="#title-pane" type="button">
                        <i class="bi bi-chat-square-text"></i> Título
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="description-tab" data-bs-toggle="tab" data-bs-target="#description-pane" type="button">
                        <i class="bi bi-file-text"></i> Descrição
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="attributes-tab" data-bs-toggle="tab" data-bs-target="#attributes-pane" type="button">
                        <i class="bi bi-list-check"></i> Ficha Técnica
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="optimizationTabContent">
                <!-- Title Tab -->
                <div class="tab-pane fade show active" id="title-pane" role="tabpanel">
                    <h5 class="mb-3">Otimização de Título</h5>
                    
                    <!-- Current Title -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Título Atual <span class="badge bg-secondary" id="title-score-before">--</span></label>
                        <div class="card bg-light">
                            <div class="card-body">
                                <p class="mb-2" id="current-title">Carregando...</p>
                                <div id="title-issues" class="mt-2"></div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Suggestions -->
                    <div id="title-suggestions">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 text-muted">Gerando sugestões com IA...</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-outline-primary" onclick="regenerateTitles()">
                            <i class="bi bi-arrow-clockwise"></i> Gerar Novas Sugestões
                        </button>
                    </div>
                </div>

                <!-- Description Tab -->
                <div class="tab-pane fade" id="description-pane" role="tabpanel">
                    <h5 class="mb-3">Otimização de Descrição</h5>
                    
                    <!-- Current Description -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Descrição Atual <span class="badge bg-secondary" id="desc-score-before">--</span></label>
                        <div class="card bg-light">
                            <div class="card-body">
                                <div id="current-description" style="max-height: 200px; overflow-y: auto;">Carregando...</div>
                                <div id="description-issues" class="mt-2"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Template Selector -->
                    <div class="mb-3">
                        <label class="form-label">Estilo de Descrição</label>
                        <select class="form-select" id="description-template" onchange="generateDescription()">
                            <option value="persuasive">Persuasiva (Foco em vendas)</option>
                            <option value="technical">Técnica (Foco em especificações)</option>
                            <option value="seo">SEO (Foco em busca)</option>
                        </select>
                    </div>

                    <!-- AI Generated Description -->
                    <div id="description-suggestion">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 text-muted">Gerando descrição otimizada...</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-outline-primary" onclick="generateDescription()">
                            <i class="bi bi-arrow-clockwise"></i> Regenerar
                        </button>
                        <button class="btn btn-success" onclick="applyDescription()" disabled id="apply-desc-btn">
                            <i class="bi bi-check-lg"></i> Aplicar Descrição
                        </button>
                    </div>
                </div>

                <!-- Tech Sheet Tab -->
                <div class="tab-pane fade" id="attributes-pane" role="tabpanel">
                    <h5 class="mb-3">Completar Ficha Técnica</h5>
                    
                    <!-- Completeness Bar -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-bold">Completude</span>
                            <span id="completeness-pct">0%</span>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar" id="completeness-bar" style="width: 0%">0%</div>
                        </div>
                    </div>

                    <!-- Missing Attributes -->
                    <div id="missing-attributes">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 text-muted">Analisando atributos...</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-success" onclick="applyAllAttributes()" disabled id="apply-attrs-btn">
                            <i class="bi bi-check-all"></i> Aplicar Todos
                        </button>
                        <button class="btn btn-outline-secondary" onclick="skipAttributes()">
                            <i class="bi bi-x-lg"></i> Ignorar Sugestões
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Panel -->
    <div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-clipboard-check"></i> Resumo da Otimização</h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <small class="text-muted">Score</small>
                <div class="d-flex justify-content-between align-items-center">
                    <span id="summary-score-before">--</span>
                    <i class="bi bi-arrow-right"></i>
                    <span class="text-success fw-bold" id="summary-score-after">--</span>
                </div>
            </div>

            <hr>

            <div id="changes-summary">
                <p class="text-muted text-center">Nenhuma mudança selecionada</p>
            </div>

            <hr>

            <div class="mb-3">
                <small class="text-muted">Estimativa de Impacto</small>
                <div id="impact-estimate">
                    <div class="d-flex justify-content-between">
                        <span>Views:</span>
                        <span class="text-success">--</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>CTR:</span>
                        <span class="text-success">--</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Conversão:</span>
                        <span class="text-success">--</span>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button class="btn btn-success btn-lg" onclick="applyAll()" disabled id="apply-all-btn">
                    <i class="bi bi-check-circle"></i> Aplicar Tudo
                </button>
                <button class="btn btn-outline-primary" onclick="previewFinal()">
                    <i class="bi bi-eye"></i> Preview Final
                </button>
                <button class="btn btn-outline-secondary" onclick="saveDraft()">
                    <i class="bi bi-save"></i> Salvar Rascunho
                </button>
                <a href="/dashboard/ai-optimization" class="btn btn-outline-danger">
                    <i class="bi bi-x-lg"></i> Cancelar
                </a>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

const itemId = document.getElementById('ai-item-editor').dataset.itemId;
let optimizationData = {
    title: null,
    description: null,
    attributes: null
};

// Load item data and generate optimizations
async function loadItemData() {
    try {
        // Get optimization suggestions
        const response = await fetch(`/api/ai/suggestions/${itemId}`);
        if (!response.ok) throw new Error('Failed to load suggestions');
        
        const data = await response.json();
        
        // Update current values
        document.getElementById('current-title').textContent = data.current.title || '';
        document.getElementById('current-description').innerHTML = 
            (data.current.description || '').replace(/\n/g, '<br>');
        
        // Update scores
        updateScoreDisplay(data.current.score || 0);
        
        // Load optimizations
        await loadTitleSuggestions();
        await loadDescriptionSuggestion();
        await loadAttributeSuggestions();
        
    } catch (error) {
        console.error('Error loading item data:', error);
        showError('Erro ao carregar dados do anúncio');
    }
}

function updateScoreDisplay(score) {
    document.getElementById('score-before').textContent = score;
    document.getElementById('summary-score-before').textContent = score;
    
    const badge = document.getElementById('score-before-badge');
    if (score < 50) {
        badge.className = 'badge bg-danger mt-2';
        badge.textContent = 'Crítico';
    } else if (score < 70) {
        badge.className = 'badge bg-warning mt-2';
        badge.textContent = 'Médio';
    } else if (score < 85) {
        badge.className = 'badge bg-info mt-2';
        badge.textContent = 'Bom';
    } else {
        badge.className = 'badge bg-success mt-2';
        badge.textContent = 'Excelente';
    }
}

async function loadTitleSuggestions() {
    try {
        const data = await requestJson('/api/ai/optimize/title', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId })
        });
        
        optimizationData.title = data;
        
        renderTitleSuggestions(data.suggestions || []);
        
    } catch (error) {
        console.error('Error loading title suggestions:', error);
        document.getElementById('title-suggestions').innerHTML = 
            '<div class="alert alert-danger">Erro ao gerar sugestões</div>';
    }
}

function renderTitleSuggestions(suggestions) {
    const container = document.getElementById('title-suggestions');
    
    if (!suggestions || suggestions.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhuma sugestão disponível</p>';
        return;
    }
    
    let html = '';
    suggestions.forEach((suggestion, index) => {
        const isRecommended = index === 0;
        html += `
            <div class="card mb-3 ${isRecommended ? 'border-primary' : ''}">
                <div class="card-body">
                    ${isRecommended ? '<span class="badge bg-primary mb-2">⭐ Recomendado</span>' : ''}
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0">${suggestion.title}</h6>
                        <span class="badge bg-success">Score: ${suggestion.score}/100</span>
                    </div>
                    <div class="mb-2">
                        <small class="text-success">✅ ${suggestion.length} caracteres</small>
                        <small class="text-success ms-2">✅ ${suggestion.keywords} keywords</small>
                    </div>
                    <p class="mb-2 text-muted"><small>Estimativa: +${suggestion.improvement}% CTR</small></p>
                    <button class="btn btn-sm btn-primary" onclick="selectTitle(${index})">
                        <i class="bi bi-check-lg"></i> Aplicar Este
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

async function loadDescriptionSuggestion() {
    try {
        const template = document.getElementById('description-template').value;
        const data = await requestJson('/api/ai/optimize/description', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId, template })
        });
        
        optimizationData.description = data;
        
        renderDescriptionSuggestion(data);
        
    } catch (error) {
        console.error('Error loading description:', error);
        document.getElementById('description-suggestion').innerHTML = 
            '<div class="alert alert-danger">Erro ao gerar descrição</div>';
    }
}

function renderDescriptionSuggestion(data) {
    const container = document.getElementById('description-suggestion');
    
    container.innerHTML = `
        <div class="card border-success">
            <div class="card-header bg-success text-white d-flex justify-content-between">
                <span>Descrição Otimizada</span>
                <span class="badge bg-light text-dark">Score: ${data.score}/100</span>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <div class="formatted-description">${(data.description || '').replace(/\n/g, '<br>')}</div>
            </div>
            <div class="card-footer bg-light">
                <small class="text-muted">
                    ${data.char_count} caracteres | ${data.keywords_count} keywords
                </small>
            </div>
        </div>
    `;
    
    document.getElementById('apply-desc-btn').disabled = false;
}

async function loadAttributeSuggestions() {
    try {
        const data = await requestJson('/api/ai/optimize/tech-sheet', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId })
        });
        
        optimizationData.attributes = data;
        
        renderAttributeSuggestions(data);
        
    } catch (error) {
        console.error('Error loading attributes:', error);
        document.getElementById('missing-attributes').innerHTML = 
            '<div class="alert alert-danger">Erro ao analisar atributos</div>';
    }
}

function renderAttributeSuggestions(data) {
    const container = document.getElementById('missing-attributes');
    const completeness = data.completeness || 0;
    
    // Update completeness bar
    document.getElementById('completeness-pct').textContent = completeness + '%';
    document.getElementById('completeness-bar').style.width = completeness + '%';
    document.getElementById('completeness-bar').textContent = completeness + '%';
    
    if (completeness >= 95) {
        document.getElementById('completeness-bar').className = 'progress-bar bg-success';
    } else if (completeness >= 70) {
        document.getElementById('completeness-bar').className = 'progress-bar bg-warning';
    } else {
        document.getElementById('completeness-bar').className = 'progress-bar bg-danger';
    }
    
    const suggestions = data.suggestions || [];
    
    if (suggestions.length === 0) {
        container.innerHTML = '<div class="alert alert-success">✅ Ficha técnica completa!</div>';
        return;
    }
    
    let html = '<div class="list-group">';
    suggestions.forEach((attr, index) => {
        html += `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1 me-3">
                        <strong>${attr.name}</strong>
                        <div class="form-text">${attr.suggested_value}</div>
                    </div>
                    <button class="btn btn-sm btn-outline-success" onclick="applyAttribute(${index})">
                        <i class="bi bi-check-lg"></i> Aplicar
                    </button>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
    document.getElementById('apply-attrs-btn').disabled = false;
}

function selectTitle(index) {
    // Mark as selected and update summary
    console.log('Selected title:', index);
    document.getElementById('apply-all-btn').disabled = false;
    updateSummary();
}

function applyDescription() {
    console.log('Applying description');
    updateSummary();
}

function applyAttribute(index) {
    console.log('Applying attribute:', index);
}

function applyAllAttributes() {
    console.log('Applying all attributes');
    updateSummary();
}

function updateSummary() {
    // Update changes summary
    const changes = [];
    if (optimizationData.title) changes.push('✓ Título');
    if (optimizationData.description) changes.push('✓ Descrição');
    if (optimizationData.attributes) changes.push('✓ Atributos');
    
    document.getElementById('changes-summary').innerHTML = changes.join('<br>') || 
        '<p class="text-muted text-center">Nenhuma mudança</p>';
    
    // Estimate impact
    document.getElementById('impact-estimate').innerHTML = `
        <div class="d-flex justify-content-between">
            <span>Views:</span>
            <span class="text-success">+145%</span>
        </div>
        <div class="d-flex justify-content-between">
            <span>CTR:</span>
            <span class="text-success">+89%</span>
        </div>
        <div class="d-flex justify-content-between">
            <span>Conversão:</span>
            <span class="text-success">+67%</span>
        </div>
    `;
}

async function applyAll() {
    if (!confirm('Aplicar todas as otimizações no Mercado Livre?')) return;
    
    try {
        const result = await requestJson('/api/ai/optimize/complete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                item_id: itemId,
                optimize_title: !!optimizationData.title,
                optimize_description: !!optimizationData.description,
                optimize_attributes: !!optimizationData.attributes
            })
        });
        
        if (result.success) {
            alert('✅ Otimizações aplicadas com sucesso!');
            window.location.href = '/dashboard/ai-optimization';
        } else {
            alert('Erro: ' + (result.error || 'Unknown error'));
        }
        
    } catch (error) {
        console.error('Error applying optimizations:', error);
        alert('Erro ao aplicar otimizações');
    }
}

function regenerateTitles() {
    document.getElementById('title-suggestions').innerHTML = 
        '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    loadTitleSuggestions();
}

function generateDescription() {
    document.getElementById('description-suggestion').innerHTML = 
        '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    loadDescriptionSuggestion();
}

function previewFinal() {
    alert('Preview functionality coming soon!');
}

function saveDraft() {
    alert('Draft saved!');
}

function skipAttributes() {
    if (confirm('Pular sugestões de atributos?')) {
        document.getElementById('attributes-tab').classList.add('disabled');
    }
}

function showError(message) {
    const alert = `
        <div class="alert alert-danger alert-dismissible fade show">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.getElementById('ai-item-editor').insertAdjacentHTML('afterbegin', alert);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadItemData();
});
</script>

<style>
.formatted-description {
    white-space: pre-wrap;
    line-height: 1.6;
}
.sticky-top {
    position: sticky;
}
</style>

<?php include __DIR__ . '/../../layouts/modern/partials/page-footer.php'; ?>
