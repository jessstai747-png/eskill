<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\AuditService;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "=== Verifying Phase 9: Security & Audit ===\n\n";

// 1. Audit Log Test
echo "[Audit] Creating Test Log Entry... ";
$audit = new AuditService();
$success = $audit->log(1, 'TEST_ACTION', 'System', 'Verification Script Test', ['foo'=>'bar'], ['foo'=>'baz']);

if ($success) {
    echo "OK\n";
    
    // Read back
    $logs = $audit->getLogs(5);
    $found = false;
    foreach($logs as $log) {
        if ($log['action'] === 'TEST_ACTION') $found = true;
    }
    echo "[Audit] Reading Recent Logs... " . ($found ? "OK (Found Entry)" : "FAIL (Entry missing)") . "\n";
} else {
    echo "FAIL (Insert Error)\n";
}

// 2. Security Headers (Simulated Check)
// Since this is CLI, we can't check headers directly, but we can verify index.php modification via file check
echo "\n[Security] Verifying index.php Headers... ";
$indexContent = file_get_contents(__DIR__ . '/../public/index.php');
if (strpos($indexContent, 'X-Frame-Options: SAMEORIGIN') !== false && strpos($indexContent, 'session.cookie_httponly') !== false) {
    echo "OK (Headers Configured)\n";
} else {
    echo "FAIL (Headers missing in code)\n";
}

echo "\n=== Verification Complete ===\n";
