/**
 * ML Advanced Dashboard JavaScript
 * 
 * Features:
 * - Real-time service status monitoring
 * - Unified service execution
 * - Interactive charts
 * - AI insights display
 * - Performance metrics
 */
class MLAdvancedDashboard {
    constructor() {
        this.config = {
            apiEndpoint: '/api/ml-advanced',
            refreshInterval: 10000, // 10 seconds
            chartUpdateInterval: 30000 // 30 seconds
        };
        
        this.services = {
            ads: { status: 'active', lastRun: Date.now(), metrics: {} },
            qa: { status: 'active', lastRun: Date.now(), metrics: {} },
            pricing: { status: 'active', lastRun: Date.now(), metrics: {} },
            competitor: { status: 'active', lastRun: Date.now(), metrics: {} },
            analytics: { status: 'active', lastRun: Date.now(), metrics: {} }
        };
        
        this.charts = {};
        this.insights = [];
        
        this.init();
    }

    /**
     * Initialize dashboard
     */
    init() {
        this.setupEventListeners();
        this.initializeCharts();
        this.loadInitialData();
        this.startRealTimeUpdates();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Service control buttons
        window.optimizeAds = () => this.showServiceConfig('ads');
        window.manageQA = () => this.showServiceConfig('qa');
        window.managePricing = () => this.showServiceConfig('pricing');
        window.manageCompetitors = () => this.showServiceConfig('competitor');
        window.viewAnalytics = () => this.showAnalyticsDashboard();
        window.executeAllServices = () => this.executeAllServices();
    }

    /**
     * Initialize charts
     */
    initializeCharts() {
        // Performance trends chart
        const perfCtx = document.getElementById('performanceChart');
        if (perfCtx) {
            this.charts.performance = new Chart(perfCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Revenue',
                            data: [],
                            borderColor: '#00b4d8',
                            backgroundColor: 'rgba(0, 180, 216, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Conversions',
                            data: [],
                            borderColor: '#00ff88',
                            backgroundColor: 'rgba(0, 255, 136, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: { color: '#ffffff' }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            ticks: { color: '#ffffff' }
                        },
                        y: {
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            ticks: { color: '#ffffff' }
                        }
                    }
                }
            });
        }

        // Service distribution chart
        const distCtx = document.getElementById('distributionChart');
        if (distCtx) {
            this.charts.distribution = new Chart(distCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['ML Ads', 'Smart Q&A', 'Dynamic Pricing', 'Competitor Intel', 'Analytics'],
                    datasets: [{
                        data: [25, 20, 20, 15, 20],
                        backgroundColor: [
                            '#667eea',
                            '#00b4d8',
                            '#f77f00',
                            '#7400b8',
                            '#00ff88'
                        ],
                        borderWidth: 2,
                        borderColor: '#1a1f3a'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: { color: '#ffffff' },
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }

    /**
     * Load initial data
     */
    async loadInitialData() {
        try {
            const response = await this.apiCall('/dashboard');
            
            if (response.success) {
                this.updateDashboardData(response.dashboard);
                this.updateServicesStatus(response.dashboard.services_status);
            }
        } catch (error) {
            console.error('Error loading initial data:', error);
        }
    }

    /**
     * Execute all services
     */
    async executeAllServices() {
        try {
            this.showLoadingOverlay('Executing all ML services...');
            
            const response = await this.apiCall('/execute-all', {
                method: 'POST',
                body: JSON.stringify({
                    execution_plan: 'comprehensive'
                })
            });
            
            if (response.success) {
                this.updateServicesStatus(response.services_executed);
                this.displayExecutionSummary(response.summary);
                this.updateCharts(response.results);
                this.displayInsights(response.unified_insights);
                
                this.showNotification('All ML services executed successfully!', 'success');
            } else {
                this.showNotification('Failed to execute services: ' + response.error, 'danger');
            }
        } catch (error) {
            console.error('Error executing services:', error);
            this.showNotification('Error executing services', 'danger');
        } finally {
            this.hideLoadingOverlay();
        }
    }

    /**
     * Update dashboard data
     */
    updateDashboardData(dashboard) {
        // Update overview metrics
        if (dashboard.overview) {
            this.updateMetrics(dashboard.overview);
        }
        
        // Update service status
        if (dashboard.services_status) {
            this.updateServicesStatus(dashboard.services_status);
        }
        
        // Update recent insights
        if (dashboard.recent_insights) {
            this.displayInsights(dashboard.recent_insights);
        }
    }

    /**
     * Update services status
     */
    updateServicesStatus(statuses) {
        Object.keys(statuses).forEach(service => {
            this.services[service] = {
                ...this.services[service],
                ...statuses[service],
                lastUpdated: Date.now()
            };
        });
        
        this.updateServiceStatusIndicators();
    }

    /**
     * Update service status indicators
     */
    updateServiceStatusIndicators() {
        Object.keys(this.services).forEach(service => {
            const indicator = document.querySelector(`.service-status:has(.fa-${this.getServiceIcon(service)}) .status-indicator`);
            const statusText = document.querySelector(`.service-status:has(.fa-${this.getServiceIcon(service)}) span:last-child`);
            
            if (indicator && statusText) {
                const status = this.services[service].status;
                
                indicator.className = 'status-indicator';
                if (status === 'active') {
                    indicator.classList.add('status-active');
                } else if (status === 'warning') {
                    indicator.classList.add('status-warning');
                } else {
                    indicator.classList.add('status-inactive');
                }
                
                statusText.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            }
        });
    }

    /**
     * Update metrics display
     */
    updateMetrics(overview) {
        // Update metric cards with animation
        if (overview.active_campaigns !== undefined) {
            this.animateValue('.metric-card:nth-child(1) .ai-metric', overview.active_campaigns);
        }
        
        if (overview.pending_questions !== undefined) {
            this.animateValue('.metric-card:nth-child(2) .ai-metric', overview.pending_questions);
        }
        
        if (overview.price_optimizations !== undefined) {
            this.animateValue('.metric-card:nth-child(3) .ai-metric', overview.price_optimizations);
        }
        
        if (overview.competitors_monitored !== undefined) {
            this.animateValue('.metric-card:nth-child(4) .ai-metric', overview.competitors_monitored);
        }
    }

    /**
     * Display insights
     */
    displayInsights(insights) {
        const container = document.getElementById('insightsContainer');
        if (!container) return;
        
        container.innerHTML = '';
        
        insights.forEach((insight, index) => {
            const insightElement = document.createElement('div');
            insightElement.className = 'insight-item';
            insightElement.style.opacity = '0';
            insightElement.innerHTML = `
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">${insight.service.toUpperCase()}</h6>
                        <p class="mb-0">${insight.insight}</p>
                    </div>
                    <div class="text-muted">
                        <small>Just now</small>
                    </div>
                </div>
            `;
            
            container.appendChild(insightElement);
            
            // Animate appearance
            setTimeout(() => {
                insightElement.style.opacity = '1';
                insightElement.style.transform = 'translateY(0)';
            }, index * 200);
        });
    }

    /**
     * Update charts with new data
     */
    updateCharts(results) {
        // Update performance chart
        if (this.charts.performance && results.ads && results.pricing) {
            const performanceData = this.generatePerformanceData(results);
            this.charts.performance.data.labels = performanceData.labels;
            this.charts.performance.data.datasets[0].data = performanceData.revenue;
            this.charts.performance.data.datasets[1].data = performanceData.conversions;
            this.charts.performance.update('none');
        }
        
        // Update distribution chart
        if (this.charts.distribution && results) {
            const distributionData = this.generateDistributionData(results);
            this.charts.distribution.data.datasets[0].data = distributionData.data;
            this.charts.distribution.update('none');
        }
    }

    /**
     * Generate performance data for charts
     */
    generatePerformanceData(results) {
        const now = new Date();
        const labels = [];
        const revenue = [];
        const conversions = [];
        
        // Generate last 7 days of data
        for (let i = 6; i >= 0; i--) {
            const date = new Date(now);
            date.setDate(date.getDate() - i);
            labels.push(date.toLocaleDateString());
            
            // Simulate data based on results
            revenue.push(Math.random() * 10000 + 5000);
            conversions.push(Math.floor(Math.random() * 100 + 50));
        }
        
        return { labels, revenue, conversions };
    }

    /**
     * Generate distribution data for charts
     */
    generateDistributionData(results) {
        const services = ['ads', 'qa', 'pricing', 'competitor', 'analytics'];
        const data = [];
        
        services.forEach(service => {
            const result = results[service];
            const value = result?.optimized_count || Math.floor(Math.random() * 100) || 20;
            data.push(value);
        });
        
        return { data };
    }

    /**
     * Display execution summary
     */
    displayExecutionSummary(summary) {
        const summaryContainer = document.getElementById('executionSummary');
        const summaryContent = document.getElementById('summaryContent');
        
        if (!summaryContainer || !summaryContent) return;
        
        summaryContent.innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <div class="impact-item">
                        <span class="metric-label">Services Executed</span>
                        <span class="metric-value">${summary.total_services}</span>
                    </div>
                    <div class="impact-item">
                        <span class="metric-label">Success Rate</span>
                        <span class="impact-positive">${summary.successful_services}/${summary.total_services}</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="impact-item">
                        <span class="metric-label">Revenue Increase</span>
                        <span class="impact-positive">${summary.estimated_impact.revenue_increase}</span>
                    </div>
                    <div class="impact-item">
                        <span class="metric-label">Cost Reduction</span>
                        <span class="impact-positive">${summary.estimated_impact.cost_reduction}</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="impact-item">
                        <span class="metric-label">Efficiency Gain</span>
                        <span class="impact-positive">${summary.estimated_impact.efficiency_gain}</span>
                    </div>
                    <div class="impact-item">
                        <span class="metric-label">Total Optimizations</span>
                        <span class="impact-positive">${summary.total_optimizations}</span>
                    </div>
                </div>
            </div>
        `;
        
        summaryContainer.style.display = 'block';
        summaryContainer.scrollIntoView({ behavior: 'smooth' });
    }

    /**
     * Show service configuration modal
     */
    showServiceConfig(service) {
        // Implementation would show configuration modal for specific service
        console.log(`Show configuration for ${service} service`);
        this.showNotification(`Opening ${service.toUpperCase()} configuration...`, 'info');
    }

    /**
     * Show analytics dashboard
     */
    showAnalyticsDashboard() {
        // Implementation would open comprehensive analytics view
        console.log('Opening analytics dashboard');
        this.showNotification('Opening analytics dashboard...', 'info');
    }

    /**
     * Start real-time updates
     */
    startRealTimeUpdates() {
        // Periodic data refresh
        setInterval(() => {
            this.refreshServiceStatus();
            this.refreshMetrics();
        }, this.config.refreshInterval);
        
        // Chart updates
        setInterval(() => {
            this.updateChartData();
        }, this.config.chartUpdateInterval);
    }

    /**
     * Refresh service status
     */
    async refreshServiceStatus() {
        try {
            const response = await this.apiCall('/system/status');
            if (response.success) {
                this.updateServicesStatus(response.status.services);
            }
        } catch (error) {
            console.error('Error refreshing service status:', error);
        }
    }

    /**
     * Refresh metrics
     */
    async refreshMetrics() {
        try {
            const response = await this.apiCall('/dashboard');
            if (response.success) {
                this.updateMetrics(response.dashboard.overview);
            }
        } catch (error) {
            console.error('Error refreshing metrics:', error);
        }
    }

    /**
     * Update chart data with simulated real-time values
     */
    updateChartData() {
        // Simulate real-time data updates
        if (this.charts.performance) {
            const data = this.charts.performance.data;
            
            // Shift and add new data
            data.labels.shift();
            data.datasets[0].data.shift();
            data.datasets[1].data.shift();
            
            const newRevenue = data.datasets[0].data[data.datasets[0].data.length - 1] * (1 + Math.random() * 0.1);
            const newConversions = data.datasets[1].data[data.datasets[1].data.length - 1] + Math.floor(Math.random() * 10);
            
            data.labels.push(new Date().toLocaleTimeString());
            data.datasets[0].data.push(newRevenue);
            data.datasets[1].data.push(newConversions);
            
            this.charts.performance.update('none');
        }
    }

    /**
     * Show loading overlay
     */
    showLoadingOverlay(message = 'Processing...') {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            const messageElement = overlay.querySelector('p');
            if (messageElement) {
                messageElement.textContent = message;
            }
            overlay.style.display = 'flex';
        }
    }

    /**
     * Hide loading overlay
     */
    hideLoadingOverlay() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 1001; min-width: 300px;';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => {
            document.body.removeChild(toast);
        });
    }

    /**
     * Animate value change
     */
    animateValue(selector, value) {
        const element = document.querySelector(selector);
        if (!element) return;
        
        const startValue = parseFloat(element.textContent) || 0;
        const duration = 1000;
        const startTime = performance.now();
        
        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const currentValue = startValue + (value - startValue) * easeOutQuart;
            
            element.textContent = Math.round(currentValue);
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        requestAnimationFrame(animate);
    }

    /**
     * Get service icon
     */
    getServiceIcon(service) {
        const icons = {
            ads: 'ad',
            qa: 'comments',
            pricing: 'chart-line',
            competitor: 'search',
            analytics: 'chart-bar'
        };
        
        return icons[service] || 'cog';
    }

    /**
     * API call helper
     */
    async apiCall(endpoint, options = {}) {
        const url = this.config.apiEndpoint + endpoint;
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        const finalOptions = { ...defaultOptions, ...options };
        
        const response = await fetch(url, finalOptions);
        return await response.json();
    }
}

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new MLAdvancedDashboard();
});