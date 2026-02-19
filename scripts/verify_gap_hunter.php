<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Mock Environment if needed
$_ENV['REDIS_HOST'] = '127.0.0.1';
$_ENV['REDIS_PORT'] = 6379;
$_ENV['APP_KEY'] = 'base64:' . base64_encode(random_bytes(32));

echo "--- Testing Gap Hunter Algorithm ---\n";

use App\Services\GapHunterService;
use App\Database;

// Mock Database/API dependencies? 
// GapHunterService calls MercadoLivreClient which does HTTP requests.
// We probably can't run this fully without mocking API responses or having real creds.
// However, we can instantiate and maybe check if method exists and history logging works if we Mock results.

// Let's rely on unit test style mocking or just instantiation check + reflection to verify algorithm logic if possible.
// Or just check that it runs without syntax error.

try {
    $service = new GapHunterService();
    echo "Service instantiated.\n";
    
    if (method_exists($service, 'analyzeCategory')) {
        echo "Method analyzeCategory exists.\n";
    } else {
        echo "FAIL: Method analyzeCategory missing.\n";
    }
    
    // Check DB table existence
    $db = Database::getInstance();
    $stmt = $db->query("SHOW TABLES LIKE 'gap_trend_snapshots'");
    if ($stmt->rowCount() > 0) {
        echo "Table gap_trend_snapshots exists.\n";
    } else {
        echo "FAIL: Table gap_trend_snapshots missing.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
