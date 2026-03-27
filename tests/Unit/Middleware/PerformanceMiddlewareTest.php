<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Middleware\PerformanceMiddleware;
use App\Middleware\PerformanceTimer;

class PerformanceMiddlewareTest extends TestCase
{
    // =============================
    // TESTES DE INSTANCIAÇÃO
    // =============================

    public function testCanBeInstantiated(): void
    {
        $middleware = new PerformanceMiddleware();
        $this->assertInstanceOf(PerformanceMiddleware::class, $middleware);
    }

    public function testCanSetSlowThreshold(): void
    {
        $middleware = new PerformanceMiddleware(2.0, false);
        $this->assertInstanceOf(PerformanceMiddleware::class, $middleware);
    }

    // =============================
    // TESTES DE START/FINISH
    // =============================

    public function testStartAndFinishWork(): void
    {
        $middleware = new PerformanceMiddleware(1.0, false);

        $middleware->start();
        usleep(10000); // 10ms
        $middleware->finish();

        // Se chegou aqui sem exceção, passou
        $this->assertTrue(true);
    }

    // =============================
    // TESTES DE MEASURE
    // =============================

    public function testMeasureReturnsCallbackResult(): void
    {
        $result = PerformanceMiddleware::measure(function () {
            return 'test_result';
        }, 'test_operation');

        $this->assertEquals('test_result', $result);
    }

    public function testMeasureExecutesCallback(): void
    {
        $executed = false;

        PerformanceMiddleware::measure(function () use (&$executed) {
            $executed = true;
        }, 'test_operation');

        $this->assertTrue($executed);
    }

    public function testMeasureWithExpensiveOperation(): void
    {
        $result = PerformanceMiddleware::measure(function () {
            $sum = 0;
            for ($i = 0; $i < 1000; $i++) {
                $sum += $i;
            }
            return $sum;
        }, 'expensive_calculation');

        $this->assertEquals(499500, $result);
    }

    // =============================
    // TESTES DE TIMER
    // =============================

    public function testTimerReturnsPerformanceTimer(): void
    {
        $timer = PerformanceMiddleware::timer('test_timer');
        $this->assertInstanceOf(PerformanceTimer::class, $timer);
    }

    public function testTimerElapsedReturnsFloat(): void
    {
        $timer = PerformanceMiddleware::timer('test_timer');
        usleep(5000); // 5ms

        $elapsed = $timer->elapsed();

        $this->assertIsFloat($elapsed);
        $this->assertGreaterThan(0, $elapsed);
    }

    public function testTimerStopReturnsFloat(): void
    {
        $timer = PerformanceMiddleware::timer('test_timer');
        usleep(5000); // 5ms

        $duration = $timer->stop();

        $this->assertIsFloat($duration);
        $this->assertGreaterThan(0, $duration);
    }

    public function testTimerMeasuresCorrectTime(): void
    {
        $timer = PerformanceMiddleware::timer('test_timer');
        usleep(50000); // 50ms

        $elapsed = $timer->elapsed();

        // Deve ser aproximadamente 50ms (0.05s) com margem de erro
        $this->assertGreaterThan(0.04, $elapsed);
        $this->assertLessThan(0.15, $elapsed);
    }

    // =============================
    // TESTES DE SHUTDOWN
    // =============================

    public function testRegisterShutdownDoesNotThrow(): void
    {
        // Não podemos testar shutdown diretamente, mas podemos verificar que não lança exceção
        try {
            PerformanceMiddleware::registerShutdown(2.0);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail('registerShutdown should not throw exception');
        }
    }

    // =============================
    // TESTES DE EDGE CASES
    // =============================

    public function testMeasureWithException(): void
    {
        $this->expectException(\RuntimeException::class);

        PerformanceMiddleware::measure(function () {
            throw new \RuntimeException('Test exception');
        }, 'failing_operation');
    }

    public function testTimerCanBeUsedMultipleTimes(): void
    {
        $timer1 = PerformanceMiddleware::timer('timer1');
        $timer2 = PerformanceMiddleware::timer('timer2');

        usleep(10000);
        $elapsed1 = $timer1->elapsed();

        usleep(10000);
        $elapsed2 = $timer2->elapsed();

        // Timer2 deve ter mais tempo pois foi criado primeiro e esperou mais
        $this->assertGreaterThan($elapsed1, $elapsed2);
    }

    public function testMeasureWithZeroTimeOperation(): void
    {
        $result = PerformanceMiddleware::measure(function () {
            return true;
        }, 'instant_operation');

        $this->assertTrue($result);
    }
}
