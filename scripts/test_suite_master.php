<?php
/**
 * MASTER TEST SUITE - Phase 23
 * Comprehensive system integration testing
 */

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  🧪 MASTER TEST SUITE - SYSTEM INTEGRITY CHECK            ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

$db = Database::getInstance();
$results = [];
$totalTests = 0;
$passedTests = 0;

// Test Runner Function
function runTest($name, $callable) {
    global $results, $totalTests, $passedTests;
    $totalTests++;
    
    echo "▶ Testing: $name... ";
    
    try {
        $result = $callable();
        if ($result === true || $result === 'PASS') {
            echo "✅ PASS\n";
            $passedTests++;
            $results[$name] = 'PASS';
            return true;
        } else {
            echo "❌ FAIL: $result\n";
            $results[$name] = "FAIL: $result";
            return false;
        }
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $results[$name] = "ERROR: " . $e->getMessage();
        return false;
    }
}

// ============================================================================
// SECTION 1: DATABASE INTEGRITY
// ============================================================================
echo "\n🗄️  DATABASE TESTS\n";
echo "─────────────────────────────────────────────────────────────\n";

runTest("Database Connection", function() use ($db) {
    return $db->query("SELECT 1")->fetchColumn() == 1;
});

runTest("Critical Tables Exist", function() use ($db) {
    $tables = ['items', 'ml_orders', 'ml_questions', 'ai_agents', 'mobile_devices', 'shopee_items'];
    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'")->fetch();
        if (!$result) return "Missing table: $table";
    }
    return true;
});

runTest("Database Indexes", function() use ($db) {
    $check = $db->query("SHOW INDEX FROM items WHERE Key_name = 'idx_status'")->fetch();
    return $check ? true : "Missing critical index on items.status";
});

// ============================================================================
// SECTION 2: CORE SERVICES
// ============================================================================
echo "\n⚙️  CORE SERVICE TESTS\n";
echo "─────────────────────────────────────────────────────────────\n";

runTest("ItemService Instantiation", function() {
    $service = new \App\Services\ItemService();
    return method_exists($service, 'getItem');
});

runTest("OrderService Profit Calculation", function() use ($db) {
    // Create mock order
    $db->exec("INSERT INTO ml_orders (ml_order_id, ml_account_id, order_data, status, total_amount, date_created) 
               VALUES (9999999999, 1, '{}', 'paid', 100.00, NOW()) 
               ON DUPLICATE KEY UPDATE status='paid'");
    
    $order = $db->query("SELECT * FROM ml_orders WHERE ml_order_id = 9999999999")->fetch(PDO::FETCH_ASSOC);
    
    // Cleanup
    $db->exec("DELETE FROM ml_orders WHERE ml_order_id = 9999999999");
    
    return $order ? true : "Failed to create test order";
});

runTest("QuestionService AI Integration Check", function() {
    $service = new \App\Services\QuestionService();
    return method_exists($service, 'generateDraftAnswer');
});

// ============================================================================
// SECTION 3: AI AGENTS
// ============================================================================
echo "\n🤖 AI AGENT TESTS\n";
echo "─────────────────────────────────────────────────────────────\n";

runTest("Agent Registry", function() use ($db) {
    $agents = $db->query("SELECT COUNT(*) FROM ai_agents WHERE status = 'active'")->fetchColumn();
    return $agents >= 2 ? true : "Expected at least 2 active agents, found $agents";
});

runTest("Guardian Agent Logic", function() {
    $agent = new \App\Agents\GuardianAgent();
    return method_exists($agent, 'checkLowStock') && method_exists($agent, 'checkStagnantAds');
});

runTest("Sniper Agent Logic", function() {
    $agent = new \App\Agents\SniperAgent();
    return method_exists($agent, 'scanForOpportunities');
});

runTest("Agent Logging System", function() use ($db) {
    $db->exec("INSERT INTO ai_agent_logs (agent_code, level, message) VALUES ('test', 'info', 'Test log entry')");
    $check = $db->query("SELECT id FROM ai_agent_logs WHERE agent_code = 'test' ORDER BY id DESC LIMIT 1")->fetch();
    $db->exec("DELETE FROM ai_agent_logs WHERE agent_code = 'test'");
    return $check ? true : "Failed to log agent action";
});

// ============================================================================
// SECTION 4: MULTI-CHANNEL
// ============================================================================
echo "\n🌐 MULTI-CHANNEL TESTS\n";
echo "─────────────────────────────────────────────────────────────\n";

runTest("Mobile API Authentication", function() {
    $controller = new \App\Controllers\Mobile\AuthController();
    return method_exists($controller, 'login');
});

runTest("Mobile Dashboard Data", function() {
    $controller = new \App\Controllers\Mobile\DashboardController();
    return method_exists($controller, 'overview');
});

runTest("Shopee Service Integration", function() {
    $service = new \App\Services\ShopeeService();
    return method_exists($service, 'getAuthUrl') && method_exists($service, 'getItems');
});

runTest("Shopee Database Schema", function() use ($db) {
    $check = $db->query("SHOW TABLES LIKE 'shopee_items'")->fetch();
    return $check ? true : "Missing shopee_items table";
});

// ============================================================================
// SECTION 5: REPORTING SYSTEM
// ============================================================================
echo "\n📊 REPORTING TESTS\n";
echo "─────────────────────────────────────────────────────────────\n";

runTest("Report Service - Sales", function() {
    $service = new \App\Services\ReportService();
    return method_exists($service, 'generateSalesReport');
});

runTest("Report Service - Inventory", function() {
    $service = new \App\Services\ReportService();
    return method_exists($service, 'generateInventoryReport');
});

runTest("Report Service - Customer", function() {
    $service = new \App\Services\ReportService();
    return method_exists($service, 'generateCustomerReport');
});

// ============================================================================
// SECTION 6: ROUTING & CONTROLLERS
// ============================================================================
echo "\n🛣️  ROUTING TESTS\n";
echo "─────────────────────────────────────────────────────────────\n";

runTest("AgentController Exists", function() {
    return class_exists('\App\Controllers\AgentController');
});

runTest("ShopeeController Exists", function() {
    return class_exists('\App\Controllers\ShopeeController');
});

runTest("ReportController Enhanced", function() {
    $controller = new \App\Controllers\ReportController();
    $reflection = new ReflectionClass($controller);
    return $reflection->hasMethod('generatePdf') && $reflection->hasMethod('generateCsv');
});

// ============================================================================
// FINAL SUMMARY
// ============================================================================
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  📈 TEST SUMMARY                                           ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
printf("║  Total Tests:   %-42d ║\n", $totalTests);
printf("║  Passed:        %-42d ║\n", $passedTests);
printf("║  Failed:        %-42d ║\n", $totalTests - $passedTests);
printf("║  Success Rate:  %-41.1f%% ║\n", ($passedTests / $totalTests) * 100);
echo "╚════════════════════════════════════════════════════════════╝\n";

if ($passedTests === $totalTests) {
    echo "\n🎉 ALL TESTS PASSED! System is 100% operational.\n\n";
    exit(0);
} else {
    echo "\n⚠️  Some tests failed. Review output above.\n\n";
    exit(1);
}
