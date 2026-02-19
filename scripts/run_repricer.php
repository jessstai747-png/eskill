<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Services\RepricingService;

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting Repricer Job...\n";

    $service = new RepricingService(1); // Default to Account 1 for now
    $results = $service->executeBatch(50);

    echo "Repricing Completed.\n";
    echo "Total Items Processed: {$results['total']}\n";
    echo "Prices Updated: {$results['updated']}\n";
    echo "Errors: {$results['errors']}\n";

    if (!empty($results['details'])) {
        foreach ($results['details'] as $id => $detail) {
            if (isset($detail['error'])) {
                 echo " - Item $id Error: {$detail['error']}\n";
            } elseif ($detail['updated']) {
                 echo " - Item $id Updated: R$ {$detail['old_price']} -> R$ {$detail['new_price']} ({$detail['strategy']})\n";
            } else {
                 // Verbose only
                 // echo " - Item $id Skipped: {$detail['reason']}\n";
            }
        }
    }

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
