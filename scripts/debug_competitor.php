<?php
// Include necessary files to reproduce the competitor dashboard issue
require_once __DIR__ . '/vendor/autoload.php'; // Load composer autoloader

// Initialize session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Mock session data for testing
$_SESSION['account_id'] = 1; // Use a default account ID

try {
    echo "Creating CompetitorAnalysisController...\n";
    $controller = new \App\Controllers\CompetitorAnalysisController();
    
    echo "Calling index method...\n";
    $controller->index();
    
    echo "Success: No error occurred\n";
} catch (Exception $e) {
    echo "Error occurred: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "Fatal Error occurred: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}