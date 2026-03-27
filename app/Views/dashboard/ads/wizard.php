<?php

declare(strict_types=1);

$title = 'Criar Anúncio';
$subtitle = 'Anuncie seus produtos em 3 passos simples';
$breadcrumbs = [
    ['label' => 'Meus Anúncios', 'url' => '/dashboard/ads'],
    ['label' => 'Criar Anúncio', 'url' => ''],
];
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<!-- Stepper Visual -->
<div class="d-flex justify-content-center mb-4">
    <div class="d-flex align-items-center gap-2">
        <div class="wizard-step active" id="step-indicator-1">
            <span class="wizard-step-number">1</span>
            <span class="wizard-step-label d-none d-md-inline">Escolha os Produtos</span>
        </div>
        <div class="wizard-step-line"></div>
        <div class="wizard-step" id="step-indicator-2">
            <span class="wizard-step-number">2</span>
            <span class="wizard-step-label d-none d-md-inline">Defina o Orçamento</span>
        </div>
        <div class="wizard-step-line"></div>
        <div class="wizard-step" id="step-indicator-3">
            <span class="wizard-step-number">3</span>
            <span class="wizard-step-label d-none d-md-inline">Confirmar e Criar</span>
        </div>
    </div>
</div>

<style>
    .wizard-step {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        background: #f0f0f0;
        color: #888;
        font-weight: 500;
        transition: all 0.3s;
    }
    .wizard-step.active {
        background: var(--bs-primary, #3483fa);
        color: white;
    }
    .wizard-step.completed {
        background: var(--bs-success, #00a650);
        color: white;
    }
    .wizard-step-number {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        background: rgba(255,255,255,0.2);
    }
    .wizard-step-line {
        width: 40px;
        height: 2px;
        background: #ddd;
    }
    .product-card {
        cursor: pointer;
        transition: all 0.2s;
        border: 2px solid transparent !important;
    }
    .product-card:hover {
        border-color: var(--bs-primary, #3483fa) !important;
        transform: translateY(-2px);
    }
    .product-card.selected {
        border-color: var(--bs-primary, #3483fa) !important;
        background: rgba(52, 131, 250, 0.05);
    }
    .product-card.selected .product-check {
        display: flex !important;
    }
    .product-check {
        display: none !important;
        position: absolute;
        top: 8px;
        right: 8px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: var(--bs-primary, #3483fa);
        color: white;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }
</style>

<!-- PASSO 1: Selecionar Produtos -->
<div class="wizard-panel" id="step-1">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-1"><i class="bi bi-box-seam me-2"></i>Passo 1: O que você quer anunciar?</h5>
            <p class="text-muted mb-0 small">Selecione os produtos que quer promover. Recomendamos começar com 1 a 5 produtos.</p>
        </div>
        <div class="card-body">
            <!-- Search -->
            <div class="input-group mb-3">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="product-search" placeholder="Buscar produto pelo nome...">
            </div>

            <!-- Products Grid -->
            <div id="products-loading" class="text-center py-5">
                <div class="spinner-border text-primary mb-3"></div>
                <p class="text-muted">Carregando seus produtos...</p>
            </div>
            <div class="row g-3" id="products-grid" style="display:none"></div>
            <div id="products-empty" class="text-center py-4" style="display:none">
                <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                <p class="text-muted mt-2">Nenhum produto ativo encontrado.</p>
            </div>
        </div>
        <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center py-3">
            <span class="text-muted small"><span id="selected-count">0</span> produto(s) selecionado(s)</span>
            <button class="btn btn-primary" id="btn-step-2" disabled>
                Próximo <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>
</div>

<!-- PASSO 2: Orçamento -->
<div class="wizard-panel" id="step-2" style="display:none">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-1"><i class="bi bi-wallet2 me-2"></i>Passo 2: Quanto quer investir por dia?</h5>
            <p class="text-muted mb-0 small">Defina o orçamento diário. Você pode alterar a qualquer momento depois.</p>
        </div>
        <div class="card-body text-center">
            <!-- Sugestão automática -->
            <div class="alert alert-info d-flex align-items-start text-start mb-4" id="budget-suggestion">
                <i class="bi bi-lightbulb-fill me-2 mt-1 text-warning"></i>
                <div>
                    <strong>Sugestão inteligente:</strong>
                    <p class="mb-0 small" id="budget-explanation">Calculando a melhor sugestão para você...</p>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="mb-4">
                        <label class="form-label fw-bold fs-5">Orçamento Diário</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">R$</span>
                            <input type="number" class="form-control text-center fs-3 fw-bold" id="wizard-budget" min="5" max="1000" step="5" value="20">
                            <span class="input-group-text">/dia</span>
                        </div>
                    </div>

                    <input type="range" class="form-range mb-3" id="wizard-budget-slider" min="5" max="500" step="5" value="20">
                    <div class="d-flex justify-content-between small text-muted mb-4">
                        <span>R$ 5</span>
                        <span>R$ 100</span>
                        <span>R$ 250</span>
                        <span>R$ 500</span>
                    </div>

                    <!-- Estimativas visuais -->
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="bg-light rounded p-2 text-center">
                                <small class="text-muted d-block">Por Semana</small>
                                <strong id="est-weekly">R$ 140</strong>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-2 text-center">
                                <small class="text-muted d-block">Por Mês</small>
                                <strong id="est-monthly">R$ 600</strong>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-2 text-center">
                                <small class="text-muted d-block">Risco Máx</small>
                                <strong id="est-risk" class="text-success">Baixo</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dicas -->
            <div class="bg-light rounded p-3 text-start small">
                <strong><i class="bi bi-info-circle me-1"></i>Dicas para iniciantes:</strong>
                <ul class="mb-0 mt-1">
                    <li>Comece com R$ 10-30/dia para testar sem arriscar muito.</li>
                    <li>O Mercado Livre só cobra quando alguém clica no anúncio.</li>
                    <li>Se não gostar dos resultados, pause a qualquer momento.</li>
                </ul>
            </div>
        </div>
        <div class="card-footer bg-white border-0 d-flex justify-content-between py-3">
            <button class="btn btn-outline-secondary" id="btn-back-1">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </button>
            <button class="btn btn-primary" id="btn-step-3">
                Próximo <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>
</div>

<!-- PASSO 3: Confirmação -->
<div class="wizard-panel" id="step-3" style="display:none">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-1"><i class="bi bi-check2-circle me-2"></i>Passo 3: Confirmar e Criar</h5>
            <p class="text-muted mb-0 small">Revise tudo antes de criar. A campanha começa PAUSADA por segurança.</p>
        </div>
        <div class="card-body">
            <!-- Resumo -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="bg-light rounded p-3">
                        <h6 class="text-muted mb-2"><i class="bi bi-box-seam me-1"></i>Produtos Selecionados</h6>
                        <ul class="list-unstyled mb-0" id="summary-products"></ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="bg-light rounded p-3">
                        <h6 class="text-muted mb-2"><i class="bi bi-gear me-1"></i>Configuração</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted">Orçamento diário:</td>
                                <td class="fw-bold" id="summary-budget">R$ 20,00</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tipo:</td>
                                <td>Product Ads</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Lance:</td>
                                <td>Automático (recomendado)</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status inicial:</td>
                                <td><span class="badge bg-secondary">Pausada</span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Aviso de segurança -->
            <div class="alert alert-success d-flex align-items-start mb-3">
                <i class="bi bi-shield-check me-2 mt-1"></i>
                <div>
                    <strong>Seguro!</strong> A campanha será criada PAUSADA. Você decide quando ativar.
                    Não será cobrado nada até que você ative manualmente.
                </div>
            </div>

            <!-- Nome opcional -->
            <div class="mb-3">
                <label class="form-label small text-muted">Nome da campanha (opcional):</label>
                <input type="text" class="form-control" id="campaign-name" placeholder="Ex: Promoção de Verão">
                <small class="text-muted">Se deixar vazio, geramos um nome automático.</small>
            </div>
        </div>
        <div class="card-footer bg-white border-0 d-flex justify-content-between py-3">
            <button class="btn btn-outline-secondary" id="btn-back-2">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </button>
            <button class="btn btn-success btn-lg" id="btn-create">
                <i class="bi bi-rocket-takeoff me-1"></i> Criar Anúncio
            </button>
        </div>
    </div>
</div>

<!-- PASSO 4: Sucesso -->
<div class="wizard-panel" id="step-success" style="display:none">
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <div class="mb-3">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
            </div>
            <h3 class="mb-2">Anúncio Criado!</h3>
            <p class="text-muted mb-4" id="success-message">Sua campanha foi criada com sucesso.</p>

            <div class="bg-light rounded p-3 mb-4 text-start" style="max-width: 500px; margin: 0 auto;">
                <h6><i class="bi bi-lightbulb me-1 text-warning"></i>Próximos Passos:</h6>
                <ul class="mb-0" id="success-tips">
                    <li>A campanha está PAUSADA. Ative quando estiver pronto.</li>
                    <li>Acompanhe os resultados nos primeiros 7 dias.</li>
                </ul>
            </div>

            <div class="d-flex justify-content-center gap-3">
                <a href="/dashboard/ads" class="btn btn-primary">
                    <i class="bi bi-bar-chart me-1"></i> Ver Meus Anúncios
                </a>
                <a href="/dashboard/ads/criar" class="btn btn-outline-primary">
                    <i class="bi bi-plus-circle me-1"></i> Criar Outro
                </a>
            </div>
        </div>
    </div>
</div>

<script src="/js/ads-wizard.js?v=<?= @filemtime(__DIR__ . '/../../../../public/js/ads-wizard.js') ?: '1' ?>" nonce="<?= CSP_NONCE ?>"></script>
