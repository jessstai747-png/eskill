<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ApiTokenService;
use App\Services\NotificationService;
use App\Services\PdfService;
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "\n[0] Cleaning up tables...\n";
$db = Database::getInstance();
$db->exec("DROP TABLE IF EXISTS api_tokens");
$db->exec("DROP TABLE IF EXISTS alert_notifications");

// --- 1. Test API Token Service ---
echo "\n[1] Testing ApiTokenService...\n";
try {
    // Get first valid user
    
    // Get first valid user
    $stmt = $db->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Create one if absolutely empty
         $db->exec("INSERT INTO users (name, email, password) VALUES ('Test User', 'test@example.com', 'hash')");
         $userId = $db->lastInsertId();
         echo "[SETUP] Created user $userId.\n";
    } else {
        $userId = $user['id'];
        echo "[SETUP] Using user ID $userId.\n";
    }

    $apiService = new ApiTokenService();
    
    // Create Token
    $tokenData = $apiService->createToken($userId, 'Test Token', ['read', 'write'], 30);
    if (!empty($tokenData['token'])) {
        echo "[OK] Token created: " . substr($tokenData['token'], 0, 10) . "...\n";
    } else {
        echo "[FAIL] Token creation returned empty.\n";
    }

    // Validate Token
    $valid = $apiService->validateToken($tokenData['token']);
    if ($valid && $valid['user_id'] == $userId) {
        echo "[OK] Token validation successful.\n";
    } else {
        echo "[FAIL] Token validation failed. Dump:\n";
        print_r($valid);
        echo "Expected UserID: $userId\n";
    }
    
} catch (Exception $e) {
    echo "[FAIL] ApiTokenService Error: " . $e->getMessage() . "\n";
}

// --- 2. Test Notification Service ---
echo "\n[2] Testing NotificationService...\n";
try {
    $notifyService = new NotificationService();
    
    // Send Alert (Internal DB log check)
    $results = $notifyService->sendAlert("Test Alert", "This is a test alert from script.", "LOW");
    
    // Verify DB
    $db = Database::getInstance();
    $stmt = $db->query("SELECT * FROM alert_notifications ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetch();
    
    if ($last && $last['title'] === 'Test Alert') {
        echo "[OK] Notification saved to DB (ID: " . $last['id'] . ")\n";
    } else {
        echo "[FAIL] Notification not found in DB.\n";
        print_r($results);
    }

} catch (Exception $e) {
    echo "[FAIL] NotificationService Error: " . $e->getMessage() . "\n";
}

// --- 3. Test PDF Service ---
echo "\n[3] Testing PdfService...\n";
try {
    if (!class_exists('Dompdf\Dompdf')) {
        echo "[SKIP] Dompdf class not found. Run 'composer require dompdf/dompdf'.\n";
    } else {
        $pdfService = new PdfService();
        
        $data = [
            'total_sales' => 5000.00,
            'period' => 'last_30_days',
            'top_products' => [
                ['title' => 'Product A', 'quantity' => 10, 'revenue' => 1000],
                ['title' => 'Product B', 'quantity' => 5, 'revenue' => 500]
            ]
        ];
        
        $pdfContent = $pdfService->generateSalesReport($data);
        
        if (strlen($pdfContent) > 100 && strpos($pdfContent, '%PDF') === 0) {
            echo "[OK] PDF Generated successfully (" . strlen($pdfContent) . " bytes).\n";
        } else {
            echo "[FAIL] PDF Generation returned invalid content.\n";
        }
    }
} catch (Exception $e) {
    echo "[FAIL] PdfService Error: " . $e->getMessage() . "\n";
}
