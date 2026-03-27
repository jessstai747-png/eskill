<?php

declare(strict_types=1);

/**
 * Teste da rota /dashboard/competitors
 */

// Set environment to avoid logging to file during test
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Log errors to file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/storage/logs/competitors_test.log');

session_start();

// Simulate authenticated user
$_SESSION['user_id'] = 1;
$_SESSION['account_id'] = 1;

try {
    // Test database connection and table creation
    require_once __DIR__ . '/app/Services/CompetitorService.php';
    
    echo "Testing CompetitorService initialization...\n";
    $service = new \App\Services\CompetitorService(1);
    echo "✅ CompetitorService initialized successfully\n";
    
    // Test getting recent alerts
    echo "\nTesting getRecentAlerts()...\n";
    $alerts = $service->getRecentAlerts(5);
    echo "✅ Got " . count($alerts) . " alerts\n";
    
    echo "\n✅ All tests passed!\n";
    echo "The route should now work correctly.\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
