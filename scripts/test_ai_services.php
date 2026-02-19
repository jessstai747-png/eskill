<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\AI\SEO\AIInsightsService;
use App\Services\AI\SEO\AIImageAnalyzer;
use App\Services\AI\SEO\AIPricingOptimizer;
use App\Services\AI\SEO\AIChatbotService;
use Dotenv\Dotenv;

// Load env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "Environment loaded.\n";
echo "OPENAI_API_KEY from \$_ENV: " . ($_ENV['OPENAI_API_KEY'] ?? 'MISSING') . "\n";
echo "OPENAI_API_KEY from \$_SERVER: " . ($_SERVER['OPENAI_API_KEY'] ?? 'MISSING') . "\n";
echo "OPENAI_API_KEY from getenv: " . (getenv('OPENAI_API_KEY') ?: 'MISSING') . "\n";

$accountId = 1; // Dummy account ID

try {
    echo "\nTesting AIInsightsService...\n";
    $service = new AIInsightsService($accountId);
    echo "AIInsightsService instantiated successfully.\n";
} catch (\Throwable $e) {
    echo "AIInsightsService FAILED: " . $e->getMessage() . "\n";
}

try {
    echo "\nTesting AIImageAnalyzer...\n";
    $service = new AIImageAnalyzer($accountId);
    echo "AIImageAnalyzer instantiated successfully.\n";
} catch (\Throwable $e) {
    echo "AIImageAnalyzer FAILED: " . $e->getMessage() . "\n";
}

try {
    echo "\nTesting AIPricingOptimizer...\n";
    $service = new AIPricingOptimizer($accountId);
    echo "AIPricingOptimizer instantiated successfully.\n";
} catch (\Throwable $e) {
    echo "AIPricingOptimizer FAILED: " . $e->getMessage() . "\n";
}

try {
    echo "\nTesting AIChatbotService...\n";
    $service = new AIChatbotService($accountId);
    echo "AIChatbotService instantiated successfully.\n";
} catch (\Throwable $e) {
    echo "AIChatbotService FAILED: " . $e->getMessage() . "\n";
}
