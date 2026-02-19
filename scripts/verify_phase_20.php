<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;
use App\Services\AutonomousAgentService;

echo "=============================================\n";
echo "   🤖 TESTE AI AGENTS (PHASE 20) \n";
echo "=============================================\n";

$db = Database::getInstance();

// 1. Setup Mock Items
// Low Stock Item
$db->prepare("INSERT INTO items (ml_item_id, account_id, title, status, available_quantity, price, data) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE available_quantity=2")
    ->execute(['MLB_TEST_LOW', 1, 'Item Pouco Estoque', 'active', 2, 50.00, json_encode(['sold_quantity' => 10])]);

// Zombie Item
$db->prepare("INSERT INTO items (ml_item_id, account_id, title, status, available_quantity, price, created_at, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status='active'")
    ->execute(['MLB_TEST_ZOMBIE', 1, 'Item Zumbi', 'active', 100, 100.00, date('Y-m-d H:i:s', strtotime('-90 days')), json_encode(['sold_quantity' => 0])]);

// 2. Run Guardian Agent
$service = new AutonomousAgentService();
echo "Running Guardian Agent...\n";
$res = $service->runAgent('guardian');
print_r($res);

// 3. Verify Logs
echo "\nChecking Logs...\n";
$logs = $service->getLogs('guardian', 50);
$foundLowStock = false;
$foundZombie = false;

foreach ($logs as $log) {
    echo "[{$log['level']}] {$log['message']}\n";
    if (strpos($log['message'], 'Baixo Estoque') !== false) $foundLowStock = true;
    if (strpos($log['message'], 'Item Zumbi') !== false) $foundZombie = true;
}

if ($foundLowStock && $foundZombie) {
    echo "✅ SUCESSO: O Guardião detectou os problemas!\n";
} else {
    echo "❌ FALHA: Logs não encontrados.\n";
}

// Cleanup
$db->exec("DELETE FROM items WHERE ml_item_id IN ('MLB_TEST_LOW', 'MLB_TEST_ZOMBIE')");
$db->exec("DELETE FROM ai_agent_logs WHERE agent_code = 'guardian'");
