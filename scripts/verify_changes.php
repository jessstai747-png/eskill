<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Mock ENV
$_ENV['APP_ENV'] = 'testing';
$_ENV['CACHE_ENABLED'] = true;

// Mock Caching
// We can't easily mock the full middleware stack without a request, 
// so we will test the CacheMiddleware logic directly.

use App\Middleware\CacheMiddleware;

echo "--- Testing CacheMiddleware ---\n";

$middleware = new CacheMiddleware();
$uri = '/p/test-product-123';
$content = "<html><h1>Product Page</h1></html>";

// 1. Cache the response
echo "1. Caching response for $uri...\n";
$success = $middleware->cacheApiResponse('test', [], 10); // Using public method to test internals or reflection?
// Actually we need to test cacheResponse which is private, or simulate handle.

// Let's use reflection to test private method or just use handle with a mock next
echo "   Checking handle() flow...\n";

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['QUERY_STRING'] = '';
$_SESSION['user_id'] = 1;

$next = function() use ($content) {
    echo "   (Generating content)\n";
    return $content;
};

// First call - Should generate
$result1 = $middleware->handle($uri, 'GET', $next);
echo "   Result 1 length: " . strlen($result1) . "\n";

// Second call - Should be cached (we need to capture headers to verify HIT)
// Headers can't be tested easily in CLI without runkit or similar, but we can check if 'Generating content' is printed.
echo "2. requesting same URI (expecting cache hit)...\n";
$nextHit = function() {
    echo "   (FAIL: Should not be called)\n";
    return "New Content";
};

$result2 = $middleware->handle($uri, 'GET', $nextHit);
echo "   Result 2 length: " . strlen($result2) . "\n";

if ($result1 === $result2) {
    echo "SUCCESS: Content matches.\n";
} else {
    echo "FAIL: Content mismatch.\n";
}

echo "\n--- Testing AI Service (Mock) ---\n";
// We won't call real API here to save tokens, but we check if the service is instantiable
$ai = new \App\Services\AIContentGeneratorService();
echo "AI Service instantiated successfully.\n";
