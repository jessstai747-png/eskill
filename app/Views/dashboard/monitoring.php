<!-- Dashboard Monitoring View -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Monitoramento do Sistema</h4>
        <p class="text-muted mb-0">Métricas de performance e saúde da aplicação</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success" id="systemStatus"><i class="bi bi-circle-fill me-1"></i>Online</span>
        <button class="btn btn-outline-primary btn-sm" onclick="loadMetrics()"><i class="bi bi-arrow-clockwise"></i> Atualizar</button>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">CPU</span>
                    <span class="fw-bold" id="cpuUsage">0%</span>
                </div>
                <div class="progress" style="height:8px">
                    <div class="progress-bar bg-primary" id="cpuBar" style="width:0%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Memória</span>
                    <span class="fw-bold" id="memUsage">0%</span>
                </div>
                <div class="progress" style="height:8px">
                    <div class="progress-bar bg-success" id="memBar" style="width:0%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Disco</span>
                    <span class="fw-bold" id="diskUsage">0%</span>
                </div>
                <div class="progress" style="height:8px">
                    <div class="progress-bar bg-warning" id="diskBar" style="width:0%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Uptime</span>
                    <span class="fw-bold" id="uptime">-</span>
                </div>
                <small class="text-muted" id="lastRestart">-</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Requisições (últimas 24h)</h6>
            </div>
            <div class="card-body">
                <canvas id="requestsChart" height="250"></canvas>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Tempo de Resposta</h6>
            </div>
            <div class="card-body">
                <canvas id="responseChart" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-database me-2"></i>Banco de Dados</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Conexões Ativas</span>
                        <span id="dbConnections">0</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Queries/min</span>
                        <span id="dbQueries">0</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Slow Queries</span>
                        <span id="dbSlowQueries" class="text-warning">0</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span>Tamanho Total</span>
                        <span id="dbSize">0 MB</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Cache</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between small mb-1">
                    <span>Hit Rate</span>
                    <span id="cacheHitRate" class="text-success">0%</span>
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span>Itens em Cache</span>
                    <span id="cacheItems">0</span>
                </div>
                <div class="d-flex justify-content-between small">
                    <span>Memória Usada</span>
                    <span id="cacheMemory">0 MB</span>
                </div>
                <button class="btn btn-sm btn-outline-danger w-100 mt-3" onclick="clearCache()">
                    <i class="bi bi-trash"></i> Limpar Cache
                </button>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Últimos Erros</h6>
            </div>
            <div class="list-group list-group-flush" id="errorsList" style="max-height:200px;overflow-y:auto">
                <div class="text-center py-3 text-muted small">Sem erros recentes</div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

let requestsChart, responseChart;

async function loadMetrics() {
    try {
        const data = await requestJson('/api/monitoring/metrics');
        
        // System metrics
        document.getElementById('cpuUsage').textContent = (data.cpu || 0) + '%';
        document.getElementById('cpuBar').style.width = (data.cpu || 0) + '%';
        document.getElementById('memUsage').textContent = (data.memory || 0) + '%';
        document.getElementById('memBar').style.width = (data.memory || 0) + '%';
        document.getElementById('diskUsage').textContent = (data.disk || 0) + '%';
        document.getElementById('diskBar').style.width = (data.disk || 0) + '%';
        document.getElementById('uptime').textContent = data.uptime || '-';
        document.getElementById('lastRestart').textContent = 'Último restart: ' + (data.last_restart || '-');
        
        // Database
        document.getElementById('dbConnections').textContent = data.db?.connections || 0;
        document.getElementById('dbQueries').textContent = data.db?.queries_per_min || 0;
        document.getElementById('dbSlowQueries').textContent = data.db?.slow_queries || 0;
        document.getElementById('dbSize').textContent = data.db?.size || '0 MB';
        
        // Cache
        document.getElementById('cacheHitRate').textContent = (data.cache?.hit_rate || 0) + '%';
        document.getElementById('cacheItems').textContent = data.cache?.items || 0;
        document.getElementById('cacheMemory').textContent = data.cache?.memory || '0 MB';
        
        // Charts
        updateRequestsChart(data.requests_by_hour || []);
        updateResponseChart(data.response_times || []);
        
        // Errors
        renderErrors(data.recent_errors || []);
    } catch (e) {
        console.error('Error loading metrics:', e);
    }
}

function updateRequestsChart(data) {
    const ctx = document.getElementById('requestsChart').getContext('2d');
    if (requestsChart) requestsChart.destroy();
    
    requestsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(d => d.hour + 'h'),
            datasets: [{
                label: 'Requisições',
                data: data.map(d => d.count),
                backgroundColor: 'rgba(13, 110, 253, 0.5)',
                borderColor: '#0d6efd',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } }
        }
    });
}

function updateResponseChart(data) {
    const ctx = document.getElementById('responseChart').getContext('2d');
    if (responseChart) responseChart.destroy();
    
    responseChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(d => d.time),
            datasets: [{
                label: 'Tempo de Resposta (ms)',
                data: data.map(d => d.avg),
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } }
        }
    });
}

function renderErrors(errors) {
    const container = document.getElementById('errorsList');
    if (errors.length === 0) {
        container.innerHTML = '<div class="text-center py-3 text-muted small">Sem erros recentes</div>';
        return;
    }
    
    container.innerHTML = errors.map(e => `
        <div class="list-group-item py-2">
            <div class="d-flex justify-content-between">
                <small class="text-danger text-truncate" style="max-width:180px">${e.message}</small>
            </div>
            <small class="text-muted">${e.time}</small>
        </div>
    `).join('');
}

async function clearCache() {
    if (!confirm('Limpar todo o cache? Isso pode afetar temporariamente a performance.')) return;
    
    try {
        await requestJson('/api/cache/clear', { method: 'POST' });
        alert('Cache limpo com sucesso!');
        loadMetrics();
    } catch (e) {
        alert('Erro ao limpar cache');
    }
}

loadMetrics();
setInterval(loadMetrics, 30000);
</script>
