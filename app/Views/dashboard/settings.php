<?php
/**
 * Configurações - Versão Moderna
 * Integrado com o layout moderno (sidebar, temas, etc.)
 */

// Verificação de autenticação
require_once __DIR__ . '/../../Services/UserService.php';
$userService = new App\Services\UserService();
if (!$userService->isAuthenticated()) {
    header('Location: /login');
    exit;
}
?>

<!-- Page Header -->
<?php
$title = 'Configurações';
$subtitle = 'Gerencie suas preferências e configurações do sistema';
include __DIR__ . '/../layouts/modern/partials/page-header.php';
?>

<div class="row">
    <div class="col-md-8">
        <!-- Notificações -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-bell"></i> Notificações</h5>
            </div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="notifyNewOrders" checked>
                    <label class="form-check-label" for="notifyNewOrders">
                        Notificar sobre novos pedidos
                    </label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="notifyTokenExpiring" checked>
                    <label class="form-check-label" for="notifyTokenExpiring">
                        Alertar quando token estiver expirando
                    </label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="notifyPriceChanges">
                    <label class="form-check-label" for="notifyPriceChanges">
                        Notificar sobre mudanças de preço
                    </label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="notifyNewCompetitors">
                    <label class="form-check-label" for="notifyNewCompetitors">
                        Alertar sobre novos concorrentes
                    </label>
                </div>
                <button class="btn btn-primary mt-3" onclick="saveNotifications()">
                    <i class="bi bi-save"></i> Salvar Preferências
                </button>
            </div>
        </div>

        <!-- Integração Telegram -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-telegram"></i> Integração Telegram</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Configure o Telegram para receber notificações importantes.
                </div>
                <div class="mb-3">
                    <label for="telegramBotToken" class="form-label">Bot Token</label>
                    <input type="text" class="form-control" id="telegramBotToken"
                        placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
                    <small class="form-text text-muted">Obtenha em @BotFather no Telegram</small>
                </div>
                <div class="mb-3">
                    <label for="telegramChatId" class="form-label">Chat ID</label>
                    <input type="text" class="form-control" id="telegramChatId"
                        placeholder="123456789">
                    <small class="form-text text-muted">Seu ID de chat no Telegram</small>
                </div>
                <button class="btn btn-primary" onclick="saveTelegram()">
                    <i class="bi bi-save"></i> Salvar Configuração
                </button>
            </div>
        </div>

        <!-- Sincronização -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-arrow-repeat"></i> Sincronização Automática</h5>
            </div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="autoSyncOrders" checked>
                    <label class="form-check-label" for="autoSyncOrders">
                        Sincronizar pedidos automaticamente
                    </label>
                </div>
                <div class="mb-3">
                    <label for="syncInterval" class="form-label">Intervalo de Sincronização (minutos)</label>
                    <select class="form-select" id="syncInterval">
                        <option value="15">15 minutos</option>
                        <option value="30" selected>30 minutos</option>
                        <option value="60">1 hora</option>
                        <option value="120">2 horas</option>
                    </select>
                </div>
                <button class="btn btn-primary" onclick="saveSyncSettings()">
                    <i class="bi bi-save"></i> Salvar Configuração
                </button>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Ações Rápidas -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-lightning"></i> Ações Rápidas</h5>
            </div>
            <div class="card-body">
                <button class="btn btn-outline-primary w-100 mb-2" onclick="syncNow()">
                    <i class="bi bi-arrow-clockwise"></i> Sincronizar Agora
                </button>
                <button class="btn btn-outline-success w-100 mb-2" onclick="exportData()">
                    <i class="bi bi-download"></i> Exportar Dados
                </button>
                <button class="btn btn-outline-info w-100 mb-2" onclick="viewLogs()">
                    <i class="bi bi-file-text"></i> Ver Logs
                </button>
                <button class="btn btn-outline-warning w-100" onclick="clearCache()">
                    <i class="bi bi-trash"></i> Limpar Cache
                </button>
            </div>
        </div>

        <!-- Informações do Sistema -->
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Sistema</h5>
            </div>
            <div class="card-body">
                <small class="text-muted">
                    <strong>Versão:</strong> 1.0.0<br>
                    <strong>PHP:</strong> <?= phpversion() ?><br>
                    <strong>Última atualização:</strong> <?= date('d/m/Y H:i') ?>
                </small>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

    function saveNotifications() {
        const settings = {
            newOrders: document.getElementById('notifyNewOrders').checked,
            tokenExpiring: document.getElementById('notifyTokenExpiring').checked,
            priceChanges: document.getElementById('notifyPriceChanges').checked,
            newCompetitors: document.getElementById('notifyNewCompetitors').checked
        };

        // Salvar via API
        requestJson('/api/settings/notifications', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(settings)
            })
            .then(data => {
                if (data.success) {
                    alert('Preferências salvas com sucesso!');
                } else {
                    alert('Erro ao salvar preferências');
                }
            });
    }

    function saveTelegram() {
        const token = document.getElementById('telegramBotToken').value;
        const chatId = document.getElementById('telegramChatId').value;

        requestJson('/api/settings/telegram', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    token,
                    chatId
                })
            })
            .then(data => {
                if (data.success) {
                    alert('Configuração do Telegram salva!');
                } else {
                    alert('Erro ao salvar configuração');
                }
            });
    }

    function saveSyncSettings() {
        const autoSync = document.getElementById('autoSyncOrders').checked;
        const interval = document.getElementById('syncInterval').value;

        requestJson('/api/settings/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    autoSync,
                    interval
                })
            })
            .then(data => {
                if (data.success) {
                    alert('Configurações de sincronização salvas!');
                }
            });
    }

    function syncNow() {
        if (confirm('Deseja sincronizar agora?')) {
            requestJson('/api/polling/all', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                })
                .then(data => {
                    alert('Sincronização iniciada!');
                });
        }
    }

    function exportData() {
        window.open('/api/export/analysis/json', '_blank');
    }

    function viewLogs() {
        alert('Funcionalidade de logs em desenvolvimento');
    }

    function clearCache() {
        if (confirm('Deseja limpar o cache?')) {
            requestJson('/api/cache/clear', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                })
                .then(data => {
                    alert('Cache limpo com sucesso!');
                });
        }
    }
</script>