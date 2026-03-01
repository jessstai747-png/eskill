<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\JobService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class JobServiceRetryPolicyTest extends TestCase
{
    private JobService $service;
    private ReflectionMethod $calculateRetryDelayMethod;
    private ReflectionMethod $getStaleTimeoutMethod;

    /** @var array<string, string|false> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $refClass = new ReflectionClass(JobService::class);
        $instance = $refClass->newInstanceWithoutConstructor();
        $this->service = $instance;

        $this->calculateRetryDelayMethod = $refClass->getMethod('calculateRetryDelaySeconds');
        $this->calculateRetryDelayMethod->setAccessible(true);

        $this->getStaleTimeoutMethod = $refClass->getMethod('getStaleProcessingTimeoutSeconds');
        $this->getStaleTimeoutMethod->setAccessible(true);
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key]);
            } else {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }

        parent::tearDown();
    }

    public function testCalculateRetryDelayUsesExponentialBackoffAndCap(): void
    {
        $this->setEnv('JOB_RETRY_BASE_SECONDS', '2');
        $this->setEnv('JOB_RETRY_MAX_SECONDS', '20');
        $this->setEnv('JOB_RETRY_JITTER_PERCENT', '0');

        $this->assertSame(2, $this->retryDelayForAttempt(1));
        $this->assertSame(4, $this->retryDelayForAttempt(2));
        $this->assertSame(8, $this->retryDelayForAttempt(3));
        $this->assertSame(16, $this->retryDelayForAttempt(4));
        $this->assertSame(20, $this->retryDelayForAttempt(5));
        $this->assertSame(20, $this->retryDelayForAttempt(6));
    }

    public function testCalculateRetryDelayAppliesBoundedJitter(): void
    {
        $this->setEnv('JOB_RETRY_BASE_SECONDS', '10');
        $this->setEnv('JOB_RETRY_MAX_SECONDS', '60');
        $this->setEnv('JOB_RETRY_JITTER_PERCENT', '0.30');

        $delay = $this->retryDelayForAttempt(1);
        $this->assertGreaterThanOrEqual(10, $delay);
        $this->assertLessThanOrEqual(13, $delay);
    }

    public function testGetStaleProcessingTimeoutClampsBounds(): void
    {
        $this->setEnv('JOB_STALE_PROCESSING_SECONDS', '5');
        $timeoutLow = (int)$this->getStaleTimeoutMethod->invoke($this->service);
        $this->assertSame(60, $timeoutLow);

        $this->setEnv('JOB_STALE_PROCESSING_SECONDS', '90000');
        $timeoutHigh = (int)$this->getStaleTimeoutMethod->invoke($this->service);
        $this->assertSame(86400, $timeoutHigh);
    }

    private function retryDelayForAttempt(int $attempt): int
    {
        return (int)$this->calculateRetryDelayMethod->invoke($this->service, $attempt);
    }

    private function setEnv(string $key, string $value): void
    {
        if (!array_key_exists($key, $this->envBackup)) {
            $this->envBackup[$key] = getenv($key);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}
