<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\AI\Testing\ABTestingService;
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "--- Testing A/B Testing Service ---\n";

// 1. Initialize (creates tables)
$ab = new ABTestingService();
echo "[OK] Service initialized.\n";

// 2. Create Test
$itemId = 'TEST-ITEM-' . time();
$testId = $ab->createTest(
    'Title Optimization Test',
    $itemId,
    ['title' => 'Original Title'],
    ['title' => 'Optimized Title']
);
echo "[OK] Test created with ID: $testId\n";

// 3. Track Metrics
// Variant A: Low conversion
$ab->trackMetrics($testId, 'a', [
    'views' => 1000,
    'visits' => 100, // 10% CTR
    'sales' => 5,    // 5% Conv
    'revenue' => 500
]);

// Variant B: High conversion
$ab->trackMetrics($testId, 'b', [
    'views' => 1000,
    'visits' => 120, // 12% CTR
    'sales' => 20,   // ~16% Conv (Definitely Significant)
    'revenue' => 2000
]);
echo "[OK] Metrics tracked.\n";

// 4. Get Results
$results = $ab->getTestResults($testId);

echo "--- Results ---\n";
echo "Winner: " . $results['winner'] . "\n";
echo "Significant: " . ($results['is_significant'] ? 'Yes' : 'No') . "\n";
echo "Confidence: " . $results['confidence_level'] . "%\n";
echo "Improvement: " . print_r($results['improvement'], true);

if ($results['winner'] === 'b' && $results['is_significant']) {
    echo "\n[SUCCESS] A/B Logic Verified.\n";
} else {
    echo "\n[FAILURE] Logic not matching expected outcome.\n";
}

// Cleanup
$db = Database::getInstance();
$db->exec("DELETE FROM ai_ab_tests WHERE id = $testId");
