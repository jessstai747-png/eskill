#!/usr/bin/env php
<?php
/**
 * AI Optimization System - Queue Monitor
 * Real-time monitoring of the optimization queue
 */

require __DIR__ . '/../vendor/autoload.php';

echo "📊 AI Optimization Queue Monitor\n";
echo "==================================\n\n";

try {
    $db = App\Database::getInstance();
    
    // Queue stats
    $stmt = $db->query("
        SELECT 
            status,
            COUNT(*) as count,
            AVG(attempts) as avg_attempts
        FROM ai_optimization_queue
        GROUP BY status
    ");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Status Overview:\n";
    echo "────────────────────────────────\n";
    
    $total = 0;
    $statusCounts = [];
    foreach ($stats as $stat) {
        $statusCounts[$stat['status']] = $stat['count'];
        $total += $stat['count'];
        
        $icon = match($stat['status']) {
            'pending' => '⏳',
            'processing' => '⚙️',
            'completed' => '✅',
            'failed' => '❌',
            default => '📦'
        };
        
        printf("%s %-12s: %5d items\n", $icon, ucfirst($stat['status']), $stat['count']);
    }
    
    echo "────────────────────────────────\n";
    printf("   %-12s: %5d items\n\n", "Total", $total);
    
    // Recent items
    $stmt = $db->query("
        SELECT 
            id,
            item_id,
            status,
            attempts,
            created_at,
            updated_at
        FROM ai_optimization_queue
        ORDER BY updated_at DESC
        LIMIT 10
    ");
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($recent)) {
        echo "Recent Activity (Last 10):\n";
        echo "────────────────────────────────────────────────────\n";
        printf("%-8s | %-12s | %-10s | %-8s\n", "ID", "Item", "Status", "Updated");
        echo "────────────────────────────────────────────────────\n";
        
        foreach ($recent as $item) {
            $updated = date('H:i:s', strtotime($item['updated_at']));
            printf(
                "%-8d | %-12s | %-10s | %-8s\n",
                $item['id'],
                $item['item_id'],
                $item['status'],
                $updated
            );
        }
        echo "\n";
    }
    
    // Failed items
    $stmt = $db->query("
        SELECT item_id, attempts, error
        FROM ai_optimization_queue
        WHERE status = 'failed'
        ORDER BY updated_at DESC
        LIMIT 5
    ");
    $failed = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($failed)) {
        echo "⚠️  Recent Failures:\n";
        echo "────────────────────────────────────────────────────\n";
        foreach ($failed as $item) {
            echo "Item: {$item['item_id']}\n";
            echo "Attempts: {$item['attempts']}\n";
            echo "Error: " . substr($item['error'] ?? 'Unknown error', 0, 60) . "\n";
            echo "────────────────────────────────────────────────────\n";
        }
        echo "\n";
    }
    
    // Performance metrics
    $stmt = $db->query("
        SELECT 
            COUNT(*) as processed,
            AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_processing_time
        FROM ai_optimization_queue
        WHERE status = 'completed'
        AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $perf = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($perf['processed'] > 0) {
        echo "⚡ Performance (Last Hour):\n";
        echo "────────────────────────────────\n";
        echo "   Processed: {$perf['processed']} items\n";
        echo "   Avg Time: " . round($perf['avg_processing_time'], 2) . " seconds\n";
        echo "   Throughput: " . round($perf['processed'] / 60, 2) . " items/min\n\n";
    }
    
    // Recommendations
    $pending = $statusCounts['pending'] ?? 0;
    $failed = $statusCounts['failed'] ?? 0;
    
    echo "💡 Recommendations:\n";
    echo "────────────────────────────────\n";
    
    if ($pending > 100) {
        echo "⚠️  High pending queue ($pending items)\n";
        echo "   → Consider starting more workers\n";
        echo "   → Run: php bin/ai-worker.php &\n\n";
    }
    
    if ($failed > 10) {
        echo "⚠️  Multiple failures ($failed items)\n";
        echo "   → Check error logs\n";
        echo "   → Verify API keys\n";
        echo "   → Review failed items above\n\n";
    }
    
    if ($pending == 0 && $failed == 0) {
        echo "✅ Queue is healthy!\n\n";
    }
    
    // Worker status
    exec('ps aux | grep "[a]i-worker.php"', $workers);
    $workerCount = count($workers);
    
    echo "🔧 Worker Status:\n";
    echo "────────────────────────────────\n";
    if ($workerCount > 0) {
        echo "✅ $workerCount worker(s) running\n";
        foreach ($workers as $worker) {
            if (preg_match('/\s+(\d+)\s+/', $worker, $matches)) {
                echo "   PID: {$matches[1]}\n";
            }
        }
    } else {
        echo "⚠️  No workers running\n";
        echo "   Start with: php bin/ai-worker.php &\n";
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "✅ Monitoring complete!\n";
echo "\nTip: Run this in a loop for live monitoring:\n";
echo "     watch -n 5 php bin/ai-queue-monitor.php\n";
