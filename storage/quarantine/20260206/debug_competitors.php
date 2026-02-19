<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load autoloader
require_once __DIR__ . '/../autoload.php';

// Start session
session_start();

// Simulate authentication
$_SESSION['user_id'] = 1;
$_SESSION['account_id'] = 1;

echo "<h1>Debug Competitors Route</h1>";

try {
    echo "<h2>Step 1: Load UserService</h2>";
    $userService = new \App\Services\UserService();
    echo "✅ UserService loaded<br>";
    
    echo "<h2>Step 2: Check Authentication</h2>";
    $isAuth = $userService->isAuthenticated();
    echo "✅ Is authenticated: " . ($isAuth ? 'YES' : 'NO') . "<br>";
    
    if (!$isAuth) {
        echo "❌ User is not authenticated!<br>";
        exit;
    }
    
    echo "<h2>Step 3: Initialize CompetitorService</h2>";
    $service = new \App\Services\CompetitorService($_SESSION['account_id']);
    echo "✅ CompetitorService initialized<br>";
    
    echo "<h2>Step 4: Get Database Connection</h2>";
    $db = \App\Database::getInstance();
    echo "✅ Database connected<br>";
    
    echo "<h2>Step 5: Check if tables exist</h2>";
    $tables = $db->query("SHOW TABLES LIKE 'competitor%'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Found tables:<br>";
    foreach ($tables as $table) {
        echo "- $table<br>";
    }
    
    echo "<h2>Step 6: Query competitor_items</h2>";
    $stmt = $db->query("
        SELECT ci.*, 
               (SELECT price FROM competitor_price_history cph WHERE cph.competitor_item_id = ci.id ORDER BY recorded_at ASC LIMIT 1) as first_price,
               (SELECT recorded_at FROM competitor_price_history cph WHERE cph.competitor_item_id = ci.id ORDER BY recorded_at ASC LIMIT 1) as first_date
        FROM competitor_items ci 
        WHERE status != 'closed'
        ORDER BY updated_at DESC
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Query executed successfully. Found " . count($items) . " items<br>";
    
    echo "<h2>Step 7: Get alerts</h2>";
    $alerts = $service->getRecentAlerts(5);
    echo "✅ Got " . count($alerts) . " alerts<br>";
    
    echo "<h2>Step 8: Load view</h2>";
    $viewPath = __DIR__ . '/../app/Views/dashboard/competitors/index.php';
    if (file_exists($viewPath)) {
        echo "✅ View file exists: $viewPath<br>";
    } else {
        echo "❌ View file NOT found: $viewPath<br>";
    }
    
    echo "<hr>";
    echo "<h2>✅ ALL TESTS PASSED!</h2>";
    echo "<p>The route should work. Try accessing: <a href='/dashboard/competitors'>/dashboard/competitors</a></p>";
    
} catch (\Exception $e) {
    echo "<h2>❌ ERROR</h2>";
    echo "<pre style='background: #fee; padding: 15px; border: 2px solid red;'>";
    echo "Message: " . $e->getMessage() . "\n\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";
    echo "Stack Trace:\n" . $e->getTraceAsString();
    echo "</pre>";
}
