<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Mock session for authentication if needed (though our API might be protected)
// For this script, we'll simulate a logged-in user if necessary, or just check public-ish endpoints if available.
// Actually, let's just test the controller logic directly or via internal request simulation if possible.
// But we can just use cURL to localhost.

function testEndpoint($url, $method = 'GET', $data = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost" . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    // We might need a cookie or token. For now, let's see if we get a 401 (which is good connectivity) or 404 (bad).
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Testing $url [$method]: HTTP $httpCode\n";
    // echo "Response: " . substr($response, 0, 100) . "...\n";
    return $httpCode;
}

echo "--- Verifying Agent API ---\n";
// List projects (should probably be 401 or 200)
testEndpoint('/api/agent/projects');

echo "\n--- Verifying Deep Research API ---\n";
// Deep Research endpoint
testEndpoint('/api/research/sellers/MLA1055'); // Category ID for Cellphones

