<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Pipeline;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Core\Pipeline
 */
class PipelineTest extends TestCase
{
    // ─── Basic passthrough ────────────────────────────────────────────────────

    public function testThenReturnPassesThroughWithNoStages(): void
    {
        $result = (new Pipeline('hello'))->thenReturn();
        $this->assertSame('hello', $result);
    }

    public function testThenCallsDestinationWithSubject(): void
    {
        $received = null;
        (new Pipeline(42))->then(function ($subject) use (&$received) {
            $received = $subject;
        });
        $this->assertSame(42, $received);
    }

    // ─── send() ───────────────────────────────────────────────────────────────

    public function testSendOverridesConstructorSubject(): void
    {
        $result = (new Pipeline('original'))
            ->send('replaced')
            ->thenReturn();

        $this->assertSame('replaced', $result);
    }

    // ─── Single stage ─────────────────────────────────────────────────────────

    public function testSingleStageTransformsSubject(): void
    {
        $result = (new Pipeline('hello'))
            ->pipe(fn($text, $next) => $next(strtoupper($text)))
            ->thenReturn();

        $this->assertSame('HELLO', $result);
    }

    // ─── Multiple stages ──────────────────────────────────────────────────────

    public function testMultipleStagesRunInOrder(): void
    {
        $log = [];

        (new Pipeline('x'))
            ->pipe(function ($s, $next) use (&$log) {
                $log[] = 'A';
                return $next($s);
            })
            ->pipe(function ($s, $next) use (&$log) {
                $log[] = 'B';
                return $next($s);
            })
            ->pipe(function ($s, $next) use (&$log) {
                $log[] = 'C';
                return $next($s);
            })
            ->thenReturn();

        $this->assertSame(['A', 'B', 'C'], $log);
    }

    public function testMultipleStagesTransformChained(): void
    {
        $result = (new Pipeline(1))
            ->pipe(fn($n, $next) => $next($n + 1))   // 2
            ->pipe(fn($n, $next) => $next($n * 3))   // 6
            ->pipe(fn($n, $next) => $next($n - 1))   // 5
            ->thenReturn();

        $this->assertSame(5, $result);
    }

    // ─── Stage can break the chain ────────────────────────────────────────────

    public function testStageCanShortCircuit(): void
    {
        $log = [];

        $result = (new Pipeline('subject'))
            ->pipe(function ($s, $next) use (&$log) {
                $log[] = 'first';
                return 'short-circuited'; // does NOT call $next
            })
            ->pipe(function ($s, $next) use (&$log) {
                $log[] = 'second'; // should never run
                return $next($s);
            })
            ->thenReturn();

        $this->assertSame('short-circuited', $result);
        $this->assertSame(['first'], $log);
    }

    // ─── Object stages with handle() ──────────────────────────────────────────

    public function testObjectStageWithHandleMethod(): void
    {
        $stage = new class {
            public function handle(string $subject, callable $next): string
            {
                return $next($subject . '_handled');
            }
        };

        $result = (new Pipeline('value'))
            ->pipe($stage)
            ->thenReturn();

        $this->assertSame('value_handled', $result);
    }

    // ─── Object stages with process() ────────────────────────────────────────

    public function testObjectStageWithProcessMethod(): void
    {
        $stage = new class {
            public function process(int $n, callable $next): int
            {
                return $next($n * 2);
            }
        };

        $result = (new Pipeline(5))
            ->pipe($stage)
            ->thenReturn();

        $this->assertSame(10, $result);
    }

    // ─── Unknown object stage ─────────────────────────────────────────────────

    public function testUnknownObjectStageThrowsInvalidArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Pipeline('x'))
            ->pipe(new \stdClass())
            ->thenReturn();
    }

    // ─── then() return value ──────────────────────────────────────────────────

    public function testThenReturnsDestinationResult(): void
    {
        $result = (new Pipeline(10))
            ->pipe(fn($n, $next) => $next($n + 5))
            ->then(fn($n) => $n * 2);

        $this->assertSame(30, $result);
    }

    // ─── Immutability: pipe() on shared instance ──────────────────────────────

    public function testPipeReturnsSelf(): void
    {
        $pipeline = new Pipeline('x');
        $returned = $pipeline->pipe(fn($s, $next) => $next($s));
        $this->assertSame($pipeline, $returned);
    }

    // ─── Array subject ────────────────────────────────────────────────────────

    public function testPipelineWorksWithArraySubject(): void
    {
        $result = (new Pipeline(['a', 'b', 'c']))
            ->pipe(fn($arr, $next) => $next(array_map('strtoupper', $arr)))
            ->pipe(fn($arr, $next) => $next(array_reverse($arr)))
            ->thenReturn();

        $this->assertSame(['C', 'B', 'A'], $result);
    }
}
