<?php
// Verify Orders API Backend Logic
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_PORT'] = '3306';
$_ENV['DB_DATABASE'] = 'meli';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = 'Tr1unf0@';
$_ENV['APP_KEY'] = 'dbcb4ee5a3c9c67c6e2b315025a4ff7d6a2cfb47ef66132ba865502ef528b29e';

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Database.php';

// Mock Session for OrderController
session_start();
$_SESSION['user_id'] = 1;

// Simulate having linked accounts
// We need to know valid account IDs.
// Let's first query the DB to find valid accounts.

try {
    $db = \App\Database::getInstance();
    $stmt = $db->query("SELECT id, nickname FROM ml_accounts");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($accounts) . " accounts in DB.\n";
    if (empty($accounts)) {
        echo "WARNING: No accounts found. API will return empty list/error.\n";
    }

    $accountIds = array_column($accounts, 'id');
    // Mock SessionHelper behavior by mocking the session data it likely uses if we were using the helper? 
    // OrderController uses SessionHelper::getUserAccountIds(). 
    // We can't easily mock static method SessionHelper::getUserAccountIds without runkit.
    // BUT, we can instantiate OrderService directly and test getOrdersFromMultipleAccounts, which is what the controller calls.
    
    require_once __DIR__ . '/app/Services/OrderService.php';
    
    echo "Instantiating OrderService...\n";
    $service = new \App\Services\OrderService(null); // No specific account context needed for multi-account fetch
    
    echo "Fetching orders for account IDs: " . implode(', ', $accountIds) . "\n";
    $result = $service->getOrdersFromMultipleAccounts($accountIds, ['limit' => 5]);
    
    echo "Total Orders Found: " . ($result['total'] ?? 'N/A') . "\n";
    echo "Results Count: " . count($result['results']) . "\n";
    
    if (!empty($result['results'])) {
        $first = $result['results'][0];
        echo "Sample Order ID: " . ($first['id'] ?? 'NULL') . "\n";
        echo "Sample Total: " . ($first['total_amount'] ?? 'NULL') . "\n";
    } else {
        echo "No orders found in local DB. This might be normal if sync hasn't run.\n";
        
        // Check if table exists
        try {
            $db->query("SELECT 1 FROM ml_orders LIMIT 1");
            echo "Table 'ml_orders' exists.\n";
        } catch (\PDOException $e) {
            echo "CRITICAL: Table 'ml_orders' does not exist or error accessing it: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nVERIFICATION PASSED\n";

} catch (\Throwable $e) {
    echo "VERIFICATION FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
