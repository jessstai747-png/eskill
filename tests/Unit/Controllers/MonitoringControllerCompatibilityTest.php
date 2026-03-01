<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;

class MonitoringControllerCompatibilityTest extends TestCase
{
    public function testMonitoringRoutesExposeLegacyAliases(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Routes/api.php');
        $this->assertIsString($source);

        $this->assertStringContainsString("api/monitoring/system-logs", $source);
        $this->assertStringContainsString("api/monitoring/job-stats", $source);
    }

    public function testControllerHasCompatibilityShapesForLegacyDashboard(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Controllers/MonitoringController.php');
        $this->assertIsString($source);

        $this->assertStringContainsString('function jobStats', $source);
        $this->assertStringContainsString('new MonitoringAlertNotificationService()', $source);
        $this->assertStringContainsString('dispatchMlOperationalAlerts($alerts)', $source);
        $this->assertStringContainsString("'notification_dispatch' => \$notificationDispatch", $source);
        $this->assertStringContainsString("'hourly_stats' => \$hourlyStats", $source);
        $this->assertStringContainsString("\$metrics['basic_stats']['pending_jobs'] ?? \$metrics['clone_jobs']['pending'] ?? 0", $source);
    }
}
