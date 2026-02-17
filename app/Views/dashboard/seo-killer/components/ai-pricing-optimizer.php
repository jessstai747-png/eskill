<!-- AI Pricing Optimizer Interface -->
<div class="ai-pricing-optimizer" id="aiPricingOptimizer">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1">
                        <i class="bi bi-currency-dollar text-success"></i>
                        AI Pricing Optimizer
                    </h3>
                    <p class="text-muted mb-0">Otimização dinâmica de preços com Machine Learning</p>
                </div>
                <button class="btn btn-outline-primary" onclick="showPricingWizard()">
                    <i class="bi bi-magic"></i>
                    Wizard de Preços
                </button>
            </div>
        </div>
    </div>

    <!-- Product Selector -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-8">
                            <label class="form-label">Selecione um Produto</label>
                            <select class="form-select" id="pricingProductSelect" onchange="loadPricingData()">
                                <option value="">Escolha um produto...</option>
                                <!-- Products loaded dynamically -->
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary w-100" onclick="analyzePricing()">
                                <i class="bi bi-graph-up-arrow"></i>
                                Analisar Preço
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analysis Results -->
    <div id="pricingResults" style="display: none;">
        <!-- Current Situation -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="bi bi-tag fs-1 text-primary"></i>
                        </div>
                        <h6 class="text-muted mb-1">Preço Atual</h6>
                        <h3 class="mb-0" id="currentPrice">R$ 0,00</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="bi bi-trophy fs-1 text-success"></i>
                        </div>
                        <h6 class="text-muted mb-1">Preço Sugerido</h6>
                        <h3 class="mb-0 text-success" id="suggestedPrice">R$ 0,00</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="bi bi-graph-up fs-1 text-info"></i>
                        </div>
                        <h6 class="text-muted mb-1">Ganho Estimado</h6>
                        <h3 class="mb-0 text-info" id="estimatedGain">+0%</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing Strategies -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">
                            <i class="bi bi-lightbulb"></i>
                            Estratégias de Precificação
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="pricingStrategies" class="row"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Elasticity Analysis -->
        <div class="row mb-4">
            <div class="col-md-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">
                            <i class="bi bi-activity"></i>
                            Análise de Elasticidade
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="elasticityLoading" class="text-center py-4">
                            <div class="spinner-border text-primary"></div>
                        </div>
                        <div id="elasticityContent" style="display: none;">
                            <!-- Elasticity Coefficient -->
                            <div class="alert alert-info mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">Coeficiente de Elasticidade</h6>
                                        <p class="mb-0 mt-1" id="elasticityExplanation"></p>
                                    </div>
                                    <h2 class="mb-0" id="elasticityCoefficient">0.0</h2>
                                </div>
                            </div>

                            <!-- Scenarios -->
                            <h6 class="mb-3">Simulação de Cenários</h6>
                            <div id="elasticityScenarios"></div>

                            <!-- Recommendation -->
                            <div class="alert alert-warning mt-3 mb-0">
                                <h6 class="alert-heading">
                                    <i class="bi bi-megaphone"></i>
                                    Recomendação
                                </h6>
                                <p class="mb-0" id="elasticityRecommendation"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Competitive Analysis -->
            <div class="col-md-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">
                            <i class="bi bi-bar-chart"></i>
                            Análise Competitiva
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="competitiveLoading" class="text-center py-4">
                            <div class="spinner-border text-primary"></div>
                        </div>
                        <div id="competitiveContent" style="display: none;">
                            <!-- Position Gauge -->
                            <div class="text-center mb-4">
                                <canvas id="competitivePositionChart" width="200" height="200"></canvas>
                                <h5 class="mt-3" id="marketPosition"></h5>
                            </div>

                            <!-- Competitor Stats -->
                            <div class="competitive-stats">
                                <div class="stat-row">
                                    <span class="text-muted">Menor Preço:</span>
                                    <strong id="minPrice">R$ 0</strong>
                                </div>
                                <div class="stat-row">
                                    <span class="text-muted">Preço Médio:</span>
                                    <strong id="avgPrice">R$ 0</strong>
                                </div>
                                <div class="stat-row">
                                    <span class="text-muted">Maior Preço:</span>
                                    <strong id="maxPrice">R$ 0</strong>
                                </div>
                                <div class="stat-row">
                                    <span class="text-muted">Seu Percentil:</span>
                                    <strong id="percentile">0%</strong>
                                </div>
                            </div>

                            <!-- Opportunities -->
                            <div id="pricingOpportunities" class="mt-3"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Forecast -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">
                            <i class="bi bi-crystal-ball"></i>
                            Previsão de Receita
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Teste Diferentes Preços</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="customPricePoints" placeholder="99.90, 109.90, 119.90, 129.90">
                                    <button class="btn btn-primary" onclick="forecastRevenue()">
                                        <i class="bi bi-search"></i>
                                        Prever
                                    </button>
                                </div>
                                <small class="text-muted">Separe os preços por vírgula</small>
                            </div>
                        </div>
                        <div id="forecastResults"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dynamic Pricing Rules -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-gear"></i>
                                Regras de Precificação Dinâmica
                            </h5>
                            <button class="btn btn-sm btn-primary" onclick="showCreateRuleModal()">
                                <i class="bi bi-plus-circle"></i>
                                Nova Regra
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="pricingRulesList"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Apply Price Section -->
        <div class="row">
            <div class="col-12">
                <div class="card border-primary border-2 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">Aplicar Novo Preço</h5>
                                <p class="text-muted mb-0">
                                    Baseado na estratégia: <strong id="selectedStrategy">-</strong>
                                </p>
                            </div>
                            <button class="btn btn-success btn-lg" onclick="applyNewPrice()">
                                <i class="bi bi-check-circle"></i>
                                Aplicar R$ <span id="priceToApply">0,00</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Dynamic Rule Modal -->
<div class="modal fade" id="createRuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-gear"></i>
                    Criar Regra de Precificação Dinâmica
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="dynamicRuleForm">
                    <div class="mb-3">
                        <label class="form-label">Condição da Regra</label>
                        <select class="form-select" id="ruleCondition">
                            <option value="competitor_price_below">Quando concorrente baixar preço</option>
                            <option value="competitor_price_above">Quando concorrente subir preço</option>
                            <option value="low_stock">Quando estoque estiver baixo (&lt;10)</option>
                            <option value="high_stock">Quando estoque estiver alto (&gt;50)</option>
                            <option value="time_of_day">Horário específico</option>
                            <option value="day_of_week">Dia da semana específico</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Ação</label>
                        <select class="form-select" id="ruleAction">
                            <option value="decrease_percentage">Diminuir preço em %</option>
                            <option value="increase_percentage">Aumentar preço em %</option>
                            <option value="set_fixed_price">Definir preço fixo</option>
                            <option value="match_competitor">Igualar ao concorrente</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Valor</label>
                        <input type="number" class="form-control" id="ruleValue" step="0.01" placeholder="Ex: 5 (para 5%)">
                        <small class="text-muted">Para porcentagem, use valores como 5 (= 5%). Para preço fixo, use valor em reais.</small>
                    </div>

                    <div class="alert alert-info">
                        <h6 class="alert-heading">
                            <i class="bi bi-info-circle"></i>
                            Prévia da Regra
                        </h6>
                        <p class="mb-0" id="rulePreview">Configure a regra acima para ver a prévia...</p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveDynamicRule()">
                    <i class="bi bi-check-lg"></i>
                    Salvar Regra
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
    // AI Pricing Optimizer JavaScript
    let pricingData = {
        currentItem: null,
        suggestion: null,
        elasticity: null,
        competitive: null,
        forecast: null,
        rules: []
    };

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadProductsForPricing();

        // Update rule preview on form change
        ['ruleCondition', 'ruleAction', 'ruleValue'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', updateRulePreview);
        });
    });

    // Load Products
    async function loadProductsForPricing() {
        try {
            const {
                data: result
            } = await requestJson('/api/items?status=active');

            // Handle different response structures
            let items = [];
            if (Array.isArray(result)) {
                items = result;
            } else if (result.data && Array.isArray(result.data)) {
                items = result.data;
            } else if (result.data && result.data.items && Array.isArray(result.data.items)) {
                items = result.data.items;
            } else if (result.items && Array.isArray(result.items)) {
                items = result.items;
            }

            const select = document.getElementById('pricingProductSelect');
            if (items.length === 0) {
                select.innerHTML = '<option value="">Nenhum produto encontrado</option>';
                return;
            }

            select.innerHTML = '<option value="">Escolha um produto...</option>' +
                items.map(item => `
                <option value="${item.id}" data-price="${item.price}">
                    ${item.title} - R$ ${parseFloat(item.price).toFixed(2)}
                </option>
            `).join('');
        } catch (error) {
            console.error('Error loading products:', error);
            const select = document.getElementById('pricingProductSelect');
            select.innerHTML = '<option value="">Erro ao carregar produtos</option>';
        }
    }

    // Analyze Pricing
    async function analyzePricing() {
        const select = document.getElementById('pricingProductSelect');
        const itemId = select.value;

        if (!itemId) {
            alert('Selecione um produto primeiro!');
            return;
        }

        pricingData.currentItem = itemId;
        document.getElementById('pricingResults').style.display = 'block';

        // Load all analyses in parallel
        await Promise.all([
            loadPricingSuggestion(itemId),
            loadElasticityAnalysis(itemId),
            loadCompetitiveAnalysis(itemId)
        ]);
    }

    // Load Pricing Suggestion
    async function loadPricingSuggestion(itemId) {
        try {
            const {
                data: result
            } = await requestJson('/api/ai/pricing/suggest', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id: itemId,
                    goal: 'balanced'
                })
            });

            if (!result || !result.data) {
                throw new Error('Formato de resposta inválido');
            }
            const {
                data
            } = result;

            pricingData.suggestion = data;

            // Update summary cards
            document.getElementById('currentPrice').textContent = `R$ ${parseFloat(data.current_price).toFixed(2)}`;
            document.getElementById('suggestedPrice').textContent = `R$ ${parseFloat(data.suggested_price).toFixed(2)}`;

            const gain = ((data.suggested_price - data.current_price) / data.current_price * 100).toFixed(1);
            const gainEl = document.getElementById('estimatedGain');
            gainEl.textContent = `${gain > 0 ? '+' : ''}${gain}%`;
            gainEl.className = `mb-0 ${gain > 0 ? 'text-success' : 'text-danger'}`;

            // Render strategies
            renderPricingStrategies(data);

            // Set apply section
            document.getElementById('selectedStrategy').textContent = data.strategy;
            document.getElementById('priceToApply').textContent = parseFloat(data.suggested_price).toFixed(2);

        } catch (error) {
            console.error('Error loading pricing suggestion:', error);
            // Show error state in UI
            document.getElementById('currentPrice').textContent = '---';
            document.getElementById('suggestedPrice').textContent = '---';
            document.getElementById('estimatedGain').textContent = '---';
        }
    }

    // Render Pricing Strategies
    function renderPricingStrategies(data) {
        const container = document.getElementById('pricingStrategies');
        const strategies = [{
                name: 'Penetração de Mercado',
                price: data.competitors.min * 0.95,
                goal: 'Ganhar market share',
                icon: 'graph-down-arrow',
                color: 'info'
            },
            {
                name: 'Competitivo',
                price: data.competitors.avg,
                goal: 'Posicionamento equilibrado',
                icon: 'graph-up',
                color: 'primary'
            },
            {
                name: 'Premium',
                price: data.competitors.max * 1.05,
                goal: 'Maximizar margem',
                icon: 'star',
                color: 'warning'
            },
            {
                name: 'Baseado em Margem',
                price: data.suggested_price,
                goal: '30% de margem consistente',
                icon: 'calculator',
                color: 'success'
            }
        ];

        container.innerHTML = strategies.map(s => `
        <div class="col-md-6 col-lg-3 mb-3">
            <div class="strategy-card card h-100 ${data.strategy === s.name ? 'border-' + s.color + ' border-2' : ''}">
                <div class="card-body text-center">
                    <div class="strategy-icon mb-2">
                        <i class="bi bi-${s.icon} fs-1 text-${s.color}"></i>
                    </div>
                    <h6 class="card-title">${s.name}</h6>
                    <h4 class="text-${s.color} mb-2">R$ ${s.price.toFixed(2)}</h4>
                    <p class="text-muted small mb-3">${s.goal}</p>
                    ${data.strategy === s.name ? '<span class="badge bg-' + s.color + '"><i class="bi bi-check-lg"></i> Recomendado</span>' : ''}
                </div>
            </div>
        </div>
    `).join('');
    }

    // Load Elasticity Analysis
    async function loadElasticityAnalysis(itemId) {
        const loadingEl = document.getElementById('elasticityLoading');
        const contentEl = document.getElementById('elasticityContent');

        loadingEl.style.display = 'block';
        contentEl.style.display = 'none';

        try {
            const {
                data: apiResp
            } = await requestJson('/api/ai/pricing/elasticity', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id: itemId
                })
            });

            const {
                data
            } = apiResp;
            pricingData.elasticity = data;

            // Display coefficient
            document.getElementById('elasticityCoefficient').textContent = data.elasticity_coefficient.toFixed(2);
            document.getElementById('elasticityExplanation').textContent = data.explanation;

            // Render scenarios
            const scenariosEl = document.getElementById('elasticityScenarios');
            scenariosEl.innerHTML = data.scenarios.map(s => {
                const isPositive = parseFloat(s.net_revenue_effect.replace('%', '')) > 0;
                return `
                <div class="scenario-item mb-2 p-3 border rounded ${isPositive ? 'border-success bg-success bg-opacity-10' : 'border-danger bg-danger bg-opacity-10'}">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Mudança: ${s.price_change}</strong>
                            <br>
                            <small class="text-muted">
                                Volume: ${s.expected_volume_change}
                            </small>
                        </div>
                        <h5 class="mb-0 ${isPositive ? 'text-success' : 'text-danger'}">
                            ${s.net_revenue_effect}
                        </h5>
                    </div>
                </div>
            `;
            }).join('');

            // Recommendation
            document.getElementById('elasticityRecommendation').textContent = data.recommendation;

            loadingEl.style.display = 'none';
            contentEl.style.display = 'block';

        } catch (error) {
            console.error('Error loading elasticity:', error);
            loadingEl.innerHTML = '<div class="alert alert-danger">Dados históricos insuficientes para análise de elasticidade.</div>';
        }
    }

    // Load Competitive Analysis
    async function loadCompetitiveAnalysis(itemId) {
        const loadingEl = document.getElementById('competitiveLoading');
        const contentEl = document.getElementById('competitiveContent');

        loadingEl.style.display = 'block';
        contentEl.style.display = 'none';

        try {
            const {
                data: apiResp
            } = await requestJson(`/api/ai/pricing/competitive/${itemId}`);
            const {
                data
            } = apiResp;
            pricingData.competitive = data;

            // Draw position chart
            drawCompetitivePositionChart(data.position, data.percentile);

            // Display stats
            document.getElementById('minPrice').textContent = `R$ ${data.competitors.min.toFixed(2)}`;
            document.getElementById('avgPrice').textContent = `R$ ${data.competitors.avg.toFixed(2)}`;
            document.getElementById('maxPrice').textContent = `R$ ${data.competitors.max.toFixed(2)}`;
            document.getElementById('percentile').textContent = `${data.percentile}º`;

            // Market position label
            const positionLabels = {
                'lowest': {
                    text: 'Mais Barato',
                    color: 'info'
                },
                'below_average': {
                    text: 'Abaixo da Média',
                    color: 'success'
                },
                'average': {
                    text: 'Na Média',
                    color: 'primary'
                },
                'above_average': {
                    text: 'Acima da Média',
                    color: 'warning'
                },
                'highest': {
                    text: 'Mais Caro',
                    color: 'danger'
                }
            };
            const pos = positionLabels[data.position];
            document.getElementById('marketPosition').innerHTML = `<span class="badge bg-${pos.color} fs-6">${pos.text}</span>`;

            // Opportunities
            const opportunitiesEl = document.getElementById('pricingOpportunities');
            if (data.opportunities && data.opportunities.length > 0) {
                opportunitiesEl.innerHTML = `
                <div class="alert alert-warning mb-0">
                    <h6 class="alert-heading"><i class="bi bi-lightbulb"></i> Oportunidades</h6>
                    <ul class="mb-0">
                        ${data.opportunities.map(o => `<li>${o}</li>`).join('')}
                    </ul>
                </div>
            `;
            }

            loadingEl.style.display = 'none';
            contentEl.style.display = 'block';

        } catch (error) {
            console.error('Error loading competitive analysis:', error);
            loadingEl.innerHTML = '<div class="alert alert-danger">Erro ao carregar análise competitiva.</div>';
        }
    }

    // Draw Competitive Position Chart
    function drawCompetitivePositionChart(position, percentile) {
        const canvas = document.getElementById('competitivePositionChart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        const radius = 80;

        // Clear
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Background arc
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0.75 * Math.PI, 0.25 * Math.PI);
        ctx.lineWidth = 20;
        ctx.strokeStyle = '#e0e0e0';
        ctx.stroke();

        // Position arc
        const percentileAngle = 0.75 * Math.PI + (percentile / 100) * 1.5 * Math.PI;
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0.75 * Math.PI, percentileAngle);
        ctx.lineWidth = 20;
        ctx.strokeStyle = getPositionColor(position);
        ctx.stroke();

        // Percentile text
        ctx.font = 'bold 24px Arial';
        ctx.fillStyle = '#333';
        ctx.textAlign = 'center';
        ctx.fillText(`${percentile}º`, centerX, centerY + 10);
    }

    function getPositionColor(position) {
        const colors = {
            'lowest': '#17a2b8',
            'below_average': '#28a745',
            'average': '#007bff',
            'above_average': '#ffc107',
            'highest': '#dc3545'
        };
        return colors[position] || '#6c757d';
    }

    // Forecast Revenue
    async function forecastRevenue() {
        const input = document.getElementById('customPricePoints').value;
        const pricePoints = input.split(',').map(p => parseFloat(p.trim())).filter(p => !isNaN(p));

        if (pricePoints.length === 0) {
            alert('Digite pelo menos um preço válido!');
            return;
        }

        try {
            const {
                data: apiResp
            } = await requestJson('/api/ai/pricing/forecast', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id: pricingData.currentItem,
                    price_points: pricePoints
                })
            });

            const {
                data
            } = apiResp;
            pricingData.forecast = data;

            // Render forecast table
            const resultsEl = document.getElementById('forecastResults');
            resultsEl.innerHTML = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Preço</th>
                            <th>Volume Estimado</th>
                            <th>Receita</th>
                            <th>Confiança</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.scenarios.map(s => `
                            <tr class="${s.price === data.best_scenario.price ? 'table-success' : ''}">
                                <td><strong>R$ ${s.price.toFixed(2)}</strong></td>
                                <td>${s.estimated_volume} un.</td>
                                <td><strong>R$ ${s.expected_revenue.toFixed(2)}</strong></td>
                                <td>
                                    <span class="badge bg-secondary">${s.confidence}%</span>
                                </td>
                                <td>
                                    ${s.price === data.best_scenario.price ? '<span class="badge bg-success"><i class="bi bi-trophy"></i> Melhor</span>' : ''}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            <div class="alert alert-success mt-3">
                <strong><i class="bi bi-trophy"></i> Melhor Cenário:</strong>
                R$ ${data.best_scenario.price.toFixed(2)} com ganho potencial de R$ ${data.potential_gain.toFixed(2)}
            </div>
        `;

        } catch (error) {
            console.error('Error forecasting:', error);
        }
    }

    // Dynamic Rules
    function showCreateRuleModal() {
        new bootstrap.Modal(document.getElementById('createRuleModal')).show();
    }

    function updateRulePreview() {
        const condition = document.getElementById('ruleCondition').value;
        const action = document.getElementById('ruleAction').value;
        const value = document.getElementById('ruleValue').value;

        const preview = document.getElementById('rulePreview');

        if (!condition || !action || !value) {
            preview.textContent = 'Configure todos os campos...';
            return;
        }

        const conditionTexts = {
            'competitor_price_below': 'Quando concorrente baixar preço',
            'competitor_price_above': 'Quando concorrente subir preço',
            'low_stock': 'Quando estoque < 10',
            'high_stock': 'Quando estoque > 50',
            'time_of_day': 'No horário especificado',
            'day_of_week': 'No dia da semana especificado'
        };

        const actionTexts = {
            'decrease_percentage': `diminuir ${value}%`,
            'increase_percentage': `aumentar ${value}%`,
            'set_fixed_price': `definir para R$ ${value}`,
            'match_competitor': 'igualar ao concorrente'
        };

        preview.textContent = `${conditionTexts[condition]}, ${actionTexts[action]}`;
    }

    async function saveDynamicRule() {
        const rule = {
            condition: document.getElementById('ruleCondition').value,
            action: document.getElementById('ruleAction').value,
            value: parseFloat(document.getElementById('ruleValue').value)
        };

        try {
            const {
                data: apiResp
            } = await requestJson('/api/ai/pricing/dynamic-rules', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id: pricingData.currentItem,
                    rules: [rule]
                })
            });

            const {
                data
            } = apiResp;

            bootstrap.Modal.getInstance(document.getElementById('createRuleModal')).hide();

            Toastify({
                text: "Regra criada com sucesso!",
                duration: 3000,
                backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
            }).showToast();

            // Reload rules
            loadPricingRules();

        } catch (error) {
            console.error('Error saving rule:', error);
            alert('Erro ao salvar regra!');
        }
    }

    // Apply New Price
    async function applyNewPrice() {
        if (!confirm('Aplicar o novo preço no Mercado Livre?')) return;

        const newPrice = parseFloat(document.getElementById('priceToApply').textContent.replace('R$ ', ''));

        try {
            Toastify({
                text: "Aplicando novo preço...",
                duration: 2000,
                backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
            }).showToast();

            await requestJson(`/api/items/${pricingData.currentItem}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    price: newPrice
                })
            });

            Toastify({
                text: `Preço atualizado para R$ ${newPrice.toFixed(2)}!`,
                duration: 3000,
                backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
            }).showToast();

            // Refresh analysis
            loadPricingSuggestion(pricingData.currentItem);

        } catch (error) {
            console.error('Error applying price:', error);
            alert('Erro ao aplicar preço!');
        }
    }
</script>

<style>
    .strategy-card {
        transition: all 0.2s;
        cursor: pointer;
    }

    .strategy-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }

    .competitive-stats {
        background: #f8f9fa;
        padding: 16px;
        border-radius: 8px;
    }

    .stat-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #dee2e6;
    }

    .stat-row:last-child {
        border-bottom: none;
    }

    .scenario-item {
        transition: all 0.2s;
    }

    .scenario-item:hover {
        transform: translateX(4px);
    }
</style>