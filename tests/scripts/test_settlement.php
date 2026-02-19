<?php
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Services/SettlementService.php';

try {
    echo "Instantiating SettlementService...\n";
    $service = new \App\Services\SettlementService();
    echo "Service instantiated.\n";
    
    echo "Calling getSummary...\n";
    $summary = $service->getSummary();
    print_r($summary);
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
