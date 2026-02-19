async function requestJson(url, options = {}) {
    if (window.ApiClient) return window.ApiClient.request(url, options);
    const resp = await fetch(url, { credentials: 'include', ...options });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}

const AICenter = {
    init: function () {
        this.loadStats();
        this.loadStatus();
        this.initIntervals();
    },

    initIntervals: function () {
        // Refresh status every 5s (Heartbeat)
        setInterval(() => this.loadStatus(), 5000);
        // Refresh stats every 30s
        setInterval(() => this.loadStats(), 30000);
    },

    refreshApps: function () {
        this.loadStats();
        this.loadStatus();
    },

    loadStats: function () {
        requestJson('/api/ai-center/stats')
            .then(data => {
                this.renderDecisionStats(data.decisions);
                this.renderPredictionChart(data.predictive);
                if (data.activity) this.renderActivityFeed(data.activity);
            })
            .catch(err => console.error('Stats Error:', err));
    },

    loadStatus: function () {
        requestJson('/api/ai-center/status')
            .then(data => {
                this.renderHarnessStatus(data.harness);
                this.renderAutoPilotStatus(data.autopilot);
            })
            .catch(err => console.error('Status Error:', err));
    },

    renderDecisionStats: function (stats) {
        const container = document.getElementById('decision-stats-body');
        if (!stats) return;

        container.innerHTML = `
            <div class="row text-center">
                <div class="col-4 border-end">
                    <h6 class="text-muted text-uppercase small mb-2">Decisões Totais</h6>
                    <div class="h2 fw-bold text-dark">${stats.total_decisions || 0}</div>
                </div>
                <div class="col-4 border-end">
                    <h6 class="text-muted text-uppercase small mb-2">Preços Ajustados</h6>
                    <div class="h2 fw-bold text-primary">${stats.pricing_updates || 0}</div>
                </div>
                <div class="col-4">
                    <h6 class="text-muted text-uppercase small mb-2">Precisão IA</h6>
                    <div class="h2 fw-bold text-success">${stats.accuracy || '0%'}</div>
                </div>
            </div>
        `;
    },

    renderHarnessStatus: function (harness) {
        const container = document.getElementById('harness-status-container');
        const list = document.getElementById('health-list');
        const uptime = document.getElementById('uptime-display');

        // Status Cards
        const statusColor = harness.status === 'running' ? 'success' : (harness.status === 'idle' ? 'warning' : 'danger');

        container.innerHTML = `
            <div class="col-md-3">
                <div class="card h-100 border-${statusColor} border-start border-4 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase">Worker Status</div>
                        <div class="h4 mt-2 mb-0 fw-bold text-${statusColor}">
                            <span class="status-dot ${harness.status === 'running' ? 'online' : 'offline'} me-2"></span>
                            ${harness.status.toUpperCase()}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-start border-4 border-info shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase">Current Task</div>
                        <div class="h5 mt-2 mb-0 text-truncate" title="${harness.current_task}">
                            ${harness.current_task || 'None'}
                        </div>
                    </div>
                </div>
            </div>
             <div class="col-md-3">
                <div class="card h-100 border-start border-4 border-primary shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase">Heartbeat</div>
                        <div class="h4 mt-2 mb-0">
                            ${this.timeSince(harness.last_heartbeat)}
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Health List
        uptime.innerText = harness.uptime_formatted;
    },

    renderAutoPilotStatus: function (ap) {
        const toggle = document.getElementById('autopilotToggle');
        const statusText = document.getElementById('ap-status-text');

        toggle.checked = ap.enabled;
        statusText.innerText = ap.enabled ? 'ATIVO' : 'PAUSADO';

        document.getElementById('ap-mode-text').innerText = ap.mode;
        document.getElementById('ap-items-text').innerText = ap.active_optimizations;
    },

    renderPredictionChart: function (data) {
        const ctx = document.getElementById('predictionChart');
        if (!ctx) return;

        // If data is missing or empty, show Zero State (could be improved visually)
        if (!data || !data.chart_data || data.chart_data.length === 0) {
            // Check if we just want to clear the chart
            if (window.predictionChartInstance) {
                window.predictionChartInstance.destroy();
                window.predictionChartInstance = null;
            }
            // Draw text on canvas or replace canvas
            const parent = ctx.parentElement;
            if (parent.querySelector('.no-data-msg')) return;

            // Simple "No Data" overlay
            // For now, let's just leave it empty or render empty chart
            return;
        }

        // Check if chart instance exists
        if (window.predictionChartInstance) {
            window.predictionChartInstance.data.labels = data.chart_labels || [];
            window.predictionChartInstance.data.datasets[0].data = data.chart_data || [];
            window.predictionChartInstance.update();
            return;
        }

        window.predictionChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.chart_labels || ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                datasets: [{
                    label: 'Previsão de Demanda',
                    data: data.chart_data || [0, 0, 0, 0, 0],
                    borderColor: '#4f46e5',
                    tension: 0.4,
                    fill: true,
                    backgroundColor: 'rgba(79, 70, 229, 0.1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { border: { display: false }, beginAtZero: true }
                }
            }
        });
    },

    renderActivityFeed: function (activities) {
        const container = document.getElementById('activity-feed-container');
        if (!container) return;

        if (!activities || activities.length === 0) {
            container.innerHTML = '<div class="p-4 text-center text-muted">Nenhuma atividade recente.</div>';
            return;
        }

        container.innerHTML = activities.map(log => {
            const time = this.timeSince(log.created_at);
            const icon = this.getActivityIcon(log.action);
            const actionText = this.formatAction(log.action);

            return `
                <div class="list-group-item d-flex align-items-center px-4 py-3 border-bottom-0">
                    <div class="icon-box bg-light rounded-circle p-2 me-3 text-primary d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                        <i class="bi ${icon}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-dark">${actionText}</div>
                        <div class="small text-muted">${log.resource || 'System'}</div>
                    </div>
                    <div class="text-muted small">${time}</div>
                </div>
            `;
        }).join('');
    },

    getActivityIcon: function (action) {
        action = action.toLowerCase();
        if (action.includes('price')) return 'bi-tag-fill';
        if (action.includes('stock') || action.includes('inventory')) return 'bi-box-seam-fill';
        if (action.includes('error') || action.includes('fail')) return 'bi-exclamation-triangle-fill';
        if (action.includes('login') || action.includes('auth')) return 'bi-shield-lock-fill';
        if (action.includes('ai') || action.includes('gpt') || action.includes('claude')) return 'bi-robot';
        return 'bi-activity';
    },

    formatAction: function (action) {
        return action.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    },

    timeSince: function (dateStr) {
        if (!dateStr) return 'Never';
        const date = new Date(dateStr);
        const seconds = Math.floor((new Date() - date) / 1000);
        if (seconds < 60) return seconds + "s ago";
        return Math.floor(seconds / 60) + "m ago";
    }
};

const AIWizard = {
    selectedProvider: null,

    selectProvider: function (provider) {
        this.selectedProvider = provider;
        document.querySelectorAll('.selection-card').forEach(el => el.classList.remove('selected'));
        document.querySelector(`.selection-card[data-provider="${provider}"]`).classList.add('selected');
    },

    saveConfig: function () {
        if (!this.selectedProvider) {
            alert('Por favor, selecione um provedor (Anthropic ou OpenAI).');
            return;
        }

        const key = document.getElementById('apiKeyInput').value;
        const model = document.getElementById('modelPreference').value;

        if (!key) {
            alert('Por favor, insira sua chave de API.');
            return;
        }

        // Show loading state
        const btn = document.querySelector('#aiConfigModal .btn-primary');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Salvando...';

        requestJson('/api/ai/config/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                provider: this.selectedProvider,
                key: key,
                model: model
            })
        })
            .then(data => {
                if (data.success) {
                    alert('Configuração salva com sucesso!');
                    const modalEl = document.getElementById('aiConfigModal');
                    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.hide();
                    window.location.reload();
                } else {
                    alert('Erro ao salvar: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Erro de conexão ou erro no servidor.');
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    AICenter.init();

    // Toggle Password Visibility
    const toggleBtn = document.getElementById('toggleKeyVisibility');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const input = document.getElementById('apiKeyInput');
            const icon = toggleBtn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    }

    // Auto-select Anthropic
    AIWizard.selectProvider('anthropic');
});
