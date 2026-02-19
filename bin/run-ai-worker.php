#!/usr/bin/env php
<?php

/**
 * 🤖 AI Optimization Worker Runner
 * 
 * Background worker for processing AI optimization jobs
 * 
 * Usage:
 *   php bin/run-ai-worker.php [options]
 * 
 * Options:
 *   --batch=N      Process N jobs per batch (default: 10)
 *   --sleep=N      Sleep N seconds between batches (default: 5)
 *   --once         Process one batch and exit
 *   --daemon       Run as daemon (default)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Jobs\AIOptimizationWorker;

// Load environment
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

// Parse command line options
$options = getopt('', ['batch::', 'sleep::', 'once', 'daemon', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
🤖 AI Optimization Worker Runner

Usage:
  php bin/run-ai-worker.php [options]

Options:
  --batch=N      Process N jobs per batch (default: 10)
  --sleep=N      Sleep N seconds between batches (default: 5)
  --once         Process one batch and exit
  --daemon       Run as daemon (default)
  --help         Show this help message

Examples:
  php bin/run-ai-worker.php                    # Run as daemon with defaults
  php bin/run-ai-worker.php --batch=20         # Process 20 jobs per batch
  php bin/run-ai-worker.php --once             # Process one batch and exit
  php bin/run-ai-worker.php --sleep=10         # Wait 10s between batches

HELP;
    exit(0);
}

$batchSize = isset($options['batch']) ? (int) $options['batch'] : 10;
$sleepInterval = isset($options['sleep']) ? (int) $options['sleep'] : 5;
$runOnce = isset($options['once']);

echo "🤖 AI Optimization Worker Runner\n";
echo "=================================\n";
echo "Batch size: {$batchSize}\n";
echo "Sleep interval: {$sleepInterval}s\n";
echo "Mode: " . ($runOnce ? 'Single batch' : 'Daemon') . "\n";
echo "=================================\n\n";

$worker = new AIOptimizationWorker($batchSize, $sleepInterval);

if ($runOnce) {
    $processed = $worker->processBatch();
    echo "\n✅ Processed {$processed} jobs\n";
    exit(0);
}

// Run as daemon
$worker->run();
