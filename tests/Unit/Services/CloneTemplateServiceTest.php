<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CloneTemplateService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Behavioral tests for CloneTemplateService
 *
 * Importante: o construtor do service inicializa DB. Estes testes evitam o
 * construtor e testam apenas a lógica pura de regras (sem I/O).
 *
 * @covers \App\Services\CloneTemplateService
 */
class CloneTemplateServiceTest extends TestCase
{
	private CloneTemplateService $service;

	protected function setUp(): void
	{
		$ref = new ReflectionClass(CloneTemplateService::class);
		$instance = $ref->newInstanceWithoutConstructor();
		$this->assertInstanceOf(CloneTemplateService::class, $instance);
		$this->service = $instance;
	}

	public function testApplyTemplateRulesCopyPriceAndStock(): void
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
		$this->assertSame(10, $result['calculated']['final_stock']);
		$this->assertSame('Test Product', $result['calculated']['final_title']);
		$this->assertFalse($result['should_skip']);
	}

	public function testApplyTemplateRulesMarkupPercentPriceRule(): void
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

	public function testApplyTemplateRulesMarkdownPercentPriceRule(): void
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

	public function testApplyTemplateRulesFixedStockRule(): void
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

		$this->assertSame(5, $result['calculated']['final_stock']);
	}

	public function testApplyTemplateRulesPercentageStockRule(): void
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

		$this->assertSame(50, $result['calculated']['final_stock']);
	}

	public function testCalculateStockZeroRule(): void
	{
		$template = [
			'stock_type' => 'zero',
		];

		$this->assertSame(0, $this->service->calculateStock(999, $template));
	}

	public function testApplyTitleRulesPrefixAndSuffix(): void
	{
		$template = [
			'title_prefix' => '[PROMO]',
			'title_suffix' => '- Oferta',
		];

		$title = $this->service->applyTitleRules('Test Product', $template);

		$this->assertStringStartsWith('[PROMO]', $title);
		$this->assertStringEndsWith('- Oferta', $title);
	}

	public function testApplyTitleRulesRemovePatterns(): void
	{
		$template = [
			'title_remove_patterns' => json_encode(['/\\bTest\\b/i']),
		];

		$title = $this->service->applyTitleRules('Test Product', $template);
		$this->assertSame('Product', $title);
	}

	public function testApplyTitleRulesTruncatesTo60Chars(): void
	{
		$template = [
			'title_prefix' => '[NEW]',
		];

		$title = $this->service->applyTitleRules(
			'This Is A Very Long Product Title That Exceeds The Maximum Allowed Characters',
			$template
		);

		$this->assertLessThanOrEqual(60, mb_strlen($title));
		$this->assertStringEndsWith('...', $title);
	}

	public function testCalculatePriceRoundingTo099(): void
	{
		$template = [
			'pricing_type' => 'copy',
			'pricing_round_to' => 0.99,
		];

		$this->assertEquals(99.99, $this->service->calculatePrice(100.00, $template));
		$this->assertEquals(100.99, $this->service->calculatePrice(100.999, $template));
	}

	public function testSkipsCatalogItemsWhenConfigured(): void
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
		$this->assertNotEmpty($result['skip_reason']);
	}

	public function testSkipsNonCatalogItemsWhenConfigured(): void
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
		$this->assertNotEmpty($result['skip_reason']);
	}

	public function testReturnsExpectedStructure(): void
	{
		$template = [
			'pricing_type' => 'copy',
			'stock_type' => 'copy',
			'id' => 123,
			'slug' => 'test-template',
			'initial_status' => 'active',
			'clone_description' => 0,
			'clone_variations' => 1,
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

		$this->assertSame(123, $result['options']['template_id']);
		$this->assertSame('test-template', $result['options']['template_slug']);
		$this->assertFalse($result['options']['start_paused']);
		$this->assertFalse($result['options']['clone_description']);
		$this->assertTrue($result['options']['clone_variations']);
	}

	public function testParsesPostCloneActionsFromJson(): void
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

		$this->assertSame(['tech_sheet', 'seo_optimize'], $result['post_clone_actions']);
	}

	public function testParsesPostCloneActionsInvalidJsonReturnsEmptyArray(): void
	{
		$template = [
			'pricing_type' => 'copy',
			'stock_type' => 'copy',
			'post_clone_actions' => '{invalid-json',
		];

		$item = [
			'price' => 100.00,
			'available_quantity' => 10,
			'title' => 'Test Product',
		];

		$result = $this->service->applyTemplateRules($template, $item);

		$this->assertSame([], $result['post_clone_actions']);
	}
}
