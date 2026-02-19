<?php
/**
 * Dashboard de Logs do Sistema
 */

use App\Helpers\SecurityHelper;

$pageTitle = 'Logs do Sistema';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/dashboard'],
    ['title' => 'Logs', 'url' => '/dashboard/logs']
];

require_once __DIR__ . '/../../layouts/header.php';
?>

<style>
.log-entry {
    border-left: 4px solid #e5e7eb;
    padding: 1rem;
    margin-bottom: 0.5rem;
    background: white;
    border-radius: 0 8px 8px 0;
    transition: all 0.2s;
}
.log-entry:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.log-entry.debug { border-left-color: #6b7280; }
.log-entry.info { border-left-color: #3b82f6; }
.log-entry.warning { border-left-color: #f59e0b; }
.log-entry.error { border-left-color: #ef4444; }
.log-entry.critical { border-left-color: #dc2626; background: #fef2f2; }

.log-level {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}
.log-level.debug { background: #f3f4f6; color: #6b7280; }
.log-level.info { background: #dbeafe; color: #1e40af; }
.log-level.warning { background: #fef3c7; color: #92400e; }
.log-level.error { background: #fee2e2; color: #991b1b; }
.log-level.critical { background: #dc2626; color: white; }

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.stat-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}
.log-message {
    font-family: 'Monaco', 'Courier New', monospace;
    font-size: 0.875rem;
    margin: 0.5rem 0;
}
.log-context {
    background: #f9fafb;
    padding: 0.75rem;
    border-radius: 6px;
    font-family: 'Monaco', 'Courier New', monospace;
    font-size: 0.75rem;
    max-height: 200px;
    overflow-y: auto;
    display: none;
}
.log-context.expanded {
    display: block;
}
.log-meta {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.5rem;
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2">
                        <i class="bi bi-file-text"></i> Logs do Sistema
                    </h1>
                    <p class="text-muted mb-0">
                        Visualização e análise de logs estruturados
                    </p>
                </div>
                <div>
                    <button class="btn btn-outline-danger" onclick="cleanupOldLogs()">
                        <i class="bi bi-trash"></i> Limpar Antigos
                    </button>
                    <button class="btn btn-primary" onclick="exportLogs()">
                        <i class="bi bi-download"></i> Exportar CSV
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="stat-card text-center">
                <div class="stat-value text-primary"><?= $stats['total'] ?? 0 ?></div>
                <div class="text-muted small">Total de Logs</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card text-center">
                <div class="stat-value text-secondary"><?= $stats['by_level']['debug'] ?? 0 ?></div>
                <div class="text-muted small">Debug</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card text-center">
                <div class="stat-value text-info"><?= $stats['by_level']['info'] ?? 0 ?></div>
                <div class="text-muted small">Info</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card text-center">
                <div class="stat-value text-warning"><?= $stats['by_level']['warning'] ?? 0 ?></div>
                <div class="text-muted small">Warnings</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card text-center">
                <div class="stat-value text-danger"><?= $stats['by_level']['error'] ?? 0 ?></div>
                <div class="text-muted small">Errors</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card text-center">
                <div class="stat-value" style="color: #dc2626;"><?= $stats['by_level']['critical'] ?? 0 ?></div>
                <div class="text-muted small">Critical</div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Nível</label>
                            <select class="form-select" name="level" id="levelFilter">
                                <option value="">Todos</option>
                                <option value="debug">Debug</option>
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="error">Error</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Buscar</label>
                            <input type="text" class="form-control" name="search" id="searchInput" 
                                   placeholder="Buscar em mensagens e contexto...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Início</label>
                            <input type="datetime-local" class="form-control" name="start_date">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Fim</label>
                            <input type="datetime-local" class="form-control" name="end_date">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Logs -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Logs Recentes</h5>
                        <button class="btn btn-sm btn-outline-secondary" onclick="refreshLogs()">
                            <i class="bi bi-arrow-clockwise"></i> Atualizar
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="logsContainer" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($logs)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                <p class="mt-3">Nenhum log encontrado</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($logs as $log): 
                                $level = strtolower($log['level_name'] ?? 'info');
                                $timestamp = $log['datetime'] ?? '';
                                $message = SecurityHelper::e($log['message'] ?? '');
                                $context = $log['context'] ?? [];
                                $extra = $log['extra'] ?? [];
                            ?>
                                <div class="log-entry <?= $level ?>" data-log-id="<?= uniqid() ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <span class="log-level <?= $level ?>"><?= $level ?></span>
                                            <span class="text-muted ms-2"><?= $timestamp ?></span>
                                            <div class="log-message"><?= $message ?></div>
                                            
                                            <?php if (!empty($context) || !empty($extra)): ?>
                                                <button class="btn btn-sm btn-link text-decoration-none p-0 mt-1" 
                                                        onclick="toggleContext(this)">
                                                    <i class="bi bi-chevron-down"></i> Ver detalhes
                                                </button>
                                                <div class="log-context">
                                                    <pre class="mb-0"><?= json_encode(array_merge($context, ['extra' => $extra]), JSON_PRETTY_PRINT) ?></pre>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="log-meta">
                                                <?php if (isset($extra['user_id'])): ?>
                                                    <i class="bi bi-person"></i> Usuário: <?= $extra['user_id'] ?>
                                                <?php endif; ?>
                                                <?php if (isset($extra['ip'])): ?>
                                                    <i class="bi bi-geo-alt ms-2"></i> IP: <?= $extra['ip'] ?>
                                                <?php endif; ?>
                                                <?php if (isset($extra['url'])): ?>
                                                    <i class="bi bi-link ms-2"></i> <?= SecurityHelper::e($extra['url']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
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

// Filtrar logs
document.getElementById('filterForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const params = new URLSearchParams(formData);
    
    window.location.href = '/dashboard/logs?' + params.toString();
});

// Atualizar em tempo real
let autoRefresh = false;
function refreshLogs() {
    window.location.reload();
}

// Toggle context
function toggleContext(button) {
    const context = button.nextElementSibling;
    context.classList.toggle('expanded');
    
    const icon = button.querySelector('i');
    icon.classList.toggle('bi-chevron-down');
    icon.classList.toggle('bi-chevron-up');
    
    button.innerHTML = context.classList.contains('expanded') 
        ? '<i class="bi bi-chevron-up"></i> Ocultar detalhes'
        : '<i class="bi bi-chevron-down"></i> Ver detalhes';
}

// Limpar logs antigos
function cleanupOldLogs() {
    if (!confirm('Tem certeza que deseja remover logs com mais de 30 dias?')) {
        return;
    }
    
    requestJson('/api/logs/cleanup', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ days: 30 })
    })
    .then(data => {
        alert(data.message);
        window.location.reload();
    })
    .catch(error => {
        alert('Erro ao limpar logs: ' + error.message);
    });
}

// Exportar logs
function exportLogs() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = '/api/logs/export?' + params.toString();
}

// Auto-refresh a cada 30 segundos (opcional)
// setInterval(refreshLogs, 30000);
</script>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
