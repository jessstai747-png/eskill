<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\AI\Core\AutoOptimizationService;
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "--- Testing Auto-Optimization Service ---\n";

// 1. Initialize Service (triggers table creation)
$stmt = Database::getInstance()->query("SELECT id FROM ml_accounts LIMIT 1");
$accountId = $stmt->fetchColumn() ?: 12345;

// Create dummy account if not exists
if ($accountId == 12345) {
    try {
        Database::getInstance()->exec("
            INSERT INTO ml_accounts (id, user_id, nickname, access_token, refresh_token, expires_in)
            VALUES (12345, 1, 'Test Account', 'test_token', 'test_refresh', 21600)
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");
        echo "[OK] Dummy account 12345 ensured.\n";
    } catch (Exception $e) { 
        echo "[WARN] Could not ensure dummy account (might violate User FK): " . $e->getMessage() . "\n";
    }
}

$service = new AutoOptimizationService($accountId);
echo "[OK] Service initialized for Account $accountId.\n";

// 2. Clear previous test data
$db = Database::getInstance();
$db->exec("DELETE FROM ai_scheduled_optimizations WHERE account_id = $accountId");
$db->exec("DELETE FROM ai_auto_optimization_rules WHERE account_id = $accountId");

// 3. Create a Test Rule (Rule: Score < 90 -> Auto Optimize)
// We set a high threshold (90) to ensure our test item (likely having 0-100 score) triggers it
$db->prepare("
    INSERT INTO ai_auto_optimization_rules 
    (account_id, name, condition_type, condition_operator, condition_value, action, priority)
    VALUES (?, 'Test Rule', 'score', 'lt', '90', 'auto_optimize', 100)
")->execute([$accountId]);
echo "[OK] Test rule created (Score < 90 -> Auto Optimize).\n";

// 4. Mock an Item (We need to insert a dummy item into 'items' table if not exists, or verify logic with Reflection)
// Since analyzeAndApplyRules fetches from DB, we need a real item in the DB.
// Let's create a dummy item.
$itemId = 'TEST-AUTO-' . time();
$db->prepare("
    INSERT INTO items (ml_item_id, account_id, title, price, status, created_at)
    VALUES (?, ?, 'Test Item for Auto Opt', 100.00, 'active', NOW())
")->execute([$itemId, $accountId]);
echo "[OK] Dummy item $itemId created.\n";

// 5. Run Analysis
echo "Running analysis...\n";
$results = $service->analyzeAndApplyRules(['limit' => 1]);

print_r($results);

// 6. Verify Schedule
$stmt = $db->prepare("SELECT * FROM ai_scheduled_optimizations WHERE item_id = ?");
$stmt->execute([$itemId]);
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);

if ($schedule) {
    echo "[SUCCESS] Optimization scheduled for item $itemId.\n";
    echo "Status: " . $schedule['status'] . "\n";
} else {
    echo "[FAILURE] No optimization scheduled.\n";
}

// Cleanup
$db->exec("DELETE FROM items WHERE ml_item_id = '$itemId'");
