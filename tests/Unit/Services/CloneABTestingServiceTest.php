<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CloneABTestingService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for pure winner/confidence logic.
 *
 * Importante: evitamos o construtor do service (ele depende de DB).
 *
 * @covers \App\Services\CloneABTestingService
 */
class CloneABTestingServiceTest extends TestCase
{
    private function makeServiceWithTest(?array $test): CloneABTestingService
    {
        return new class($test) extends CloneABTestingService {
            private ?array $stub;

            public function __construct(?array $stub)
            {
                $this->stub = $stub;
            }

            public function getTest(int $testId): ?array
            {
                return $this->stub;
            }
        };
    }

    public function testDetermineWinnerReturnsNoDataWhenTestIsNull(): void
    {
        $service = $this->makeServiceWithTest(null);
        $winner = $service->determineWinner(1);

        $this->assertNull($winner['variation_id']);
        $this->assertSame(0, $winner['confidence']);
        $this->assertSame('Sem dados', $winner['reason']);
    }

    public function testDetermineWinnerReturnsNoControlWhenMissingControlVariation(): void
    {
        $service = $this->makeServiceWithTest([
            'target_metric' => CloneABTestingService::METRIC_CONVERSION,
            'min_sample_size' => 1,
            'confidence_level' => 95,
            'variations' => [
                [
                    'id' => 2,
                    'name' => 'Var A',
                    'is_control' => 0,
                    'total_views' => 100,
                    'total_sales' => 10,
                    'conversion_rate' => 10.0,
                ],
            ],
        ]);

        $winner = $service->determineWinner(1);
        $this->assertNull($winner['variation_id']);
        $this->assertSame('Controle não encontrado', $winner['reason']);
    }

    public function testDetermineWinnerReturnsInsufficientSample(): void
    {
        $service = $this->makeServiceWithTest([
            'target_metric' => CloneABTestingService::METRIC_VIEWS,
            'min_sample_size' => 100,
            'confidence_level' => 95,
            'variations' => [
                [
                    'id' => 1,
                    'name' => 'Control',
                    'is_control' => 1,
                    'total_views' => 10,
                ],
                [
                    'id' => 2,
                    'name' => 'Var',
                    'is_control' => 0,
                    'total_views' => 10,
                ],
            ],
        ]);

        $winner = $service->determineWinner(1);
        $this->assertNull($winner['variation_id']);
        $this->assertSame(0, $winner['confidence']);
        $this->assertStringContainsString('Amostra insuficiente', $winner['reason']);
    }

    public function testDetermineWinnerReturnsSignificantWinnerForConversionRate(): void
    {
        $service = $this->makeServiceWithTest([
            'target_metric' => CloneABTestingService::METRIC_CONVERSION,
            'min_sample_size' => 100,
            'confidence_level' => 95,
            'variations' => [
                [
                    'id' => 1,
                    'name' => 'Control',
                    'is_control' => 1,
                    'total_views' => 10000,
                    'total_sales' => 100,
                    'conversion_rate' => 1.0,
                ],
                [
                    'id' => 2,
                    'name' => 'Var Winner',
                    'is_control' => 0,
                    'total_views' => 10000,
                    'total_sales' => 1000,
                    'conversion_rate' => 10.0,
                ],
            ],
        ]);

        $winner = $service->determineWinner(1);

        $this->assertSame(2, $winner['variation_id']);
        $this->assertTrue($winner['is_significant']);
        $this->assertGreaterThanOrEqual(95, $winner['confidence']);
        $this->assertSame('Var Winner', $winner['variation_name']);
    }

    public function testDetermineWinnerReturnsInconclusiveWhenConfidenceLow(): void
    {
        $service = $this->makeServiceWithTest([
            'target_metric' => CloneABTestingService::METRIC_CONVERSION,
            'min_sample_size' => 10,
            'confidence_level' => 95,
            'variations' => [
                [
                    'id' => 1,
                    'name' => 'Control',
                    'is_control' => 1,
                    'total_views' => 100,
                    'total_sales' => 5,
                    'conversion_rate' => 5.0,
                ],
                [
                    'id' => 2,
                    'name' => 'Var',
                    'is_control' => 0,
                    'total_views' => 100,
                    'total_sales' => 6,
                    'conversion_rate' => 6.0,
                ],
            ],
        ]);

        $winner = $service->determineWinner(1);
        $this->assertNull($winner['variation_id']);
        $this->assertFalse($winner['is_significant']);
        $this->assertStringContainsString('inconclusivo', strtolower((string) $winner['reason']));
    }
}
