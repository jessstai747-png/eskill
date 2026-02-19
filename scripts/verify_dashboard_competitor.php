<?php
// Mocking session and request
$_SESSION['user_id'] = 123;
// We need to bypass Auth check or mock it. 
// Since we are running cli script, we can instantiate controller and mock dependencies or just test the service output again?
// Let's test the endpoint via curl if possible, but we need auth cookie.
// Easier to test controller method directly with mocked dependencies? 
// Or just replicate the logic in a script.

// Replicating logic to verify 'getmetrics' output structure
require_once __DIR__ . '/../vendor/autoload.php';
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use App\Database;
use App\Services\CompetitorService;

echo "Testing Competitor Alerts retrieval...\n";

try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT id FROM ml_accounts WHERE status = 'active' LIMIT 1");
    $accountId = $stmt->fetchColumn();

    if ($accountId) {
        $service = new CompetitorService((int)$accountId);
        $alerts = $service->getRecentAlerts(5);
        echo "Alerts Count: " . count($alerts) . "\n";
        print_r($alerts);
    } else {
        echo "No active account.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
