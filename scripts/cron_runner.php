<?php
/**
 * Cron Runner Wrapper
 * 
 * Executes the main sync script and ensures logs are written 
 * where the dashboard expects them (storage/logs/cron_sync.log).
 */

$rootDir = dirname(__DIR__);
$logFile = $rootDir . '/storage/logs/cron_sync.log';
$script = $rootDir . '/scripts/ml_sync_all.php';

// Rotate log if too big (> 5MB)
if (file_exists($logFile) && filesize($logFile) > 5 * 1024 * 1024) {
    rename($logFile, $logFile . '.old');
}

$timestamp = date('Y-m-d H:i:s');
$output = "[$timestamp] Starting Cron Runner...\n";

// Execute sync
// Capture both stdout and stderr
$command = "php $script --force 2>&1";
exec($command, $lines, $returnCode);
$output .= implode("\n", $lines);

// Execute Auto-Answer Bot
$output .= "\n[$timestamp] Running AutoAnswerJob...\n";
// Assuming we have a script wrapper or run via code snippet
// For simplicity, we'll include the class/job here or run a dedicated script
// Let's create a dedicated runner script for jobs if needed, or simple inline:
$jobCommand = "php -r \"require '$rootDir/vendor/autoload.php'; (new App\Jobs\AutoAnswerJob())->run();\" 2>&1";
exec($jobCommand, $jobLines, $jobReturn);
$output .= implode("\n", $jobLines);

// Execute Webhook Processor Queue (Real-Time)
$output .= "\n[$timestamp] Processing Webhook Queue...\n";
$webhookCommand = "php -r \"require '$rootDir/vendor/autoload.php'; (new App\Services\WebhookProcessorService())->processQueue();\" 2>&1";
exec($webhookCommand, $webhookLines, $webhookReturn);
$output .= implode("\n", $webhookLines);

// Execute AI Agents (Phase 20)
$output .= "\n[$timestamp] Running AgentJob...\n";
$agentCommand = "php -r \"require '$rootDir/vendor/autoload.php'; (new App\Jobs\AgentJob())->run();\" 2>&1";
exec($agentCommand, $agentLines, $agentReturn);
$output .= implode("\n", $agentLines);

$output .= "\n[$timestamp] Finished with exit code: $returnCode\n";
$output .= "----------------------------------------\n";

// Write to log file
file_put_contents($logFile, $output, FILE_APPEND);

echo "Cron Runner finished. Log written to: $logFile\n";
