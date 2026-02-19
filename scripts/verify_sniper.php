<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;
use App\Services\AutonomousAgentService;

echo "=============================================\n";
echo "   🎯 TESTE SNIPER AGENT \n";
echo "=============================================\n";

$db = Database::getInstance();

// 1. Setup Mock Item
$db->prepare("INSERT INTO items (ml_item_id, account_id, title, status, price, min_price, auto_reprice) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE auto_reprice=1, min_price=80.00")
    ->execute(['MLB_TEST_SNIPER', 1, 'Item Sniper Alvo', 'active', 100.00, 80.00, 1]);

// 2. Run Sniper Agent
$service = new AutonomousAgentService();
echo "Running Sniper Agent...\n";
$res = $service->runAgent('sniper');
print_r($res);

// 3. Verify Logs
echo "\nChecking Logs...\n";
$logs = $service->getLogs('sniper', 10);
$foundShot = false;

foreach ($logs as $log) {
    echo "[{$log['level']}] {$log['message']}\n";
    if ($log['level'] === 'action' && strpos($log['message'], 'Sniper Shot') !== false) {
        $foundShot = true;
    }
}

if ($foundShot) {
    echo "✅ SUCESSO: O Sniper disparou!\n";
} else {
    echo "❌ FALHA: Sniper não atirou.\n";
}

// Cleanup
$db->exec("DELETE FROM items WHERE ml_item_id = 'MLB_TEST_SNIPER'");
$db->exec("DELETE FROM ai_agent_logs WHERE agent_code = 'sniper'");
