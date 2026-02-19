/**
 * Real-Time Dashboard JavaScript
 * Handles WebSocket connections, real-time data updates, and dashboard interactions
 */

class RealTimeDashboard {
    constructor() {
        this.websocket = null;
        this.subscriptions = new Set();
        this.charts = {};
        this.metrics = {
            items: { current: 0, previous: 0, lastUpdate: null },
            views: { current: 0, previous: 0, lastUpdate: null },
            sales: { current: 0, previous: 0, lastUpdate: null },
            seo: { current: 0, previous: 0, lastUpdate: null }
        };
        
        this.config = {
            wsUrl: `ws://${window.location.hostname}:8080`,
            reconnectInterval: 5000,
            maxReconnectAttempts: 10,
            reconnectAttempts: 0,
            chartUpdateInterval: 5000
        };

        this.init();
    }

    /**
     * Initialize dashboard
     */
    init() {
        this.setupEventListeners();
        this.initCharts();
        this.connectWebSocket();
        this.startPeriodicUpdates();
        this.loadInitialData();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Subscription buttons
        document.querySelectorAll('.btn-subscribe').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const channel = e.target.dataset.channel;
                this.toggleSubscription(channel, e.target);
            });
        });

        // Refresh buttons
        document.getElementById('refreshItems').addEventListener('click', () => {
            this.refreshItems();
        });

        document.getElementById('refreshCompetitors').addEventListener('click', () => {
            this.refreshCompetitors();
        });

        // Window events
        window.addEventListener('beforeunload', () => {
            this.disconnect();
        });

        // Periodic chart updates
        setInterval(() => {
            this.updateCharts();
        }, this.config.chartUpdateInterval);
    }

    /**
     * Connect to WebSocket
     */
    connectWebSocket() {
        try {
            this.updateWebSocketStatus('connecting', 'Conectando...');
            
            this.websocket = new WebSocket(this.config.wsUrl);
            
            this.websocket.onopen = (event) => {
                console.log('WebSocket connected');
                this.updateWebSocketStatus('connected', 'Conectado');
                this.config.reconnectAttempts = 0;
                this.showNotification('Conectado ao servidor em tempo real', 'success');
                
                // Resubscribe to channels after reconnect
                this.subscriptions.forEach(channel => {
                    this.subscribeToChannel(channel);
                });
            };

            this.websocket.onmessage = (event) => {
                this.handleWebSocketMessage(event);
            };

            this.websocket.onclose = (event) => {
                console.log('WebSocket disconnected:', event.code, event.reason);
                this.updateWebSocketStatus('disconnected', 'Desconectado');
                
                if (this.config.reconnectAttempts < this.config.maxReconnectAttempts) {
                    this.config.reconnectAttempts++;
                    this.showNotification(
                        `Desconectado. Tentando reconectar (${this.config.reconnectAttempts}/${this.config.maxReconnectAttempts})...`, 
                        'warning'
                    );
                    
                    setTimeout(() => {
                        this.connectWebSocket();
                    }, this.config.reconnectInterval);
                } else {
                    this.showNotification('Falha ao reconectar. Por favor, recarregue a página.', 'danger');
                }
            };

            this.websocket.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.updateWebSocketStatus('disconnected', 'Erro de conexão');
                this.showNotification('Erro na conexão WebSocket', 'danger');
            };

        } catch (error) {
            console.error('Failed to create WebSocket connection:', error);
            this.updateWebSocketStatus('disconnected', 'Falha na conexão');
        }
    }

    /**
     * Handle WebSocket messages
     */
    handleWebSocketMessage(event) {
        try {
            const data = JSON.parse(event.data);
            console.log('WebSocket message received:', data);

            switch (data.type) {
                case 'connection':
                    console.log('Connection message:', data.message);
                    break;

                case 'subscription_confirmed':
                    console.log('Subscribed to:', data.channel);
                    this.updateSubscriptionButton(data.channel, true);
                    break;

                case 'unsubscription_confirmed':
                    console.log('Unsubscribed from:', data.channel);
                    this.updateSubscriptionButton(data.channel, false);
                    break;

                case 'broadcast':
                    this.handleBroadcastMessage(data);
                    break;

                case 'items_data':
                    this.updateItemsData(data.data);
                    break;

                case 'competitors_data':
                    this.updateCompetitorsData(data.data);
                    break;

                case 'seo_monitoring_data':
                    this.updateSEOMetrics(data.data);
                    break;

                case 'orders_data':
                    this.updateOrdersData(data.data);
                    break;

                case 'pong':
                    // Ping-pong response for connection health check
                    break;

                default:
                    console.log('Unknown message type:', data.type);
            }
        } catch (error) {
            console.error('Error parsing WebSocket message:', error);
        }
    }

    /**
     * Handle broadcast messages
     */
    handleBroadcastMessage(data) {
        const { channel, event, updates } = data.data;

        switch (channel) {
            case 'items':
                if (event === 'item_updates') {
                    this.handleItemUpdates(updates);
                }
                break;

            case 'competitors':
                if (event === 'price_changes') {
                    this.handlePriceChanges(updates);
                }
                break;

            case 'seo_monitoring':
                if (event === 'score_updates') {
                    this.handleSEOScoreUpdates(updates);
                }
                break;

            case 'orders':
                if (event === 'new_orders') {
                    this.handleNewOrders(updates);
                }
                break;
        }
    }

    /**
     * Toggle subscription to channel
     */
    toggleSubscription(channel, button) {
        if (this.subscriptions.has(channel)) {
            this.unsubscribeFromChannel(channel);
            button.classList.remove('active');
            button.textContent = 'Assinar';
        } else {
            this.subscribeToChannel(channel);
            button.classList.add('active');
            button.textContent = 'Remover';
        }
    }

    /**
     * Subscribe to channel
     */
    subscribeToChannel(channel) {
        if (!this.websocket || this.websocket.readyState !== WebSocket.OPEN) {
            this.showNotification('WebSocket não está conectado', 'warning');
            return;
        }

        const params = this.getChannelParams(channel);
        const message = {
            type: 'subscribe',
            channel: channel,
            params: params
        };

        this.websocket.send(JSON.stringify(message));
        this.subscriptions.add(channel);
    }

    /**
     * Unsubscribe from channel
     */
    unsubscribeFromChannel(channel) {
        if (!this.websocket || this.websocket.readyState !== WebSocket.OPEN) {
            return;
        }

        const message = {
            type: 'unsubscribe',
            channel: channel
        };

        this.websocket.send(JSON.stringify(message));
        this.subscriptions.delete(channel);
    }

    /**
     * Get channel parameters
     */
    getChannelParams(channel) {
        // These would be dynamically determined based on user context
        switch (channel) {
            case 'items':
                return {
                    account_id: this.getAccountId(),
                    limit: 20
                };
            case 'competitors':
                return {
                    keyword: 'celular',
                    category_id: 'MLB1055'
                };
            case 'seo_monitoring':
                return {
                    item_id: 'MLB123456789'
                };
            case 'orders':
                return {
                    account_id: this.getAccountId()
                };
            default:
                return {};
        }
    }

    /**
     * Get current account ID (would be determined from session/user context)
     */
    getAccountId() {
        return 1; // Placeholder
    }

    /**
     * Update subscription button state
     */
    updateSubscriptionButton(channel, isActive) {
        const button = document.querySelector(`[data-channel="${channel}"]`);
        if (button) {
            if (isActive) {
                button.classList.add('active');
                button.textContent = 'Remover';
            } else {
                button.classList.remove('active');
                button.textContent = 'Assinar';
            }
        }
    }

    /**
     * Update WebSocket status indicator
     */
    updateWebSocketStatus(status, text) {
        const statusElement = document.getElementById('websocketStatus');
        const statusText = document.getElementById('statusText');
        
        statusElement.className = `websocket-status ${status}`;
        statusText.textContent = text;
    }

    /**
     * Initialize charts
     */
    initCharts() {
        // Performance Chart
        const perfCtx = document.getElementById('performanceChart').getContext('2d');
        this.charts.performance = new Chart(perfCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Visualizações',
                    data: [],
                    borderColor: '#3483fa',
                    backgroundColor: 'rgba(52, 131, 250, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Vendas',
                    data: [],
                    borderColor: '#00a650',
                    backgroundColor: 'rgba(0, 165, 80, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#ffffff' }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#b0b0b0' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    },
                    y: {
                        ticks: { color: '#b0b0b0' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    }
                }
            }
        });

        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        this.charts.status = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Ativo', 'Pausado', 'Encerrado'],
                datasets: [{
                    data: [0, 0, 0],
                    backgroundColor: ['#00a650', '#ffa500', '#ff5252']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#ffffff' }
                    }
                }
            }
        });
    }

    /**
     * Update charts with new data
     */
    updateCharts() {
        const now = new Date();
        const timeLabel = now.toLocaleTimeString();

        // Update performance chart
        const perfChart = this.charts.performance;
        if (perfChart.data.labels.length > 10) {
            perfChart.data.labels.shift();
            perfChart.data.datasets[0].data.shift();
            perfChart.data.datasets[1].data.shift();
        }

        perfChart.data.labels.push(timeLabel);
        perfChart.data.datasets[0].data.push(this.metrics.views.current);
        perfChart.data.datasets[1].data.push(this.metrics.sales.current);
        perfChart.update('none');

        // Update status chart (this would be based on actual item status data)
        // For demo, using random data
        this.charts.status.data.datasets[0].data = [
            Math.floor(Math.random() * 50) + 20,
            Math.floor(Math.random() * 20) + 5,
            Math.floor(Math.random() * 10) + 2
        ];
        this.charts.status.update('none');
    }

    /**
     * Update items data
     */
    updateItemsData(data) {
        const tbody = document.getElementById('itemsTableBody');
        
        if (!data || !data.results) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center">Nenhum item encontrado</td></tr>';
            return;
        }

        tbody.innerHTML = '';
        
        data.results.slice(0, 10).forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.id}</td>
                <td title="${item.title}">${this.truncateText(item.title, 30)}</td>
                <td>R$ ${item.price?.toFixed(2) || '0.00'}</td>
                <td>
                    <span class="status-indicator status-online"></span>
                    ${item.status || 'Ativo'}
                </td>
            `;
            tbody.appendChild(row);
        });

        // Update metrics
        this.updateMetric('items', data.paging?.total || 0);
        this.updateLastUpdate('items');
    }

    /**
     * Update competitors data
     */
    updateCompetitorsData(data) {
        const tbody = document.getElementById('competitorsTableBody');
        
        if (!data || !data.top_performers) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center">Nenhum concorrente encontrado</td></tr>';
            return;
        }

        tbody.innerHTML = '';
        
        data.top_performers.slice(0, 5).forEach((item, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    #${index + 1} ${this.truncateText(item.title, 25)}
                </td>
                <td>R$ ${item.price?.toFixed(2) || '0.00'}</td>
                <td>${item.sold_quantity || 0}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-success" style="width: ${Math.min(100, (item.sold_quantity || 0) * 10)}%">
                            ${Math.min(100, (item.sold_quantity || 0) * 10)}%
                        </div>
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    /**
     * Update SEO metrics
     */
    updateSEOMetrics(data) {
        if (data.seo_score !== undefined) {
            this.updateMetric('seo', data.seo_score);
            this.updateLastUpdate('seo');
        }
    }

    /**
     * Update orders data
     */
    updateOrdersData(data) {
        if (data.total !== undefined) {
            this.updateMetric('sales', data.total);
            this.updateLastUpdate('sales');
        }
    }

    /**
     * Handle item updates
     */
    handleItemUpdates(updates) {
        updates.forEach(update => {
            this.showNotification(
                `Item ${update.id} atualizado: ${this.truncateText(update.title, 30)}`,
                'info'
            );
        });
        
        // Refresh items data if subscribed
        if (this.subscriptions.has('items')) {
            this.refreshItems();
        }
    }

    /**
     * Handle price changes
     */
    handlePriceChanges(changes) {
        changes.forEach(change => {
            const type = change.change_type === 'increase' ? 'success' : 'warning';
            const arrow = change.change_type === 'increase' ? '↑' : '↓';
            
            this.showNotification(
                `Preço alterado: Item ${change.item_id} ${arrow} ${change.change_percent}%`,
                type
            );
        });
        
        // Refresh competitors data if subscribed
        if (this.subscriptions.has('competitors')) {
            this.refreshCompetitors();
        }
    }

    /**
     * Handle SEO score updates
     */
    handleSEOScoreUpdates(updates) {
        Object.entries(updates).forEach(([itemId, score]) => {
            this.showNotification(
                `Score SEO atualizado: Item ${itemId} - ${score}/100`,
                'info'
            );
        });
    }

    /**
     * Handle new orders
     */
    handleNewOrders(notifications) {
        notifications.forEach(order => {
            this.showNotification(
                `Novo pedido: #${order.id} - R$ ${order.amount}`,
                'success'
            );
        });
        
        // Update sales metric
        if (notifications.length > 0) {
            this.metrics.sales.current += notifications.length;
            this.updateMetricDisplay('sales');
        }
    }

    /**
     * Update metric value
     */
    updateMetric(type, value) {
        const metric = this.metrics[type];
        metric.previous = metric.current;
        metric.current = value;
        metric.lastUpdate = new Date();
        
        this.updateMetricDisplay(type);
    }

    /**
     * Update metric display
     */
    updateMetricDisplay(type) {
        const metric = this.metrics[type];
        const element = document.getElementById(`${type === 'seo' ? 'avgSEOScore' : `total${type.charAt(0).toUpperCase() + type.slice(1)}`}`);
        const changeElement = document.getElementById(`${type}Change`);
        
        if (element) {
            if (type === 'seo') {
                element.textContent = metric.current + '/100';
            } else {
                element.textContent = this.formatNumber(metric.current);
            }
        }
        
        if (changeElement && metric.previous > 0) {
            const changePercent = ((metric.current - metric.previous) / metric.previous * 100).toFixed(1);
            const isPositive = changePercent >= 0;
            
            changeElement.className = `metric-change ${isPositive ? 'positive' : 'negative'}`;
            changeElement.innerHTML = `
                <i class="fas fa-arrow-${isPositive ? 'up' : 'down'}"></i> ${Math.abs(changePercent)}%
            `;
        }
    }

    /**
     * Update last update time
     */
    updateLastUpdate(type) {
        const element = document.getElementById(`${type}LastUpdate`);
        if (element) {
            const now = new Date();
            element.textContent = `Atualizado ${now.toLocaleTimeString()}`;
        }
    }

    /**
     * Refresh items data
     */
    refreshItems() {
        const btn = document.getElementById('refreshItems');
        btn.classList.add('spinning');
        
        // Send request via WebSocket
        if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
            this.websocket.send(JSON.stringify({
                type: 'get_item_updates',
                account_id: this.getAccountId()
            }));
        }
        
        setTimeout(() => {
            btn.classList.remove('spinning');
        }, 1000);
    }

    /**
     * Refresh competitors data
     */
    refreshCompetitors() {
        const btn = document.getElementById('refreshCompetitors');
        btn.classList.add('spinning');
        
        // Send request via WebSocket
        if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
            this.websocket.send(JSON.stringify({
                type: 'get_competitor_data',
                keyword: 'celular',
                category_id: 'MLB1055'
            }));
        }
        
        setTimeout(() => {
            btn.classList.remove('spinning');
        }, 1000);
    }

    /**
     * Load initial data
     */
    loadInitialData() {
        // Load some demo data
        this.updateMetric('items', 45);
        this.updateMetric('views', 1234);
        this.updateMetric('sales', 23);
        this.updateMetric('seo', 78);
        
        // Load demo table data
        this.updateItemsData({
            results: [
                { id: 'MLB123', title: 'Celular Smartphone Galaxy S21', price: 2499.99, status: 'Ativo' },
                { id: 'MLB456', title: 'iPhone 13 128GB', price: 3499.99, status: 'Ativo' },
                { id: 'MLB789', title: 'Xiaomi Redmi Note 11', price: 1299.99, status: 'Ativo' }
            ],
            paging: { total: 45 }
        });
        
        this.updateCompetitorsData({
            top_performers: [
                { title: 'Celular Galaxy A52', price: 1899.99, sold_quantity: 15 },
                { title: 'Motorola Moto G50', price: 1499.99, sold_quantity: 12 },
                { title: 'LG K51', price: 1199.99, sold_quantity: 8 }
            ]
        });
    }

    /**
     * Start periodic updates
     */
    startPeriodicUpdates() {
        // Send ping every 30 seconds to keep connection alive
        setInterval(() => {
            if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
                this.websocket.send(JSON.stringify({ type: 'ping' }));
            }
        }, 30000);
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        const container = document.getElementById('notificationContainer');
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show notification-toast`;
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        container.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    /**
     * Disconnect WebSocket
     */
    disconnect() {
        if (this.websocket) {
            this.websocket.close();
            this.websocket = null;
        }
    }

    /**
     * Format number with locale
     */
    formatNumber(num) {
        return new Intl.NumberFormat('pt-BR').format(num);
    }

    /**
     * Truncate text
     */
    truncateText(text, maxLength) {
        if (text.length <= maxLength) {
            return text;
        }
        return text.substring(0, maxLength) + '...';
    }
}

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new RealTimeDashboard();
});