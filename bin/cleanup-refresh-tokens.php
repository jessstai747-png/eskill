#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Load env only when not in production to avoid accidental fallback to .env files
$env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');
if ($env !== 'production') {
    // In non-production environments it's convenient to load .env; in production we rely
    // on explicit environment variables provided by the process manager / secret store.
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

use App\Services\RefreshTokenService;

$env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';
$days = $argv[1] ?? 30;

// Only run scheduled cleanup in production environment
if ($env !== 'production') {
    echo "Skipping cleanup-refresh-tokens: not in production (APP_ENV={$env})\n";
    exit(0);
}

try {
    $logger = new \App\Services\StructuredLogService();
    $logger->info('Scheduled: cleanup-refresh-tokens started', ['days' => (int)$days]);

    $service = new RefreshTokenService();
    $removed = $service->cleanupExpiredAndPrune((int)$days);
    $logger->info('Scheduled: cleanup-refresh-tokens completed', ['removed' => $removed]);
    echo "Cleanup completed. Removed {$removed} rows.\n";

    // Mark cron execution timestamp for monitoring
    @file_put_contents(__DIR__ . '/../storage/logs/cron_cleanup_refresh_tokens.log', date('c') . " - removed={$removed}\n", FILE_APPEND | LOCK_EX);
    exit(0);
    
    // Alert if a very large number of rows were removed (possible incident)
    $alertThreshold = (int)(getenv('CLEANUP_REMOVE_ALERT_THRESHOLD') ?: ($_ENV['CLEANUP_REMOVE_ALERT_THRESHOLD'] ?? 1000));
    if ($removed >= $alertThreshold) {
        try {
            $alerter = new \App\Services\AlertService();
            $alerter->alert('Large refresh token cleanup', ['removed' => $removed]);
        } catch (Throwable $e) {
            // ignore
        }
    }
    exit(0);
} catch (Throwable $e) {
    try {
        $logger->critical('Scheduled: cleanup-refresh-tokens failed', ['error' => $e->getMessage()]);
    } catch (Throwable $inner) {
        // fallback
        echo "ERROR: " . $e->getMessage() . "\n";
    }
    exit(2);
}
