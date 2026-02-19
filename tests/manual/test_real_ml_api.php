<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\MercadoLivreClient;
use App\Services\MercadoLivreAuthService;
use Dotenv\Dotenv;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../', '.env'); // Force load .env (production-like)
$dotenv->safeLoad();

$authService = new MercadoLivreAuthService();
$url = $authService->getAuthUrl(1); // Pass int ID

echo "--- Mercado Livre Real API Verification ---\n";

// 1. Verify Public Endpoint Access (No Auth needed)
echo "\n[1] Testing Public API Access (Site Info)...\n";

// Force network access even if env thinks it's testing
putenv("ML_ALLOW_NETWORK=true"); 

$client = new MercadoLivreClient();
// Using a raw GET to a public endpoint: https://api.mercadolibre.com/sites/MLB
// 3rd arg null (no cache preference), 4th arg true (explicitly public)
$response = $client->get('/sites/MLB', [], null, true); 

if (isset($response['id']) && $response['id'] === 'MLB') {
    echo "✅ SUCCESS: Connected to Mercado Livre API.\n";
    echo "   Site: " . $response['name'] . "\n";
    echo "   Country: " . $response['country_id'] . "\n";
} else {
    echo "❌ FAILED: Could not fetch site info.\n";
    print_r($response);
}

// 2. Verify Auth URL Generation
echo "\n[2] Testing Auth URL Generation...\n";

// Mercado Livre uses mercadolivre.com.br or mercadolibre.com depending on region/config
if (filter_var($url, FILTER_VALIDATE_URL) && (strpos($url, 'mercadolibre.com') !== false || strpos($url, 'mercadolivre.com') !== false)) {
    echo "✅ SUCCESS: Auth URL generated correctly.\n";
    echo "   URL: $url\n";
} else {
    echo "❌ FAILED: Auth URL generation failed.\n";
    echo "   URL: $url\n";
}

// 3. Test Categories (Public)
echo "\n[3] Testing Category Fetching...\n";
$categories = $client->get('/sites/MLB/categories', [], null, true);

if (!empty($categories) && is_array($categories) && !isset($categories['error'])) {
    echo "✅ SUCCESS: Fetched " . count($categories) . " categories.\n";
    echo "   Example: " . ($categories[0]['name'] ?? 'Unknown') . " (" . ($categories[0]['id'] ?? 'Unknown') . ")\n";
} else {
    echo "❌ FAILED: Could not fetch categories.\n";
    if (isset($categories['error'])) {
        echo "   Error: " . $categories['message'] . "\n";
    }
}

echo "\n--- Verification Complete ---\n";
