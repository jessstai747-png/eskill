<?php
// Mock Request to Controller directly since we can't do full HTTP stack easily in CLI
require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use App\Controllers\QuestionController;
use App\Database;

// Mock Session
$_SESSION['active_ml_account_id'] = 1;

echo "\n📢 Testing QuestionController::draft...\n";

// We need an ID.
// Same issue as before, finding a real ID.
// But we want to test that the ROUTE reaches the CONTROLLER and calls SERVICE.
// Even if service returns error, if we get JSON response, the controller plumbing is working.

// Since I can't easily instantiate Controller with full Router context effectively without mocking $_SERVER items or using a framework bootstrapper,
// I will just check if the method exists and instantiate it manually.

if (method_exists(QuestionController::class, 'draft')) {
    echo "✅ Method 'draft' exists in QuestionController.\n";
    
    // Check API route file textually
    $routes = file_get_contents(__DIR__ . '/../app/Routes/api.php');
    if (strpos($routes, "api/questions/{id}/draft") !== false) {
        echo "✅ Route '/api/questions/{id}/draft' defined in api.php.\n";
    } else {
        echo "❌ Route missing.\n";
    }

} else {
    echo "❌ Method 'draft' missing.\n";
}
