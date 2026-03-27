<?php

declare(strict_types=1);

$title = 'System Health';
$subtitle = 'Monitoramento de Infraestrutura e Performance';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<div class="row g-4 mb-4">
    <!-- Database Status -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-muted mb-0">Database</h6>
                    <i class="bi bi-database text-primary fs-4"></i>
                </div>
                <h3 class="fw-bold mb-1" id="db-latency">-- ms</h3>
                <div id="db-status-badge">
                    <span class="badge bg-secondary">Verificando...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Redis Status -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-muted mb-0">Redis / Cache</h6>
                    <i class="bi bi-lightning-charge text-warning fs-4"></i>
                </div>
                <h3 class="fw-bold mb-1" id="redis-latency">-- ms</h3>
                <div id="redis-status-badge">
                    <span class="badge bg-secondary">Verificando...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Disk Usage -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-muted mb-0">Disco</h6>
                    <i class="bi bi-hdd text-secondary fs-4"></i>
                </div>
                <h3 class="fw-bold mb-1" id="disk-percent">--%</h3>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-info" id="disk-bar" style="width: 0%"></div>
                </div>
                <small class="text-muted mt-1 d-block" id="disk-details">-- GB livres</small>
            </div>
        </div>
    </div>

    <!-- Queue Status -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-muted mb-0">Fila de Jobs</h6>
                    <i class="bi bi-layers text-success fs-4"></i>
                </div>
                <h3 class="fw-bold mb-1" id="queue-size">--</h3>
                <small class="text-muted">Jobs pendentes</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0">Informações do Servidor</h6>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        PHP Version
                        <span class="fw-bold" id="php-version">--</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        Server Software
                        <span class="fw-bold" id="server-software">--</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        Memory Limit
                        <span class="fw-bold" id="memory-limit">--</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        Memory Used (Script)
                        <span class="fw-bold" id="memory-used">--</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body text-center py-5">
                <i class="bi bi-activity fs-1 text-muted mb-3 d-block"></i>
                <h5>Auto-Refresh Ativo</h5>
                <p class="text-muted">O monitoramento atualiza a cada 30 segundos.</p>
                <button class="btn btn-primary" onclick="healthMonitor.check()">
                    <i class="bi bi-arrow-clockwise"></i> Atualizar Agora
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">

    const healthMonitor = {
        init: function() {
            this.check();
            setInterval(() => this.check(), 30000);
        },

        check: async function() {
            try {
                const data = await requestJson('/api/health/check');
                
                if (data.success) {
                    this.render(data);
                }
            } catch (e) {
                console.error('Health check failed', e);
            }
        },

        render: function(data) {
            // DB
            document.getElementById('db-latency').textContent = data.database.latency_ms + ' ms';
            const dbBadge = data.database.status === 'ok' 
                ? '<span class="badge bg-success">Online</span>' 
                : '<span class="badge bg-danger">Offline</span>';
            document.getElementById('db-status-badge').innerHTML = dbBadge;

            // Redis
            document.getElementById('redis-latency').textContent = data.redis.latency_ms + ' ms';
            const redisBadge = data.redis.status === 'ok' 
                ? '<span class="badge bg-success">Online</span>' 
                : (data.redis.status === 'not_configured' ? '<span class="badge bg-secondary">N/A</span>' : '<span class="badge bg-danger">Erro</span>');
            document.getElementById('redis-status-badge').innerHTML = redisBadge;

            // Disk
            document.getElementById('disk-percent').textContent = data.storage.used_percent + '%';
            document.getElementById('disk-bar').style.width = data.storage.used_percent + '%';
            document.getElementById('disk-details').textContent = data.storage.free_gb + ' GB livres';

            // Queue
            document.getElementById('queue-size').textContent = data.queue.pending_jobs;
            
            // System
            document.getElementById('php-version').textContent = data.system.php_version;
            document.getElementById('server-software').textContent = data.system.server_software;
            document.getElementById('memory-limit').textContent = data.memory.limit;
            document.getElementById('memory-used').textContent = data.memory.used_mb + ' MB';
        }
    };

    document.addEventListener('DOMContentLoaded', () => healthMonitor.init());
</script>
