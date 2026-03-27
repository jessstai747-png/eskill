<?php

declare(strict_types=1);

$title = 'Gestão de Clientes (CRM)';
$subtitle = 'Conheça seus compradores e aumente o LTV';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<div class="row">
    <div class="col-md-9">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary"><i class="bi bi-people-fill"></i> Base de Compradores</h5>
                <div class="input-group w-auto">
                    <input type="text" class="form-control form-control-sm" placeholder="Buscar cliente...">
                    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Cliente / Apelido</th>
                                <th>Localização</th>
                                <th>Última Compra</th>
                                <th>Pedidos</th>
                                <th>Total Gasto (LTV)</th>
                                <th class="text-end pe-4">Tags</th>
                            </tr>
                        </thead>
                        <tbody id="crm-list">
                            <tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Detail -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center" id="crm-detail-placeholder">
                <div class="py-5 text-muted">
                    <i class="bi bi-person-bounding-box display-4"></i>
                    <p class="mt-3">Selecione um cliente para ver detalhes.</p>
                </div>
            </div>
            <div class="card-body d-none" id="crm-detail-content">
                <div class="text-center mb-4">
                    <div class="avatar bg-primary text-white rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; font-size: 1.5rem;" id="detail-initials">JS</div>
                    <h5 class="mb-0" id="detail-name">Nome</h5>
                    <small class="text-muted" id="detail-nick">@nickname</small>
                    <div class="mt-2" id="detail-tier-badge"></div>
                </div>
                
                <hr>
                
                <div class="mb-3">
                    <label class="small text-muted text-uppercase fw-bold">Contato</label>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="bi bi-envelope text-muted"></i> <span id="detail-email" class="text-truncate">email@...</span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="small text-muted text-uppercase fw-bold">Métricas</label>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Total Gasto:</span>
                        <strong class="text-success" id="detail-spent">R$ 0,00</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Pedidos:</span>
                        <strong id="detail-orders">0</strong>
                    </div>
                </div>
                
                <div class="mb-3">
                     <label class="small text-muted text-uppercase fw-bold">Notas Internas</label>
                     <textarea class="form-control form-control-sm bg-light" rows="3" id="detail-notes" readonly></textarea>
                </div>
                
                <button class="btn btn-outline-primary w-100 btn-sm">Ver Pedidos</button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">

    const crmManager = {
        init: function() {
            this.loadCustomers();
        },

        loadCustomers: async function() {
            try {
                const data = await requestJson('/api/crm/customers');
                
                if (data.success) {
                    this.render(data.customers);
                }
            } catch (e) {
                console.error(e);
            }
        },

        render: function(customers) {
            const container = document.getElementById('crm-list');
            const money = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);

            let html = '';
            customers.forEach(cx => {
                const date = new Date(cx.last_order_date).toLocaleDateString('pt-BR');
                let tagsHtml = '';
                if(cx.tags) {
                    cx.tags.forEach(tag => {
                        let color = 'secondary';
                        if (tag === 'vip') color = 'warning text-dark';
                        if (tag === 'whale') color = 'primary';
                        if (tag === 'new') color = 'info text-dark';
                        tagsHtml += `<span class="badge bg-${color} me-1">${tag}</span>`;
                    });
                }

                html += `
                    <tr style="cursor: pointer;" onclick="crmManager.loadDetail(${cx.id})">
                        <td class="ps-4">
                            <div class="fw-bold">${cx.name}</div>
                            <small class="text-muted">@${cx.nickname}</small>
                        </td>
                        <td>${cx.state}</td>
                        <td>${date}</td>
                        <td class="text-center">${cx.total_orders}</td>
                        <td class="fw-bold text-success">${money(cx.total_spent)}</td>
                        <td class="text-end pe-4">${tagsHtml}</td>
                    </tr>
                `;
            });
            container.innerHTML = html;
        },
        
        loadDetail: async function(id) {
            document.getElementById('crm-detail-placeholder').classList.add('d-none');
            const content = document.getElementById('crm-detail-content');
            content.classList.remove('d-none');
            
            // Show loading state if needed, here just swift mock load
            try {
                const data = await requestJson('/api/crm/customer?id=' + id);
                
                if (data.success) {
                    const cx = data.customer;
                    const money = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);
                    
                    document.getElementById('detail-name').textContent = cx.first_name + ' ' + cx.last_name;
                    document.getElementById('detail-nick').textContent = '@' + cx.nickname;
                    document.getElementById('detail-email').textContent = cx.email;
                    document.getElementById('detail-initials').textContent = cx.first_name[0] + cx.last_name[0];
                    
                    document.getElementById('detail-spent').textContent = money(cx.total_spent);
                    document.getElementById('detail-orders').textContent = cx.total_orders;
                    document.getElementById('detail-notes').value = cx.notes || '';
                    
                    let tierBadge = '<span class="badge bg-secondary">Standard</span>';
                    if (cx.ltv_tier === 'Gold') tierBadge = '<span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> Gold</span>';
                    document.getElementById('detail-tier-badge').innerHTML = tierBadge;
                }
            } catch (e) {
                console.error(e);
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => crmManager.init());
</script>
