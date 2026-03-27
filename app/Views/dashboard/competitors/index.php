<?php

declare(strict_types=1);

$title = 'Central de Espionagem 2.0';
ob_start();

function normalizeExternalUrl(?string $url): string
{
    $trimmed = trim((string)$url);
    if ($trimmed === '') return '';
    if (str_starts_with($trimmed, 'data:') || str_starts_with($trimmed, 'blob:') || str_starts_with($trimmed, '#')) {
        return $trimmed;
    }
    if (str_starts_with($trimmed, '//')) {
        return 'https:' . $trimmed;
    }
    if (str_starts_with($trimmed, 'http://')) {
        return 'https://' . substr($trimmed, strlen('http://'));
    }
    return $trimmed;
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 bg-dark text-white">
            <div class="card-body p-4 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold mb-1"><i class="bi bi-incognito me-2"></i> Espionagem 2.0</h2>
                    <p class="mb-0 opacity-75">Monitore concorrentes, descubra estratégias e receba alertas de preço.</p>
                </div>
                <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-2"></i> Monitorar Novo Anúncio
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Alerts Section -->
<?php if (!empty($alerts)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold border-start border-4 border-warning ps-2">⚠️ Alertas Recentes</h6>
    </div>
    <div class="list-group list-group-flush">
        <?php foreach($alerts as $alert): ?>
            <div class="list-group-item">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1 text-danger"><?= $alert['change_type'] == 'price_change' ? 'Alteração de Preço' : 'Mudança de Status' ?></h6>
                    <small class="text-muted"><?= date('d/m H:i', strtotime($alert['created_at'])) ?></small>
                </div>
                <p class="mb-1 text-muted small">em <strong><?= htmlspecialchars($alert['title']) ?></strong></p>
                <small>
                    De: <span class="text-decoration-line-through">R$ <?= number_format($alert['old_value'],2,',','.') ?></span>
                    Para: <span class="fw-bold text-success">R$ <?= number_format($alert['new_value'],2,',','.') ?></span>
                </small>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Main List -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold">Itens Monitorados</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Anúncio Concorrente</th>
                    <th>Vendedor</th>
                    <th>Preço Atual</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="ps-4">
                        <div class="d-flex flex-column">
                            <span class="fw-medium text-truncate" style="max-width: 350px;">
                                <a href="/dashboard/competitors/details/<?= $item['ml_item_id'] ?>" class="text-decoration-none text-dark stretched-link">
                                    <?= htmlspecialchars($item['title'] ?? 'Carregando...') ?>
                                </a>
                            </span>
                            <small class="text-muted font-monospace"><?= $item['ml_item_id'] ?></small>
                        </div>
                    </td>
                    <td>
                        <i class="bi bi-shop me-1"></i> <small><?= htmlspecialchars($item['seller_id']) ?></small>
                    </td>
                    <td>
                        <span class="fw-bold fs-6">R$ <?= number_format($item['price'], 2, ',', '.') ?></span>
                        <?php if(isset($item['first_price']) && $item['first_price'] > $item['price']): ?>
                            <br><small class="text-success" style="font-size: 0.75rem;"><i class="bi bi-arrow-down"></i> Queda detectada</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border"><?= $item['status'] ?></span>
                    </td>
                    <td class="text-end pe-4" style="position: relative; z-index: 2;">
                        <a href="<?= htmlspecialchars(normalizeExternalUrl($item['permalink'] ?? '') ?: '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver no ML">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <a href="/dashboard/competitors/details/<?= $item['ml_item_id'] ?>" class="btn btn-sm btn-outline-primary ms-1" title="Análise e Histórico">
                            <i class="bi bi-graph-up"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($items)): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">Nenhum concorrente monitorado. Adicione o primeiro!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/dashboard/competitors/add" method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Novo Alvo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Link ou ID do Anúncio (ML)</label>
                        <input type="text" name="url" class="form-control" placeholder="https://produto.mercadolivre.com.br/MLB-..." required>
                        <div class="form-text">Cole o link completo do produto que deseja espionar.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Iniciar Monitoramento</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/modern/app.php';
?>
