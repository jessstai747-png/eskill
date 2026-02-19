<?php
$pageTitle = 'Redefinir Senha - Mercado Livre Manager';
ob_start();

$token = $_GET['token'] ?? '';
?>

<div class="auth-header">
    <div class="brand-logo">
        <i class="bi bi-shield-lock"></i>
    </div>
    <h2 class="auth-title">Redefinir Senha</h2>
    <p class="auth-subtitle">Crie uma nova senha segura para sua conta</p>
</div>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (empty($token)): ?>
    <div class="alert alert-warning">
        Token não fornecido ou inválido.
    </div>
    <a href="/auth/login" class="btn btn-primary w-100">Voltar para Login</a>
<?php else: ?>
    <form method="POST" action="/auth/reset-password" id="resetForm">
        <input type="hidden" name="_token" value="<?= isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '' ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        
        <div class="mb-4">
            <label for="password" class="form-label text-secondary fw-medium">Nova Senha</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password" placeholder="Mínimo 6 caracteres" required minlength="6" autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label for="password_confirm" class="form-label text-secondary fw-medium">Confirmar Nova Senha</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Confirme a nova senha" required minlength="6">
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 mb-4 shadow-sm">
            <i class="bi bi-check-lg me-2"></i> Alterar Senha
        </button>
    </form>
<?php endif; ?>

<div class="text-center">
    <p class="mb-0">
        <a href="/auth/login">Voltar para login</a>
    </p>
</div>

<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
    document.getElementById('resetForm')?.addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const passwordConfirm = document.getElementById('password_confirm').value;
        
        if (password !== passwordConfirm) {
            e.preventDefault();
            alert('As senhas não coincidem!');
            return false;
        }
    });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/modern/auth.php';
?>
