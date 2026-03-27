#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Tech Sheet Scheduler Worker
 * 
 * Executa jobs agendados do Tech Sheet automaticamente
 * 
 * Usage:
 *   php bin/tech-sheet-scheduler.php --account=123
 *   php bin/tech-sheet-scheduler.php --account=123 --job-type=auto_optimizer
 *   php bin/tech-sheet-scheduler.php --account=123 --dry-run
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\TechSheetSchedulerService;

// Parse options
$options = getopt('', ['account:', 'job-type::', 'dry-run', 'help']);

if (isset($options['help']) || !isset($options['account'])) {
    echo "\n";
    echo "Tech Sheet Scheduler Worker\n";
    echo "============================\n\n";
    echo "Usage:\n";
    echo "  php bin/tech-sheet-scheduler.php --account=ACCOUNT_ID [options]\n\n";
    echo "Options:\n";
    echo "  --account=ID       Account ID (required)\n";
    echo "  --job-type=TYPE    Run only specific job type\n";
    echo "  --dry-run          Check jobs without executing\n";
    echo "  --help             Show this help\n\n";
    echo "Job Types:\n";
    echo "  auto_optimizer     Auto-optimize items\n";
    echo "  email_report       Send daily email report\n";
    echo "  batch_analysis     Batch analysis of items\n";
    echo "  cleanup            Cleanup old data\n\n";
    exit(0);
}

$accountId = (int) $options['account'];
$jobType = $options['job-type'] ?? null;
$dryRun = isset($options['dry-run']);

// Colors
function colorize($text, $color) {
    $colors = [
        'green' => "\033[0;32m",
        'yellow' => "\033[1;33m",
        'red' => "\033[0;31m",
        'blue' => "\033[0;34m",
        'reset' => "\033[0m",
    ];
    
    return $colors[$color] . $text . $colors['reset'];
}

echo "\n";
echo colorize("╔════════════════════════════════════════╗\n", 'blue');
echo colorize("║  Tech Sheet Scheduler Worker          ║\n", 'blue');
echo colorize("╚════════════════════════════════════════╝\n", 'blue');
echo "\n";

try {
    $scheduler = new TechSheetSchedulerService($accountId);
    
    // Check due jobs
    echo colorize("→ Checking scheduled jobs...\n", 'yellow');
    $dueJobs = $scheduler->checkDueJobs();
    
    if (empty($dueJobs)) {
        echo colorize("✓ No jobs due for execution\n", 'green');
        exit(0);
    }
    
    echo colorize("✓ Found " . count($dueJobs) . " job(s) ready to run\n\n", 'green');
    
    // List jobs
    $jobs = $scheduler->listJobs([
        'status' => 'active'
    ]);
    
    $jobsToRun = array_filter($jobs, function($job) use ($dueJobs, $jobType) {
        $isDue = in_array($job['id'], $dueJobs);
        $matchesType = !$jobType || $job['job_type'] === $jobType;
        return $isDue && $matchesType;
    });
    
    if (empty($jobsToRun)) {
        echo colorize("✓ No matching jobs to run\n", 'green');
        exit(0);
    }
    
    foreach ($jobsToRun as $job) {
        echo "┌─────────────────────────────────────\n";
        echo "│ Job #" . $job['id'] . " - " . strtoupper($job['job_type']) . "\n";
        echo "├─────────────────────────────────────\n";
        echo "│ Schedule: " . $job['schedule_cron'] . "\n";
        echo "│ Last Run: " . ($job['last_run_at'] ?? 'Never') . "\n";
        echo "│ Run Count: " . $job['run_count'] . "\n";
        echo "└─────────────────────────────────────\n\n";
        
        if ($dryRun) {
            echo colorize("  [DRY RUN] Would execute job #{$job['id']}\n", 'yellow');
            continue;
        }
        
        echo colorize("  → Executing...\n", 'yellow');
        $startTime = microtime(true);
        
        try {
            $result = $scheduler->runJob($job['id']);
            $duration = microtime(true) - $startTime;
            
            if ($result['success']) {
                echo colorize("  ✓ Success", 'green');
                echo " (duration: " . round($duration, 2) . "s)\n";
                
                // Show details
                if (isset($result['processed'])) {
                    echo "    Processed: {$result['processed']}\n";
                }
                if (isset($result['success'])) {
                    echo "    Success: {$result['success']}\n";
                }
                if (isset($result['failed'])) {
                    echo "    Failed: {$result['failed']}\n";
                }
                
            } else {
                echo colorize("  ✗ Failed: " . ($result['error'] ?? 'Unknown error') . "\n", 'red');
            }
            
        } catch (\Exception $e) {
            echo colorize("  ✗ Error: " . $e->getMessage() . "\n", 'red');
        }
        
        echo "\n";
    }
    
    echo colorize("✓ Scheduler run completed\n", 'green');
    
} catch (\Exception $e) {
    echo colorize("✗ Error: " . $e->getMessage() . "\n", 'red');
    exit(1);
}

echo "\n";
