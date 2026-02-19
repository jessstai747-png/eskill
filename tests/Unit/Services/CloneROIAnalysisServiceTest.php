<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CloneROIAnalysisService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * DB-free tests for ROI/aggregation logic.
 *
 * Importante: o construtor inicializa DB e cria tabelas; evitamos o construtor.
 *
 * @covers \App\Services\CloneROIAnalysisService
 */
class CloneROIAnalysisServiceTest extends TestCase
{
	private CloneROIAnalysisService $service;

	protected function setUp(): void
	{
		$ref = new ReflectionClass(CloneROIAnalysisService::class);
		$instance = $ref->newInstanceWithoutConstructor();
		$this->assertInstanceOf(CloneROIAnalysisService::class, $instance);
		$this->service = $instance;
	}

	public function testCalculateSummaryIncludesZeroConversionRates(): void
	{
		$method = new ReflectionMethod(CloneROIAnalysisService::class, 'calculateSummary');
		$method->setAccessible(true);

		$items = [
			[
				'visits' => 100,
				'sales' => 0,
				'revenue' => 0,
				'conversion_rate' => 0.0,
			],
			[
				'visits' => 100,
				'sales' => 1,
				'revenue' => 10.0,
				'conversion_rate' => 1.0,
			],
			[
				'visits' => null,
				'sales' => null,
				'revenue' => null,
				'conversion_rate' => null,
			],
		];

		$summary = $method->invoke($this->service, $items);
		$this->assertSame(3, $summary['total_items']);
		$this->assertSame(2, $summary['items_with_metrics']);
		$this->assertSame(200, $summary['total_visits']);
		$this->assertSame(1, $summary['total_sales']);
		$this->assertSame(10.0, $summary['total_revenue']);
		$this->assertSame(0.5, $summary['avg_conversion_rate']);
		$this->assertSame(0.5, $summary['overall_conversion_rate']);
	}

	public function testCalculateROIIndicatorThresholds(): void
	{
		$method = new ReflectionMethod(CloneROIAnalysisService::class, 'calculateROIIndicator');
		$method->setAccessible(true);

		$this->assertSame('excellent', $method->invoke($this->service, ['revenue' => 6000, 'conversion_rate' => 3.0, 'sales' => 10]));
		$this->assertSame('good', $method->invoke($this->service, ['revenue' => 1200, 'conversion_rate' => 1.5, 'sales' => 5]));
		$this->assertSame('moderate', $method->invoke($this->service, ['revenue' => 100, 'conversion_rate' => 0.1, 'sales' => 1]));
		$this->assertSame('low', $method->invoke($this->service, ['revenue' => 0, 'conversion_rate' => 0.0, 'sales' => 0]));
	}

	public function testGetUnderperformerRecommendationReturnsExpectedActions(): void
	{
		$method = new ReflectionMethod(CloneROIAnalysisService::class, 'getUnderperformerRecommendation');
		$method->setAccessible(true);

		$pause = $method->invoke($this->service, ['visits' => 100, 'conversion_rate' => 0.05]);
		$this->assertSame('pause', $pause['action']);

		$optimize = $method->invoke($this->service, ['visits' => 50, 'conversion_rate' => 0.4]);
		$this->assertSame('optimize', $optimize['action']);

		$monitor = $method->invoke($this->service, ['visits' => 10, 'conversion_rate' => 0.0]);
		$this->assertSame('monitor', $monitor['action']);
	}

	public function testIdentifyTopPerformersSortsByRevenue(): void
	{
		$method = new ReflectionMethod(CloneROIAnalysisService::class, 'identifyTopPerformers');
		$method->setAccessible(true);

		$items = [
			[
				'target_item_id' => 'T1',
				'source_item_id' => 'S1',
				'title' => 'Item 1',
				'brand' => 'A',
				'visits' => 100,
				'sales' => 2,
				'revenue' => 200.0,
				'conversion_rate' => 2.0,
			],
			[
				'target_item_id' => 'T2',
				'source_item_id' => 'S2',
				'title' => 'Item 2',
				'brand' => 'B',
				'visits' => 100,
				'sales' => 5,
				'revenue' => 500.0,
				'conversion_rate' => 5.0,
			],
			[
				'target_item_id' => 'T3',
				'source_item_id' => 'S3',
				'title' => 'Item 3',
				'brand' => 'C',
				'visits' => 10,
				'sales' => 0,
				'revenue' => 0.0,
				'conversion_rate' => 0.0,
			],
		];

		$top = $method->invoke($this->service, $items, 2);
		$this->assertCount(2, $top);
		$this->assertSame('T2', $top[0]['target_item_id']);
		$this->assertSame('T1', $top[1]['target_item_id']);
	}

	public function testIdentifyUnderperformersFindsLowConversionCandidates(): void
	{
		$method = new ReflectionMethod(CloneROIAnalysisService::class, 'identifyUnderperformers');
		$method->setAccessible(true);

		$items = [
			[
				'target_item_id' => 'T1',
				'source_item_id' => 'S1',
				'title' => 'Item 1',
				'visits' => 49,
				'sales' => 0,
				'conversion_rate' => 0.0,
			],
			[
				'target_item_id' => 'T2',
				'source_item_id' => 'S2',
				'title' => 'Item 2',
				'visits' => 100,
				'sales' => 0,
				'conversion_rate' => 0.0,
			],
			[
				'target_item_id' => 'T3',
				'source_item_id' => 'S3',
				'title' => 'Item 3',
				'visits' => 80,
				'sales' => 1,
				'conversion_rate' => 0.4,
			],
			[
				'target_item_id' => 'T4',
				'source_item_id' => 'S4',
				'title' => 'Item 4',
				'visits' => 80,
				'sales' => 1,
				'conversion_rate' => 1.0,
			],
		];

		$under = $method->invoke($this->service, $items, 10);
		$this->assertCount(2, $under);
		$this->assertSame('T2', $under[0]['target_item_id']);
	}
}
