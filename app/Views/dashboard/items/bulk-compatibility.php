<?php

declare(strict_types=1);

$title = 'Compatibilidades em Massa';
$subtitle = 'Adicione modelos compatíveis de moto em lote para seus anúncios';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';

?>

<div class="row g-3 mb-4">
    <!-- Stat cards -->
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <i class="bi bi-list-check fs-2 text-primary"></i>
                <h3 class="mt-2 mb-1" id="statTotal">—</h3>
                <p class="text-muted small mb-0">Total Carregados</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <i class="bi bi-exclamation-triangle fs-2 text-warning"></i>
                <h3 class="mt-2 mb-1" id="statMissing">—</h3>
                <p class="text-muted small mb-0">Sem Compatibilidade</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <i class="bi bi-check-circle fs-2 text-success"></i>
                <h3 class="mt-2 mb-1" id="statSelected">0</h3>
                <p class="text-muted small mb-0">Selecionados</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <i class="bi bi-lightning-charge fs-2 text-info"></i>
                <h3 class="mt-2 mb-1" id="statApplied">0</h3>
                <p class="text-muted small mb-0">Aplicados Hoje</p>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-puzzle-fill text-warning me-2"></i>Itens sem COMPATIBLE_MODELS</h5>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-sm btn-outline-secondary" id="btnRefresh" onclick="loadMissingItems()">
                <i class="bi bi-arrow-clockwise"></i> Recarregar
            </button>
            <button class="btn btn-sm btn-outline-primary" id="btnSuggestAll" onclick="suggestAll()" disabled>
                <i class="bi bi-stars"></i> Sugerir IA para Selecionados
            </button>
            <button class="btn btn-sm btn-success" id="btnApplyAll" onclick="applyAll()" disabled>
                <i class="bi bi-lightning-charge"></i> Aplicar Selecionados
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="itemsTable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3" style="width:40px">
                            <input type="checkbox" class="form-check-input" id="checkAll" onchange="toggleAll(this.checked)">
                        </th>
                        <th style="width:60px">Foto</th>
                        <th>Título</th>
                        <th style="width:100px">Preço</th>
                        <th style="width:80px">Vendas</th>
                        <th style="width:260px">Modelos Sugeridos pela IA</th>
                        <th style="width:110px" class="text-center">Ação</th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm me-2"></div>
                            Carregando itens…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
        <span class="text-muted small" id="paginationInfo"></span>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="btnPrev" onclick="changePage(-1)" disabled>
                <i class="bi bi-chevron-left"></i> Anterior
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="btnNext" onclick="changePage(1)" disabled>
                Próxima <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<!-- Modal de edição de sugestões -->
<div class="modal fade" id="editSuggestModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar Modelos Compatíveis</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="editModalTitle"></p>
                <label class="form-label fw-semibold">Modelos compatíveis <small class="text-muted">(um por linha)</small></label>
                <textarea class="form-control font-monospace" id="editModelsTextarea" rows="10" placeholder="CG 160&#10;Titan 160&#10;Fan 160&#10;..."></textarea>
                <div class="form-text mt-1">Cada linha = um modelo. Separe variações em linhas diferentes.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveEditedModels()">
                    <i class="bi bi-check-lg"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999" id="toastContainer"></div>

<script nonce="<?= CSP_NONCE ?>">
/* =============================================
   Bulk Compatibility Manager — JavaScript
   ============================================= */

const PAGE_LIMIT  = 50;
let currentOffset = 0;
let totalItems    = 0;
let allItems      = [];            // items loaded from API (without suggestions yet)
let suggestions   = {};           // { itemId: ['Model A', 'Model B'] }
let applied       = new Set();    // item IDs successfully applied this session
let editTarget    = null;         // item ID being edited in modal

// ─── Bootstrap ───────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadMissingItems();
});

// ─── Load items missing COMPATIBLE_MODELS ────────────────────────────────────
async function loadMissingItems() {
    setLoading(true);
    resetSelection();

    try {
        const data = await requestJson(
            `/api/compatibility/bulk/missing?limit=${PAGE_LIMIT}&offset=${currentOffset}`
        );

        if (data.error) {
            showError(data.error);
            return;
        }

        allItems  = data.items  || [];
        totalItems = data.paging?.total || allItems.length;

        document.getElementById('statTotal').textContent   = totalItems;
        document.getElementById('statMissing').textContent = data.missing_count ?? allItems.length;
        document.getElementById('paginationInfo').textContent =
            `Exibindo ${currentOffset + 1}–${currentOffset + allItems.length} de ${totalItems} ativos sem compatibilidade`;

        document.getElementById('btnPrev').disabled = currentOffset <= 0;
        document.getElementById('btnNext').disabled = currentOffset + allItems.length >= totalItems;

        renderTable();
    } catch (e) {
        showError('Erro de conexão: ' + e.message);
    } finally {
        setLoading(false);
    }
}

// ─── Render table ────────────────────────────────────────────────────────────
function renderTable() {
    const tbody = document.getElementById('itemsBody');

    if (allItems.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted">
            <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
            Todos os itens já possuem COMPATIBLE_MODELS preenchido!
        </td></tr>`;
        return;
    }

    tbody.innerHTML = allItems.map(item => {
        const sug  = suggestions[item.id] || [];
        const isApplied = applied.has(item.id);

        const sugHtml = sug.length > 0
            ? `<div class="d-flex flex-wrap gap-1">
                ${sug.slice(0, 5).map(m => `<span class="badge bg-light text-dark border">${escHtml(m)}</span>`).join('')}
                ${sug.length > 5 ? `<span class="badge bg-secondary">+${sug.length - 5}</span>` : ''}
               </div>`
            : `<span class="text-muted small">—</span>`;

        const actionBtns = isApplied
            ? `<span class="badge bg-success"><i class="bi bi-check-lg"></i> Aplicado</span>`
            : `<button class="btn btn-xs btn-outline-primary me-1" title="Editar modelos"
                        onclick="openEditModal('${escAttr(item.id)}', '${escAttr(item.title)}')">
                    <i class="bi bi-pencil"></i>
               </button>
               <button class="btn btn-xs btn-success" title="Aplicar este item"
                        onclick="applySingle('${escAttr(item.id)}')" ${sug.length === 0 ? 'disabled' : ''}>
                    <i class="bi bi-lightning"></i>
               </button>`;

        return `<tr data-id="${escAttr(item.id)}" class="${isApplied ? 'table-success' : ''}">
            <td class="ps-3">
                <input type="checkbox" class="form-check-input row-check" value="${escAttr(item.id)}"
                       onchange="updateSelectionCount()" ${isApplied ? 'disabled' : ''}>
            </td>
            <td>
                <img src="${escAttr(item.thumbnail?.replace('http://', 'https://') || '')}"
                     alt="" width="48" height="48" class="rounded object-fit-cover"
                     onerror="this.src='/public/img/no-image.png'">
            </td>
            <td>
                <div class="fw-semibold small">${escHtml(item.title)}</div>
                <div class="text-muted" style="font-size:0.72rem">${escHtml(item.id)}</div>
            </td>
            <td class="small">R$ ${Number(item.price || 0).toLocaleString('pt-BR', {minimumFractionDigits:2})}</td>
            <td class="text-center small">${item.sold_quantity ?? 0}</td>
            <td>${sugHtml}</td>
            <td class="text-center">${actionBtns}</td>
        </tr>`;
    }).join('');
}

// ─── Select all ──────────────────────────────────────────────────────────────
function toggleAll(checked) {
    document.querySelectorAll('.row-check:not(:disabled)').forEach(cb => {
        cb.checked = checked;
    });
    updateSelectionCount();
}

function updateSelectionCount() {
    const count = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('statSelected').textContent = count;
    document.getElementById('btnSuggestAll').disabled = count === 0;
    document.getElementById('btnApplyAll').disabled   = count === 0;
}

function resetSelection() {
    document.getElementById('checkAll').checked = false;
    document.getElementById('statSelected').textContent = '0';
    document.getElementById('btnSuggestAll').disabled = true;
    document.getElementById('btnApplyAll').disabled   = true;
}

function getSelectedIds() {
    return [...document.querySelectorAll('.row-check:checked')].map(cb => cb.value);
}

// ─── Suggest all selected ────────────────────────────────────────────────────
async function suggestAll() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;

    const items = allItems.filter(i => ids.includes(i.id));

    document.getElementById('btnSuggestAll').disabled = true;
    document.getElementById('btnSuggestAll').innerHTML =
        '<span class="spinner-border spinner-border-sm me-1"></span>Sugerindo…';

    try {
        const data = await requestJson('/api/compatibility/bulk/suggest-by-title', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: items.map(i => ({ id: i.id, title: i.title, category_id: i.category_id })) }),
        });

        if (data.error) { showError(data.error); return; }

        let count = 0;
        for (const [itemId, result] of Object.entries(data.results || {})) {
            if (result.suggestions?.length > 0) {
                suggestions[itemId] = result.suggestions;
                count++;
            }
        }

        renderTable();
        showToast(`${count} itens receberam sugestões da IA`, 'success');
    } catch (e) {
        showError('Erro ao sugerir: ' + e.message);
    } finally {
        document.getElementById('btnSuggestAll').disabled = false;
        document.getElementById('btnSuggestAll').innerHTML =
            '<i class="bi bi-stars"></i> Sugerir IA para Selecionados';
    }
}

// ─── Apply all selected ──────────────────────────────────────────────────────
async function applyAll() {
    const ids = getSelectedIds().filter(id => (suggestions[id] || []).length > 0);
    if (ids.length === 0) {
        showToast('Primeiro clique em "Sugerir IA" para gerar modelos', 'warning');
        return;
    }

    const applications = ids.map(id => ({ item_id: id, models: suggestions[id] }));

    document.getElementById('btnApplyAll').disabled = true;
    document.getElementById('btnApplyAll').innerHTML =
        '<span class="spinner-border spinner-border-sm me-1"></span>Aplicando…';

    try {
        const data = await requestJson('/api/compatibility/bulk/apply', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ applications }),
        });

        if (data.error) { showError(data.error); return; }

        let ok = 0, fail = 0;
        for (const r of data.results || []) {
            if (r.success) { applied.add(r.item_id); ok++; }
            else fail++;
        }

        document.getElementById('statApplied').textContent =
            parseInt(document.getElementById('statApplied').textContent || '0') + ok;

        renderTable();
        showToast(
            `${ok} item(s) atualizado(s) com sucesso${fail > 0 ? `, ${fail} com erro` : ''}`,
            ok > 0 ? 'success' : 'danger'
        );
    } catch (e) {
        showError('Erro ao aplicar: ' + e.message);
    } finally {
        document.getElementById('btnApplyAll').disabled = false;
        document.getElementById('btnApplyAll').innerHTML =
            '<i class="bi bi-lightning-charge"></i> Aplicar Selecionados';
    }
}

// ─── Apply single item ───────────────────────────────────────────────────────
async function applySingle(itemId) {
    const models = suggestions[itemId];
    if (!models || models.length === 0) {
        showToast('Gere sugestões primeiro', 'warning');
        return;
    }

    try {
        const data = await requestJson('/api/compatibility/bulk/apply', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ applications: [{ item_id: itemId, models }] }),
        });

        const result = (data.results || [])[0];
        if (result?.success) {
            applied.add(itemId);
            document.getElementById('statApplied').textContent =
                parseInt(document.getElementById('statApplied').textContent || '0') + 1;
            renderTable();
            showToast('Compatibilidade aplicada com sucesso!', 'success');
        } else {
            showToast(result?.message || 'Erro ao aplicar', 'danger');
        }
    } catch (e) {
        showError('Erro: ' + e.message);
    }
}

// ─── Edit modal ──────────────────────────────────────────────────────────────
function openEditModal(itemId, title) {
    editTarget = itemId;
    document.getElementById('editModalTitle').textContent = title;
    const existing = suggestions[itemId] || [];
    document.getElementById('editModelsTextarea').value = existing.join('\n');
    new bootstrap.Modal(document.getElementById('editSuggestModal')).show();
}

function saveEditedModels() {
    if (!editTarget) return;
    const lines = document.getElementById('editModelsTextarea').value
        .split('\n')
        .map(l => l.trim())
        .filter(l => l !== '');
    suggestions[editTarget] = lines;
    renderTable();
    bootstrap.Modal.getInstance(document.getElementById('editSuggestModal'))?.hide();
    showToast('Modelos salvos', 'success');
}

// ─── Pagination ───────────────────────────────────────────────────────────────
function changePage(direction) {
    currentOffset = Math.max(0, currentOffset + direction * PAGE_LIMIT);
    loadMissingItems();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ─── Utilities ───────────────────────────────────────────────────────────────
function setLoading(on) {
    document.getElementById('btnRefresh').disabled = on;
    if (on) {
        document.getElementById('itemsBody').innerHTML =
            '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm me-2"></div>Carregando…</td></tr>';
    }
}

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function escAttr(str) {
    return String(str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function showToast(msg, type = 'info') {
    const id  = 'toast_' + Date.now();
    const map = { success: 'bg-success', danger: 'bg-danger', warning: 'bg-warning text-dark', info: 'bg-info text-dark' };
    const html = `<div id="${id}" class="toast align-items-center text-white ${map[type] || 'bg-secondary'} border-0 show mb-2" role="alert">
        <div class="d-flex">
            <div class="toast-body small">${escHtml(msg)}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="document.getElementById('${id}').remove()"></button>
        </div>
    </div>`;
    document.getElementById('toastContainer').insertAdjacentHTML('beforeend', html);
    setTimeout(() => document.getElementById(id)?.remove(), 5000);
}

function showError(msg) {
    showToast(msg, 'danger');
    document.getElementById('itemsBody').innerHTML =
        `<tr><td colspan="7" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>${escHtml(msg)}</td></tr>`;
}
</script>

<style>
.btn-xs { padding: 0.15rem 0.4rem; font-size: 0.72rem; }
.object-fit-cover { object-fit: cover; }
</style>
