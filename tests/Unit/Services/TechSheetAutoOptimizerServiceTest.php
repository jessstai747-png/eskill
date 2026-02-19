<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\TechSheetAutoOptimizerService;

class TechSheetAutoOptimizerServiceTest extends TestCase
{
    private int $testAccountId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testAccountId = 1;
    }

    public function testGetStatsReturnsValidStructure(): void
    {
        $service = new TechSheetAutoOptimizerService($this->testAccountId);
        $stats = $service->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('enabled', $stats);
        $this->assertArrayHasKey('min_confidence', $stats);
        $this->assertArrayHasKey('eligible_items', $stats);
        $this->assertArrayHasKey('total_eligible_suggestions', $stats);
    }

    public function testEnabledIsBoolean(): void
    {
        $service = new TechSheetAutoOptimizerService($this->testAccountId);
        $stats = $service->getStats();

        $this->assertIsBool($stats['enabled']);
    }

    public function testMinConfidenceIsInteger(): void
    {
        $service = new TechSheetAutoOptimizerService($this->testAccountId);
        $stats = $service->getStats();

        $this->assertIsInt($stats['min_confidence']);
        $this->assertGreaterThanOrEqual(0, $stats['min_confidence']);
        $this->assertLessThanOrEqual(100, $stats['min_confidence']);
    }

    public function testEligibleItemsIsInteger(): void
    {
        $service = new TechSheetAutoOptimizerService($this->testAccountId);
        $stats = $service->getStats();

        $this->assertIsInt($stats['eligible_items']);
        $this->assertGreaterThanOrEqual(0, $stats['eligible_items']);
    }

    public function testAutoOptimizeDryRunReturnsValidStructure(): void
    {
        $service = new TechSheetAutoOptimizerService($this->testAccountId);
        $result = $service->autoOptimize([
            'dry_run' => true,
            'limit' => 5,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('dry_run', $result);
        
        if ($result['success']) {
            $this->assertTrue($result['dry_run']);
            $this->assertArrayHasKey('results', $result);
        }
    }

    public function testBySourceIsArray(): void
    {
        $service = new TechSheetAutoOptimizerService($this->testAccountId);
        $stats = $service->getStats();

        $this->assertIsArray($stats['by_source']);
    }
}
