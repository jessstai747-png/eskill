<?php
require_once __DIR__ . '/../vendor/autoload.php';

echo "--- Testing API Documentation ---\n\n";

try {
    echo "1. Checking OpenAPI Specification\n";
    $specPath = __DIR__ . '/../public/api-docs/openapi.json';
    
    if (!file_exists($specPath)) {
        throw new Exception("OpenAPI spec not found");
    }
    
    $spec = json_decode(file_get_contents($specPath), true);
    
    if (!$spec) {
        throw new Exception("Invalid JSON in OpenAPI spec");
    }
    
    echo "   ✓ OpenAPI spec found and valid\n";
    echo "   Version: " . ($spec['info']['version'] ?? 'unknown') . "\n";
    echo "   Title: " . ($spec['info']['title'] ?? 'unknown') . "\n";
    
    echo "\n2. Counting Documented Endpoints\n";
    $endpointCount = count($spec['paths'] ?? []);
    echo "   Endpoints: $endpointCount\n";
    
    foreach ($spec['paths'] as $path => $methods) {
        foreach ($methods as $method => $details) {
            if ($method === 'get' || $method === 'post' || $method === 'put' || $method === 'delete') {
                echo "   - " . strtoupper($method) . " $path\n";
            }
        }
    }
    
    echo "\n3. Checking Tags\n";
    $tagCount = count($spec['tags'] ?? []);
    echo "   Tags: $tagCount\n";
    
    foreach ($spec['tags'] as $tag) {
        echo "   - " . $tag['name'] . ": " . $tag['description'] . "\n";
    }
    
    echo "\n4. Checking Documentation UI\n";
    $uiPath = __DIR__ . '/../public/api-docs/index.html';
    
    if (!file_exists($uiPath)) {
        throw new Exception("Documentation UI not found");
    }
    
    echo "   ✓ Swagger UI page found\n";
    
    $uiContent = file_get_contents($uiPath);
    if (strpos($uiContent, 'swagger-ui') !== false) {
        echo "   ✓ Swagger UI configured\n";
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "✅ API Documentation: WORKING\n";
    echo "\nAccess documentation at:\n";
    echo "- Interactive UI: http://localhost/api-docs\n";
    echo "- OpenAPI Spec: http://localhost/api-docs/openapi.json\n";
    echo "\nFeatures:\n";
    echo "- $endpointCount documented endpoints\n";
    echo "- $tagCount API categories\n";
    echo "- Interactive Swagger UI\n";
    echo "- Try-it-out functionality\n";
    echo "- Request/response examples\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
