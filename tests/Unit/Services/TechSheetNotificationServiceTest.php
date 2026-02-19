<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\TechSheetNotificationService;

class TechSheetNotificationServiceTest extends TestCase
{
    private int $testAccountId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testAccountId = 1;
    }

    public function testGetAlertsReturnsValidStructure(): void
    {
        $service = new TechSheetNotificationService($this->testAccountId);
        $alerts = $service->getAlerts();

        $this->assertIsArray($alerts);
        $this->assertArrayHasKey('alerts', $alerts);
        $this->assertArrayHasKey('summary', $alerts);
        $this->assertArrayHasKey('generated_at', $alerts);
    }

    public function testAlertsSummaryHasRequiredFields(): void
    {
        $service = new TechSheetNotificationService($this->testAccountId);
        $alerts = $service->getAlerts();

        $summary = $alerts['summary'];
        $this->assertArrayHasKey('total_critical', $summary);
        $this->assertArrayHasKey('total_missing_required', $summary);
        $this->assertArrayHasKey('priority_level', $summary);
    }

    public function testPriorityLevelIsValid(): void
    {
        $service = new TechSheetNotificationService($this->testAccountId);
        $alerts = $service->getAlerts();

        $priorityLevel = $alerts['summary']['priority_level'];
        $validLevels = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];
        
        $this->assertContains($priorityLevel, $validLevels);
    }

    public function testCriticalCompletenessIsArray(): void
    {
        $service = new TechSheetNotificationService($this->testAccountId);
        $alerts = $service->getAlerts();

        $this->assertIsArray($alerts['alerts']['critical_completeness']);
    }

    public function testGenerateDailyReportReturnsValidStructure(): void
    {
        $service = new TechSheetNotificationService($this->testAccountId);
        $report = $service->generateDailyReport();

        $this->assertIsArray($report);
        $this->assertArrayHasKey('date', $report);
        $this->assertArrayHasKey('overview', $report);
        $this->assertArrayHasKey('alerts', $report);
        $this->assertArrayHasKey('action_items', $report);
    }

    public function testActionItemsIsArray(): void
    {
        $service = new TechSheetNotificationService($this->testAccountId);
        $report = $service->generateDailyReport();

        $this->assertIsArray($report['action_items']);
    }
}
