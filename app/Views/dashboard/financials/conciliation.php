<?php

declare(strict_types=1);

$title = 'Conciliação Financeira';
ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 bg-primary text-white">
            <div class="card-body p-4 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold mb-1">Conciliação Financeira</h2>
                    <p class="mb-0 opacity-75">Importe seus relatórios do Mercado Livre para verificar seus recebimentos.</p>
                </div>
                <button class="btn btn-light text-primary fw-bold" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="bi bi-cloud-upload me-2"></i> Importar Relatório
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="icon-circle bg-success-subtle text-success me-3">
                        <i class="bi bi-check-lg fs-4"></i>
                    </div>
                    <h6 class="text-muted mb-0">Conciliados</h6>
                </div>
                <h3 class="fw-bold mb-0"><?= number_format($stats['CONCILIATED'], 0, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="icon-circle bg-warning-subtle text-warning me-3">
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </div>
                    <h6 class="text-muted mb-0">Pendentes</h6>
                </div>
                <h3 class="fw-bold mb-0"><?= number_format($stats['PENDING'], 0, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="icon-circle bg-danger-subtle text-danger me-3">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                    <h6 class="text-muted mb-0">Divergentes</h6>
                </div>
                <h3 class="fw-bold mb-0"><?= number_format($stats['MISMATCH'], 0, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="icon-circle bg-info-subtle text-info me-3">
                        <i class="bi bi-wallet2 fs-4"></i>
                    </div>
                    <h6 class="text-muted mb-0">Total Confirmado</h6>
                </div>
                <h3 class="fw-bold mb-0">R$ <?= number_format($stats['total_amount'], 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Últimos Lançamentos</h5>
        <a href="/dashboard/financials/conciliation/reconcile" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-arrow-repeat me-1"></i> Reconciliar Pendentes
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Data</th>
                    <th>Descrição</th>
                    <th>Referência</th>
                    <th>Tipo</th>
                    <th>Valor Bruto</th>
                    <th>Líquido</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($settlements as $s): ?>
                <tr>
                    <td class="ps-4 text-muted small"><?= date('d/m/Y', strtotime($s['date_released'])) ?></td>
                    <td><?= htmlspecialchars($s['description'] ?? '') ?></td>
                    <td class="small font-monospace"><?= htmlspecialchars($s['external_reference'] ?? '-') ?></td>
                    <td><span class="badge bg-secondary-subtle text-secondary"><?= $s['type'] ?></span></td>
                    <td>R$ <?= number_format($s['gross_amount'], 2, ',', '.') ?></td>
                    <td class="fw-bold <?= $s['net_amount'] < 0 ? 'text-danger' : 'text-success' ?>">
                        R$ <?= number_format($s['net_amount'], 2, ',', '.') ?>
                    </td>
                    <td>
                        <?php if($s['status'] === 'CONCILIATED'): ?>
                            <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i> Conciliado</span>
                        <?php elseif($s['status'] === 'MISMATCH'): ?>
                            <span class="badge bg-danger-subtle text-danger"><i class="bi bi-exclamation-circle me-1"></i> Divergente</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning">Pendente</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($settlements)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">Nenhum registro encontrado. Importe um relatório.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/dashboard/financials/conciliation/upload" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Importar Relatório de Liberações</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-5">
                    <i class="bi bi-file-earmark-spreadsheet text-primary display-4 mb-3"></i>
                    <p class="mb-3">Selecione o arquivo CSV/XLSX baixado do Mercado Livre.</p>
                    <input type="file" name="report" class="form-control" required accept=".csv,.xlsx">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4">Importar e Conciliar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.icon-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/modern/app.php';
?>
