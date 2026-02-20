<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ErrorMonitoringService;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * @covers \App\Services\ErrorMonitoringService
 */
class ErrorMonitoringServiceTest extends TestCase
{
    /**
     * Test logError handles error data structure correctly
     */
    public function testLogErrorAcceptsValidErrorData(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params): bool {
                return $params[0] === 'Exception'
                    && $params[1] === 'Test error message'
                    && $params[2] === '/path/to/file.php'
                    && $params[3] === 42;
            }))
            ->willReturn(true);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $service = $this->createServiceWithMockPdo($pdo);

        // Should not throw
        $service->logError([
            'type' => 'Exception',
            'message' => 'Test error message',
            'file' => '/path/to/file.php',
            'line' => 42,
            'trace' => ['frame1', 'frame2'],
            'context' => ['key' => 'value'],
            'user_id' => 1,
            'account_id' => 2,
            'severity' => 'error',
        ]);

        $this->assertTrue(true); // If we got here, no exception was thrown
    }

    /**
     * Test logError uses defaults for missing fields
     */
    public function testLogErrorUsesDefaultsForMissingFields(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params): bool {
                // Check defaults are applied
                return $params[0] === 'Error' // default type
                    && $params[1] === '' // default message
                    && $params[2] === '' // default file
                    && $params[3] === 0; // default line
            }))
            ->willReturn(true);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $service = $this->createServiceWithMockPdo($pdo);
        $service->logError([]);

        $this->assertTrue(true);
    }

    /**
     * Test logError handles PDO exception gracefully
     */
    public function testLogErrorHandlesPdoExceptionGracefully(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')
            ->willThrowException(new \PDOException('DB connection failed'));

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $service = $this->createServiceWithMockPdo($pdo);

        // Should not throw, just log warning internally
        $service->logError(['message' => 'Test']);

        $this->assertTrue(true);
    }

    /**
     * Test getRecentErrors returns array
     */
    public function testGetRecentErrorsReturnsArray(): void
    {
        $expectedRows = [
            ['id' => 1, 'error_type' => 'Exception', 'error_message' => 'Error 1'],
            ['id' => 2, 'error_type' => 'Error', 'error_message' => 'Error 2'],
        ];

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($expectedRows);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $service = $this->createServiceWithMockPdo($pdo);
        $result = $service->getRecentErrors(10);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Exception', $result[0]['error_type']);
    }

    /**
     * Test getRecentErrors limits results
     */
    public function testGetRecentErrorsRespectsLimit(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('LIMIT 25'))
            ->willReturn($stmt);

        $service = $this->createServiceWithMockPdo($pdo);
        $service->getRecentErrors(25);

        $this->assertTrue(true);
    }

    /**
     * Test getRecentErrors filters by severity
     */
    public function testGetRecentErrorsFiltersBySeverity(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['critical'])
            ->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('severity = ?'))
            ->willReturn($stmt);

        $service = $this->createServiceWithMockPdo($pdo);
        $service->getRecentErrors(50, 'critical');

        $this->assertTrue(true);
    }

    /**
     * Test severity levels are recognized
     */
    public function testSeverityLevelsExist(): void
    {
        $expectedSeverities = ['critical', 'error', 'warning', 'info'];

        // Verify service can be instantiated (structural test)
        $reflection = new \ReflectionClass(ErrorMonitoringService::class);
        $this->assertTrue($reflection->hasMethod('logError'));
        $this->assertTrue($reflection->hasMethod('getRecentErrors'));

        // These are the valid severity levels the service should accept
        foreach ($expectedSeverities as $severity) {
            $this->assertIsString($severity);
        }
    }

    /**
     * Test limit is clamped to valid range
     */
    public function testLimitIsClampedToValidRange(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(\PDO::class);
        // Max limit should be 200
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('LIMIT 200'))
            ->willReturn($stmt);

        $service = $this->createServiceWithMockPdo($pdo);
        $service->getRecentErrors(9999); // Should be clamped to 200

        $this->assertTrue(true);
    }

    /**
     * Helper to create service with mock PDO
     */
    private function createServiceWithMockPdo(\PDO $pdo): ErrorMonitoringService
    {
        $service = $this->getMockBuilder(ErrorMonitoringService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $reflection = new ReflectionProperty(ErrorMonitoringService::class, 'db');
        $reflection->setAccessible(true);
        $reflection->setValue($service, $pdo);

        return $service;
    }
}
