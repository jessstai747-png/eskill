<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ReportService;
use App\Controllers\ReportController;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "=== Verifying Phase 8: Automation & Reporting ===\n\n";

// 1. Reporting
echo "[Reporting] Generating Sales PDF... ";
try {
    $reportService = new ReportService();
    $url = $reportService->generateSalesReport('2023-12-01', '2023-12-31');
    if (file_exists(__DIR__ . '/../public' . $url)) {
        echo "OK (Created: $url)\n";
    } else {
        echo "FAIL (File not found)\n";
    }
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

// 2. Auto-Answer
echo "\n[AutoScanner] Running AutoAnswerJob... \n";
try {
    $job = new App\Jobs\AutoAnswerJob();
    // Capture output
    ob_start();
    $job->run();
    $output = ob_get_clean();
    echo $output;
    
    if (strpos($output, 'Starting AutoAnswerJob') !== false) {
        echo "OK (Job executed)\n";
    } else {
        echo "FAIL (Job execution unclear)\n";
    }
} catch (\Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

echo "\n=== Verification Complete ===\n";
