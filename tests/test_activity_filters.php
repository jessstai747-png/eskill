<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Mock Database/PDO if needed, or better, use the actual service if we can connect to DB
// Assuming we can use the actual service as we are in the environment

// Bootstrap minimal app
define('ROOT_PATH', dirname(__DIR__));
require_once __DIR__ . '/../config/app.php'; 

// Load .env
if (file_exists(ROOT_PATH . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
    $dotenv->load();
}

// Low-level autoload if needed
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

use App\Services\AuditLogService;
use App\Database;

try {
    $service = new AuditLogService();
    $db = Database::getInstance();

    // 1. Create some test data
    $userId = 99999; // Test user
    $service->log('test_action_1', $userId, null, ['desc' => 'Test 1']);
    $service->log('test_action_2', $userId, null, ['desc' => 'Test 2']);
    
    // Validating basic fetch
    echo "Fetching logs for user $userId...\n";
    $logs = $service->getLogs(['user_id' => $userId]);
    echo "Found " . count($logs) . " logs.\n";

    // Validating Action Filter
    echo "Filtering by action 'test_action_1'...\n";
    $logs = $service->getLogs(['user_id' => $userId, 'action' => 'test_action_1']);
    echo "Found " . count($logs) . " logs (Expected: >=1).\n";
    
    if (count($logs) > 0 && $logs[0]['action'] === 'test_action_1') {
        echo "✅ Action Filter Passed\n";
    } else {
        echo "❌ Action Filter Failed\n";
    }

    // Validating Date Filter
    echo "Filtering by date (today)...\n";
    $today = date('Y-m-d');
    $logs = $service->getLogs([
        'user_id' => $userId, 
        'date_from' => $today . ' 00:00:00',
        'date_to' => $today . ' 23:59:59'
    ]);
    echo "Found " . count($logs) . " logs (Expected: >=2).\n";

    if (count($logs) >= 2) {
        echo "✅ Date Filter Passed\n";
    } else {
        echo "❌ Date Filter Failed\n";
    }

    // Cleanup
    $db->exec("DELETE FROM audit_logs WHERE user_id = $userId");
    echo "Cleanup done.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
