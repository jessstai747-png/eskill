<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\PromotionService;
use App\Controllers\CustomerController;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Mock Session
$_SESSION['user_id'] = 1;
$_SESSION['active_ml_account_id'] = 1;

echo "=== Verifying Phase 6: Promotions & CRM ===\n\n";

// 1. Promotions
echo "[Promotions] Testing PromotionService... ";
try {
    $promoService = new PromotionService(1);
    $promos = $promoService->getPromotions();
    if (count($promos) > 0) {
        $first = $promos[0];
        echo "OK (Found '{$first['name']}')\n";
        
        // Test Items
        echo "[Promotions] Testing Item Eligibility... ";
        $items = $promoService->getPromotionItems($first['id']);
        if (count($items) > 0) {
           echo "OK (Found " . count($items) . " eligible items)\n";
        } else {
           echo "FAIL (No items)\n";
        }
        
    } else {
        echo "FAIL (No promotions)\n";
    }
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// 2. Customer CRM
echo "[CRM] Testing Customer Aggregation... ";
try {
    ob_start();
    $customerController = new CustomerController();
    $customerController->listCustomers();
    $output = ob_get_clean();
    $data = json_decode($output, true);
    
    if ($data['success'] && count($data['customers']) > 0) {
        echo "OK (Found " . count($data['customers']) . " customers)\n";
    } else {
        echo "FAIL (Invalid response)\n";
    }
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

echo "\n=== Verification Complete ===\n";
