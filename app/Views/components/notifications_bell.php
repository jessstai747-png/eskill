<!-- Componente de Notificações com Bell Icon -->
<div class="dropdown">
    <button class="btn btn-link text-white position-relative" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-bell fs-5" id="bell-icon"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notification-badge" style="display: none;">
            0
        </span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" style="width: 380px; max-height: 550px; overflow-y: auto;">
        <li>
            <h6 class="dropdown-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bell-fill me-2"></i>Notificações</span>
                <div>
                    <button class="btn btn-sm btn-link p-0 me-2" onclick="toggleNotificationSound()" title="Ativar/Desativar Som">
                        <i class="bi bi-volume-up" id="sound-toggle-icon"></i>
                    </button>
                    <button class="btn btn-sm btn-link p-0" onclick="markAllNotificationsRead()">
                        Limpar
                    </button>
                </div>
            </h6>
        </li>
        
        <!-- Notificações em Tempo Real -->
        <li id="realtime-notifications-section">
            <div class="px-3 py-1 bg-light d-flex justify-content-between align-items-center">
                <small class="text-muted fw-bold"><i class="bi bi-lightning-fill text-warning me-1"></i>Tempo Real</small>
                <span class="badge bg-primary" id="realtime-count">0</span>
            </div>
        </li>
        <div id="realtime-notifications-list">
            <li class="px-3 py-2 text-center text-muted">
                <small>Nenhuma notificação recente</small>
            </li>
        </div>
        
        <li><hr class="dropdown-divider"></li>
        
        <!-- Alertas do Sistema -->
        <li>
            <div class="px-3 py-1 bg-light d-flex justify-content-between align-items-center">
                <small class="text-muted fw-bold"><i class="bi bi-bell me-1"></i>Alertas</small>
                <span class="badge bg-secondary" id="alerts-count">0</span>
            </div>
        </li>
        <div id="notifications-list">
            <li class="px-3 py-2 text-center">
                <div class="spinner-border spinner-border-sm text-primary"></div>
            </li>
        </div>
        <li>
            <hr class="dropdown-divider">
        </li>
        <li>
            <a class="dropdown-item text-center" href="/dashboard/alerts">
                <i class="bi bi-gear me-2"></i>Ver todas e configurar
            </a>
        </li>
    </ul>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
    // Estado do som de notificação
    let soundEnabled = localStorage.getItem('notification_sound') !== 'false';
    updateSoundIcon();
    
    // Obter token CSRF
    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }
    
    // Toggle som de notificação
    function toggleNotificationSound() {
        soundEnabled = !soundEnabled;
        localStorage.setItem('notification_sound', soundEnabled);
        updateSoundIcon();
        
        // Atualizar sistema de notificações em tempo real
        if (window.realTimeNotifications) {
            window.realTimeNotifications.config.soundEnabled = soundEnabled;
        }
    }
    
    function updateSoundIcon() {
        const icon = document.getElementById('sound-toggle-icon');
        if (icon) {
            icon.className = soundEnabled ? 'bi bi-volume-up text-success' : 'bi bi-volume-mute text-muted';
        }
    }

    // Carregar contador de notificações (alertas + tempo real)
    async function loadNotificationCount() {
        let total = 0;
        
        // Alertas do sistema
        try {
            const alertsData = await requestJson('/api/alerts/count');
            total += alertsData.count || 0;
            const alertsCountEl = document.getElementById('alerts-count');
            if (alertsCountEl) alertsCountEl.textContent = alertsData.count || 0;
        } catch (error) {
            console.error('Erro ao carregar alertas:', error);
        }
        
        // Notificações em tempo real
        try {
            const realtimeData = await requestJson('/api/notifications/realtime/unread');
            const rtCount = realtimeData.count || 0;
            total += rtCount;
            const rtCountEl = document.getElementById('realtime-count');
            if (rtCountEl) rtCountEl.textContent = rtCount;
            
            // Mostrar/ocultar seção se não houver notificações
            const section = document.getElementById('realtime-notifications-section');
            if (section) {
                section.style.display = rtCount > 0 ? 'block' : 'none';
            }
        } catch (error) {
            console.error('Erro ao carregar notificações em tempo real:', error);
        }
        
        // Atualizar badge principal
        const badge = document.getElementById('notification-badge');
        if (total > 0) {
            badge.textContent = total > 99 ? '99+' : total;
            badge.style.display = 'block';
            // Animação sutil do sininho
            const bellIcon = document.getElementById('bell-icon');
            if (bellIcon) bellIcon.classList.add('bi-bell-fill');
        } else {
            badge.style.display = 'none';
            const bellIcon = document.getElementById('bell-icon');
            if (bellIcon) bellIcon.classList.remove('bi-bell-fill');
        }
    }
    
    // Carregar notificações em tempo real
    async function loadRealtimeNotifications() {
        try {
            const data = await requestJson('/api/notifications/realtime/poll');
            
            const list = document.getElementById('realtime-notifications-list');
            if (!list) return;
            
            if (!data.notifications || data.notifications.length === 0) {
                list.innerHTML = '<li class="px-3 py-2 text-center text-muted"><small>Nenhuma notificação recente</small></li>';
                return;
            }
            
            let html = '';
            data.notifications.slice(0, 5).forEach(notification => {
                const icon = {
                    'order': 'bi-cart-check text-success',
                    'question': 'bi-chat-dots text-primary',
                    'message': 'bi-envelope text-info',
                    'alert': 'bi-exclamation-triangle text-warning'
                }[notification.type] || 'bi-bell text-secondary';
                
                const date = new Date(notification.created_at).toLocaleString('pt-BR', {
                    hour: '2-digit', minute: '2-digit'
                });
                
                html += `
                    <li>
                        <a class="dropdown-item py-2" href="${notification.url || '#'}" onclick="markRealtimeRead(${notification.id})">
                            <div class="d-flex align-items-start">
                                <i class="bi ${icon} fs-5 me-2 mt-1"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold small">${notification.title}</div>
                                    <div class="text-muted small text-truncate" style="max-width: 280px;">${notification.message || ''}</div>
                                    <small class="text-muted">${date}</small>
                                </div>
                            </div>
                        </a>
                    </li>
                `;
            });
            
            list.innerHTML = html;
        } catch (error) {
            console.error('Erro ao carregar notificações em tempo real:', error);
        }
    }

    // Carregar lista de alertas do sistema
    function loadNotifications() {
        requestJson('/api/alerts?unread=1&limit=5')
            .then(alerts => {
                const list = document.getElementById('notifications-list');

                if (alerts.length === 0) {
                    list.innerHTML = '<li class="px-3 py-2 text-muted text-center"><small>Nenhum alerta</small></li>';
                    return;
                }

                let html = '';
                alerts.forEach(alert => {
                    const severityClass = {
                        'info': 'text-info',
                        'warning': 'text-warning',
                        'danger': 'text-danger',
                        'success': 'text-success'
                    } [alert.severity] || 'text-secondary';

                    const icon = {
                        'info': 'bi-info-circle',
                        'warning': 'bi-exclamation-triangle',
                        'danger': 'bi-x-circle',
                        'success': 'bi-check-circle'
                    } [alert.severity] || 'bi-bell';

                    const date = new Date(alert.created_at).toLocaleString('pt-BR', {
                        hour: '2-digit', minute: '2-digit'
                    });

                    html += `
                        <li>
                            <a class="dropdown-item py-2" href="#" onclick="markNotificationRead(${alert.id}); return false;">
                                <div class="d-flex align-items-start">
                                    <i class="bi ${icon} ${severityClass} fs-5 me-2 mt-1"></i>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold small">${alert.message}</div>
                                        <small class="text-muted">${date}</small>
                                    </div>
                                </div>
                            </a>
                        </li>
                    `;
                });

                list.innerHTML = html;
            })
            .catch(error => {
                console.error('Erro ao carregar notificações:', error);
                document.getElementById('notifications-list').innerHTML =
                    '<li class="px-3 py-2 text-danger text-center"><small>Erro ao carregar</small></li>';
            });
    }
    
    function markRealtimeRead(id) {
        requestJson(`/api/notifications/realtime/${id}/read`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() }
        }).then(() => {
            loadNotificationCount();
            loadRealtimeNotifications();
        });
    }

    function markNotificationRead(alertId) {
        requestJson(`/api/alerts/${alertId}/read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken()
                }
            })
            .then(() => {
                loadNotifications();
                loadNotificationCount();
            });
    }

    function markAllNotificationsRead() {
        // Marcar alertas como lidos
        requestJson('/api/alerts/read-all', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() }
        });
        
        // Marcar notificações em tempo real como lidas
        requestJson('/api/notifications/realtime/read-all', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() }
        }).then(() => {
            loadNotifications();
            loadRealtimeNotifications();
            loadNotificationCount();
        });
    }

    // Carregar ao abrir dropdown
    document.getElementById('notificationsDropdown').addEventListener('shown.bs.dropdown', function() {
        loadNotifications();
        loadRealtimeNotifications();
    });

    // Atualizar contador a cada 30 segundos
    setInterval(loadNotificationCount, 30000);
    loadNotificationCount();
    
    // Listener para notificações do sistema de tempo real
    window.addEventListener('realtime-notification', function(e) {
        loadNotificationCount();
        loadRealtimeNotifications();
    });
</script>