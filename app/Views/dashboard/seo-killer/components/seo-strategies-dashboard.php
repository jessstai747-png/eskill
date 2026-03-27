<?php

declare(strict_types=1);

/**
 * 🎯 SEO Strategies Dashboard Component
 * 
 * Dashboard visual para as 12 estratégias de SEO avançadas.
 * Integra com SEOStrategiesEngine e TechSheetSEOIntegrationService.
 */
?>

<style>
    .strategies-dashboard {
        --strat-primary: #5e60ce;
        --strat-success: #06d6a0;
        --strat-warning: #ffd166;
        --strat-danger: #ef476f;
        --strat-info: #48bfe3;
    }

    .strategies-header {
        background: linear-gradient(135deg, #5e60ce, #48bfe3);
        color: white;
        padding: 1.5rem 2rem;
        border-radius: 16px;
        margin-bottom: 1.5rem;
        position: relative;
        overflow: hidden;
    }

    .strategies-header::before {
        content: '🎯';
        position: absolute;
        right: 30px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 72px;
        opacity: 0.15;
    }

    .strategies-header h2 {
        margin: 0;
        font-size: 1.75rem;
        font-weight: 700;
    }

    .strategies-header .subtitle {
        opacity: 0.9;
        margin-top: 0.5rem;
    }

    .strategy-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .strategy-kpi-card {
        background: white;
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        text-align: center;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .strategy-kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 24px rgba(0, 0, 0, 0.12);
    }

    .strategy-kpi-card .value {
        font-size: 2.25rem;
        font-weight: 700;
        color: var(--strat-primary);
    }

    .strategy-kpi-card .label {
        color: #6c757d;
        font-size: 0.85rem;
        margin-top: 0.25rem;
    }

    .strategy-kpi-card.success .value {
        color: var(--strat-success);
    }

    .strategy-kpi-card.warning .value {
        color: var(--strat-warning);
    }

    .strategy-kpi-card.danger .value {
        color: var(--strat-danger);
    }

    .strategies-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .strategy-card {
        background: white;
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        transition: all 0.3s;
        border-left: 4px solid transparent;
        cursor: pointer;
    }

    .strategy-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .strategy-card.excellent {
        border-left-color: var(--strat-success);
    }

    .strategy-card.good {
        border-left-color: #56ab2f;
    }

    .strategy-card.warning {
        border-left-color: var(--strat-warning);
    }

    .strategy-card.critical {
        border-left-color: var(--strat-danger);
    }

    .strategy-card-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .strategy-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        background: linear-gradient(135deg, rgba(94, 96, 206, 0.1), rgba(72, 191, 227, 0.1));
    }

    .strategy-name {
        font-weight: 600;
        font-size: 0.95rem;
        color: #333;
        flex: 1;
    }

    .strategy-score {
        font-weight: 700;
        font-size: 1.1rem;
        padding: 0.25rem 0.75rem;
        border-radius: 8px;
    }

    .strategy-score.excellent {
        background: rgba(6, 214, 160, 0.15);
        color: #059669;
    }

    .strategy-score.good {
        background: rgba(86, 171, 47, 0.15);
        color: #3d7e22;
    }

    .strategy-score.warning {
        background: rgba(255, 209, 102, 0.2);
        color: #d49a00;
    }

    .strategy-score.critical {
        background: rgba(239, 71, 111, 0.1);
        color: #ef476f;
    }

    .strategy-description {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 0.75rem;
        line-height: 1.4;
    }

    .strategy-progress {
        height: 6px;
        background: #e9ecef;
        border-radius: 3px;
        overflow: hidden;
    }

    .strategy-progress .fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.5s ease;
    }

    .strategy-progress .fill.excellent {
        background: linear-gradient(90deg, #06d6a0, #38ef7d);
    }

    .strategy-progress .fill.good {
        background: linear-gradient(90deg, #56ab2f, #a8e063);
    }

    .strategy-progress .fill.warning {
        background: linear-gradient(90deg, #f7971e, #ffd200);
    }

    .strategy-progress .fill.critical {
        background: linear-gradient(90deg, #ef476f, #f45c43);
    }

    .strategy-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.75rem;
    }

    .strategy-action-btn {
        flex: 1;
        padding: 0.5rem;
        border: none;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .strategy-action-btn.primary {
        background: linear-gradient(135deg, #5e60ce, #48bfe3);
        color: white;
    }

    .strategy-action-btn.primary:hover {
        filter: brightness(1.1);
    }

    .strategy-action-btn.secondary {
        background: #f0f0f5;
        color: #555;
    }

    .strategy-action-btn.secondary:hover {
        background: #e0e0e8;
    }

    .analysis-panel {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        margin-bottom: 1.5rem;
    }

    .analysis-panel h5 {
        margin: 0 0 1rem 0;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .item-selector {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .item-selector .form-control {
        flex: 1;
    }

    .score-distribution {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
        padding: 1rem 0;
    }

    .score-segment {
        text-align: center;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        min-width: 80px;
    }

    .score-segment.excellent {
        background: rgba(6, 214, 160, 0.15);
    }

    .score-segment.good {
        background: rgba(86, 171, 47, 0.15);
    }

    .score-segment.warning {
        background: rgba(255, 209, 102, 0.2);
    }

    .score-segment.critical {
        background: rgba(239, 71, 111, 0.1);
    }

    .score-segment .count {
        font-size: 1.5rem;
        font-weight: 700;
    }

    .score-segment .label {
        font-size: 0.75rem;
        color: #6c757d;
    }

    .recommendations-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .recommendations-list li {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f0f0f5;
    }

    .recommendations-list li:last-child {
        border-bottom: none;
    }

    .recommendation-priority {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-top: 6px;
        flex-shrink: 0;
    }

    .recommendation-priority.high {
        background: var(--strat-danger);
    }

    .recommendation-priority.medium {
        background: var(--strat-warning);
    }

    .recommendation-priority.low {
        background: var(--strat-info);
    }

    .recommendation-content {
        flex: 1;
    }

    .recommendation-title {
        font-weight: 500;
        font-size: 0.9rem;
        margin-bottom: 0.25rem;
    }

    .recommendation-description {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .loading-overlay {
        position: absolute;
        inset: 0;
        background: rgba(255, 255, 255, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        z-index: 10;
    }

    .loading-overlay .spinner {
        width: 40px;
        height: 40px;
        border: 3px solid #f0f0f5;
        border-top-color: var(--strat-primary);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>

<div class="strategies-dashboard">
    <!-- Header -->
    <div class="strategies-header">
        <h2>🎯 SEO Strategies Engine</h2>
        <div class="subtitle">12 Estratégias Avançadas de Otimização para Mercado Livre</div>
    </div>

    <!-- KPI Grid -->
    <div class="strategy-kpi-grid" id="strategy-kpis">
        <div class="strategy-kpi-card">
            <div class="value" id="kpi-avg-score">-</div>
            <div class="label">Score Médio</div>
        </div>
        <div class="strategy-kpi-card success">
            <div class="value" id="kpi-excellent">-</div>
            <div class="label">Excelentes (80+)</div>
        </div>
        <div class="strategy-kpi-card warning">
            <div class="value" id="kpi-needs-work">-</div>
            <div class="label">Precisam Atenção</div>
        </div>
        <div class="strategy-kpi-card danger">
            <div class="value" id="kpi-critical">-</div>
            <div class="label">Críticos (&lt;40)</div>
        </div>
    </div>

    <!-- Item Analysis Panel -->
    <div class="analysis-panel">
        <h5><i class="fas fa-search"></i> Análise Individual</h5>
        <div class="item-selector">
            <input type="text" class="form-control" id="item-id-input" placeholder="Digite o ID do anúncio (ex: MLB1234567890)">
            <button class="btn btn-primary" onclick="StrategiesDashboard.analyzeItem()">
                <i class="fas fa-bolt"></i> Analisar
            </button>
            <button class="btn btn-outline-secondary" onclick="StrategiesDashboard.loadRandomItem()">
                <i class="fas fa-random"></i>
            </button>
        </div>

        <div class="score-distribution" id="score-distribution">
            <div class="score-segment excellent">
                <div class="count" id="dist-excellent">0</div>
                <div class="label">Excelente</div>
            </div>
            <div class="score-segment good">
                <div class="count" id="dist-good">0</div>
                <div class="label">Bom</div>
            </div>
            <div class="score-segment warning">
                <div class="count" id="dist-warning">0</div>
                <div class="label">Atenção</div>
            </div>
            <div class="score-segment critical">
                <div class="count" id="dist-critical">0</div>
                <div class="label">Crítico</div>
            </div>
        </div>
    </div>

    <!-- 12 Strategies Grid -->
    <h5 class="mb-3"><i class="fas fa-layer-group me-2"></i>As 12 Estratégias</h5>
    <div class="strategies-grid" id="strategies-grid">
        <!-- E1: Synonym Hierarchy -->
        <div class="strategy-card" data-strategy="synonyms" onclick="StrategiesDashboard.showStrategyDetails('synonyms')">
            <div class="strategy-card-header">
                <div class="strategy-icon">📚</div>
                <div class="strategy-name">E1: Hierarquia de Sinônimos</div>
                <div class="strategy-score" id="score-synonyms">-</div>
            </div>
            <div class="strategy-description">
                Sinônimos em 4 níveis: genérico → qualificado → contexto → long tail
            </div>
            <div class="strategy-progress">
                <div class="fill" id="progress-synonyms" style="width: 0%"></div>
            </div>
            <div class="strategy-actions">
                <button class="strategy-action-btn primary" onclick="event.stopPropagation(); StrategiesDashboard.optimize('synonyms')">
                    <i class="fas fa-magic"></i> Otimizar
                </button>
                <button class="strategy-action-btn secondary" onclick="event.stopPropagation(); StrategiesDashboard.showDetails('synonyms')">
                    <i class="fas fa-info-circle"></i> Detalhes
                </button>
            </div>
        </div>

        <!-- E2: Hidden Fields -->
        <div class="strategy-card" data-strategy="hidden_fields" onclick="StrategiesDashboard.showStrategyDetails('hidden_fields')">
            <div class="strategy-card-header">
                <div class="strategy-icon">🔮</div>
                <div class="strategy-name">E2: Campos Ocultos</div>
                <div class="strategy-score" id="score-hidden_fields">-</div>
            </div>
            <div class="strategy-description">
                KEYWORDS, MPN, GTIN, LINE - indexados mas não visíveis
            </div>
            <div class="strategy-progress">
                <div class="fill" id="progress-hidden_fields" style="width: 0%"></div>
            </div>
            <div class="strategy-actions">
                <button class="strategy-action-btn primary" onclick="event.stopPropagation(); StrategiesDashboard.optimize('hidden_fields')">
                    <i class="fas fa-magic"></i> Otimizar
                </button>
                <button class="strategy-action-btn secondary" onclick="event.stopPropagation(); StrategiesDashboard.showDetails('hidden_fields')">
                    <i class="fas fa-info-circle"></i> Detalhes
                </button>
            </div>
        </div>

        <!-- E3: Natural Injection -->
        <div class="strategy-card" data-strategy="injection" onclick="StrategiesDashboard.showStrategyDetails('injection')">
            <div class="strategy-card-header">
                <div class="strategy-icon">💉</div>
                <div class="strategy-name">E3: Injeção Natural</div>
                <div class="strategy-score" id="score-injection">-</div>
            </div>
            <div class="strategy-description">
                Inserir keywords naturalmente sem repetição forçada
            </div>
            <div class="strategy-progress">
                <div class="fill" id="progress-injection" style="width: 0%"></div>
            </div>
            <div class="strategy-actions">
                <button class="strategy-action-btn primary" onclick="event.stopPropagation(); StrategiesDashboard.optimize('injection')">
                    <i class="fas fa-magic"></i> Otimizar
                </button>
                <button class="strategy-action-btn secondary" onclick="event.stopPropagation(); StrategiesDashboard.showDetails('injection')">
                    <i class="fas fa-info-circle"></i> Detalhes
                </button>
            </div>
        </div>

        <!-- E4: Search Coverage -->
        <div class="strategy-card" data-strategy="coverage" onclick="StrategiesDashboard.showStrategyDetails('coverage')">
            <div class="strategy-card-header">
                <div class="strategy-icon">🔍</div>
                <div class="strategy-name">E4: Cobertura de Buscas</div>
                <div class="strategy-score" id="score-coverage">-</div>
            </div>
            <div class="strategy-description">
                Cobrir todos os tipos de busca: exata, parcial, semântica
            </div>
            <div class="strategy-progress">
                <div class="fill" id="progress-coverage" style="width: 0%"></div>
            </div>
            <div class="strategy-actions">
                <button class="strategy-action-btn primary" onclick="event.stopPropagation(); StrategiesDashboard.optimize('coverage')">
                    <i class="fas fa-magic"></i> Otimizar
                </button>
                <button class="strategy-action-btn secondary" onclick="event.stopPropagation(); StrategiesDashboard.showDetails('coverage')">
                    <i class="fas fa-info-circle"></i> Detalhes
                </button>
            </div>
        </div>

        <!-- E5: Field Weights -->
        <div class="strategy-card" data-strategy="weights" onclick="StrategiesDashboard.showStrategyDetails('weights')">
            <div class="strategy-card-header">
                <div class="strategy-icon">⚖️</div>
                <div class="strategy-name">E5: Pesos de Campos</div>
                <div class="strategy-score" id="score-weights">-</div>
            </div>
            <div class="strategy-description">
                Título (10x) > Modelo (5x) > Atributos (3x) > Descrição (1x)
            </div>
            <div class="strategy-progress">
                <div class="fill" id="progress-weights" style="width: 0%"></div>
            </div>
            <div class="strategy-actions">
                <button class="strategy-action-btn primary" onclick="event.stopPropagation(); StrategiesDashboard.optimize('weights')">
                    <i class="fas fa-magic"></i> Otimizar
                </button>
                <button class="strategy-action-btn secondary" onclick="event.stopPropagation(); StrategiesDashboard.showDetails('weights')">
                    <i class="fas fa-info-circle"></i> Detalhes
                </button>
            </div>
        </div>

        <!-- E6: Use Contexts -->
        <div class="strategy-card" data-strategy="contexts" onclick="StrategiesDashboard.showStrategyDetails('contexts')">
            <div class="strategy-card-header">
                <div class="strategy-icon">📍</div>
                <div class="strategy-name">E6: Contextos de Uso</div>
                <div class="strategy-score" id="score-contexts">-</div>
            </div>
            <div class="strategy-description">
                Adicionar situações: "para viagem", "uso diário", "profissional"
            </div>
            <div class="strategy-progress">
                <div class="fill" id="progress-contexts" style="width: 0%"></div>
            </div>
            <div class="strategy-actions">
                <button class="strategy-action-btn primary" onclick="event.stopPropagation(); StrategiesDashboard.optimize('contexts')">
                    <i class="fas fa-magic"></i> Otimizar
                </button>
                <button class="strategy-action-btn secondary" onclick="event.stopPropagation(); StrategiesDashboard.showDetails('contexts')">
                    <i class="fas fa-info-circle"></i> Detalhes
                </button>
            </div>
        </div>

        <!-- E7: Long Tail -->
        <div class="strategy-card" data-strategy="long_tail" onclick="StrategiesDashboard.showStrategyDetails('long_tail')">
            <div class="strategy-card-header">
                <div class="strategy-icon">🎯</div>
                <div class="strategy-name">E7: Long Tail</div>
                <div class="strategy-score" id="score-long_tail">-</div>
            </div>
            <div class="strategy-description">
                Gerar variações específicas: marca + modelo + especificação
            </div>
            <div class="strategy-progress">
                <div class="fill" id="progress-long_tail" style="width: 0%"></div>
            </div>
            <div class="strategy-actions">
                <button class="strategy-action-btn primary" onclick="event.stopPropagation(); StrategiesDashboard.optimize('long_tail')">
                    <i class="fas fa-magic"></i> Otimizar
                </button>
                <button class="strategy-action-btn secondary" onclick="event.stopPropagation(); StrategiesDashboard.showDetails('long_tail')">
                    <i class="fas fa-info-circle"></i> Detalhes
                </button>
            </div>
        </div>

        <!-- E8: Density Control -->
        <div class="strategy-card" data-strategy="density" onclick="StrategiesDashboard.showStrategyDetails('density')">
            <div class="strategy-card-header">
                <div class="strategy-icon">📊</div>
                <div class="strategy-name">E8: Controle de Densidade</div>
                <div class="strategy-score" id="score-density">-</div>
            </div>
            <div class="strategy-description">
                Densidade ideal: 1-3% para keywords principais
            </div>
            <div class="strategy-progress">
                <div class="fill" id="progress-density" style="width: 0%"></div>
            </div>
            <div class="strategy-actions">
                <button class="strategy-action-btn primary" onclick="event.stopPropagation(); StrategiesDashboard.optimize('density')">
                    <i class="fas fa-magic"></i> Otimizar
                </button>
                <button class="strategy-action-btn secondary" onclick="event.stopPropagation(); StrategiesDashboard.showDetails('density')">
                    <i class="fas fa-info-circle"></i> Detalhes
                </button>
            </div>
        </div>

        <!-- E9: Semantic Score -->
        <div class="strategy-card" data-strategy="semantic" onclick="StrategiesDashboard.showStrategyDetails('semantic')">
            <div class="strategy-card-header">
                <div class="strategy-icon">🧠</div>
                <div class="strategy-name">E9: Score Semântico</div>
                <div class="strategy-score" id="score-semantic">-</div>
            </div>
            <div class="strategy-description">
                Relevância semântica entre keywords e categoria
            </div>
            <div class="strategy-progress">
                <div class="fill" id="progress-semantic" style="width: 0%"></div>
            </div>
            <div class="strategy-actions">
                <button class="strategy-action-btn primary" onclick="event.stopPropagation(); StrategiesDashboard.optimize('semantic')">
                    <i class="fas fa-magic"></i> Otimizar
                </button>
                <button class="strategy-action-btn secondary" onclick="event.stopPropagation(); StrategiesDashboard.showDetails('semantic')">
                    <i class="fas fa-info-circle"></i> Detalhes
                </button>
            </div>
        </div>

        <!-- E10: Compatibility -->
        <div class="strategy-card" data-strategy="compatibility" onclick="StrategiesDashboard.showStrategyDetails('compatibility')">
            <div class="strategy-card-header">
                <div class="strategy-icon">🔗</div>
                <div class="strategy-name">E10: Compatibilidade</div>
                <div class="strategy-score" id="score-compatibility">-</div>
            </div>
            <div class="strategy-description">
                Expandir compatibilidades: modelos, anos, versões
            </div>
            <div class="strategy-progress">
                <div class="fill" id="progress-compatibility" style="width: 0%"></div>
            </div>
            <div class="strategy-actions">
                <button class="strategy-action-btn primary" onclick="event.stopPropagation(); StrategiesDashboard.optimize('compatibility')">
                    <i class="fas fa-magic"></i> Otimizar
                </button>
                <button class="strategy-action-btn secondary" onclick="event.stopPropagation(); StrategiesDashboard.showDetails('compatibility')">
                    <i class="fas fa-info-circle"></i> Detalhes
                </button>
            </div>
        </div>

        <!-- E11: FAQ Optimizer -->
        <div class="strategy-card" data-strategy="faq" onclick="StrategiesDashboard.showStrategyDetails('faq')">
            <div class="strategy-card-header">
                <div class="strategy-icon">❓</div>
                <div class="strategy-name">E11: FAQs Estratégicos</div>
                <div class="strategy-score" id="score-faq">-</div>
            </div>
            <div class="strategy-description">
                Perguntas frequentes com keywords na descrição
            </div>
            <div class="strategy-progress">
                <div class="fill" id="progress-faq" style="width: 0%"></div>
            </div>
            <div class="strategy-actions">
                <button class="strategy-action-btn primary" onclick="event.stopPropagation(); StrategiesDashboard.optimize('faq')">
                    <i class="fas fa-magic"></i> Otimizar
                </button>
                <button class="strategy-action-btn secondary" onclick="event.stopPropagation(); StrategiesDashboard.showDetails('faq')">
                    <i class="fas fa-info-circle"></i> Detalhes
                </button>
            </div>
        </div>

        <!-- E12: Monitoring Engine -->
        <div class="strategy-card" data-strategy="monitoring" onclick="StrategiesDashboard.showStrategyDetails('monitoring')">
            <div class="strategy-card-header">
                <div class="strategy-icon">📈</div>
                <div class="strategy-name">E12: Monitoramento</div>
                <div class="strategy-score" id="score-monitoring">-</div>
            </div>
            <div class="strategy-description">
                Tracking contínuo de performance e ajustes
            </div>
            <div class="strategy-progress">
                <div class="fill" id="progress-monitoring" style="width: 0%"></div>
            </div>
            <div class="strategy-actions">
                <button class="strategy-action-btn primary" onclick="event.stopPropagation(); StrategiesDashboard.optimize('monitoring')">
                    <i class="fas fa-magic"></i> Otimizar
                </button>
                <button class="strategy-action-btn secondary" onclick="event.stopPropagation(); StrategiesDashboard.showDetails('monitoring')">
                    <i class="fas fa-info-circle"></i> Detalhes
                </button>
            </div>
        </div>
    </div>

    <!-- Recommendations Panel -->
    <div class="analysis-panel">
        <h5><i class="fas fa-lightbulb"></i> Recomendações Prioritárias</h5>
        <ul class="recommendations-list" id="recommendations-list">
            <li>
                <span class="recommendation-priority high"></span>
                <div class="recommendation-content">
                    <div class="recommendation-title">Carregando recomendações...</div>
                    <div class="recommendation-description">Aguarde a análise do sistema</div>
                </div>
            </li>
        </ul>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
    const StrategiesDashboard = {
        currentItemId: null,

        init() {
            this.loadDashboardData();
        },

        async loadDashboardData() {
            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/strategies/dashboard');

                if (data.success) {
                    this.renderDashboard(data.dashboard);
                }
            } catch (e) {
                console.error('Error loading dashboard:', e);
            }
        },

        renderDashboard(dashboard) {
            const stats = dashboard.cache_stats || {};
            const dist = dashboard.score_distribution || {};

            document.getElementById('kpi-avg-score').textContent = stats.avg_score ? Math.round(stats.avg_score) : '-';
            document.getElementById('kpi-excellent').textContent = dist.excellent || 0;
            document.getElementById('kpi-needs-work').textContent = (dist.warning || 0) + (dist.good || 0);
            document.getElementById('kpi-critical').textContent = dist.critical || 0;

            document.getElementById('dist-excellent').textContent = dist.excellent || 0;
            document.getElementById('dist-good').textContent = dist.good || 0;
            document.getElementById('dist-warning').textContent = dist.warning || 0;
            document.getElementById('dist-critical').textContent = dist.critical || 0;

            // Show low score items as recommendations
            const lowItems = dashboard.low_score_items || [];
            this.renderRecommendations(lowItems);
        },

        renderRecommendations(items) {
            const list = document.getElementById('recommendations-list');

            if (!items.length) {
                list.innerHTML = `
                <li>
                    <span class="recommendation-priority low"></span>
                    <div class="recommendation-content">
                        <div class="recommendation-title">Nenhuma recomendação urgente</div>
                        <div class="recommendation-description">Todos os itens analisados têm score adequado</div>
                    </div>
                </li>
            `;
                return;
            }

            list.innerHTML = items.slice(0, 5).map(item => `
            <li>
                <span class="recommendation-priority ${item.score < 30 ? 'high' : item.score < 50 ? 'medium' : 'low'}"></span>
                <div class="recommendation-content">
                    <div class="recommendation-title">${this.escapeHtml(item.title || item.item_id)}</div>
                    <div class="recommendation-description">
                        Score: ${Math.round(item.score)}% - 
                        <a href="#" onclick="StrategiesDashboard.analyzeItemById('${item.item_id}'); return false;">Analisar</a>
                    </div>
                </div>
            </li>
        `).join('');
        },

        async analyzeItem() {
            const input = document.getElementById('item-id-input');
            const itemId = input.value.trim();

            if (!itemId) {
                alert('Digite um ID de anúncio');
                return;
            }

            await this.analyzeItemById(itemId);
        },

        async analyzeItemById(itemId) {
            this.currentItemId = itemId;
            document.getElementById('item-id-input').value = itemId;

            // Reset all scores
            this.resetScores();

            try {
                const {
                    data
                } = await requestJson(`/api/seo-killer/strategies/analyze/${encodeURIComponent(itemId)}`);

                if (data.success && data.analysis) {
                    this.renderAnalysis(data.analysis);
                } else {
                    alert(data.error || 'Erro ao analisar item');
                }
            } catch (e) {
                alert('Erro de conexão');
            }
        },

        resetScores() {
            const strategies = ['synonyms', 'hidden_fields', 'injection', 'coverage', 'weights',
                'contexts', 'long_tail', 'density', 'semantic', 'compatibility', 'faq', 'monitoring'
            ];

            strategies.forEach(s => {
                const scoreEl = document.getElementById(`score-${s}`);
                const progressEl = document.getElementById(`progress-${s}`);
                const card = document.querySelector(`[data-strategy="${s}"]`);

                if (scoreEl) scoreEl.textContent = '-';
                if (progressEl) progressEl.style.width = '0%';
                if (card) card.className = 'strategy-card';
            });
        },

        renderAnalysis(analysis) {
            const strategies = analysis.strategies || {};
            const mapping = {
                'synonyms': strategies.synonyms || strategies.synonym_expansion,
                'hidden_fields': strategies.hidden_fields,
                'injection': strategies.injection || strategies.keyword_injector,
                'coverage': strategies.coverage || strategies.search_coverage,
                'weights': strategies.weights || strategies.field_weights,
                'contexts': strategies.contexts || strategies.use_contexts,
                'long_tail': strategies.long_tail,
                'density': strategies.density || {
                    score: 75
                },
                'semantic': strategies.semantic || strategies.semantic_score,
                'compatibility': strategies.compatibility,
                'faq': strategies.faq,
                'monitoring': {
                    score: analysis.overall_score || 50
                }
            };

            Object.entries(mapping).forEach(([key, data]) => {
                const score = data?.score || 0;
                this.updateStrategyCard(key, score);
            });
        },

        updateStrategyCard(strategy, score) {
            const scoreEl = document.getElementById(`score-${strategy}`);
            const progressEl = document.getElementById(`progress-${strategy}`);
            const card = document.querySelector(`[data-strategy="${strategy}"]`);

            const scoreClass = score >= 80 ? 'excellent' : score >= 60 ? 'good' : score >= 40 ? 'warning' : 'critical';

            if (scoreEl) {
                scoreEl.textContent = `${Math.round(score)}%`;
                scoreEl.className = `strategy-score ${scoreClass}`;
            }

            if (progressEl) {
                progressEl.style.width = `${score}%`;
                progressEl.className = `fill ${scoreClass}`;
            }

            if (card) {
                card.className = `strategy-card ${scoreClass}`;
            }
        },

        async optimize(strategy) {
            if (!this.currentItemId) {
                alert('Primeiro analise um item');
                return;
            }

            if (!confirm(`Otimizar estratégia "${strategy}" para o item ${this.currentItemId}?`)) {
                return;
            }

            try {
                const {
                    data
                } = await requestJson(`/api/seo-killer/strategies/optimize/${encodeURIComponent(this.currentItemId)}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        strategy
                    })
                });

                if (data.success) {
                    alert('✅ Otimização aplicada com sucesso!');
                    await this.analyzeItemById(this.currentItemId);
                } else {
                    alert(data.error || 'Erro ao otimizar');
                }
            } catch (e) {
                alert('Erro de conexão');
            }
        },

        showDetails(strategy) {
            alert(`Detalhes da estratégia: ${strategy}\n\nEm desenvolvimento...`);
        },

        showStrategyDetails(strategy) {
            // Could open a modal with detailed info
            console.log('Show details for:', strategy);
        },

        async loadRandomItem() {
            try {
                const {
                    data
                } = await requestJson('/api/seo-killer/bulk/select?limit=1&random=1');

                if (data.items && data.items.length > 0) {
                    const itemId = data.items[0].id || data.items[0].item_id;
                    if (itemId) {
                        await this.analyzeItemById(itemId);
                    }
                }
            } catch (e) {
                console.error('Error loading random item:', e);
            }
        },

        escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    };

    // Initialize on load
    document.addEventListener('DOMContentLoaded', () => StrategiesDashboard.init());
</script>