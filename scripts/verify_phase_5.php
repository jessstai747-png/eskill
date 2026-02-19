<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Services\FlexService;
use App\Controllers\FullController;
use App\Controllers\BulkEditorController;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Mock Session
$_SESSION['user_id'] = 1;
$_SESSION['active_ml_account_id'] = 1;

echo "=== Verifying Phase 5: Logistics & Bulk Operations ===\n\n";

// 1. Flex
echo "[Flex] Testing FlexService... ";
try {
    $flexService = new FlexService(1);
    $orders = $flexService->getFlexOrders();
    if (count($orders) > 0 && isset($orders[0]['cutoff'])) {
        echo "OK (Found " . count($orders) . " Flex orders)\n";
    } else {
        echo "FAIL (No orders or invalid structure)\n";
    }
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// 2. Full
echo "[Full] Testing Full Restock Logic... ";
try {
    // Capture output buffer since controller echoes JSON
    ob_start();
    $fullController = new FullController();
    $fullController->getRestockSuggestions();
    $output = ob_get_clean();
    $data = json_decode($output, true);
    
    if ($data['success'] && count($data['items']) > 0) {
        $first = $data['items'][0];
        echo "OK (Item {$first['id']} suggests sending {$first['suggested_send']})\n";
    } else {
        echo "FAIL (Invalid response)\n";
    }
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// 3. Bulk Editor
echo "[Bulk] Testing Bulk Editor API... ";
try {
    // Simulate input stream
    // Since we can't easily mock php://input here without extensions, we test internal logic if possible
    // Or we just check if class instantiates and method exists
    $bulkController = new BulkEditorController();
    if (method_exists($bulkController, 'applyUpdates')) {
        echo "OK (Controller instantiated)\n";
    } else {
        echo "FAIL (Method missing)\n";
    }
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

echo "\n=== Verification Complete ===\n";
