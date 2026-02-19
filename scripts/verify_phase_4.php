<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Services\ClaimsService;
use App\Services\ItemService;
use App\Services\DashboardService;
use App\Services\UserService;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Mock Session
$_SESSION['user_id'] = 1;
$_SESSION['active_ml_account_id'] = 1;

echo "=== Verifying Phase 4: Advanced Operations ===\n\n";

// 1. Claims
echo "[Claims] Testing ClaimsService... ";
try {
    $claimsService = new ClaimsService(1);
    $claims = $claimsService->getClaims();
    if (count($claims) > 0 && isset($claims[0]['id'])) {
        echo "OK (Found " . count($claims) . " claims)\n";
    } else {
        echo "FAIL (No claims found)\n";
    }
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// 2. Catalog
echo "[Catalog] Testing ItemService Catalog Methods... ";
try {
    $itemService = new ItemService(1);
    $details = $itemService->getCatalogDetails('MLB123');
    if (isset($details['buy_box_winner'])) {
        echo "OK (Buy Box Price: R$ " . $details['buy_box_winner']['price'] . ")\n";
    } else {
        echo "FAIL (Invalid structure)\n";
    }
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// 3. Reputation
echo "[Reputation] Testing Dashboard Metrics... ";
try {
    $dashboardService = new DashboardService();
    $metrics = $dashboardService->getMetrics(1);
    
    if (isset($metrics['reputation_metrics']['claims_rate'])) {
        echo "OK (Claims Rate: " . $metrics['reputation_metrics']['claims_rate'] . "%)\n";
    } else {
        echo "FAIL (Missing reputation metrics)\n";
    }
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

echo "\n=== Verification Complete ===\n";
