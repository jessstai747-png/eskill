<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\AI\Providers\OpenAIProvider;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "Checking OpenAI Config...\n";
$key = $_ENV['OPENAI_API_KEY'] ?? '';
echo "API Key Length: " . strlen($key) . "\n";
if (strlen($key) < 5) {
    echo "ERROR: OPENAI_API_KEY is too short or missing.\n";
}

$bgKey = $_ENV['REMOVE_BG_API_KEY'] ?? '';
echo "Remove.bg Key Length: " . strlen($bgKey) . "\n";
if (strlen($bgKey) < 5) {
    echo "ERROR: REMOVE_BG_API_KEY is too short or missing.\n";
}

$provider = new OpenAIProvider([]);

echo "Attempting simple chat...\n";
$response = $provider->chat([
    ['role' => 'user', 'content' => 'Hello']
], ['model' => 'gpt-3.5-turbo']);

print_r($response);
