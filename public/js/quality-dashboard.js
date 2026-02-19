/**
 * Quality Dashboard - JavaScript Module
 * Gerencia interação com dashboard de qualidade
 */

async function requestJson(url, options = {}) {
    if (window.ApiClient) return window.ApiClient.request(url, options);
    const resp = await fetch(url, { credentials: 'include', ...options });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}

const QualityDashboard = {
    accountId: null,
    currentPage: 1,
    chart: null,
    
    /**
     * Inicializa o dashboard
     */
    init(accountId) {
        this.accountId = accountId;
        this.loadStats();
        this.loadItems();
    },
    
    /**
     * Carrega estatísticas do dashboard
     */
    async loadStats() {
        try {
            const data = await requestJson(`/api/quality/dashboard/stats?account_id=${this.accountId}`);
            
            if (data.success) {
                this.updateStats(data.data);
                this.renderChart(data.data.quality_distribution);
            } else {
                this.showError('Erro ao carregar estatísticas');
            }
        } catch (error) {
            console.error('Error loading stats:', error);
            this.showError('Erro ao carregar estatísticas');
        }
    },
    
    /**
     * Atualiza cards de estatísticas
     */
    updateStats(data) {
        const itemStats = data.item_stats || {};
        const distribution = data.quality_distribution || {};
        
        document.getElementById('total-items').textContent = itemStats.total_items || 0;
        document.getElementById('excellent-items').textContent = distribution.excellent || 0;
        document.getElementById('good-items').textContent = distribution.good || 0;
        
        const needsImprovement = (distribution.fair || 0) + (distribution.poor || 0);
        document.getElementById('poor-items').textContent = needsImprovement;
    },
    
    /**
     * Renderiza gráfico de distribuição
     */
    renderChart(distribution) {
        const ctx = document.getElementById('qualityChart');
        
        // Destruir gráfico anterior se existir
        if (this.chart) {
            this.chart.destroy();
        }
        
        this.chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Excelente (>80)', 'Bom (60-80)', 'Regular (40-60)', 'Precisa Melhorar (<40)'],
                datasets: [{
                    data: [
                        distribution.excellent || 0,
                        distribution.good || 0,
                        distribution.fair || 0,
                        distribution.poor || 0
                    ],
                    backgroundColor: [
                        '#28a745',
                        '#17a2b8',
                        '#ffc107',
                        '#dc3545'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 14
                            },
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    },
    
    /**
     * Carrega lista de itens
     */
    async loadItems(page = 1) {
        try {
            this.showLoading();
            
            const minScore = document.getElementById('filter-min-score')?.value || 0;
            const maxScore = document.getElementById('filter-max-score')?.value || 100;
            const status = document.getElementById('filter-status')?.value || 'active';
            
            const url = `/api/quality/dashboard/items?account_id=${this.accountId}&page=${page}&min_score=${minScore}&max_score=${maxScore}&status=${status}`;
            const response = await fetch(url);
            const data = await response.json();
            
            this.hideLoading();
            
            if (data.success) {
                this.renderItems(data.data.items);
                this.renderPagination(data.data.pagination);
                this.currentPage = page;
            } else {
                this.showError('Erro ao carregar itens');
            }
        } catch (error) {
            this.hideLoading();
            console.error('Error loading items:', error);
            this.showError('Erro ao carregar itens');
        }
    },
    
    /**
     * Renderiza tabela de itens
     */
    renderItems(items) {
        const tbody = document.getElementById('items-table-body');
        
        if (!items || items.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Nenhum item encontrado</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = items.map(item => {
            const qualityClass = this.getQualityClass(item.quality_level);
            const qualityLabel = this.getQualityLabel(item.quality_level);
            const scoreColor = this.getScoreColor(item.quality_score);
            
            return `
                <tr class="item-row" onclick="QualityDashboard.viewItemDetails('${item.item_id}')">
                    <td><code>${item.item_id}</code></td>
                    <td>
                        <div class="text-truncate" style="max-width: 300px;" title="${item.title}">
                            ${item.title}
                        </div>
                    </td>
                    <td>
                        <span class="badge ${scoreColor} fs-6">
                            ${item.quality_score}
                        </span>
                    </td>
                    <td>
                        <span class="badge ${qualityClass}">
                            ${qualityLabel}
                        </span>
                    </td>
                    <td>R$ ${this.formatPrice(item.price)}</td>
                    <td>
                        <span class="badge bg-secondary">
                            ${item.sold_quantity || 0}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); QualityDashboard.analyzeItem('${item.item_id}')">
                            <i class="fas fa-search"></i> Analisar
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    },
    
    /**
     * Renderiza paginação
     */
    renderPagination(pagination) {
        const container = document.getElementById('pagination-container');
        
        if (!pagination || pagination.total_pages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        const pages = [];
        for (let i = 1; i <= pagination.total_pages; i++) {
            if (i === 1 || i === pagination.total_pages || Math.abs(i - pagination.page) <= 2) {
                pages.push(i);
            } else if (pages[pages.length - 1] !== '...') {
                pages.push('...');
            }
        }
        
        container.innerHTML = `
            <ul class="pagination justify-content-center">
                <li class="page-item ${pagination.page === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="QualityDashboard.loadItems(${pagination.page - 1}); return false;">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                ${pages.map(p => {
                    if (p === '...') {
                        return '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    return `
                        <li class="page-item ${p === pagination.page ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="QualityDashboard.loadItems(${p}); return false;">
                                ${p}
                            </a>
                        </li>
                    `;
                }).join('')}
                <li class="page-item ${pagination.page === pagination.total_pages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="QualityDashboard.loadItems(${pagination.page + 1}); return false;">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        `;
    },
    
    /**
     * Analisa item específico
     */
    async analyzeItem(itemId) {
        try {
            this.showLoading();
            
            const data = await requestJson(`/api/quality/report/${itemId}?account_id=${this.accountId}`);
            
            this.hideLoading();
            
            if (data.success) {
                this.showItemReport(data.data);
            } else {
                this.showError('Erro ao analisar item');
            }
        } catch (error) {
            this.hideLoading();
            console.error('Error analyzing item:', error);
            this.showError('Erro ao analisar item');
        }
    },
    
    /**
     * Exibe relatório do item em modal
     */
    showItemReport(report) {
        // Implementar modal com relatório detalhado
        alert(`Relatório completo:\nScore: ${report.score?.score || 'N/A'}\nSaúde: ${report.health?.overall_health || 'N/A'}`);
    },
    
    /**
     * Visualiza detalhes do item
     */
    viewItemDetails(itemId) {
        window.location.href = `/items/${itemId}`;
    },
    
    /**
     * Helpers
     */
    getQualityClass(level) {
        const classes = {
            'excellent': 'bg-success',
            'good': 'bg-info',
            'fair': 'bg-warning',
            'poor': 'bg-danger',
            'unknown': 'bg-secondary'
        };
        return classes[level] || 'bg-secondary';
    },
    
    getQualityLabel(level) {
        const labels = {
            'excellent': 'Excelente',
            'good': 'Bom',
            'fair': 'Regular',
            'poor': 'Precisa Melhorar',
            'unknown': 'Desconhecido'
        };
        return labels[level] || 'N/A';
    },
    
    getScoreColor(score) {
        if (score > 80) return 'bg-success';
        if (score > 60) return 'bg-info';
        if (score > 40) return 'bg-warning';
        return 'bg-danger';
    },
    
    formatPrice(price) {
        return parseFloat(price || 0).toFixed(2).replace('.', ',');
    },
    
    showLoading() {
        document.querySelector('.loading-spinner').style.display = 'block';
    },
    
    hideLoading() {
        document.querySelector('.loading-spinner').style.display = 'none';
    },
    
    showError(message) {
        alert(message);
    }
};

/**
 * Funções globais para eventos onclick
 */
function applyFilters() {
    QualityDashboard.loadItems(1);
}

function refreshItems() {
    QualityDashboard.loadStats();
    QualityDashboard.loadItems(QualityDashboard.currentPage);
}
