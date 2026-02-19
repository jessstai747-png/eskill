<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

echo "Checking Environment Variables...\n";
if (!empty($_ENV['ANTHROPIC_API_KEY'])) {
    $len = strlen($_ENV['ANTHROPIC_API_KEY']);
    $first4 = substr($_ENV['ANTHROPIC_API_KEY'], 0, 4);
    echo "✅ ANTHROPIC_API_KEY found! Length: $len. Starts with: $first4***\n";
    
    // Check if it's the example placeholder
    if (strpos($_ENV['ANTHROPIC_API_KEY'], 'sk-ant-api03-xxxxx') !== false) {
        echo "⚠️ WARNING: It looks like the example key!\n";
    }
} else {
    echo "❌ ANTHROPIC_API_KEY is missing or empty.\n";
}
