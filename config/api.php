<?php

/**
 * Real-Time Database API Configuration
 * 
 * Configuration settings for the professional database API
 */

return [
    'api' => [
        'version' => '1.0.0',
        'name' => 'Real-Time Database API',
        'description' => 'Professional API for real-time database operations',
        'base_url' => $_ENV['APP_URL'] ?? 'https://eskill.com.br',
        'prefix' => '/api/v1',
        'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    ],
    
    'security' => [
        'rate_limit' => [
            'requests_per_hour' => 100,
            'window_seconds' => 3600,
        ],
        'authentication' => [
            'required' => true,
            'token_ttl' => 3600, // 1 hour
            'refresh_interval' => 1800, // 30 minutes
        ],
        'allowed_tables' => [
            'users', 'items', 'ml_orders', 'products', 'categories', 
            'ml_accounts', 'account_health_history', 'seo_analysis_cache',
            'notifications', 'settings', 'logs', 'analytics'
        ],
        'max_results' => 1000,
    ],
    
    'logging' => [
        'enabled' => true,
        'level' => $_ENV['LOG_LEVEL'] ?? 'info',
        'file' => __DIR__ . '/../storage/logs/api.log',
    ],
    
    'database' => [
        'connection_timeout' => 30,
        'query_timeout' => 60,
        'max_connections' => 10,
    ],
    
    'caching' => [
        'enabled' => true,
        'ttl' => 300, // 5 minutes
        'driver' => 'file', // file, redis, memcached
    ],
];