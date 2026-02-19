<?php

// Simple test to verify that the classes can be loaded and instantiated
// Include the autoloader
require_once __DIR__ . '/autoload.php';

echo "Testing class loading...\n";

// Try to include the file directly first
require_once __DIR__ . '/app/Services/SEO/SynonymExpansionService.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Services/MercadoLivreClient.php';

echo "Files included successfully\n";

// Now try to instantiate
try {
    $synonymService = new \App\Services\SEO\SynonymExpansionService();
    echo "✅ SynonymExpansionService instantiated successfully\n";
} catch (Exception $e) {
    echo "❌ SynonymExpansionService failed: " . $e->getMessage() . "\n";
}

try {
    $db = new \App\Database();
    echo "✅ Database instantiated successfully\n";
} catch (Exception $e) {
    echo "❌ Database failed: " . $e->getMessage() . "\n";
}

// Test the HiddenAttributesDetector methods
require_once __DIR__ . '/app/Services/HiddenAttributesDetector.php';

try {
    $detector = new \App\Services\HiddenAttributesDetector();
    echo "✅ HiddenAttributesDetector instantiated successfully\n";
    
    // Check if the required methods exist
    $methods = ['detectKeywordFields', 'generateKeywordsFieldValue', 'generateMPNValue', 'generateLineValue', 'applyHiddenFields'];
    
    foreach ($methods as $method) {
        if (method_exists($detector, $method)) {
            echo "✅ Method {$method} exists\n";
        } else {
            echo "❌ Method {$method} missing\n";
        }
    }
} catch (Exception $e) {
    echo "❌ HiddenAttributesDetector failed: " . $e->getMessage() . "\n";
}