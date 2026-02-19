<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;

$client = new Client();
try {
    echo "Testing Public Search with explicit headers...\n";
    $response = $client->get('https://api.mercadolibre.com/sites/MLB/search?q=iphone&limit=1', [
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json'
        ]
    ]);
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Body: " . substr($response->getBody(), 0, 100) . "...\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
