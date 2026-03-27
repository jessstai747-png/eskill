<?php

declare(strict_types=1);

$pageTitle = 'Verificação em Duas Etapas - Mercado Livre Manager';
ob_start();
?>

<div class="auth-header">
    <div class="brand-logo">
        <i class="bi bi-shield-check"></i>
    </div>
    <h2 class="auth-title">Autenticação 2FA</h2>
    <p class="auth-subtitle">Digite o código do seu aplicativo autenticador</p>
</div>

<form method="POST" action="/auth/2fa/verify">
    <input type="hidden" name="_token" value="<?= isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '' ?>">
    
    <div class="mb-4">
        <label for="code" class="form-label text-secondary fw-medium">Código de Verificação</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-qr-code"></i></span>
            <input type="text" class="form-control form-control-lg text-center letter-spacing-2" id="code" name="code" placeholder="000 000" required autofocus maxlength="6" pattern="[0-9]*" inputmode="numeric" autocomplete="one-time-code">
        </div>
        <div class="form-text mt-2 text-muted small text-center">
            Abra o Google Authenticator ou Authy para ver o código.
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 mb-4 shadow-sm">
        <i class="bi bi-shield-lock-fill me-2"></i> Verificar
    </button>
    
    <div class="text-center pt-2">
        <a href="/auth/login" class="text-decoration-none text-secondary fw-medium">
            <i class="bi bi-arrow-left me-1"></i> Voltar para Login
        </a>
    </div>
</form>

<style>
    .letter-spacing-2 {
        letter-spacing: 0.5em;
        font-weight: 600;
    }
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/modern/auth.php';
?>