<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CloneAnalyticsService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * DB-free tests for CloneAnalyticsService helper logic.
 *
 * Importante: o construtor inicializa DB; evitamos o construtor.
 *
 * @covers \App\Services\CloneAnalyticsService
 */
class CloneAnalyticsServiceTest extends TestCase
{
	private CloneAnalyticsService $service;

	protected function setUp(): void
	{
		$ref = new ReflectionClass(CloneAnalyticsService::class);
		$instance = $ref->newInstanceWithoutConstructor();
		$this->assertInstanceOf(CloneAnalyticsService::class, $instance);
		$this->service = $instance;
	}

	public function testParsePeriodWithFixedNowReturnsExpectedDateFrom(): void
	{
		$method = new ReflectionMethod(CloneAnalyticsService::class, 'parsePeriod');
		$method->setAccessible(true);

		$now = new DateTimeImmutable('2026-02-19 12:00:00 UTC');
		$this->assertSame('2026-02-12 12:00:00', $method->invoke($this->service, '7d', $now));
		$this->assertSame('2026-02-18 12:00:00', $method->invoke($this->service, '24h', $now));
	}

	public function testGetComparisonPeriodReturnsPreviousWindowStart(): void
	{
		$method = new ReflectionMethod(CloneAnalyticsService::class, 'getComparisonPeriod');
		$method->setAccessible(true);

		$now = new DateTimeImmutable('2026-02-19 12:00:00 UTC');
		$dateFrom = '2026-02-12 12:00:00';
		$this->assertSame('2026-02-05 12:00:00', $method->invoke($this->service, $dateFrom, $now));
	}

	public function testCalculatePercentilesReturnsZerosForEmptyInput(): void
	{
		$method = new ReflectionMethod(CloneAnalyticsService::class, 'calculatePercentiles');
		$method->setAccessible(true);

		$result = $method->invoke($this->service, [], [50, 90]);
		$this->assertSame([50 => 0, 90 => 0], $result);
	}

	public function testCalculatePercentilesUsesNearestRank(): void
	{
		$method = new ReflectionMethod(CloneAnalyticsService::class, 'calculatePercentiles');
		$method->setAccessible(true);

		$data = [1.0, 2.0, 3.0, 4.0];
		$result = $method->invoke($this->service, $data, [50, 90, 99]);

		$this->assertSame(2.0, $result[50]);
		$this->assertSame(4.0, $result[90]);
		$this->assertSame(4.0, $result[99]);
	}
}
