<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use App\Services\CompetitorService;
use App\Database;

echo "\n🔍 Testing CompetitorService...\n\n";

try {
    $db = Database::getInstance();
    
    // Get first active account
    $stmt = $db->query("SELECT id FROM ml_accounts WHERE status = 'active' LIMIT 1");
    $accountId = $stmt->fetchColumn();
    
    if (!$accountId) {
        die("❌ No active account found.\n");
    }
    
    $service = new CompetitorService((int)$accountId);
    
    // 1. Add Seller (Official Store Example or generic)
    // Using a random large ID for test safe-guard
    echo "1️⃣  Adding Watch Seller... ";
    $res = $service->addSellerToWatch(123456789, "TestCompetitor");
    echo $res['success'] ? "✅ OK\n" : "❌ " . ($res['error'] ?? 'Error') . "\n";
    
    // 2. Add Watch Item (We need a real Item ID ideally, but let's try a safe known one or handle error)
    // We will list search items first to find a real external item ID to test?
    // Actually, `addItemToWatch` validates with API. So we need a real item ID.
    // Let's Skip this or use a placeholder if we don't have one.
    // Ideally we search "iphone" and pick first result that is NOT ours.
    
    echo "2️⃣  Searching for a competitor item to watch... ";
    $searchService = new \App\Services\SearchService((int)$accountId);
    $search = $searchService->search(['q' => 'smartphone', 'limit' => 1]);
    
    if (!empty($search['results'][0]['id'])) {
        $itemId = $search['results'][0]['id'];
        echo "Found: $itemId. Adding... ";
        
        $res = $service->addItemToWatch($itemId);
        echo $res['success'] ? "✅ OK\n" : "❌ " . ($res['error'] ?? 'Error') . "\n";
        
        // 3. Record Snapshot
        echo "3️⃣  Recording Snapshot... ";
        $stats = $service->recordSnapshot();
        print_r($stats);
        
        // 4. Check Logs
        echo "4️⃣  Checking Logs... ";
        $logs = $service->getRecentAlerts();
        echo count($logs) . " alerts found.\n";
        
    } else {
        echo "⚠️  Could not find item to test.\n";
    }

} catch (Exception $e) {
    echo "\n❌ Exception: " . $e->getMessage() . "\n";
}
