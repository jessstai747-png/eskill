<!-- Meta tags essenciais -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= \App\Helpers\SecurityHelper::csrfToken() ?>">

<!-- Favicon e Ícones -->
<link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.svg">
<link rel="manifest" href="/manifest.json">

<!-- CSRF Helper - Adiciona automaticamente tokens em todas as requisições AJAX -->
<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>" src="/js/csrf-helper.js"></script>

<!-- Real-Time Notifications with Audio -->
<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>" src="/js/realtime-notifications.js" defer></script>
<script nonce="<?= $cspNonce ?? $_SESSION['csp_nonce'] ?? '' ?>">
    // Habilitar notificações em tempo real globalmente
    window.enableRealTimeNotifications = true;
</script>