<?php

declare(strict_types=1);

$pageTitle = 'Recuperar Senha - Mercado Livre Manager';
ob_start();
?>

<div class="auth-header">
    <div class="brand-logo">
        <i class="bi bi-key"></i>
    </div>
    <h2 class="auth-title">Recuperar Senha</h2>
    <p class="auth-subtitle">Informe seu e-mail para receber as instruções</p>
</div>

<form method="POST" action="/auth/forgot-password">
    <input type="hidden" name="_token" value="<?= isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '' ?>">
    
    <div class="mb-4">
        <label for="email" class="form-label text-secondary fw-medium">E-mail</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="email" name="email" placeholder="seu@email.com" required autofocus>
        </div>
        <div class="form-text mt-2 text-muted small">
            Enviaremos um link para você redefinir sua senha.
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 mb-4 shadow-sm">
        <i class="bi bi-send me-2"></i> Enviar Link de Recuperação
    </button>
    
    <div class="text-center pt-2">
        <a href="/auth/login" class="text-decoration-none text-secondary fw-medium">
            <i class="bi bi-arrow-left me-1"></i> Voltar para Login
        </a>
    </div>
</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/modern/auth.php';
?>
