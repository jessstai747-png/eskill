<!-- Dashboard Alerts View -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Central de Alertas</h4>
        <p class="text-muted mb-0">Gerencie notificações e alertas do sistema</p>
    </div>
    <button class="btn btn-outline-primary btn-sm" onclick="markAllRead()">
        <i class="bi bi-check-all"></i> Marcar Todos como Lidos
    </button>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card h-100 border-danger">
            <div class="card-body text-center">
                <i class="bi bi-exclamation-triangle fs-1 text-danger"></i>
                <h3 class="mt-2 mb-1" id="criticalCount">0</h3>
                <p class="text-muted mb-0">Críticos</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 border-warning">
            <div class="card-body text-center">
                <i class="bi bi-exclamation-circle fs-1 text-warning"></i>
                <h3 class="mt-2 mb-1" id="warningCount">0</h3>
                <p class="text-muted mb-0">Avisos</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 border-info">
            <div class="card-body text-center">
                <i class="bi bi-info-circle fs-1 text-info"></i>
                <h3 class="mt-2 mb-1" id="infoCount">0</h3>
                <p class="text-muted mb-0">Informativos</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 border-success">
            <div class="card-body text-center">
                <i class="bi bi-check-circle fs-1 text-success"></i>
                <h3 class="mt-2 mb-1" id="successCount">0</h3>
                <p class="text-muted mb-0">Sucesso</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#allAlerts">Todos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#unreadAlerts">Não Lidos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#priceAlerts">Preços</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#stockAlerts">Estoque</a>
            </li>
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="allAlerts">
                <div id="alertsList" class="list-group list-group-flush">
                    <div class="text-center py-5 text-muted">Carregando...</div>
                </div>
            </div>
            <div class="tab-pane fade" id="unreadAlerts">
                <div id="unreadList" class="list-group list-group-flush"></div>
            </div>
            <div class="tab-pane fade" id="priceAlerts">
                <div id="priceList" class="list-group list-group-flush"></div>
            </div>
            <div class="tab-pane fade" id="stockAlerts">
                <div id="stockList" class="list-group list-group-flush"></div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">

async function loadAlerts() {
    try {
        const data = await requestJson('/api/alerts');
        
        const counts = { critical: 0, warning: 0, info: 0, success: 0 };
        (data.alerts || []).forEach(a => counts[a.type]++);
        
        document.getElementById('criticalCount').textContent = counts.critical;
        document.getElementById('warningCount').textContent = counts.warning;
        document.getElementById('infoCount').textContent = counts.info;
        document.getElementById('successCount').textContent = counts.success;
        
        renderAlerts('alertsList', data.alerts || []);
        renderAlerts('unreadList', (data.alerts || []).filter(a => !a.read));
        renderAlerts('priceList', (data.alerts || []).filter(a => a.category === 'price'));
        renderAlerts('stockList', (data.alerts || []).filter(a => a.category === 'stock'));
    } catch (e) {
        document.getElementById('alertsList').innerHTML = '<div class="text-center py-5 text-danger">Erro ao carregar</div>';
    }
}

function renderAlerts(containerId, alerts) {
    const container = document.getElementById(containerId);
    if (alerts.length === 0) {
        container.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-bell-slash fs-1 d-block mb-2"></i>Nenhum alerta</div>';
        return;
    }
    
    const typeIcons = {
        critical: 'exclamation-triangle text-danger',
        warning: 'exclamation-circle text-warning',
        info: 'info-circle text-info',
        success: 'check-circle text-success'
    };
    
    container.innerHTML = alerts.map(a => `
        <div class="list-group-item ${a.read ? 'bg-light' : ''}">
            <div class="d-flex align-items-start">
                <i class="bi bi-${typeIcons[a.type] || 'bell'} fs-4 me-3"></i>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <h6 class="mb-1">${a.title}</h6>
                        <small class="text-muted">${a.created_at}</small>
                    </div>
                    <p class="mb-1 text-muted small">${a.message}</p>
                    ${a.action_url ? `<a href="${a.action_url}" class="btn btn-sm btn-outline-primary mt-1">Ver Detalhes</a>` : ''}
                </div>
                <button class="btn btn-sm btn-link text-muted" onclick="dismissAlert(${a.id})" title="Dispensar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
    `).join('');
}

async function markAllRead() {
    try {
        await requestJson('/api/alerts/read-all', { method: 'POST' });
        loadAlerts();
    } catch (e) {
        alert('Erro ao marcar alertas');
    }
}

async function dismissAlert(id) {
    try {
        await requestJson(`/api/alerts/${id}`, { method: 'DELETE' });
        loadAlerts();
    } catch (e) {
        alert('Erro ao dispensar alerta');
    }
}

loadAlerts();
</script>
