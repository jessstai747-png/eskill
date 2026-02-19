<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\WhatsAppService;
use App\Services\AuditLogService;
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$db = Database::getInstance();
$userId = 1; // Assuming user ID 1 exists
$accountId = 12345;

echo "--- Testing Audit Logs ---\n";
$audit = new AuditLogService();
$action = 'TEST_ACTION_' . time();
$audit->log($action, $userId, $accountId, ['test' => 'data']);

$logs = $audit->getLogs(['action' => $action]);
if (!empty($logs) && $logs[0]['action'] === $action) {
    echo "[SUCCESS] Audit log created and retrieved.\n";
} else {
    echo "[FAILURE] Audit log NOT found.\n";
}

echo "\n--- Testing WhatsApp Service ---\n";
$whatsapp = new WhatsAppService($userId);

// Configure simulator
$whatsapp->saveSettings([
    'provider' => 'simulator',
    'is_active' => 1
]);

// Re-instantiate to test loading
$whatsapp = new WhatsAppService($userId);
$settings = $whatsapp->getSettings();
echo "Debug Settings: " . print_r($settings, true) . "\n";

$response = $whatsapp->send('5511999999999', 'Hello automated test');
if ($response['success']) {
    echo "[SUCCESS] WhatsApp message sent via simulator. Response: " . json_encode($response) . "\n";
} else {
    echo "[FAILURE] WhatsApp message failed. Error: " . ($response['error'] ?? 'Unknown') . "\n";
}

// Check logs
$msgLogs = $whatsapp->getLogs(5);
if (!empty($msgLogs)) {
    echo "[SUCCESS] WhatsApp logs retrieved. Count: " . count($msgLogs) . "\n";
} else {
    echo "[FAILURE] WhatsApp logs empty.\n";
}
