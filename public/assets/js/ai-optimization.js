/**
 * AI Optimization Dashboard JavaScript
 */

const AIOptimization = {
    /**
     * Optimize items by category
     */
    optimizeCategory: async function (category) {
        const button = event.target;
        const originalText = button.textContent;

        button.disabled = true;
        button.innerHTML = '<span class="ai-loading"></span> Processando...';

        try {
            // Get items by score range
            const scoreRanges = {
                'critical': { min: 0, max: 50 },
                'medium': { min: 50, max: 70 },
                'low': { min: 70, max: 85 }
            };

            const range = scoreRanges[category];

            // TODO: Fetch actual item IDs from API
            const itemIds = await this.fetchItemsByScore(range.min, range.max);

            if (itemIds.length === 0) {
                alert('Nenhum anúncio encontrado nesta categoria');
                return;
            }

            // Confirm action
            const confirmed = confirm(`Otimizar ${itemIds.length} anúncios? Isso gastará aproximadamente R$ ${(itemIds.length * 0.20).toFixed(2)}`);

            if (!confirmed) {
                return;
            }

            // Start batch optimization
            const result = await this.batchOptimize(itemIds);

            if (result.success) {
                alert(`✅ ${result.stats.success} anúncios otimizados com sucesso!\\nMelhoria média: +${result.stats.average_improvement} pontos`);
                location.reload();
            } else {
                alert('❌ Erro ao otimizar: ' + (result.error || 'Erro desconhecido'));
            }

        } catch (error) {
            console.error('Error:', error);
            alert('❌ Erro ao otimizar: ' + error.message);
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    },

    /**
     * Fetch items by score range
     */
    fetchItemsByScore: async function (minScore, maxScore) {
        try {
            const response = await fetch(`/api/ai/items-by-score?minScore=${minScore}&maxScore=${maxScore}`);
            if (!response.ok) {
                throw new Error('Falha ao buscar anúncios: ' + response.statusText);
            }
            const itemIds = await response.json();
            console.log(`Fetched items with score ${minScore}-${maxScore}:`, itemIds);
            return itemIds;
        } catch (error) {
            console.error('Erro ao buscar anúncios por score:', error);
            alert('Não foi possível carregar os anúncios para otimização. Tente novamente.');
            return []; // Retorna array vazio em caso de erro
        }
    },

    /**
     * Batch optimize items
     */
    batchOptimize: async function (itemIds) {
        const response = await fetch('/api/ai/optimize/batch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                item_ids: itemIds,
                optimize_title: true,
                optimize_description: true,
                optimize_attributes: true,
                delay_ms: 500
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    },

    /**
     * Optimize single item
     */
    optimizeItem: async function (itemId, options = {}) {
        const response = await fetch('/api/ai/optimize/complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                item_id: itemId,
                ...options
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    },

    /**
     * Get optimization suggestions
     */
    getSuggestions: async function (itemId) {
        const response = await fetch(`/api/ai/suggestions/${itemId}`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    },

    /**
     * Analyze title
     */
    analyzeTitle: async function (title, context = {}) {
        const response = await fetch('/api/ai/analyze/title', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                title: title,
                context: context
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    },

    /**
     * Show loading overlay
     */
    showLoading: function (message = 'Processando...') {
        const overlay = document.createElement('div');
        overlay.id = 'ai-loading-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        `;

        overlay.innerHTML = `
            <div style="background: white; padding: 32px; border-radius: 12px; text-align: center;">
                <div class="ai-loading" style="width: 40px; height: 40px; margin: 0 auto 16px;"></div>
                <p style="font-weight: 600;">${message}</p>
            </div>
        `;

        document.body.appendChild(overlay);
    },

    /**
     * Hide loading overlay
     */
    hideLoading: function () {
        const overlay = document.getElementById('ai-loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    },

    /**
     * Format score badge
     */
    formatScoreBadge: function (score) {
        let className, label;

        if (score >= 85) {
            className = 'score-excellent';
            label = '🟢 Excelente';
        } else if (score >= 70) {
            className = 'score-good';
            label = '🟡 Bom';
        } else if (score >= 50) {
            className = 'score-medium';
            label = '🟠 Médio';
        } else {
            className = 'score-critical';
            label = '🔴 Crítico';
        }

        return `<span class="score-badge ${className}">${score} - ${label}</span>`;
    }
};

// Export functions to global scope for inline onclick handlers
window.optimizeCategory = AIOptimization.optimizeCategory.bind(AIOptimization);
window.openBulkOptimizer = function () {
    window.location = '/dashboard/ai-optimization/batch';
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
    console.log('AI Optimization Dashboard loaded');

    // Add any initialization code here
});
