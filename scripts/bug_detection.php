<?php
/**
 * Comprehensive Bug Detection Script
 * Checks for PHP syntax errors, missing files, database issues
 */

$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_PORT'] = '3306';
$_ENV['DB_DATABASE'] = 'meli';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = 'Tr1unf0@';
$_ENV['APP_KEY'] = 'dbcb4ee5a3c9c67c6e2b315025a4ff7d6a2cfb47ef66132ba865502ef528b29e';

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Database.php';

$bugs = [];
$warnings = [];

echo "=== COMPREHENSIVE BUG DETECTION ===\n\n";

// Test 1: Check critical view files exist
echo "[1] Checking Critical View Files...\n";
$criticalViews = [
    '/app/Views/dashboard/index.php',
    '/app/Views/dashboard/metrics.php',
    '/app/Views/dashboard/questions.php',
    '/app/Views/dashboard/messages.php',
    '/app/Views/dashboard/items.php',
    '/app/Views/dashboard/catalog_clone.php',
    '/app/Views/dashboard/analytics/index.php',
    '/app/Views/auth/login.php',
    '/app/Views/auth/register.php',
];

foreach ($criticalViews as $view) {
    $path = __DIR__ . $view;
    if (!file_exists($path)) {
        $bugs[] = "Missing view file: $view";
        echo "  ✗ Missing: $view\n";
    } else {
        // Check for PHP syntax errors
        $output = [];
        $returnCode = 0;
        exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            $bugs[] = "Syntax error in $view: " . implode(' ', $output);
            echo "  ✗ Syntax error: $view\n";
        } else {
            echo "  ✓ $view\n";
        }
    }
}

// Test 2: Check database connectivity and tables
echo "\n[2] Checking Database...\n";
try {
    $db = \App\Database::getInstance();
    echo "  ✓ Database connected\n";
    
    $requiredTables = [
        'users',
        'ml_accounts',
        'ml_items',
        'ml_questions',
        'ml_orders',
        'settlements',
        'jobs',
        'ai_optimization_logs'
    ];
    
    foreach ($requiredTables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as cnt FROM $table LIMIT 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "  ✓ Table '$table' exists (rows: {$result['cnt']})\n";
        } catch (\Exception $e) {
            $warnings[] = "Table '$table' may not exist or is inaccessible";
            echo "  ⚠ Table '$table': " . $e->getMessage() . "\n";
        }
    }
} catch (\Exception $e) {
    $bugs[] = "Database connection failed: " . $e->getMessage();
    echo "  ✗ Database error: " . $e->getMessage() . "\n";
}

// Test 3: Check for duplicate route definitions
echo "\n[3] Checking Routes...\n";
$routeFiles = [
    '/app/Routes/web.php',
    '/app/Routes/api.php',
    '/app/Routes/auth.php',
];

$allRoutes = [];
foreach ($routeFiles as $routeFile) {
    $path = __DIR__ . $routeFile;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        // Extract route definitions
        preg_match_all('/\$router->(get|post|put|delete|patch)\([\'"]([^\'"]+)/', $content, $matches);
        foreach ($matches[2] as $route) {
            if (isset($allRoutes[$route])) {
                $warnings[] = "Duplicate route: $route in $routeFile";
                echo "  ⚠ Duplicate route: $route\n";
            }
            $allRoutes[$route] = $routeFile;
        }
        echo "  ✓ Checked $routeFile (" . count($matches[2]) . " routes)\n";
    }
}

// Test 4: Check for undefined variables in critical controllers
echo "\n[4] Checking Controllers for Common Issues...\n";
$controllers = [
    '/app/Controllers/DashboardController.php',
    '/app/Controllers/QuestionController.php',
    '/app/Controllers/SettlementController.php',
    '/app/Controllers/AnalyticsController.php',
];

foreach ($controllers as $controller) {
    $path = __DIR__ . $controller;
    if (file_exists($path)) {
        $output = [];
        $returnCode = 0;
        exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            $bugs[] = "Syntax error in $controller";
            echo "  ✗ Syntax error: $controller\n";
        } else {
            echo "  ✓ $controller\n";
        }
    } else {
        $bugs[] = "Missing controller: $controller";
        echo "  ✗ Missing: $controller\n";
    }
}

// Test 5: Check JavaScript files for common issues
echo "\n[5] Checking JavaScript Files...\n";
$jsFiles = glob(__DIR__ . '/public/js/*.js');
foreach ($jsFiles as $jsFile) {
    $basename = basename($jsFile);
    // Check for undefined variables or common errors
    $content = file_get_contents($jsFile);
    
    // Check for fetch without error handling
    if (strpos($content, 'fetch(') !== false && strpos($content, '.catch(') === false) {
        $warnings[] = "JS: $basename may have fetch() without error handling";
        echo "  ⚠ $basename: fetch without .catch()\n";
    }
    
    // Check for console.log in production
    $logCount = substr_count($content, 'console.log(');
    if ($logCount > 10) {
        $warnings[] = "JS: $basename has $logCount console.log statements";
        echo "  ⚠ $basename: $logCount console.log statements\n";
    }
}
echo "  ✓ Checked " . count($jsFiles) . " JavaScript files\n";

// Test 6: Check .env file
echo "\n[6] Checking Configuration...\n";
if (!file_exists(__DIR__ . '/.env')) {
    $bugs[] = ".env file missing";
    echo "  ✗ .env file not found\n";
} else {
    $envContent = file_get_contents(__DIR__ . '/.env');
    $requiredVars = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'APP_KEY', 'ML_CLIENT_ID'];
    foreach ($requiredVars as $var) {
        if (strpos($envContent, $var) === false) {
            $warnings[] = ".env missing variable: $var";
            echo "  ⚠ Missing .env variable: $var\n";
        }
    }
    echo "  ✓ .env file exists\n";
}

// Summary
echo "\n═══════════════════════════════════════\n";
echo "BUG DETECTION SUMMARY\n";
echo "═══════════════════════════════════════\n";
echo "Critical Bugs Found: " . count($bugs) . "\n";
echo "Warnings: " . count($warnings) . "\n\n";

if (count($bugs) > 0) {
    echo "CRITICAL BUGS:\n";
    foreach ($bugs as $i => $bug) {
        echo ($i + 1) . ". $bug\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "WARNINGS:\n";
    foreach ($warnings as $i => $warning) {
        echo ($i + 1) . ". $warning\n";
    }
    echo "\n";
}

if (count($bugs) === 0 && count($warnings) === 0) {
    echo "STATUS: NO CRITICAL ISSUES FOUND ✓\n";
    exit(0);
} else {
    echo "STATUS: ISSUES DETECTED ⚠\n";
    exit(count($bugs) > 0 ? 1 : 0);
}
