<?php

declare(strict_types=1);

/**
 * Clone A/B Testing Dashboard
 * Interface para gerenciamento de testes A/B de clonagem
 */
$this->layout('layouts/dashboard', ['title' => 'Testes A/B - Clonador']);
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1">
                    <i class="bi bi-diagram-3 text-primary me-2"></i>
                    Testes A/B de Clonagem
                </h1>
                <p class="text-muted mb-0">Compare variações para otimizar suas clonagens</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTestModal">
                <i class="bi bi-plus-lg me-1"></i> Novo Teste
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4" id="statsCards">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Testes Ativos</h6>
                            <h3 class="mb-0" id="activeTests">-</h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-play-circle text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Testes Concluídos</h6>
                            <h3 class="mb-0" id="completedTests">-</h3>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-check-circle text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Taxa de Vitória</h6>
                            <h3 class="mb-0" id="winRate">-</h3>
                        </div>
                        <div class="bg-info bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-trophy text-info fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Significância Média</h6>
                            <h3 class="mb-0" id="avgSignificance">-</h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-graph-up text-warning fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">Todos</option>
                        <option value="draft">Rascunho</option>
                        <option value="running">Em Execução</option>
                        <option value="paused">Pausado</option>
                        <option value="completed">Concluído</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo de Variação</label>
                    <select class="form-select" id="filterVariationType">
                        <option value="">Todos</option>
                        <option value="title">Título</option>
                        <option value="price">Preço</option>
                        <option value="description">Descrição</option>
                        <option value="images">Imagens</option>
                        <option value="shipping">Frete</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Período</label>
                    <select class="form-select" id="filterPeriod">
                        <option value="">Todos</option>
                        <option value="7">Últimos 7 dias</option>
                        <option value="30" selected>Últimos 30 dias</option>
                        <option value="90">Últimos 90 dias</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-secondary w-100" onclick="loadTests()">
                        <i class="bi bi-funnel me-1"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tests List -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">Testes A/B</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Teste</th>
                            <th>Variações</th>
                            <th>Status</th>
                            <th>Progresso</th>
                            <th>Vencedor</th>
                            <th>Significância</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="testsTable">
                        <tr>
                            <td colspan="7" class="text-center py-4">
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

<!-- Create Test Modal -->
<div class="modal fade" id="createTestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Criar Novo Teste A/B
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createTestForm">
                    <div class="mb-3">
                        <label class="form-label">Nome do Teste</label>
                        <input type="text" class="form-control" name="name" required 
                               placeholder="Ex: Teste de títulos com marca">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="description" rows="2"
                                  placeholder="Descreva o objetivo do teste"></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipo de Variação</label>
                            <select class="form-select" name="variation_type" required>
                                <option value="title">Título</option>
                                <option value="price">Preço</option>
                                <option value="description">Descrição</option>
                                <option value="images">Imagens</option>
                                <option value="shipping">Tipo de Frete</option>
                                <option value="listing_type">Tipo de Anúncio</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Métrica Principal</label>
                            <select class="form-select" name="primary_metric">
                                <option value="visits">Visitas</option>
                                <option value="sales" selected>Vendas</option>
                                <option value="conversion">Taxa de Conversão</option>
                                <option value="revenue">Receita</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Duração Mínima (dias)</label>
                            <input type="number" class="form-control" name="min_duration" value="7" min="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Significância Desejada (%)</label>
                            <input type="number" class="form-control" name="significance_threshold" 
                                   value="95" min="80" max="99">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">IDs dos Itens (separados por vírgula)</label>
                        <textarea class="form-control" name="item_ids" rows="3"
                                  placeholder="MLB123456789, MLB987654321, ..."></textarea>
                        <div class="form-text">
                            Os itens serão divididos aleatoriamente entre as variações
                        </div>
                    </div>
                    
                    <h6 class="border-bottom pb-2 mb-3">Variações</h6>
                    <div id="variationsContainer">
                        <div class="variation-row mb-3 p-3 border rounded">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label">Nome da Variação</label>
                                    <input type="text" class="form-control" name="variations[0][name]" 
                                           value="Controle" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Configuração</label>
                                    <input type="text" class="form-control" name="variations[0][config]"
                                           placeholder='{"prefix": ""}'>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <span class="badge bg-secondary">Controle</span>
                                </div>
                            </div>
                        </div>
                        <div class="variation-row mb-3 p-3 border rounded">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label">Nome da Variação</label>
                                    <input type="text" class="form-control" name="variations[1][name]" 
                                           value="Variação A" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Configuração</label>
                                    <input type="text" class="form-control" name="variations[1][config]"
                                           placeholder='{"prefix": "🔥 "}'>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                            onclick="removeVariation(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addVariation()">
                        <i class="bi bi-plus me-1"></i> Adicionar Variação
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="createTest()">
                    <i class="bi bi-check-lg me-1"></i> Criar Teste
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Test Details Modal -->
<div class="modal fade" id="testDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="testDetailsTitle">Detalhes do Teste</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="testDetailsBody">
                <!-- Dynamic content -->
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadTests();
});

let variationCount = 2;

async function loadStats() {
    try {
        const running = await requestJson('/api/clone/ab-tests?status=running');
        
        const completed = await requestJson('/api/clone/ab-tests?status=completed');
        
        document.getElementById('activeTests').textContent = running.data?.length || 0;
        document.getElementById('completedTests').textContent = completed.data?.length || 0;
        
        // Calculate win rate
        const withWinner = completed.data?.filter(t => t.winner_variation_id).length || 0;
        const winRate = completed.data?.length > 0 
            ? Math.round((withWinner / completed.data.length) * 100) 
            : 0;
        document.getElementById('winRate').textContent = winRate + '%';
        
        // Calculate average significance
        let totalSig = 0;
        let sigCount = 0;
        completed.data?.forEach(t => {
            if (t.significance) {
                totalSig += parseFloat(t.significance);
                sigCount++;
            }
        });
        const avgSig = sigCount > 0 ? Math.round(totalSig / sigCount) : 0;
        document.getElementById('avgSignificance').textContent = avgSig + '%';
    } catch (e) {
        console.error('Erro ao carregar stats:', e);
    }
}

async function loadTests() {
    const tbody = document.getElementById('testsTable');
    
    try {
        const status = document.getElementById('filterStatus').value;
        const variationType = document.getElementById('filterVariationType').value;
        
        let url = '/api/clone/ab-tests?limit=50';
        if (status) url += '&status=' + status;
        if (variationType) url += '&variation_type=' + variationType;
        
        const result = await requestJson(url);
        
        if (!result.success || !result.data?.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        Nenhum teste encontrado
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = result.data.map(test => {
            const statusBadge = getStatusBadge(test.status);
            const progress = calculateProgress(test);
            const winner = test.winner_variation_id ? 
                `<span class="badge bg-success">${test.winner_name || 'Sim'}</span>` : 
                '<span class="text-muted">-</span>';
            const significance = test.significance ? 
                `${Math.round(test.significance)}%` : '-';
            
            return `
                <tr>
                    <td>
                        <div class="fw-medium">${escapeHtml(test.name)}</div>
                        <small class="text-muted">${test.variation_type}</small>
                    </td>
                    <td>
                        <span class="badge bg-secondary">${test.variation_count || 2}</span>
                    </td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="progress" style="height: 8px; width: 100px;">
                            <div class="progress-bar" style="width: ${progress}%"></div>
                        </div>
                        <small class="text-muted">${progress}%</small>
                    </td>
                    <td>${winner}</td>
                    <td>
                        <span class="${parseFloat(test.significance || 0) >= 95 ? 'text-success fw-bold' : ''}">
                            ${significance}
                        </span>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="viewTest(${test.id})" 
                                    title="Ver Detalhes">
                                <i class="bi bi-eye"></i>
                            </button>
                            ${test.status === 'draft' ? `
                                <button class="btn btn-outline-success" onclick="startTest(${test.id})" 
                                        title="Iniciar">
                                    <i class="bi bi-play"></i>
                                </button>
                            ` : ''}
                            ${test.status === 'running' ? `
                                <button class="btn btn-outline-warning" onclick="pauseTest(${test.id})" 
                                        title="Pausar">
                                    <i class="bi bi-pause"></i>
                                </button>
                                <button class="btn btn-outline-primary" onclick="completeTest(${test.id})" 
                                        title="Concluir">
                                    <i class="bi bi-check"></i>
                                </button>
                            ` : ''}
                            ${test.status === 'paused' ? `
                                <button class="btn btn-outline-success" onclick="startTest(${test.id})" 
                                        title="Retomar">
                                    <i class="bi bi-play"></i>
                                </button>
                            ` : ''}
                            ${test.status === 'completed' && test.winner_variation_id ? `
                                <button class="btn btn-outline-success" onclick="applyWinner(${test.id})" 
                                        title="Aplicar Vencedor">
                                    <i class="bi bi-trophy"></i>
                                </button>
                            ` : ''}
                            <button class="btn btn-outline-danger" onclick="cancelTest(${test.id})" 
                                    title="Cancelar">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (e) {
        console.error('Erro ao carregar testes:', e);
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4 text-danger">
                    Erro ao carregar testes
                </td>
            </tr>
        `;
    }
}

function getStatusBadge(status) {
    const badges = {
        draft: '<span class="badge bg-secondary">Rascunho</span>',
        running: '<span class="badge bg-primary">Em Execução</span>',
        paused: '<span class="badge bg-warning">Pausado</span>',
        completed: '<span class="badge bg-success">Concluído</span>',
        cancelled: '<span class="badge bg-danger">Cancelado</span>'
    };
    return badges[status] || status;
}

function calculateProgress(test) {
    if (!test.started_at) return 0;
    if (test.status === 'completed') return 100;
    
    const start = new Date(test.started_at);
    const now = new Date();
    const minDuration = (test.min_duration_days || 7) * 24 * 60 * 60 * 1000;
    const elapsed = now - start;
    
    return Math.min(100, Math.round((elapsed / minDuration) * 100));
}

function addVariation() {
    variationCount++;
    const container = document.getElementById('variationsContainer');
    const div = document.createElement('div');
    div.className = 'variation-row mb-3 p-3 border rounded';
    div.innerHTML = `
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label">Nome da Variação</label>
                <input type="text" class="form-control" name="variations[${variationCount - 1}][name]" 
                       value="Variação ${String.fromCharCode(64 + variationCount - 1)}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Configuração</label>
                <input type="text" class="form-control" name="variations[${variationCount - 1}][config]"
                       placeholder='{"prefix": ""}'>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeVariation(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(div);
}

function removeVariation(btn) {
    if (document.querySelectorAll('.variation-row').length > 2) {
        btn.closest('.variation-row').remove();
    } else {
        alert('Um teste precisa de pelo menos 2 variações');
    }
}

async function createTest() {
    const form = document.getElementById('createTestForm');
    const formData = new FormData(form);
    
    const variations = [];
    document.querySelectorAll('.variation-row').forEach((row, index) => {
        const name = row.querySelector(`input[name*="[name]"]`).value;
        const configStr = row.querySelector(`input[name*="[config]"]`).value;
        let config = {};
        try {
            config = configStr ? JSON.parse(configStr) : {};
        } catch (e) {}
        
        variations.push({
            name: name,
            is_control: index === 0,
            config: config
        });
    });
    
    const data = {
        name: formData.get('name'),
        description: formData.get('description'),
        variation_type: formData.get('variation_type'),
        primary_metric: formData.get('primary_metric'),
        min_duration_days: parseInt(formData.get('min_duration')),
        significance_threshold: parseFloat(formData.get('significance_threshold')) / 100,
        item_ids: formData.get('item_ids').split(',').map(s => s.trim()).filter(s => s),
        variations: variations
    };
    
    try {
        const result = await requestJson('/api/clone/ab-tests', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('createTestModal')).hide();
            form.reset();
            loadTests();
            loadStats();
            showToast('Teste criado com sucesso!', 'success');
        } else {
            showToast(result.error || 'Erro ao criar teste', 'danger');
        }
    } catch (e) {
        showToast('Erro ao criar teste', 'danger');
    }
}

async function viewTest(testId) {
    try {
        const result = await requestJson(`/api/clone/ab-tests/${testId}`);
        
        if (!result.success) {
            showToast('Teste não encontrado', 'danger');
            return;
        }
        
        const test = result.data;
        document.getElementById('testDetailsTitle').textContent = test.name;
        
        // Get results
        const resultsData = await requestJson(`/api/clone/ab-tests/${testId}/winner`);
        
        document.getElementById('testDetailsBody').innerHTML = `
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6>Informações do Teste</h6>
                    <table class="table table-sm">
                        <tr><td class="text-muted">Status:</td><td>${getStatusBadge(test.status)}</td></tr>
                        <tr><td class="text-muted">Tipo:</td><td>${test.variation_type}</td></tr>
                        <tr><td class="text-muted">Métrica Principal:</td><td>${test.primary_metric}</td></tr>
                        <tr><td class="text-muted">Criado em:</td><td>${formatDate(test.created_at)}</td></tr>
                        <tr><td class="text-muted">Iniciado em:</td><td>${formatDate(test.started_at) || '-'}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Resultados</h6>
                    ${resultsData.success && resultsData.data ? `
                        <div class="alert ${resultsData.data.winner ? 'alert-success' : 'alert-info'}">
                            ${resultsData.data.winner ? `
                                <strong>Vencedor:</strong> ${resultsData.data.winner.name}<br>
                                <strong>Significância:</strong> ${Math.round(resultsData.data.significance * 100)}%<br>
                                <strong>Melhoria:</strong> ${resultsData.data.improvement}%
                            ` : `
                                Ainda sem vencedor definido
                            `}
                        </div>
                    ` : '<p class="text-muted">Aguardando dados</p>'}
                </div>
            </div>
            
            <h6>Variações</h6>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Variação</th>
                            <th>Itens</th>
                            <th>Visitas</th>
                            <th>Vendas</th>
                            <th>Conversão</th>
                            <th>Receita</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${(test.variations || []).map(v => `
                            <tr class="${v.is_control ? 'table-light' : ''}">
                                <td>
                                    ${v.name}
                                    ${v.is_control ? '<span class="badge bg-secondary ms-1">Controle</span>' : ''}
                                    ${test.winner_variation_id == v.id ? '<span class="badge bg-success ms-1">Vencedor</span>' : ''}
                                </td>
                                <td>${v.item_count || 0}</td>
                                <td>${formatNumber(v.visits || 0)}</td>
                                <td>${formatNumber(v.sales || 0)}</td>
                                <td>${((v.sales / Math.max(v.visits, 1)) * 100).toFixed(2)}%</td>
                                <td>R$ ${formatNumber(v.revenue || 0)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
        
        new bootstrap.Modal(document.getElementById('testDetailsModal')).show();
    } catch (e) {
        showToast('Erro ao carregar detalhes', 'danger');
    }
}

async function startTest(testId) {
    if (!confirm('Iniciar o teste?')) return;
    
    try {
        const result = await requestJson(`/api/clone/ab-tests/${testId}/start`, {method: 'POST'});
        
        if (result.success) {
            loadTests();
            loadStats();
            showToast('Teste iniciado!', 'success');
        } else {
            showToast(result.error || 'Erro ao iniciar', 'danger');
        }
    } catch (e) {
        showToast('Erro ao iniciar teste', 'danger');
    }
}

async function pauseTest(testId) {
    if (!confirm('Pausar o teste?')) return;
    
    try {
        const result = await requestJson(`/api/clone/ab-tests/${testId}/pause`, {method: 'POST'});
        
        if (result.success) {
            loadTests();
            showToast('Teste pausado!', 'warning');
        } else {
            showToast(result.error || 'Erro ao pausar', 'danger');
        }
    } catch (e) {
        showToast('Erro ao pausar teste', 'danger');
    }
}

async function completeTest(testId) {
    if (!confirm('Concluir o teste e determinar vencedor?')) return;
    
    try {
        const result = await requestJson(`/api/clone/ab-tests/${testId}/complete`, {method: 'POST'});
        
        if (result.success) {
            loadTests();
            loadStats();
            showToast('Teste concluído!', 'success');
        } else {
            showToast(result.error || 'Erro ao concluir', 'danger');
        }
    } catch (e) {
        showToast('Erro ao concluir teste', 'danger');
    }
}

async function cancelTest(testId) {
    if (!confirm('Cancelar este teste? Esta ação não pode ser desfeita.')) return;
    
    try {
        const result = await requestJson(`/api/clone/ab-tests/${testId}`, {method: 'DELETE'});
        
        if (result.success) {
            loadTests();
            loadStats();
            showToast('Teste cancelado', 'info');
        } else {
            showToast(result.error || 'Erro ao cancelar', 'danger');
        }
    } catch (e) {
        showToast('Erro ao cancelar teste', 'danger');
    }
}

async function applyWinner(testId) {
    if (!confirm('Aplicar a configuração vencedora a todos os itens do teste?')) return;
    
    try {
        const result = await requestJson(`/api/clone/ab-tests/${testId}/apply-winner`, {method: 'POST'});
        
        if (result.success) {
            showToast(`Configuração aplicada a ${result.applied_count || 0} itens!`, 'success');
        } else {
            showToast(result.error || 'Erro ao aplicar', 'danger');
        }
    } catch (e) {
        showToast('Erro ao aplicar vencedor', 'danger');
    }
}

function formatDate(dateStr) {
    if (!dateStr) return null;
    return new Date(dateStr).toLocaleDateString('pt-BR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

function formatNumber(num) {
    return new Intl.NumberFormat('pt-BR').format(num);
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function showToast(message, type = 'info') {
    const container = document.querySelector('.toast-container') || (() => {
        const c = document.createElement('div');
        c.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(c);
        return c;
    })();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    container.appendChild(toast);
    new bootstrap.Toast(toast).show();
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}
</script>
