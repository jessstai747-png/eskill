#!/usr/bin/env php
<?php
/**
 * Background Worker for AI Optimization Queue
 * Uses the 'Agent Harness' for robust execution.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\AI\Core\Harness\AgentHarness;

// Load environment variables using Dotenv
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

// Instantiate and run the Harness
$harness = new AgentHarness();
$harness->run();
