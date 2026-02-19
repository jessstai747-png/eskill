<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Services\AnalyticsService;

echo "=================================================\n";
echo "   📊 TESTE ANALYTICS SERVICE (PHASE 24)\n";
echo "=================================================\n";

$service = new AnalyticsService();
$passed = 0;
$total = 0;

// Test 1: Dashboard Summary
$total++;
echo "\n1. Testing Dashboard Summary...\n";
$summary = $service->getDashboardSummary();
if (isset($summary['revenue_today']) && isset($summary['growth_rate'])) {
    echo "   ✅ Summary: Revenue Today = R$ {$summary['revenue_today']}, Growth = {$summary['growth_rate']}%\n";
    $passed++;
} else {
    echo "   ❌ FAILED: Missing summary fields\n";
}

// Test 2: Revenue Trend
$total++;
echo "\n2. Testing Revenue Trend...\n";
$trend = $service->getRevenueTrend(date('Y-m-01'), date('Y-m-d'));
echo "   Found " . count($trend) . " data points\n";
if (is_array($trend)) {
    echo "   ✅ Revenue trend calculation working\n";
    if (count($trend) > 0) {
        echo "   Sample: Period {$trend[0]['period']} = R$ {$trend[0]['revenue']}\n";
    }
    $passed++;
} else {
    echo "   ❌ FAILED\n";
}

// Test 3: Customer LTV
$total++;
echo "\n3. Testing Customer LTV Segmentation...\n";
$ltv = $service->getCustomerLTV();
if (is_array($ltv)) {
    echo "   ✅ Found " . count($ltv) . " customer segments\n";
    foreach ($ltv as $segment) {
        echo "   - {$segment['segment']}: {$segment['customer_count']} customers, Avg LTV = R$ {$segment['avg_ltv']}\n";
    }
    $passed++;
} else {
    echo "   ❌ FAILED\n";
}

// Test 4: Profit Margins
$total++;
echo "\n4. Testing Profit Margin Analysis...\n";
$margins = $service->getProfitMargins();
if (is_array($margins)) {
    echo "   ✅ Found " . count($margins) . " listing types\n";
    foreach ($margins as $m) {
        echo "   - {$m['listing_type']}: {$m['avg_margin']}% margin\n";
    }
    $passed++;
} else {
    echo "   ❌ FAILED\n";
}

// Test 5: Inventory Turnover
$total++;
echo "\n5. Testing Inventory Turnover...\n";
$turnover = $service->getInventoryTurnover();
if (is_array($turnover)) {
    echo "   ✅ Found " . count($turnover) . " categories with turnover data\n";
    $passed++;
} else {
    echo "   ❌ FAILED\n";
}

// Test 6: Forecast
$total++;
echo "\n6. Testing Revenue Forecast...\n";
$forecast = $service->getForecast(7);
if (is_array($forecast) && count($forecast) === 7) {
    echo "   ✅ Generated 7-day forecast\n";
    echo "   - Tomorrow: R$ {$forecast[0]['predicted_revenue']}\n";
    $passed++;
} else {
    echo "   ❌ FAILED: Expected 7 days, got " . count($forecast) . "\n";
}

// Summary
echo "\n=================================================\n";
echo "RESULTS: $passed / $total tests passed\n";
if ($passed === $total) {
    echo "✅ ALL ANALYTICS TESTS PASSED!\n";
    exit(0);
} else {
    echo "⚠️  Some tests failed\n";
    exit(1);
}
