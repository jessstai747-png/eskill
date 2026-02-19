<?php
/**
 * Configurações Específicas para Produção
 * Carregar após config/app.php quando APP_ENV=production
 */

$config = require __DIR__ . '/app.php';

// Sobrescrever configurações para produção
return array_merge($config, [
    'env' => 'production',
    'debug' => false,
    
    // Desabilitar exibição de erros
    'error_reporting' => E_ALL & ~E_DEPRECATED & ~E_STRICT,
    'display_errors' => false,
    'log_errors' => true,
    
    // Segurança adicional
    'session' => [
        'cookie_httponly' => true,
        'cookie_secure' => true, // Apenas HTTPS
        'cookie_samesite' => 'Strict',
    ],
    
    // Cache mais agressivo em produção
    'cache' => [
        'driver' => $_ENV['CACHE_DRIVER'] ?? 'redis',
        'ttl' => (int)($_ENV['CACHE_TTL'] ?? 7200), // 2 horas
    ],
    
    // Rate limiting mais restritivo
    'rate_limit' => [
        'max_requests' => (int)($_ENV['RATE_LIMIT_MAX'] ?? 60),
        'window_seconds' => (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 60),
    ],
]);
