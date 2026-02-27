<?php
/**
 * Comprehensive System Verification Script
 * Tests all major components and reports issues
 */

$_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? (getenv('DB_HOST') ?: 'localhost');
$_ENV['DB_PORT'] = $_ENV['DB_PORT'] ?? (getenv('DB_PORT') ?: '3306');
$_ENV['DB_DATABASE'] = $_ENV['DB_DATABASE'] ?? (getenv('DB_DATABASE') ?: 'meli');
$_ENV['DB_USERNAME'] = $_ENV['DB_USERNAME'] ?? (getenv('DB_USERNAME') ?: 'root');

$dbPassword = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
if ($dbPassword !== false && $dbPassword !== null) {
    $_ENV['DB_PASSWORD'] = (string)$dbPassword;
}

$appKey = $_ENV['APP_KEY'] ?? getenv('APP_KEY');
if ($appKey !== false && $appKey !== null) {
    $_ENV['APP_KEY'] = (string)$appKey;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Database.php';

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Test User';

$results = [];
$errors = [];

echo "=== COMPREHENSIVE SYSTEM VERIFICATION ===\n\n";

// 1. Database Connectivity
echo "[1/10] Testing Database Connectivity...\n";
try {
    $db = \App\Database::getInstance();
    $stmt = $db->query("SELECT 1");
    $results['database'] = 'OK';
    echo "✓ Database connection successful\n\n";
} catch (\Exception $e) {
    $errors[] = "Database: " . $e->getMessage();
    echo "✗ Database connection failed: " . $e->getMessage() . "\n\n";
}

// 2. Critical Tables Existence
echo "[2/10] Checking Critical Tables...\n";
$requiredTables = ['users', 'ml_accounts', 'ml_orders', 'items', 'ml_questions'];
$missingTables = [];
foreach ($requiredTables as $table) {
    try {
        $db->query("SELECT 1 FROM $table LIMIT 1");
        echo "✓ Table '$table' exists\n";
    } catch (\PDOException $e) {
        $missingTables[] = $table;
        echo "✗ Table '$table' missing or inaccessible\n";
    }
}
if (empty($missingTables)) {
    $results['tables'] = 'OK';
} else {
    $errors[] = "Missing tables: " . implode(', ', $missingTables);
}
echo "\n";

// 3. Dashboard Service
echo "[3/10] Testing Dashboard Service...\n";
try {
    require_once __DIR__ . '/app/Services/DashboardService.php';
    $dashService = new \App\Services\DashboardService();
    $metrics = $dashService->getMetrics();
    if (isset($metrics['recent_orders_count'])) {
        $results['dashboard_service'] = 'OK';
        echo "✓ Dashboard Service working (found " . $metrics['recent_orders_count'] . " orders)\n\n";
    } else {
        $errors[] = "Dashboard Service: Invalid metrics structure";
        echo "✗ Dashboard Service returned invalid structure\n\n";
    }
} catch (\Exception $e) {
    $errors[] = "Dashboard Service: " . $e->getMessage();
    echo "✗ Dashboard Service failed: " . $e->getMessage() . "\n\n";
}

// 4. Order Service
echo "[4/10] Testing Order Service...\n";
try {
    require_once __DIR__ . '/app/Services/OrderService.php';
    $orderService = new \App\Services\OrderService(null);
    
    // Get accounts
    $stmt = $db->query("SELECT id FROM ml_accounts LIMIT 3");
    $accountIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($accountIds)) {
        $orders = $orderService->getOrdersFromMultipleAccounts($accountIds, ['limit' => 5]);
        $results['order_service'] = 'OK';
        echo "✓ Order Service working (" . ($orders['total'] ?? 0) . " orders found)\n\n";
    } else {
        echo "⚠ No ML accounts found to test Order Service\n\n";
    }
} catch (\Exception $e) {
    $errors[] = "Order Service: " . $e->getMessage();
    echo "✗ Order Service failed: " . $e->getMessage() . "\n\n";
}

// 5. Question Service
echo "[5/10] Testing Question Service...\n";
try {
    require_once __DIR__ . '/app/Services/QuestionService.php';
    $questionService = new \App\Services\QuestionService(1);
    $questions = $questionService->getQuestions(['limit' => 1]);
    $results['question_service'] = 'OK';
    echo "✓ Question Service working\n\n";
} catch (\Exception $e) {
    $errors[] = "Question Service: " . $e->getMessage();
    echo "✗ Question Service failed: " . $e->getMessage() . "\n\n";
}

// 6. Item Service
echo "[6/10] Testing Item Service...\n";
try {
    require_once __DIR__ . '/app/Services/ItemService.php';
    $itemService = new \App\Services\ItemService(1);
    // Just instantiate - full test would require API calls
    $results['item_service'] = 'OK';
    echo "✓ Item Service instantiated successfully\n\n";
} catch (\Exception $e) {
    $errors[] = "Item Service: " . $e->getMessage();
    echo "✗ Item Service failed: " . $e->getMessage() . "\n\n";
}

// 7. Settlement Service
echo "[7/10] Testing Settlement Service...\n";
try {
    require_once __DIR__ . '/app/Services/SettlementService.php';
    $settlementService = new \App\Services\SettlementService();
    $summary = $settlementService->getSummary();
    $results['settlement_service'] = 'OK';
    echo "✓ Settlement Service working\n\n";
} catch (\Exception $e) {
    $errors[] = "Settlement Service: " . $e->getMessage();
    echo "✗ Settlement Service failed: " . $e->getMessage() . "\n\n";
}

// 8. User Service
echo "[8/10] Testing User Service...\n";
try {
    require_once __DIR__ . '/app/Services/UserService.php';
    $userService = new \App\Services\UserService();
    $user = $userService->getCurrentUser();
    if ($user) {
        $results['user_service'] = 'OK';
        echo "✓ User Service working (user: " . ($user['name'] ?? 'Unknown') . ")\n\n";
    } else {
        echo "⚠ User Service: No current user (expected in test context)\n\n";
    }
} catch (\Exception $e) {
    $errors[] = "User Service: " . $e->getMessage();
    echo "✗ User Service failed: " . $e->getMessage() . "\n\n";
}

// 9. View Rendering
echo "[9/10] Testing View Rendering...\n";
try {
    $_SERVER['REQUEST_URI'] = '/dashboard/orders';
    ob_start();
    require __DIR__ . '/app/Views/dashboard/orders-content.php';
    $output = ob_get_clean();
    if (strlen($output) > 1000 && strpos($output, 'Pedidos') !== false) {
        $results['view_rendering'] = 'OK';
        echo "✓ View rendering working (" . strlen($output) . " bytes)\n\n";
    } else {
        $errors[] = "View Rendering: Output too small or missing key elements";
        echo "✗ View rendering produced unexpected output\n\n";
    }
} catch (\Exception $e) {
    $errors[] = "View Rendering: " . $e->getMessage();
    echo "✗ View rendering failed: " . $e->getMessage() . "\n\n";
}

// 10. Error Log Analysis
echo "[10/10] Analyzing Error Logs...\n";
$errorLogPath = __DIR__ . '/storage/logs/error.log';
if (file_exists($errorLogPath)) {
    $recentErrors = shell_exec("tail -n 50 $errorLogPath | grep -E 'Fatal|Critical' | wc -l");
    $recentErrors = (int)trim($recentErrors);
    if ($recentErrors > 0) {
        echo "⚠ Found $recentErrors recent Fatal/Critical errors in logs\n";
        $errors[] = "Error Logs: $recentErrors recent critical errors";
    } else {
        echo "✓ No recent Fatal/Critical errors in logs\n";
        $results['error_logs'] = 'OK';
    }
} else {
    echo "⚠ Error log file not found\n";
}
echo "\n";

// Summary
echo "=== VERIFICATION SUMMARY ===\n";
echo "Passed: " . count($results) . "/" . 10 . " checks\n";
if (!empty($errors)) {
    echo "\nISSUES FOUND:\n";
    foreach ($errors as $i => $error) {
        echo ($i + 1) . ". $error\n";
    }
    echo "\nVERIFICATION: FAILED\n";
    exit(1);
} else {
    echo "\nVERIFICATION: PASSED ✓\n";
    echo "All critical systems are operational.\n";
    exit(0);
}
