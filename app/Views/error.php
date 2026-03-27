<?php

declare(strict_types=1);

/**
 * Error Page - Fallback para erros
 */

$errorTitle = $error ?? 'Erro Desconhecido';
$errorMessage = isset($error) ? $error : 'Ocorreu um erro inesperado.';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg border-0">
                <div class="card-body text-center p-5">
                    <div class="mb-4">
                        <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 4rem;"></i>
                    </div>
                    <h2 class="text-danger mb-3">Ops! Algo deu errado</h2>
                    <p class="text-muted mb-4"><?= htmlspecialchars($errorMessage) ?></p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="javascript:history.back()" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Voltar
                        </a>
                        <a href="/dashboard" class="btn btn-primary">
                            <i class="bi bi-house me-2"></i>Dashboard
                        </a>
                    </div>
                </div>
            </div>
            
            <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
            <div class="card mt-4 border-warning">
                <div class="card-header bg-warning text-dark">
                    <i class="bi bi-bug me-2"></i>Debug Info
                </div>
                <div class="card-body">
                    <pre class="mb-0"><code><?= htmlspecialchars($error) ?></code></pre>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
