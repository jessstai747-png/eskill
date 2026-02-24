<?php

/**
 * Layout Principal
 *
 * Template base que inclui navbar, head comum e estrutura HTML
 * Variáveis esperadas:
 * - $pageTitle: título da página
 * - $content: conteúdo da página (via ob_get_clean())
 */

use App\Services\UserService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cspNonce = $_SESSION['csp_nonce'] ?? '';

$userService = new UserService();
$currentUser = $userService->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Mercado Livre Manager') ?> - ML Manager</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Theme CSS -->
    <link rel="stylesheet" href="/css/theme.css">

    <?php require_once __DIR__ . '/head_common.php'; ?>

    <!-- API Client (loaded early so view scripts can use requestJson) -->
    <script nonce="<?= $cspNonce ?>" src="/js/csrf-helper.js"></script>
    <script nonce="<?= $cspNonce ?>" src="/js/api-client.js?v=<?= @filemtime(__DIR__ . '/../../../public/js/api-client.js') ?: time() ?>"></script>
    <script nonce="<?= $cspNonce ?>">
        async function requestJson(url, options = {}) {
            if (window.ApiClient) return window.ApiClient.request(url, options);
            const resp = await fetch(url, {
                credentials: 'include',
                ...options
            });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            return resp.json();
        }
    </script>
</head>

<body>
    <?php require_once __DIR__ . '/../components/navbar.php'; ?>

    <main class="container-fluid py-4">
        <!-- Breadcrumb Navigation -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb breadcrumb-modern">
                <li class="breadcrumb-item">
                    <a href="/dashboard"><i class="bi bi-house-door"></i></a>
                </li>
                <?php
                if (!isset($breadcrumbs)) {
                    $path_segments = array_filter(explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
                    $current_path = '';
                    foreach ($path_segments as $segment) {
                        if ($segment === 'dashboard') continue; // Already handled by home icon
                        $current_path .= '/' . $segment;
                        $name = str_replace(['-', '_'], ' ', $segment);
                        $name = ucwords($name);

                        // Check if it's the last item
                        if (end($path_segments) === $segment) {
                            echo '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($name) . '</li>';
                        } else {
                            echo '<li class="breadcrumb-item"><a href="/dashboard' . $current_path . '">' . htmlspecialchars($name) . '</a></li>';
                        }
                    }
                }
                ?>
            </ol>
        </nav>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </main>

    <!-- Command Palette (Global) -->
    <?php require_once __DIR__ . '/../components/command-palette.php'; ?>


    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">
                &copy; <?= date('Y') ?> Mercado Livre Manager - Desenvolvido por eSkill
            </span>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script nonce="<?= $cspNonce ?>" src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Common JS -->
    <script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
        // CSRF Token para requisições AJAX
        const csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';

        // Função para requisições autenticadas
        async function fetchAPI(url, options = {}) {
            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                }
            };
            return fetch(url, {
                ...defaultOptions,
                ...options
            });
        }
    </script>

</body>

</html>
