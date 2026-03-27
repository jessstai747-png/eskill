<?php

declare(strict_types=1);

$pageTitle = 'AI Autonomous Agents';
$activePage = 'agents';
?>

<div class="container-fluid px-0 px-md-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 text-white">AI Autonomous Agents</h4>
            <p class="mb-0 text-white-50">Gerencie sua força de trabalho de IA autônoma</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newProjectModal">
            <i class="bi bi-plus-lg me-2"></i> Novo Projeto
        </button>
    </div>

    <!-- Alert Area -->
    <div id="alert-area"></div>

    <div class="row">
        <!-- Autonomous Agents -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm bg-dark text-white">
                <div class="card-header bg-transparent border-bottom border-secondary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-cpu me-2"></i> Agentes Autônomos (Background)</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="loadAutonomousAgents()">
                        <i class="bi bi-arrow-clockwise"></i> Atualizar
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0 align-middle">
                            <thead class="bg-black bg-opacity-25">
                                <tr>
                                    <th class="ps-4">Agente</th>
                                    <th>Status</th>
                                    <th>Última Execução</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="autonomous-list">
                                <tr><td colspan="4" class="text-center py-3">Carregando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 bg-black bg-opacity-25 border-top border-secondary">
                        <h6 class="text-white-50 mb-2">Logs Recentes</h6>
                        <div id="autonomous-logs" class="font-monospace text-info small" style="max-height: 150px; overflow-y: auto;">
                            <!-- Logs will appear here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project List (Dev Mode) -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm bg-dark text-white">
                <div class="card-header bg-transparent border-bottom border-secondary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-code-square me-2"></i> Projetos de Desenvolvimento (DevAgents)</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="loadProjects()">
                        <i class="bi bi-arrow-clockwise"></i> Atualizar
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0 align-middle">
                            <thead class="bg-black bg-opacity-25">
                                <tr>
                                    <th class="ps-4">Projeto</th>
                                    <th>Status</th>
                                    <th>Progresso</th>
                                    <th>Última Atividade</th>
                                    <th class="text-end pe-4">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="projects-list">
                                <tr>
                                    <td colspan="5" class="text-center py-5">
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

        <!-- Working Console -->
        <div class="col-12" id="console-section" style="display: none;">
            <div class="row">
                <!-- Session Controller -->
                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm bg-dark text-white h-100">
                        <div class="card-header bg-transparent border-bottom border-secondary">
                            <h5 class="mb-0">Controle da Sessão</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-3">
                                <div class="p-3 border border-secondary rounded bg-black bg-opacity-25">
                                    <h6 class="text-muted text-uppercase small mb-2">Projeto Atual</h6>
                                    <h4 class="mb-1" id="active-project-name">-</h4>
                                    <span class="badge bg-primary" id="active-project-status">-</span>
                                </div>

                                <button id="btn-run-session" class="btn btn-lg btn-success" onclick="runSession()">
                                    <i class="bi bi-play-circle-fill me-2"></i> Iniciar Sessão de Coding
                                </button>

                                <div class="mt-3">
                                    <h6 class="text-muted text-uppercase small mb-2">Próxima Tarefa</h6>
                                    <div class="p-3 border border-dashed border-secondary rounded" id="next-task-display">
                                        <em class="text-white-50">Aguardando inicialização...</em>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Logs -->
                <div class="col-lg-8 mb-4">
                    <div class="card border-0 shadow-sm bg-dark text-white h-100">
                        <div class="card-header bg-transparent border-bottom border-secondary d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-terminal me-2"></i> Console em Tempo Real</h5>
                            <span class="badge bg-secondary" id="session-timer">00:00</span>
                        </div>
                        <div class="card-body bg-black font-monospace p-3" style="min-height: 400px; max-height: 600px; overflow-y: auto;" id="console-output">
                            <div class="text-success">> Sistema pronto. Selecione um projeto para começar.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Project Modal -->
<div class="modal fade" id="newProjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Novo Projeto de Agente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="new-project-form">
                    <div class="mb-3">
                        <label class="form-label">Nome do Projeto</label>
                        <input type="text" class="form-control bg-black text-white border-secondary" name="name" required placeholder="ex: Criar Landing Page">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição / Objetivo</label>
                        <textarea class="form-control bg-black text-white border-secondary" name="description" rows="4" required placeholder="Descreva o que o agente deve construir..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoria</label>
                        <select class="form-select bg-black text-white border-secondary" name="category">
                            <option value="dashboard">Dashboard Feature</option>
                            <option value="api">API Endpoint</option>
                            <option value="frontend">Frontend Component</option>
                            <option value="optimization">Otimização</option>
                            <option value="general">Geral</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="createProject()">Criar Projeto</button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">

let activeProjectId = null;
let pollInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    loadProjects();
    loadAutonomousAgents();
    loadAutonomousLogs();
});

function loadAutonomousAgents() {
    requestJson('/api/agent/autonomous')
        .then(data => {
            const tbody = document.getElementById('autonomous-list');
            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3">Nenhum agente configurado.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            data.data.forEach(a => {
                const row = `
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold">${a.name}</div>
                            <small class="text-muted text-uppercase">${a.code}</small>
                        </td>
                        <td><span class="badge bg-${a.status === 'active' ? 'success' : 'secondary'}">${a.status}</span></td>
                        <td>${a.last_run_at ? new Date(a.last_run_at).toLocaleString() : '-'}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-info" title="Configurar"><i class="bi bi-gear"></i></button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        });
}

function loadAutonomousLogs() {
    requestJson('/api/agent/autonomous/logs')
        .then(data => {
            const container = document.getElementById('autonomous-logs');
            if (data.data.length === 0) {
                container.innerHTML = '<div class="text-muted">Sem logs recentes.</div>';
                return;
            }
            container.innerHTML = '';
            data.data.forEach(l => {
                const color = l.level === 'warning' ? 'text-warning' : (l.level === 'error' ? 'text-danger' : 'text-info');
                container.innerHTML += `<div><span class="text-muted">[${new Date(l.created_at).toLocaleTimeString()}]</span> <span class="${color}">[${l.agent_code}] ${l.message}</span></div>`;
            });
        });
}

function loadProjects() {
    requestJson('/api/agent/projects')
        .then(data => {
            const tbody = document.getElementById('projects-list');
            tbody.innerHTML = '';
            
            if (data.data.projects.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Nenhum projeto ativo. Crie um novo para começar.</td></tr>';
                return;
            }

            data.data.projects.forEach(p => {
                const row = `
                    <tr style="cursor: pointer" onclick="selectProject(${p.id}, '${p.name}', '${p.status}')">
                        <td class="ps-4">
                            <div class="fw-bold">${p.name}</div>
                            <small class="text-muted">${p.category}</small>
                        </td>
                        <td><span class="badge bg-${getStatusColor(p.status)}">${p.status}</span></td>
                        <td>
                            <div class="progress" style="height: 6px; width: 100px;">
                                <div class="progress-bar" role="progressbar" style="width: ${p.progress_percent}%"></div>
                            </div>
                            <small class="text-muted">${p.progress_percent}%</small>
                        </td>
                        <td>${new Date(p.updated_at).toLocaleString()}</td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-chevron-right"></i></button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        })
        .catch(err => console.error(err));
}

function getStatusColor(status) {
    switch(status) {
        case 'completed': return 'success';
        case 'in_progress': return 'primary';
        case 'failed': return 'danger';
        default: return 'secondary';
    }
}

function createProject() {
    const form = document.getElementById('new-project-form');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Parse requirements (simple split by newline for now)
    data.requirements = data.description.split('\n').filter(line => line.trim().length > 0);

    const btn = document.querySelector('#newProjectModal .btn-primary');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Criando...';
    btn.disabled = true;

    requestJson('/api/agent/projects/start', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(resp => {
        if (resp.success) {
            bootstrap.Modal.getInstance(document.getElementById('newProjectModal')).hide();
            loadProjects();
            logConsole(`Projeto "${data.name}" criado com sucesso! ID: ${resp.data.project_id}`);
            selectProject(resp.data.project_id, data.name, 'initialized');
        } else {
            alert('Erro: ' + resp.error);
        }
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function selectProject(id, name, status) {
    activeProjectId = id;
    document.getElementById('console-section').style.display = 'block';
    
    document.getElementById('active-project-name').innerText = name;
    document.getElementById('active-project-status').innerText = status;
    document.getElementById('active-project-status').className = `badge bg-${getStatusColor(status)}`;
    
    document.getElementById('console-section').scrollIntoView({ behavior: 'smooth' });
    logConsole(`Selecionado projeto: ${name}`);
    
    // Load status details
    updateProjectStatus(id);
}

function updateProjectStatus(id) {
    requestJson(`/api/agent/projects/${id}/status`)
        .then(resp => {
            if(resp.success && resp.data) {
                const nextTask = resp.data.features_breakdown?.pending_features > 0 
                    ? `Pending Features: ${resp.data.pending_features}` 
                    : "Todas as tarefas concluídas!";
                
                document.getElementById('next-task-display').innerHTML = `<strong class="text-white">${nextTask}</strong>`;
            }
        });
}

function runSession() {
    if (!activeProjectId) return;

    const btn = document.getElementById('btn-run-session');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Executando...';
    
    logConsole("Iniciação de sessão de coding solicidata...");

    requestJson(`/api/agent/projects/${activeProjectId}/session`, {
        method: 'POST'
    })
    .then(resp => {
        if (resp.success) {
            logConsole(`Sessão concluída!`, 'success');
            logConsole(`Feature trabalhada: ${resp.data.feature_worked_on}`);
            logConsole(`Arquivos modificados: ${resp.data.files_modified.join(', ')}`);
            if (resp.data.tests_passed) {
                logConsole(`✅ Testes passaram`, 'success');
            } else {
                logConsole(`❌ Falha nos testes`, 'danger');
            }
            updateProjectStatus(activeProjectId);
            loadProjects(); // Refresh list stats
        } else {
            logConsole(`Erro na sessão: ${resp.error}`, 'danger');
        }
    })
    .catch(err => {
        logConsole(`Erro de comunicação: ${err}`, 'danger');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-circle-fill me-2"></i> Iniciar Sessão de Coding';
    });
}

function logConsole(msg, type = 'info') {
    const consoleDiv = document.getElementById('console-output');
    const timestamp = new Date().toLocaleTimeString();
    const colorClass = type === 'danger' ? 'text-danger' : (type === 'success' ? 'text-success' : 'text-info');
    
    consoleDiv.innerHTML += `<div class="mb-1"><span class="text-muted">[${timestamp}]</span> <span class="${colorClass}">${msg}</span></div>`;
    consoleDiv.scrollTop = consoleDiv.scrollHeight;
}
</script>
