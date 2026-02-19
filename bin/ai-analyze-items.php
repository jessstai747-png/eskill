#!/usr/bin/env php
<?php
/**
 * AI Item Analysis - Generate Optimization Plan
 * Analyzes all items and creates prioritized action plan
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Services\AI\Analytics\ItemAnalyzer;

echo "🔍 AI Item Analysis - Prioritization Report\n";
echo "============================================\n\n";

try {
    $analyzer = new ItemAnalyzer();
    
    echo "Analyzing all items...\n";
    $analyzedItems = $analyzer->analyzeAllItems();
    
    echo "Generating optimization plan...\n\n";
    $plan = $analyzer->generateOptimizationPlan($analyzedItems);
    
    // Display summary
    echo "📊 SUMMARY\n";
    echo "══════════════════════════════════════════\n";
    echo "Total Items: {$plan['summary']['total_items']}\n";
    echo "  🔴 Critical:  {$plan['summary']['critical']} items\n";
    echo "  🟠 High:      {$plan['summary']['high']} items\n";
    echo "  🟡 Medium:    {$plan['summary']['medium']} items\n";
    echo "  🟢 Low:       {$plan['summary']['low']} items\n\n";
    
    echo "💰 FINANCIAL PROJECTION\n";
    echo "══════════════════════════════════════════\n";
    echo "Estimated Optimization Cost: R$ {$plan['summary']['estimated_total_cost']}\n";
    echo "Estimated Additional Revenue: R$ {$plan['summary']['estimated_additional_revenue']}\n";
    echo "Expected ROI: {$plan['summary']['estimated_roi']}\n\n";
    
    // Display recommendations
    echo "📋 RECOMMENDED OPTIMIZATION PHASES\n";
    echo "══════════════════════════════════════════\n";
    foreach ($plan['recommendations'] as $i => $phase) {
        echo "\n" . ($i + 1) . ". {$phase['phase']}\n";
        echo "   Items: {$phase['count']}\n";
        echo "   Cost: R$ {$phase['estimated_cost']}\n";
        echo "   Revenue: R$ {$phase['estimated_revenue']}\n";
        echo "   Timeline: {$phase['timeline']}\n";
    }
    
    echo "\n\n";
    
    // Top 10 opportunities
    echo "🎯 TOP 10 OPTIMIZATION OPPORTUNITIES\n";
    echo "══════════════════════════════════════════\n";
    echo sprintf("%-8s | %-30s | %-10s | %-8s | %-10s\n", 
        "Priority", "Item", "Score", "Cost", "Revenue");
    echo "──────────────────────────────────────────────────────────────────────────\n";
    
    foreach ($plan['top_opportunities'] as $item) {
        $title = substr($item['title'] ?? 'No title', 0, 28);
        echo sprintf("%-8s | %-30s | %3d/100   | R$ %5.2f | R$ %8.2f\n",
            strtoupper($item['priority_level']),
            $title,
            $item['priority_score'],
            $item['optimization_cost'],
            $item['estimated_roi']['additional_revenue']
        );
    }
    
    echo "\n\n";
    
    // Export to JSON
    if (isset($argv[1]) && $argv[1] === '--export') {
        $filename = 'optimization_plan_' . date('Y-m-d_His') . '.json';
        file_put_contents($filename, json_encode($plan, JSON_PRETTY_PRINT));
        echo "📁 Full plan exported to: $filename\n\n";
    }
    
    // Export to CSV for batch processing
    if (isset($argv[1]) && $argv[1] === '--csv') {
        $filename = 'optimization_queue_' . date('Y-m-d_His') . '.csv';
        $fp = fopen($filename, 'w');
        
        fputcsv($fp, ['Item ID', 'Title', 'Priority', 'Score', 'Cost', 'Est. Revenue', 'Issues']);
        
        foreach ($analyzedItems as $item) {
            $issueCount = count($item['issues']);
            fputcsv($fp, [
                $item['item_id'],
                $item['title'],
                $item['priority_level'],
                $item['priority_score'],
                $item['optimization_cost'],
                $item['estimated_roi']['additional_revenue'],
                $issueCount . ' issues'
            ]);
        }
        
        fclose($fp);
        echo "📁 CSV queue exported to: $filename\n\n";
    }
    
    echo "✅ Analysis complete!\n\n";
    echo "Next Steps:\n";
    echo "1. Review top opportunities above\n";
    echo "2. Start with Phase 1 (Critical items)\n";
    echo "3. Run: php bin/ai.sh worker start\n";
    echo "4. Monitor: php bin/ai.sh queue\n\n";
    
    echo "Export Options:\n";
    echo "  --export  Export full JSON plan\n";
    echo "  --csv     Export CSV for batch import\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
