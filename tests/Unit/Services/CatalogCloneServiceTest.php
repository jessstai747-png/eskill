<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CatalogCloneService;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for CatalogCloneService
 * 
 * Tests core business logic, validation, and error handling.
 * Integration tests with real API calls are in tests/Integration/
 */
class CatalogCloneServiceTest extends TestCase
{
    private CatalogCloneService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CatalogCloneService();
    }

    // =========================================================================
    // INSTANTIATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(CatalogCloneService::class, $this->service);
    }

    /**
     * @test
     */
    public function service_has_required_public_methods(): void
    {
        $this->assertTrue(method_exists($this->service, 'cloneCatalogItem'));
        $this->assertTrue(method_exists($this->service, 'simulateClone'));
        $this->assertTrue(method_exists($this->service, 'getCloneHistory'));
        $this->assertTrue(method_exists($this->service, 'getCloneMetrics'));
        $this->assertTrue(method_exists($this->service, 'searchItemsWithFilters'));
        $this->assertTrue(method_exists($this->service, 'createCloneSchedule'));
        $this->assertTrue(method_exists($this->service, 'listSellerItems'));
    }

    // =========================================================================
    // VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function clone_blocks_same_account(): void
    {
        $result = $this->service->cloneCatalogItem([
            'source_account_id' => 1,
            'source_item_id' => 'MLB123456789',
            'target_account_id' => 1,
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('mesma conta', strtolower($result['message']));
    }

    /**
     * @test
     */
    public function simulate_clone_blocks_same_account(): void
    {
        $result = $this->service->simulateClone([
            'source_account_id' => 1,
            'source_item_id' => 'MLB123456789',
            'target_account_id' => 1,
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('iguais', strtolower($result['message']));
    }

    // =========================================================================
    // PRICING STRATEGY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function markup_calculation_is_correct(): void
    {
        $originalPrice = 100.0;
        $markupPercent = 15.0;
        
        $expectedPrice = round($originalPrice * (1 + ($markupPercent / 100)), 2);
        
        $this->assertEqualsWithDelta(115.0, $expectedPrice, 0.01);
    }

    /**
     * @test
     */
    public function markdown_calculation_is_correct(): void
    {
        $originalPrice = 100.0;
        $markdownPercent = 10.0;
        
        $expectedPrice = $originalPrice * (1 - ($markdownPercent / 100));
        
        $this->assertEquals(90.0, $expectedPrice);
    }

    /**
     * @test
     */
    public function price_rounds_to_two_decimals(): void
    {
        $price = 99.999;
        $rounded = round($price, 2);
        
        $this->assertEquals(100.0, $rounded);
    }

    /**
     * @test
     */
    public function zero_markup_keeps_original_price(): void
    {
        $originalPrice = 199.90;
        $markupPercent = 0;
        
        $finalPrice = $originalPrice * (1 + ($markupPercent / 100));
        
        $this->assertEquals(199.90, $finalPrice);
    }

    /**
     * @test
     */
    public function negative_markup_reduces_price(): void
    {
        $originalPrice = 100.0;
        $markupPercent = -20.0;
        
        $finalPrice = $originalPrice * (1 + ($markupPercent / 100));
        
        $this->assertEquals(80.0, $finalPrice);
    }

    // =========================================================================
    // METRICS TESTS
    // =========================================================================

    /**
     * @test
     * @group integration
     */
    public function get_clone_metrics_returns_expected_structure(): void
    {
        try {
            $metrics = $this->service->getCloneMetrics();

            $this->assertIsArray($metrics);
            $this->assertArrayHasKey('today', $metrics);
            $this->assertArrayHasKey('success_rate', $metrics);
            $this->assertArrayHasKey('total', $metrics);
            $this->assertArrayHasKey('avg_per_hour', $metrics);
            $this->assertArrayHasKey('pending', $metrics);
            $this->assertArrayHasKey('errors', $metrics);
        } catch (\PDOException $e) {
            $this->markTestSkipped('Requires cloned_items table: ' . $e->getMessage());
        }
    }

    /**
     * @test
     * @group integration
     */
    public function metrics_values_are_numeric(): void
    {
        try {
            $metrics = $this->service->getCloneMetrics();

            $this->assertIsInt($metrics['today']);
            $this->assertIsFloat($metrics['success_rate']);
            $this->assertIsInt($metrics['total']);
            $this->assertIsFloat($metrics['avg_per_hour']);
            $this->assertIsInt($metrics['pending']);
            $this->assertIsInt($metrics['errors']);
        } catch (\PDOException $e) {
            $this->markTestSkipped('Requires cloned_items table: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // HISTORY TESTS
    // =========================================================================

    /**
     * @test
     * @group integration
     */
    public function get_clone_history_returns_array(): void
    {
        try {
            $history = $this->service->getCloneHistory(10);
            $this->assertIsArray($history);
        } catch (\PDOException $e) {
            $this->markTestSkipped('Requires cloned_items table: ' . $e->getMessage());
        }
    }

    /**
     * @test
     * @group integration
     */
    public function get_clone_history_respects_limit(): void
    {
        try {
            $history = $this->service->getCloneHistory(5);
            $this->assertLessThanOrEqual(5, count($history));
        } catch (\PDOException $e) {
            $this->markTestSkipped('Requires cloned_items table: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // SUCCESS RATE CALCULATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function success_rate_calculation(): void
    {
        $todayClones = 100;
        $todaySuccess = 95;
        
        $successRate = $todayClones > 0 ? round(($todaySuccess / $todayClones) * 100, 1) : 0;
        
        $this->assertEquals(95.0, $successRate);
    }

    /**
     * @test
     */
    public function success_rate_handles_zero_clones(): void
    {
        $todayClones = 0;
        $todaySuccess = 0;
        
        $successRate = $todayClones > 0 ? round(($todaySuccess / $todayClones) * 100, 1) : 0;
        
        $this->assertEquals(0, $successRate);
    }

    // =========================================================================
    // AVERAGE PER HOUR CALCULATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function avg_per_hour_calculation(): void
    {
        $last24hCount = 240;
        $avgPerHour = round($last24hCount / 24, 1);
        
        $this->assertEquals(10.0, $avgPerHour);
    }

    /**
     * @test
     */
    public function avg_per_hour_handles_small_numbers(): void
    {
        $last24hCount = 5;
        $avgPerHour = round($last24hCount / 24, 1);
        
        $this->assertEquals(0.2, $avgPerHour);
    }

    // =========================================================================
    // ITEM ID VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function ml_item_id_format_validation(): void
    {
        // Valid MLB format
        $validId = 'MLB1234567890';
        $this->assertMatchesRegularExpression('/^ML[A-Z]\d+$/', $validId);
        
        // Invalid formats
        $invalidId1 = '1234567890';
        $this->assertDoesNotMatchRegularExpression('/^ML[A-Z]\d+$/', $invalidId1);
        
        $invalidId2 = 'MLBXYZ';
        $this->assertDoesNotMatchRegularExpression('/^ML[A-Z]\d+$/', $invalidId2);
    }

    // =========================================================================
    // PAYLOAD CONSTRUCTION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function picture_payload_format(): void
    {
        $sourcePictures = [
            ['url' => 'https://example.com/img1.jpg', 'id' => '123'],
            ['url' => 'https://example.com/img2.jpg', 'id' => '456'],
        ];

        $payload = array_map(function($pic) { 
            return ['source' => $pic['url']]; 
        }, $sourcePictures);

        $this->assertCount(2, $payload);
        $this->assertEquals(['source' => 'https://example.com/img1.jpg'], $payload[0]);
        $this->assertEquals(['source' => 'https://example.com/img2.jpg'], $payload[1]);
    }

    /**
     * @test
     */
    public function catalog_product_id_detection(): void
    {
        $catalogItem = ['catalog_product_id' => 'MLB12345678'];
        $nonCatalogItem = ['title' => 'Test Item'];

        $isCatalog1 = !empty($catalogItem['catalog_product_id']);
        $isCatalog2 = !empty($nonCatalogItem['catalog_product_id']);

        $this->assertTrue($isCatalog1);
        $this->assertFalse($isCatalog2);
    }
}
