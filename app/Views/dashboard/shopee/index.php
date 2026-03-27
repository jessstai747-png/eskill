<?php

declare(strict_types=1);

$title = 'Shopee Integration';
$subtitle = 'Gerencie sua loja Shopee';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1 text-warning">Shopee Status</h5>
                    <p class="mb-0 text-muted">Integração ativa em modo de demonstração.</p>
                </div>
                <div>
                    <a href="<?= $authUrl ?>" target="_blank" class="btn btn-warning text-white">
                        <i class="bi bi-shop"></i> Conectar Loja
                    </a>
                    <button class="btn btn-outline-secondary ms-2" onclick="syncShopee()">
                        <i class="bi bi-arrow-repeat"></i> Sincronizar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 text-secondary">Itens Sincronizados</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">Shopee ID</th>
                            <th>Nome</th>
                            <th>SKU</th>
                            <th>Preço</th>
                            <th>Estoque</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="ps-3 text-muted">#<?= $item['shopee_item_id'] ?></td>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><span class="badge bg-light text-dark"><?= $item['sku'] ?></span></td>
                            <td>R$ <?= number_format($item['price'], 2, ',', '.') ?></td>
                            <td><?= $item['stock'] ?></td>
                            <td><span class="badge bg-success"><?= $item['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                Nenhum item sincronizado. Clique em "Sincronizar".
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">

async function syncShopee() {
    const toastId = Toast.info('Sincronizando com Shopee...', 0);
    try {
        const json = await requestJson('/api/shopee/sync');
        
        if (json.success) {
            Toast.dismiss(toastId);
            Toast.success('Sincronização concluída!');
            setTimeout(() => location.reload(), 1000);
        } else {
            Toast.dismiss(toastId);
            Toast.error(json.error || 'Erro ao sincronizar');
        }
    } catch (e) {
        Toast.dismiss(toastId);
        Toast.error('Erro de conexão');
    }
}
</script>
