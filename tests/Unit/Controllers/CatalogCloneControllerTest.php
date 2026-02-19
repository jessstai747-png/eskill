<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Controllers\CatalogCloneController
 */
class CatalogCloneControllerTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\App\Controllers\CatalogCloneController::class);
    }

    // =========================================================================
    // Structural
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(\App\Controllers\CatalogCloneController::class));
    }

    public function testHasDeclareStrictTypes(): void
    {
        $file = $this->reflection->getFileName();
        $this->assertNotFalse($file);
        $this->assertStringContainsString('declare(strict_types=1)', file_get_contents($file));
    }

    public function testExtendsBaseController(): void
    {
        $parent = $this->reflection->getParentClass();
        $this->assertNotFalse($parent);
        $this->assertSame('App\\Controllers\\BaseController', $parent->getName());
    }

    /**
     * @dataProvider coreEndpointsProvider
     */
    public function testCoreEndpointExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function coreEndpointsProvider(): array
    {
        return [
            ['index'],
            ['cloneItem'],
            ['cloneBatch'],
            ['getMetrics'],
            ['searchWithFilters'],
            ['createSchedule'],
            ['getSchedules'],
            ['cancelSchedule'],
            ['simulate'],
            ['pricePreview'],
            ['listSellerItems'],
            ['getSellerSummary'],
            ['resolveItemIds'],
        ];
    }

    /**
     * @dataProvider advancedEndpointsProvider
     */
    public function testAdvancedEndpointExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function advancedEndpointsProvider(): array
    {
        return [
            ['dryRun'],
            ['cloneItemNew'],
            ['createJob'],
            ['getJobStatus'],
            ['listJobs'],
            ['listActiveJobs'],
            ['getHistory'],
        ];
    }

    /**
     * @dataProvider templateEndpointsProvider
     */
    public function testTemplateEndpointExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function templateEndpointsProvider(): array
    {
        return [
            ['listTemplates'],
            ['getTemplate'],
            ['createTemplate'],
            ['updateTemplate'],
            ['deleteTemplate'],
            ['previewTemplate'],
        ];
    }

    /**
     * @dataProvider metricsEndpointsProvider
     */
    public function testMetricsEndpointExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function metricsEndpointsProvider(): array
    {
        return [
            ['getMetricsDashboard'],
            ['getJobsMetrics'],
            ['getTopErrors'],
            ['getDailyReport'],
        ];
    }

    /**
     * @dataProvider monitoringEndpointsProvider
     */
    public function testMonitoringEndpointExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function monitoringEndpointsProvider(): array
    {
        return [
            ['listAlerts'],
            ['acknowledgeAlert'],
            ['listFeatureFlags'],
            ['updateFeatureFlag'],
            ['exportReport'],
            ['downloadReport'],
        ];
    }

    public function testHasOver40PublicMethods(): void
    {
        $methods = $this->reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        $ownMethods = array_filter($methods, fn($m) => $m->getDeclaringClass()->getName() === $this->reflection->getName());
        $this->assertGreaterThanOrEqual(40, count($ownMethods));
    }

    public function testHasTryCatchBlocks(): void
    {
        $file = $this->reflection->getFileName();
        $source = file_get_contents($file);
        $catchCount = substr_count($source, 'catch (');
        $this->assertGreaterThanOrEqual(10, $catchCount);
    }
}
