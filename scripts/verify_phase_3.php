<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Services\AdsService;
use App\Controllers\AdsController;
use App\Services\RealTimeNotificationService;
use App\Controllers\HealthController;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Mock Environment
$_SESSION['user_id'] = 1;
$_SESSION['active_ml_account_id'] = 1;
$_SERVER['REQUEST_METHOD'] = 'GET';

echo "=== Verifying Phase 3 Features ===\n\n";

// 1. Ads Service
echo "[Ads] Testing AdsService... ";
try {
    $adsService = new AdsService();
    // Use reflection to call protected/mock methods if needed, or public ones
    // Here we test metrics (public)
    $metrics = $adsService->getMetrics();
    if (isset($metrics['roas'])) {
        echo "OK (ROAS: {$metrics['roas']})\n";
    } else {
        echo "FAIL (Missing metrics)\n";
    }
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// 2. Notification Preferences
echo "[Notifications] Testing Preferences... ";
try {
    $notifyService = new RealTimeNotificationService();
    $notifyService->saveSettings(1, [
        'email_orders' => false,
        'whatsapp_orders' => true
    ]);
    
    $settings = $notifyService->getSettings(1);
    if ($settings['email_orders'] == 0 && $settings['whatsapp_orders'] == 1) {
        echo "OK (Settings Saved)\n";
    } else {
        echo "FAIL (Values mismatch)\n";
    }
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// 3. Health Check
echo "[Health] Testing HealthController... ";
try {
    ob_start();
    $health = new HealthController();
    $health->check();
    $output = ob_get_clean();
    $json = json_decode($output, true);
    
    if (isset($json['success']) && $json['success']) {
        echo "OK (DB Latency: {$json['database']['latency_ms']}ms)\n";
    } else {
        echo "FAIL (Invalid JSON response)\n";
    }
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

echo "\n=== Verification Complete ===\n";
