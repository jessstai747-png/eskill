/**
 * SEO Dashboard JavaScript
 * Integração com APIs de otimização SEO
 */

class SEODashboard {
    constructor() {
        this.apiBase = '/api/seo';
        this.currentAccountId = null;
        this.charts = {};
        this.init();
    }

    async init() {
        this.loadUserInfo();
        this.setupEventListeners();
        await this.loadMetrics();
        this.setupRealTimeUpdates();
    }

    setupEventListeners() {
        // Character counter for title
        document.getElementById('titleInput')?.addEventListener('input', (e) => {
            const count = e.target.value.length;
            document.getElementById('charCount').textContent = count;
            
            const progress = Math.min((count / 60) * 100, 100);
            document.getElementById('charProgress').style.width = progress + '%';
            
            // Color coding
            const progressBar = document.getElementById('charProgress');
            if (count >= 45 && count <= 60) {
                progressBar.className = 'progress-bar bg-success';
            } else if (count < 45 || count > 65) {
                progressBar.className = 'progress-bar bg-danger';
            } else {
                progressBar.className = 'progress-bar bg-warning';
            }
        });

        // Tab change events
        document.querySelectorAll('#seoTabs button').forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => {
                this.onTabChange(e.target.getAttribute('data-bs-target'));
            });
        });
    }

    async requestJson(url, options = {}) {
        if (window.ApiClient && typeof window.ApiClient.json === 'function') {
            return window.ApiClient.json(url, options);
        }

        const response = await fetch(url, options);
        const data = await response.json();
        return { response, data };
    }

    async loadUserInfo() {
        try {
            const { data } = await this.requestJson('/api/user/info');
            if (data.success) {
                this.currentAccountId = data.account_id;
                document.getElementById('userAccount').textContent = data.account_name || 'Conta Principal';
            }
        } catch (error) {
            console.error('Error loading user info:', error);
            document.getElementById('userAccount').textContent = 'Erro ao carregar';
        }
    }

    async loadMetrics() {
        try {
            const { data } = await this.requestJson(`${this.apiBase}/metrics`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (data.success) {
                this.updateMetricsDashboard(data.data);
            }
        } catch (error) {
            console.error('Error loading metrics:', error);
        }
    }

    updateMetricsDashboard(metrics) {
        // Update metric cards with animation
        this.animateValue('optimizationScore', 0, metrics.optimization_score.average_score, 1000);
        this.animateValue('keywordCount', 0, metrics.keyword_performance.total_keywords, 1000);
        this.animateValue('gapReduction', 0, metrics.keyword_performance.keyword_gap_reduced, 1000, '%');
        this.animateValue('roiImpact', 0, metrics.roi_metrics.roi_percentage, 1000, '%');

        // Update progress bar
        const scoreProgress = document.getElementById('scoreProgress');
        if (scoreProgress) {
            scoreProgress.style.width = metrics.optimization_score.average_score + '%';
            scoreProgress.className = `progress-bar ${this.getProgressBarClass(metrics.optimization_score.average_score)}`;
        }
    }

    animateValue(elementId, start, end, duration, suffix = '') {
        const element = document.getElementById(elementId);
        if (!element) return;

        const startTime = performance.now();
        
        const updateValue = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const value = start + (end - start) * this.easeOutQuad(progress);
            element.textContent = Math.round(value) + suffix;
            
            if (progress < 1) {
                requestAnimationFrame(updateValue);
            }
        };
        
        requestAnimationFrame(updateValue);
    }

    easeOutQuad(t) {
        return t * (2 - t);
    }

    getProgressBarClass(score) {
        if (score >= 75) return 'bg-success';
        if (score >= 50) return 'bg-warning';
        return 'bg-danger';
    }

    showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }

    hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }

    showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show alert-seo`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('.container-fluid');
        container.insertBefore(alertDiv, container.firstChild);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }

    setupRealTimeUpdates() {
        // Update metrics every 30 seconds
        setInterval(() => {
            this.loadMetrics();
        }, 30000);
    }

    onTabChange(target) {
        // Load tab-specific data when switching tabs
        switch(target) {
            case '#monitoring':
                this.loadMonitoringData();
                break;
            case '#analysis':
                this.loadAnalysisData();
                break;
        }
    }

    async loadMonitoringData() {
        try {
            const { data } = await this.requestJson(`${this.apiBase}/monitor-optimization`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_ids: ['MLB1234567890', 'MLB0987654321']
                })
            });

            if (data.success) {
                this.updateMonitoringCharts(data.data);
            }
        } catch (error) {
            console.error('Error loading monitoring data:', error);
        }
    }

    updateMonitoringCharts(data) {
        const ctx = document.getElementById('monitoringChart');
        if (!ctx) return;

        if (this.charts.monitoring) {
            this.charts.monitoring.destroy();
        }

        this.charts.monitoring = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Semana 1', 'Semana 2', 'Semana 3', 'Semana 4'],
                datasets: [{
                    label: 'Score SEO',
                    data: [65, 72, 78, 85],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Visibilidade',
                    data: [60, 68, 75, 82],
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#fff'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#fff'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#fff'
                        }
                    }
                }
            }
        });
    }

    async loadAnalysisData() {
        // Load recent analysis data
    }
}

// Global functions for button clicks
let dashboard;

async function requestJson(url, options = {}) {
    if (window.ApiClient && typeof window.ApiClient.json === 'function') {
        return window.ApiClient.json(url, options);
    }

    const response = await fetch(url, options);
    const data = await response.json();
    return { response, data };
}

async function analyzeProduct() {
    dashboard.showLoading();
    
    const productId = document.getElementById('productId').value;
    const title = document.getElementById('productTitle').value;
    const category = document.getElementById('productCategory').value;
    const description = document.getElementById('productDescription').value;
    const keywords = document.getElementById('targetKeywords').value;

    try {
        const { data } = await requestJson('/api/seo/analyze-product', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                product: {
                    id: productId,
                    title: title,
                    category: category,
                    description: description,
                    keywords: keywords.split(',').map(k => k.trim())
                }
            })
        });

        if (data.success) {
            displayAnalysisResults(data.data);
        } else {
            dashboard.showAlert(data.error || 'Erro na análise', 'danger');
        }
    } catch (error) {
        dashboard.showAlert('Erro ao conectar com o servidor', 'danger');
    } finally {
        dashboard.hideLoading();
    }
}

function displayAnalysisResults(results) {
    const resultsDiv = document.getElementById('analysisResults');
    const scoreDiv = document.getElementById('analysisScore');
    const actionList = document.getElementById('actionList');
    
    // Update score
    const score = results.score || 0;
    scoreDiv.textContent = `Score: ${score}/100`;
    scoreDiv.className = `optimization-score ${score >= 75 ? 'score-high' : score >= 50 ? 'score-medium' : 'score-low'}`;
    
    // Create action list
    const actions = results.action_summary || [];
    actionList.innerHTML = actions.map(action => `
        <div class="alert alert-info alert-seo">
            <i class="fas fa-arrow-right me-2"></i>
            <strong>${action.action}</strong>
            <span class="badge bg-warning ms-2">${action.priority}</span>
        </div>
    `).join('');
    
    // Create chart
    createAnalysisChart(results);
    
    resultsDiv.style.display = 'block';
}

function createAnalysisChart(data) {
    const ctx = document.getElementById('analysisChart');
    if (!ctx) return;

    if (dashboard.charts.analysis) {
        dashboard.charts.analysis.destroy();
    }

    dashboard.charts.analysis = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: ['Título', 'Descrição', 'Keywords', 'Atributos', 'Imagens'],
            datasets: [{
                label: 'Atual',
                data: [
                    data.title_analysis?.score || 0,
                    data.description_analysis?.score || 0,
                    data.keywords?.score || 0,
                    data.attributes?.score || 0,
                    data.images?.score || 0
                ],
                borderColor: '#e74c3c',
                backgroundColor: 'rgba(231, 76, 60, 0.2)'
            }, {
                label: 'Potencial',
                data: [85, 90, 80, 85, 90],
                borderColor: '#27ae60',
                backgroundColor: 'rgba(39, 174, 96, 0.2)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#fff'
                    }
                }
            },
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#fff',
                        backdropColor: 'transparent'
                    }
                }
            }
        }
    });
}

async function optimizeTitle() {
    dashboard.showLoading();
    
    const title = document.getElementById('titleInput').value;
    const category = document.getElementById('titleCategory').value;

    try {
        const { data } = await requestJson('/api/seo/optimize-title', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                title: title,
                context: {
                    category: category
                }
            })
        });

        if (data.success) {
            displayTitleResults(data.data);
        } else {
            dashboard.showAlert(data.error || 'Erro na otimização', 'danger');
        }
    } catch (error) {
        dashboard.showAlert('Erro ao conectar com o servidor', 'danger');
    } finally {
        dashboard.hideLoading();
    }
}

function displayTitleResults(data) {
    const resultsDiv = document.getElementById('titleResults');
    const suggestionsDiv = document.getElementById('suggestedTitles');
    const gapsDiv = document.getElementById('titleGaps');
    
    // Display suggested titles
    const optimizedTitles = data.optimized_titles?.optimized_titles || [];
    suggestionsDiv.innerHTML = optimizedTitles.map((titleObj, index) => `
        <div class="alert alert-success alert-seo">
            <h6><i class="fas fa-star me-2"></i>Opção ${index + 1}</h6>
            <div class="fw-bold">${titleObj.title}</div>
            <small>Estratégia: ${titleObj.strategy} | Caracteres: ${titleObj.character_count}</small>
            <div class="mt-2">
                <small>${titleObj.improvement_reason}</small>
            </div>
        </div>
    `).join('');
    
    // Display gaps analysis
    const gaps = data.analysis?.gaps;
    if (gaps) {
        gapsDiv.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-eye me-2"></i>Lacunas Visíveis</h6>
                    ${Object.entries(gaps.visible_gaps).map(([key, value]) => `
                        <div class="mb-2">
                            <strong>${key}:</strong> ${Array.isArray(value) ? value.join(', ') : value}
                        </div>
                    `).join('')}
                </div>
                <div class="col-md-6">
                    <h6><i class="fas fa-eye-slash me-2"></i>Lacunas Ocultas</h6>
                    ${Object.entries(gaps.hidden_gaps).map(([key, value]) => `
                        <div class="mb-2">
                            <strong>${key}:</strong> ${Array.isArray(value) ? value.slice(0, 3).join(', ') : value}
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    resultsDiv.style.display = 'block';
}

async function analyzeKeywordGaps() {
    dashboard.showLoading();
    
    const productId = document.getElementById('gapProductId').value;
    const competitorCount = document.getElementById('competitorCount').value;

    try {
        const { data } = await requestJson('/api/seo/analyze-keyword-gaps', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                product_id: productId,
                context: {
                    competitor_count: parseInt(competitorCount)
                }
            })
        });

        if (data.success) {
            displayGapResults(data.data);
        } else {
            dashboard.showAlert(data.error || 'Erro na análise', 'danger');
        }
    } catch (error) {
        dashboard.showAlert('Erro ao conectar com o servidor', 'danger');
    } finally {
        dashboard.hideLoading();
    }
}

function displayGapResults(data) {
    const resultsDiv = document.getElementById('gapResults');
    const criticalGapsDiv = document.getElementById('criticalGaps');
    const longTailDiv = document.getElementById('longTailOpportunities');
    
    // Display critical gaps
    const criticalGaps = data.gap_analysis?.gap_severity?.critical || [];
    criticalGapsDiv.innerHTML = criticalGaps.map(gap => `
        <div class="alert alert-danger alert-seo">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>${gap}</strong>
            <div class="mt-1">
                <small>Impacto: Alto | Prioridade: Imediata</small>
            </div>
        </div>
    `).join('');
    
    // Display long tail opportunities
    const longTail = data.long_tail_opportunities?.long_tail_keywords || [];
    longTailDiv.innerHTML = longTail.map(opp => `
        <div class="alert alert-info alert-seo">
            <i class="fas fa-search me-2"></i>
            <strong>${opp.keyword}</strong>
            <div class="mt-1">
                <small>Intenção: ${opp.search_intent} | Concorrência: ${opp.competition_level}</small>
            </div>
        </div>
    `).join('');
    
    // Create gap chart
    createGapChart(data);
    
    resultsDiv.style.display = 'block';
}

function createGapChart(data) {
    const ctx = document.getElementById('gapChart');
    if (!ctx) return;

    if (dashboard.charts.gap) {
        dashboard.charts.gap.destroy();
    }

    dashboard.charts.gap = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Minhas Keywords', 'Keywords Concorrentes', 'Overlap', 'Lacunas'],
            datasets: [{
                label: 'Keywords',
                data: [
                    data.my_keywords?.length || 0,
                    data.competitor_analysis?.total_competitor_keywords || 0,
                    data.competitor_analysis?.keyword_overlap || 0,
                    data.gap_analysis?.missing_keywords?.length || 0
                ],
                backgroundColor: [
                    '#3498db',
                    '#e74c3c',
                    '#f39c12',
                    '#27ae60'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#fff'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#fff'
                    }
                }
            }
        }
    });
}

async function analyzeSemantic() {
    dashboard.showLoading();
    
    const text = document.getElementById('semanticInput').value;
    const category = document.getElementById('semanticCategory').value;
    const type = document.getElementById('semanticType').value;

    try {
        const { data } = await requestJson('/api/seo/analyze-semantic', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                type: type,
                product: {
                    title: text.substring(0, 100),
                    description: text,
                    category: category
                },
                keyword: text.substring(0, 50)
            })
        });

        if (data.success) {
            displaySemanticResults(data.data, type);
        } else {
            dashboard.showAlert(data.error || 'Erro na análise semântica', 'danger');
        }
    } catch (error) {
        dashboard.showAlert('Erro ao conectar com o servidor', 'danger');
    } finally {
        dashboard.hideLoading();
    }
}

function displaySemanticResults(data, type) {
    const resultsDiv = document.getElementById('semanticResults');
    const cloudDiv = document.getElementById('semanticCloud');
    const clustersDiv = document.getElementById('semanticClusters');
    const intentDiv = document.getElementById('intentMapping');
    
    if (type === 'structure') {
        // Display semantic cloud
        const keywords = data.semantic_core?.semantic_clusters || [];
        cloudDiv.innerHTML = keywords.flatMap(cluster => 
            cluster.keywords.map(keyword => 
                `<span class="semantic-tag">${keyword}</span>`
            )
        ).join('');
        
        // Display clusters
        clustersDiv.innerHTML = keywords.map(cluster => `
            <div class="alert alert-info alert-seo">
                <h6><i class="fas fa-sitemap me-2"></i>${cluster.cluster_name}</h6>
                <div>${cluster.keywords.join(', ')}</div>
                <small>Peso: ${(cluster.semantic_weight * 100).toFixed(1)}%</small>
            </div>
        `).join('');
        
        // Display intent mapping
        const intent = data.user_intent_mapping;
        intentDiv.innerHTML = `
            <div class="alert alert-success alert-seo">
                <h6><i class="fas fa-bullseye me-2"></i>Intenção Principal</h6>
                <div><strong>${intent.primary_intent}</strong></div>
                <div>Score: ${intent.intent_fulfillment_score}/100</div>
            </div>
        `;
    }
    
    resultsDiv.style.display = 'block';
}

async function extractModel() {
    await processModelAttribute('extract');
}

async function optimizeModel() {
    await processModelAttribute('optimize');
}

async function validateModel() {
    await processModelAttribute('validate');
}

async function processModelAttribute(action) {
    dashboard.showLoading();
    
    const title = document.getElementById('modelTitle').value;
    const category = document.getElementById('modelCategory').value;
    const currentModel = document.getElementById('currentModel').value;
    const brand = document.getElementById('brandInput').value;

    try {
        const { data } = await requestJson('/api/seo/optimize-model-attribute', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: action,
                product: {
                    title: title,
                    category: category,
                    brand: brand
                },
                current_model: currentModel
            })
        });

        if (data.success) {
            displayModelResults(data.data, action);
        } else {
            dashboard.showAlert(data.error || 'Erro no processamento', 'danger');
        }
    } catch (error) {
        dashboard.showAlert('Erro ao conectar com o servidor', 'danger');
    } finally {
        dashboard.hideLoading();
    }
}

function displayModelResults(data, action) {
    const resultsDiv = document.getElementById('modelResults');
    const suggestionsDiv = document.getElementById('modelSuggestions');
    const patternsDiv = document.getElementById('modelPatterns');
    const validationDiv = document.getElementById('modelValidation');
    
    if (action === 'extract' || action === 'optimize') {
        const suggestions = data.suggestions || [];
        suggestionsDiv.innerHTML = `
            <div class="model-suggestion">
                <h6><i class="fas fa-lightbulb me-2"></i>Modelo Recomendado</h6>
                <div class="fw-bold fs-5">${suggestions.recommended_model || data.current_model}</div>
                <div class="mt-2">
                    <small>Confiança: ${data.confidence_score || 0}%</small>
                </div>
            </div>
        `;
        
        // Display alternatives
        if (suggestions.alternatives) {
            suggestionsDiv.innerHTML += suggestions.alternatives.map(alt => `
                <div class="alert alert-info alert-seo">
                    <i class="fas fa-circle me-2"></i>${alt}
                </div>
            `).join('');
        }
    }
    
    if (action === 'validate') {
        const validation = data.validation_results || {};
        validationDiv.innerHTML = `
            <div class="alert ${validation.overall_score >= 75 ? 'alert-success' : 'alert-warning'} alert-seo">
                <h6><i class="fas fa-check-circle me-2"></i>Resultado da Validação</h6>
                <div>Score Geral: ${validation.overall_score}/100</div>
                <div>Aprovado: ${validation.recommendation?.approved ? 'Sim' : 'Não'}</div>
                ${validation.recommendation?.final_model ? `<div>Modelo Final: <strong>${validation.recommendation.final_model}</strong></div>` : ''}
            </div>
        `;
        
        // Display issues
        if (data.issues_found) {
            validationDiv.innerHTML += data.issues_found.map(issue => `
                <div class="alert alert-danger alert-seo">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>${issue.description}</strong>
                    <div><small>Sugestão: ${issue.fix_suggestion}</small></div>
                </div>
            `).join('');
        }
    }
    
    resultsDiv.style.display = 'block';
}

async function startMonitoring() {
    dashboard.showLoading();
    
    const products = document.getElementById('monitoringProducts').value
        .split('\n')
        .map(p => p.trim())
        .filter(p => p);
    const timeRange = document.getElementById('timeRange').value;

    try {
        const { data } = await requestJson('/api/seo/monitor-optimization', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                product_ids: products,
                time_range: timeRange
            })
        });

        if (data.success) {
            displayMonitoringResults(data.data);
        } else {
            dashboard.showAlert(data.error || 'Erro no monitoramento', 'danger');
        }
    } catch (error) {
        dashboard.showAlert('Erro ao conectar com o servidor', 'danger');
    } finally {
        dashboard.hideLoading();
    }
}

function displayMonitoringResults(data) {
    const resultsDiv = document.getElementById('monitoringResults');
    const alertsDiv = document.getElementById('monitoringAlerts');
    const improvementsDiv = document.getElementById('monitoringImprovements');
    
    // Create monitoring chart
    dashboard.updateMonitoringCharts(data);
    
    // Display alerts
    const alerts = data.summary?.alerts || [];
    alertsDiv.innerHTML = alerts.map(alert => `
        <div class="alert alert-warning alert-seo">
            <i class="fas fa-exclamation-circle me-2"></i>
            ${alert}
        </div>
    `).join('');
    
    // Display improvements
    const improvements = data.summary?.improvements || [];
    improvementsDiv.innerHTML = improvements.map(improvement => `
        <div class="alert alert-success alert-seo">
            <i class="fas fa-arrow-up me-2"></i>
            ${improvement}
        </div>
    `).join('');
    
    resultsDiv.style.display = 'block';
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    dashboard = new SEODashboard();
});