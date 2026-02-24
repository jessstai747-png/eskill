<?php

/**
 * Dashboard Principal - Design Moderno e Profissional
 * Visão geral completa do ecossistema de vendas
 */

$pageTitle = 'Dashboard';
$currentDate = date('d/m/Y');
$greeting = (date('H') < 12) ? 'Bom dia' : ((date('H') < 18) ? 'Boa tarde' : 'Boa noite');
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Usuário');
?>

<style>
    /* Dashboard Hero */
    .dashboard-hero {
        background: var(--primary-gradient);
        border-radius: 16px;
        padding: 2rem;
        color: #fff;
        position: relative;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .dashboard-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .dashboard-hero::after {
        content: '';
        position: absolute;
        bottom: -30%;
        right: 20%;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
    }

    .hero-content {
        position: relative;
        z-index: 1;
    }

    .hero-greeting {
        font-size: 0.875rem;
        opacity: 0.9;
        margin-bottom: 0.25rem;
    }

    .hero-title {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .hero-subtitle {
        font-size: 0.9rem;
        opacity: 0.85;
    }

    .hero-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
    }

    .hero-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 10px;
        color: #fff;
        font-weight: 500;
        font-size: 0.875rem;
        text-decoration: none;
        transition: all 0.2s ease;
        backdrop-filter: blur(10px);
    }

    .hero-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        color: #fff;
        transform: translateY(-2px);
    }

    .hero-btn-primary {
        background: #fff;
        color: #667eea;
        border-color: #fff;
    }

    .hero-btn-primary:hover {
        background: #f8fafc;
        color: #5a67d8;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    @media (max-width: 1199.98px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 575.98px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Metric Card */
    .metric-card {
        background: #fff;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .metric-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
    }

    .metric-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--metric-color, var(--primary-gradient));
    }

    .metric-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .metric-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: #fff;
    }

    .metric-icon.primary {
        background: var(--primary-gradient);
    }

    .metric-icon.success {
        background: var(--success-gradient);
    }

    .metric-icon.warning {
        background: var(--warning-gradient);
    }

    .metric-icon.danger {
        background: var(--danger-gradient);
    }

    .metric-icon.info {
        background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
    }

    .metric-change {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.25rem 0.5rem;
        border-radius: 50px;
    }

    .metric-change.up {
        background: rgba(34, 197, 94, 0.1);
        color: #16a34a;
    }

    .metric-change.down {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }

    .metric-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: #1e293b;
        line-height: 1.2;
        margin-bottom: 0.25rem;
    }

    .metric-label {
        font-size: 0.8125rem;
        color: #64748b;
        font-weight: 500;
    }

    .metric-card[data-color="primary"]::before {
        --metric-color: var(--primary-gradient);
    }

    .metric-card[data-color="success"]::before {
        --metric-color: var(--success-gradient);
    }

    .metric-card[data-color="warning"]::before {
        --metric-color: var(--warning-gradient);
    }

    .metric-card[data-color="danger"]::before {
        --metric-color: var(--danger-gradient);
    }

    .metric-card[data-color="info"]::before {
        --metric-color: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
    }

    /* Content Sections */
    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .section-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .section-title i {
        color: #667eea;
    }

    /* Account Cards */
    .accounts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
    }

    .account-card {
        background: #fff;
        border-radius: 16px;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        border: 2px solid transparent;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .account-card:hover {
        border-color: #667eea;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
    }

    .account-card.active {
        border-color: #22c55e;
        background: linear-gradient(135deg, rgba(34, 197, 94, 0.05) 0%, rgba(22, 163, 74, 0.02) 100%);
    }

    .account-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .account-avatar {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        object-fit: cover;
        background: #f1f5f9;
    }

    .account-info {
        flex: 1;
        min-width: 0;
    }

    .account-name {
        font-weight: 600;
        color: #1e293b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .account-email {
        font-size: 0.75rem;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .account-status {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        font-size: 0.6875rem;
        font-weight: 600;
        padding: 0.25rem 0.625rem;
        border-radius: 50px;
        text-transform: uppercase;
    }

    .account-status.active {
        background: rgba(34, 197, 94, 0.1);
        color: #16a34a;
    }

    .account-status.inactive {
        background: rgba(100, 116, 139, 0.1);
        color: #64748b;
    }

    .account-badges {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.5rem;
        flex-wrap: wrap;
    }

    .account-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        font-size: 0.6875rem;
        font-weight: 600;
        padding: 0.25rem 0.625rem;
        border-radius: 50px;
        text-transform: uppercase;
    }

    .badge-connected {
        background: rgba(34, 197, 94, 0.1);
        color: #16a34a;
    }

    .badge-expiring {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }

    .badge-disconnected {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }

    .badge-unknown {
        background: rgba(148, 163, 184, 0.1);
        color: #64748b;
    }

    .badge-synced {
        background: rgba(59, 130, 246, 0.1);
        color: #2563eb;
    }

    .badge-unsynced {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }

    .badge-syncing {
        background: rgba(14, 165, 233, 0.1);
        color: #0ea5e9;
    }

    .account-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.75rem;
    }

    .account-actions .btn {
        flex: 1;
    }

    .account-card.pending {
        opacity: 0.7;
        pointer-events: none;
    }

    .account-metrics {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        padding-top: 0.75rem;
        border-top: 1px solid #f1f5f9;
    }

    .account-metric {
        text-align: center;
    }

    .account-metric-value {
        font-size: 1rem;
        font-weight: 700;
        color: #1e293b;
    }

    .account-metric-label {
        font-size: 0.6875rem;
        color: #94a3b8;
        text-transform: uppercase;
    }

    /* Charts Section */
    .chart-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        height: 100%;
    }

    .chart-header {
        padding: 1.25rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .chart-title {
        font-weight: 600;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .chart-title i {
        color: #667eea;
    }

    .chart-body {
        padding: 1.25rem;
    }

    /* Orders List */
    .orders-list {
        max-height: 360px;
        overflow-y: auto;
    }

    .order-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.875rem;
        border-radius: 10px;
        transition: background 0.2s ease;
    }

    .order-item:hover {
        background: #f8fafc;
    }

    .order-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .order-icon.paid {
        background: rgba(34, 197, 94, 0.1);
        color: #16a34a;
    }

    .order-icon.pending {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }

    .order-icon.shipped {
        background: rgba(59, 130, 246, 0.1);
        color: #2563eb;
    }

    .order-icon.cancelled {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }

    .order-content {
        flex: 1;
        min-width: 0;
    }

    .order-id {
        font-weight: 600;
        color: #1e293b;
        font-size: 0.875rem;
    }

    .order-date {
        font-size: 0.75rem;
        color: #94a3b8;
    }

    .order-amount {
        font-weight: 600;
        color: #1e293b;
    }

    /* Reputation Card */
    .reputation-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        padding: 1.5rem;
        text-align: center;
    }

    .reputation-score {
        position: relative;
        width: 140px;
        height: 140px;
        margin: 0 auto 1.5rem;
    }

    .reputation-circle {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: conic-gradient(#22c55e 0% var(--score-percent, 85%),
                #e2e8f0 var(--score-percent, 85%) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    .reputation-circle::before {
        content: '';
        position: absolute;
        width: 110px;
        height: 110px;
        background: #fff;
        border-radius: 50%;
    }

    .reputation-value {
        position: relative;
        z-index: 1;
        font-size: 2rem;
        font-weight: 700;
        color: #22c55e;
    }

    .reputation-label {
        font-size: 0.75rem;
        color: #64748b;
        margin-bottom: 0.5rem;
    }

    .reputation-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        color: #fff;
        font-weight: 600;
        font-size: 0.75rem;
        border-radius: 50px;
        text-transform: uppercase;
    }

    .reputation-metrics {
        margin-top: 1.5rem;
    }

    .rep-metric {
        margin-bottom: 1rem;
    }

    .rep-metric:last-child {
        margin-bottom: 0;
    }

    .rep-metric-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.8125rem;
    }

    .rep-metric-name {
        color: #64748b;
    }

    .rep-metric-value {
        font-weight: 600;
        color: #1e293b;
    }

    .rep-progress {
        height: 6px;
        background: #e2e8f0;
        border-radius: 3px;
        overflow: hidden;
    }

    .rep-progress-bar {
        height: 100%;
        border-radius: 3px;
        transition: width 0.6s ease;
    }

    .rep-progress-bar.success {
        background: var(--success-gradient);
    }

    .rep-progress-bar.warning {
        background: var(--warning-gradient);
    }

    .rep-progress-bar.danger {
        background: var(--danger-gradient);
    }

    /* Quick Actions */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
    }

    .quick-action {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.25rem;
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s ease;
    }

    .quick-action:hover {
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        transform: translateY(-2px);
        color: inherit;
    }

    .quick-action-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: #fff;
        flex-shrink: 0;
    }

    .quick-action-content {
        flex: 1;
        min-width: 0;
    }

    .quick-action-title {
        font-weight: 600;
        color: #1e293b;
        font-size: 0.9375rem;
    }

    .quick-action-desc {
        font-size: 0.75rem;
        color: #64748b;
    }

    /* Empty State */
    .empty-state-card {
        background: #fff;
        border-radius: 16px;
        padding: 3rem;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .empty-state-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 1.5rem;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .empty-state-icon i {
        font-size: 2rem;
        color: #667eea;
    }

    .empty-state-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }

    .empty-state-desc {
        color: #64748b;
        margin-bottom: 1.5rem;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
    }

    /* Skeleton Loading */
    .skeleton-metric {
        background: #fff;
        border-radius: 16px;
        padding: 1.5rem;
    }

    .skeleton-value {
        height: 2rem;
        width: 60%;
        margin-bottom: 0.5rem;
    }

    .skeleton-label {
        height: 1rem;
        width: 40%;
    }

    /* Dark Theme */
    [data-theme="dark"] .dashboard-hero {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    }

    [data-theme="dark"] .metric-card,
    [data-theme="dark"] .account-card,
    [data-theme="dark"] .chart-card,
    [data-theme="dark"] .reputation-card,
    [data-theme="dark"] .quick-action,
    [data-theme="dark"] .empty-state-card {
        background: #1e293b;
        border-color: #334155;
    }

    [data-theme="dark"] .metric-value,
    [data-theme="dark"] .metric-label,
    [data-theme="dark"] .account-name,
    [data-theme="dark"] .chart-title,
    [data-theme="dark"] .order-id,
    [data-theme="dark"] .order-amount,
    [data-theme="dark"] .quick-action-title,
    [data-theme="dark"] .rep-metric-value,
    [data-theme="dark"] .section-title,
    [data-theme="dark"] .empty-state-title {
        color: #f1f5f9;
    }

    [data-theme="dark"] .account-email,
    [data-theme="dark"] .order-date,
    [data-theme="dark"] .quick-action-desc,
    [data-theme="dark"] .rep-metric-name,
    [data-theme="dark"] .reputation-label,
    [data-theme="dark"] .empty-state-desc {
        color: #94a3b8;
    }

    [data-theme="dark"] .chart-header,
    [data-theme="dark"] .account-metrics {
        border-color: #334155;
    }

    [data-theme="dark"] .order-item:hover {
        background: #334155;
    }

    [data-theme="dark"] .reputation-circle::before {
        background: #1e293b;
    }

    [data-theme="dark"] .rep-progress {
        background: #334155;
    }
</style>

<!-- Dashboard Hero -->
<div class="dashboard-hero">
    <div class="hero-content">
        <p class="hero-greeting"><?= $greeting ?>, <?= $userName ?>!</p>
        <h1 class="hero-title">Bem-vindo ao seu Dashboard</h1>
        <p class="hero-subtitle">Acompanhe suas vendas, gerencie pedidos e otimize seu negócio no Mercado Livre</p>

        <div class="hero-actions">
            <a href="/auth/authorize" class="hero-btn hero-btn-primary">
                <i class="bi bi-plus-lg"></i>
                Conectar Conta
            </a>
            <a href="/dashboard/items" class="hero-btn">
                <i class="bi bi-box-seam"></i>
                Meus Produtos
            </a>
            <a href="/dashboard/orders" class="hero-btn">
                <i class="bi bi-receipt"></i>
                Ver Pedidos
            </a>
        </div>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid" id="stats-grid">
    <!-- Loading skeletons -->
    <div class="metric-card skeleton-metric">
        <div class="skeleton skeleton-value"></div>
        <div class="skeleton skeleton-label"></div>
    </div>
    <div class="metric-card skeleton-metric">
        <div class="skeleton skeleton-value"></div>
        <div class="skeleton skeleton-label"></div>
    </div>
    <div class="metric-card skeleton-metric">
        <div class="skeleton skeleton-value"></div>
        <div class="skeleton skeleton-label"></div>
    </div>
    <div class="metric-card skeleton-metric">
        <div class="skeleton skeleton-value"></div>
        <div class="skeleton skeleton-label"></div>
    </div>
</div>

<!-- Accounts Section -->
<div class="section-header">
    <h2 class="section-title">
        <i class="bi bi-people"></i>
        Contas Conectadas
    </h2>
    <a href="/auth/authorize" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i>
        Nova Conta
    </a>
</div>

<div class="accounts-grid mb-4" id="accounts-grid">
    <!-- Loading state -->
    <div class="account-card">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="skeleton" style="width: 48px; height: 48px; border-radius: 12px;"></div>
            <div class="flex-1">
                <div class="skeleton mb-2" style="height: 1rem; width: 120px;"></div>
                <div class="skeleton" style="height: 0.75rem; width: 160px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <!-- Orders Chart -->
    <div class="col-lg-4">
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">
                    <i class="bi bi-pie-chart"></i>
                    Pedidos por Status
                </span>
            </div>
            <div class="chart-body">
                <canvas id="ordersChart" height="220"></canvas>
            </div>
        </div>
    </div>

    <!-- Revenue Chart -->
    <div class="col-lg-4">
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">
                    <i class="bi bi-graph-up"></i>
                    Receita (7 dias)
                </span>
            </div>
            <div class="chart-body">
                <canvas id="revenueChart" height="220"></canvas>
            </div>
        </div>
    </div>

    <!-- Reputation -->
    <div class="col-lg-4">
        <div class="reputation-card" id="reputation-card">
            <p class="reputation-label">Sua Reputação</p>
            <div class="reputation-score">
                <div class="reputation-circle" style="--score-percent: 0%">
                    <span class="reputation-value" id="rep-score">--</span>
                </div>
            </div>
            <div class="reputation-badge" id="rep-badge">
                <i class="bi bi-award"></i>
                <span>Carregando...</span>
            </div>

            <div class="reputation-metrics">
                <div class="rep-metric">
                    <div class="rep-metric-header">
                        <span class="rep-metric-name">Reclamações</span>
                        <span class="rep-metric-value" id="rep-claims">--%</span>
                    </div>
                    <div class="rep-progress">
                        <div class="rep-progress-bar success" id="bar-claims" style="width: 0%"></div>
                    </div>
                </div>
                <div class="rep-metric">
                    <div class="rep-metric-header">
                        <span class="rep-metric-name">Cancelamentos</span>
                        <span class="rep-metric-value" id="rep-cancel">--%</span>
                    </div>
                    <div class="rep-progress">
                        <div class="rep-progress-bar success" id="bar-cancel" style="width: 0%"></div>
                    </div>
                </div>
                <div class="rep-metric">
                    <div class="rep-metric-header">
                        <span class="rep-metric-name">Atrasos</span>
                        <span class="rep-metric-value" id="rep-delay">--%</span>
                    </div>
                    <div class="rep-progress">
                        <div class="rep-progress-bar success" id="bar-delay" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">
                    <i class="bi bi-clock-history"></i>
                    Pedidos Recentes
                </span>
                <a href="/dashboard/orders" class="btn btn-sm btn-outline-primary">Ver Todos</a>
            </div>
            <div class="chart-body p-0">
                <div class="orders-list" id="orders-list">
                    <!-- Loading -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="chart-card h-100">
            <div class="chart-header">
                <span class="chart-title">
                    <i class="bi bi-lightning"></i>
                    Ações Rápidas
                </span>
            </div>
            <div class="chart-body">
                <div class="d-grid gap-2">
                    <a href="/dashboard/items/create" class="quick-action">
                        <div class="quick-action-icon" style="background: var(--primary-gradient);">
                            <i class="bi bi-plus-lg"></i>
                        </div>
                        <div class="quick-action-content">
                            <div class="quick-action-title">Novo Anúncio</div>
                            <div class="quick-action-desc">Criar produto</div>
                        </div>
                    </a>
                    <a href="/dashboard/seo-killer" class="quick-action">
                        <div class="quick-action-icon" style="background: var(--success-gradient);">
                            <i class="bi bi-fire"></i>
                        </div>
                        <div class="quick-action-content">
                            <div class="quick-action-title">SEO Killer</div>
                            <div class="quick-action-desc">Otimizar anúncios</div>
                        </div>
                    </a>
                    <a href="/dashboard/promotions" class="quick-action">
                        <div class="quick-action-icon" style="background: var(--warning-gradient);">
                            <i class="bi bi-tag"></i>
                        </div>
                        <div class="quick-action-content">
                            <div class="quick-action-title">Promoções</div>
                            <div class="quick-action-desc">Criar ofertas</div>
                        </div>
                    </a>
                    <a href="/dashboard/messages" class="quick-action">
                        <div class="quick-action-icon" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                            <i class="bi bi-chat-dots"></i>
                        </div>
                        <div class="quick-action-content">
                            <div class="quick-action-title">Mensagens</div>
                            <div class="quick-action-desc">Responder clientes</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

    document.addEventListener('DOMContentLoaded', function() {
        // Load Dashboard Data
        loadMetrics();
        loadAccounts();
        loadOrders();
        initCharts();
        startAccountPolling();
    });

    const accountsState = {
        accounts: [],
        activeId: null,
        tokenStatus: {},
        syncStatus: {},
        pendingId: null,
        pollTimer: null,
        pollInterval: 30000
    };

    // Load Metrics
    async function loadMetrics() {
        try {
            const metrics = await requestJson('/api/dashboard/metrics');

            const statsGrid = document.getElementById('stats-grid');
            statsGrid.innerHTML = `
            <div class="metric-card" data-color="primary">
                <div class="metric-header">
                    <div class="metric-icon primary">
                        <i class="bi bi-shop"></i>
                    </div>
                    ${metrics.accounts_growth ? `
                    <span class="metric-change ${metrics.accounts_growth >= 0 ? 'up' : 'down'}">
                        <i class="bi bi-arrow-${metrics.accounts_growth >= 0 ? 'up' : 'down'}"></i>
                        ${Math.abs(metrics.accounts_growth)}%
                    </span>` : ''}
                </div>
                <div class="metric-value">${metrics.active_accounts || 0}</div>
                <div class="metric-label">Contas Ativas</div>
            </div>

            <div class="metric-card" data-color="info">
                <div class="metric-header">
                    <div class="metric-icon info">
                        <i class="bi bi-cart-check"></i>
                    </div>
                    ${metrics.orders_growth ? `
                    <span class="metric-change ${metrics.orders_growth >= 0 ? 'up' : 'down'}">
                        <i class="bi bi-arrow-${metrics.orders_growth >= 0 ? 'up' : 'down'}"></i>
                        ${Math.abs(metrics.orders_growth)}%
                    </span>` : ''}
                </div>
                <div class="metric-value">${metrics.recent_orders || 0}</div>
                <div class="metric-label">Pedidos (30 dias)</div>
            </div>

            <div class="metric-card" data-color="success">
                <div class="metric-header">
                    <div class="metric-icon success">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                    ${metrics.revenue_growth ? `
                    <span class="metric-change ${metrics.revenue_growth >= 0 ? 'up' : 'down'}">
                        <i class="bi bi-arrow-${metrics.revenue_growth >= 0 ? 'up' : 'down'}"></i>
                        ${Math.abs(metrics.revenue_growth)}%
                    </span>` : ''}
                </div>
                <div class="metric-value">R$ ${formatNumber(metrics.total_revenue || 0)}</div>
                <div class="metric-label">Receita Total</div>
            </div>

            <div class="metric-card" data-color="warning">
                <div class="metric-header">
                    <div class="metric-icon warning">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    ${metrics.profit_growth ? `
                    <span class="metric-change ${metrics.profit_growth >= 0 ? 'up' : 'down'}">
                        <i class="bi bi-arrow-${metrics.profit_growth >= 0 ? 'up' : 'down'}"></i>
                        ${Math.abs(metrics.profit_growth)}%
                    </span>` : ''}
                </div>
                <div class="metric-value">R$ ${formatNumber(metrics.net_profit || 0)}</div>
                <div class="metric-label">Lucro Líquido</div>
            </div>
        `;

            // Update reputation
            if (metrics.reputation_metrics) {
                updateReputation(metrics.reputation_metrics);
            }

            // Update orders chart
            if (metrics.orders_by_status) {
                updateOrdersChart(metrics.orders_by_status);
            }

        } catch (error) {
            console.error('Error loading metrics:', error);
            const statsGrid = document.getElementById('stats-grid');
            if (statsGrid) {
                statsGrid.innerHTML = `
                <div class="text-center py-4" style="grid-column: 1 / -1;">
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2 mb-0">Falha ao carregar métricas. <a href="javascript:location.reload()">Tentar novamente</a></p>
                </div>
            `;
            }
        }
    }

    // Load Accounts
    async function loadAccounts(options = {}) {
        const {
            silent = false
        } = options;
        const grid = document.getElementById('accounts-grid');

        try {
            const data = await requestJson('/api/dashboard/accounts');
            const accounts = Array.isArray(data) ? data : (data.accounts || []);
            accountsState.accounts = accounts;
            accountsState.activeId = data.active_account_id ?? null;

            if (!accounts || accounts.length === 0) {
                grid.innerHTML = `
                <div class="empty-state-card" style="grid-column: 1 / -1;">
                    <div class="empty-state-icon">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <h3 class="empty-state-title">Nenhuma conta conectada</h3>
                    <p class="empty-state-desc">Conecte sua conta do Mercado Livre para começar a gerenciar suas vendas</p>
                    <a href="/auth/authorize" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-2"></i>
                        Conectar Conta
                    </a>
                </div>
            `;
                return;
            }

            await refreshTokenStatuses();
            await refreshSyncStatuses();

            renderAccounts();
        } catch (error) {
            if (!silent) {
                grid.innerHTML = `
                <div class="empty-state-card" style="grid-column: 1 / -1;">
                    <div class="empty-state-icon">
                        <i class="bi bi-wifi-off"></i>
                    </div>
                    <h3 class="empty-state-title">Erro ao carregar contas</h3>
                    <p class="empty-state-desc">${escapeHtml(error.message || 'Falha de conexão com o servidor')}</p>
                    <button class="btn btn-outline-primary" data-action="retry-load-accounts">
                        <i class="bi bi-arrow-repeat me-2"></i>
                        Tentar novamente
                    </button>
                </div>
            `;
            }
            Toast.error('Não foi possível atualizar as contas');
        }
    }

    function renderAccounts() {
        const grid = document.getElementById('accounts-grid');
        grid.innerHTML = accountsState.accounts.map(account => {
            const tokenStatus = accountsState.tokenStatus[account.id] || {
                token_status: 'unknown'
            };
            const syncStatus = accountsState.syncStatus[account.id] || {};
            const active = accountsState.activeId === account.id;
            const pending = accountsState.pendingId === account.id;

            const connectionMeta = getConnectionMeta(account, tokenStatus);
            const syncMeta = getSyncMeta(syncStatus, account.id);
            const lastSyncLabel = syncStatus.last_synced_at ? formatDate(syncStatus.last_synced_at) : 'N/A';

            return `
            <div class="account-card ${active ? 'active' : ''} ${pending ? 'pending' : ''}" data-action="select-account" data-account-id="${account.id}">
                <div class="account-header">
                    <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(account.nickname || 'U')}&background=667eea&color=fff&size=96"
                         alt="${escapeHtml(account.nickname || '')}"
                         class="account-avatar">
                    <div class="account-info">
                        <div class="account-name">${escapeHtml(account.nickname || 'Sem nome')}</div>
                        <div class="account-email">${escapeHtml(account.email || '')}</div>
                        <div class="account-badges">
                            <span class="account-badge ${connectionMeta.class}">
                                <i class="bi ${connectionMeta.icon}"></i>
                                ${connectionMeta.label}
                            </span>
                            <span class="account-badge ${syncMeta.class}">
                                <i class="bi ${syncMeta.icon}"></i>
                                ${syncMeta.label}
                            </span>
                        </div>
                    </div>
                    <span class="account-status ${active ? 'active' : 'inactive'}">
                        <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i>
                        ${active ? 'Ativa' : 'Inativa'}
                    </span>
                </div>
                <div class="account-metrics">
                    <div class="account-metric">
                        <div class="account-metric-value">${syncStatus.items_count ?? 0}</div>
                        <div class="account-metric-label">Produtos</div>
                    </div>
                    <div class="account-metric">
                        <div class="account-metric-value">${lastSyncLabel}</div>
                        <div class="account-metric-label">Último Sync</div>
                    </div>
                    <div class="account-metric">
                        <div class="account-metric-value">${connectionMeta.tokenLabel}</div>
                        <div class="account-metric-label">Token</div>
                    </div>
                </div>
                <div class="account-actions">
                    <button class="btn btn-outline-primary btn-sm" data-action="sync-account" data-account-id="${account.id}">
                        <i class="bi bi-arrow-repeat me-1"></i>
                        Sincronizar
                    </button>
                    <button class="btn btn-outline-danger btn-sm" data-action="unlink-account" data-account-id="${account.id}">
                        <i class="bi bi-link-45deg me-1"></i>
                        Desvincular
                    </button>
                </div>
            </div>
        `;
        }).join('');
    }

    function getConnectionMeta(account, tokenStatus) {
        const tokenState = tokenStatus.token_status || 'unknown';
        if (accountsState.pendingId === account.id) {
            return {
                label: 'Ativando',
                class: 'badge-syncing',
                icon: 'bi-arrow-repeat',
                tokenLabel: '...'
            };
        }
        if (tokenState === 'valid') {
            return {
                label: 'Conectada',
                class: 'badge-connected',
                icon: 'bi-check-circle',
                tokenLabel: 'Válido'
            };
        }
        if (tokenState === 'expiring_soon') {
            return {
                label: 'Expirando',
                class: 'badge-expiring',
                icon: 'bi-exclamation-triangle',
                tokenLabel: 'Expirando'
            };
        }
        if (tokenState === 'expired') {
            return {
                label: 'Expirada',
                class: 'badge-disconnected',
                icon: 'bi-x-circle',
                tokenLabel: 'Expirado'
            };
        }
        return {
            label: 'Desconhecida',
            class: 'badge-unknown',
            icon: 'bi-question-circle',
            tokenLabel: 'N/A'
        };
    }

    function getSyncMeta(syncStatus, accountId) {
        if (accountsState.pendingId && accountsState.pendingId === accountId) {
            return {
                label: 'Verificando',
                class: 'badge-syncing',
                icon: 'bi-arrow-repeat'
            };
        }
        if (syncStatus.needs_sync === false) {
            return {
                label: 'Sincronizada',
                class: 'badge-synced',
                icon: 'bi-check-circle'
            };
        }
        if (syncStatus.needs_sync === true) {
            return {
                label: 'Desincronizada',
                class: 'badge-unsynced',
                icon: 'bi-x-circle'
            };
        }
        return {
            label: 'Sem Sync',
            class: 'badge-unknown',
            icon: 'bi-dash-circle'
        };
    }

    async function refreshTokenStatuses() {
        try {
            const data = await requestJson('/api/multi-account/tokens/status');
            const statuses = data.accounts || [];
            accountsState.tokenStatus = statuses.reduce((acc, item) => {
                acc[item.id] = item;
                return acc;
            }, {});
        } catch (error) {
            accountsState.tokenStatus = {};
            Toast.warning('Não foi possível validar o status dos tokens');
        }
    }

    async function refreshSyncStatuses() {
        const syncRequests = accountsState.accounts.map(account => {
            return requestJson(`/api/accounts/${account.id}/sync/status`)
                .then(data => ({
                    id: account.id,
                    status: data.data || {}
                }))
                .catch(() => ({
                    id: account.id,
                    status: {}
                }));
        });

        const results = await Promise.all(syncRequests);
        accountsState.syncStatus = results.reduce((acc, item) => {
            acc[item.id] = item.status;
            return acc;
        }, {});
    }

    function startAccountPolling() {
        if (accountsState.pollTimer) return;
        accountsState.pollTimer = setInterval(() => {
            if (document.hidden) return;
            loadAccounts({
                silent: true
            });
        }, accountsState.pollInterval);
    }

    async function refreshSingleAccount(accountId) {
        await refreshTokenStatuses();
        const statusData = await requestJson(`/api/accounts/${accountId}/sync/status`);
        accountsState.syncStatus[accountId] = statusData.data || {};
        renderAccounts();
    }

    // Load Orders
    async function loadOrders() {
        const list = document.getElementById('orders-list');
        try {
        let data;
        if (window.ApiClient) {
            const { response, data: body } = await window.ApiClient.json('/api/orders/all?limit=6');
            data = body || {};
            if (!response.ok && !data.error) {
                data.error = true;
                data.message = `Falha ao carregar pedidos (HTTP ${response.status})`;
            }
        } else {
            const resp = await fetch('/api/orders/all?limit=6', { credentials: 'include' });
            data = await resp.json().catch(() => ({}));
            if (!resp.ok && !data.error) {
                data.error = true;
                data.message = `Falha ao carregar pedidos (HTTP ${resp.status})`;
            }
        }

            // Handle API error responses (422 = missing seller, 401 = token expired)
            if (data.error) {
                list.innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2 mb-0">${data.message || 'Não foi possível carregar pedidos'}</p>
                </div>
            `;
                return;
            }

            if (!data.results || data.results.length === 0) {
                list.innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2 mb-0">Nenhum pedido encontrado</p>
                </div>
            `;
                return;
            }

            list.innerHTML = data.results.map(order => {
                const status = order.status?.toLowerCase() || 'pending';
                const statusConfig = {
                    paid: {
                        icon: 'bi-check-circle',
                        class: 'paid'
                    },
                    confirmed: {
                        icon: 'bi-check',
                        class: 'paid'
                    },
                    shipped: {
                        icon: 'bi-truck',
                        class: 'shipped'
                    },
                    delivered: {
                        icon: 'bi-house-check',
                        class: 'paid'
                    },
                    cancelled: {
                        icon: 'bi-x-circle',
                        class: 'cancelled'
                    },
                    pending: {
                        icon: 'bi-clock',
                        class: 'pending'
                    }
                } [status] || {
                    icon: 'bi-clock',
                    class: 'pending'
                };

                return `
                <div class="order-item">
                    <div class="order-icon ${statusConfig.class}">
                        <i class="bi ${statusConfig.icon}"></i>
                    </div>
                    <div class="order-content">
                        <div class="order-id">Pedido #${order.id}</div>
                        <div class="order-date">${formatDate(order.date_created)}</div>
                    </div>
                    <div class="order-amount">R$ ${formatNumber(order.total_amount || 0)}</div>
                </div>
            `;
            }).join('');

        } catch (error) {
            console.error('Error loading orders:', error);
            if (list) {
                list.innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-wifi-off text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2 mb-0">Falha ao carregar pedidos</p>
                </div>
            `;
            }
        }
    }

    // Update Reputation
    function updateReputation(rep) {
        const scoreEl = document.getElementById('rep-score');
        const badgeEl = document.getElementById('rep-badge');
        const circleEl = document.querySelector('.reputation-circle');

        // Calculate score (0-100)
        const score = Math.round(100 - (rep.claims_rate + rep.cancellations_rate + rep.delayed_rate) * 10);
        const clampedScore = Math.max(0, Math.min(100, score));

        scoreEl.textContent = clampedScore;
        circleEl.style.setProperty('--score-percent', clampedScore + '%');

        // Determine level
        let level = 'Iniciante';
        let color = '#64748b';
        if (clampedScore >= 90) {
            level = 'MercadoLíder Platinum';
            color = '#f59e0b';
        } else if (clampedScore >= 80) {
            level = 'MercadoLíder Gold';
            color = '#eab308';
        } else if (clampedScore >= 70) {
            level = 'MercadoLíder';
            color = '#22c55e';
        } else if (clampedScore >= 50) {
            level = 'Bom';
            color = '#3b82f6';
        }

        badgeEl.innerHTML = `<i class="bi bi-award"></i><span>${level}</span>`;
        badgeEl.style.background = `linear-gradient(135deg, ${color} 0%, ${adjustColor(color, -20)} 100%)`;

        // Update metrics
        document.getElementById('rep-claims').textContent = rep.claims_rate + '%';
        document.getElementById('rep-cancel').textContent = rep.cancellations_rate + '%';
        document.getElementById('rep-delay').textContent = rep.delayed_rate + '%';

        // Update progress bars
        updateProgressBar('bar-claims', rep.claims_rate, 1);
        updateProgressBar('bar-cancel', rep.cancellations_rate, 0.5);
        updateProgressBar('bar-delay', rep.delayed_rate, 4);
    }

    function updateProgressBar(id, value, maxAllowed) {
        const bar = document.getElementById(id);
        const percent = Math.min(100, (value / maxAllowed) * 100);
        bar.style.width = percent + '%';

        bar.classList.remove('success', 'warning', 'danger');
        if (value > maxAllowed) bar.classList.add('danger');
        else if (value > maxAllowed * 0.7) bar.classList.add('warning');
        else bar.classList.add('success');
    }

    // Initialize Charts
    let ordersChart = null;
    let revenueChart = null;

    function initCharts() {
        if (typeof Chart === 'undefined') {
            console.warn('[Dashboard] Chart.js não carregado — gráficos indisponíveis.');
            document.querySelectorAll('.chart-body').forEach(el => {
                el.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-exclamation-triangle" style="font-size:1.5rem"></i><p class="mt-2 mb-0">Gráficos indisponíveis (Chart.js não carregou)</p></div>';
            });
            return;
        }

        // Orders Doughnut Chart
        const ordersCtx = document.getElementById('ordersChart')?.getContext('2d');
        if (ordersCtx) {
            ordersChart = new Chart(ordersCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pagos', 'Enviados', 'Pendentes', 'Cancelados'],
                    datasets: [{
                        data: [0, 0, 0, 0],
                        backgroundColor: ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 16,
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }

        // Revenue Line Chart
        const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
        if (revenueCtx) {
            const days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
            const today = new Date().getDay();
            const labels = [];
            for (let i = 6; i >= 0; i--) {
                labels.push(days[(today - i + 7) % 7]);
            }

            revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Receita',
                        data: [0, 0, 0, 0, 0, 0, 0],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2,
                        pointRadius: 4,
                        pointBackgroundColor: '#667eea'
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
                                color: 'rgba(0,0,0,0.05)'
                            },
                            ticks: {
                                callback: v => 'R$ ' + formatNumber(v)
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    }

    function updateOrdersChart(data) {
        if (!ordersChart || !data) return;

        const statusMap = {
            'paid': 0,
            'confirmed': 0,
            'shipped': 1,
            'ready_to_ship': 1,
            'pending': 2,
            'cancelled': 3
        };

        const counts = [0, 0, 0, 0];
        data.forEach(item => {
            const idx = statusMap[item.status?.toLowerCase()] ?? 2;
            counts[idx] += item.count || 0;
        });

        ordersChart.data.datasets[0].data = counts;
        ordersChart.update();
    }

    // Utility Functions
    function formatNumber(num) {
        return new Intl.NumberFormat('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(num);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return dateStr;
        return date.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function adjustColor(color, amount) {
        return '#' + color.replace(/^#/, '').replace(/../g, c =>
            ('0' + Math.min(255, Math.max(0, parseInt(c, 16) + amount)).toString(16)).substr(-2)
        );
    }

    function selectAccount(accountId) {
        accountsState.pendingId = accountId;
        accountsState.activeId = accountId;
        renderAccounts();

        requestJson('/api/dashboard/switch-account', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    account_id: accountId
                })
            })
            .then(async data => {
                if (data.success) {
                    Toast.success('Conta alterada com sucesso');
                    await refreshSingleAccount(accountId);
                } else {
                    Toast.error(data.error || 'Erro ao trocar conta');
                }
            })
            .catch(() => Toast.error('Erro ao trocar conta'))
            .finally(() => {
                accountsState.pendingId = null;
                renderAccounts();
            });
    }

    async function syncAccountFromDashboard(event, accountId) {
        event.stopPropagation();
        try {
            const data = await requestJson(`/api/accounts/${accountId}/sync`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });
            if (data.success) {
                Toast.success('Sincronização concluída');
                await refreshSingleAccount(accountId);
            } else {
                Toast.error(data.error || 'Falha na sincronização');
            }
        } catch (error) {
            Toast.error('Erro ao sincronizar conta');
        }
    }

    async function unlinkAccount(event, accountId) {
        event.stopPropagation();
        const account = accountsState.accounts.find(acc => acc.id == accountId);
        const nickname = account?.nickname || 'Conta';
        const confirmUnlink = window.confirm(`Deseja desvincular a conta ${nickname}?`);
        if (!confirmUnlink) return;

        try {
            const data = await requestJson(`/auth/disconnect/${accountId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });
            if (data.success) {
                Toast.success('Conta desvinculada com sucesso');
                await loadAccounts();
            } else {
                Toast.error(data.error || 'Falha ao desvincular conta');
            }
        } catch (error) {
            Toast.error('Erro ao desvincular conta');
        }
    }

    // Event delegation for dynamic elements
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;

        const action = target.dataset.action;
        const accountId = target.dataset.accountId;

        switch (action) {
            case 'retry-load-accounts':
                e.preventDefault();
                loadAccounts();
                break;
            case 'select-account':
                e.preventDefault();
                // Don't select if clicking on action buttons
                if (!e.target.closest('.account-actions')) {
                    selectAccount(accountId);
                }
                break;
            case 'sync-account':
                e.preventDefault();
                e.stopPropagation(); // Prevent card selection
                syncAccountFromDashboard(e, accountId);
                break;
            case 'unlink-account':
                e.preventDefault();
                e.stopPropagation(); // Prevent card selection
                unlinkAccount(e, accountId);
                break;
        }
    });
</script>
