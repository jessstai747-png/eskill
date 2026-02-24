<?php
$title = 'Editor em Massa';
$subtitle = 'Atualize múltiplos anúncios simultaneamente';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<div class="row">
    <div class="col-12">
        <!-- Action Bar -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body bg-light">
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <span class="fw-bold"><i class="bi bi-layers-fill"></i> Ações em Massa:</span>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="bulk-action">
                            <option value="">Selecione uma ação...</option>
                            <option value="price_increase">Aumentar Preço (%)</option>
                            <option value="price_decrease">Diminuir Preço (%)</option>
                            <option value="pause">Pausar Anúncios</option>
                            <option value="activate">Ativar Anúncios</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control" id="bulk-value" placeholder="Valor (ex: 10)" disabled>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary" id="btn-apply" onclick="bulkManager.apply()" disabled>
                            <i class="bi bi-play-fill"></i> Aplicar
                        </button>
                    </div>
                    <div class="col-auto ms-auto">
                        <span class="text-muted" id="selected-count-display">0 selecionados</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4" style="width: 40px;">
                                    <input type="checkbox" class="form-check-input" id="check-all" onclick="bulkManager.toggleAll(this)">
                                </th>
                                <th>Anúncio</th>
                                <th>Preço Atual</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Estoque</th>
                            </tr>
                        </thead>
                        <tbody id="items-list">
                            <tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination Mock -->
                <div class="d-flex justify-content-center py-3">
                    <nav>
                        <ul class="pagination mb-0">
                            <li class="page-item disabled"><a class="page-link" href="#">Anterior</a></li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">Próximo</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

function normalizeExternalUrl(url) {
    if (!url || typeof url !== 'string') return '';
    const trimmed = url.trim();
    if (!trimmed) return '';
    if (trimmed.startsWith('data:') || trimmed.startsWith('blob:') || trimmed.startsWith('#')) return trimmed;
    if (trimmed.startsWith('//')) return `${window.location.protocol}${trimmed}`;
    if (trimmed.startsWith('http://')) return `https://${trimmed.slice('http://'.length)}`;
    return trimmed;
}

    const bulkManager = {
        selectedIds: new Set(),
        
        init: function() {
            this.loadItems();
            
            document.getElementById('bulk-action').addEventListener('change', (e) => {
                const val = e.target.value;
                const input = document.getElementById('bulk-value');
                input.disabled = !val.includes('price');
                if (!val.includes('price')) input.value = '';
                this.updateUI();
            });
        },

        loadItems: async function() {
            try {
                // Reuse existing items API
                const data = await requestJson('/api/items?limit=10');
                
                if (data.items) {
                    this.render(data.items);
                }
            } catch (e) {
                console.error(e);
            }
        },

        render: function(items) {
            const container = document.getElementById('items-list');
            const money = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);

            let html = '';
            items.forEach(item => {
                html += `
                    <tr>
                        <td class="ps-4">
                            <input type="checkbox" class="form-check-input item-check" value="${item.id}" onchange="bulkManager.handleCheck(this)">
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="${normalizeExternalUrl(item.thumbnail)}" class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                <div>
                                    <div class="fw-bold text-truncate" style="max-width: 400px;">${item.title}</div>
                                    <small class="text-muted">${item.id}</small>
                                </div>
                            </div>
                        </td>
                        <td>${money(item.price)}</td>
                        <td><span class="badge bg-${item.status === 'active' ? 'success' : 'secondary'}">${item.status}</span></td>
                        <td class="text-end pe-4">${item.available_quantity}</td>
                    </tr>
                `;
            });
            container.innerHTML = html;
        },
        
        toggleAll: function(source) {
            document.querySelectorAll('.item-check').forEach(chk => {
                chk.checked = source.checked;
                this.handleCheck(chk);
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
            document.getElementById('selected-count-display').textContent = `${count} selecionados`;
            
            const action = document.getElementById('bulk-action').value;
            const btn = document.getElementById('btn-apply');
            
            btn.disabled = count === 0 || action === '';
        },
        
        apply: async function() {
            const action = document.getElementById('bulk-action').value;
            const value = document.getElementById('bulk-value').value;
            
            if (action.includes('price') && !value) return alert('Informe o valor');
            
            if (!confirm(`Aplicar ${action} para ${this.selectedIds.size} itens? Esta ação não pode ser desfeita.`)) return;
            
            try {
                const result = await requestJson('/api/items/bulk-update', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        ids: Array.from(this.selectedIds),
                        action: action,
                        value: value
                    })
                });
                
                if (result.success) {
                    Toast.success(result.message);
                    this.selectedIds.clear();
                    document.getElementById('check-all').checked = false;
                    this.updateUI();
                    this.loadItems(); // reload
                } else {
                    Toast.error(result.error);
                }
            } catch (e) {
                Toast.error('Erro ao processar');
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => bulkManager.init());
</script>
