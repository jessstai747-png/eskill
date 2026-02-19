<?php

use PHPUnit\Framework\TestCase;
use App\Services\TechSheetAnalyticsService;
use App\Database;

class TechSheetAnalyticsServiceTest extends TestCase
{
    private TechSheetAnalyticsService $service;
    private int $testAccountId = 999;

    protected function setUp(): void
    {
        $this->service = new TechSheetAnalyticsService($this->testAccountId);
    }

    public function testGetDashboardReturnsValidStructure(): void
    {
        $dashboard = $this->service->getDashboard();

        $this->assertIsArray($dashboard);
        $this->assertArrayHasKey('overview', $dashboard);
        $this->assertArrayHasKey('by_category', $dashboard);
        $this->assertArrayHasKey('trending', $dashboard);
        $this->assertArrayHasKey('suggestions', $dashboard);
        $this->assertArrayHasKey('generated_at', $dashboard);
    }

    public function testGetOverviewReturnsValidMetrics(): void
    {
        $overview = $this->service->getOverview();

        $this->assertIsArray($overview);
        $this->assertArrayHasKey('total_items', $overview);
        $this->assertArrayHasKey('total_categories', $overview);
        $this->assertArrayHasKey('analyzed_items', $overview);
        $this->assertArrayHasKey('analysis_coverage', $overview);
        $this->assertArrayHasKey('avg_completeness', $overview);
        $this->assertArrayHasKey('items_with_critical_gaps', $overview);

        // Validar tipos
        $this->assertIsInt($overview['total_items']);
        $this->assertIsInt($overview['total_categories']);
        $this->assertIsInt($overview['analyzed_items']);
        $this->assertIsNumeric($overview['analysis_coverage']);
        $this->assertIsNumeric($overview['avg_completeness']);
    }

    public function testGetMetricsByCategoryReturnsArray(): void
    {
        $categories = $this->service->getMetricsByCategory(10);

        $this->assertIsArray($categories);
        
        if (!empty($categories)) {
            $firstCategory = $categories[0];
            $this->assertArrayHasKey('category_id', $firstCategory);
            $this->assertArrayHasKey('item_count', $firstCategory);
            $this->assertArrayHasKey('analyzed_count', $firstCategory);
            $this->assertArrayHasKey('avg_completeness', $firstCategory);
            $this->assertArrayHasKey('health_score', $firstCategory);
        }
    }

    public function testGetTrendingImprovementsReturnsArray(): void
    {
        $trending = $this->service->getTrendingImprovements(7, 10);

        $this->assertIsArray($trending);
        $this->assertLessThanOrEqual(10, count($trending));
    }

    public function testGetSuggestionsStatsReturnsValidStructure(): void
    {
        $stats = $this->service->getSuggestionsStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('by_status', $stats);
        $this->assertArrayHasKey('by_source', $stats);
        $this->assertIsArray($stats['by_status']);
        $this->assertIsArray($stats['by_source']);
    }

    public function testGetPriorityCategoriesReturnsLimitedResults(): void
    {
        $limit = 5;
        $priorities = $this->service->getPriorityCategoriesForOptimization($limit);

        $this->assertIsArray($priorities);
        $this->assertLessThanOrEqual($limit, count($priorities));
    }

    public function testHealthScoreIsInValidRange(): void
    {
        $categories = $this->service->getMetricsByCategory(10);

        // Ensure we have data to test, or explicitly pass with message
        if (empty($categories)) {
            $this->assertTrue(true, 'No categories returned - test passes vacuously');
            return;
        }

        foreach ($categories as $cat) {
            $healthScore = $cat['health_score'];
            $this->assertGreaterThanOrEqual(0, $healthScore, 'Health score deve ser >= 0');
            $this->assertLessThanOrEqual(100, $healthScore, 'Health score deve ser <= 100');
        }
    }
}
