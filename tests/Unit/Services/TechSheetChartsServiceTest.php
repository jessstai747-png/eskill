<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\TechSheetChartsService;

class TechSheetChartsServiceTest extends TestCase
{
    private int $testAccountId = 999;

    public function testGetCompletenessTrendReturnsValidStructure(): void
    {
        $service = new TechSheetChartsService($this->testAccountId);
        $result = $service->getCompletenessTrend(7);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertIsArray($result['datasets']);
    }

    public function testGetCategoryDistributionReturnsValidStructure(): void
    {
        $service = new TechSheetChartsService($this->testAccountId);
        $result = $service->getCategoryDistribution();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
    }

    public function testGetSuggestionsStatusReturnsPieChart(): void
    {
        $service = new TechSheetChartsService($this->testAccountId);
        $result = $service->getSuggestionsStatus();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
    }

    public function testGetSourcePerformanceReturnsValidData(): void
    {
        $service = new TechSheetChartsService($this->testAccountId);
        $result = $service->getSourcePerformance();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
    }

    public function testGetImprovementsTimelineReturnsTimeSeries(): void
    {
        $service = new TechSheetChartsService($this->testAccountId);
        $result = $service->getImprovementsTimeline();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertCount(3, $result['datasets']); // 3 linhas: geradas, aprovadas, aplicadas
    }

    public function testGetActivityHeatmapReturnsMatrix(): void
    {
        $service = new TechSheetChartsService($this->testAccountId);
        $result = $service->getActivityHeatmap();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('days', $result);
        $this->assertArrayHasKey('hours', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(7, $result['days']); // 7 dias da semana
        $this->assertCount(7, $result['data']); // 7 linhas na matriz
    }

    public function testGetDashboardChartsReturnsAllCharts(): void
    {
        $service = new TechSheetChartsService($this->testAccountId);
        $result = $service->getDashboardCharts();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('completeness_trend', $result);
        $this->assertArrayHasKey('category_distribution', $result);
        $this->assertArrayHasKey('suggestions_status', $result);
        $this->assertArrayHasKey('source_performance', $result);
        $this->assertArrayHasKey('improvements_timeline', $result);
        $this->assertArrayHasKey('activity_heatmap', $result);
    }
}
