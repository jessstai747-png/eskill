<?php
declare(strict_types=1);

$cspNonce = defined('CSP_NONCE') ? CSP_NONCE : (($GLOBALS['cspNonce'] ?: null) ?? ($_SESSION['csp_nonce'] ?? ''));
$dashboardCssVersion = @filemtime(__DIR__ . '/../../../../public/css/dashboard-modern.css') ?: time();
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <title><?= $pageTitle ?? 'Mercado Livre Manager' ?></title>

    <!-- Fonts -->
    <?php if (getenv('APP_ENV') !== 'testing'): ?>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php endif; ?>

    <!-- Icons -->
    <?php if (getenv('APP_ENV') !== 'testing'): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php else: ?>
        <style>
            .bi::before {
                content: '';
            }
        </style>
    <?php endif; ?>

    <!-- Core CSS -->
    <?php if (getenv('APP_ENV') !== 'testing'): ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php else: ?>
        <style>
            *,
            ::after,
            ::before {
                box-sizing: border-box
            }

            body {
                margin: 0;
                font-family: sans-serif
            }

            .d-flex {
                display: flex
            }

            .align-items-center {
                align-items: center
            }

            .justify-content-center {
                justify-content: center
            }

            .text-center {
                text-align: center
            }

            .w-100 {
                width: 100%
            }

            .mb-4 {
                margin-bottom: 1.5rem
            }

            .mt-2 {
                margin-top: .5rem
            }

            .form-control {
                display: block;
                width: 100%;
                padding: .375rem .75rem;
                border: 1px solid #ced4da;
                border-radius: .25rem
            }

            .btn {
                display: inline-block;
                padding: .375rem .75rem;
                border: 1px solid transparent;
                border-radius: .25rem;
                cursor: pointer
            }

            .btn-primary {
                background: #0d6efd;
                color: #fff;
                border-color: #0d6efd
            }

            .input-group {
                display: flex
            }

            .input-group-text {
                padding: .375rem .75rem;
                border: 1px solid #ced4da;
                background: #e9ecef
            }

            .form-check {
                display: block
            }

            .form-check-input {
                margin-right: .25em
            }

            .alert {
                padding: .75rem 1.25rem;
                border-radius: .25rem
            }

            .alert-success {
                background: #d1e7dd;
                color: #0f5132
            }

            .alert-danger {
                background: #f8d7da;
                color: #842029
            }

            .text-muted {
                color: #6c757d
            }

            .small {
                font-size: .875rem
            }

            .fw-medium {
                font-weight: 500
            }

            .fw-semibold {
                font-weight: 600
            }

            .text-decoration-none {
                text-decoration: none
            }

            .ms-1 {
                margin-left: .25rem
            }

            .pt-2 {
                padding-top: .5rem
            }

            .mb-0 {
                margin-bottom: 0
            }
        </style>
    <?php endif; ?>
    <link href="/css/dashboard-modern.css?v=<?= $dashboardCssVersion ?>" rel="stylesheet">

    <style>
        body {
            background: var(--bg-body);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            background-image: radial-gradient(circle at 10% 20%, rgba(37, 99, 235, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(139, 92, 246, 0.05) 0%, transparent 20%);
        }

        .auth-container {
            width: 100%;
            max-width: 440px;
            padding: 1.5rem;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
        }

        [data-theme="dark"] .auth-card {
            background: rgba(30, 41, 59, 0.8);
            border-color: rgba(255, 255, 255, 0.05);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-logo {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.75rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.3);
        }

        .auth-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .auth-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .form-control {
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
        }

        .input-group-text {
            border-color: var(--border-color);
            background: var(--bg-surface-alt);
        }

        .btn-primary {
            padding: 0.75rem;
            font-weight: 600;
            border-radius: 12px;
            background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.3);
        }

        .footer-link {
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 1.5rem;
        }

        .footer-link a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 600;
        }

        .footer-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <main class="auth-container">
        <div class="auth-card">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?= $content ?? '' ?>
        </div>

        <div class="text-center">
            <small class="text-muted">&copy; <?= date('Y') ?> Mercado Livre Manager. Todos os direitos reservados.</small>
        </div>
    </main>

    <!-- Scripts -->
    <?php if (getenv('APP_ENV') !== 'testing'): ?>
        <script nonce="<?= $cspNonce ?>" src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php endif; ?>
    <?php if (getenv('APP_ENV') !== 'testing'): ?>
        <script nonce="<?= $cspNonce ?>">
            // Auto-dismiss alerts after 5 seconds
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    var alerts = document.querySelectorAll('.alert');
                    alerts.forEach(function(alert) {
                        var bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    });
                }, 5000);
            });
        </script>
    <?php endif; ?>
</body>

</html>
