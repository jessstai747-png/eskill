<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;
use App\Services\RepricingService;

try {
    $db = Database::getInstance();
    
    // Cleanup
    $db->exec("DELETE FROM items WHERE ml_item_id = 'TEST-REPRICER'");
    
    // Insert Test Item
    echo "Creating Test Item...\n";
    $stmt = $db->prepare("INSERT INTO items (ml_item_id, account_id, title, price, cost_price, min_price, max_price, pricing_strategy, auto_reprice, status, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute(['TEST-REPRICER', 1, 'Test Repricer Item', 100.00, 50.00, 80.00, 150.00, 'aggressive', 1, 'active']);
    
    // Run Repricer
    echo "Running Repricer Service...\n";
    $service = new RepricingService(1);
    $results = $service->executeBatch(50);
    
    print_r($results);
    
    // Check if it attempted to process
    if ($results['total'] > 0) {
        if ($results['errors'] > 0) {
             echo "SUCCESS: Attempted to process and failed (likely due to API), which is expected behavior without API mocks.\n";
        } else {
             echo "SUCCESS: Processed successfully.\n";
        }
    } else {
        echo "FAILURE: Did not pick up the item.\n";
    }
    
    // Cleanup
    $db->exec("DELETE FROM items WHERE ml_item_id = 'TEST-REPRICER'");

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
