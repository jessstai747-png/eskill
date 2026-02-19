<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "--- Testing Performance Metrics Service ---\n";

use App\Services\PerformanceMetricsService;

try {
    $service = new PerformanceMetricsService();
    echo "Service instantiated successfully.\n";
    
    echo "\nFetching metrics...\n";
    $metrics = $service->getMetrics();
    
    echo "\n=== METRICS SUMMARY ===\n";
    
    // Cache
    if (isset($metrics['cache'])) {
        echo "\nCache:\n";
        echo "  Hit Rate: " . ($metrics['cache']['hit_rate'] ?? 'N/A') . "\n";
        echo "  Connection: " . ($metrics['cache']['connection'] ?? 'N/A') . "\n";
    }
    
    // Queue
    if (isset($metrics['queue'])) {
        echo "\nQueue:\n";
        echo "  Pending: " . ($metrics['queue']['pending'] ?? 0) . "\n";
        echo "  Processing: " . ($metrics['queue']['processing'] ?? 0) . "\n";
        echo "  Completed: " . ($metrics['queue']['completed'] ?? 0) . "\n";
        echo "  Failed: " . ($metrics['queue']['failed'] ?? 0) . "\n";
    }
    
    // LLM
    if (isset($metrics['llm']['today'])) {
        echo "\nLLM (Today):\n";
        echo "  Calls: " . ($metrics['llm']['today']['calls'] ?? 0) . "\n";
        echo "  Tokens: " . number_format($metrics['llm']['today']['tokens'] ?? 0) . "\n";
        echo "  Cost: $" . ($metrics['llm']['today']['cost_usd'] ?? 0) . "\n";
        echo "  Avg Duration: " . ($metrics['llm']['today']['avg_duration_ms'] ?? 0) . "ms\n";
    }
    
    // Database
    if (isset($metrics['database'])) {
        echo "\nDatabase:\n";
        echo "  Status: " . ($metrics['database']['status'] ?? 'N/A') . "\n";
        echo "  Connections: " . ($metrics['database']['connections'] ?? 0) . "\n";
    }
    
    // System
    if (isset($metrics['system'])) {
        echo "\nSystem:\n";
        echo "  Memory Usage: " . round(($metrics['system']['memory_usage'] ?? 0) / 1024 / 1024, 2) . " MB\n";
        echo "  PHP Version: " . ($metrics['system']['php_version'] ?? 'N/A') . "\n";
    }
    
    echo "\n✅ SUCCESS: All metrics retrieved successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
