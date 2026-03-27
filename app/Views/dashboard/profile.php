<?php

declare(strict_types=1);

/**
 * Meu Perfil - Versão Moderna
 * Integrado com o layout moderno (sidebar, temas, etc.)
 */

require_once __DIR__ . '/../../Services/UserService.php';
$userService = new App\Services\UserService();
$currentUser = $userService->getCurrentUser();

if (!$currentUser) {
    header('Location: /login');
    exit;
}
?>

<style>
    .profile-header {
        background: linear-gradient(135deg, var(--primary-color, #667eea) 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        margin-bottom: 2rem;
        border-radius: 12px;
    }

    .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        color: var(--primary-color, #667eea);
        margin: 0 auto 1rem;
    }
</style>

<!-- Profile Header -->
<div class="profile-header">
    <div class="text-center">
        <div class="profile-avatar">
            <i class="bi bi-person-circle"></i>
        </div>
        <h2><?= htmlspecialchars($currentUser['name']) ?></h2>
        <p class="mb-0"><?= htmlspecialchars($currentUser['email']) ?></p>
    </div>
</div>

<!-- Alert Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($_SESSION['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <!-- Informações Pessoais -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-person"></i> Informações Pessoais</h5>
            </div>
            <div class="card-body">
                <form id="profileForm" method="POST" action="/api/user/profile">
                    <input type="hidden" name="_token" value="<?= isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '' ?>">

                    <div class="mb-3">
                        <label for="name" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="name" name="name"
                            value="<?= htmlspecialchars($currentUser['name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email"
                            value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar Alterações
                    </button>
                </form>
            </div>
        </div>

        <!-- Alterar Senha -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-lock"></i> Alterar Senha</h5>
            </div>
            <div class="card-body">
                <form id="passwordForm" method="POST" action="/api/user/change-password">
                    <input type="hidden" name="_token" value="<?= isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '' ?>">

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Senha Atual</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nova Senha</label>
                        <input type="password" class="form-control" id="new_password" name="new_password"
                            minlength="6" required>
                        <small class="form-text text-muted">Mínimo de 6 caracteres</small>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar Nova Senha</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key"></i> Alterar Senha
                    </button>
                </form>
            </div>
        </div>

        <!-- Autenticação de Dois Fatores (2FA) -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Autenticação de Dois Fatores (2FA)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($currentUser['two_factor_enabled'])): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill"></i> A autenticação de dois fatores está ativada.
                    </div>
                    <p>Sua conta está protegida. Para desativar, entre em contato com o suporte (ou implemente a rota de desativação).</p>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i> A autenticação de dois fatores não está ativada.
                    </div>
                    <p>Adicione uma camada extra de segurança à sua conta exigindo um código do seu celular ao fazer login.</p>
                    <a href="/auth/2fa/setup" class="btn btn-success">
                        <i class="bi bi-shield-plus"></i> Configurar 2FA
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Exportar Dados -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-download"></i> Exportar Dados</h5>
            </div>
            <div class="card-body">
                <p>Baixe uma cópia dos seus dados pessoais, logs de atividade e informações de contas vinculadas.</p>
                <div class="d-flex gap-2">
                    <a href="/api/export/user/json" class="btn btn-outline-primary" target="_blank">
                        <i class="bi bi-filetype-json"></i> Exportar JSON
                    </a>
                    <a href="/api/export/user/csv" class="btn btn-outline-success" target="_blank">
                        <i class="bi bi-file-earmark-zip"></i> Exportar CSV (ZIP)
                    </a>
                </div>
            </div>
        </div>

        <!-- Informações da Conta -->
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Informações da Conta</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">ID do Usuário:</dt>
                    <dd class="col-sm-8"><?= $currentUser['id'] ?></dd>

                    <dt class="col-sm-4">E-mail:</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($currentUser['email']) ?></dd>

                    <dt class="col-sm-4">Conta criada em:</dt>
                    <dd class="col-sm-8"><?= date('d/m/Y H:i', strtotime($currentUser['created_at'])) ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
    // Validação de senha
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('As senhas não coincidem!');
            return false;
        }
    });

    // Submit via AJAX - Profile Form
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Perfil atualizado com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + (data.message || 'Erro ao atualizar perfil'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao atualizar perfil');
            });
    });

    // Submit via AJAX - Password Form
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Senha alterada com sucesso!');
                    this.reset();
                } else {
                    alert('Erro: ' + (data.message || 'Erro ao alterar senha'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao alterar senha');
            });
    });
</script>