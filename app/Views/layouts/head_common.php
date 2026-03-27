<!-- Meta tags essenciais -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= \App\Helpers\SecurityHelper::csrfToken() ?>">

<!-- Favicon e Ícones -->
<link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.svg">
<link rel="manifest" href="/manifest.json">

<!-- CSRF Helper - Adiciona automaticamente tokens em todas as requisições AJAX -->
<script nonce="<?= CSP_NONCE ?>" src="/js/csrf-helper.js"></script>

<!-- Mercado Livre Integration Preflight Helper -->
<script nonce="<?= CSP_NONCE ?>" src="/js/ml-integration-preflight.js" defer></script>

<!-- Real-Time Notifications with Audio -->
<script nonce="<?= CSP_NONCE ?>" src="/js/realtime-notifications.js" defer></script>
<script nonce="<?= CSP_NONCE ?>">
    // Habilitar notificações em tempo real globalmente
    window.enableRealTimeNotifications = true;
</script>