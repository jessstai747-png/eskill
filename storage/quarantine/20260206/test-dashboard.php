<?php
// Test script for DashboardController
header('Content-Type: text/plain');

require __DIR__ . '/../vendor/autoload.php';

echo "=== Testing DashboardController ===\n\n";

try {
    // Test BaseController instantiation
    $baseController = new class extends \App\Controllers\BaseController {
        public function test() {
            return 'OK';
        }
    };
    echo "✓ BaseController has constructor\n";
    
    // Test services
    $db = App\Database::getInstance();
    echo "✓ Database connected\n";
    
    $dashboardService = new App\Services\DashboardService();
    echo "✓ DashboardService instantiated\n";
    
    $userService = new App\Services\UserService();
    echo "✓ UserService instantiated\n";
    
    $cloneService = new App\Services\CatalogCloneService(1);
    echo "✓ CatalogCloneService instantiated\n";
    
    // Test DashboardController
    $controller = new App\Controllers\DashboardController(
        $dashboardService,
        $userService,
        $cloneService
    );
    echo "✓ DashboardController instantiated\n";
    
    echo "\n✅ All tests passed!\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

// Self-delete
@unlink(__FILE__);
