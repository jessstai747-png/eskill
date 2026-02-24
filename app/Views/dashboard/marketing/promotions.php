<?php
$title = 'Central de Promoções';
$subtitle = 'Gerencie suas ofertas e campanhas do Mercado Livre';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary"><i class="bi bi-tag-fill"></i> Promoções Disponíveis</h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="promoManager.loadPromotions()">
                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Nome da Campanha</th>
                                <th>Tipo</th>
                                <th>Prazo de Inscrição</th>
                                <th>Itens Elegíveis</th>
                                <th class="text-end pe-4">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="promo-list">
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="spinner-border text-primary"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Items -->
<div class="modal fade" id="itemsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Itens Elegíveis</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Item</th>
                                <th>Preço Atual</th>
                                <th class="text-success">Preço Sugerido</th>
                                <th class="text-end pe-4">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="promo-items-list">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
    const promoManager = {
        currentPromoId: null,

        init: function() {
            this.loadPromotions();
        },

        loadPromotions: async function() {
            try {
                const data = await requestJson('/api/marketing/promotions');

                if (data.success) {
                    this.render(data.promotions);
                }
            } catch (e) {
                console.error(e);
                Toast.error('Erro ao carregar promoções');
            }
        },

        render: function(promotions) {
            const container = document.getElementById('promo-list');
            if (promotions.length === 0) {
                container.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Nenhuma promoção disponível no momento.</td></tr>';
                return;
            }

            let html = '';
            promotions.forEach(promo => {
                const deadline = new Date(promo.deadline_date).toLocaleDateString('pt-BR') + ' ' + new Date(promo.deadline_date).toLocaleTimeString('pt-BR', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                let typeBadge = 'bg-secondary';
                if (promo.type === 'DEAL_OF_THE_DAY') typeBadge = 'bg-warning text-dark';
                if (promo.type === 'LIGHTNING') typeBadge = 'bg-danger';
                if (promo.type === 'MARKETPLACE_CAMPAIGN') typeBadge = 'bg-primary';

                html += `
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold">${promo.name}</div>
                            <small class="text-muted">ID: ${promo.id}</small>
                        </td>
                        <td><span class="badge ${typeBadge}">${promo.type}</span></td>
                        <td>${deadline}</td>
                        <td>
                            <span class="badge bg-light text-dark border">${promo.items_eligible_count} elegíveis</span>
                            <span class="badge bg-success bg-opacity-10 text-success">${promo.items_joined_count} participando</span>
                        </td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-outline-primary" onclick="promoManager.viewItems('${promo.id}')">
                                Ver Itens
                            </button>
                        </td>
                    </tr>
                `;
            });
            container.innerHTML = html;
        },

        viewItems: async function(promoId) {
            this.currentPromoId = promoId;
            const container = document.getElementById('promo-items-list');
            container.innerHTML = '<tr><td colspan="4" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>';
            new bootstrap.Modal(document.getElementById('itemsModal')).show();

            try {
                const data = await requestJson('/api/marketing/promotions/items?id=' + promoId);

                if (data.success) {
                    this.renderItems(data.items);
                }
            } catch (e) {
                console.error(e);
                container.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Erro ao carregar itens.</td></tr>';
            }
        },

        renderItems: function(items) {
            const container = document.getElementById('promo-items-list');
            const money = (val) => new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(val);

            if (items.length === 0) {
                container.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Nenhum item elegível.</td></tr>';
                return;
            }

            let html = '';
            items.forEach(item => {
                const isJoined = item.status === 'joined';

                html += `
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold">${item.title}</div>
                            <small class="text-muted">${item.id}</small>
                        </td>
                        <td><span class="text-muted text-decoration-line-through">${money(item.price)}</span></td>
                        <td class="text-success fw-bold">${money(item.suggested_price || item.promotion_price)}</td>
                        <td class="text-end pe-4">
                            ${isJoined ?
                                '<span class="badge bg-success">Participando</span>' :
                                `<button class="btn btn-sm btn-success" onclick="promoManager.join('${item.id}', ${item.suggested_price})">Participar</button>`
                            }
                        </td>
                    </tr>
                `;
            });
            container.innerHTML = html;
        },

        join: async function(itemId, price) {
            if (!confirm('Confirmar participação com o preço sugerido?')) return;

            try {
                const result = await requestJson('/api/marketing/promotions/join', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        promotion_id: this.currentPromoId,
                        items: [{
                            item_id: itemId,
                            price: price
                        }]
                    })
                });


                if (result.success) {
                    Toast.success('Item adicionado à promoção!');
                    this.viewItems(this.currentPromoId); // Refresh modal
                } else {
                    Toast.error(result.error);
                }
            } catch (e) {
                Toast.error('Erro ao participar');
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => promoManager.init());
</script>
