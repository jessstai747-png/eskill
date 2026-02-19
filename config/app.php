<?php

$appUrl = $_ENV['APP_URL'] ?? null;

if (!$appUrl && !empty($_SERVER['HTTP_HOST'])) {
    $isHttpsRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $isHttpsRequest ? 'https' : 'http';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    $basePath = ($basePath === '.' ? '' : $basePath);

    $appUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . ($basePath ? $basePath : '');
}

return [
    'name' => 'Mercado Livre Manager',
    'version' => '1.0.0',
    'url' => $appUrl ?: 'http://localhost/eskill/public',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'key' => $_ENV['APP_KEY'] ?? '',
    
    'mercadolivre' => [
        'app_id' => $_ENV['ML_APP_ID'] ?? getenv('ML_APP_ID') ?? $_ENV['ML_CLIENT_ID'] ?? getenv('ML_CLIENT_ID') ?? '',
        'client_secret' => $_ENV['ML_CLIENT_SECRET'] ?? getenv('ML_CLIENT_SECRET') ?? '',
        'redirect_uri' => $_ENV['ML_REDIRECT_URI'] ?? getenv('ML_REDIRECT_URI') ?? '',
        'auth_url' => 'https://auth.mercadolivre.com.br/authorization',
        'token_url' => 'https://api.mercadolibre.com/oauth/token',
        'api_url' => 'https://api.mercadolibre.com',
        'site_id' => 'MLB', // Brasil
    ],
    
    'cache' => [
        'driver' => $_ENV['CACHE_DRIVER'] ?? 'file',
        'ttl' => (int)($_ENV['CACHE_TTL'] ?? 3600),
    ],
    
    'log' => [
        'level' => $_ENV['LOG_LEVEL'] ?? 'warning',
        'file' => $_ENV['LOG_FILE'] ?? 'storage/logs/app.log',
    ],
    
    'email' => [
        'enabled' => filter_var($_ENV['EMAIL_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'from' => $_ENV['EMAIL_FROM'] ?? 'noreply@eskill.com.br',
        'reply_to' => $_ENV['EMAIL_REPLY_TO'] ?? 'suporte@eskill.com.br',
        'smtp_host' => $_ENV['SMTP_HOST'] ?? '',
        'smtp_port' => (int)($_ENV['SMTP_PORT'] ?? 587),
        'smtp_user' => $_ENV['SMTP_USER'] ?? '',
        'smtp_pass' => $_ENV['SMTP_PASS'] ?? '',
        'smtp_secure' => $_ENV['SMTP_SECURE'] ?? 'tls',
    ],
    
    'polling' => [
        'enabled' => filter_var($_ENV['POLLING_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'interval_minutes' => (int)($_ENV['POLLING_INTERVAL_MINUTES'] ?? 30),
    ],
    
    'telegram' => [
        'enabled' => filter_var($_ENV['TELEGRAM_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'bot_token' => $_ENV['TELEGRAM_BOT_TOKEN'] ?? null,
        'chat_id' => $_ENV['TELEGRAM_CHAT_ID'] ?? null,
    ],
    
    'monitoring' => [
        'enabled' => filter_var($_ENV['MONITORING_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'interval_minutes' => (int)($_ENV['MONITORING_INTERVAL_MINUTES'] ?? 5),
    ],
    
    // ========================================
    // Feature Flags - Ficha Técnica (Tech Sheet)
    // ========================================
    'tech_sheet' => [
        'enabled' => filter_var($_ENV['TECH_SHEET_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'ai_enabled' => filter_var($_ENV['TECH_SHEET_AI_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'benchmark_enabled' => filter_var($_ENV['TECH_SHEET_BENCHMARK_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'auto_apply' => filter_var($_ENV['TECH_SHEET_AUTO_APPLY'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'min_confidence_auto' => (int)($_ENV['TECH_SHEET_MIN_CONFIDENCE_AUTO'] ?? 90),
        'batch_limit' => (int)($_ENV['TECH_SHEET_BATCH_LIMIT'] ?? 200),
        'summary_ttl_hours' => (int)($_ENV['TECH_SHEET_SUMMARY_TTL_HOURS'] ?? 12),
        'benchmark_max_competitors' => (int)($_ENV['TECH_SHEET_BENCHMARK_MAX_COMPETITORS'] ?? 10),
        'benchmark_cache_ttl' => (int)($_ENV['TECH_SHEET_BENCHMARK_CACHE_TTL'] ?? 3600),
    ],
];

