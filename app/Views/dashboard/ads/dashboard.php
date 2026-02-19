<?php
$title = 'Meus Anúncios';
$subtitle = 'Veja como seus anúncios estão performando — de forma simples e clara';
$breadcrumbs = [['label' => 'Meus Anúncios', 'url' => '']];
$actions = '<a href="/dashboard/ads/criar" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Criar Anúncio</a>';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<!-- Saúde Geral (Semáforo) -->
<div id="health-banner" class="alert alert-secondary d-flex align-items-center mb-4" style="display:none !important;">
    <span id="health-emoji" class="fs-2 me-3">➖</span>
    <div>
        <strong id="health-label">Carregando...</strong>
        <p id="health-message" class="mb-0 small text-muted">Analisando seus anúncios...</p>
    </div>
</div>

<!-- Métricas Simples (linguagem leiga) -->
<div class="row mb-4 g-3">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                    <small class="text-muted">Quanto Investiu</small>
                    <i class="bi bi-question-circle text-muted" style="cursor:help" data-bs-toggle="tooltip"
                       title="Total gasto com anúncios nos últimos 30 dias"></i>
                </div>
                <h3 class="fw-bold text-primary mb-0" id="kpi-investiu">
                    <span class="placeholder-glow"><span class="placeholder col-8"></span></span>
                </h3>
                <small class="text-muted">últimos 30 dias</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                    <small class="text-muted">Vendeu com Ads</small>
                    <i class="bi bi-question-circle text-muted" style="cursor:help" data-bs-toggle="tooltip"
                       title="Receita gerada pelas suas campanhas de anúncio"></i>
                </div>
                <h3 class="fw-bold text-success mb-0" id="kpi-vendeu">
                    <span class="placeholder-glow"><span class="placeholder col-8"></span></span>
                </h3>
                <small class="text-muted">últimos 30 dias</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                    <small class="text-muted">Lucro dos Ads</small>
                    <i class="bi bi-question-circle text-muted" style="cursor:help" data-bs-toggle="tooltip"
                       title="Diferença entre o que vendeu com anúncios e o que gastou"></i>
                </div>
                <h3 class="fw-bold mb-0" id="kpi-lucro">
                    <span class="placeholder-glow"><span class="placeholder col-8"></span></span>
                </h3>
                <small class="text-muted">vendas - investimento</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                    <small class="text-muted">Custo por Venda</small>
                    <i class="bi bi-question-circle text-muted" style="cursor:help" data-bs-toggle="tooltip"
                       title="% do valor vendido que foi gasto em anúncio (ACOS). Abaixo de 15% é bom."></i>
                </div>
                <h3 class="fw-bold mb-0" id="kpi-custo-venda">
                    <span class="placeholder-glow"><span class="placeholder col-6"></span></span>
                </h3>
                <small class="text-muted">meta: abaixo de 15%</small>
            </div>
        </div>
    </div>
</div>

<!-- Diagnóstico (frases simples) -->
<div class="card border-0 shadow-sm mb-4" id="diagnostico-card" style="display:none">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0"><i class="bi bi-clipboard2-pulse me-2"></i>Diagnóstico dos Anúncios</h5>
    </div>
    <div class="card-body" id="diagnostico-body">
        <!-- preenchido via JS -->
    </div>
</div>

<!-- Ações Rápidas -->
<div class="row mb-4 g-3">
    <div class="col-md-4">
        <button class="btn btn-outline-success w-100 py-3 d-flex flex-column align-items-center gap-1 quick-action-btn" data-action="activate_all">
            <i class="bi bi-play-circle fs-3"></i>
            <strong>Ativar Tudo</strong>
            <small class="text-muted">Reativar campanhas pausadas</small>
        </button>
    </div>
    <div class="col-md-4">
        <button class="btn btn-outline-warning w-100 py-3 d-flex flex-column align-items-center gap-1 quick-action-btn" data-action="pause_unprofitable">
            <i class="bi bi-pause-circle fs-3"></i>
            <strong>Pausar Prejuízo</strong>
            <small class="text-muted">Parar campanhas que gastam demais</small>
        </button>
    </div>
    <div class="col-md-4">
        <button class="btn btn-outline-primary w-100 py-3 d-flex flex-column align-items-center gap-1 quick-action-btn" data-action="optimize">
            <i class="bi bi-magic fs-3"></i>
            <strong>Otimizar Tudo</strong>
            <small class="text-muted">IA ajusta lances e orçamentos</small>
        </button>
    </div>
</div>

<!-- Lista de Campanhas -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-megaphone me-2"></i>Suas Campanhas</h5>
        <div class="d-flex gap-2">
            <a href="/dashboard/ads/criar" class="btn btn-sm btn-primary">
                <i class="bi bi-plus me-1"></i>Nova Campanha
            </a>
            <button class="btn btn-sm btn-outline-secondary" id="btn-refresh">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div id="campaigns-container">
            <!-- Estado vazio / carregando -->
            <div class="text-center py-5" id="campaigns-loading">
                <div class="spinner-border text-primary mb-3"></div>
                <p class="text-muted">Carregando suas campanhas...</p>
            </div>
            <div class="text-center py-5" id="campaigns-empty" style="display:none">
                <i class="bi bi-megaphone text-muted" style="font-size: 3rem;"></i>
                <h5 class="mt-3 text-muted">Nenhuma campanha encontrada</h5>
                <p class="text-muted mb-3">Comece a anunciar seus produtos para vender mais!</p>
                <a href="/dashboard/ads/criar" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Criar Meu Primeiro Anúncio
                </a>
            </div>

            <!-- Tabela responsiva de campanhas -->
            <div class="table-responsive" id="campaigns-table-wrap" style="display:none">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Campanha</th>
                            <th>Tipo</th>
                            <th>Orçamento/dia</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="campaigns-list"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Glossário Interativo -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3" role="button" data-bs-toggle="collapse" data-bs-target="#glossario-body">
        <h5 class="mb-0 d-flex justify-content-between align-items-center">
            <span><i class="bi bi-book me-2"></i>Glossário — O que significa cada termo?</span>
            <i class="bi bi-chevron-down"></i>
        </h5>
    </div>
    <div class="collapse" id="glossario-body">
        <div class="card-body" id="glossario-content">
            <!-- preenchido via JS -->
        </div>
    </div>
</div>

<!-- Modal de Edição de Orçamento -->
<div class="modal fade" id="budgetModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">Alterar Orçamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted small mb-2">Quanto quer investir por dia nesta campanha?</p>
                <div class="input-group input-group-lg mb-3">
                    <span class="input-group-text">R$</span>
                    <input type="number" class="form-control text-center" id="budget-input" min="5" step="5" value="20">
                    <span class="input-group-text">/dia</span>
                </div>
                <input type="range" class="form-range" id="budget-slider" min="5" max="500" step="5" value="20">
                <div class="d-flex justify-content-between small text-muted">
                    <span>R$ 5</span>
                    <span>R$ 500</span>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-save-budget">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script src="/js/ads-manager.js?v=<?= @filemtime(__DIR__ . '/../../../../public/js/ads-manager.js') ?: '1' ?>" nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>"></script>
