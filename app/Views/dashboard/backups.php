<!-- Dashboard Backups View -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Backups do Sistema</h4>
        <p class="text-muted mb-0">Gerencie e restaure backups do banco de dados</p>
    </div>
    <button class="btn btn-primary btn-sm" onclick="createBackup()">
        <i class="bi bi-cloud-arrow-up"></i> Criar Backup Agora
    </button>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-archive fs-1 text-primary"></i>
                <h3 class="mt-2 mb-1" id="totalBackups">0</h3>
                <p class="text-muted mb-0">Backups Disponíveis</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-hdd fs-1 text-success"></i>
                <h3 class="mt-2 mb-1" id="totalSize">0 MB</h3>
                <p class="text-muted mb-0">Espaço Utilizado</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-clock fs-1 text-info"></i>
                <h3 class="mt-2 mb-1" id="lastBackup">-</h3>
                <p class="text-muted mb-0">Último Backup</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-archive me-2"></i>Lista de Backups</h6>
                <button class="btn btn-sm btn-outline-primary" onclick="loadBackups()">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Arquivo</th>
                                <th>Tamanho</th>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="backupsTable">
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
                <h6 class="mb-0"><i class="bi bi-gear me-2"></i>Configurações</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small">Backup Automático</label>
                    <select class="form-select form-select-sm" id="autoBackup">
                        <option value="daily">Diário</option>
                        <option value="weekly">Semanal</option>
                        <option value="monthly">Mensal</option>
                        <option value="disabled">Desativado</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Retenção (dias)</label>
                    <input type="number" class="form-control form-control-sm" id="retention" value="30" min="1">
                </div>
                <button class="btn btn-primary btn-sm w-100" onclick="saveSettings()">Salvar Configurações</button>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informações</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Backups são criptografados</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Armazenados localmente</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Inclui todas as tabelas</li>
                    <li><i class="bi bi-exclamation-triangle text-warning me-2"></i>Restaurar sobrescreve dados</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">

async function loadBackups() {
    try {
        const data = await requestJson('/api/backups');
        
        document.getElementById('totalBackups').textContent = data.total || 0;
        document.getElementById('totalSize').textContent = formatSize(data.total_size || 0);
        document.getElementById('lastBackup').textContent = data.last_backup || '-';
        
        const tbody = document.getElementById('backupsTable');
        if (!data.backups || data.backups.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-archive fs-1 d-block mb-2"></i>Nenhum backup encontrado</td></tr>';
            return;
        }
        
        tbody.innerHTML = data.backups.map(b => `
            <tr>
                <td>
                    <i class="bi bi-file-earmark-zip text-warning me-2"></i>
                    <span class="text-truncate" style="max-width:200px;display:inline-block">${b.filename}</span>
                </td>
                <td>${formatSize(b.size)}</td>
                <td><small>${b.created_at}</small></td>
                <td><span class="badge bg-${b.type === 'auto' ? 'info' : 'primary'}">${b.type === 'auto' ? 'Automático' : 'Manual'}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="/api/backups/${b.id}/download" class="btn btn-outline-primary" title="Baixar">
                            <i class="bi bi-download"></i>
                        </a>
                        <button class="btn btn-outline-warning" onclick="restoreBackup('${b.id}')" title="Restaurar">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteBackup('${b.id}')" title="Excluir">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    } catch (e) {
        document.getElementById('backupsTable').innerHTML = '<tr><td colspan="5" class="text-center py-5 text-danger">Erro ao carregar</td></tr>';
    }
}

function formatSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

async function createBackup() {
    if (!confirm('Criar um novo backup agora?')) return;
    
    try {
        const data = await requestJson('/api/backups', { method: 'POST' });
        
        if (data.success) {
            alert('Backup criado com sucesso!');
            loadBackups();
        } else {
            alert('Erro ao criar backup: ' + (data.error || 'Erro desconhecido'));
        }
    } catch (e) {
        alert('Erro ao criar backup');
    }
}

async function restoreBackup(id) {
    if (!confirm('ATENÇÃO: Restaurar este backup irá sobrescrever todos os dados atuais. Deseja continuar?')) return;
    if (!confirm('Tem certeza? Esta ação não pode ser desfeita!')) return;
    
    try {
        const data = await requestJson(`/api/backups/${id}/restore`, { method: 'POST' });
        
        if (data.success) {
            alert('Backup restaurado com sucesso!');
            location.reload();
        } else {
            alert('Erro ao restaurar: ' + (data.error || 'Erro desconhecido'));
        }
    } catch (e) {
        alert('Erro ao restaurar backup');
    }
}

async function deleteBackup(id) {
    if (!confirm('Excluir este backup?')) return;
    
    try {
        await requestJson(`/api/backups/${id}`, { method: 'DELETE' });
        loadBackups();
    } catch (e) {
        alert('Erro ao excluir backup');
    }
}

async function saveSettings() {
    const autoBackup = document.getElementById('autoBackup').value;
    const retention = document.getElementById('retention').value;
    
    try {
        await requestJson('/api/backups/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ auto_backup: autoBackup, retention })
        });
        alert('Configurações salvas!');
    } catch (e) {
        alert('Erro ao salvar');
    }
}

loadBackups();
</script>
