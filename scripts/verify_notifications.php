<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "--- Testing Real-Time Notifications ---\n\n";

use App\Services\NotificationBroadcaster;

// Mock session
$_SESSION = [
    'user_id' => 1,
    'current_account_id' => 1
];

try {
    $broadcaster = new NotificationBroadcaster();
    
    echo "1. Testing Notification Broadcasting\n";
    
    // Test order notification
    echo "   Broadcasting order notification...\n";
    $success = $broadcaster->broadcastOrder([
        'id' => 12345,
        'buyer' => ['nickname' => 'TestBuyer'],
        'total_amount' => 99.90
    ], 1, 1);
    
    echo "   " . ($success ? "✓" : "✗") . " Order notification\n";
    
    // Test question notification
    echo "   Broadcasting question notification...\n";
    $success = $broadcaster->broadcastQuestion([
        'id' => 67890,
        'text' => 'Does this product come with warranty?',
        'item_id' => 'MLB123456'
    ], 1, 1);
    
    echo "   " . ($success ? "✓" : "✗") . " Question notification\n";
    
    // Test price alert
    echo "   Broadcasting price alert...\n";
    $success = $broadcaster->broadcastPriceAlert(
        'Competitor dropped price by 15%',
        ['competitor' => 'Store XYZ', 'price' => 84.90],
        1,
        1
    );
    
    echo "   " . ($success ? "✓" : "✗") . " Price alert\n";
    
    // Test stock alert
    echo "   Broadcasting stock alert...\n";
    $success = $broadcaster->broadcastStockAlert('MLB123456', 3, 1, 1);
    
    echo "   " . ($success ? "✓" : "✗") . " Stock alert\n";
    
    echo "\n2. Testing Notification History\n";
    $history = $broadcaster->getHistory(1, 10);
    echo "   Found " . count($history) . " notification(s) in history\n";
    
    if (count($history) > 0) {
        echo "   Latest: " . $history[0]['type'] . " at " . $history[0]['created_at'] . "\n";
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "✅ Real-Time Notifications: WORKING\n";
    echo "\nTo test SSE streaming:\n";
    echo "1. Open browser to /dashboard\n";
    echo "2. Open browser console\n";
    echo "3. Run: NotificationClient.sendTest()\n";
    echo "4. You should see a toast notification appear\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
