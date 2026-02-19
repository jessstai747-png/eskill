/**
 * SEO Killer 2.0 - Advanced AI Dashboard
 * 
 * Features:
 * - Real-time AI metrics
 * - Predictive analytics visualization
 * - Autonomous optimization control
 * - Learning system monitoring
 * - Interactive strategy generation
 */
class SEOKiller2Dashboard {
    constructor() {
        this.config = {
            apiEndpoint: '/api/seo-killer-2',
            refreshInterval: 5000,
            animationDuration: 300
        };
        
        this.metrics = {
            confidence: 0,
            predictions: 0,
            learningRate: 0,
            successRate: 0
        };
        
        this.strategies = [];
        this.timeline = [];
        this.charts = {};
        
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
        this.initializeAIAnimations();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // AI Control buttons
        document.getElementById('executeLearningCycle')?.addEventListener('click', () => {
            this.executeLearningCycle();
        });

        document.getElementById('generatePredictions')?.addEventListener('click', () => {
            this.generatePredictions();
        });

        document.getElementById('optimizeAutonomously')?.addEventListener('click', () => {
            this.optimizeAutonomously();
        });

        document.getElementById('trainModels')?.addEventListener('click', () => {
            this.trainModels();
        });

        // Toggle switches
        document.querySelectorAll('.form-check-input').forEach(toggle => {
            toggle.addEventListener('change', (e) => {
                this.updateAIConfig(e.target.id, e.target.checked);
            });
        });

        // Periodic data refresh
        setInterval(() => {
            this.refreshDashboardData();
        }, this.config.refreshInterval);
    }

    /**
     * Initialize charts
     */
    initializeCharts() {
        // Prediction Chart
        const ctx = document.getElementById('predictionChart');
        if (ctx) {
            this.charts.prediction = new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Actual Performance',
                        data: [],
                        borderColor: '#00ff88',
                        backgroundColor: 'rgba(0, 255, 136, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'AI Prediction',
                        data: [],
                        borderColor: '#00b4d8',
                        backgroundColor: 'rgba(0, 180, 216, 0.1)',
                        borderDash: [5, 5],
                        tension: 0.4
                    }]
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
    }

    /**
     * Load initial dashboard data
     */
    async loadInitialData() {
        try {
            this.showLoadingOverlay();
            
            const response = await this.apiCall('/dashboard/initial-data');
            
            if (response.success) {
                this.updateMetrics(response.data.metrics);
                this.updateStrategies(response.data.strategies);
                this.updateTimeline(response.data.timeline);
                this.updatePredictionChart(response.data.predictions);
            }
            
        } catch (error) {
            console.error('Error loading initial data:', error);
            this.showNotification('Erro ao carregar dados iniciais', 'danger');
        } finally {
            this.hideLoadingOverlay();
        }
    }

    /**
     * Execute learning cycle
     */
    async executeLearningCycle() {
        try {
            this.showLoadingOverlay('Executing AI Learning Cycle...');
            
            const response = await this.apiCall('/autopilot/execute-learning', {
                method: 'POST',
                body: JSON.stringify({
                    adaptive_learning: true,
                    autonomous_testing: document.getElementById('abTestingEnabled').checked,
                    real_time_adjustment: document.getElementById('realTimeAdjustment').checked
                })
            });
            
            if (response.success) {
                this.showNotification('Learning cycle executed successfully', 'success');
                this.updateLearningResults(response.data);
                this.refreshDashboardData();
            } else {
                this.showNotification(response.error || 'Learning cycle failed', 'danger');
            }
            
        } catch (error) {
            console.error('Learning cycle error:', error);
            this.showNotification('Erro no ciclo de aprendizado', 'danger');
        } finally {
            this.hideLoadingOverlay();
        }
    }

    /**
     * Generate predictions
     */
    async generatePredictions() {
        try {
            this.showLoadingOverlay('Generating AI Predictions...');
            
            const response = await this.apiCall('/predictions/generate', {
                method: 'POST',
                body: JSON.stringify({
                    prediction_horizon: 30,
                    confidence_threshold: 0.8,
                    include_seasonal: true
                })
            });
            
            if (response.success) {
                this.showNotification(`Generated ${response.data.count} predictions`, 'success');
                this.updatePredictionChart(response.data.predictions);
                this.updateMetrics(response.data.metrics);
            } else {
                this.showNotification(response.error || 'Prediction generation failed', 'danger');
            }
            
        } catch (error) {
            console.error('Prediction generation error:', error);
            this.showNotification('Erro na geração de previsões', 'danger');
        } finally {
            this.hideLoadingOverlay();
        }
    }

    /**
     * Optimize autonomously
     */
    async optimizeAutonomously() {
        try {
            this.showLoadingOverlay('Executing Autonomous Optimization...');
            
            const response = await this.apiCall('/autopilot/optimize-autonomously', {
                method: 'POST',
                body: JSON.stringify({
                    learning_enabled: document.getElementById('learningEnabled').checked,
                    risk_tolerance: 'medium',
                    max_concurrent_tests: 5
                })
            });
            
            if (response.success) {
                this.showNotification(`Executed ${response.data.optimizations_count} autonomous optimizations`, 'success');
                this.updateStrategies(response.data.strategies);
                this.updateTimeline(response.data.timeline);
                this.animateOptimizationResults(response.data.results);
            } else {
                this.showNotification(response.error || 'Autonomous optimization failed', 'danger');
            }
            
        } catch (error) {
            console.error('Autonomous optimization error:', error);
            this.showNotification('Erro na otimização autônoma', 'danger');
        } finally {
            this.hideLoadingOverlay();
        }
    }

    /**
     * Train AI models
     */
    async trainModels() {
        try {
            this.showLoadingOverlay('Training AI Models...');
            
            const response = await this.apiCall('/ai/train-models', {
                method: 'POST',
                body: JSON.stringify({
                    training_days: 30,
                    model_types: ['performance', 'pricing', 'trends', 'seasonal'],
                    validation_split: 0.2
                })
            });
            
            if (response.success) {
                this.showNotification('AI Models trained successfully', 'success');
                this.updateModelMetrics(response.data.metrics);
                this.animateTrainingProgress(response.data.training_progress);
            } else {
                this.showNotification(response.error || 'Model training failed', 'danger');
            }
            
        } catch (error) {
            console.error('Model training error:', error);
            this.showNotification('Erro no treinamento dos modelos', 'danger');
        } finally {
            this.hideLoadingOverlay();
        }
    }

    /**
     * Update dashboard metrics with animation
     */
    updateMetrics(metrics) {
        // Animate metric updates
        this.animateValue('confidence', metrics.confidence || 0, '%');
        this.animateValue('predictions', metrics.predictions || 0, '');
        this.animateValue('learningRate', metrics.learningRate || 0, '');
        this.animateValue('successRate', metrics.successRate || 0, '%');
        
        // Update progress bars
        this.updateProgressBar('confidence', metrics.confidence || 0);
        
        this.metrics = { ...this.metrics, ...metrics };
    }

    /**
     * Update AI strategies
     */
    updateStrategies(strategies) {
        const container = document.getElementById('strategiesContainer');
        if (!container) return;
        
        container.innerHTML = '';
        
        strategies.forEach((strategy, index) => {
            const strategyCard = this.createStrategyCard(strategy, index);
            container.appendChild(strategyCard);
            
            // Animate card appearance
            setTimeout(() => {
                strategyCard.style.opacity = '1';
                strategyCard.style.transform = 'translateX(0)';
            }, index * 100);
        });
        
        this.strategies = strategies;
    }

    /**
     * Create strategy card element
     */
    createStrategyCard(strategy, index) {
        const card = document.createElement('div');
        card.className = 'strategy-card';
        card.style.opacity = '0';
        card.style.transform = 'translateX(-20px)';
        card.style.transition = 'all 0.5s ease';
        
        card.innerHTML = `
            <div class="strategy-score">${strategy.confidence}%</div>
            <h6 class="mb-2">${strategy.title}</h6>
            <p class="text-muted small mb-2">${strategy.description}</p>
            <div class="d-flex justify-content-between align-items-center">
                <span class="badge bg-primary">${strategy.type}</span>
                <small class="text-muted">Impact: ${strategy.expected_impact}</small>
            </div>
        `;
        
        card.addEventListener('click', () => {
            this.showStrategyDetails(strategy);
        });
        
        return card;
    }

    /**
     * Update learning timeline
     */
    updateTimeline(timeline) {
        const container = document.getElementById('learningTimeline');
        if (!container) return;
        
        container.innerHTML = '';
        
        timeline.forEach((event, index) => {
            const timelineItem = this.createTimelineItem(event, index);
            container.appendChild(timelineItem);
        });
        
        this.timeline = timeline;
    }

    /**
     * Create timeline item
     */
    createTimelineItem(event, index) {
        const item = document.createElement('div');
        item.className = 'timeline-item';
        
        item.innerHTML = `
            <div class="mb-2">
                <h6 class="mb-1">${event.title}</h6>
                <small class="text-muted">${new Date(event.timestamp * 1000).toLocaleString()}</small>
            </div>
            <p class="mb-0">${event.description}</p>
            ${event.metrics ? `<div class="mt-2"><small class="text-info">Results: ${JSON.stringify(event.metrics)}</small></div>` : ''}
        `;
        
        return item;
    }

    /**
     * Update prediction chart
     */
    updatePredictionChart(predictions) {
        if (!this.charts.prediction) return;
        
        const chart = this.charts.prediction;
        
        // Add new data points
        predictions.forEach(pred => {
            chart.data.labels.push(new Date(pred.date).toLocaleDateString());
            chart.data.datasets[0].data.push(pred.actual);
            chart.data.datasets[1].data.push(pred.predicted);
        });
        
        // Keep only last 20 data points
        if (chart.data.labels.length > 20) {
            chart.data.labels.shift();
            chart.data.datasets[0].data.shift();
            chart.data.datasets[1].data.shift();
        }
        
        chart.update('none');
    }

    /**
     * Update learning results
     */
    updateLearningResults(results) {
        // Update learning timeline with new results
        const newEvent = {
            title: 'Learning Cycle Completed',
            description: `Processed ${results.items_processed} items with ${results.improvements} improvements`,
            timestamp: Math.floor(Date.now() / 1000),
            metrics: results
        };
        
        this.timeline.unshift(newEvent);
        this.updateTimeline(this.timeline);
        
        // Update confidence based on results
        if (results.accuracy !== undefined) {
            this.animateValue('confidence', results.accuracy, '%');
        }
    }

    /**
     * Animate numerical value change
     */
    animateValue(elementId, targetValue, suffix = '') {
        const element = document.querySelector(`[data-metric="${elementId}"]`) || 
                       document.getElementById(elementId);
        
        if (!element) return;
        
        const startValue = parseFloat(element.textContent) || 0;
        const duration = 1000;
        const startTime = performance.now();
        
        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const currentValue = startValue + (targetValue - startValue) * easeOutQuart;
            
            element.textContent = currentValue.toFixed(1) + suffix;
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        requestAnimationFrame(animate);
    }

    /**
     * Update progress bar
     */
    updateProgressBar(metric, value) {
        const progressBar = document.querySelector(`.progress-bar[aria-label="${metric}"]`);
        if (progressBar) {
            progressBar.style.width = `${value}%`;
            progressBar.setAttribute('aria-valuenow', value);
        }
    }

    /**
     * Show strategy details modal
     */
    showStrategyDetails(strategy) {
        // Create modal dynamically
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content bg-dark text-light">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">${strategy.title}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Strategy Details</h6>
                                <p>${strategy.description}</p>
                                <p><strong>Type:</strong> ${strategy.type}</p>
                                <p><strong>Confidence:</strong> ${strategy.confidence}%</p>
                            </div>
                            <div class="col-md-6">
                                <h6>Expected Impact</h6>
                                <p>${strategy.expected_impact}</p>
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-success" style="width: ${strategy.confidence}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="dashboard.executeStrategy('${strategy.id}')">Execute Strategy</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        modal.addEventListener('hidden.bs.modal', () => {
            document.body.removeChild(modal);
        });
    }

    /**
     * Execute specific strategy
     */
    async executeStrategy(strategyId) {
        try {
            this.showLoadingOverlay('Executing Strategy...');
            
            const response = await this.apiCall('/strategies/execute', {
                method: 'POST',
                body: JSON.stringify({ strategy_id: strategyId })
            });
            
            if (response.success) {
                this.showNotification('Strategy executed successfully', 'success');
                this.refreshDashboardData();
            } else {
                this.showNotification(response.error || 'Strategy execution failed', 'danger');
            }
            
        } catch (error) {
            console.error('Strategy execution error:', error);
            this.showNotification('Erro na execução da estratégia', 'danger');
        } finally {
            this.hideLoadingOverlay();
        }
    }

    /**
     * Show loading overlay
     */
    showLoadingOverlay(message = 'Processing...') {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'flex';
            const messageElement = overlay.querySelector('p');
            if (messageElement) {
                messageElement.textContent = message;
            }
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
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        // Add to container
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(container);
        }
        
        container.appendChild(toast);
        
        // Show toast
        const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
        bsToast.show();
        
        // Remove after hidden
        toast.addEventListener('hidden.bs.toast', () => {
            container.removeChild(toast);
        });
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

    /**
     * Refresh dashboard data
     */
    async refreshDashboardData() {
        try {
            const response = await this.apiCall('/dashboard/refresh');
            if (response.success) {
                this.updateMetrics(response.data.metrics);
                // Update other components as needed
            }
        } catch (error) {
            console.error('Error refreshing dashboard:', error);
        }
    }

    /**
     * Start real-time updates
     */
    startRealTimeUpdates() {
        // Initialize WebSocket connection for real-time updates
        this.initializeWebSocket();
    }

    /**
     * Initialize WebSocket for real-time updates
     */
    initializeWebSocket() {
        try {
            const ws = new WebSocket(`ws://${window.location.hostname}:8080`);
            
            ws.onopen = () => {
                console.log('SEO Killer 2.0 WebSocket connected');
                ws.send(JSON.stringify({
                    type: 'subscribe',
                    channel: 'seo_killer_updates'
                }));
            };
            
            ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                this.handleRealTimeUpdate(data);
            };
            
            ws.onclose = () => {
                console.log('WebSocket disconnected, attempting reconnect...');
                setTimeout(() => this.initializeWebSocket(), 5000);
            };
            
        } catch (error) {
            console.error('WebSocket initialization failed:', error);
        }
    }

    /**
     * Handle real-time updates
     */
    handleRealTimeUpdate(data) {
        switch (data.type) {
            case 'metrics_update':
                this.updateMetrics(data.metrics);
                break;
            case 'strategy_completed':
                this.refreshDashboardData();
                this.showNotification('Strategy completed successfully', 'success');
                break;
            case 'learning_completed':
                this.updateLearningResults(data.results);
                break;
        }
    }

    /**
     * Update AI configuration
     */
    async updateAIConfig(configKey, value) {
        try {
            await this.apiCall('/config/update', {
                method: 'POST',
                body: JSON.stringify({ [configKey]: value })
            });
        } catch (error) {
            console.error('Config update error:', error);
        }
    }

    /**
     * Initialize AI animations
     */
    initializeAIAnimations() {
        // Add floating animation to AI elements
        const aiElements = document.querySelectorAll('.ai-metric, .learning-indicator');
        aiElements.forEach(element => {
            element.style.animation = 'float 3s ease-in-out infinite';
        });
    }

    /**
     * Animate optimization results
     */
    animateOptimizationResults(results) {
        results.forEach((result, index) => {
            setTimeout(() => {
                this.showNotification(
                    `Optimization ${result.id} completed: ${result.impact}% improvement`,
                    'success'
                );
            }, index * 1000);
        });
    }

    /**
     * Animate training progress
     */
    animateTrainingProgress(progress) {
        const steps = 10;
        const stepDuration = progress.duration / steps;
        
        for (let i = 0; i <= steps; i++) {
            setTimeout(() => {
                const percentage = (i / steps) * 100;
                this.updateProgressBar('training', percentage);
                
                if (i === steps) {
                    this.showNotification('AI Models training completed', 'success');
                }
            }, i * stepDuration);
        }
    }

    /**
     * Update model metrics
     */
    updateModelMetrics(metrics) {
        // Update model-specific metrics
        if (metrics.accuracy) {
            this.animateValue('modelAccuracy', metrics.accuracy, '%');
        }
        
        if (metrics.loss) {
            this.animateValue('modelLoss', metrics.loss, '');
        }
    }
}

// Global functions for onclick handlers
window.executeLearningCycle = () => window.dashboard.executeLearningCycle();
window.generatePredictions = () => window.dashboard.generatePredictions();
window.optimizeAutonomously = () => window.dashboard.optimizeAutonomously();
window.trainModels = () => window.dashboard.trainModels();

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new SEOKiller2Dashboard();
});