<?php

declare(strict_types=1);

/**
 * Clone Notifications Settings View
 * Configuração de webhooks Slack/Discord para notificações de clonagem
 */

$pageTitle = 'Notificações de Clonagem';
$pageDescription = 'Configure webhooks Slack e Discord para receber alertas';

// Include layout
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-bell text-primary me-2"></i>
                        Notificações de Clonagem
                    </h1>
                    <p class="text-muted mb-0">
                        Configure webhooks para receber alertas no Slack ou Discord
                    </p>
                </div>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWebhookModal">
                        <i class="fas fa-plus me-1"></i> Adicionar Webhook
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Webhooks Configurados -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-link text-muted me-2"></i>
                        Webhooks Configurados
                    </h5>
                </div>
                <div class="card-body">
                    <div id="webhooks-list" class="table-responsive">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Eventos Disponíveis -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-alt text-muted me-2"></i>
                        Eventos Disponíveis
                    </h5>
                </div>
                <div class="card-body">
                    <div id="events-list">
                        <div class="text-center py-3">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle text-muted me-2"></i>
                        Como Configurar
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6><i class="fab fa-slack text-primary me-2"></i>Slack</h6>
                        <ol class="small text-muted">
                            <li>Acesse <strong>Settings → Integrations → Apps</strong> no Slack</li>
                            <li>Busque por <strong>Incoming Webhooks</strong></li>
                            <li>Clique em <strong>Add to Slack</strong></li>
                            <li>Selecione o canal e copie a URL do webhook</li>
                            <li>Cole a URL aqui e configure os eventos</li>
                        </ol>
                    </div>
                    <div>
                        <h6><i class="fab fa-discord text-indigo me-2"></i>Discord</h6>
                        <ol class="small text-muted">
                            <li>Abra as <strong>Configurações do Canal</strong> no Discord</li>
                            <li>Vá em <strong>Integrações → Webhooks</strong></li>
                            <li>Clique em <strong>Novo Webhook</strong></li>
                            <li>Configure o nome e avatar, depois copie a URL</li>
                            <li>Cole a URL aqui e configure os eventos</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Histórico de Notificações -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history text-muted me-2"></i>
                        Histórico Recente
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="CloneNotifications.loadHistory()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div id="history-list" class="table-responsive">
                        <div class="text-center py-3">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Adicionar Webhook -->
<div class="modal fade" id="addWebhookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    Adicionar Webhook
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="webhook-form">
                    <!-- Tipo -->
                    <div class="mb-3">
                        <label class="form-label">Tipo de Webhook</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="webhook_type" id="type-slack" value="slack" checked>
                            <label class="btn btn-outline-primary" for="type-slack">
                                <i class="fab fa-slack me-1"></i> Slack
                            </label>
                            <input type="radio" class="btn-check" name="webhook_type" id="type-discord" value="discord">
                            <label class="btn btn-outline-primary" for="type-discord">
                                <i class="fab fa-discord me-1"></i> Discord
                            </label>
                        </div>
                    </div>

                    <!-- URL -->
                    <div class="mb-3">
                        <label for="webhook-url" class="form-label">URL do Webhook</label>
                        <input type="url" class="form-control" id="webhook-url" required
                               placeholder="https://hooks.slack.com/services/...">
                        <div class="form-text" id="url-hint">
                            Cole a URL do Incoming Webhook do Slack
                        </div>
                    </div>

                    <!-- Username -->
                    <div class="mb-3">
                        <label for="webhook-username" class="form-label">Nome do Bot (opcional)</label>
                        <input type="text" class="form-control" id="webhook-username" 
                               value="Clone Bot" placeholder="Clone Bot">
                    </div>

                    <!-- Severidade Mínima -->
                    <div class="mb-3">
                        <label class="form-label">Severidade Mínima</label>
                        <select class="form-select" id="webhook-severity">
                            <option value="info">Informação (todos os alertas)</option>
                            <option value="warning">Aviso (avisos e erros)</option>
                            <option value="error">Erro (apenas erros)</option>
                            <option value="critical">Crítico (apenas alertas críticos)</option>
                        </select>
                    </div>

                    <!-- Eventos -->
                    <div class="mb-3">
                        <label class="form-label">Eventos</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="event-all" checked>
                            <label class="form-check-label" for="event-all">
                                <strong>Todos os eventos</strong>
                            </label>
                        </div>
                        <hr class="my-2">
                        <div id="events-checkboxes" class="row">
                            <!-- Preenchido via JS -->
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="CloneNotifications.saveWebhook()">
                    <i class="fas fa-save me-1"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">

const CloneNotifications = {
    events: [],
    severities: [],

    init() {
        this.loadWebhooks();
        this.loadEvents();
        this.loadHistory();
        this.bindEvents();
    },

    bindEvents() {
        // Mudar hint da URL baseado no tipo
        document.querySelectorAll('input[name="webhook_type"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                const hint = document.getElementById('url-hint');
                const urlInput = document.getElementById('webhook-url');
                if (e.target.value === 'slack') {
                    hint.textContent = 'Cole a URL do Incoming Webhook do Slack';
                    urlInput.placeholder = 'https://hooks.slack.com/services/...';
                } else {
                    hint.textContent = 'Cole a URL do Webhook do Discord';
                    urlInput.placeholder = 'https://discord.com/api/webhooks/...';
                }
            });
        });

        // Toggle eventos individuais
        document.getElementById('event-all').addEventListener('change', (e) => {
            document.querySelectorAll('#events-checkboxes input').forEach(cb => {
                cb.disabled = e.target.checked;
                if (e.target.checked) cb.checked = false;
            });
        });
    },

    async loadWebhooks() {
        try {
            const data = await requestJson('/api/clone/notifications/webhooks');

            const container = document.getElementById('webhooks-list');
            
            if (!data.webhooks || data.webhooks.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-bell-slash fa-3x mb-3"></i>
                        <p class="mb-0">Nenhum webhook configurado</p>
                        <small>Clique em "Adicionar Webhook" para começar</small>
                    </div>
                `;
                return;
            }

            let html = `
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>URL</th>
                            <th>Eventos</th>
                            <th>Severidade</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.webhooks.forEach(webhook => {
                const typeIcon = webhook.type === 'slack' 
                    ? '<i class="fab fa-slack text-primary"></i>' 
                    : '<i class="fab fa-discord text-indigo"></i>';
                
                const statusBadge = webhook.status === 'active'
                    ? '<span class="badge bg-success">Ativo</span>'
                    : '<span class="badge bg-secondary">Inativo</span>';

                const maskedUrl = webhook.url.substring(0, 40) + '...';
                const events = webhook.events || '["*"]';
                const eventsList = JSON.parse(events);
                const eventsLabel = eventsList.includes('*') ? 'Todos' : eventsList.length + ' eventos';

                html += `
                    <tr>
                        <td>${typeIcon} ${webhook.type}</td>
                        <td><code class="small">${maskedUrl}</code></td>
                        <td>${eventsLabel}</td>
                        <td><span class="badge bg-light text-dark">${webhook.min_severity || 'info'}</span></td>
                        <td>${statusBadge}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="CloneNotifications.testWebhook(${webhook.id})" title="Testar">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                            ${webhook.status === 'active' 
                                ? `<button class="btn btn-sm btn-outline-warning me-1" onclick="CloneNotifications.disableWebhook(${webhook.id})" title="Desativar"><i class="fas fa-pause"></i></button>`
                                : `<button class="btn btn-sm btn-outline-success me-1" onclick="CloneNotifications.enableWebhook(${webhook.id})" title="Ativar"><i class="fas fa-play"></i></button>`
                            }
                            <button class="btn btn-sm btn-outline-danger" onclick="CloneNotifications.deleteWebhook(${webhook.id})" title="Remover">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;

        } catch (e) {
            console.error('Erro ao carregar webhooks:', e);
            document.getElementById('webhooks-list').innerHTML = `
                <div class="alert alert-danger">Erro ao carregar webhooks</div>
            `;
        }
    },

    async loadEvents() {
        try {
            const data = await requestJson('/api/clone/notifications/events');

            this.events = data.events || [];
            this.severities = data.severities || [];

            // Lista de eventos
            const container = document.getElementById('events-list');
            let html = '<ul class="list-unstyled mb-0">';
            this.events.forEach(event => {
                html += `
                    <li class="mb-2">
                        <strong>${event.name}</strong><br>
                        <small class="text-muted">${event.description}</small>
                    </li>
                `;
            });
            html += '</ul>';
            container.innerHTML = html;

            // Checkboxes no modal
            const checkboxes = document.getElementById('events-checkboxes');
            let cbHtml = '';
            this.events.forEach((event, idx) => {
                cbHtml += `
                    <div class="col-6 col-md-4 mb-2">
                        <div class="form-check">
                            <input class="form-check-input event-checkbox" type="checkbox" id="event-${idx}" value="${event.id}" disabled>
                            <label class="form-check-label small" for="event-${idx}">${event.name}</label>
                        </div>
                    </div>
                `;
            });
            checkboxes.innerHTML = cbHtml;

        } catch (e) {
            console.error('Erro ao carregar eventos:', e);
        }
    },

    async loadHistory() {
        try {
            const data = await requestJson('/api/clone/notifications/history?limit=50');

            const container = document.getElementById('history-list');

            if (!data.history || data.history.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-3 text-muted">
                        <small>Nenhuma notificação enviada ainda</small>
                    </div>
                `;
                return;
            }

            let html = `
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Evento</th>
                            <th>Tipo</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.history.slice(0, 20).forEach(log => {
                const statusIcon = log.success 
                    ? '<i class="fas fa-check-circle text-success"></i>'
                    : '<i class="fas fa-times-circle text-danger"></i>';
                
                const typeIcon = log.webhook_type === 'slack'
                    ? '<i class="fab fa-slack text-primary"></i>'
                    : '<i class="fab fa-discord text-indigo"></i>';

                html += `
                    <tr>
                        <td class="small">${log.created_at}</td>
                        <td><span class="badge bg-light text-dark">${log.event}</span></td>
                        <td>${typeIcon}</td>
                        <td>${statusIcon} ${log.error ? `<small class="text-danger">${log.error}</small>` : ''}</td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;

        } catch (e) {
            console.error('Erro ao carregar histórico:', e);
        }
    },

    async saveWebhook() {
        const type = document.querySelector('input[name="webhook_type"]:checked').value;
        const url = document.getElementById('webhook-url').value.trim();
        const username = document.getElementById('webhook-username').value.trim();
        const severity = document.getElementById('webhook-severity').value;
        const allEvents = document.getElementById('event-all').checked;
        
        let events = ['*'];
        if (!allEvents) {
            events = Array.from(document.querySelectorAll('.event-checkbox:checked')).map(cb => cb.value);
            if (events.length === 0) {
                alert('Selecione pelo menos um evento');
                return;
            }
        }

        if (!url) {
            alert('URL do webhook é obrigatória');
            return;
        }

        try {
            const data = await requestJson(`/api/clone/notifications/${type}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    webhook_url: url,
                    username: username || 'Clone Bot',
                    min_severity: severity,
                    events: events
                })
            });

            if (data.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('addWebhookModal')).hide();
                document.getElementById('webhook-form').reset();
                this.loadWebhooks();
                this.showToast('Webhook configurado com sucesso!', 'success');
            } else {
                alert(data.error || 'Erro ao salvar webhook');
            }
        } catch (e) {
            console.error('Erro ao salvar webhook:', e);
            alert('Erro ao salvar webhook');
        }
    },

    async testWebhook(id) {
        try {
            const data = await requestJson(`/api/clone/notifications/webhook/${id}/test`, { method: 'POST' });

            if (data.status === 'success') {
                this.showToast('Teste enviado! Verifique seu canal.', 'success');
            } else {
                this.showToast('Falha ao enviar teste: ' + (data.error || 'Erro desconhecido'), 'danger');
            }
        } catch (e) {
            console.error('Erro ao testar webhook:', e);
            this.showToast('Erro ao testar webhook', 'danger');
        }
    },

    async enableWebhook(id) {
        try {
            await requestJson(`/api/clone/notifications/webhook/${id}/enable`, { method: 'PUT' });
            this.loadWebhooks();
            this.showToast('Webhook ativado', 'success');
        } catch (e) {
            console.error('Erro:', e);
        }
    },

    async disableWebhook(id) {
        try {
            await requestJson(`/api/clone/notifications/webhook/${id}/disable`, { method: 'PUT' });
            this.loadWebhooks();
            this.showToast('Webhook desativado', 'warning');
        } catch (e) {
            console.error('Erro:', e);
        }
    },

    async deleteWebhook(id) {
        if (!confirm('Tem certeza que deseja remover este webhook?')) return;

        try {
            await requestJson(`/api/clone/notifications/webhook/${id}`, { method: 'DELETE' });
            this.loadWebhooks();
            this.showToast('Webhook removido', 'info');
        } catch (e) {
            console.error('Erro:', e);
        }
    },

    showToast(message, type = 'info') {
        // Toast simples via alert (pode ser melhorado com Bootstrap Toast)
        const colors = {
            success: '#198754',
            danger: '#dc3545',
            warning: '#ffc107',
            info: '#0dcaf0'
        };
        
        const toast = document.createElement('div');
        toast.className = 'position-fixed bottom-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast show" role="alert">
                <div class="toast-header" style="background-color: ${colors[type]}; color: white;">
                    <strong class="me-auto">Notificação</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">${message}</div>
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => toast.remove(), 5000);
    }
};

document.addEventListener('DOMContentLoaded', () => CloneNotifications.init());
</script>

<style>
.text-indigo { color: #5865F2 !important; }
</style>