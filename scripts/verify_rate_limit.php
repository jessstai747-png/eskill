<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "--- Testing Rate Limit Tracker Service ---\n\n";

use App\Services\RateLimitTrackerService;

try {
    $tracker = new RateLimitTrackerService();
    
    // Simulate some API calls
    echo "Simulating API calls...\n";
    for ($i = 1; $i <= 5; $i++) {
        $tracker->trackCall('mercadolivre');
        echo "  Call $i tracked\n";
    }
    
    echo "\n=== RATE LIMIT STATUS ===\n\n";
    
    $status = $tracker->getStatus();
    
    foreach ($status as $provider => $data) {
        echo strtoupper($provider) . ":\n";
        echo "  Can Make Call: " . ($data['can_call'] ? 'YES' : 'NO') . "\n";
        
        echo "  Usage:\n";
        echo "    Minute: {$data['usage']['minute']}\n";
        echo "    Hour: {$data['usage']['hour']}\n";
        echo "    Day: {$data['usage']['day']}\n";
        
        echo "  Limits:\n";
        echo "    Minute: {$data['limits']['minute']}\n";
        echo "    Hour: " . ($data['limits']['hour'] ?? 'N/A') . "\n";
        
        echo "  Usage %:\n";
        foreach ($data['usage_percentage'] as $period => $pct) {
            echo "    " . ucfirst($period) . ": {$pct}%\n";
        }
        
        echo "  Prediction:\n";
        echo "    Will Hit Limit: " . ($data['prediction']['will_hit_limit'] ? 'YES' : 'NO') . "\n";
        echo "    Time to Limit: " . ($data['prediction']['time_to_limit_minutes'] ?? 'N/A') . " min\n";
        echo "    Recommendation: {$data['prediction']['recommendation']}\n";
        
        if ($data['alert']) {
            echo "  ⚠️  ALERT: {$data['alert']['level']} - {$data['alert']['message']}\n";
        }
        
        echo "\n";
    }
    
    // Test predictive throttling
    echo "=== PREDICTIVE THROTTLING TEST ===\n\n";
    $prediction = $tracker->predictLimitHit('mercadolivre', 10);
    echo "Looking ahead 10 minutes:\n";
    echo "  Will hit limit: " . ($prediction['will_hit_limit'] ? 'YES' : 'NO') . "\n";
    echo "  Current rate: {$prediction['current_rate_per_minute']} calls/min\n";
    echo "  Predicted usage: {$prediction['predicted_usage']}\n";
    echo "  Recommendation: {$prediction['recommendation']}\n";
    
    echo "\n✅ SUCCESS: Rate limit tracking working!\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
