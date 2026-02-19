/**
 * Real-Time Notification Client
 * Connects to SSE endpoint and displays notifications
 */
class NotificationClient {
    constructor(options = {}) {
        this.eventSource = null;
        this.reconnectDelay = options.reconnectDelay || 3000;
        this.onNotification = options.onNotification || this.defaultHandler;
        this.onConnected = options.onConnected || (() => { });
        this.onError = options.onError || console.error;
        this.autoConnect = options.autoConnect !== false;

        if (this.autoConnect) {
            this.connect();
        }
    }

    connect() {
        if (this.eventSource) {
            this.disconnect();
        }

        console.log('[Notifications] Connecting to SSE stream...');

        this.eventSource = new EventSource('/api/notifications/stream');

        // Connection established
        this.eventSource.addEventListener('connected', (e) => {
            const data = JSON.parse(e.data);
            console.log('[Notifications] Connected:', data);
            this.onConnected(data);
        });

        // Order notifications
        this.eventSource.addEventListener('order.new', (e) => {
            const data = JSON.parse(e.data);
            this.onNotification('order', data);
            this.showToast(data.data);
        });

        // Question notifications
        this.eventSource.addEventListener('question.new', (e) => {
            const data = JSON.parse(e.data);
            this.onNotification('question', data);
            this.showToast(data.data);
        });

        // Price alerts
        this.eventSource.addEventListener('price.alert', (e) => {
            const data = JSON.parse(e.data);
            this.onNotification('price', data);
            this.showToast(data.data);
        });

        // Stock alerts
        this.eventSource.addEventListener('stock.low', (e) => {
            const data = JSON.parse(e.data);
            this.onNotification('stock', data);
            this.showToast(data.data);
        });

        // Test notifications
        this.eventSource.addEventListener('test', (e) => {
            const data = JSON.parse(e.data);
            this.onNotification('test', data);
            this.showToast(data.data);
        });

        // Error handling
        this.eventSource.onerror = (error) => {
            console.error('[Notifications] Connection error:', error);
            this.onError(error);

            // Auto-reconnect
            setTimeout(() => {
                console.log('[Notifications] Reconnecting...');
                this.connect();
            }, this.reconnectDelay);
        };
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
            console.log('[Notifications] Disconnected');
        }
    }

    defaultHandler(type, data) {
        console.log(`[Notification] ${type}:`, data);
    }

    showToast(notification) {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = 'notification-toast';
        toast.innerHTML = `
            <div class="notification-icon">${notification.icon || '🔔'}</div>
            <div class="notification-content">
                <div class="notification-title">${notification.title}</div>
                <div class="notification-message">${notification.message}</div>
            </div>
        `;

        // Add to page
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                max-width: 400px;
            `;
            document.body.appendChild(container);
        }

        container.appendChild(toast);

        // Play sound (optional)
        this.playSound();

        // Auto-remove after 5 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 5000);

        // Click to navigate
        if (notification.url) {
            toast.style.cursor = 'pointer';
            toast.addEventListener('click', () => {
                window.location.href = notification.url;
            });
        }
    }

    playSound() {
        // Optional: Play notification sound
        try {
            const audio = new Audio('/assets/sounds/notification.mp3');
            audio.volume = 0.3;
            audio.play().catch(() => { }); // Ignore if blocked
        } catch (e) {
            // Sound not available
        }
    }

    // Test notification
    static sendTest() {
        return fetch('/api/notifications/test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        }).then(r => r.json());
    }
}

// Auto-initialize if in dashboard
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        if (window.location.pathname.startsWith('/dashboard')) {
            window.notificationClient = new NotificationClient();
        }
    });
} else {
    if (window.location.pathname.startsWith('/dashboard')) {
        window.notificationClient = new NotificationClient();
    }
}
