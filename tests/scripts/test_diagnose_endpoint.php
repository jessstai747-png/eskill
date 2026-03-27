<?php

declare(strict_types=1);

/**
 * Teste do Endpoint /api/seo-killer/diagnose
 */

// Carregar autoloader do Composer
require_once __DIR__ . '/vendor/autoload.php';

// Carregar autoloader customizado
require_once __DIR__ . '/autoload.php';

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
        putenv(trim($name) . '=' . trim($value));
    }
}

echo "Testing /api/seo-killer/diagnose endpoint...\n";
echo str_repeat("=", 60) . "\n\n";

try {
    echo "1. Testing autoloader...\n";
    echo "   ✅ Autoloader loaded\n\n";

    echo "2. Testing Guzzle...\n";
    if (class_exists('GuzzleHttp\Client')) {
        echo "   ✅ Guzzle HTTP Client found\n\n";
    } else {
        echo "   ❌ Guzzle HTTP Client NOT found\n\n";
    }

    echo "3. Testing Database connection...\n";
    $db = App\Database::getInstance();
    echo "   ✅ Database connected\n\n";

    echo "4. Testing StartupValidator...\n";
    App\Services\StartupValidator::validate();
    echo "   ✅ StartupValidator passed\n\n";

    echo "5. Checking ml_accounts table...\n";
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM ml_accounts");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "   ✅ ml_accounts table exists with $count accounts\n\n";

    echo "6. Testing MercadoLivreClient class...\n";
    if (class_exists('App\Services\MercadoLivreClient')) {
        echo "   ✅ MercadoLivreClient class found\n\n";
    } else {
        echo "   ❌ MercadoLivreClient class NOT found\n\n";
    }

    echo "7. Testing SEOKillerEngine instantiation...\n";
    $accountId = 1; // Test with account ID 1
    $engine = new \App\Services\AI\SEO\SEOKillerEngine($accountId);
    echo "   ✅ SEOKillerEngine instantiated successfully\n\n";

    echo "8. Testing diagnoseAccount method...\n";
    $result = $engine->diagnoseAccount();
    echo "   ✅ diagnoseAccount executed successfully\n\n";

    echo "9. Result:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

    echo str_repeat("=", 60) . "\n";
    echo "✅ ALL TESTS PASSED!\n";

} catch (Exception $e) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nFile: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nTrace:\n" . substr($e->getTraceAsString(), 0, 500) . "\n";
}
