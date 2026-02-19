<?php
// Simulate environment for backend test
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_PORT'] = '3306';
$_ENV['DB_DATABASE'] = 'meli';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = 'Tr1unf0@';
$_ENV['APP_KEY'] = 'dbcb4ee5a3c9c67c6e2b315025a4ff7d6a2cfb47ef66132ba865502ef528b29e';

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Services/DashboardService.php';
// Mocks/Stubs if needed, but we want to test real service
// We need to mock ItemService and others since we don't want to instantiate everything if dependencies are heavy
// But let's try to instantiate DashboardService directly first. Need to mock dependencies in constructor?
// DashboardService uses `new ItemService()` inside `getMetrics`. We can't mock "new" easily in plain PHP without DI container or runkit.
// So we rely on ItemService being instantiable.

// We need to define namespace autoloading or require files manually
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

try {
    echo "Instantiating DashboardService...\n";
    $service = new \App\Services\DashboardService();
    
    echo "Fetching metrics...\n";
    $metrics = $service->getMetrics();
    
    echo "Validating structure...\n";
    $requiredKeys = ['recent_orders_count', 'total_revenue', 'sales_over_time', 'orders_by_status'];
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $metrics)) {
            throw new Exception("Missing key: $key");
        }
    }
    
    echo "Metrics JSON Structure:\n";
    echo json_encode($metrics, JSON_PRETTY_PRINT);
    echo "\n\nVERIFICATION PASSED\n";
    
} catch (\Throwable $e) {
    echo "VERIFICATION FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
