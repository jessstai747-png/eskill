<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;
use App\Services\SettingsService;

echo "Starting Settings Service Verification...\n";

// 1. Setup
$accountId = 1; 
$service = new SettingsService($accountId);

// 2. Set Values
echo "Setting default tax rate to 15.5%...\n";
$service->set('default_tax_rate', 15.5);
$service->set('default_pricing_strategy', 'competitive');

// 3. Get Values
$tax = $service->getDefaultTaxRate();
$strategy = $service->getDefaultPricingStrategy();

echo "Retrieved Tax: $tax (Expected: 15.5)\n";
echo "Retrieved Strategy: $strategy (Expected: competitive)\n";

if ($tax == 15.5 && $strategy === 'competitive') {
    echo "SUCCESS: Settings saved and retrieved correctly.\n";
} else {
    echo "FAILURE: Values do not match.\n";
}
