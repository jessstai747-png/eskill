<?php

/**
 * Test Claude API Connection
 * 
 * Verifica se a API da Anthropic está configurada e acessível.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use App\Services\ClaudeClient;

echo "🔌 Testing Claude API Connection\n";
echo "================================\n\n";

try {
    // Check if API key is configured
    if (empty($_ENV['ANTHROPIC_API_KEY'])) {
        echo "❌ ANTHROPIC_API_KEY not found in .env\n\n";
        echo "To use Claude API, add to .env:\n";
        echo "ANTHROPIC_API_KEY=your_api_key_here\n\n";
        exit(1);
    }

    echo "✓ API key found in environment\n";
    echo "  Length: " . strlen($_ENV['ANTHROPIC_API_KEY']) . " characters\n\n";

    // Create client
    echo "Creating Claude client...\n";
    $client = new ClaudeClient();
    echo "✓ Client created successfully\n\n";

    // Test connection
    echo "Testing API connection...\n";
    $connected = $client->testConnection();

    if ($connected) {
        echo "✅ Connection successful!\n\n";
        
        // Test simple completion
        echo "Testing message completion...\n";
        $response = $client->complete([
            ['role' => 'user', 'content' => 'What is 2+2? Answer with just the number.']
        ], [
            'max_tokens' => 10,
        ]);

        $answer = $response['content'][0]['text'] ?? '';
        echo "✓ Response: {$answer}\n";

        // Show usage
        $usage = $client->getUsageStats($response);
        echo "\n📊 Token Usage:\n";
        echo "   Input tokens:  {$usage['input_tokens']}\n";
        echo "   Output tokens: {$usage['output_tokens']}\n";

        echo "\n✅ Claude API is ready to use!\n";
        exit(0);

    } else {
        echo "❌ Connection failed\n\n";
        echo "Possible issues:\n";
        echo "- Invalid API key\n";
        echo "- Network connectivity\n";
        echo "- API quota exceeded\n";
        exit(1);
    }

} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
