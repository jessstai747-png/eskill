<?php

declare(strict_types=1);

$pageTitle = 'Login - Dashboard | Mercado Livre Manager';
ob_start();
?>

<div class="auth-header">
    <div class="brand-logo">
        <i class="bi bi-shop"></i>
    </div>
    <h2 class="auth-title">Bem-vindo de volta!</h2>
    <p class="auth-subtitle">Acesse sua conta para continuar</p>
</div>

<form method="POST" action="/auth/login">
    <input type="hidden" name="_token" value="<?= isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '' ?>">

    <div class="mb-4">
        <label for="email" class="form-label text-secondary fw-medium">E-mail</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="email" name="email" placeholder="seu@email.com" required autofocus>
        </div>
    </div>

    <div class="mb-4">
        <label for="password" class="form-label text-secondary fw-medium">Senha</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-2">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label text-secondary small" for="remember">Lembrar-me</label>
            </div>
            <a href="/auth/forgot-password" class="text-decoration-none small text-primary fw-medium"><span>Esqueceu a senha?</span></a>
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 mb-4 shadow-sm">
        <i class="bi bi-box-arrow-in-right me-2"></i> Entrar na Plataforma
    </button>

    <div class="text-center pt-2">
        <p class="mb-0 text-secondary">
            Não tem uma conta?
            <a href="/auth/register" class="text-primary fw-semibold text-decoration-none ms-1"><span>Cadastre-se grátis</span></a>
        </p>
    </div>


</form>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/modern/auth.php';
?>
