<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\PushNotificationService;
use App\Database;

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "\n--- Testing PWA Backend Services ---\n";

// --- 1. Static Assets Check ---
echo "\n[1] Checking Static Assets...\n";
$assets = [
    'public/manifest.json',
    'public/service-worker.js'
];

foreach ($assets as $asset) {
    if (file_exists(__DIR__ . '/../' . $asset)) {
        echo "[OK] Found $asset\n";
    } else {
        echo "[FAIL] Missing $asset\n";
    }
}

// --- 2. Push Notification Service ---
echo "\n[2] Testing PushNotificationService...\n";

try {
    $db = Database::getInstance();
    
    // Ensure dummy user exists for FK
    $userId = 99999;
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        $db->exec("INSERT INTO users (id, name, email, password) VALUES ($userId, 'PWA Test User', 'pwa@test.com', 'hash')");
    }

    $pushService = new PushNotificationService();
    
    // Check VAPID Keys
    $publicKey = $pushService->getVapidPublicKey();
    if (!empty($publicKey)) {
        echo "[OK] VAPID Public Key generated: " . substr($publicKey, 0, 20) . "...\n";
    } else {
        echo "[FAIL] Failed to generate VAPID Key.\n";
    }

    // Test Subscription Mock
    $mockEndpoint = "https://fcm.googleapis.com/fcm/send/test-token-" . time();
    $mockSubscription = [
        'endpoint' => $mockEndpoint,
        'keys' => [
            'p256dh' => 'mock-p256dh-key',
            'auth' => 'mock-auth-key'
        ]
    ];

    // Save Subscription
    $result = $pushService->saveSubscription($userId, $mockSubscription);
    if ($result['success']) {
        echo "[OK] Subscription saved successfully (ID: {$result['id']}).\n";
        
        // Verify in DB
        $saved = $pushService->findSubscriptionByEndpoint($mockEndpoint);
        if ($saved && $saved['user_id'] == $userId) {
            echo "[OK] Subscription verified in DB.\n";
        } else {
            echo "[FAIL] Could not verify subscription in DB.\n";
        }

        // Test Remove
        $remove = $pushService->removeSubscription($userId, $mockEndpoint);
        if ($remove['success']) {
            echo "[OK] Subscription removed successfully.\n";
        } else {
            echo "[FAIL] Failed to remove subscription.\n";
        }

    } else {
        echo "[FAIL] Failed to save subscription: " . ($result['error'] ?? 'Unknown error') . "\n";
    }

} catch (Exception $e) {
    echo "[CRITICAL] Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n--- Test Completed ---\n";
