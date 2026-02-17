#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  ML Health Mapping Fix Validation\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

// Test 1: diagnose() returns derived fields
echo "Test 1: MercadoLivreClient::diagnose() derived fields...\n";
$client = new \App\Services\MercadoLivreClient(null);
$diagnosis = $client->diagnose();

$requiredFields = ['connected', 'token_valid', 'public_api', 'auth_ok', 'api_accessible', 'checks'];
$missing = [];
foreach ($requiredFields as $field) {
    if (!array_key_exists($field, $diagnosis)) {
        $missing[] = $field;
    }
}

if (empty($missing)) {
    echo "  ✅ All derived fields present\n";
    echo "     - token_valid: " . var_export($diagnosis['token_valid'], true) . "\n";
    echo "     - public_api: " . var_export($diagnosis['public_api'], true) . "\n";
    echo "     - auth_ok: " . var_export($diagnosis['auth_ok'], true) . "\n";
} else {
    echo "  ❌ Missing fields: " . implode(', ', $missing) . "\n";
    exit(1);
}

// Test 2: getMercadoLivreHealth() doesn't fail due to missing fields
echo "\nTest 2: MercadoLivreAIIntegrationService health mapping...\n";
try {
    $service = new \App\Services\MercadoLivre\MercadoLivreAIIntegrationService(0);
    $health = $service->getHealthStatus();
    
    $mlHealth = $health['ml'] ?? [];
    $requiredMLFields = ['connected', 'token_valid', 'public_api', 'auth_ok', 'items_count', 'seller_id', 'token_source', 'db_unavailable', 'checks', 'account_id', 'mode'];
    
    $missingML = [];
    foreach ($requiredMLFields as $field) {
        if (!array_key_exists($field, $mlHealth)) {
            $missingML[] = $field;
        }
    }
    
    if (empty($missingML)) {
        echo "  ✅ All health fields present\n";
        echo "     - connected: " . var_export($mlHealth['connected'], true) . "\n";
        echo "     - token_valid: " . var_export($mlHealth['token_valid'], true) . "\n";
        echo "     - public_api: " . var_export($mlHealth['public_api'], true) . "\n";
        echo "     - auth_ok: " . var_export($mlHealth['auth_ok'], true) . "\n";
        echo "     - token_source: " . var_export($mlHealth['token_source'], true) . "\n";
        echo "     - mode: " . var_export($mlHealth['mode'], true) . "\n";
    } else {
        echo "  ❌ Missing health fields: " . implode(', ', $missingML) . "\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n═══════════════════════════════════════════════════════════════════\n";
echo "  ✅ All tests passed! Health mapping fix is working.\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

exit(0);
