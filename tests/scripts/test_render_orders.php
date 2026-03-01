<?php
// Simulate environment for Orders Page Render
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
// Mock Session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Test User';
$_SESSION['active_ml_account_id'] = 1;

// Mock UserService to bypass detailed auth checks if needed, but Controller uses real one.
// We will try running the controller method directly.

require_once __DIR__ . '/app/Controllers/BaseController.php';
require_once __DIR__ . '/app/Controllers/OrdersController.php';

try {
    echo "Instantiating OrdersController...\n";
    $controller = new \App\Controllers\OrdersController();

    // Capture Output
    ob_start();
    $controller->index();
    $output = ob_get_clean();

    echo "Page Rendered Successfully. Length: " . strlen($output) . " bytes.\n";
    // Check for fatal errors in output just in case
    if (strpos($output, 'Fatal error') !== false) {
        echo "FOUND FATAL ERROR IN OUTPUT\n";
    }

    // Check if key elements exist
    if (strpos($output, 'Pedidos') !== false) {
        echo "Found 'Pedidos' title.\n";
    } else {
        echo "WARNING: 'Pedidos' title not found.\n";
    }
} catch (\Throwable $e) {
    echo "RENDER FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
