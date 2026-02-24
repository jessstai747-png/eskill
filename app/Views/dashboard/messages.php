<?php
$title = 'Mensagens Automáticas';
$subtitle = 'Configure as mensagens enviadas automaticamente aos compradores';
$breadcrumbs = [['label' => 'Mensagens', 'url' => '']];

// Account Selector
$requireAccountSelection = true;
$showAccountBanner = true;
include __DIR__ . '/../components/account-selector.php';

include __DIR__ . '/../layouts/modern/partials/page-header.php';
?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0">Adicionar Nova Mensagem</h6>
            </div>
            <div class="card-body">
                <form id="templateForm">
                    <input type="hidden" id="editId">
                    <div class="mb-3">
                        <label class="form-label">Nome Interno</label>
                        <input type="text" class="form-control" id="formName" placeholder="Ex: Agradecimento Padrão" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gatilho (Quando enviar?)</label>
                        <select class="form-select" id="formTrigger" required>
                            <option value="paid">Compra Confirmada (Paid)</option>
                            <option value="shipped">Produto Enviado (Shipped)</option>
                            <option value="delivered">Produto Entregue (Delivered)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Conteúdo da Mensagem</label>
                        <textarea class="form-control" id="formContent" rows="6" required></textarea>
                        <div class="form-text small mt-2">
                            <span class="d-block mb-1">Variáveis disponíveis:</span>
                            <span class="badge bg-light text-dark border me-1 cursor-pointer" onclick="insertVar('{buyer_name}')">{buyer_name}</span>
                            <span class="badge bg-light text-dark border me-1 cursor-pointer" onclick="insertVar('{product_title}')">{product_title}</span>
                            <span class="badge bg-light text-dark border me-1 cursor-pointer" onclick="insertVar('{order_id}')">{order_id}</span>
                        </div>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="formActive" checked>
                        <label class="form-check-label" for="formActive">Ativar este template</label>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary" id="saveBtn">
                            <i class="bi bi-plus-circle me-1"></i> Salvar Template
                        </button>
                        <button type="button" class="btn btn-outline-secondary d-none" id="cancelBtn" onclick="resetForm()">
                            Cancelar Edição
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Templates Configurados</h6>
                <button class="btn btn-sm btn-outline-primary" onclick="loadTemplates()">
                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Nome</th>
                                <th>Gatilho</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="templatesList">
                            <!-- Templates loaded via JS -->
                            <tr><td colspan="4" class="text-center py-5 text-muted">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

document.addEventListener('DOMContentLoaded', loadTemplates);

function insertVar(text) {
    const textarea = document.getElementById('formContent');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const before = textarea.value.substring(0, start);
    const after = textarea.value.substring(end, textarea.value.length);
    textarea.value = before + text + after;
    textarea.focus();
    textarea.selectionStart = start + text.length;
    textarea.selectionEnd = start + text.length;
}

async function loadTemplates() {
    try {
        const data = await requestJson('/api/messages/templates?account_id=' + (getAccountId() || ''));
        
        const list = document.getElementById('templatesList');
        if (!data.success || !data.templates.length) {
            list.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted">Nenhum template encontrado. Crie o primeiro ao lado!</td></tr>';
            return;
        }

        list.innerHTML = data.templates.map(tpl => `
            <tr>
                <td class="ps-4 fw-medium">${escapeHtml(tpl.name)}</td>
                <td>${getTriggerBadge(tpl.event_trigger)}</td>
                <td>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" onchange="toggleActive(${tpl.id}, this.checked)" ${tpl.is_active == 1 ? 'checked' : ''}>
                    </div>
                </td>
                <td class="text-end pe-4">
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-secondary" onclick='editTemplate(${JSON.stringify(tpl).replace(/'/g, "&#39;")})'>
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(${tpl.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    } catch (e) {
        console.error(e);
        document.getElementById('templatesList').innerHTML = '<tr><td colspan="4" class="text-center text-danger py-5">Erro ao carregar templates</td></tr>';
    }
}

function getTriggerBadge(trigger) {
    const map = {
        'paid': '<span class="badge bg-success bg-opacity-10 text-success">Compra Confirmada</span>',
        'shipped': '<span class="badge bg-primary bg-opacity-10 text-primary">Enviado</span>',
        'delivered': '<span class="badge bg-info bg-opacity-10 text-info">Entregue</span>'
    };
    return map[trigger] || `<span class="badge bg-secondary">${trigger}</span>`;
}

document.getElementById('templateForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const id = document.getElementById('editId').value;
    const url = id ? `/api/messages/templates/${id}` : '/api/messages/templates';
    const method = id ? 'PUT' : 'POST';

    const body = {
        name: document.getElementById('formName').value,
        event_trigger: document.getElementById('formTrigger').value,
        content: document.getElementById('formContent').value,
        is_active: document.getElementById('formActive').checked ? 1 : 0
    };

    if (!id) body.account_id = getAccountId();

    try {
        const res = await fetch(url, {
            method: method,
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
            body: JSON.stringify(body)
        });
        
        if (res.ok) {
            resetForm();
            loadTemplates();
            // showToast('Salvo com sucesso'); 
        } else {
            alert('Erro ao salvar');
        }
    } catch (e) {
        alert('Erro ao salvar');
    }
});

function editTemplate(tpl) {
    document.getElementById('editId').value = tpl.id;
    document.getElementById('formName').value = tpl.name;
    document.getElementById('formTrigger').value = tpl.event_trigger;
    document.getElementById('formContent').value = tpl.content;
    document.getElementById('formActive').checked = tpl.is_active == 1;
    
    document.getElementById('saveBtn').innerHTML = '<i class="bi bi-check-circle me-1"></i> Atualizar Template';
    document.getElementById('cancelBtn').classList.remove('d-none');
}

function resetForm() {
    document.getElementById('templateForm').reset();
    document.getElementById('editId').value = '';
    document.getElementById('saveBtn').innerHTML = '<i class="bi bi-plus-circle me-1"></i> Salvar Template';
    document.getElementById('cancelBtn').classList.add('d-none');
}

async function deleteTemplate(id) {
    if(!confirm('Tem certeza?')) return;
    await requestJson(`/api/messages/templates/${id}`, {method: 'DELETE', headers: {'X-CSRF-Token': csrfToken}});
    loadTemplates();
}

async function toggleActive(id, active) {
    await requestJson(`/api/messages/templates/${id}`, {
        method: 'PUT', 
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
        body: JSON.stringify({is_active: active ? 1 : 0})
    });
}

function getAccountId() {
    // Helper to get active account ID from session or selector
    // Assuming global function or hidden input exists
    return document.querySelector('select#accountSelector')?.value || 
           <?php echo \App\Helpers\SessionHelper::getActiveAccountId() ?? 0; ?>;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
