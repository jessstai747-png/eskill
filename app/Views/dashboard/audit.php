<?php
/**
 * Auditoria - Versão Moderna
 * Integrado com o layout moderno
 */
?>

<!-- Custom Styles for Audit -->
<style>
    .log-details-pre {
        background-color: var(--bs-light);
        padding: 1rem;
        border-radius: 0.5rem;
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid var(--bs-border-color);
    }
    
    [data-bs-theme="dark"] .log-details-pre {
        background-color: #2b3035;
        border-color: #495057;
        color: #e9ecef;
    }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-shield-check text-primary"></i>
        Logs de Auditoria
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadLogs()">
            <i class="bi bi-arrow-clockwise"></i> Atualizar
        </button>
    </div>
</div>

<div class="alert alert-info d-flex align-items-center mb-4">
    <i class="bi bi-info-circle me-3 fs-4"></i>
    <div>
        <strong>Registro de Atividades</strong>
        <br>
        Monitore todas as ações realizadas no sistema para segurança e conformidade.
    </div>
</div>

<!-- Filtros -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h5 class="card-title mb-0"><i class="bi bi-funnel"></i> Filtros</h5>
    </div>
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Ação</label>
                <input type="text" class="form-control" id="action" placeholder="Ex: login, update_item">
            </div>
            <div class="col-md-3">
                <label class="form-label">ID Usuário</label>
                <input type="number" class="form-control" id="user_id" placeholder="ID">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data Início</label>
                <input type="date" class="form-control" id="date_from">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data Fim</label>
                <input type="date" class="form-control" id="date_to">
            </div>
            <div class="col-12 text-end">
                <button type="button" class="btn btn-secondary" onclick="clearFilters()">Limpar</button>
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Tabela -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Data/Hora</th>
                        <th>Ação</th>
                        <th>Usuário</th>
                        <th>Conta ML</th>
                        <th>IP</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody id="logsTableBody">
                    <tr>
                        <td colspan="7" class="text-center py-4">Carregando...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Detalhes -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="logDetails" class="log-details-pre"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

    document.addEventListener('DOMContentLoaded', loadLogs);
    
    // Ensure form exists before adding listener to avoid null reference if DOM not ready
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            loadLogs();
        });
    }

    function clearFilters() {
        document.getElementById('filterForm').reset();
        loadLogs();
    }

    async function loadLogs() {
        const tbody = document.getElementById('logsTableBody');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>';

        const params = new URLSearchParams();
        const fields = ['action', 'user_id', 'date_from', 'date_to'];

        fields.forEach(field => {
            const el = document.getElementById(field);
            if (el && el.value) params.append(field, el.value);
        });

        try {
            const logs = await requestJson(`/api/audit?${params.toString()}`);

            if (logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Nenhum registro encontrado</td></tr>';
                return;
            }

            tbody.innerHTML = logs.map(log => `
                <tr>
                    <td>${log.id}</td>
                    <td>${new Date(log.created_at).toLocaleString('pt-BR')}</td>
                    <td><span class="badge bg-info text-dark rounded-pill">${log.action}</span></td>
                    <td>${log.user_id || '-'}</td>
                    <td>${log.ml_account_id || '-'}</td>
                    <td><small class="text-muted">${log.ip_address}</small></td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary" onclick='showDetails(${JSON.stringify(log.data || {})})'>
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        } catch (error) {
            console.error(error);
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Erro ao carregar logs</td></tr>';
        }
    }

    function showDetails(data) {
        document.getElementById('logDetails').textContent = JSON.stringify(data, null, 2);
        new bootstrap.Modal(document.getElementById('detailsModal')).show();
    }
</script>