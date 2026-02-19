<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Mock Environment
$_ENV['REDIS_HOST'] = '127.0.0.1';
$_ENV['REDIS_PORT'] = 6379;

echo "--- Testing Queue System ---\n";

use App\Services\QueueService;
use App\Services\JobService;

// 1. Test Queue Push/Pop
$queue = new QueueService();
$jobId = uniqid();
$payload = ['job_id' => 123, 'test' => true];

echo "Pushing job...\n";
$id = $queue->push('test_job', $payload);
echo "Pushed ID: $id\n";

echo "Popping job...\n";
$popped = $queue->pop('default', 2);
if ($popped && $popped['id'] === $id) {
    echo "SUCCESS: Job popped correctly.\n";
} else {
    echo "FAIL: Job pop mismatch or timeout.\n";
    print_r($popped);
}

// 2. Test Job Service Integration (Dispatch)
// Needs DB connection, might fail if credentials are not accessible in this context without .env
// We assume migration fixed .env loading, so if we load .env here it should work.

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

try {
    $jobService = new JobService();
    echo "Dispatching 'ai_generation' job via JobService...\n";
    $dbJobId = $jobService->dispatch('ai_generation', ['prompt' => 'test', 'system' => 'test']);
    echo "Job dispatched to DB ID: $dbJobId\n";
    
    // Redis should have it now
    $popped2 = $queue->pop('default', 2);
    if ($popped2 && $popped2['payload']['job_id'] == $dbJobId) {
        echo "SUCCESS: JobService pushed to Redis automatically.\n";
    } else {
        echo "FAIL: JobService did not push to Redis.\n";
        print_r($popped2);
    }

} catch (Exception $e) {
    echo "DB/JobService Error: " . $e->getMessage() . "\n";
}
