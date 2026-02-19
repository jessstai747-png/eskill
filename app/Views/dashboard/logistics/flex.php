<?php
$title = 'Mercado Envio Flex';
$subtitle = 'Gestão de entregas no mesmo dia';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="mb-0 text-primary"><i class="bi bi-lightning-charge-fill"></i> Pedidos Pendentes</h5>
                    <span class="badge bg-warning text-dark" id="cutoff-timer">Cutoff em: --:--</span>
                </div>
                
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary" onclick="flexManager.loadOrders()">
                        <i class="bi bi-arrow-clockwise"></i> Atualizar
                    </button>
                    <button class="btn btn-sm btn-success" onclick="flexManager.openAssignModal()" id="btn-assign" disabled>
                        <i class="bi bi-person-badge"></i> Atribuir Motorista
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4" style="width: 40px;">
                                    <input type="checkbox" class="form-check-input" id="check-all" onclick="flexManager.toggleAll(this)">
                                </th>
                                <th>Pedido</th>
                                <th>Comprador</th>
                                <th>Endereço</th>
                                <th>Horário</th>
                                <th class="text-end pe-4">Status</th>
                            </tr>
                        </thead>
                        <tbody id="flex-list">
                            <tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light">
                <small class="text-muted"><i class="bi bi-info-circle"></i> Pedidos Flex devem ser despachados até o horário de corte para não afetar a reputação.</small>
            </div>
        </div>
    </div>
</div>

<!-- Modal Motorista -->
<div class="modal fade" id="driverModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Atribuir Motorista</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Você selecionou <strong id="selected-count">0</strong> pedidos.</p>
                <div class="mb-3">
                    <label class="form-label">Nome do Motorista</label>
                    <input type="text" class="form-control" id="driver-name" placeholder="Ex: Carlos Silva">
                </div>
                <div class="mb-3">
                    <label class="form-label">Placa do Veículo (Opcional)</label>
                    <input type="text" class="form-control" id="vehicle-plate" placeholder="ABC-1234">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="flexManager.assignDriver()">Confirmar Envio</button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
    async function requestJson(url, options = {}) {
        if (window.ApiClient) return window.ApiClient.request(url, options);
        const resp = await fetch(url, { credentials: 'include', ...options });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        return resp.json();
    }

    const flexManager = {
        selectedIds: new Set(),
        
        init: function() {
            this.loadOrders();
            this.startCutoffTimer();
        },

        loadOrders: async function() {
            try {
                const data = await requestJson('/api/logistics/flex/orders');
                
                if (data.success) {
                    this.render(data.orders);
                }
            } catch (e) {
                console.error(e);
            }
        },

        render: function(orders) {
            const container = document.getElementById('flex-list');
            if (orders.length === 0) {
                container.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Nenhum pedido Flex pendente. Bom trabalho! 🚀</td></tr>';
                return;
            }

            let html = '';
            orders.forEach(order => {
                const date = new Date(order.date_created).toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
                
                html += `
                    <tr>
                        <td class="ps-4">
                            <input type="checkbox" class="form-check-input order-check" value="${order.id}" onchange="flexManager.handleCheck(this)">
                        </td>
                        <td>
                            <div class="fw-bold">#${order.id}</div>
                            <small class="text-muted">${order.items[0].title}</small>
                            ${order.items.length > 1 ? '<span class="badge bg-secondary p-1 ms-1">+' + (order.items.length - 1) + '</span>' : ''}
                        </td>
                        <td>${order.buyer.first_name} ${order.buyer.last_name}</td>
                        <td>
                            <div class="text-truncate" style="max-width: 250px;" title="${order.shipping.address}">
                                ${order.shipping.address}<br>
                                <small class="text-muted">${order.shipping.city} - ${order.shipping.state}</small>
                            </div>
                        </td>
                        <td>${date}</td>
                        <td class="text-end pe-4">
                            <span class="badge bg-success">Pronto para Envio</span>
                        </td>
                    </tr>
                `;
            });
            container.innerHTML = html;
        },
        
        toggleAll: function(source) {
            document.querySelectorAll('.order-check').forEach(chk => {
                chk.checked = source.checked;
                this.handleCheck(chk); // Update set
            });
        },
        
        handleCheck: function(chk) {
            if (chk.checked) {
                this.selectedIds.add(chk.value);
            } else {
                this.selectedIds.delete(chk.value);
            }
            this.updateUI();
        },
        
        updateUI: function() {
            const count = this.selectedIds.size;
            const btn = document.getElementById('btn-assign');
            btn.disabled = count === 0;
            btn.innerHTML = `<i class="bi bi-person-badge"></i> Atribuir Motorista ${count > 0 ? '('+count+')' : ''}`;
        },
        
        openAssignModal: function() {
            document.getElementById('selected-count').textContent = this.selectedIds.size;
            new bootstrap.Modal(document.getElementById('driverModal')).show();
        },
        
        assignDriver: async function() {
            const driver = document.getElementById('driver-name').value;
            const plate = document.getElementById('vehicle-plate').value;
            
            if (!driver) return alert('Informe o nome do motorista');
            
            try {
                const result = await requestJson('/api/logistics/flex/assign', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        order_ids: Array.from(this.selectedIds),
                        driver: driver,
                        plate: plate
                    })
                });
                
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('driverModal')).hide();
                    Toast.success(result.message);
                    this.selectedIds.clear();
                    this.updateUI();
                    this.loadOrders();
                } else {
                    Toast.error(result.error);
                }
            } catch (err) {
                Toast.error('Erro de conexão');
            }
        },
        
        startCutoffTimer: function() {
            const timer = document.getElementById('cutoff-timer');
            // Mock cutoff: 12:00 PM today
            const now = new Date();
            const cutoff = new Date();
            cutoff.setHours(12, 0, 0, 0);
            
            if (now > cutoff) {
                timer.innerHTML = "Cutoff Encerrado";
                timer.classList.replace('bg-warning', 'bg-danger');
                timer.classList.replace('text-dark', 'text-white');
            } else {
                // simple static for updates
                timer.innerHTML = "Cutoff: 12:00"; 
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => flexManager.init());
</script>
