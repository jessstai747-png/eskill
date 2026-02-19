<?php
$title = 'Gestão de Devoluções (RMA)';
ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 bg-primary text-white">
            <div class="card-body p-4 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold mb-1">Devoluções & RMA</h2>
                    <p class="mb-0 opacity-75">Gerencie a logística reversa, triagem e reentrada de estoque.</p>
                </div>
                <button class="btn btn-light text-primary fw-bold" data-bs-toggle="modal" data-bs-target="#registerModal">
                    <i class="bi bi-plus-lg me-2"></i> Nova Devolução
                </button>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" id="pills-pending-tab" data-bs-toggle="pill" data-bs-target="#pills-pending" type="button">
            <i class="bi bi-hourglass-split me-2"></i> Pendentes & Triagem
            <span class="badge bg-danger rounded-pill ms-2"><?= count($pending) ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="pills-history-tab" data-bs-toggle="pill" data-bs-target="#pills-history" type="button">
            <i class="bi bi-clock-history me-2"></i> Histórico Concluído
        </button>
    </li>
</ul>

<div class="tab-content" id="pills-tabContent">
    
    <!-- Pending Tab -->
    <div class="tab-pane fade show active" id="pills-pending">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Pedido ML</th>
                            <th>SKU / Produto</th>
                            <th>Reclamação</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $r): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="fw-bold text-dark">#<?= $r['ml_order_id'] ?></span>
                                <br>
                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded p-2 me-2 text-primary fw-bold">
                                        <?= htmlspecialchars($r['sku'] ?? 'N/A') ?>
                                    </div>
                                    <small class="text-muted">Qtd: <?= $r['quantity'] ?></small>
                                </div>
                            </td>
                            <td>
                                <?php if($r['claim_id']): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                        <i class="bi bi-exclamation-triangle me-1"></i> <?= $r['claim_id'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">Sem Reclamação</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    $statusMap = [
                                        'WAITING_ARRIVAL' => ['bg' => 'bg-info-subtle', 'text' => 'text-info', 'label' => 'Aguardando Chegada'],
                                        'RECEIVED' => ['bg' => 'bg-primary-subtle', 'text' => 'text-primary', 'label' => 'Recebido no CD'],
                                        'CHECKING' => ['bg' => 'bg-warning-subtle', 'text' => 'text-warning', 'label' => 'Em Triagem'],
                                    ];
                                    $s = $statusMap[$r['status']] ?? ['bg' => 'bg-secondary', 'text' => 'text-white', 'label' => $r['status']];
                                ?>
                                <span class="badge <?= $s['bg'] ?> <?= $s['text'] ?>"><?= $s['label'] ?></span>
                            </td>
                            <td>
                                <?php if($r['status'] == 'WAITING_ARRIVAL'): ?>
                                    <form action="/dashboard/returns/receive" method="POST" class="d-inline">
                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-box-seam me-1"></i> Confirmar Chegada
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-primary" onclick="openInspection(<?= $r['id'] ?>, '<?= $r['sku'] ?>')">
                                        <i class="bi bi-clipboard-check me-1"></i> Realizar Triagem
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($pending)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">Nenhuma devolução pendente.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- History Tab -->
    <div class="tab-pane fade" id="pills-history">
        <div class="card border-0 shadow-sm">
             <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Conclusão</th>
                            <th>Pedido</th>
                            <th>SKU</th>
                            <th>Decisão</th>
                            <th>Condição</th>
                            <th>Responsável</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td class="ps-4 text-muted small"><?= date('d/m/Y H:i', strtotime($h['updated_at'])) ?></td>
                            <td>#<?= $h['ml_order_id'] ?></td>
                            <td><?= $h['sku'] ?></td>
                            <td>
                                <?php if($h['status'] == 'RESTOCKED'): ?>
                                    <span class="badge bg-success-subtle text-success"><i class="bi bi-arrow-return-left"></i> Reintegrado</span>
                                <?php elseif($h['status'] == 'DISCARDED'): ?>
                                    <span class="badge bg-danger-subtle text-danger"><i class="bi bi-trash"></i> Descartado</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">Devolvido ao Comp.</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class="bi bi-star-fill text-<?= $i <= $h['condition_rating'] ? 'warning' : 'muted opacity-25' ?>" style="font-size: 0.75rem;"></i>
                                <?php endfor; ?>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($h['inspector_name'] ?? 'Sistema') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
             </div>
        </div>
    </div>

</div>

<!-- Register Modal -->
<div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/dashboard/returns/register" method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Registrar Nova Devolução</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">ML Order ID</label>
                        <input type="number" name="ml_order_id" class="form-control" required placeholder="Ex: 200000...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-control" required placeholder="Ex: SKU-123">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ID Reclamação (Opcional)</label>
                        <input type="text" name="claim_id" class="form-control" placeholder="Ex: 512345...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantidade</label>
                        <input type="number" name="quantity" class="form-control" value="1" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Inspection Modal -->
<div class="modal fade" id="inspectionModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/dashboard/returns/inspect" method="POST">
            <input type="hidden" name="id" id="inspect_id">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">Triagem de Produto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="bi bi-info-circle fs-4 me-3"></i>
                        <div>
                            Avaliando SKU: <strong id="inspect_sku">...</strong>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Condição Visual</label>
                        <div class="d-flex justify-content-between px-3">
                            <div class="form-check text-center">
                                <input class="form-check-input float-none" type="radio" name="condition" value="1" required>
                                <label class="d-block small text-danger">Sucata</label>
                            </div>
                            <div class="form-check text-center">
                                <input class="form-check-input float-none" type="radio" name="condition" value="2">
                                <label class="d-block small">Péssimo</label>
                            </div>
                            <div class="form-check text-center">
                                <input class="form-check-input float-none" type="radio" name="condition" value="3">
                                <label class="d-block small">Usado</label>
                            </div>
                            <div class="form-check text-center">
                                <input class="form-check-input float-none" type="radio" name="condition" value="4">
                                <label class="d-block small">Bom</label>
                            </div>
                            <div class="form-check text-center">
                                <input class="form-check-input float-none" type="radio" name="condition" value="5" checked>
                                <label class="d-block small text-success">Novo</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Decisão Final</label>
                        <select name="resolution" class="form-select" onchange="updateDecisionPreview(this.value)">
                            <option value="RESTOCK">Reintegrar ao Estoque (+1)</option>
                            <option value="DISCARD">Descartar (Perda)</option>
                            <option value="RETURNED_TO_BUYER">Devolver ao Comprador (Rejeitar)</option>
                        </select>
                        <div id="decisionPreview" class="form-text text-success mt-1">
                            <i class="bi bi-check-circle"></i> O estoque será incrementado automaticamente.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notas da Inspeção</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Ex: Produto lacrado, caixa levemente amassada..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4">Concluir Triagem</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
function openInspection(id, sku) {
    document.getElementById('inspect_id').value = id;
    document.getElementById('inspect_sku').textContent = sku;
    new bootstrap.Modal(document.getElementById('inspectionModal')).show();
}

function updateDecisionPreview(val) {
    const el = document.getElementById('decisionPreview');
    if (val === 'RESTOCK') {
        el.className = 'form-text text-success mt-1';
        el.innerHTML = '<i class="bi bi-check-circle"></i> O estoque será incrementado automaticamente.';
    } else if (val === 'DISCARD') {
        el.className = 'form-text text-danger mt-1';
        el.innerHTML = '<i class="bi bi-trash"></i> O item será contabilizado como perda.';
    } else {
        el.className = 'form-text text-warning mt-1';
        el.innerHTML = '<i class="bi bi-reply"></i> O item será devolvido (frete por conta do comprador?).';
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/modern/app.php';
?>
