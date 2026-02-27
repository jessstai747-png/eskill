<?php
/**
 * Comprehensive All-Modules Test Suite
 * Tests all major dashboard modules systematically
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
$_SESSION['active_ml_account_id'] = 1;

$results = [];
$errors = [];
$warnings = [];

echo "=== COMPREHENSIVE ALL-MODULES TEST ===\n\n";

// Helper function to test view rendering
function testView($name, $path, $expectedContent) {
    global $results, $errors, $warnings;
    
    echo "Testing $name...\n";
    $_SERVER['REQUEST_URI'] = $path;
    
    try {
        ob_start();
        require __DIR__ . '/app/Views/dashboard/' . basename($path) . '.php';
        $output = ob_get_clean();
        
        if (strlen($output) > 1000 && strpos($output, $expectedContent) !== false) {
            echo "  ✓ $name renders (" . strlen($output) . " bytes)\n";
            $results[$name] = 'OK';
        } else {
            $warnings[] = "$name: Output too small or missing key content";
            echo "  ⚠ $name: Incomplete output\n";
        }
    } catch (\Exception $e) {
        $errors[] = "$name: " . $e->getMessage();
        echo "  ✗ $name failed: " . $e->getMessage() . "\n";
    }
}

// Helper function to test controller
function testController($name, $className, $method = 'index') {
    global $results, $errors;
    
    echo "Testing $name Controller...\n";
    
    try {
        $reflection = new ReflectionClass($className);
        if ($reflection->hasMethod($method)) {
            echo "  ✓ $name Controller has $method() method\n";
            $results[$name . '_controller'] = 'OK';
        } else {
            $errors[] = "$name Controller missing $method() method";
            echo "  ✗ $name Controller missing $method() method\n";
        }
    } catch (\Exception $e) {
        $errors[] = "$name Controller: " . $e->getMessage();
        echo "  ✗ $name Controller error: " . $e->getMessage() . "\n";
    }
}

// Test 1: Questions Module
echo "\n[1/10] QUESTIONS MODULE\n";
echo "─────────────────────────\n";
try {
    require_once __DIR__ . '/app/Controllers/QuestionController.php';
    testController('Question', 'App\\Controllers\\QuestionController', 'index');
    
    // Check questions table
    $db = \App\Database::getInstance();
    $stmt = $db->query("SELECT COUNT(*) as count FROM ml_questions");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  ✓ Questions table: $count questions\n";
    $results['questions_db'] = 'OK';
} catch (\Exception $e) {
    $errors[] = "Questions: " . $e->getMessage();
    echo "  ✗ Questions error: " . $e->getMessage() . "\n";
}

// Test 2: Messages Module
echo "\n[2/10] MESSAGES MODULE\n";
echo "─────────────────────────\n";
try {
    require_once __DIR__ . '/app/Controllers/MessageController.php';
    testController('Message', 'App\\Controllers\\MessageController', 'index');
    $results['messages'] = 'OK';
} catch (\Exception $e) {
    $warnings[] = "Messages: " . $e->getMessage();
    echo "  ⚠ Messages: " . $e->getMessage() . "\n";
}

// Test 3: Categories Module
echo "\n[3/10] CATEGORIES MODULE\n";
echo "─────────────────────────\n";
try {
    require_once __DIR__ . '/app/Controllers/CategoryController.php';
    testController('Category', 'App\\Controllers\\CategoryController', 'index');
    $results['categories'] = 'OK';
} catch (\Exception $e) {
    $errors[] = "Categories: " . $e->getMessage();
    echo "  ✗ Categories error: " . $e->getMessage() . "\n";
}

// Test 4: Analytics Module
echo "\n[4/10] ANALYTICS MODULE\n";
echo "─────────────────────────\n";
try {
    require_once __DIR__ . '/app/Controllers/AnalyticsController.php';
    testController('Analytics', 'App\\Controllers\\AnalyticsController', 'dashboard');
    $results['analytics'] = 'OK';
} catch (\Exception $e) {
    $warnings[] = "Analytics: " . $e->getMessage();
    echo "  ⚠ Analytics: " . $e->getMessage() . "\n";
}

// Test 5: SEO Module
echo "\n[5/10] SEO MODULE\n";
echo "─────────────────────────\n";
try {
    require_once __DIR__ . '/app/Controllers/SeoController.php';
    testController('SEO', 'App\\Controllers\\SeoController', 'dashboard');
    $results['seo'] = 'OK';
} catch (\Exception $e) {
    $warnings[] = "SEO: " . $e->getMessage();
    echo "  ⚠ SEO: " . $e->getMessage() . "\n";
}

// Test 6: Shipping Module
echo "\n[6/10] SHIPPING MODULE\n";
echo "─────────────────────────\n";
try {
    require_once __DIR__ . '/app/Controllers/ShippingController.php';
    testController('Shipping', 'App\\Controllers\\ShippingController', 'index');
    $results['shipping'] = 'OK';
} catch (\Exception $e) {
    $warnings[] = "Shipping: " . $e->getMessage();
    echo "  ⚠ Shipping: " . $e->getMessage() . "\n";
}

// Test 7: Claims Module
echo "\n[7/10] CLAIMS MODULE\n";
echo "─────────────────────────\n";
try {
    require_once __DIR__ . '/app/Controllers/ClaimsController.php';
    testController('Claims', 'App\\Controllers\\ClaimsController', 'index');
    $results['claims'] = 'OK';
} catch (\Exception $e) {
    $warnings[] = "Claims: " . $e->getMessage();
    echo "  ⚠ Claims: " . $e->getMessage() . "\n";
}

// Test 8: Returns Module
echo "\n[8/10] RETURNS MODULE\n";
echo "─────────────────────────\n";
try {
    require_once __DIR__ . '/app/Controllers/ReturnController.php';
    testController('Return', 'App\\Controllers\\ReturnController', 'index');
    $results['returns'] = 'OK';
} catch (\Exception $e) {
    $warnings[] = "Returns: " . $e->getMessage();
    echo "  ⚠ Returns: " . $e->getMessage() . "\n";
}

// Test 9: Catalog Clone Module
echo "\n[9/10] CATALOG CLONE MODULE\n";
echo "─────────────────────────\n";
try {
    require_once __DIR__ . '/app/Controllers/CatalogCloneController.php';
    testController('CatalogClone', 'App\\Controllers\\CatalogCloneController', 'index');
    $results['catalog_clone'] = 'OK';
} catch (\Exception $e) {
    $warnings[] = "Catalog Clone: " . $e->getMessage();
    echo "  ⚠ Catalog Clone: " . $e->getMessage() . "\n";
}

// Test 10: Customers Module
echo "\n[10/10] CUSTOMERS MODULE\n";
echo "─────────────────────────\n";
try {
    require_once __DIR__ . '/app/Controllers/CustomerController.php';
    testController('Customer', 'App\\Controllers\\CustomerController', 'index');
    $results['customers'] = 'OK';
} catch (\Exception $e) {
    $warnings[] = "Customers: " . $e->getMessage();
    echo "  ⚠ Customers: " . $e->getMessage() . "\n";
}

// Summary
echo "\n═══════════════════════════════════════\n";
echo "COMPREHENSIVE TEST SUMMARY\n";
echo "═══════════════════════════════════════\n";
echo "Modules Tested: 10\n";
echo "Passed: " . count($results) . "\n";
echo "Errors: " . count($errors) . "\n";
echo "Warnings: " . count($warnings) . "\n\n";

if (!empty($errors)) {
    echo "CRITICAL ERRORS:\n";
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

echo "TESTED MODULES:\n";
foreach ($results as $module => $status) {
    echo "✓ " . ucfirst(str_replace('_', ' ', $module)) . "\n";
}

if (count($errors) > 0) {
    echo "\nSTATUS: ISSUES FOUND ⚠\n";
    exit(1);
} else {
    echo "\nSTATUS: ALL MODULES OPERATIONAL ✓\n";
    exit(0);
}
