<?php

namespace Tests\Unit\Services;

use App\Services\CloneTemplateService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CloneTemplateService
 * 
 * Note: These tests focus on the rule application logic which doesn't require DB.
 * DB-dependent tests would need migrations applied to test database.
 */
class CloneTemplateServiceTest extends TestCase
{
    private CloneTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CloneTemplateService();
    }

    /**
     * @test
     */
    public function it_applies_copy_price_rule(): void
    {
        $template = [
            'pricing_type' => 'copy',
            'stock_type' => 'copy',
        ];

        $item = [
            'price' => 100.00,
            'available_quantity' => 10,
            'title' => 'Test Product',
        ];

        $result = $this->service->applyTemplateRules($template, $item);

        $this->assertEquals(100.00, $result['calculated']['final_price']);
        $this->assertEquals(10, $result['calculated']['final_stock']);
    }

    /**
     * @test
     */
    public function it_applies_markup_percent_price_rule(): void
    {
        $template = [
            'pricing_type' => 'markup_percent',
            'pricing_value' => 30,
            'stock_type' => 'copy',
        ];

        $item = [
            'price' => 100.00,
            'available_quantity' => 10,
            'title' => 'Test Product',
        ];

        $result = $this->service->applyTemplateRules($template, $item);

        $this->assertEquals(130.00, $result['calculated']['final_price']);
    }

    /**
     * @test
     */
    public function it_applies_markdown_percent_price_rule(): void
    {
        $template = [
            'pricing_type' => 'markdown_percent',
            'pricing_value' => 10,
            'stock_type' => 'copy',
        ];

        $item = [
            'price' => 100.00,
            'available_quantity' => 10,
            'title' => 'Test Product',
        ];

        $result = $this->service->applyTemplateRules($template, $item);

        $this->assertEquals(90.00, $result['calculated']['final_price']);
    }

    /**
     * @test
     */
    public function it_applies_fixed_stock_rule(): void
    {
        $template = [
            'pricing_type' => 'copy',
            'stock_type' => 'fixed',
            'stock_value' => 5,
        ];

        $item = [
            'price' => 100.00,
            'available_quantity' => 100,
            'title' => 'Test Product',
        ];

        $result = $this->service->applyTemplateRules($template, $item);

        $this->assertEquals(5, $result['calculated']['final_stock']);
    }

    /**
     * @test
     */
    public function it_applies_percentage_stock_rule(): void
    {
        $template = [
            'pricing_type' => 'copy',
            'stock_type' => 'percentage',
            'stock_value' => 50,
        ];

        $item = [
            'price' => 100.00,
            'available_quantity' => 100,
            'title' => 'Test Product',
        ];

        $result = $this->service->applyTemplateRules($template, $item);

        $this->assertEquals(50, $result['calculated']['final_stock']);
    }

    /**
     * @test
     */
    public function it_applies_title_prefix(): void
    {
        $template = [
            'pricing_type' => 'copy',
            'stock_type' => 'copy',
            'title_prefix' => '[PROMO] ',
        ];

        $item = [
            'price' => 100.00,
            'available_quantity' => 10,
            'title' => 'Test Product',
        ];

        $result = $this->service->applyTemplateRules($template, $item);

        $this->assertStringStartsWith('[PROMO]', $result['calculated']['final_title']);
    }

    /**
     * @test
     */
    public function it_applies_title_suffix(): void
    {
        $template = [
            'pricing_type' => 'copy',
            'stock_type' => 'copy',
            'title_suffix' => ' - Oferta',
        ];

        $item = [
            'price' => 100.00,
            'available_quantity' => 10,
            'title' => 'Test Product',
        ];

        $result = $this->service->applyTemplateRules($template, $item);

        $this->assertStringEndsWith('- Oferta', $result['calculated']['final_title']);
    }

    /**
     * @test
     */
    public function it_truncates_title_to_60_chars(): void
    {
        $template = [
            'pricing_type' => 'copy',
            'stock_type' => 'copy',
            'title_prefix' => '[NEW] ',
        ];

        $item = [
            'price' => 100.00,
            'available_quantity' => 10,
            'title' => 'This Is A Very Long Product Title That Exceeds The Maximum Allowed Characters',
        ];

        $result = $this->service->applyTemplateRules($template, $item);

        $this->assertLessThanOrEqual(60, strlen($result['calculated']['final_title']));
    }

    /**
     * @test
     */
    public function it_skips_catalog_items_when_configured(): void
    {
        $template = [
            'pricing_type' => 'copy',
            'stock_type' => 'copy',
            'skip_catalog_items' => true,
        ];

        $item = [
            'price' => 100.00,
            'available_quantity' => 10,
            'title' => 'Catalog Product',
            'catalog_product_id' => 'MLB12345678',
        ];

        $result = $this->service->applyTemplateRules($template, $item);

        $this->assertTrue($result['should_skip']);
        $this->assertNotNull($result['skip_reason']);
    }

    /**
     * @test
     */
    public function it_skips_non_catalog_items_when_configured(): void
    {
        $template = [
            'pricing_type' => 'copy',
            'stock_type' => 'copy',
            'skip_non_catalog_items' => true,
        ];

        $item = [
            'price' => 100.00,
            'available_quantity' => 10,
            'title' => 'Non-Catalog Product',
        ];

        $result = $this->service->applyTemplateRules($template, $item);

        $this->assertTrue($result['should_skip']);
    }

    /**
     * @test
     */
    public function it_returns_correct_structure(): void
    {
        $template = [
            'pricing_type' => 'copy',
            'stock_type' => 'copy',
        ];

        $item = [
            'price' => 100.00,
            'available_quantity' => 10,
            'title' => 'Test Product',
        ];

        $result = $this->service->applyTemplateRules($template, $item);

        $this->assertArrayHasKey('pricing_strategy', $result);
        $this->assertArrayHasKey('stock_strategy', $result);
        $this->assertArrayHasKey('options', $result);
        $this->assertArrayHasKey('calculated', $result);
        $this->assertArrayHasKey('should_skip', $result);
        $this->assertArrayHasKey('post_clone_actions', $result);
    }

    /**
     * @test
     */
    public function it_parses_post_clone_actions(): void
    {
        $template = [
            'pricing_type' => 'copy',
            'stock_type' => 'copy',
            'post_clone_actions' => json_encode(['tech_sheet', 'seo_optimize']),
        ];

        $item = [
            'price' => 100.00,
            'available_quantity' => 10,
            'title' => 'Test Product',
        ];

        $result = $this->service->applyTemplateRules($template, $item);

        $this->assertIsArray($result['post_clone_actions']);
    }
}
