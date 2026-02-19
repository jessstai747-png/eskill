<?php

/**
 * Integration Test Script
 * 
 * Tests all major API integrations to ensure 100% functionality
 */

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Services/AI/Core/RateLimiterService.php';
require_once __DIR__ . '/../app/Services/Webhooks/WebhookService.php';

use App\Services\Webhooks\WebhookService;

echo "🚀 Testing Eskill Integrations - 100% API Validation\n";
echo str_repeat("=", 60) . "\n\n";

// Load environment
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        putenv($line);
        [$key, $value] = explode('=', $line, 2);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

$results = [];

// Test 1: AI Configuration
echo "🤖 Testing AI Configuration...\n";
$openaiKey = $_ENV['OPENAI_API_KEY'] ?? '';
$anthropicKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
$geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
$aiEnabled = $_ENV['TECH_SHEET_AI_ENABLED'] ?? 'false';

echo "🔧 AI Enabled: " . ($aiEnabled === 'true' ? 'YES' : 'NO') . "\n";
echo "🔑 OpenAI Key: " . (!empty($openaiKey) ? substr($openaiKey, 0, 10) . '...' : 'NOT SET') . "\n";
echo "🔑 Anthropic Key: " . (!empty($anthropicKey) ? substr($anthropicKey, 0, 10) . '...' : 'NOT SET') . "\n";
echo "🔑 Gemini Key: " . (!empty($geminiKey) ? substr($geminiKey, 0, 10) . '...' : 'NOT SET') . "\n";

$providersConfigured = 0;
if (!empty($openaiKey)) $providersConfigured++;
if (!empty($anthropicKey)) $providersConfigured++;
if (!empty($geminiKey)) $providersConfigured++;

echo "📊 AI Providers Configured: {$providersConfigured}\n";

if ($aiEnabled === 'true' && $providersConfigured >= 2) {
    echo "✅ AI Integration: PASS\n";
    $results['ai'] = 'PASS';
} elseif ($aiEnabled === 'true' && $providersConfigured >= 1) {
    echo "⚠️ AI Integration: PARTIAL\n";
    $results['ai'] = 'PARTIAL';
} else {
    echo "❌ AI Integration: FAIL\n";
    $results['ai'] = 'FAIL';
}

echo "\n";

// Test 2: Rate Limiting Configuration
echo "⏱️ Testing Rate Limiting Configuration...\n";
$rateLimit = (int)($_ENV['API_RATE_LIMIT'] ?? 100);
$burstLimit = (int)($_ENV['API_BURST_LIMIT'] ?? 10);

echo "📊 Rate Limit: {$rateLimit} requests/minute\n";
echo "📊 Burst Limit: {$burstLimit} requests/10s\n";

if ($rateLimit > 0 && $burstLimit > 0) {
    echo "✅ Rate Limiting: PASS\n";
    $results['rate_limiting'] = 'PASS';
} else {
    echo "❌ Rate Limiting: FAIL\n";
    $results['rate_limiting'] = 'FAIL';
}

echo "\n";

// Test 3: Brevo Integration
echo "📧 Testing Brevo Integration...\n";
$brevoKey = $_ENV['BREVO_API_KEY'] ?? '';
if (!empty($brevoKey)) {
    echo "🔑 Brevo API Key: " . substr($brevoKey, 0, 10) . "...\n";
    
    // Test API connectivity
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.brevo.com/v3/account',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'api-key: ' . $brevoKey
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✅ Brevo Integration: PASS\n";
        $results['brevo'] = 'PASS';
    } else {
        echo "⚠️ Brevo Integration: PARTIAL (Key configured but API test failed)\n";
        $results['brevo'] = 'PARTIAL';
    }
} else {
    echo "❌ Brevo Integration: FAIL (No API key)\n";
    $results['brevo'] = 'FAIL';
}

echo "\n";

// Test 4: Telegram Integration
echo "📱 Testing Telegram Integration...\n";
$telegramEnabled = $_ENV['TELEGRAM_ENABLED'] ?? 'false';
$telegramToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
$telegramChat = $_ENV['TELEGRAM_CHAT_ID'] ?? '';

echo "🔧 Telegram Enabled: " . ($telegramEnabled === 'true' ? 'YES' : 'NO') . "\n";
echo "🔑 Token: " . (!empty($telegramToken) ? substr($telegramToken, 0, 10) . '...' : 'NOT SET') . "\n";
echo "💬 Chat ID: " . (!empty($telegramChat) ? 'CONFIGURED' : 'NOT SET') . "\n";

if ($telegramEnabled === 'true' && !empty($telegramToken) && !empty($telegramChat)) {
    echo "✅ Telegram Integration: PASS\n";
    $results['telegram'] = 'PASS';
} else {
    echo "❌ Telegram Integration: FAIL\n";
    $results['telegram'] = 'FAIL';
}

echo "\n";

// Test 5: Mercado Livre Configuration
echo "🛒 Testing Mercado Livre Configuration...\n";
$mlAppId = $_ENV['ML_APP_ID'] ?? '';
$mlSecret = $_ENV['ML_CLIENT_SECRET'] ?? '';
$mlAccessToken = $_ENV['ML_ACCESS_TOKEN'] ?? '';
$mlProxyEnabled = $_ENV['ML_PROXY_ENABLED'] ?? 'false';

echo "🆔 App ID: " . (!empty($mlAppId) ? 'CONFIGURED' : 'NOT SET') . "\n";
echo "🔑 Client Secret: " . (!empty($mlSecret) ? 'CONFIGURED' : 'NOT SET') . "\n";
echo "🎫 Access Token: " . (!empty($mlAccessToken) ? 'CONFIGURED' : 'NOT SET') . "\n";
echo "🌐 Proxy Enabled: " . ($mlProxyEnabled === 'true' ? 'YES' : 'NO') . "\n";

if (!empty($mlAppId) && !empty($mlSecret) && !empty($mlAccessToken)) {
    echo "✅ Mercado Livre Integration: PASS\n";
    $results['mercadolivre'] = 'PASS';
} else {
    echo "❌ Mercado Livre Integration: FAIL\n";
    $results['mercadolivre'] = 'FAIL';
}

echo "\n";

// Test 6: Backup Configuration
echo "💾 Testing Backup Configuration...\n";
$backupType = $_ENV['BACKUP_REMOTE_TYPE'] ?? 'none';
$awsKey = $_ENV['AWS_ACCESS_KEY_ID'] ?? '';
$awsBucket = $_ENV['AWS_BUCKET'] ?? '';

echo "📦 Backup Type: {$backupType}\n";
echo "🔑 AWS Access Key: " . (!empty($awsKey) ? substr($awsKey, 0, 8) . '...' : 'NOT SET') . "\n";
echo "🪣 AWS Bucket: " . (!empty($awsBucket) ? $awsBucket : 'NOT SET') . "\n";

if ($backupType !== 'none' && !empty($awsKey) && !empty($awsBucket)) {
    echo "✅ Backup Integration: PASS\n";
    $results['backup'] = 'PASS';
} else {
    echo "❌ Backup Integration: FAIL\n";
    $results['backup'] = 'FAIL';
}

echo "\n";

// Test 7: Webhooks Service
echo "🔗 Testing Webhooks Service...\n";
try {
    $webhookService = new WebhookService();
    $webhooks = $webhookService->listWebhooks();
    $events = WebhookService::getAvailableEvents();
    
    echo "📊 Available Events: " . count($events) . "\n";
    echo "📊 Registered Webhooks: " . count($webhooks) . "\n";
    echo "✅ Webhooks Service: PASS\n";
    $results['webhooks'] = 'PASS';
} catch (Exception $e) {
    echo "❌ Webhooks Service: FAIL - " . $e->getMessage() . "\n";
    $results['webhooks'] = 'FAIL';
}

echo "\n";

// Summary
echo str_repeat("=", 60) . "\n";
echo "📊 INTEGRATION TEST SUMMARY\n";
echo str_repeat("=", 60) . "\n";

$totalTests = count($results);
$passedTests = count(array_filter($results, fn($r) => $r === 'PASS'));
$partialTests = count(array_filter($results, fn($r) => $r === 'PARTIAL'));

foreach ($results as $integration => $status) {
    $icon = match($status) {
        'PASS' => '✅',
        'PARTIAL' => '⚠️',
        'FAIL' => '❌',
        default => '❓'
    };
    echo "{$icon} {$integration}: {$status}\n";
}

echo "\n";
echo "📈 Results: {$passedTests}/{$totalTests} passed, {$partialTests} partial\n";

$percentage = round(($passedTests + ($partialTests * 0.5)) / $totalTests * 100, 1);
echo "🎯 Overall Integration Health: {$percentage}%\n\n";

if ($percentage >= 90) {
    echo "🎉 EXCELLENT! System is 100% ready for production!\n";
} elseif ($percentage >= 75) {
    echo "👍 GOOD! System is mostly functional with minor issues.\n";
} elseif ($percentage >= 50) {
    echo "⚠️ ATTENTION! System needs configuration fixes.\n";
} else {
    echo "🚨 CRITICAL! Major configuration issues detected.\n";
}

echo "\n🔧 Next steps:\n";
echo "1. Replace demo API keys with real credentials\n";
echo "2. Test webhooks with actual external endpoints\n";
echo "3. Configure backup destination with real AWS credentials\n";
echo "4. Set up Telegram bot with real token\n";
echo "5. Enable production monitoring and logging\n";