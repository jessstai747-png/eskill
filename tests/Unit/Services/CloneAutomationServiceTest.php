<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CloneAutomationService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Importante: o construtor do service inicializa DB e cria tabelas. Estes testes
 * evitam o construtor e exercitam apenas a lógica pura (sem I/O).
 *
 * @covers \App\Services\CloneAutomationService
 */
class CloneAutomationServiceTest extends TestCase
{
	private CloneAutomationService $service;

	protected function setUp(): void
	{
		$ref = new ReflectionClass(CloneAutomationService::class);
		$instance = $ref->newInstanceWithoutConstructor();
		$this->assertInstanceOf(CloneAutomationService::class, $instance);
		$this->service = $instance;
	}

	public function testExtractBrandReturnsEmptyWhenMissing(): void
	{
		$method = new ReflectionMethod(CloneAutomationService::class, 'extractBrand');
		$method->setAccessible(true);

		$this->assertSame('', $method->invoke($this->service, ['attributes' => []]));
		$this->assertSame('', $method->invoke($this->service, []));
	}

	public function testExtractBrandReturnsValueNameWhenPresent(): void
	{
		$method = new ReflectionMethod(CloneAutomationService::class, 'extractBrand');
		$method->setAccessible(true);

		$item = [
			'attributes' => [
				['id' => 'BRAND', 'value_name' => 'GIVI'],
			],
		];

		$this->assertSame('GIVI', $method->invoke($this->service, $item));
	}

	public function testShouldIncludeItemHonorsOnlyAvailable(): void
	{
		$method = new ReflectionMethod(CloneAutomationService::class, 'shouldIncludeItem');
		$method->setAccessible(true);

		$item = ['title' => 'Produto', 'status' => 'paused'];
		$filters = ['only_available' => true];

		$this->assertFalse($method->invoke($this->service, $item, $filters));
	}

	public function testShouldIncludeItemHonorsOnlyCatalog(): void
	{
		$method = new ReflectionMethod(CloneAutomationService::class, 'shouldIncludeItem');
		$method->setAccessible(true);

		$item = ['title' => 'Produto', 'status' => 'active'];
		$filters = ['only_catalog' => true, 'only_available' => true];

		$this->assertFalse($method->invoke($this->service, $item, $filters));
	}

	public function testShouldIncludeItemHonorsExcludeKeywords(): void
	{
		$method = new ReflectionMethod(CloneAutomationService::class, 'shouldIncludeItem');
		$method->setAccessible(true);

		$item = ['title' => 'Baú Givi 45L', 'status' => 'active', 'catalog_product_id' => 'X'];
		$filters = [
			'exclude_keywords' => ['givi'],
			'only_available' => true,
			'only_catalog' => true,
		];

		$this->assertFalse($method->invoke($this->service, $item, $filters));
	}

	public function testShouldIncludeItemHonorsIncludeKeywords(): void
	{
		$method = new ReflectionMethod(CloneAutomationService::class, 'shouldIncludeItem');
		$method->setAccessible(true);

		$item = ['title' => 'Baú Givi 45L', 'status' => 'active', 'catalog_product_id' => 'X'];
		$filters = [
			'include_keywords' => ['givi'],
			'only_available' => true,
			'only_catalog' => true,
		];

		$this->assertTrue($method->invoke($this->service, $item, $filters));
	}

	public function testShouldIncludeItemHonorsBrandsFilter(): void
	{
		$method = new ReflectionMethod(CloneAutomationService::class, 'shouldIncludeItem');
		$method->setAccessible(true);

		$item = [
			'title' => 'Baú 45L',
			'status' => 'active',
			'catalog_product_id' => 'X',
			'attributes' => [
				['id' => 'BRAND', 'value_name' => 'GIVI'],
			],
		];

		$filters = [
			'brands' => ['GIVI'],
			'only_available' => true,
			'only_catalog' => true,
		];
		$this->assertTrue($method->invoke($this->service, $item, $filters));

		$filtersMismatch = $filters;
		$filtersMismatch['brands'] = ['SHAD'];
		$this->assertFalse($method->invoke($this->service, $item, $filtersMismatch));
	}

	public function testIsRuleScheduledNowWithinMargin(): void
	{
		$method = new ReflectionMethod(CloneAutomationService::class, 'isRuleScheduledNow');
		$method->setAccessible(true);

		$config = [
			'run_at' => '03:00',
			'days_of_week' => [1, 2, 3, 4, 5],
		];

		$this->assertTrue($method->invoke($this->service, $config, 1, '03:15'));
		$this->assertFalse($method->invoke($this->service, $config, 7, '03:15'));
		$this->assertFalse($method->invoke($this->service, $config, 1, '04:00'));
	}

	public function testDecodeJsonReturnsEmptyArrayOnInvalidJson(): void
	{
		$method = new ReflectionMethod(CloneAutomationService::class, 'decodeJson');
		$method->setAccessible(true);

		$result = $method->invoke($this->service, '{invalid');
		$this->assertIsArray($result);
		$this->assertSame([], $result);
	}
}
