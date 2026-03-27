<?php

declare(strict_types=1);

$title = 'Smart Notifications';
$subtitle = 'Gerencie seus canais de notificação';
include __DIR__ . '/../../layouts/modern/partials/page-header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Preferências de Canais</h5>
            </div>
            <div class="card-body">
                <form id="notification-settings-form">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 40%;">Evento</th>
                                    <th class="text-center" style="width: 20%;">E-mail</th>
                                    <th class="text-center" style="width: 20%;">WhatsApp</th>
                                    <th class="text-center" style="width: 20%;">In-App (Som)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <strong>Novos Pedidos</strong>
                                        <div class="text-muted small">Quando um novo pedido é recebido</div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input" type="checkbox" name="email_orders" id="email_orders">
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input" type="checkbox" name="whatsapp_orders" id="whatsapp_orders">
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Novas Perguntas</strong>
                                        <div class="text-muted small">Dúvidas de clientes nos anúncios</div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input" type="checkbox" name="email_questions" id="email_questions">
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input" type="checkbox" name="whatsapp_questions" id="whatsapp_questions">
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Estoque Baixo</strong>
                                        <div class="text-muted small">Alertas de reposição</div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input" type="checkbox" disabled checked>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input" type="checkbox" name="whatsapp_low_stock" id="whatsapp_low_stock">
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-3">Configurações Gerais</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Som das Notificações</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="sound_enabled" id="sound_enabled">
                                <label class="form-check-label" for="sound_enabled">Ativar sons In-App</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notificações Desktop</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="desktop_enabled" id="desktop_enabled">
                                <label class="form-check-label" for="desktop_enabled">Permitir pop-ups do navegador</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar Preferências
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm bg-primary text-white mb-4">
            <div class="card-body">
                <h5><i class="bi bi-whatsapp"></i> WhatsApp Status</h5>
                <p>Para receber notificações no WhatsApp, certifique-se de que sua instância está conectada.</p>
                <a href="/dashboard/whatsapp" class="btn btn-outline-light btn-sm">Verificar Conexão</a>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
    const settingsManager = {
        init: function() {
            this.loadSettings();
            document.getElementById('notification-settings-form').addEventListener('submit', (e) => this.saveSettings(e));
        },

        loadSettings: async function() {
            try {
                const data = await requestJson('/api/notifications/settings');

                if (data.success) {
                    const s = data.settings;
                    // Populate fields
                    const fields = ['email_orders', 'whatsapp_orders', 'email_questions', 'whatsapp_questions', 'whatsapp_low_stock', 'sound_enabled', 'desktop_enabled'];
                    fields.forEach(f => {
                        const el = document.getElementById(f);
                        if (el) el.checked = !!s[f];
                    });
                }
            } catch (e) {
                console.error(e);
            }
        },

        saveSettings: async function(e) {
            e.preventDefault();
            const form = e.target;
            const data = {};

            // Collect all checkbox values
            const fields = ['email_orders', 'whatsapp_orders', 'email_questions', 'whatsapp_questions', 'whatsapp_low_stock', 'sound_enabled', 'desktop_enabled'];
            fields.forEach(f => {
                const el = document.getElementById(f);
                if (el) data[f] = el.checked;
            });

            try {
                const result = await requestJson('/api/notifications/settings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                if (result.success) {
                    Toast.success('Configurações salvas!');
                } else {
                    Toast.error('Erro ao salvar.');
                }
            } catch (err) {
                Toast.error('Erro de conexão.');
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => settingsManager.init());
</script>
