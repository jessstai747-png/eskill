<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

class CatalogCloneMonitoringServiceOperationalTest extends TestCase
{
    public function testServiceIncludesMlOperationalMetricsInRealtimePayload(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Services/CatalogCloneMonitoringService.php');
        $this->assertIsString($source);

        $this->assertStringContainsString("'basic_stats' => \$this->buildBasicStats(\$cloneJobMetrics)", $source);
        $this->assertStringContainsString("'ml_operations' => \$this->getMlOperationalMetrics()", $source);
        $this->assertStringContainsString('function getMlOperationalMetrics', $source);
        $this->assertStringContainsString('function buildBasicStats', $source);
    }

    public function testServiceHasMlOperationalAlertThresholdsAndTypes(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Services/CatalogCloneMonitoringService.php');
        $this->assertIsString($source);

        $this->assertStringContainsString('ML_MONITOR_WEBHOOK_FAILED_THRESHOLD', $source);
        $this->assertStringContainsString('ML_MONITOR_WEBHOOK_OLDEST_FAILED_MINUTES_THRESHOLD', $source);
        $this->assertStringContainsString('ML_MONITOR_JOB_RETRY_PENDING_THRESHOLD', $source);
        $this->assertStringContainsString('ML_MONITOR_JOB_RECLAIMED_HOURLY_THRESHOLD', $source);
        $this->assertStringContainsString('ML_MONITOR_STALE_PROCESSING_THRESHOLD', $source);
        $this->assertStringContainsString('ml_webhook_failed_backlog', $source);
        $this->assertStringContainsString('ml_job_retry_backlog', $source);
        $this->assertStringContainsString('ml_job_reclaimed_spike', $source);
        $this->assertStringContainsString('ml_job_processing_stale', $source);
        $this->assertStringContainsString("'severity' => 'CRITICAL'", $source);
        $this->assertStringContainsString("'severity' => 'WARNING'", $source);
    }
}
