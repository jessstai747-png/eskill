<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI\Core;

use PHPUnit\Framework\TestCase;
use App\Services\AI\Core\RetryService;
use App\Services\AI\Core\LoggingService;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \App\Services\AI\Core\RetryService
 */
class RetryServiceTest extends TestCase
{
    /** @var LoggingService&MockObject */
    private LoggingService $logger;
    private RetryService $retry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggingService::class);

        $this->retry = new RetryService($this->logger, [
            'max_retries' => 2,
            'base_delay_ms' => 1,
            'max_delay_ms' => 10,
            'backoff_multiplier' => 2,
            'jitter' => false,
            'failure_threshold' => 3,
            'success_threshold' => 1,
            'timeout_seconds' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        $this->retry->resetCircuit('test_op');
        parent::tearDown();
    }

    public function testExecuteReturnsResultOnSuccess(): void
    {
        $result = $this->retry->execute(fn() => 'ok', 'test_op');
        $this->assertSame('ok', $result);
    }

    public function testExecuteRetriesOnRetryableException(): void
    {
        $attempts = 0;
        $result = $this->retry->execute(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 2) {
                throw new \Exception('connection refused');
            }
            return 'recovered';
        }, 'test_op');

        $this->assertSame('recovered', $result);
        $this->assertSame(2, $attempts);
    }

    public function testExecuteThrowsAfterMaxRetries(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('always fails');

        $this->retry->execute(function () {
            throw new \Exception('always fails', 500);
        }, 'test_op');
    }

    public function testExecuteDoesNotRetryOnNonRetryableError(): void
    {
        $attempts = 0;
        try {
            $this->retry->execute(function () use (&$attempts) {
                $attempts++;
                throw new \Exception('not retryable', 400);
            }, 'test_op');
        } catch (\Exception $e) {
            // expected
        }
        $this->assertSame(1, $attempts);
    }

    public function testExecuteRetriesOnCustomRetryableErrors(): void
    {
        $attempts = 0;
        try {
            $this->retry->execute(function () use (&$attempts) {
                $attempts++;
                throw new \Exception('custom error', 400);
            }, 'test_op', ['custom error']);
        } catch (\Exception $e) {
            // expected after max retries
        }
        $this->assertSame(3, $attempts); // 1 initial + 2 retries
    }

    public function testExecuteRetriesOnRetryableHttpCodes(): void
    {
        $retryCodes = [429, 500, 502, 503, 504];
        foreach ($retryCodes as $code) {
            $this->retry->resetCircuit("test_code_{$code}");
            $attempts = 0;
            try {
                $this->retry->execute(function () use (&$attempts, $code) {
                    $attempts++;
                    throw new \Exception("Error with code {$code}", $code);
                }, "test_code_{$code}");
            } catch (\Exception $e) {
                // expected
            }
            $this->assertSame(3, $attempts, "Code {$code} should be retryable");
        }
    }

    public function testCircuitBreakerOpensAfterThreshold(): void
    {
        // Trigger 3 failures (threshold) to open circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->retry->execute(function () {
                    throw new \Exception('fail', 500);
                }, 'test_op');
            } catch (\Exception $e) {
                // expected
            }
        }

        // Now the circuit should be open
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circuit breaker is OPEN');
        $this->retry->execute(fn() => 'should not run', 'test_op');
    }

    public function testGetCircuitStatusReturnsClosedByDefault(): void
    {
        $status = $this->retry->getCircuitStatus('new_operation');
        $this->assertSame('closed', $status['state']);
    }

    public function testGetCircuitStatusReturnsAllCircuits(): void
    {
        $this->retry->execute(fn() => 'ok', 'op_a');
        $this->retry->execute(fn() => 'ok', 'op_b');

        $all = $this->retry->getCircuitStatus();
        $this->assertIsArray($all);
    }

    public function testResetCircuitClearsState(): void
    {
        // Trigger failures
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->retry->execute(function () {
                    throw new \Exception('fail', 500);
                }, 'test_op');
            } catch (\Exception $e) {
                // expected
            }
        }

        // Reset the circuit
        $this->retry->resetCircuit('test_op');

        // Should work again
        $result = $this->retry->execute(fn() => 'back in business', 'test_op');
        $this->assertSame('back in business', $result);
    }

    public function testExecuteWithFallbackUsesFallbackOnFailure(): void
    {
        $result = $this->retry->executeWithFallback(
            fn() => throw new \Exception('primary fails', 500),
            fn() => 'fallback result',
            'test_op'
        );
        $this->assertSame('fallback result', $result);
    }

    public function testExecuteWithFallbackReturnsPrimaryOnSuccess(): void
    {
        $result = $this->retry->executeWithFallback(
            fn() => 'primary result',
            fn() => 'fallback result',
            'test_op'
        );
        $this->assertSame('primary result', $result);
    }

    public function testExecuteRetriesOnTransientMessages(): void
    {
        $transientMessages = [
            'connection timeout occurred',
            'rate limit exceeded',
            'service unavailable',
            'temporary failure detected',
        ];

        foreach ($transientMessages as $idx => $msg) {
            $opName = "transient_{$idx}";
            $this->retry->resetCircuit($opName);
            $attempts = 0;
            try {
                $this->retry->execute(function () use (&$attempts, $msg) {
                    $attempts++;
                    throw new \Exception($msg);
                }, $opName);
            } catch (\Exception $e) {
                // expected
            }
            $this->assertGreaterThan(1, $attempts, "Message '{$msg}' should be retryable");
        }
    }
}
