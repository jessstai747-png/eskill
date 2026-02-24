<?php
/**
 * WhatsApp Integration - Modern Layout
 */
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-whatsapp text-success"></i> Integração WhatsApp</h2>
        <p class="text-muted">Gerencie a conexão e configurações do WhatsApp</p>
    </div>
</div>

<?php if (isset($_SESSION['flash_message'])): ?>
    <div class="alert alert-<?= $_SESSION['flash_type'] ?> alert-dismissible fade show">
        <?= $_SESSION['flash_message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
<?php endif; ?>

<div class="row">
    <!-- Configurações -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-gear"></i> Configurações</h5>
            </div>
            <div class="card-body">
                <form action="/dashboard/whatsapp/save" method="POST">
                    <div class="mb-3">
                        <label class="form-label">Provedor</label>
                        <select name="provider" class="form-select" id="providerSelect" onchange="toggleFields()">
                            <option value="simulator" <?= ($settings['provider'] ?? '') === 'simulator' ? 'selected' : '' ?>>Simulador (Log apenas)</option>
                            <option value="twilio" <?= ($settings['provider'] ?? '') === 'twilio' ? 'selected' : '' ?>>Twilio</option>
                            <option value="wppconnect" <?= ($settings['provider'] ?? '') === 'wppconnect' ? 'selected' : '' ?>>WppConnect / Genérico</option>
                        </select>
                    </div>

                    <div id="twilioFields" class="provider-fields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Account SID</label>
                            <input type="text" name="api_key" class="form-control" value="<?= htmlspecialchars($settings['api_key'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Auth Token</label>
                            <input type="password" name="api_secret" class="form-control" value="<?= htmlspecialchars($settings['api_secret'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">From Number (Twilio)</label>
                            <input type="text" name="from_number" class="form-control" value="<?= htmlspecialchars($settings['from_number'] ?? '') ?>" placeholder="+1234567890">
                        </div>
                    </div>

                    <div id="wppFields" class="provider-fields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">API URL</label>
                            <input type="url" name="api_url" class="form-control" value="<?= htmlspecialchars($settings['api_url'] ?? '') ?>" placeholder="http://localhost:21465">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Session / Key</label>
                            <input type="text" name="api_key" class="form-control" value="<?= htmlspecialchars($settings['api_key'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Token (Bearer)</label>
                            <input type="password" name="api_secret" class="form-control" value="<?= htmlspecialchars($settings['api_secret'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= ($settings['is_active'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Ativar Integração</label>
                    </div>

                    <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Teste -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-send"></i> Testar Envio</h5>
            </div>
            <div class="card-body">
                <form id="testForm">
                    <div class="mb-3">
                        <label class="form-label">Número de Destino</label>
                        <input type="text" id="testPhone" class="form-control" placeholder="5511999999999" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        <div class="form-text">Inclua o código do país (ex: 55 para Brasil)</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mensagem</label>
                        <textarea id="testMessage" class="form-control" rows="3">Teste de integração WhatsApp - Mercado Livre Manager</textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Enviar Teste</button>
                </form>
                <div id="testResult" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<!-- Logs -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0"><i class="bi bi-clock-history"></i> Histórico de Envios</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Destino</th>
                        <th>Mensagem</th>
                        <th>Status</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                            <td><?= htmlspecialchars($log['to_number']) ?></td>
                            <td><?= htmlspecialchars(substr($log['message'], 0, 50)) ?>...</td>
                            <td>
                                <?php
                                $badgeClass = match ($log['status']) {
                                    'sent', 'delivered' => 'success',
                                    'failed' => 'danger',
                                    'pending' => 'warning',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?= $badgeClass ?>"><?= $log['status'] ?></span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary" onclick='showDetails(<?= json_encode($log['provider_response']) ?>)'>
                                    <i class="bi bi-info-circle"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">Nenhum registro encontrado</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">

    function toggleFields() {
        const provider = document.getElementById('providerSelect').value;
        document.querySelectorAll('.provider-fields').forEach(el => el.style.display = 'none');

        if (provider === 'twilio') {
            document.getElementById('twilioFields').style.display = 'block';
        } else if (provider === 'wppconnect') {
            document.getElementById('wppFields').style.display = 'block';
        }
    }

    // Init
    toggleFields();

    document.getElementById('testForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button');
        const resultDiv = document.getElementById('testResult');

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
        resultDiv.innerHTML = '';

        try {
            const formData = new FormData();
            formData.append('phone', document.getElementById('testPhone').value);
            formData.append('message', document.getElementById('testMessage').value);

            const data = await requestJson('/dashboard/whatsapp/test', {
                method: 'POST',
                body: formData
            });

            if (data.success) {
                resultDiv.innerHTML = '<div class="alert alert-success">Mensagem enviada com sucesso! ID: ' + (data.id || 'OK') + '</div>';
                setTimeout(() => location.reload(), 2000);
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger">Erro: ' + (data.error || 'Falha desconhecida') + '</div>';
            }
        } catch (error) {
            resultDiv.innerHTML = '<div class="alert alert-danger">Erro de conexão</div>';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Enviar Teste';
        }
    });

    function showDetails(json) {
        alert(JSON.stringify(JSON.parse(json || '{}'), null, 2));
    }
</script>