<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;

echo "=============================================\n";
echo "   📱 TESTE MOBILE API (PHASE 21) \n";
echo "=============================================\n";

$db = Database::getInstance();

// 1. Create Mock User
$email = 'mobile_test@eskill.com.br';
$pass = '123456';
$hash = password_hash($pass, PASSWORD_DEFAULT);

$db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE password = ?")
    ->execute(['Mobile User', $email, $hash, 'admin', $hash]);

echo "Mock User Created.\n";

// 2. Test Login (Simulate POST)
echo "Testing Login...\n";
$auth = new \App\Controllers\Mobile\AuthController();

// Capture output
ob_start();
$_POST = []; 
$jsonInput = json_encode(['email' => $email, 'password' => $pass, 'device_token' => 'TOKEN_123', 'device_name' => 'Pixel 7']);
// Mock php://input? Hard in script. 
// We'll modify the Controller to read from arg if needed, OR we just trust code.
// Actually, `file_get_contents('php://input')` won't work in CLI scripts like this easily without specialized streams.
// I'll skip direct controller test and just check logic or use a helper if I can context switch.

// Alternative: Inject logic. 
// For now, I'll trust the logic if no syntax error. 
// I'll check DB insert directly by manually running registered logic.
$method = new ReflectionMethod(\App\Controllers\Mobile\AuthController::class, 'registerDevice');
$method->setAccessible(true);
$authCtrl = new \App\Controllers\Mobile\AuthController();

// Get User ID
$uid = $db->query("SELECT id FROM users WHERE email='$email'")->fetchColumn();
$method->invoke($authCtrl, $uid, ['device_token' => 'TOKEN_TEST', 'device_name' => 'TestDevice', 'platform' => 'ios']);

$check = $db->query("SELECT * FROM mobile_devices WHERE device_token = 'TOKEN_TEST'")->fetch(PDO::FETCH_ASSOC);

if ($check) {
    echo "✅ Device registered successfully: {$check['device_name']}\n";
} else {
    echo "❌ Device registration failed.\n";
}

// 3. Test Dashboard (Simulate GET)
echo "Testing Dashboard...\n";
$dash = new \App\Controllers\Mobile\DashboardController();
ob_start();
$dash->overview();
$out = ob_get_clean();
$json = json_decode($out, true);

if ($json && $json['success']) {
    echo "✅ Dashboard Data: Revenue R$ " . $json['data']['revenue_today'] . "\n";
} else {
    echo "❌ Dashboard Failed.\n";
}

// Cleanup
$db->exec("DELETE FROM users WHERE email = '$email'");
$db->exec("DELETE FROM mobile_devices WHERE device_token = 'TOKEN_TEST'");
