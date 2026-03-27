<?php

declare(strict_types=1);

$title = 'Business Intelligence';
$subtitle = 'Analytics avançados e insights preditivos';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<style>
    .analytics-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        height: 100%;
    }

    .metric-big {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--primary-color, #6f42c1);
    }

    .metric-label {
        font-size: 0.9rem;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .trend-up {
        color: #28a745;
    }

    .trend-down {
        color: #dc3545;
    }

    .chart-container {
        position: relative;
        height: 300px;
    }
</style>

<!-- Real-Time Summary Cards -->
<div class="row g-4 mb-4" id="summary-cards">
    <div class="col-md-3">
        <div class="analytics-card">
            <div class="metric-label">Receita Hoje</div>
            <div class="metric-big" id="revenue-today">R$ 0</div>
            <small class="trend-up" id="growth-rate">+0%</small> vs ontem
        </div>
    </div>
    <div class="col-md-3">
        <div class="analytics-card">
            <div class="metric-label">Perguntas Pendentes</div>
            <div class="metric-big text-warning" id="pending-questions">0</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="analytics-card">
            <div class="metric-label">Itens Ativos</div>
            <div class="metric-big text-info" id="active-items">0</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="analytics-card">
            <div class="metric-label">Taxa Conversão</div>
            <div class="metric-big text-success" id="conversion-rate">0%</div>
        </div>
    </div>
</div>

<!-- Interactive Charts -->
<div class="row g-4 mb-4">
    <!-- Revenue Trend -->
    <div class="col-lg-8">
        <div class="analytics-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">📈 Evolução de Receita</h5>
                <select class="form-select form-select-sm w-auto" id="period-selector">
                    <option value="7">Últimos 7 dias</option>
                    <option value="30" selected>Últimos 30 dias</option>
                    <option value="90">Últimos 90 dias</option>
                </select>
            </div>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Customer Segments -->
    <div class="col-lg-4">
        <div class="analytics-card">
            <h5 class="mb-3">👥 Segmentos de Clientes (LTV)</h5>
            <div class="chart-container">
                <canvas id="ltvChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Profit Margins -->
    <div class="col-lg-6">
        <div class="analytics-card">
            <h5 class="mb-3">💰 Margens de Lucro por Tipo</h5>
            <div class="chart-container">
                <canvas id="marginChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Inventory Turnover -->
    <div class="col-lg-6">
        <div class="analytics-card">
            <h5 class="mb-3">🔄 Giro de Estoque (Top Categorias)</h5>
            <div id="turnover-table"></div>
        </div>
    </div>
</div>

<!-- Forecast Section -->
<div class="row g-4">
    <div class="col-12">
        <div class="analytics-card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h5 class="mb-3">🔮 Previsão de Receita (Próximos 7 Dias)</h5>
            <div class="chart-container">
                <canvas id="forecastChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="/js/analytics-dashboard.js?v=<?= @filemtime(__DIR__ . '/../../../../public/js/analytics-dashboard.js') ?: time() ?>"></script>
