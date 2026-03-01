<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CloneHealthMonitorService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Comprehensive unit tests for CloneHealthMonitorService.
 *
 * Tests health monitoring checks, score calculation, diagnostics,
 * DB offline graceful degradation, threshold boundaries, and metrics.
 *
 * @covers \App\Services\CloneHealthMonitorService
 */
class CloneHealthMonitorServiceTest extends TestCase
{
    private CloneHealthMonitorService $service;
    private MockObject $mockPdo;
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPdo = $this->createMock(PDO::class);

        /** @var PDO $pdo */
        $pdo = $this->mockPdo;
        $this->pdo = $pdo;

        // DB-offline service via reflection for helper method tests
        $ref = new ReflectionClass(CloneHealthMonitorService::class);
        $instance = $ref->newInstanceWithoutConstructor();

        $dbProp = $ref->getProperty('db');
        $dbProp->setAccessible(true);
        $dbProp->setValue($instance, null);

        $accountProp = $ref->getProperty('accountId');
        $accountProp->setAccessible(true);
        $accountProp->setValue($instance, 0);

        $dbErrorProp = $ref->getProperty('dbError');
        $dbErrorProp->setAccessible(true);
        $dbErrorProp->setValue($instance, 'unit-test');

        $this->service = $instance;
    }

    // =========================================================================
    // HELPER: Mock PDOStatement factories
    // =========================================================================

    private function createMockStmt(
        null|bool|int|float|string|array $fetchColumnReturn = null,
        null|bool|int|float|string|array $fetchReturn = null,
        null|bool|int|float|string|array $fetchAllReturn = null
    ): PDOStatement {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        if ($fetchColumnReturn !== null) {
            $stmt->method('fetchColumn')->willReturn($fetchColumnReturn);
        }
        if ($fetchReturn !== null) {
            $stmt->method('fetch')->willReturn($fetchReturn);
        }
        if ($fetchAllReturn !== null) {
            $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        }

        return $stmt;
    }

    private function createFailingStmt(): PDOStatement
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willThrowException(new \RuntimeException('DB error'));
        return $stmt;
    }

    /**
     * @param PDOStatement[] $stmts
     */
    private function configurePrepareSequence(array $stmts): void
    {
        $this->mockPdo
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(...$stmts);
    }

    /**
     * Build a standard 9-stmt sequence for getSystemHealth (7 checks + 2 metrics).
     * Disk space (check 8) uses disk_free_space(), no DB call.
     *
     * @param array<string, array|int|string|float|bool|null> $overrides  Keys: active_jobs, stuck_jobs, error_rate, queue_size,
     *                                           recent_activity, workers, api_connectivity, metrics_24h, metrics_1h
     * @return PDOStatement[]
     */
    private function buildHealthStmts(array $overrides = []): array
    {
        return [
            // Check 1: active_jobs
            $this->createMockStmt(fetchColumnReturn: $overrides['active_jobs'] ?? 0),
            // Check 2: stuck_jobs
            $this->createMockStmt(fetchColumnReturn: $overrides['stuck_jobs'] ?? 0),
            // Check 3: error_rate
            $this->createMockStmt(fetchReturn: $overrides['error_rate'] ?? ['success' => 100, 'failed' => 0]),
            // Check 4: queue_size
            $this->createMockStmt(fetchColumnReturn: $overrides['queue_size'] ?? 0),
            // Check 5: recent_activity
            $this->createMockStmt(fetchColumnReturn: $overrides['recent_activity'] ?? date('Y-m-d H:i:s')),
            // Check 6: workers
            $this->createMockStmt(fetchColumnReturn: $overrides['workers'] ?? 1),
            // Check 7: api_connectivity
            $this->createMockStmt(fetchColumnReturn: $overrides['api_connectivity'] ?? 0),
            // getQuickMetrics: 24h
            $this->createMockStmt(fetchReturn: $overrides['metrics_24h'] ?? [
                'jobs_24h' => 10,
                'items_24h' => 100,
                'success_24h' => 100,
                'failed_24h' => 0,
            ]),
            // getQuickMetrics: 1h
            $this->createMockStmt(fetchReturn: $overrides['metrics_1h'] ?? [
                'jobs_1h' => 1,
                'items_1h' => 10,
            ]),
        ];
    }

    // =========================================================================
    // INSTANTIATION TESTS
    // =========================================================================

    public function testServiceCanBeInstantiatedWithMockPdo(): void
    {
        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $this->assertInstanceOf(CloneHealthMonitorService::class, $svc);
    }

    public function testServiceCanBeInstantiatedWithNullDb(): void
    {
        $svc = new CloneHealthMonitorService(0, null);
        $this->assertInstanceOf(CloneHealthMonitorService::class, $svc);
    }

    public function testServiceAcceptsAccountId(): void
    {
        $svc = new CloneHealthMonitorService(12345, $this->pdo);
        $this->assertInstanceOf(CloneHealthMonitorService::class, $svc);
    }

    public function testServiceHasRequiredPublicMethods(): void
    {
        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $this->assertTrue(method_exists($svc, 'getSystemHealth'));
        $this->assertTrue(method_exists($svc, 'runDiagnostics'));
        $this->assertTrue(method_exists($svc, 'getHealthHistory'));
        $this->assertTrue(method_exists($svc, 'logHealthCheck'));
    }

    // =========================================================================
    // DB OFFLINE GRACEFUL DEGRADATION
    // =========================================================================

    public function testGetSystemHealthReturnsCriticalWhenDbOffline(): void
    {
        $health = $this->service->getSystemHealth();

        $this->assertSame('critical', $health['status']);
        $this->assertSame(0, $health['score']);
        $this->assertIsArray($health['checks']);
        $this->assertArrayHasKey('database', $health['checks']);
        $this->assertSame('critical', $health['checks']['database']['status']);
        $this->assertSame('offline', $health['checks']['database']['value']);
        $this->assertIsArray($health['issues']);
        $this->assertNotEmpty($health['issues']);
        $this->assertSame('critical', $health['issues'][0]['severity']);
    }

    public function testGetSystemHealthHasIssuesWhenDbOffline(): void
    {
        $health = $this->service->getSystemHealth();

        $this->assertSame('database', $health['issues'][0]['component']);
        $this->assertNotEmpty($health['issues'][0]['message']);
    }

    public function testGetSystemHealthHasTimestampWhenDbOffline(): void
    {
        $health = $this->service->getSystemHealth();

        $this->assertArrayHasKey('timestamp', $health);
        $this->assertNotEmpty($health['timestamp']);
    }

    public function testRunDiagnosticsWorksWhenDbOffline(): void
    {
        $diagnostics = $this->service->runDiagnostics();

        $this->assertIsArray($diagnostics);
        $this->assertArrayHasKey('timestamp', $diagnostics);
        $this->assertArrayHasKey('database', $diagnostics);
        $this->assertArrayHasKey('storage', $diagnostics);
        $this->assertArrayHasKey('jobs', $diagnostics);
        $this->assertArrayHasKey('performance', $diagnostics);
    }

    public function testRunDiagnosticsDatabaseTablesShowOffline(): void
    {
        $diagnostics = $this->service->runDiagnostics();

        foreach ($diagnostics['database'] as $table => $info) {
            $this->assertFalse($info['exists']);
            $this->assertSame('DB offline', $info['error']);
        }
    }

    public function testRunDiagnosticsJobsShowOffline(): void
    {
        $diagnostics = $this->service->runDiagnostics();

        $this->assertArrayHasKey('error', $diagnostics['jobs']);
        $this->assertSame('DB offline', $diagnostics['jobs']['error']);
    }

    public function testRunDiagnosticsPerformanceShowOffline(): void
    {
        $diagnostics = $this->service->runDiagnostics();

        $this->assertArrayHasKey('error', $diagnostics['performance']);
        $this->assertSame('DB offline', $diagnostics['performance']['error']);
    }

    public function testGetHealthHistoryReturnsEmptyWhenDbOffline(): void
    {
        $history = $this->service->getHealthHistory();

        $this->assertIsArray($history);
        $this->assertEmpty($history);
    }

    public function testLogHealthCheckIsNoopWhenDbOffline(): void
    {
        $this->service->logHealthCheck([
            'status' => 'healthy',
            'issues' => [],
        ]);

        $this->assertTrue(true); // no exception
    }

    // =========================================================================
    // HELPER METHODS (via Reflection)
    // =========================================================================

    public function testDetermineOverallStatusMapsToHealthyWarningCritical(): void
    {
        $method = new ReflectionMethod(CloneHealthMonitorService::class, 'determineOverallStatus');
        $method->setAccessible(true);

        $this->assertSame('healthy', $method->invoke($this->service, ['ok', 'ok']));
        $this->assertSame('warning', $method->invoke($this->service, ['ok', 'warning']));
        $this->assertSame('warning', $method->invoke($this->service, ['ok', 'unknown']));
        $this->assertSame('critical', $method->invoke($this->service, ['ok', 'critical']));
    }

    public function testDetermineOverallStatusCriticalTrumpsWarning(): void
    {
        $method = new ReflectionMethod(CloneHealthMonitorService::class, 'determineOverallStatus');
        $method->setAccessible(true);

        $this->assertSame('critical', $method->invoke($this->service, ['warning', 'critical', 'ok']));
    }

    public function testCalculateHealthScoreClampsRange(): void
    {
        $method = new ReflectionMethod(CloneHealthMonitorService::class, 'calculateHealthScore');
        $method->setAccessible(true);

        $this->assertSame(100, $method->invoke($this->service, []));
        $this->assertSame(100, $method->invoke($this->service, ['ok']));
        $this->assertSame(90, $method->invoke($this->service, ['warning']));
        $this->assertSame(75, $method->invoke($this->service, ['critical']));
        $this->assertSame(70, $method->invoke($this->service, ['unknown']));
        $this->assertSame(80, $method->invoke($this->service, ['warning', 'warning']));
        $this->assertSame(0, $method->invoke($this->service, array_fill(0, 20, 'unknown')));
    }

    public function testCalculateHealthScoreNeverNegative(): void
    {
        $method = new ReflectionMethod(CloneHealthMonitorService::class, 'calculateHealthScore');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, array_fill(0, 10, 'critical'));
        $this->assertSame(0, $result);
    }

    public function testBuildIssuesFromChecksBuildsStructuredIssues(): void
    {
        $method = new ReflectionMethod(CloneHealthMonitorService::class, 'buildIssuesFromChecks');
        $method->setAccessible(true);

        $checks = [
            'active_jobs' => ['status' => 'ok', 'message' => 'ok'],
            'queue_size' => ['status' => 'warning', 'message' => 'Fila elevada'],
            'database' => ['status' => 'critical', 'message' => 'DB offline'],
            'workers' => ['status' => 'unknown'],
        ];

        $result = $method->invoke($this->service, $checks);
        $this->assertCount(3, $result);

        $this->assertSame('warning', $result[0]['severity']);
        $this->assertSame('queue_size', $result[0]['component']);
        $this->assertSame('Fila elevada', $result[0]['message']);

        $this->assertSame('critical', $result[1]['severity']);
        $this->assertSame('database', $result[1]['component']);

        $this->assertSame('warning', $result[2]['severity']);
        $this->assertSame('workers', $result[2]['component']);
    }

    public function testBuildIssuesFromChecksSkipsOkStatus(): void
    {
        $method = new ReflectionMethod(CloneHealthMonitorService::class, 'buildIssuesFromChecks');
        $method->setAccessible(true);

        $checks = [
            'a' => ['status' => 'ok', 'message' => 'fine'],
            'b' => ['status' => 'ok', 'message' => 'also fine'],
        ];

        $result = $method->invoke($this->service, $checks);
        $this->assertEmpty($result);
    }

    public function testBuildIssuesFromChecksGeneratesDefaultMessage(): void
    {
        $method = new ReflectionMethod(CloneHealthMonitorService::class, 'buildIssuesFromChecks');
        $method->setAccessible(true);

        $checks = [
            'some_component' => ['status' => 'critical', 'message' => ''],
        ];

        $result = $method->invoke($this->service, $checks);
        $this->assertCount(1, $result);
        $this->assertStringContains('some_component', $result[0]['message']);
    }

    // =========================================================================
    // getSystemHealth — ALL CHECKS HEALTHY (with mock PDO)
    // =========================================================================

    public function testGetSystemHealthReturnsHealthyWhenAllChecksPass(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts());

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('score', $health);
        $this->assertArrayHasKey('timestamp', $health);
        $this->assertArrayHasKey('checks', $health);
        $this->assertArrayHasKey('issues', $health);
        $this->assertArrayHasKey('metrics', $health);

        // All 8 checks present
        $expectedChecks = [
            'active_jobs',
            'stuck_jobs',
            'error_rate',
            'queue_size',
            'recent_activity',
            'workers',
            'api_connectivity',
            'disk_space',
        ];
        foreach ($expectedChecks as $check) {
            $this->assertArrayHasKey($check, $health['checks'], "Missing check: $check");
        }
    }

    public function testGetSystemHealthScoreIs100WhenAllOk(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts());

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame(100, $health['score']);
        $this->assertSame('healthy', $health['status']);
        $this->assertEmpty($health['issues']);
    }

    public function testGetSystemHealthScoreBetween0And100(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts());

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertGreaterThanOrEqual(0, $health['score']);
        $this->assertLessThanOrEqual(100, $health['score']);
    }

    public function testGetSystemHealthStatusIsValidValue(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts());

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertContains($health['status'], ['healthy', 'warning', 'critical']);
    }

    // =========================================================================
    // getSystemHealth — CRITICAL SCENARIOS
    // =========================================================================

    public function testGetSystemHealthReportsStuckJobsAsCritical(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts(['stuck_jobs' => 3]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('critical', $health['checks']['stuck_jobs']['status']);
        $this->assertSame(3, $health['checks']['stuck_jobs']['count']);
        $this->assertSame('critical', $health['status']);
    }

    public function testGetSystemHealthReportsCriticalErrorRate(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts([
            'error_rate' => ['success' => 40, 'failed' => 60],
        ]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('critical', $health['checks']['error_rate']['status']);
        $this->assertSame('critical', $health['status']);
    }

    public function testGetSystemHealthReportsCriticalQueueSize(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts(['queue_size' => 600]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('critical', $health['checks']['queue_size']['status']);
        $this->assertSame(600, $health['checks']['queue_size']['count']);
    }

    public function testGetSystemHealthReportsCriticalApiErrors(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts(['api_connectivity' => 15]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('critical', $health['checks']['api_connectivity']['status']);
        $this->assertSame(15, $health['checks']['api_connectivity']['errors']);
    }

    // =========================================================================
    // getSystemHealth — WARNING SCENARIOS
    // =========================================================================

    public function testGetSystemHealthReportsWarningErrorRate(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts([
            'error_rate' => ['success' => 75, 'failed' => 25],
        ]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('warning', $health['checks']['error_rate']['status']);
        $this->assertSame('warning', $health['status']);
    }

    public function testGetSystemHealthReportsWarningQueueSize(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts(['queue_size' => 150]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('warning', $health['checks']['queue_size']['status']);
        $this->assertSame(150, $health['checks']['queue_size']['count']);
    }

    public function testGetSystemHealthReportsWarningApiErrors(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts(['api_connectivity' => 5]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('warning', $health['checks']['api_connectivity']['status']);
        $this->assertSame(5, $health['checks']['api_connectivity']['errors']);
    }

    public function testGetSystemHealthReportsWarningNoWorkers(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts(['workers' => 0]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('warning', $health['checks']['workers']['status']);
        $this->assertSame(0, $health['checks']['workers']['active']);
    }

    // =========================================================================
    // ERROR RATE BOUNDARY TESTS
    // =========================================================================

    public function testErrorRateZeroWhenNoProcessing(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts([
            'error_rate' => ['success' => 0, 'failed' => 0],
        ]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('ok', $health['checks']['error_rate']['status']);
        $this->assertSame(0, $health['checks']['error_rate']['rate']);
    }

    public function testErrorRateBoundaryAt20PercentIsWarning(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts([
            'error_rate' => ['success' => 80, 'failed' => 20],
        ]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('warning', $health['checks']['error_rate']['status']);
    }

    public function testErrorRateBelow20PercentIsOk(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts([
            'error_rate' => ['success' => 85, 'failed' => 15],
        ]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('ok', $health['checks']['error_rate']['status']);
    }

    public function testErrorRateBoundaryAt50PercentIsCritical(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts([
            'error_rate' => ['success' => 50, 'failed' => 50],
        ]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('critical', $health['checks']['error_rate']['status']);
    }

    // =========================================================================
    // QUEUE SIZE BOUNDARY TESTS
    // =========================================================================

    public function testQueueAt99IsOk(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts(['queue_size' => 99]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('ok', $health['checks']['queue_size']['status']);
    }

    public function testQueueAt100IsWarning(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts(['queue_size' => 100]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('warning', $health['checks']['queue_size']['status']);
    }

    public function testQueueAt499IsWarning(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts(['queue_size' => 499]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('warning', $health['checks']['queue_size']['status']);
    }

    public function testQueueAt500IsCritical(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts(['queue_size' => 500]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('critical', $health['checks']['queue_size']['status']);
    }

    // =========================================================================
    // RECENT ACTIVITY EDGE CASES
    // =========================================================================

    public function testRecentActivityOkWhenNoJobs(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts([
            'recent_activity' => false,
        ]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('ok', $health['checks']['recent_activity']['status']);
    }

    public function testRecentActivityWarningWhenStale(): void
    {
        $staleTime = date('Y-m-d H:i:s', time() - 130 * 60); // 130 min ago (threshold=120)

        $this->configurePrepareSequence($this->buildHealthStmts([
            'recent_activity' => $staleTime,
        ]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('warning', $health['checks']['recent_activity']['status']);
        $this->assertGreaterThanOrEqual(120, $health['checks']['recent_activity']['minutes_since_last_update']);
    }

    public function testRecentActivityUnknownOnInvalidTimestamp(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts([
            'recent_activity' => 'not-a-date',
        ]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('unknown', $health['checks']['recent_activity']['status']);
    }

    // =========================================================================
    // DB QUERY FAILURE HANDLING
    // =========================================================================

    public function testActiveJobsQueryFailureReturnsCritical(): void
    {
        $stmts = $this->buildHealthStmts();
        $stmts[0] = $this->createFailingStmt(); // active_jobs fails

        $this->configurePrepareSequence($stmts);
        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('critical', $health['checks']['active_jobs']['status']);
        $this->assertArrayHasKey('error', $health['checks']['active_jobs']);
    }

    public function testStuckJobsQueryFailureReturnsCritical(): void
    {
        $stmts = $this->buildHealthStmts();
        $stmts[1] = $this->createFailingStmt();

        $this->configurePrepareSequence($stmts);
        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('critical', $health['checks']['stuck_jobs']['status']);
        $this->assertArrayHasKey('error', $health['checks']['stuck_jobs']);
    }

    public function testErrorRateQueryFailureReturnsCritical(): void
    {
        $stmts = $this->buildHealthStmts();
        $stmts[2] = $this->createFailingStmt();

        $this->configurePrepareSequence($stmts);
        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('critical', $health['checks']['error_rate']['status']);
        $this->assertArrayHasKey('error', $health['checks']['error_rate']);
    }

    public function testQueueSizeQueryFailureReturnsCritical(): void
    {
        $stmts = $this->buildHealthStmts();
        $stmts[3] = $this->createFailingStmt();

        $this->configurePrepareSequence($stmts);
        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('critical', $health['checks']['queue_size']['status']);
        $this->assertArrayHasKey('error', $health['checks']['queue_size']);
    }

    // =========================================================================
    // SCORE CALCULATION (integrated)
    // =========================================================================

    public function testScoreDecreasesWithWarnings(): void
    {
        // 1 warning: error_rate at 25%
        $this->configurePrepareSequence($this->buildHealthStmts([
            'error_rate' => ['success' => 75, 'failed' => 25],
        ]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame(90, $health['score']); // -10 for 1 warning
    }

    public function testScoreDecreasesMoreWithCritical(): void
    {
        // 1 critical: stuck_jobs > 0
        $this->configurePrepareSequence($this->buildHealthStmts(['stuck_jobs' => 2]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame(75, $health['score']); // -25 for 1 critical
    }

    public function testScoreNeverGoesBelowZero(): void
    {
        // All checks fail
        $failStmts = array_fill(0, 11, $this->createFailingStmt());

        $this->configurePrepareSequence($failStmts);
        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertGreaterThanOrEqual(0, $health['score']);
    }

    // =========================================================================
    // ISSUES ARRAY
    // =========================================================================

    public function testNoIssuesWhenAllOk(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts());

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertEmpty($health['issues']);
    }

    public function testIssuesHaveSeverityComponentMessage(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts(['stuck_jobs' => 5]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertNotEmpty($health['issues']);
        $issue = $health['issues'][0];
        $this->assertArrayHasKey('severity', $issue);
        $this->assertArrayHasKey('component', $issue);
        $this->assertArrayHasKey('message', $issue);
        $this->assertSame('critical', $issue['severity']);
        $this->assertSame('stuck_jobs', $issue['component']);
    }

    // =========================================================================
    // METRICS STRUCTURE
    // =========================================================================

    public function testMetricsStructure(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts([
            'metrics_24h' => [
                'jobs_24h' => 50,
                'items_24h' => 500,
                'success_24h' => 480,
                'failed_24h' => 20,
            ],
            'metrics_1h' => [
                'jobs_1h' => 5,
                'items_1h' => 50,
            ],
        ]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertArrayHasKey('last_24h', $health['metrics']);
        $this->assertArrayHasKey('last_hour', $health['metrics']);
        $this->assertArrayHasKey('throughput', $health['metrics']);

        $this->assertSame(50, $health['metrics']['last_24h']['jobs']);
        $this->assertSame(500, $health['metrics']['last_24h']['items']);
        $this->assertSame(480, $health['metrics']['last_24h']['success']);
        $this->assertSame(20, $health['metrics']['last_24h']['failed']);

        $this->assertSame(5, $health['metrics']['last_hour']['jobs']);
        $this->assertSame(50, $health['metrics']['last_hour']['items']);

        $this->assertSame(50, $health['metrics']['throughput']['items_per_hour']);
        $this->assertSame(500, $health['metrics']['throughput']['items_per_day']);
    }

    // =========================================================================
    // ACCOUNT FILTERING
    // =========================================================================

    public function testGetSystemHealthWorksWithAccountFilter(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts());

        $svc = new CloneHealthMonitorService(12345, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
    }

    // =========================================================================
    // WORKERS / API CONNECTIVITY — TABLE MISSING GRACEFUL DEGRADATION
    // =========================================================================

    public function testWorkersFallbackWhenTableMissing(): void
    {
        $workerFail = $this->createMock(PDOStatement::class);
        $workerFail->method('execute')
            ->willThrowException(new \RuntimeException("Table 'worker_execution_logs' doesn't exist"));

        $stmts = $this->buildHealthStmts();
        $stmts[5] = $workerFail; // workers check

        $this->configurePrepareSequence($stmts);
        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('ok', $health['checks']['workers']['status']);
    }

    public function testApiConnectivityFallbackWhenTableMissing(): void
    {
        $apiFail = $this->createMock(PDOStatement::class);
        $apiFail->method('execute')
            ->willThrowException(new \RuntimeException("Table 'clone_sync_logs' doesn't exist"));

        $stmts = $this->buildHealthStmts();
        $stmts[6] = $apiFail; // api_connectivity check

        $this->configurePrepareSequence($stmts);
        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('ok', $health['checks']['api_connectivity']['status']);
    }

    // =========================================================================
    // DISK SPACE CHECK
    // =========================================================================

    public function testDiskSpaceCheckReturnsValidStatus(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts());

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $diskCheck = $health['checks']['disk_space'];
        $this->assertContains($diskCheck['status'], ['ok', 'warning', 'critical', 'unknown']);

        if ($diskCheck['status'] !== 'unknown') {
            $this->assertArrayHasKey('free_gb', $diskCheck);
            $this->assertIsFloat($diskCheck['free_gb']);
        }
    }

    // =========================================================================
    // OVERALL STATUS DETERMINATION (integrated)
    // =========================================================================

    public function testOverallStatusCriticalWhenAnyCritical(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts(['stuck_jobs' => 1]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('critical', $health['status']);
    }

    public function testOverallStatusWarningWhenUnknownPresent(): void
    {
        $this->configurePrepareSequence($this->buildHealthStmts([
            'recent_activity' => 'not-a-date',
        ]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('unknown', $health['checks']['recent_activity']['status']);
        $this->assertSame('warning', $health['status']);
    }

    // =========================================================================
    // runDiagnostics WITH PDO MOCK
    // =========================================================================

    public function testRunDiagnosticsReturnsCorrectStructure(): void
    {
        $tableStmt = $this->createMockStmt(fetchColumnReturn: 42);

        $byStatusStmt = $this->createMock(PDOStatement::class);
        $byStatusStmt->method('execute')->willReturn(true);
        $byStatusStmt->method('fetch')
            ->willReturnOnConsecutiveCalls(
                ['status' => 'completed', 'count' => 10],
                ['status' => 'failed', 'count' => 2],
                false
            );

        $recentStmt = $this->createMockStmt(fetchAllReturn: [
            [
                'job_id' => 1,
                'status' => 'completed',
                'total_items' => 10,
                'successful_items' => 10,
                'failed_items' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ],
        ]);

        $perfStmt = $this->createMockStmt(fetchReturn: [
            'avg_duration_seconds' => 120.5,
            'avg_items_per_job' => 25.0,
            'items_per_second' => 0.2,
        ]);

        $this->mockPdo->method('query')
            ->willReturn(
                $tableStmt,
                $tableStmt,
                $tableStmt,
                $tableStmt,
                $tableStmt,
                $tableStmt,
                $perfStmt
            );

        $this->mockPdo->method('prepare')
            ->willReturnOnConsecutiveCalls($byStatusStmt, $recentStmt);

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $diagnostics = $svc->runDiagnostics();

        $this->assertArrayHasKey('timestamp', $diagnostics);
        $this->assertArrayHasKey('database', $diagnostics);
        $this->assertArrayHasKey('storage', $diagnostics);
        $this->assertArrayHasKey('jobs', $diagnostics);
        $this->assertArrayHasKey('performance', $diagnostics);
    }

    public function testRunDiagnosticsDatabaseChecksRequiredTables(): void
    {
        $tableStmt = $this->createMockStmt(fetchColumnReturn: 0);

        $this->mockPdo->method('query')->willReturn($tableStmt);
        $this->mockPdo->method('prepare')->willReturn(
            $this->createMockStmt(fetchReturn: false),
            $this->createMockStmt(fetchAllReturn: []),
        );

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $diagnostics = $svc->runDiagnostics();

        $requiredTables = [
            'cloned_items',
            'catalog_clone_jobs',
            'catalog_clone_job_items',
            'clone_templates',
            'clone_item_metrics',
            'clone_sync_logs',
        ];

        foreach ($requiredTables as $table) {
            $this->assertArrayHasKey($table, $diagnostics['database'], "Missing table: $table");
        }
    }

    public function testRunDiagnosticsStorageChecksPaths(): void
    {
        $this->mockPdo->method('query')->willReturn($this->createMockStmt(fetchColumnReturn: 0));
        $this->mockPdo->method('prepare')->willReturn(
            $this->createMockStmt(fetchReturn: false),
            $this->createMockStmt(fetchAllReturn: []),
        );

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $diagnostics = $svc->runDiagnostics();

        $this->assertArrayHasKey('logs', $diagnostics['storage']);
        $this->assertArrayHasKey('cache', $diagnostics['storage']);
        $this->assertArrayHasKey('exports', $diagnostics['storage']);

        foreach ($diagnostics['storage'] as $info) {
            $this->assertArrayHasKey('path', $info);
            $this->assertArrayHasKey('exists', $info);
            $this->assertArrayHasKey('writable', $info);
            $this->assertArrayHasKey('size_mb', $info);
        }
    }

    public function testRunDiagnosticsPerformanceReturnsNumericValues(): void
    {
        $tableStmt = $this->createMockStmt(fetchColumnReturn: 0);
        $perfStmt = $this->createMockStmt(fetchReturn: [
            'avg_duration_seconds' => 60.5,
            'avg_items_per_job' => 15.5,
            'items_per_second' => 0.25,
        ]);

        $this->mockPdo->method('query')
            ->willReturn(
                $tableStmt,
                $tableStmt,
                $tableStmt,
                $tableStmt,
                $tableStmt,
                $tableStmt,
                $perfStmt
            );

        $this->mockPdo->method('prepare')->willReturn(
            $this->createMockStmt(fetchReturn: false),
            $this->createMockStmt(fetchAllReturn: []),
        );

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $diagnostics = $svc->runDiagnostics();

        $this->assertIsFloat($diagnostics['performance']['avg_job_duration_seconds']);
        $this->assertIsFloat($diagnostics['performance']['avg_items_per_job']);
        $this->assertIsFloat($diagnostics['performance']['items_per_second']);
    }

    // =========================================================================
    // getHealthHistory TESTS
    // =========================================================================

    public function testGetHealthHistoryReturnsArray(): void
    {
        $stmt = $this->createMockStmt(fetchAllReturn: [
            ['hour' => '2024-01-01 10:00:00', 'checks' => 5, 'uptime_percent' => 100.0],
            ['hour' => '2024-01-01 11:00:00', 'checks' => 5, 'uptime_percent' => 80.0],
        ]);
        $this->mockPdo->method('prepare')->willReturn($stmt);

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $history = $svc->getHealthHistory(24);

        $this->assertIsArray($history);
        $this->assertCount(2, $history);
    }

    public function testGetHealthHistoryClamps168Hours(): void
    {
        $stmt = $this->createMockStmt(fetchAllReturn: []);
        $this->mockPdo->method('prepare')->willReturn($stmt);

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $history = $svc->getHealthHistory(9999);

        $this->assertIsArray($history);
    }

    public function testGetHealthHistoryHandlesQueryFailure(): void
    {
        $this->mockPdo->method('prepare')->willReturn($this->createFailingStmt());

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $history = $svc->getHealthHistory();

        $this->assertIsArray($history);
        $this->assertEmpty($history);
    }

    // =========================================================================
    // logHealthCheck TESTS
    // =========================================================================

    public function testLogHealthCheckInsertsRecord(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->willReturn(true);

        $this->mockPdo->method('prepare')->willReturn($stmt);

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $svc->logHealthCheck(['status' => 'healthy', 'issues' => []]);

        $this->assertTrue(true);
    }

    public function testLogHealthCheckHandlesInsertFailureGracefully(): void
    {
        $this->mockPdo->method('prepare')->willReturn($this->createFailingStmt());

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $svc->logHealthCheck(['status' => 'critical', 'issues' => ['test']]);

        $this->assertTrue(true);
    }

    // =========================================================================
    // ML CLIENT INTEGRATION — API CONNECTIVITY VIA MercadoLivreClient
    // =========================================================================

    /**
     * Build 8-stmt sequence for getSystemHealth when ML client is injected.
     * api_connectivity uses ML client (no DB stmt), so 6 DB checks + 2 metrics = 8.
     *
     * @param array<string, array|int|string|float|bool|null> $overrides
     * @return PDOStatement[]
     */
    private function buildHealthStmtsForMLClient(array $overrides = []): array
    {
        return [
            // Check 1: active_jobs
            $this->createMockStmt(fetchColumnReturn: $overrides['active_jobs'] ?? 0),
            // Check 2: stuck_jobs
            $this->createMockStmt(fetchColumnReturn: $overrides['stuck_jobs'] ?? 0),
            // Check 3: error_rate
            $this->createMockStmt(fetchReturn: $overrides['error_rate'] ?? ['success' => 100, 'failed' => 0]),
            // Check 4: queue_size
            $this->createMockStmt(fetchColumnReturn: $overrides['queue_size'] ?? 0),
            // Check 5: recent_activity
            $this->createMockStmt(fetchColumnReturn: $overrides['recent_activity'] ?? date('Y-m-d H:i:s')),
            // Check 6: workers
            $this->createMockStmt(fetchColumnReturn: $overrides['workers'] ?? 1),
            // Check 7: api_connectivity — uses ML client, NO DB stmt
            // getQuickMetrics: 24h
            $this->createMockStmt(fetchReturn: $overrides['metrics_24h'] ?? [
                'jobs_24h' => 10,
                'items_24h' => 100,
                'success_24h' => 100,
                'failed_24h' => 0,
            ]),
            // getQuickMetrics: 1h
            $this->createMockStmt(fetchReturn: $overrides['metrics_1h'] ?? [
                'jobs_1h' => 1,
                'items_1h' => 10,
            ]),
        ];
    }

    /**
     * @return MockObject&\App\Services\MercadoLivreClient
     */
    private function createMockMlClient(array $diagnoseReturn): MockObject
    {
        $mock = $this->createMock(\App\Services\MercadoLivreClient::class);
        $mock->method('diagnose')->willReturn($diagnoseReturn);
        return $mock;
    }

    private function createFailingMlClient(\Throwable $exception): MockObject
    {
        $mock = $this->createMock(\App\Services\MercadoLivreClient::class);
        $mock->method('diagnose')->willThrowException($exception);
        return $mock;
    }

    public function testMlClientConnectedReturnsApiOk(): void
    {
        $mlClient = $this->createMockMlClient([
            'connected' => true,
            'has_token' => true,
            'account_id' => 123,
            'token_source' => 'database',
            'db_unavailable' => false,
            'seller_id' => 456789,
            'user_info' => ['nickname' => 'AWAMOTOS'],
            'token_status' => 'valid',
            'api_accessible' => true,
            'items_count' => 42,
            'error' => null,
            'checks' => ['token' => true, 'public_api' => true, 'auth' => true, 'items' => true],
            'token_valid' => true,
            'public_api' => true,
            'auth_ok' => true,
        ]);

        $this->configurePrepareSequence($this->buildHealthStmtsForMLClient());

        /** @var \App\Services\MercadoLivreClient $client */
        $client = $mlClient;
        $svc = new CloneHealthMonitorService(0, $this->pdo, $client);
        $health = $svc->getSystemHealth();

        $this->assertSame('ok', $health['checks']['api_connectivity']['status']);
        $this->assertSame('conectado', $health['checks']['api_connectivity']['value']);
        $this->assertTrue($health['checks']['api_connectivity']['connected']);
        $this->assertTrue($health['checks']['api_connectivity']['api_accessible']);
        $this->assertSame('valid', $health['checks']['api_connectivity']['token_status']);
        $this->assertSame(456789, $health['checks']['api_connectivity']['seller_id']);
        $this->assertSame(42, $health['checks']['api_connectivity']['items_count']);
    }

    public function testMlClientDisconnectedTokenMissingReturnsWarning(): void
    {
        $mlClient = $this->createMockMlClient([
            'connected' => false,
            'has_token' => false,
            'account_id' => null,
            'token_source' => 'none',
            'db_unavailable' => false,
            'seller_id' => null,
            'user_info' => null,
            'token_status' => 'missing',
            'api_accessible' => true,
            'items_count' => 0,
            'error' => null,
            'checks' => ['token' => false, 'public_api' => true, 'auth' => false, 'items' => false],
            'token_valid' => false,
            'public_api' => true,
            'auth_ok' => false,
        ]);

        $this->configurePrepareSequence($this->buildHealthStmtsForMLClient());

        /** @var \App\Services\MercadoLivreClient $client */
        $client = $mlClient;
        $svc = new CloneHealthMonitorService(0, $this->pdo, $client);
        $health = $svc->getSystemHealth();

        $this->assertSame('warning', $health['checks']['api_connectivity']['status']);
        $this->assertFalse($health['checks']['api_connectivity']['connected']);
        $this->assertTrue($health['checks']['api_connectivity']['api_accessible']);
        $this->assertSame('missing', $health['checks']['api_connectivity']['token_status']);
        $this->assertStringContainsString('ausente', $health['checks']['api_connectivity']['message']);
    }

    public function testMlClientDisconnectedTokenInvalidReturnsWarning(): void
    {
        $mlClient = $this->createMockMlClient([
            'connected' => false,
            'has_token' => true,
            'account_id' => 123,
            'token_source' => 'database',
            'db_unavailable' => false,
            'seller_id' => null,
            'user_info' => null,
            'token_status' => 'invalid',
            'api_accessible' => true,
            'items_count' => 0,
            'error' => 'Token expired',
            'checks' => ['token' => false, 'public_api' => true, 'auth' => false, 'items' => false],
            'token_valid' => false,
            'public_api' => true,
            'auth_ok' => false,
        ]);

        $this->configurePrepareSequence($this->buildHealthStmtsForMLClient());

        /** @var \App\Services\MercadoLivreClient $client */
        $client = $mlClient;
        $svc = new CloneHealthMonitorService(0, $this->pdo, $client);
        $health = $svc->getSystemHealth();

        $this->assertSame('warning', $health['checks']['api_connectivity']['status']);
        $this->assertFalse($health['checks']['api_connectivity']['connected']);
        $this->assertSame('invalid', $health['checks']['api_connectivity']['token_status']);
        $this->assertStringContainsString('reautentique', $health['checks']['api_connectivity']['message']);
    }

    public function testMlClientApiInaccessibleWithoutTokenReturnsWarning(): void
    {
        $mlClient = $this->createMockMlClient([
            'connected' => false,
            'has_token' => false,
            'account_id' => null,
            'token_source' => 'none',
            'db_unavailable' => false,
            'seller_id' => null,
            'user_info' => null,
            'token_status' => 'unknown',
            'api_accessible' => false,
            'public_api_status' => 'failed',
            'public_api_policy_blocked' => false,
            'items_count' => 0,
            'error' => 'Connection timeout',
            'checks' => ['token' => false, 'public_api' => false, 'auth' => false, 'items' => false],
            'token_valid' => false,
            'public_api' => false,
            'auth_ok' => false,
        ]);

        $this->configurePrepareSequence($this->buildHealthStmtsForMLClient());

        /** @var \App\Services\MercadoLivreClient $client */
        $client = $mlClient;
        $svc = new CloneHealthMonitorService(0, $this->pdo, $client);
        $health = $svc->getSystemHealth();

        $this->assertSame('warning', $health['checks']['api_connectivity']['status']);
        $this->assertFalse($health['checks']['api_connectivity']['connected']);
        $this->assertFalse($health['checks']['api_connectivity']['api_accessible']);
        $this->assertStringContainsString('Sem token/conta ativa', $health['checks']['api_connectivity']['message']);
    }

    public function testMlClientApiInaccessibleWithTokenContextReturnsCritical(): void
    {
        $mlClient = $this->createMockMlClient([
            'connected' => false,
            'has_token' => true,
            'account_id' => 123,
            'token_source' => 'db',
            'db_unavailable' => false,
            'seller_id' => null,
            'user_info' => null,
            'token_status' => 'valid',
            'api_accessible' => false,
            'public_api_status' => 'failed',
            'public_api_policy_blocked' => false,
            'items_count' => 0,
            'error' => 'Connection timeout',
            'checks' => ['token' => true, 'public_api' => false, 'auth' => false, 'items' => false],
            'token_valid' => false,
            'public_api' => false,
            'auth_ok' => false,
        ]);

        $this->configurePrepareSequence($this->buildHealthStmtsForMLClient());

        /** @var \App\Services\MercadoLivreClient $client */
        $client = $mlClient;
        $svc = new CloneHealthMonitorService(0, $this->pdo, $client);
        $health = $svc->getSystemHealth();

        $this->assertSame('critical', $health['checks']['api_connectivity']['status']);
        $this->assertFalse($health['checks']['api_connectivity']['connected']);
        $this->assertFalse($health['checks']['api_connectivity']['api_accessible']);
        $this->assertStringContainsString('inacessível', $health['checks']['api_connectivity']['message']);
    }

    public function testMlClientDiagnoseExceptionReturnsWarning(): void
    {
        $mlClient = $this->createFailingMlClient(
            new \RuntimeException('Guzzle timeout')
        );

        $this->configurePrepareSequence($this->buildHealthStmtsForMLClient());

        /** @var \App\Services\MercadoLivreClient $client */
        $client = $mlClient;
        $svc = new CloneHealthMonitorService(0, $this->pdo, $client);
        $health = $svc->getSystemHealth();

        $this->assertSame('warning', $health['checks']['api_connectivity']['status']);
        $this->assertStringContainsString('Guzzle timeout', $health['checks']['api_connectivity']['message']);
        $this->assertFalse($health['checks']['api_connectivity']['connected']);
    }

    public function testMlClientPublicEndpointPolicyBlockedReturnsWarningNotCritical(): void
    {
        $mlClient = $this->createMockMlClient([
            'connected' => false,
            'has_token' => false,
            'account_id' => null,
            'token_source' => 'none',
            'db_unavailable' => false,
            'seller_id' => null,
            'user_info' => null,
            'token_status' => 'missing',
            'api_accessible' => false,
            'public_api_status' => 'policy_blocked',
            'public_api_policy_blocked' => true,
            'items_count' => 0,
            'error' => 'PA_UNAUTHORIZED_RESULT_FROM_POLICIES',
            'checks' => ['token' => false, 'public_api' => 'policy_blocked', 'auth' => 'skipped (no token)', 'items' => false],
            'token_valid' => false,
            'public_api' => true,
            'auth_ok' => false,
        ]);

        $this->configurePrepareSequence($this->buildHealthStmtsForMLClient());

        /** @var \App\Services\MercadoLivreClient $client */
        $client = $mlClient;
        $svc = new CloneHealthMonitorService(0, $this->pdo, $client);
        $health = $svc->getSystemHealth();

        $this->assertSame('warning', $health['checks']['api_connectivity']['status']);
        $this->assertSame('policy_blocked', $health['checks']['api_connectivity']['public_api_status']);
        $this->assertTrue($health['checks']['api_connectivity']['policy_blocked']);
    }

    public function testMlClientConnectedDoesNotAffectOtherChecks(): void
    {
        $mlClient = $this->createMockMlClient([
            'connected' => true,
            'has_token' => true,
            'account_id' => 123,
            'token_source' => 'database',
            'db_unavailable' => false,
            'seller_id' => 456,
            'user_info' => ['nickname' => 'TEST'],
            'token_status' => 'valid',
            'api_accessible' => true,
            'items_count' => 10,
            'error' => null,
            'checks' => ['token' => true, 'public_api' => true, 'auth' => true, 'items' => true],
            'token_valid' => true,
            'public_api' => true,
            'auth_ok' => true,
        ]);

        $this->configurePrepareSequence($this->buildHealthStmtsForMLClient([
            'stuck_jobs' => 2, // force critical on another check
        ]));

        /** @var \App\Services\MercadoLivreClient $client */
        $client = $mlClient;
        $svc = new CloneHealthMonitorService(0, $this->pdo, $client);
        $health = $svc->getSystemHealth();

        // API is ok even though overall is critical
        $this->assertSame('ok', $health['checks']['api_connectivity']['status']);
        $this->assertSame('critical', $health['checks']['stuck_jobs']['status']);
        $this->assertSame('critical', $health['status']);
    }

    public function testMlClientScoreCorrectWhenConnected(): void
    {
        $mlClient = $this->createMockMlClient([
            'connected' => true,
            'has_token' => true,
            'account_id' => 123,
            'token_source' => 'database',
            'db_unavailable' => false,
            'seller_id' => 456,
            'user_info' => null,
            'token_status' => 'valid',
            'api_accessible' => true,
            'items_count' => 5,
            'error' => null,
            'checks' => ['token' => true, 'public_api' => true, 'auth' => true, 'items' => true],
            'token_valid' => true,
            'public_api' => true,
            'auth_ok' => true,
        ]);

        $this->configurePrepareSequence($this->buildHealthStmtsForMLClient());

        /** @var \App\Services\MercadoLivreClient $client */
        $client = $mlClient;
        $svc = new CloneHealthMonitorService(0, $this->pdo, $client);
        $health = $svc->getSystemHealth();

        $this->assertSame(100, $health['score']);
        $this->assertSame('healthy', $health['status']);
    }

    public function testWithoutMlClientFallsBackToDbLogs(): void
    {
        // This test verifies that without ML client, the DB log check is used (9 stmts)
        $this->configurePrepareSequence($this->buildHealthStmts(['api_connectivity' => 0]));

        $svc = new CloneHealthMonitorService(0, $this->pdo);
        $health = $svc->getSystemHealth();

        $this->assertSame('ok', $health['checks']['api_connectivity']['status']);
        $this->assertSame(0, $health['checks']['api_connectivity']['errors']);
        // No ML-specific fields when using DB fallback
        $this->assertArrayNotHasKey('connected', $health['checks']['api_connectivity']);
    }

    public function testServiceAcceptsMlClientInConstructor(): void
    {
        $mlClient = $this->createMock(\App\Services\MercadoLivreClient::class);

        /** @var \App\Services\MercadoLivreClient $client */
        $client = $mlClient;
        $svc = new CloneHealthMonitorService(0, $this->pdo, $client);
        $this->assertInstanceOf(CloneHealthMonitorService::class, $svc);
    }

    // =========================================================================
    // ASSERTION ALIAS (PHPUnit < 10 compat)
    // =========================================================================

    protected static function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        static::assertStringContainsString($needle, $haystack, $message);
    }
}
