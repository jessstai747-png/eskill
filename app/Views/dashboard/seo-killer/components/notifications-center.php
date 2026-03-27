<!-- Real-time Notifications Component -->
<div id="notificationsCenter" class="notifications-center">
    <!-- Notification Bell -->
    <div class="notification-bell" onclick="toggleNotificationsPanel()">
        <i class="bi bi-bell-fill"></i>
        <span class="notification-count" id="notificationCount" style="display: none;">0</span>
    </div>

    <!-- Notifications Panel -->
    <div id="notificationsPanel" class="notifications-panel" style="display: none;">
        <div class="notifications-header">
            <h6 class="mb-0">
                <i class="bi bi-bell me-2"></i>
                Notificações
            </h6>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-link text-white p-0" onclick="markAllAsRead()" title="Marcar todas como lidas">
                    <i class="bi bi-check2-all"></i>
                </button>
                <button class="btn btn-sm btn-link text-white p-0" onclick="toggleNotificationsPanel()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="notifications-tabs">
            <button class="notification-tab active" data-filter="all" onclick="filterNotifications('all')">
                Todas
            </button>
            <button class="notification-tab" data-filter="alerts" onclick="filterNotifications('alerts')">
                Alertas
            </button>
            <button class="notification-tab" data-filter="updates" onclick="filterNotifications('updates')">
                Atualizações
            </button>
        </div>

        <!-- Notifications List -->
        <div id="notificationsList" class="notifications-list">
            <div class="notification-empty">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="text-muted mt-2">Nenhuma notificação</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="notifications-footer">
            <button class="btn btn-sm btn-outline-primary w-100" onclick="loadMoreNotifications()">
                Ver Histórico Completo
            </button>
        </div>
    </div>
</div>

<!-- Toast Container for Real-time Notifications -->
<div id="realtimeToastContainer" class="realtime-toast-container"></div>

<style>
    .notifications-center {
        position: fixed;
        top: 20px;
        right: 80px;
        z-index: 1050;
    }

    .notification-bell {
        position: relative;
        width: 44px;
        height: 44px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .notification-bell:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }

    .notification-bell i {
        font-size: 1.25rem;
        color: #2f3542;
    }

    .notification-bell.has-notifications i {
        animation: bellRing 0.5s ease;
    }

    @keyframes bellRing {

        0%,
        100% {
            transform: rotate(0);
        }

        25% {
            transform: rotate(15deg);
        }

        50% {
            transform: rotate(-15deg);
        }

        75% {
            transform: rotate(10deg);
        }
    }

    .notification-count {
        position: absolute;
        top: -5px;
        right: -5px;
        background: linear-gradient(135deg, #ff4757, #ff6b81);
        color: white;
        font-size: 0.7rem;
        font-weight: 700;
        min-width: 20px;
        height: 20px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 5px;
        box-shadow: 0 2px 8px rgba(255, 71, 87, 0.4);
    }

    .notifications-panel {
        position: absolute;
        top: 55px;
        right: 0;
        width: 380px;
        max-height: 500px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        overflow: hidden;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notifications-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: linear-gradient(135deg, #2f3542, #1e272e);
        color: white;
    }

    .notifications-tabs {
        display: flex;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }

    .notification-tab {
        flex: 1;
        padding: 0.75rem;
        border: none;
        background: none;
        color: #666;
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .notification-tab.active {
        color: #ff4757;
        border-bottom: 2px solid #ff4757;
        background: white;
    }

    .notification-tab:hover:not(.active) {
        background: #e9ecef;
    }

    .notifications-list {
        max-height: 350px;
        overflow-y: auto;
    }

    .notification-item {
        display: flex;
        gap: 0.75rem;
        padding: 1rem;
        border-bottom: 1px solid #f1f2f6;
        cursor: pointer;
        transition: background 0.2s;
    }

    .notification-item:hover {
        background: #f8f9fa;
    }

    .notification-item.unread {
        background: #fff9e6;
        border-left: 3px solid #ffa502;
    }

    .notification-item.alert {
        border-left: 3px solid #ff4757;
    }

    .notification-item.success {
        border-left: 3px solid #2ed573;
    }

    .notification-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .notification-icon.alert {
        background: rgba(255, 71, 87, 0.1);
        color: #ff4757;
    }

    .notification-icon.success {
        background: rgba(46, 213, 115, 0.1);
        color: #2ed573;
    }

    .notification-icon.info {
        background: rgba(52, 152, 219, 0.1);
        color: #3498db;
    }

    .notification-icon.warning {
        background: rgba(255, 165, 2, 0.1);
        color: #ffa502;
    }

    .notification-content {
        flex: 1;
        min-width: 0;
    }

    .notification-title {
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 0.25rem;
        color: #2f3542;
    }

    .notification-message {
        font-size: 0.8rem;
        color: #666;
        margin-bottom: 0.25rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .notification-time {
        font-size: 0.7rem;
        color: #999;
    }

    .notification-empty {
        text-align: center;
        padding: 3rem 1rem;
    }

    .notifications-footer {
        padding: 0.75rem;
        border-top: 1px solid #e9ecef;
    }

    /* Real-time Toast Notifications */
    .realtime-toast-container {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 1060;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        max-width: 350px;
    }

    .realtime-toast {
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        padding: 1rem;
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
        animation: toastSlideIn 0.3s ease;
        border-left: 4px solid #3498db;
    }

    .realtime-toast.alert {
        border-left-color: #ff4757;
    }

    .realtime-toast.success {
        border-left-color: #2ed573;
    }

    .realtime-toast.warning {
        border-left-color: #ffa502;
    }

    @keyframes toastSlideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .realtime-toast .toast-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .realtime-toast .toast-content {
        flex: 1;
    }

    .realtime-toast .toast-title {
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 0.25rem;
    }

    .realtime-toast .toast-message {
        font-size: 0.8rem;
        color: #666;
    }

    .realtime-toast .toast-close {
        background: none;
        border: none;
        color: #999;
        cursor: pointer;
        padding: 0.25rem;
    }

    .realtime-toast .toast-close:hover {
        color: #333;
    }
</style>

<script nonce="<?= CSP_NONCE ?>">
    // Notifications State
    let notificationsState = {
        isOpen: false,
        notifications: [],
        unreadCount: 0,
        currentFilter: 'all',
        lastFetch: null,
        pollingInterval: null
    };

    // Initialize Notifications
    document.addEventListener('DOMContentLoaded', function() {
        loadNotifications();
        startPolling();
    });

    // Toggle Panel
    function toggleNotificationsPanel() {
        const panel = document.getElementById('notificationsPanel');
        const bell = document.querySelector('.notification-bell');

        notificationsState.isOpen = !notificationsState.isOpen;

        if (notificationsState.isOpen) {
            panel.style.display = 'block';
            bell.classList.remove('has-notifications');
        } else {
            panel.style.display = 'none';
        }
    }

    // Load Notifications
    async function loadNotifications() {
        try {
            const data = await requestJson('/api/seo-killer/alerts');

            if (data.success && data.alerts) {
                notificationsState.notifications = data.alerts.map(alert => ({
                    id: alert.id,
                    type: getAlertType(alert.type),
                    title: alert.title || 'Alerta',
                    message: alert.message,
                    read: alert.read || false,
                    timestamp: alert.created_at,
                    data: alert.data
                }));

                updateUnreadCount();
                renderNotifications();
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    // Get alert type mapping
    function getAlertType(type) {
        const typeMap = {
            'price_drop': 'alert',
            'price_increase': 'warning',
            'stock_out': 'alert',
            'competitor_change': 'info',
            'optimization_complete': 'success',
            'seo_improvement': 'success'
        };
        return typeMap[type] || 'info';
    }

    // Update Unread Count
    function updateUnreadCount() {
        const unread = notificationsState.notifications.filter(n => !n.read).length;
        notificationsState.unreadCount = unread;

        const countBadge = document.getElementById('notificationCount');
        const bell = document.querySelector('.notification-bell');

        if (unread > 0) {
            countBadge.textContent = unread > 99 ? '99+' : unread;
            countBadge.style.display = 'flex';
            bell.classList.add('has-notifications');
        } else {
            countBadge.style.display = 'none';
            bell.classList.remove('has-notifications');
        }
    }

    // Render Notifications
    function renderNotifications() {
        const container = document.getElementById('notificationsList');
        let notifications = notificationsState.notifications;

        // Apply filter
        if (notificationsState.currentFilter !== 'all') {
            notifications = notifications.filter(n => {
                if (notificationsState.currentFilter === 'alerts') {
                    return n.type === 'alert' || n.type === 'warning';
                }
                return n.type === 'success' || n.type === 'info';
            });
        }

        if (notifications.length === 0) {
            container.innerHTML = `
            <div class="notification-empty">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="text-muted mt-2">Nenhuma notificação</p>
            </div>
        `;
            return;
        }

        container.innerHTML = notifications.map(n => `
        <div class="notification-item ${n.read ? '' : 'unread'} ${n.type}"
             onclick="handleNotificationClick('${n.id}')" data-id="${n.id}">
            <div class="notification-icon ${n.type}">
                <i class="bi ${getNotificationIcon(n.type)}"></i>
            </div>
            <div class="notification-content">
                <div class="notification-title">${escapeHtml(n.title)}</div>
                <div class="notification-message">${escapeHtml(n.message)}</div>
                <div class="notification-time">${formatTimeAgo(n.timestamp)}</div>
            </div>
        </div>
    `).join('');
    }

    // Get icon for notification type
    function getNotificationIcon(type) {
        const icons = {
            'alert': 'bi-exclamation-triangle-fill',
            'success': 'bi-check-circle-fill',
            'warning': 'bi-exclamation-circle-fill',
            'info': 'bi-info-circle-fill'
        };
        return icons[type] || 'bi-bell-fill';
    }

    // Filter Notifications
    function filterNotifications(filter) {
        notificationsState.currentFilter = filter;

        // Update tab UI
        document.querySelectorAll('.notification-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.filter === filter);
        });

        renderNotifications();
    }

    // Handle Notification Click
    async function handleNotificationClick(id) {
        const notification = notificationsState.notifications.find(n => n.id === id);
        if (!notification) return;

        // Mark as read
        if (!notification.read) {
            await markAsRead(id);
        }

        // Handle action based on notification data
        if (notification.data) {
            if (notification.data.item_id) {
                SEOKiller.openTitleGenerator(notification.data.item_id);
            } else if (notification.data.competitor_id) {
                // Navigate to competitor spy
                document.getElementById('competitor-spy-tab')?.click();
            }
        }

        toggleNotificationsPanel();
    }

    // Mark Single as Read
    async function markAsRead(id) {
        try {
            await requestJson(`/api/seo-killer/alerts/${id}/read`, {
                method: 'POST'
            });

            const notification = notificationsState.notifications.find(n => n.id === id);
            if (notification) {
                notification.read = true;
                updateUnreadCount();
                renderNotifications();
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    // Mark All as Read
    async function markAllAsRead() {
        const unread = notificationsState.notifications.filter(n => !n.read);

        for (const notification of unread) {
            await markAsRead(notification.id);
        }
    }

    // Load More Notifications (History)
    function loadMoreNotifications() {
        // Navigate to alerts tab in competitor spy
        toggleNotificationsPanel();
        document.getElementById('competitor-spy-tab')?.click();

        // After tab switch, activate alerts sub-tab
        setTimeout(() => {
            document.querySelector('[data-bs-target="#spy-alerts"]')?.click();
        }, 300);
    }

    // Start Polling for New Notifications
    function startPolling() {
        // Poll every 30 seconds
        notificationsState.pollingInterval = setInterval(() => {
            checkForNewNotifications();
        }, 30000);
    }

    // Check for New Notifications
    async function checkForNewNotifications() {
        try {
            const data = await requestJson('/api/seo-killer/alerts?unread=true');

            if (data.success && data.alerts) {
                const newAlerts = data.alerts.filter(alert => {
                    return !notificationsState.notifications.some(n => n.id === alert.id);
                });

                // Show toast for new alerts
                newAlerts.forEach(alert => {
                    showRealtimeToast({
                        type: getAlertType(alert.type),
                        title: alert.title || 'Nova Notificação',
                        message: alert.message
                    });
                });

                if (newAlerts.length > 0) {
                    loadNotifications();
                }
            }
        } catch (error) {
            console.error('Error checking for new notifications:', error);
        }
    }

    // Show Real-time Toast
    function showRealtimeToast(options) {
        const container = document.getElementById('realtimeToastContainer');

        const toast = document.createElement('div');
        toast.className = `realtime-toast ${options.type || 'info'}`;
        toast.innerHTML = `
        <div class="toast-icon ${options.type || 'info'}">
            <i class="bi ${getNotificationIcon(options.type || 'info')}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${escapeHtml(options.title)}</div>
            <div class="toast-message">${escapeHtml(options.message)}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="bi bi-x"></i>
        </button>
    `;

        container.appendChild(toast);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            toast.style.animation = 'toastSlideIn 0.3s ease reverse';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // Format Time Ago
    function formatTimeAgo(timestamp) {
        const now = new Date();
        const date = new Date(timestamp);
        const diff = Math.floor((now - date) / 1000);

        if (diff < 60) return 'Agora mesmo';
        if (diff < 3600) return `${Math.floor(diff / 60)}min atrás`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h atrás`;
        if (diff < 604800) return `${Math.floor(diff / 86400)}d atrás`;

        return date.toLocaleDateString('pt-BR');
    }

    // Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Close panel when clicking outside
    document.addEventListener('click', function(e) {
        const panel = document.getElementById('notificationsPanel');
        const bell = document.querySelector('.notification-bell');

        if (notificationsState.isOpen &&
            !panel.contains(e.target) &&
            !bell.contains(e.target)) {
            toggleNotificationsPanel();
        }
    });
</script>
