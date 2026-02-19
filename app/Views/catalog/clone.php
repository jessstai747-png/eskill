<?php
/**
 * View de Clonagem de Catálogo - Versão Melhorada
 *
 * @uses layouts/modern/app.php
 */

$pageTitle = 'Clonar Catálogo';
$pageDescription = 'Replique anúncios de catálogo entre contas';
$title = $pageTitle;
$subtitle = $pageDescription;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Catálogo', 'url' => '/catalog'],
    ['label' => 'Clonar']
];

ob_start();
?>

<style>
/* Catalog Clone Custom Styles */
.clone-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}
.clone-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.12);
}
.clone-card .card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px 12px 0 0;
    border: none;
}
.clone-card .nav-tabs {
    border: none;
}
.clone-card .nav-tabs .nav-link {
    border: none;
    color: rgba(255,255,255,0.7);
    padding: 0.75rem 1.25rem;
    font-weight: 500;
    transition: all 0.2s;
}
.clone-card .nav-tabs .nav-link:hover {
    color: white;
    background: rgba(255,255,255,0.1);
}
.clone-card .nav-tabs .nav-link.active {
    background: white;
    color: #667eea;
    border-radius: 8px 8px 0 0;
}

/* Item Preview */
.item-preview {
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
    border-radius: 12px;
    padding: 1.25rem;
    min-height: 120px;
    transition: all 0.3s ease;
}
.item-preview.loading {
    display: flex;
    align-items: center;
    justify-content: center;
}
.item-preview .preview-image {
    width: 90px;
    height: 90px;
    object-fit: contain;
    border-radius: 8px;
    background: white;
    padding: 5px;
}
.item-preview .preview-title {
    font-weight: 600;
    color: #333;
    line-height: 1.3;
    font-size: 0.95rem;
}
.item-preview .preview-price {
    font-size: 1.25rem;
    font-weight: 700;
    color: #00a650;
}
.item-preview .preview-badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
}

/* Progress Animation */
.clone-progress {
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
    background: #e9ecef;
}
.clone-progress .progress-bar {
    transition: width 0.5s ease;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
}

/* Drag & Drop Zone */
.drop-zone {
    border: 2px dashed #ccc;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    background: #fafafa;
    cursor: pointer;
}
.drop-zone.drag-over {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.05);
}
.drop-zone i {
    font-size: 2rem;
    color: #ccc;
}

/* Account Select Cards */
.account-card {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
    background: white;
}
.account-card:hover {
    border-color: #667eea;
    transform: translateY(-2px);
}
.account-card.selected {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.05);
}
.account-card.disabled {
    opacity: 0.5;
    pointer-events: none;
}
.account-card .account-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.85rem;
}

/* Metric Cards */
.metric-card {
    border-radius: 12px;
    padding: 1rem;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transition: all 0.3s ease;
    border-left: 4px solid;
}
.metric-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}
.metric-card.primary { border-left-color: #667eea; }
.metric-card.success { border-left-color: #00a650; }
.metric-card.info { border-left-color: #17a2b8; }
.metric-card.warning { border-left-color: #ffc107; }
.metric-card.danger { border-left-color: #dc3545; }
.metric-card.secondary { border-left-color: #6c757d; }
.metric-card .metric-value {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
}
.metric-card .metric-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
}

/* History Table */
.history-table {
    border-radius: 12px;
    overflow: hidden;
}
.history-table thead {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
.history-table thead th {
    border: none;
    padding: 0.875rem 1rem;
    font-weight: 500;
    font-size: 0.85rem;
}
.history-table tbody tr {
    transition: background 0.2s;
}
.history-table tbody tr:hover {
    background: rgba(102, 126, 234, 0.05);
}
.history-table td {
    padding: 0.75rem 1rem;
    vertical-align: middle;
}

/* Buttons */
.btn-clone {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    padding: 0.875rem 2rem;
    font-weight: 600;
    border-radius: 10px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.35);
}
.btn-clone::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}
.btn-clone:hover::before {
    left: 100%;
}
.btn-clone:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
    color: white;
}
.btn-clone:active {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}
.btn-clone:disabled {
    opacity: 0.6;
    transform: none;
    box-shadow: none;
}
.btn-clone:disabled::before {
    display: none;
}
.btn-clone .btn-icon {
    transition: transform 0.3s ease;
}
.btn-clone:hover .btn-icon {
    transform: scale(1.15) rotate(-5deg);
}

.btn-simulate {
    background: white;
    border: 2px solid #667eea;
    color: #667eea;
    padding: 0.875rem 2rem;
    font-weight: 600;
    border-radius: 10px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}
.btn-simulate::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(102, 126, 234, 0.1);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.4s, height 0.4s;
}
.btn-simulate:hover::before {
    width: 300px;
    height: 300px;
}
.btn-simulate:hover {
    background: rgba(102, 126, 234, 0.05);
    color: #667eea;
    border-color: #5a6fd6;
    transform: translateY(-2px);
}
.btn-simulate:active {
    transform: translateY(0);
}
.btn-simulate .btn-icon {
    transition: transform 0.3s ease;
}
.btn-simulate:hover .btn-icon {
    transform: rotate(15deg);
}

/* Button Loading State */
.btn-loading {
    pointer-events: none;
    position: relative;
}
.btn-loading .btn-text {
    visibility: hidden;
}
.btn-loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin-left: -10px;
    margin-top: -10px;
    border: 2px solid transparent;
    border-top-color: currentColor;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

/* Action Buttons Container */
.action-buttons-container {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid #e9ecef;
}
.action-buttons-container .btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

/* Secondary Action Buttons */
.btn-action-secondary {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #495057;
    padding: 0.5rem 1rem;
    font-weight: 500;
    font-size: 0.875rem;
    border-radius: 8px;
    transition: all 0.2s ease;
}
.btn-action-secondary:hover {
    background: #e9ecef;
    border-color: #ced4da;
    color: #212529;
}
.btn-action-secondary i {
    font-size: 0.9rem;
}

/* Icon Action Buttons (for input groups) */
.input-group-modern {
    position: relative;
}
.input-group-modern .form-control {
    border-radius: 10px 0 0 10px !important;
    border-right: none;
}
.input-group-modern .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
}
.btn-action-icon {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #e9ecef;
    background: white;
    color: #6c757d;
    transition: all 0.25s ease;
    position: relative;
    overflow: hidden;
}
.btn-action-icon:first-of-type {
    border-left: none;
}
.btn-action-icon:last-child {
    border-radius: 0 10px 10px 0 !important;
}
.btn-action-icon::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    opacity: 0;
    transition: opacity 0.25s ease;
}
.btn-action-icon:hover::before {
    opacity: 1;
}
.btn-action-icon i {
    position: relative;
    z-index: 1;
    transition: all 0.25s ease;
}
.btn-action-icon:hover {
    border-color: #667eea;
    color: white;
}
.btn-action-icon:hover i {
    transform: scale(1.15);
}
.btn-action-icon.btn-preview:hover i {
    animation: blink 0.3s ease;
}
.btn-action-icon.btn-search:hover i {
    animation: bounce-search 0.4s ease;
}
@keyframes blink {
    0%, 100% { transform: scale(1.15); }
    50% { transform: scale(1.3); }
}
@keyframes bounce-search {
    0%, 100% { transform: scale(1.15) translateY(0); }
    50% { transform: scale(1.2) translateY(-2px); }
}

/* Clear Button */
.btn-clear {
    background: #fff5f5;
    border: 1px solid #feb2b2;
    color: #c53030;
    padding: 0.375rem 0.75rem;
    font-size: 0.8rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}
.btn-clear:hover {
    background: #fed7d7;
    border-color: #fc8181;
    color: #9b2c2c;
}
.btn-clear i {
    transition: transform 0.2s ease;
}
.btn-clear:hover i {
    transform: rotate(-10deg) scale(1.1);
}

/* Animations */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-in {
    animation: fadeInUp 0.4s ease forwards;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.02); }
}
.pulse {
    animation: pulse 2s infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
.spin {
    animation: spin 1s linear infinite;
}
</style>

<!-- Page Header -->
<?php include __DIR__ . '/../layouts/modern/partials/page-header.php'; ?>

<div class="container-fluid py-0">

    <!-- Metrics Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="metric-card primary animate-in" style="animation-delay: 0.1s">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="metric-label mb-1">Hoje</div>
                        <div class="metric-value text-primary" id="todayClones">0</div>
                    </div>
                    <i class="bi bi-calendar3 text-primary opacity-50 fs-5"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="metric-card success animate-in" style="animation-delay: 0.15s">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="metric-label mb-1">Taxa Sucesso</div>
                        <div class="metric-value text-success" id="successRate">0%</div>
                    </div>
                    <i class="bi bi-check-circle text-success opacity-50 fs-5"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="metric-card info animate-in" style="animation-delay: 0.2s">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="metric-label mb-1">Total</div>
                        <div class="metric-value text-info" id="totalClones">0</div>
                    </div>
                    <i class="bi bi-collection text-info opacity-50 fs-5"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="metric-card warning animate-in" style="animation-delay: 0.25s">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="metric-label mb-1">Média/H</div>
                        <div class="metric-value text-warning" id="avgPerHour">0</div>
                    </div>
                    <i class="bi bi-speedometer text-warning opacity-50 fs-5"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="metric-card secondary animate-in" style="animation-delay: 0.3s">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="metric-label mb-1">Pendentes</div>
                        <div class="metric-value text-secondary" id="pendingJobs">0</div>
                    </div>
                    <i class="bi bi-clock text-secondary opacity-50 fs-5"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="metric-card danger animate-in" style="animation-delay: 0.35s">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="metric-label mb-1">Erros</div>
                        <div class="metric-value text-danger" id="errorCount">0</div>
                    </div>
                    <i class="bi bi-exclamation-triangle text-danger opacity-50 fs-5"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Form -->
        <div class="col-xl-8 col-lg-7">
            <div class="card clone-card mb-4 animate-in" style="animation-delay: 0.4s">
                <div class="card-header py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="cloneTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button">
                                <i class="bi bi-file-earmark me-1"></i> Individual
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="batch-tab" data-bs-toggle="tab" data-bs-target="#batch" type="button">
                                <i class="bi bi-stack me-1"></i> Em Lote
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="scheduled-tab" data-bs-toggle="tab" data-bs-target="#scheduled" type="button">
                                <i class="bi bi-clock-history me-1"></i> Agendada
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-4">
                    <form id="cloneForm">
                        <div class="tab-content" id="cloneTabsContent">
                            <!-- Single Mode -->
                            <div class="tab-pane fade show active" id="single" role="tabpanel">
                                <div class="row g-3">
                                    <!-- Source Account -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-box-arrow-right text-primary me-1"></i> Conta Origem
                                        </label>
                                        <select class="form-select source-account-select" id="source_account_id" name="source_account_id" required>
                                            <option value="">Selecione a conta...</option>
                                            <?php foreach ($accounts as $account): ?>
                                                <option value="<?= $account['id'] ?>" data-nickname="<?= htmlspecialchars($account['nickname'] ?? '') ?>">
                                                    <?= htmlspecialchars($account['nickname'] ?? $account['ml_user_id']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Source Item -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-tag text-primary me-1"></i> ID do Anúncio
                                        </label>
                                        <div class="input-group input-group-modern">
                                            <input type="text" class="form-control" id="source_item_id" name="source_item_id" placeholder="MLB123456789" required>
                                            <button class="btn btn-action-icon btn-preview" type="button" id="btnPreviewItem" title="Visualizar item">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-action-icon btn-search" type="button" id="btnSearchSingle" title="Buscar itens">
                                                <i class="bi bi-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Item Preview -->
                                    <div class="col-12">
                                        <div class="item-preview" id="itemPreview">
                                            <div class="text-center text-muted py-2">
                                                <i class="bi bi-image display-6 d-block mb-2 opacity-50"></i>
                                                <small>Clique em <i class="bi bi-eye"></i> para ver o preview do item</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Batch Mode -->
                            <div class="tab-pane fade" id="batch" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-box-arrow-right text-primary me-1"></i> Conta Origem
                                        </label>
                                        <select class="form-select source-account-select" id="batch_source_account_id" name="batch_source_account_id">
                                            <option value="">Selecione a conta...</option>
                                            <?php foreach ($accounts as $account): ?>
                                                <option value="<?= $account['id'] ?>">
                                                    <?= htmlspecialchars($account['nickname'] ?? $account['ml_user_id']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end">
                                        <button class="btn btn-simulate w-100" type="button" id="btnSearchBatch">
                                            <i class="bi bi-search btn-icon"></i>
                                            <span class="btn-text">Buscar Itens</span>
                                        </button>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-list-ol text-primary me-1"></i> IDs dos Anúncios
                                        </label>
                                        <div class="drop-zone" id="dropZone">
                                            <i class="bi bi-cloud-arrow-up d-block mb-2"></i>
                                            <small>Arraste um arquivo .txt ou cole IDs abaixo</small>
                                        </div>
                                        <textarea class="form-control font-monospace mt-2" id="batch_items" name="batch_items" rows="4" placeholder="MLB123456789&#10;MLB987654321"></textarea>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small class="text-muted">
                                                <span id="itemCount">0</span> IDs (máx. 50)
                                            </small>
                                            <button class="btn btn-clear" type="button" id="btnClearBatch">
                                                <i class="bi bi-trash3"></i> Limpar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Scheduled Mode -->
                            <div class="tab-pane fade" id="scheduled" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Conta Origem</label>
                                        <select class="form-select" id="schedule_source_account" required>
                                            <option value="">Selecione...</option>
                                            <?php foreach ($accounts as $account): ?>
                                                <option value="<?= $account['id'] ?>">
                                                    <?= htmlspecialchars($account['nickname'] ?? $account['ml_user_id']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Conta Destino</label>
                                        <select class="form-select" id="schedule_target_account" required>
                                            <option value="">Selecione...</option>
                                            <?php foreach ($accounts as $account): ?>
                                                <option value="<?= $account['id'] ?>">
                                                    <?= htmlspecialchars($account['nickname'] ?? $account['ml_user_id']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Data</label>
                                        <input type="date" class="form-control" id="schedule_date" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Hora</label>
                                        <input type="time" class="form-control" id="schedule_time" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Frequência</label>
                                        <select class="form-select" id="schedule_frequency">
                                            <option value="once">Uma vez</option>
                                            <option value="daily">Diário</option>
                                            <option value="weekly">Semanal</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <button type="button" class="btn btn-clone w-100" id="btnScheduleClone">
                                            <i class="bi bi-calendar-plus btn-icon"></i>
                                            <span class="btn-text">Criar Agendamento</span>
                                        </button>
                                    </div>
                                    
                                    <!-- Active Schedules -->
                                    <div class="col-12 mt-4">
                                        <h6 class="fw-bold mb-3 border-top pt-3">
                                            <i class="bi bi-list-check text-primary me-1"></i> Agendamentos Ativos
                                        </h6>
                                        <div id="activeSchedules">
                                            <div class="text-center text-muted py-3">
                                                <i class="bi bi-calendar-x fs-1 d-block mb-2 opacity-50"></i>
                                                <small>Nenhum agendamento ativo</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Target Accounts -->
                        <div id="targetSection" class="mt-4 pt-4 border-top">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-box-arrow-in-right text-success me-1"></i> Conta(s) Destino
                            </label>
                            <div class="row g-2" id="targetAccountsGrid">
                                <?php foreach ($accounts as $account): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="account-card" data-account-id="<?= $account['id'] ?>">
                                            <div class="d-flex align-items-center">
                                                <div class="account-avatar me-2">
                                                    <?= strtoupper(substr($account['nickname'] ?? $account['ml_user_id'], 0, 1)) ?>
                                                </div>
                                                <div class="flex-grow-1 overflow-hidden">
                                                    <div class="fw-semibold text-truncate" style="font-size: 0.9rem"><?= htmlspecialchars($account['nickname'] ?? 'Conta') ?></div>
                                                    <small class="text-muted"><?= $account['ml_user_id'] ?></small>
                                                </div>
                                                <div class="form-check ms-2">
                                                    <input type="checkbox" class="form-check-input target-checkbox" name="target_account_ids[]" value="<?= $account['id'] ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="alert alert-danger d-none mt-3 py-2" id="sameAccountError">
                                <i class="bi bi-exclamation-triangle me-1"></i> A conta origem não pode ser destino.
                            </div>
                        </div>

                        <!-- Pricing & Stock Strategy -->
                        <div class="mt-4 pt-4 border-top">
                            <h6 class="fw-bold mb-3">
                                <i class="bi bi-gear text-primary me-1"></i> Estratégia
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small">Preço</label>
                                    <select class="form-select form-select-sm" id="pricing_type" name="pricing_strategy[type]">
                                        <option value="copy">📋 Copiar original</option>
                                        <option value="markup_percent">📈 Markup (%)</option>
                                        <option value="aggressive">🔥 Agressivo</option>
                                        <option value="competitive">⚖️ Competitivo</option>
                                        <option value="premium">💎 Premium</option>
                                    </select>
                                </div>
                                <div class="col-md-6 d-none" id="markup_container">
                                    <label class="form-label small">Markup %</label>
                                    <input type="number" class="form-control form-control-sm" id="pricing_value" name="pricing_strategy[value]" step="0.01" value="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">Estoque</label>
                                    <select class="form-select form-select-sm" id="stock_type" name="stock_strategy[type]">
                                        <option value="copy">📋 Copiar original</option>
                                        <option value="fixed">📦 Fixo</option>
                                    </select>
                                </div>
                                <div class="col-md-6 d-none" id="stock_container">
                                    <label class="form-label small">Qtd</label>
                                    <input type="number" class="form-control form-control-sm" id="stock_value" name="stock_strategy[value]" min="1" value="1">
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mt-4 d-none" id="progressSection">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="fw-semibold" id="progressLabel">Processando...</small>
                                <small id="progressPercent">0%</small>
                            </div>
                            <div class="clone-progress">
                                <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons-container">
                            <button type="button" class="btn btn-simulate" id="btnSimulate">
                                <i class="bi bi-calculator btn-icon"></i>
                                <span class="btn-text">Simular</span>
                            </button>
                            <button type="submit" class="btn btn-clone" id="btnClone">
                                <i class="bi bi-files btn-icon"></i>
                                <span class="btn-text">Clonar Anúncio</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-xl-4 col-lg-5">
            <!-- Result Area -->
            <div class="card clone-card mb-4 animate-in" style="animation-delay: 0.45s">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold"><i class="bi bi-check2-circle me-1"></i> Resultado</h6>
                </div>
                <div class="card-body">
                    <div id="resultArea" class="text-center text-muted py-4">
                        <i class="bi bi-arrow-left-circle display-5 d-block mb-3 opacity-50"></i>
                        <p class="mb-0 small">Preencha o formulário para iniciar.</p>
                    </div>
                </div>
            </div>

            <!-- Tips Card -->
            <div class="card border-0 bg-light animate-in" style="animation-delay: 0.5s">
                <div class="card-body py-3">
                    <h6 class="fw-bold text-primary mb-2">
                        <i class="bi bi-lightbulb me-1"></i> Dicas
                    </h6>
                    <ul class="small mb-0 ps-3 text-muted">
                        <li class="mb-1">Apenas itens de <strong>catálogo</strong> podem ser clonados.</li>
                        <li class="mb-1">Duplicidades são verificadas automaticamente.</li>
                        <li class="mb-1">Use <kbd>Ctrl</kbd>+<kbd>V</kbd> para colar múltiplos IDs.</li>
                        <li>Imagens vêm do catálogo ML.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- History Table -->
    <div class="card clone-card mt-4 animate-in" style="animation-delay: 0.55s">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold"><i class="bi bi-clock-history me-1"></i> Histórico</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table history-table mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Origem</th>
                            <th>Destino</th>
                            <th>Item Origem</th>
                            <th>Item Criado</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                    Nenhum registro.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach (array_slice($history, 0, 10) as $item): ?>
                                <tr>
                                    <td>
                                        <small><?= date('d/m/Y', strtotime($item['created_at'])) ?></small><br>
                                        <small class="text-muted"><?= date('H:i', strtotime($item['created_at'])) ?></small>
                                    </td>
                                    <td><small><?= htmlspecialchars($item['source_account_name'] ?? $item['source_account_id']) ?></small></td>
                                    <td><small><?= htmlspecialchars($item['target_account_name'] ?? $item['target_account_id']) ?></small></td>
                                    <td>
                                        <a href="https://produto.mercadolivre.com.br/<?= str_replace('MLB', 'MLB-', $item['source_item_id']) ?>" 
                                           target="_blank" class="text-decoration-none small">
                                            <?= $item['source_item_id'] ?> <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['target_item_id'])): ?>
                                            <a href="https://produto.mercadolivre.com.br/<?= str_replace('MLB', 'MLB-', $item['target_item_id']) ?>" 
                                               target="_blank" class="text-decoration-none text-success small">
                                                <?= $item['target_item_id'] ?> <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['status'] === 'success'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check"></i></span>
                                        <?php elseif ($item['status'] === 'error'): ?>
                                            <span class="badge bg-danger" title="<?= htmlspecialchars($item['error_message'] ?? '') ?>"><i class="bi bi-x"></i></span>
                                        <?php elseif ($item['status'] === 'skipped_duplicate'): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-skip-forward"></i></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= $item['status'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Item Search Modal -->
<div class="modal fade" id="itemSearchModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title"><i class="bi bi-search me-2"></i>Buscar Anúncios</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="searchQuery" placeholder="Buscar por título ou ID...">
                    <button class="btn btn-primary" type="button" id="btnDoSearch">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <div class="table-responsive" style="max-height: 350px;">
                    <table class="table table-hover table-sm">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th width="35"><input type="checkbox" id="checkAllItems" class="form-check-input"></th>
                                <th width="60">Img</th>
                                <th>Título</th>
                                <th width="90">Preço</th>
                                <th width="110">ID</th>
                            </tr>
                        </thead>
                        <tbody id="searchResultsBody">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    <small>Selecione uma conta e busque.</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <div class="me-auto text-muted small" id="searchStatus"></div>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="btnSelectItems">
                    <i class="bi bi-check2 me-1"></i> Selecionar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Simulation Modal -->
<div class="modal fade" id="simulationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title"><i class="bi bi-calculator me-2"></i>Simulação</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="simulationBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary mb-3"></div>
                    <p class="mb-0 small">Calculando...</p>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-sm btn-success" id="btnConfirmCloneFromSim" style="display:none">
                    <i class="bi bi-check2 me-1"></i> Clonar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="/js/catalog-clone.js"></script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/modern/app.php';
?>
