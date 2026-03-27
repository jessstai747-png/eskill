#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * AI Optimization System - Cost Report Generator
 * Generates detailed cost reports for AI usage
 */

require __DIR__ . '/../vendor/autoload.php';

echo "💰 AI Optimization - Cost Report\n";
echo "=====================================\n\n";

$days = $argv[1] ?? 30;

try {
    $db = App\Database::getInstance();
    
    // Get cost summary
    $stmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as optimizations,
            SUM(cost) as daily_cost,
            AVG(cost) as avg_cost
        FROM ai_audit_log
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stmt->execute([$days]);
    $dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $totalOptimizations = array_sum(array_column($dailyStats, 'optimizations'));
    $totalCost = array_sum(array_column($dailyStats, 'daily_cost'));
    $avgCostPerOpt = $totalCost / max($totalOptimizations, 1);
    
    echo "Period: Last $days days\n";
    echo "─────────────────────────────────────\n\n";
    
    echo "📊 Summary\n";
    echo "   Total Optimizations: $totalOptimizations\n";
    echo "   Total Cost: R$ " . number_format($totalCost, 2, ',', '.') . "\n";
    echo "   Avg per Optimization: R$ " . number_format($avgCostPerOpt, 4, ',', '.') . "\n\n";
    
    // Daily breakdown
    echo "📅 Daily Breakdown\n";
    echo "─────────────────────────────────────\n";
    printf("%-12s | %-6s | %-10s | %-10s\n", "Date", "Count", "Cost", "Avg");
    echo "─────────────────────────────────────\n";
    
    foreach ($dailyStats as $stat) {
        printf(
            "%-12s | %6d | R$ %7.2f | R$ %7.4f\n",
            $stat['date'],
            $stat['optimizations'],
            $stat['daily_cost'],
            $stat['avg_cost']
        );
    }
    
    echo "\n";
    
    // Provider breakdown
    $stmt = $db->prepare("
        SELECT 
            provider,
            COUNT(*) as count,
            SUM(cost) as total_cost,
            AVG(cost) as avg_cost
        FROM ai_audit_log
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY provider
    ");
    $stmt->execute([$days]);
    $providerStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($providerStats)) {
        echo "🤖 By Provider\n";
        echo "─────────────────────────────────────\n";
        printf("%-15s | %-6s | %-10s | %-10s\n", "Provider", "Count", "Cost", "Avg");
        echo "─────────────────────────────────────\n";
        
        foreach ($providerStats as $stat) {
            printf(
                "%-15s | %6d | R$ %7.2f | R$ %7.4f\n",
                $stat['provider'] ?? 'unknown',
                $stat['count'],
                $stat['total_cost'],
                $stat['avg_cost']
            );
        }
        echo "\n";
    }
    
    // Action type breakdown
    $stmt = $db->prepare("
        SELECT 
            action as type,
            COUNT(*) as count,
            SUM(cost) as total_cost
        FROM ai_audit_log
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY action
    ");
    $stmt->execute([$days]);
    $actionStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($actionStats)) {
        echo "📝 By Action Type\n";
        echo "─────────────────────────────────────\n";
        printf("%-20s | %-6s | %-10s\n", "Type", "Count", "Cost");
        echo "─────────────────────────────────────\n";
        
        foreach ($actionStats as $stat) {
            printf(
                "%-20s | %6d | R$ %7.2f\n",
                $stat['type'],
                $stat['count'],
                $stat['total_cost']
            );
        }
        echo "\n";
    }
    
    // Projections
    $daysInMonth = 30;
    $avgDailyCost = $totalCost / $days;
    $projectedMonthlyCost = $avgDailyCost * $daysInMonth;
    
    echo "📈 Projections\n";
    echo "─────────────────────────────────────\n";
    echo "   Avg Daily Cost: R$ " . number_format($avgDailyCost, 2, ',', '.') . "\n";
    echo "   Projected Monthly: R$ " . number_format($projectedMonthlyCost, 2, ',', '.') . "\n";
    echo "   Daily Budget: R$ " . number_format(500 / 30, 2, ',', '.') . " (based on R$ 500/month)\n";
    
    $budgetStatus = ($avgDailyCost <= 500/30) ? '✅ Within budget' : '⚠️ Over budget';
    echo "   Status: $budgetStatus\n\n";
    
    // Export option
    if (isset($argv[2]) && $argv[2] === '--export') {
        $filename = "cost_report_" . date('Y-m-d') . ".csv";
        $fp = fopen($filename, 'w');
        
        fputcsv($fp, ['Date', 'Optimizations', 'Cost', 'Avg Cost']);
        foreach ($dailyStats as $stat) {
            fputcsv($fp, [
                $stat['date'],
                $stat['optimizations'],
                $stat['daily_cost'],
                $stat['avg_cost']
            ]);
        }
        
        fclose($fp);
        echo "📁 Report exported to: $filename\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "✅ Report complete!\n";
echo "\nUsage: php bin/ai-cost-report.php [days] [--export]\n";
echo "Example: php bin/ai-cost-report.php 30 --export\n";
