<!-- AI Insights Dashboard Component -->
<div class="ai-insights-dashboard" id="aiInsightsDashboard">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1">
                        <i class="bi bi-robot text-primary"></i>
                        AI-Powered Insights
                    </h3>
                    <p class="text-muted mb-0">Análises estratégicas com GPT-4 Turbo</p>
                </div>
                <button class="btn btn-primary" onclick="refreshAllInsights()">
                    <i class="bi bi-arrow-clockwise"></i>
                    Atualizar Insights
                </button>
            </div>
        </div>
    </div>

    <!-- Strategic Assessment Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-gradient-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-bullseye"></i>
                        Avaliação Estratégica
                    </h5>
                </div>
                <div class="card-body">
                    <div id="strategicAssessmentLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="text-muted mt-2">Analisando sua conta com IA...</p>
                    </div>
                    
                    <div id="strategicAssessmentContent" style="display: none;">
                        <!-- Overall Assessment -->
                        <div class="alert alert-info mb-4">
                            <h6 class="alert-heading">
                                <i class="bi bi-lightbulb"></i>
                                Avaliação Geral
                            </h6>
                            <p id="overallAssessment" class="mb-0"></p>
                            <small class="text-muted">
                                Confiança: <span id="assessmentConfidence"></span>%
                            </small>
                        </div>

                        <!-- Strengths & Weaknesses -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-success bg-opacity-10 border-success">
                                    <div class="card-body">
                                        <h6 class="text-success">
                                            <i class="bi bi-check-circle"></i>
                                            Pontos Fortes
                                        </h6>
                                        <ul id="strengthsList" class="mb-0"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-warning bg-opacity-10 border-warning">
                                    <div class="card-body">
                                        <h6 class="text-warning">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Áreas de Melhoria
                                        </h6>
                                        <ul id="weaknessesList" class="mb-0"></ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Growth Opportunities -->
                        <div class="card border-0 bg-light mb-4">
                            <div class="card-body">
                                <h6 class="text-primary mb-3">
                                    <i class="bi bi-graph-up-arrow"></i>
                                    Top 3 Oportunidades de Crescimento
                                </h6>
                                <div id="opportunitiesList"></div>
                            </div>
                        </div>

                        <!-- Risks -->
                        <div id="risksSection" class="alert alert-danger" style="display: none;">
                            <h6 class="alert-heading">
                                <i class="bi bi-shield-exclamation"></i>
                                Riscos Identificados
                            </h6>
                            <ul id="risksList" class="mb-0"></ul>
                        </div>

                        <!-- Next Steps -->
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-list-check"></i>
                                    Próximas Ações Recomendadas
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="nextStepsList"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Trends & Sentiment Row -->
    <div class="row mb-4">
        <!-- Trends Analysis -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-graph-up"></i>
                            Análise de Tendências
                        </h5>
                        <select class="form-select form-select-sm w-auto" id="trendsTimeRange" onchange="loadTrendsAnalysis()">
                            <option value="7">7 dias</option>
                            <option value="30" selected>30 dias</option>
                            <option value="90">90 dias</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <div id="trendsLoading" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                    </div>
                    <div id="trendsContent" style="display: none;">
                        <!-- Rising Trends -->
                        <div class="mb-3">
                            <h6 class="text-success">
                                <i class="bi bi-arrow-up-circle"></i>
                                Tendências em Alta
                            </h6>
                            <div id="risingTrendsList"></div>
                        </div>

                        <!-- Declining Trends -->
                        <div class="mb-3">
                            <h6 class="text-danger">
                                <i class="bi bi-arrow-down-circle"></i>
                                Tendências em Queda
                            </h6>
                            <div id="decliningTrendsList"></div>
                        </div>

                        <!-- Seasonal Patterns -->
                        <div class="mb-3">
                            <h6 class="text-info">
                                <i class="bi bi-calendar-event"></i>
                                Padrões Sazonais
                            </h6>
                            <div id="seasonalPatternsList"></div>
                        </div>

                        <!-- Forecast -->
                        <div class="alert alert-light border">
                            <h6 class="text-primary">
                                <i class="bi bi-crystal-ball"></i>
                                Previsão para Próximos 30 Dias
                            </h6>
                            <p id="forecastText" class="mb-0"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Market Sentiment -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-heart-pulse"></i>
                        Sentimento de Mercado
                    </h5>
                </div>
                <div class="card-body">
                    <div id="sentimentLoading" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                    </div>
                    <div id="sentimentContent" style="display: none;">
                        <!-- Sentiment Gauge -->
                        <div class="text-center mb-4">
                            <div class="sentiment-gauge" id="sentimentGauge">
                                <canvas id="sentimentGaugeCanvas" width="200" height="200"></canvas>
                            </div>
                            <h3 id="sentimentLabel" class="mt-3 mb-1"></h3>
                            <p class="text-muted" id="sentimentDescription"></p>
                        </div>

                        <!-- Key Factors -->
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">Fatores-Chave</h6>
                            <ul id="sentimentFactorsList" class="list-unstyled"></ul>
                        </div>

                        <!-- Recommendation -->
                        <div class="alert alert-info mb-0">
                            <small class="fw-bold">Recomendação Estratégica:</small>
                            <p id="sentimentRecommendation" class="mb-0 mt-1"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Prioritized Recommendations -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-star"></i>
                            Recomendações Priorizadas
                        </h5>
                        <span class="badge bg-primary">Top 10</span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="recommendationsLoading" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                    </div>
                    <div id="recommendationsContent" style="display: none;">
                        <!-- Filter Tabs -->
                        <ul class="nav nav-pills mb-3" id="recommendationTabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-filter="all" href="#" onclick="filterRecommendations('all', this); return false;">
                                    Todas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-filter="quick-wins" href="#" onclick="filterRecommendations('quick-wins', this); return false;">
                                    <i class="bi bi-lightning"></i> Quick Wins
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-filter="seo" href="#" onclick="filterRecommendations('seo', this); return false;">
                                    SEO
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-filter="pricing" href="#" onclick="filterRecommendations('pricing', this); return false;">
                                    Preços
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-filter="marketing" href="#" onclick="filterRecommendations('marketing', this); return false;">
                                    Marketing
                                </a>
                            </li>
                        </ul>

                        <!-- Recommendations List -->
                        <div id="recommendationsList"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- A/B Test Suggestions -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-shuffle"></i>
                        Sugestões de Testes A/B
                    </h5>
                </div>
                <div class="card-body">
                    <div id="abTestsLoading" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                    </div>
                    <div id="abTestsContent" style="display: none;">
                        <div id="abTestsList" class="row"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
