<?php
require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use App\Services\SearchService;
use App\Database;

echo "\n🔭 Testing Opportunity Scanner...\n\n";

try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT id FROM ml_accounts WHERE status = 'active' LIMIT 1");
    $accountId = $stmt->fetchColumn();
    
    if (!$accountId) {
        die("❌ No active account found.\n");
    }

    $service = new SearchService((int)$accountId);
    
    // Category: Celulares (Example: MLB1055) or something specific
    // Let's use 'MLB1055' (Smartphones)
    $categoryId = 'MLB1055'; 
    
    echo "Scanning Category: $categoryId...\n";
    $ops = $service->scanForOpportunities($categoryId);
    
    echo "Found " . count($ops) . " opportunities.\n\n";
    
    foreach (array_slice($ops, 0, 5) as $op) {
        echo "🔥 [Score: {$op['opportunity_score']}] {$op['title']}\n";
        echo "   💰 R$ {$op['price']} | 📦 Sold: {$op['sold_quantity']}\n";
        echo "   ⚠️  Reasons: " . implode(', ', $op['reasons']) . "\n";
        echo "   🔗 {$op['link']}\n\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
