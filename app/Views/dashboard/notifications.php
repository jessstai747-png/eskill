<!-- Dashboard Notifications View -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Notificações</h4>
        <p class="text-muted mb-0">Todas as suas notificações em um só lugar</p>
    </div>
    <div class="btn-group btn-group-sm">
        <button class="btn btn-outline-primary" onclick="markAllAsRead()">
            <i class="bi bi-check-all"></i> Marcar Todas como Lidas
        </button>
        <button class="btn btn-outline-secondary" onclick="loadNotifications()">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-envelope fs-1 text-primary"></i>
                <h3 class="mt-2 mb-1" id="totalNotifications">0</h3>
                <p class="text-muted mb-0">Total</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-envelope-open fs-1 text-success"></i>
                <h3 class="mt-2 mb-1" id="readNotifications">0</h3>
                <p class="text-muted mb-0">Lidas</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-envelope-fill fs-1 text-warning"></i>
                <h3 class="mt-2 mb-1" id="unreadNotifications">0</h3>
                <p class="text-muted mb-0">Não Lidas</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#allTab" data-filter="all">Todas</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#unreadTab" data-filter="unread">Não Lidas</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#ordersTab" data-filter="orders">Pedidos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#questionsTab" data-filter="questions">Perguntas</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#systemTab" data-filter="system">Sistema</a>
            </li>
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush" id="notificationsList">
            <div class="text-center py-5 text-muted">Carregando...</div>
        </div>
    </div>
    <div class="card-footer">
        <nav id="pagination" class="d-flex justify-content-center"></nav>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">

let currentFilter = 'all';
let currentPage = 1;

document.querySelectorAll('[data-filter]').forEach(tab => {
    tab.addEventListener('click', function() {
        currentFilter = this.dataset.filter;
        currentPage = 1;
        loadNotifications();
    });
});

async function loadNotifications() {
    try {
        const data = await requestJson(`/api/notifications?filter=${currentFilter}&page=${currentPage}`);
        
        document.getElementById('totalNotifications').textContent = data.total || 0;
        document.getElementById('readNotifications').textContent = data.read || 0;
        document.getElementById('unreadNotifications').textContent = data.unread || 0;
        
        renderNotifications(data.notifications || []);
        renderPagination(data.total_pages || 1);
    } catch (e) {
        document.getElementById('notificationsList').innerHTML = '<div class="text-center py-5 text-danger">Erro ao carregar</div>';
    }
}

function renderNotifications(notifications) {
    const container = document.getElementById('notificationsList');
    
    if (notifications.length === 0) {
        container.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-bell-slash fs-1 d-block mb-2"></i>Nenhuma notificação</div>';
        return;
    }
    
    const typeIcons = {
        order: 'bag-check text-primary',
        question: 'chat-dots text-info',
        system: 'gear text-secondary',
        alert: 'exclamation-triangle text-warning',
        success: 'check-circle text-success',
        error: 'x-circle text-danger'
    };
    
    container.innerHTML = notifications.map(n => `
        <div class="list-group-item list-group-item-action ${!n.read_at ? 'bg-light' : ''}" onclick="openNotification(${n.id})">
            <div class="d-flex align-items-start">
                <i class="bi bi-${typeIcons[n.type] || 'bell'} fs-4 me-3"></i>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <h6 class="mb-1 ${!n.read_at ? 'fw-bold' : ''}">${n.title}</h6>
                        <small class="text-muted">${n.created_at}</small>
                    </div>
                    <p class="mb-1 text-muted small">${n.message}</p>
                    ${n.action_url ? `<a href="${n.action_url}" class="btn btn-sm btn-outline-primary mt-1" onclick="event.stopPropagation()">Ver Detalhes</a>` : ''}
                </div>
                ${!n.read_at ? '<span class="badge bg-primary rounded-pill ms-2">Nova</span>' : ''}
            </div>
        </div>
    `).join('');
}

function renderPagination(totalPages) {
    const container = document.getElementById('pagination');
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<ul class="pagination pagination-sm mb-0">';
    for (let i = 1; i <= Math.min(totalPages, 10); i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${i})">${i}</a>
        </li>`;
    }
    html += '</ul>';
    container.innerHTML = html;
}

function goToPage(page) {
    currentPage = page;
    loadNotifications();
}

async function openNotification(id) {
    try {
        await requestJson(`/api/notifications/${id}/read`, { method: 'POST' });
        loadNotifications();
    } catch (e) {}
}

async function markAllAsRead() {
    try {
        await requestJson('/api/notifications/read-all', { method: 'POST' });
        loadNotifications();
    } catch (e) {
        alert('Erro ao marcar notificações');
    }
}

loadNotifications();
</script>
