#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Quick Setup Script for AI Optimization System
 * Automates initial configuration and testing
 */

echo "🚀 AI Optimization System - Quick Setup\n";
echo "==========================================\n\n";

$steps = [
    'check_requirements',
    'check_database',
    'check_migrations',
    'check_env',
    'test_api',
    'show_summary'
];

$results = [];

foreach ($steps as $step) {
    $results[$step] = call_user_func($step);
    if (!$results[$step]) {
        echo "\n❌ Setup incomplete. Please fix the errors above.\n";
        exit(1);
    }
}

echo "\n✅ Setup complete! System is ready to use.\n";
echo "\n📍 Next steps:\n";
echo "   1. Access: http://localhost/dashboard/ai-optimization\n";
echo "   2. Add API keys in Settings\n";
echo "   3. Start optimizing!\n\n";

// Step implementations

function check_requirements()
{
    echo "📋 Step 1: Checking requirements...\n";

    $required = [
        'PHP' => PHP_VERSION_ID >= 80000,
        'curl' => extension_loaded('curl'),
        'json' => extension_loaded('json'),
        'mbstring' => extension_loaded('mbstring'),
        'pdo' => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql')
    ];

    $ok = true;
    foreach ($required as $name => $check) {
        if ($check) {
            echo "  ✓ $name\n";
        } else {
            echo "  ✗ $name - MISSING\n";
            $ok = false;
        }
    }

    echo "\n";
    return $ok;
}

function check_database()
{
    echo "🗄️  Step 2: Checking database connection...\n";

    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        if (class_exists(\Dotenv\Dotenv::class)) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
            $dotenv->safeLoad();
        }

        $db = App\Database::getInstance();
        echo "  ✓ Database connected\n\n";
        return true;
    } catch (Exception $e) {
        echo "  ✗ Database connection failed: " . $e->getMessage() . "\n\n";
        return false;
    }
}

function check_migrations()
{
    echo "📊 Step 3: Checking AI optimization tables...\n";

    try {
        $db = App\Database::getInstance();

        $tables = [
            'ai_optimization_queue',
            'ai_ab_tests',
            'ai_ab_test_metrics',
            'ai_audit_log',
            'ai_performance_tracking'
        ];

        $missing = [];
        foreach ($tables as $table) {
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS cnt
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = :table"
            );
            $stmt->execute(['table' => $table]);
            $result = (int)($stmt->fetch()['cnt'] ?? 0);
            if ($result > 0) {
                echo "  ✓ $table\n";
            } else {
                echo "  ✗ $table - MISSING\n";
                $missing[] = $table;
            }
        }

        if (!empty($missing)) {
            echo "\n  Run migrations: php scripts/migrate.php\n\n";
            return false;
        }

        echo "\n";
        return true;
    } catch (Exception $e) {
        echo "  ✗ Error checking tables: " . $e->getMessage() . "\n\n";
        return false;
    }
}

function check_env()
{
    echo "⚙️  Step 4: Checking configuration...\n";

    $envFile = __DIR__ . '/../.env.ai';
    $exampleFile = __DIR__ . '/../.env.ai.example';

    if (!file_exists($envFile)) {
        if (file_exists($exampleFile)) {
            echo "  Creating .env.ai from example...\n";
            copy($exampleFile, $envFile);
            echo "  ✓ .env.ai created\n";
            echo "  ⚠️  Please edit .env.ai and add your API keys\n\n";
        } else {
            echo "  ✗ .env.ai.example not found\n\n";
            return false;
        }
    } else {
        echo "  ✓ .env.ai exists\n";

        // Check for API keys
        $content = file_get_contents($envFile);
        $hasOpenAI = strpos($content, 'OPENAI_API_KEY=sk-') !== false;
        $hasClaude = strpos($content, 'ANTHROPIC_API_KEY=sk-ant-') !== false;

        if ($hasOpenAI || $hasClaude) {
            echo "  ✓ API keys configured\n";
        } else {
            echo "  ⚠️  No API keys found in .env.ai\n";
            echo "     Add at least one: OPENAI_API_KEY or ANTHROPIC_API_KEY\n";
        }
    }

    echo "\n";
    return true;
}

function test_api()
{
    echo "🧪 Step 5: Testing API endpoints...\n";

    $endpoints = [
        '/api/ai/info' => 'GET',
        '/api/ai/queue/stats' => 'GET',
    ];

    $baseUrl = 'http://localhost';
    $ok = true;

    foreach ($endpoints as $endpoint => $method) {
        $url = $baseUrl . $endpoint;

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                echo "  ✓ $endpoint\n";
            } else {
                echo "  ⚠️  $endpoint - HTTP $httpCode\n";
            }
        } catch (Exception $e) {
            echo "  ⚠️  $endpoint - " . $e->getMessage() . "\n";
            $ok = false;
        }
    }

    echo "\n";
    return true; // Don't fail on API test errors
}

function show_summary()
{
    echo "📈 Summary\n";
    echo "─────────────────────────────────────────\n";

    try {
        $db = App\Database::getInstance();

        // Count queue items
        $queueCount = $db->query("SELECT COUNT(*) as cnt FROM ai_optimization_queue")->fetch()['cnt'] ?? 0;
        echo "  Queue items: $queueCount\n";

        // Count audit log
        $auditCount = $db->query("SELECT COUNT(*) as cnt FROM ai_audit_log")->fetch()['cnt'] ?? 0;
        echo "  Optimizations done: $auditCount\n";

        // Check worker
        exec('ps aux | grep "[a]i-worker.php"', $output);
        if (!empty($output)) {
            echo "  Worker status: ✓ Running\n";
        } else {
            echo "  Worker status: ⚠️  Not running (optional)\n";
            echo "     Start with: php bin/ai-worker.php &\n";
        }
    } catch (Exception $e) {
        echo "  Unable to fetch stats\n";
    }

    echo "\n";
    return true;
}
