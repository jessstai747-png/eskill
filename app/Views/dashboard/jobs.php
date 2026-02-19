<!-- Dashboard Jobs View -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Jobs e Tarefas</h4>
        <p class="text-muted mb-0">Acompanhe jobs agendados e execução de tarefas</p>
    </div>
    <div class="btn-group btn-group-sm">
        <button class="btn btn-outline-primary" onclick="loadJobs()"><i class="bi bi-arrow-clockwise"></i> Atualizar</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newJobModal"><i class="bi bi-plus-lg"></i> Novo Job</button>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-play-circle fs-1 text-success"></i>
                <h3 class="mt-2 mb-1" id="runningJobs">0</h3>
                <p class="text-muted mb-0">Em Execução</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-clock fs-1 text-warning"></i>
                <h3 class="mt-2 mb-1" id="scheduledJobs">0</h3>
                <p class="text-muted mb-0">Agendados</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-check-circle fs-1 text-primary"></i>
                <h3 class="mt-2 mb-1" id="completedJobs">0</h3>
                <p class="text-muted mb-0">Completados (24h)</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-x-circle fs-1 text-danger"></i>
                <h3 class="mt-2 mb-1" id="failedJobs">0</h3>
                <p class="text-muted mb-0">Falharam (24h)</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-list-task me-2"></i>Fila de Jobs</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Job</th>
                                <th>Status</th>
                                <th>Progresso</th>
                                <th>Início</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="jobsTable">
                            <tr><td colspan="5" class="text-center py-5 text-muted">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Jobs Agendados</h6>
            </div>
            <div class="list-group list-group-flush" id="scheduledList">
                <div class="text-center py-4 text-muted">Carregando...</div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Últimas Execuções</h6>
            </div>
            <div class="list-group list-group-flush" id="recentList" style="max-height:300px;overflow-y:auto">
                <div class="text-center py-4 text-muted">Carregando...</div>
            </div>
        </div>
    </div>
</div>

<!-- New Job Modal -->
<div class="modal fade" id="newJobModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Tipo de Job</label>
                    <select class="form-select" id="jobType">
                        <option value="sync_orders">Sincronizar Pedidos</option>
                        <option value="sync_items">Sincronizar Anúncios</option>
                        <option value="sync_questions">Sincronizar Perguntas</option>
                        <option value="update_prices">Atualizar Preços</option>
                        <option value="seo_analysis">Análise SEO</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Conta</label>
                    <select class="form-select" id="jobAccount">
                        <option value="all">Todas as Contas</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="createJob()">Criar Job</button>
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

async function loadJobs() {
    try {
        const data = await requestJson('/api/jobs');
        
        document.getElementById('runningJobs').textContent = data.running || 0;
        document.getElementById('scheduledJobs').textContent = data.scheduled || 0;
        document.getElementById('completedJobs').textContent = data.completed || 0;
        document.getElementById('failedJobs').textContent = data.failed || 0;
        
        renderJobsTable(data.jobs || []);
        renderScheduled(data.scheduled_jobs || []);
        renderRecent(data.recent || []);
    } catch (e) {
        document.getElementById('jobsTable').innerHTML = '<tr><td colspan="5" class="text-center py-5 text-danger">Erro</td></tr>';
    }
}

function renderJobsTable(jobs) {
    const tbody = document.getElementById('jobsTable');
    if (jobs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>Nenhum job na fila</td></tr>';
        return;
    }
    
    const statusBadge = {
        running: 'success', pending: 'warning', completed: 'primary', failed: 'danger'
    };
    
    tbody.innerHTML = jobs.map(j => `
        <tr>
            <td>
                <strong>${j.name}</strong>
                <br><small class="text-muted">${j.type}</small>
            </td>
            <td><span class="badge bg-${statusBadge[j.status] || 'secondary'}">${j.status}</span></td>
            <td>
                <div class="progress" style="height:6px;width:100px">
                    <div class="progress-bar" style="width:${j.progress || 0}%"></div>
                </div>
                <small class="text-muted">${j.progress || 0}%</small>
            </td>
            <td><small>${j.started_at || '-'}</small></td>
            <td>
                ${j.status === 'running' ? `<button class="btn btn-sm btn-outline-danger" onclick="cancelJob(${j.id})"><i class="bi bi-stop-fill"></i></button>` : ''}
                ${j.status === 'failed' ? `<button class="btn btn-sm btn-outline-primary" onclick="retryJob(${j.id})"><i class="bi bi-arrow-repeat"></i></button>` : ''}
            </td>
        </tr>
    `).join('');
}

function renderScheduled(jobs) {
    const container = document.getElementById('scheduledList');
    if (jobs.length === 0) {
        container.innerHTML = '<div class="text-center py-4 text-muted">Nenhum job agendado</div>';
        return;
    }
    container.innerHTML = jobs.map(j => `
        <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <strong>${j.name}</strong>
                <br><small class="text-muted">${j.schedule}</small>
            </div>
            <span class="badge bg-info">${j.next_run}</span>
        </div>
    `).join('');
}

function renderRecent(jobs) {
    const container = document.getElementById('recentList');
    if (jobs.length === 0) {
        container.innerHTML = '<div class="text-center py-4 text-muted">Sem execuções recentes</div>';
        return;
    }
    container.innerHTML = jobs.map(j => `
        <div class="list-group-item">
            <div class="d-flex justify-content-between">
                <span>${j.name}</span>
                <span class="badge bg-${j.status === 'completed' ? 'success' : 'danger'}">${j.status}</span>
            </div>
            <small class="text-muted">${j.completed_at} • ${j.duration}</small>
        </div>
    `).join('');
}

async function createJob() {
    const type = document.getElementById('jobType').value;
    const account = document.getElementById('jobAccount').value;
    
    try {
        await requestJson('/api/jobs', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type, account_id: account })
        });
        bootstrap.Modal.getInstance(document.getElementById('newJobModal')).hide();
        loadJobs();
    } catch (e) {
        alert('Erro ao criar job');
    }
}

async function cancelJob(id) {
    if (!confirm('Cancelar este job?')) return;
    try {
        await requestJson(`/api/jobs/${id}/cancel`, { method: 'POST' });
        loadJobs();
    } catch (e) {
        alert('Erro');
    }
}

async function retryJob(id) {
    try {
        await requestJson(`/api/jobs/${id}/retry`, { method: 'POST' });
        loadJobs();
    } catch (e) {
        alert('Erro');
    }
}

loadJobs();
setInterval(loadJobs, 10000);
</script>
