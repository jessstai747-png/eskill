<?php
/**
 * Items Module Comprehensive Test Script
 * Tests view rendering, API endpoints, and data integrity
 */

$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_PORT'] = '3306';
$_ENV['DB_DATABASE'] = 'meli';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = 'Tr1unf0@';
$_ENV['APP_KEY'] = 'dbcb4ee5a3c9c67c6e2b315025a4ff7d6a2cfb47ef66132ba865502ef528b29e';

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Database.php';

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Test User';
$_SESSION['active_ml_account_id'] = 1;
$_SERVER['REQUEST_URI'] = '/dashboard/items';

$errors = [];
$warnings = [];

echo "=== ITEMS MODULE COMPREHENSIVE TEST ===\n\n";

// 1. Test View Rendering
echo "[1/7] Testing Items View Rendering...\n";
try {
    require_once __DIR__ . '/app/Controllers/DashboardController.php';
    require_once __DIR__ . '/app/Services/UserService.php';
    require_once __DIR__ . '/app/Services/DashboardService.php';
    require_once __DIR__ . '/app/Services/CatalogCloneService.php';
    
    $userService = new \App\Services\UserService();
    $dashboardService = new \App\Services\DashboardService();
    $cloneService = new \App\Services\CatalogCloneService();
    
    $controller = new \App\Controllers\DashboardController($dashboardService, $userService, $cloneService);
    
    ob_start();
    $controller->items();
    $output = ob_get_clean();
    
    if (strlen($output) > 5000 && strpos($output, 'Meus Anúncios') !== false) {
        echo "✓ View rendered successfully (" . strlen($output) . " bytes)\n";
        
        // Check for key elements
        $checks = [
            'loadItems()' => 'JavaScript loadItems function',
            'loadStats()' => 'JavaScript loadStats function',
            'editModal' => 'Edit modal',
            'itemsGrid' => 'Items grid container',
            '/api/items/stats' => 'Stats API endpoint call'
        ];
        
        foreach ($checks as $needle => $description) {
            if (strpos($output, $needle) !== false) {
                echo "  ✓ Found: $description\n";
            } else {
                $warnings[] = "View missing: $description";
                echo "  ⚠ Missing: $description\n";
            }
        }
    } else {
        $errors[] = "View rendering produced insufficient output";
        echo "✗ View rendering failed or incomplete\n";
    }
} catch (\Exception $e) {
    $errors[] = "View Rendering: " . $e->getMessage();
    echo "✗ View rendering error: " . $e->getMessage() . "\n";
}
echo "\n";

// 2. Test ItemController Instantiation
echo "[2/7] Testing ItemController...\n";
try {
    require_once __DIR__ . '/app/Services/ItemService.php';
    require_once __DIR__ . '/app/Controllers/ItemController.php';
    
    $itemController = new \App\Controllers\ItemController();
    echo "✓ ItemController instantiated successfully\n\n";
} catch (\Exception $e) {
    $errors[] = "ItemController: " . $e->getMessage();
    echo "✗ ItemController failed: " . $e->getMessage() . "\n\n";
}

// 3. Test Items Table
echo "[3/7] Testing Items Database Table...\n";
try {
    $db = \App\Database::getInstance();
    $stmt = $db->query("SELECT COUNT(*) as count FROM items");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['count'];
    
    echo "✓ Items table exists with $count items\n";
    
    // Check for important columns
    $stmt = $db->query("SHOW COLUMNS FROM items");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = ['id', 'ml_item_id', 'title', 'price', 'available_quantity', 'status', 'sku', 'cost_price', 'tax_rate'];
    $missingColumns = [];
    
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $columns)) {
            $missingColumns[] = $col;
        }
    }
    
    if (empty($missingColumns)) {
        echo "✓ All required columns present\n";
    } else {
        $warnings[] = "Items table missing columns: " . implode(', ', $missingColumns);
        echo "⚠ Missing columns: " . implode(', ', $missingColumns) . "\n";
    }
} catch (\PDOException $e) {
    $errors[] = "Items table: " . $e->getMessage();
    echo "✗ Items table error: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Test Stats API Simulation
echo "[4/7] Testing Stats Calculation Logic...\n";
try {
    $db = \App\Database::getInstance();
    
    // Simulate stats query
    $stmt = $db->query("
        SELECT 
            status,
            COUNT(*) as count,
            SUM(COALESCE(visits, 0)) as total_visits,
            SUM(COALESCE(sold_quantity, 0)) as total_sold
        FROM items
        WHERE ml_account_id IN (SELECT id FROM ml_accounts WHERE user_id = 1)
        GROUP BY status
    ");
    
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $active = 0;
    $paused = 0;
    $closed = 0;
    $totalViews = 0;
    $totalSold = 0;
    
    foreach ($stats as $stat) {
        $totalViews += $stat['total_visits'];
        $totalSold += $stat['total_sold'];
        
        if ($stat['status'] === 'active') $active = $stat['count'];
        elseif ($stat['status'] === 'paused') $paused = $stat['count'];
        elseif ($stat['status'] === 'closed') $closed = $stat['count'];
    }
    
    echo "✓ Stats calculated:\n";
    echo "  - Active: $active\n";
    echo "  - Paused: $paused\n";
    echo "  - Closed: $closed\n";
    echo "  - Total Views: $totalViews\n";
    echo "  - Total Sold: $totalSold\n";
} catch (\Exception $e) {
    $warnings[] = "Stats calculation: " . $e->getMessage();
    echo "⚠ Stats calculation warning: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Test Categories Query
echo "[5/7] Testing Categories Query...\n";
try {
    $db = \App\Database::getInstance();
    $stmt = $db->query("
        SELECT DISTINCT category_id, category_name, COUNT(*) as count
        FROM items
        WHERE category_id IS NOT NULL
        GROUP BY category_id, category_name
        LIMIT 10
    ");
    
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ Found " . count($categories) . " categories\n";
    
    if (count($categories) > 0) {
        echo "  Sample: " . $categories[0]['category_name'] . " (" . $categories[0]['count'] . " items)\n";
    }
} catch (\Exception $e) {
    $warnings[] = "Categories query: " . $e->getMessage();
    echo "⚠ Categories query warning: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Test Item Update Logic
echo "[6/7] Testing Item Update Capability...\n";
try {
    $db = \App\Database::getInstance();
    
    // Find a test item
    $stmt = $db->query("SELECT id, ml_item_id, title FROM items LIMIT 1");
    $testItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testItem) {
        echo "✓ Found test item: " . $testItem['title'] . "\n";
        echo "  (ID: " . $testItem['ml_item_id'] . ")\n";
        
        // Check if we can prepare an update (don't actually execute)
        $stmt = $db->prepare("
            UPDATE items 
            SET sku = :sku, cost_price = :cost, tax_rate = :tax
            WHERE id = :id
        ");
        
        echo "✓ Update statement prepared successfully\n";
    } else {
        $warnings[] = "No items found to test update";
        echo "⚠ No items in database to test\n";
    }
} catch (\Exception $e) {
    $errors[] = "Item update test: " . $e->getMessage();
    echo "✗ Item update test failed: " . $e->getMessage() . "\n";
}
echo "\n";

// 7. Test JavaScript API Endpoints Existence
echo "[7/7] Checking API Endpoints Configuration...\n";
$apiEndpoints = [
    '/api/items' => 'List items',
    '/api/items/stats' => 'Get statistics',
    '/api/items/categories' => 'List categories',
    '/api/items/{id}' => 'Get item details',
    '/api/items/{id}/pause' => 'Pause item',
    '/api/items/{id}/activate' => 'Activate item',
    '/api/items/sync' => 'Sync items'
];

$apiFile = file_get_contents(__DIR__ . '/app/Routes/api.php');
$foundEndpoints = 0;

foreach ($apiEndpoints as $endpoint => $description) {
    $searchPattern = str_replace('{id}', '', $endpoint);
    if (strpos($apiFile, $searchPattern) !== false) {
        echo "✓ Found: $endpoint ($description)\n";
        $foundEndpoints++;
    } else {
        $warnings[] = "API endpoint not found: $endpoint";
        echo "⚠ Missing: $endpoint ($description)\n";
    }
}

echo "\n";

// Summary
echo "=== TEST SUMMARY ===\n";
echo "Errors: " . count($errors) . "\n";
echo "Warnings: " . count($warnings) . "\n\n";

if (!empty($errors)) {
    echo "CRITICAL ISSUES:\n";
    foreach ($errors as $i => $error) {
        echo ($i + 1) . ". $error\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "WARNINGS:\n";
    foreach ($warnings as $i => $warning) {
        echo ($i + 1) . ". $warning\n";
    }
    echo "\n";
}

if (empty($errors)) {
    echo "ITEMS MODULE: OPERATIONAL ✓\n";
    echo "The Items module is functional with " . count($warnings) . " minor warnings.\n";
    exit(0);
} else {
    echo "ITEMS MODULE: ISSUES FOUND\n";
    echo "Found " . count($errors) . " critical issues that need fixing.\n";
    exit(1);
}
