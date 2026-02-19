#!/usr/bin/env php
<?php
/**
 * Cron script to record performance metrics.
 *
 * This script should be run periodically (e.g., every 5 minutes) via cron.
 * Example cron entry:
 *  */5 * * * * /usr/bin/php /path/to/your/project/bin/record-metrics.php >> /path/to/your/project/storage/logs/cron.log 2>&1
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\PerformanceMetricsService;

echo "Starting performance metrics recording at " . date('Y-m-d H:i:s') . "\n";

try {
    $metricsService = new PerformanceMetricsService();
    $metricsService->recordCurrentMetrics();
    
    echo "Successfully recorded current performance metrics.\n";

} catch (\Exception $e) {
    echo "Error recording performance metrics: " . $e->getMessage() . "\n";
    error_log("Cron job record-metrics.php failed: " . $e->getMessage());
    exit(1);
}

echo "Finished performance metrics recording.\n";
exit(0);

