<?php

/**
 * Dashboard de Segurança
 * 
 * Exibe estatísticas de segurança, eventos recentes e IPs bloqueados
 */

$pageTitle = 'Dashboard de Segurança';
ob_start();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-shield-alt me-2"></i>Dashboard de Segurança</h1>
        <div>
            <button class="btn btn-outline-primary me-2" onclick="refreshData()">
                <i class="fas fa-sync-alt"></i> Atualizar
            </button>
            <button class="btn btn-outline-success" onclick="exportReport()">
                <i class="fas fa-download"></i> Exportar
            </button>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">IPs Bloqueados</h6>
                            <h2 id="blocked-count"><?= count($blockedIps ?? []) ?></h2>
                        </div>
                        <i class="fas fa-ban fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Eventos (24h)</h6>
                            <h2 id="events-count"><?= array_sum(array_column($stats['events'] ?? [], 'count')) ?></h2>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Rate Limits</h6>
                            <h2 id="rate-limit-count">
                                <?php
                                $rateLimits = array_filter($stats['events'] ?? [], fn($e) => $e['event_type'] === 'rate_limit_exceeded');
                                echo array_sum(array_column($rateLimits, 'count'));
                                ?>
                            </h2>
                        </div>
                        <i class="fas fa-tachometer-alt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Tokens Criptografados</h6>
                            <h2 id="encrypted-tokens">-</h2>
                        </div>
                        <i class="fas fa-lock fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Eventos Recentes -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Eventos Recentes</h5>
                    <div>
                        <select id="severity-filter" class="form-select form-select-sm d-inline-block w-auto">
                            <option value="">Todos</option>
                            <option value="critical">Crítico</option>
                            <option value="warning">Aviso</option>
                            <option value="info">Info</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="events-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>IP</th>
                                    <th>Severidade</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($events ?? [], 0, 20) as $event): ?>
                                    <tr>
                                        <td><?= date('d/m H:i:s', strtotime($event['created_at'])) ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($event['event_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <code><?= htmlspecialchars($event['ip_address']) ?></code>
                                        </td>
                                        <td>
                                            <?php
                                            $severityClass = match ($event['severity']) {
                                                'critical' => 'danger',
                                                'warning' => 'warning',
                                                default => 'info'
                                            };
                                            ?>
                                            <span class="badge bg-<?= $severityClass ?>">
                                                <?= ucfirst($event['severity']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger"
                                                onclick="blockIp('<?= htmlspecialchars($event['ip_address']) ?>')"
                                                title="Bloquear IP">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info"
                                                onclick="showDetails(<?= htmlspecialchars(json_encode($event)) ?>)"
                                                title="Detalhes">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- IPs Bloqueados -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-ban me-2"></i>IPs Bloqueados</h5>
                    <button class="btn btn-sm btn-outline-danger" onclick="showBlockIpModal()">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="list-group" id="blocked-ips-list">
                        <?php if (empty($blockedIps)): ?>
                            <p class="text-muted text-center">Nenhum IP bloqueado</p>
                        <?php else: ?>
                            <?php foreach ($blockedIps as $ip): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <code><?= htmlspecialchars($ip['ip_address']) ?></code>
                                        <small class="text-muted d-block"><?= htmlspecialchars($ip['reason']) ?></small>
                                        <?php if ($ip['blocked_until']): ?>
                                            <small class="text-info">
                                                Até: <?= date('d/m/Y H:i', strtotime($ip['blocked_until'])) ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-danger">Permanente</small>
                                        <?php endif; ?>
                                    </div>
                                    <button class="btn btn-sm btn-outline-success"
                                        onclick="unblockIp('<?= htmlspecialchars($ip['ip_address']) ?>')">
                                        <i class="fas fa-unlock"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Ações de Segurança -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Ações</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-warning" onclick="migrateTokens()">
                            <i class="fas fa-key me-2"></i>Migrar Tokens para Criptografia
                        </button>
                        <button class="btn btn-outline-info" onclick="checkTokensStatus()">
                            <i class="fas fa-shield-alt me-2"></i>Verificar Status Tokens
                        </button>
                        <button class="btn btn-outline-secondary" onclick="cleanupLogs()">
                            <i class="fas fa-broom me-2"></i>Limpar Logs Antigos
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalhes do Evento -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Evento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="event-details" class="bg-light p-3 rounded"></pre>
            </div>
        </div>
    </div>
</div>

<!-- Modal Bloquear IP -->
<div class="modal fade" id="blockIpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bloquear IP</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="block-ip-form">
                    <div class="mb-3">
                        <label class="form-label">Endereço IP</label>
                        <input type="text" class="form-control" name="ip" id="block-ip-input" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motivo</label>
                        <input type="text" class="form-control" name="reason" value="Manual block">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Duração (segundos, 0 = permanente)</label>
                        <input type="number" class="form-control" name="duration" value="0">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="submitBlockIp()">Bloquear</button>
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

    // Carregar status dos tokens na inicialização
    document.addEventListener('DOMContentLoaded', function() {
        checkTokensStatus();
    });

    function refreshData() {
        location.reload();
    }

    function exportReport() {
        window.location.href = '/api/security/export?format=json&hours=24';
    }

    function showDetails(event) {
        document.getElementById('event-details').textContent = JSON.stringify(event, null, 2);
        new bootstrap.Modal(document.getElementById('detailsModal')).show();
    }

    function showBlockIpModal() {
        document.getElementById('block-ip-input').value = '';
        new bootstrap.Modal(document.getElementById('blockIpModal')).show();
    }

    function blockIp(ip) {
        document.getElementById('block-ip-input').value = ip;
        new bootstrap.Modal(document.getElementById('blockIpModal')).show();
    }

    function submitBlockIp() {
        const form = document.getElementById('block-ip-form');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        requestJson('/api/security/block-ip', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(data => {
                if (data.success) {
                    alert('IP bloqueado com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + (data.error || 'Falha ao bloquear IP'));
                }
            })
            .catch(err => alert('Erro: ' + err.message));
    }

    function unblockIp(ip) {
        if (!confirm('Deseja realmente desbloquear ' + ip + '?')) return;

        requestJson('/api/security/unblock-ip', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ip: ip
                })
            })
            .then(data => {
                if (data.success) {
                    alert('IP desbloqueado!');
                    location.reload();
                } else {
                    alert('Erro: ' + (data.error || 'Falha ao desbloquear'));
                }
            })
            .catch(err => alert('Erro: ' + err.message));
    }

    function migrateTokens() {
        if (!confirm('Deseja migrar todos os tokens para criptografia? Esta ação não pode ser desfeita.')) return;

        requestJson('/api/security/migrate-tokens', {
                method: 'POST'
            })
            .then(data => {
                if (data.success) {
                    alert('Migração concluída!\nTokens migrados: ' + data.data.migrated);
                    checkTokensStatus();
                } else {
                    alert('Erro: ' + (data.error || 'Falha na migração'));
                }
            })
            .catch(err => alert('Erro: ' + err.message));
    }

    function checkTokensStatus() {
        requestJson('/api/security/tokens-status')
            .then(data => {
                if (data.success) {
                    const d = data.data;
                    document.getElementById('encrypted-tokens').textContent =
                        d.encryption_percentage + '%';
                }
            })
            .catch(err => console.error('Erro ao verificar tokens:', err));
    }

    function cleanupLogs() {
        const days = prompt('Excluir logs mais antigos que quantos dias?', '30');
        if (!days) return;

        requestJson('/api/security/cleanup-logs', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'days=' + days
            })
            .then(data => {
                if (data.success) {
                    alert('Limpeza concluída!\nLogs excluídos: ' + data.data.logs_deleted +
                        '\nBloqueios expirados removidos: ' + data.data.expired_blocks_removed);
                    location.reload();
                } else {
                    alert('Erro: ' + (data.error || 'Falha na limpeza'));
                }
            })
            .catch(err => alert('Erro: ' + err.message));
    }

    // Filtro por severidade
    document.getElementById('severity-filter')?.addEventListener('change', function() {
        const severity = this.value;
        const rows = document.querySelectorAll('#events-table tbody tr');

        rows.forEach(row => {
            if (!severity || row.textContent.toLowerCase().includes(severity)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
