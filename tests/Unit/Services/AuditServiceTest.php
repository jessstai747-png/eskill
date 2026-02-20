<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\AuditService;
use App\Services\AuditLogService;
use PDO;
use PDOStatement;

/**
 * @covers \App\Services\AuditService
 * @covers \App\Services\AuditLogService
 */
class AuditServiceTest extends TestCase
{
    private PDO $mockDb;
    private PDOStatement $mockStmt;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(PDO::class);
        $this->mockStmt = $this->createMock(PDOStatement::class);
    }

    // ── AuditService: Constructor ───────────────────────────────────

    public function testAuditServiceConstructorWithDb(): void
    {
        $service = new AuditService(db: $this->mockDb, skipDbAutoConnect: true);
        $this->assertInstanceOf(AuditService::class, $service);
    }

    public function testAuditServiceConstructorWithSkipDb(): void
    {
        $service = new AuditService(skipDbAutoConnect: true);
        $this->assertInstanceOf(AuditService::class, $service);
    }

    // ── AuditService: log() ─────────────────────────────────────────

    public function testAuditServiceLogExecutesInsert(): void
    {
        $service = new AuditService(db: $this->mockDb, skipDbAutoConnect: true);

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $result = $service->log(1, 'update', 'item', 'Changed title');
        $this->assertTrue($result);
    }

    public function testAuditServiceLogWithOldAndNewValues(): void
    {
        $service = new AuditService(db: $this->mockDb, skipDbAutoConnect: true);
        $capturedParams = [];

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });

        $old = ['title' => 'Old Title'];
        $new = ['title' => 'New Title'];
        $service->log(1, 'update', 'item', 'title change', $old, $new);

        $this->assertSame(json_encode($old), $capturedParams['old_value']);
        $this->assertSame(json_encode($new), $capturedParams['new_value']);
    }

    public function testAuditServiceLogReturnsFalseOnException(): void
    {
        $service = new AuditService(db: $this->mockDb, skipDbAutoConnect: true);

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willThrowException(new \PDOException('DB error'));

        $result = $service->log(1, 'delete', 'item');
        $this->assertFalse($result);
    }

    public function testAuditServiceLogReturnsFalseWhenDbNull(): void
    {
        $service = new AuditService(skipDbAutoConnect: true);
        $result = $service->log(1, 'update', 'item');
        $this->assertFalse($result);
    }

    public function testAuditServiceLogNullOldNewValues(): void
    {
        $service = new AuditService(db: $this->mockDb, skipDbAutoConnect: true);
        $capturedParams = [];

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;
                return true;
            });

        $service->log(5, 'view', 'dashboard');

        $this->assertNull($capturedParams['old_value']);
        $this->assertNull($capturedParams['new_value']);
    }

    // ── AuditService: getLogs() ─────────────────────────────────────

    public function testAuditServiceGetLogsReturnsArray(): void
    {
        $service = new AuditService(db: $this->mockDb, skipDbAutoConnect: true);
        $expected = [
            ['id' => 1, 'action' => 'login', 'user_name' => 'John'],
        ];

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute');

        $this->mockStmt->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expected);

        $result = $service->getLogs(50);
        $this->assertSame($expected, $result);
    }

    public function testAuditServiceGetLogsReturnsEmptyWhenDbNull(): void
    {
        $service = new AuditService(skipDbAutoConnect: true);
        $result = $service->getLogs();
        $this->assertSame([], $result);
    }

    public function testAuditServiceGetLogsLimitClamped(): void
    {
        $service = new AuditService(db: $this->mockDb, skipDbAutoConnect: true);

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function (string $sql) {
                $this->assertStringContainsString('LIMIT 500', $sql);
                return $this->mockStmt;
            });

        $this->mockStmt->expects($this->once())
            ->method('execute');

        $this->mockStmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $service->getLogs(9999);
    }

    // ── AuditLogService: Constructor ────────────────────────────────

    public function testAuditLogServiceConstructorWithDb(): void
    {
        $service = new AuditLogService(db: $this->mockDb, skipDbAutoConnect: true);
        $this->assertInstanceOf(AuditLogService::class, $service);
    }

    public function testAuditLogServiceConstructorWithSkipDb(): void
    {
        $service = new AuditLogService(skipDbAutoConnect: true);
        $this->assertInstanceOf(AuditLogService::class, $service);
    }

    // ── AuditLogService: log() ──────────────────────────────────────

    public function testAuditLogServiceLogExecutesPrepare(): void
    {
        $service = new AuditLogService(db: $this->mockDb, skipDbAutoConnect: true);

        // ensureAuditLogsTable calls getAttribute, exec
        $this->mockDb->expects($this->once())
            ->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn('mysql');

        $this->mockDb->method('exec');

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute');

        $service->log('item.update', 1, 100, ['item_id' => 'MLB123']);
    }

    public function testAuditLogServiceLogSkipsWhenDbNull(): void
    {
        $service = new AuditLogService(skipDbAutoConnect: true);
        // Should not throw, silently skip
        $service->log('item.update', 1, 100);
        $this->assertTrue(true); // If we reach here, no exception
    }

    public function testAuditLogServiceLogHandlesException(): void
    {
        $service = new AuditLogService(db: $this->mockDb, skipDbAutoConnect: true);

        $this->mockDb->method('getAttribute')->willReturn('mysql');
        $this->mockDb->method('exec');

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willThrowException(new \PDOException('Insert failed'));

        // Should not throw — handles gracefully
        $service->log('item.delete', 1, 100);
        $this->assertTrue(true);
    }

    // ── AuditLogService: getLogs() ──────────────────────────────────

    public function testAuditLogServiceGetLogsReturnsLogs(): void
    {
        $service = new AuditLogService(db: $this->mockDb, skipDbAutoConnect: true);
        $logs = [
            ['id' => 1, 'action' => 'login', 'data' => '{"ip":"127.0.0.1"}'],
            ['id' => 2, 'action' => 'update', 'data' => '{"field":"title"}'],
        ];

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute');

        $this->mockStmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn($logs);

        $result = $service->getLogs();

        $this->assertCount(2, $result);
        $this->assertSame(['ip' => '127.0.0.1'], $result[0]['data']);
        $this->assertSame(['field' => 'title'], $result[1]['data']);
    }

    public function testAuditLogServiceGetLogsWithFilters(): void
    {
        $service = new AuditLogService(db: $this->mockDb, skipDbAutoConnect: true);

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function (string $sql) {
                $this->assertStringContainsString('action = :action', $sql);
                $this->assertStringContainsString('user_id = :user_id', $sql);
                return $this->mockStmt;
            });

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (array $params) {
                $this->assertSame('login', $params['action']);
                $this->assertSame(5, $params['user_id']);
                return true;
            });

        $this->mockStmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $service->getLogs(['action' => 'login', 'user_id' => 5]);
    }

    public function testAuditLogServiceGetLogsReturnsEmptyWhenDbNull(): void
    {
        $service = new AuditLogService(skipDbAutoConnect: true);
        $result = $service->getLogs();
        $this->assertSame([], $result);
    }

    public function testAuditLogServiceGetLogsLimitClamped(): void
    {
        $service = new AuditLogService(db: $this->mockDb, skipDbAutoConnect: true);

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function (string $sql) {
                $this->assertStringContainsString('LIMIT 500', $sql);
                return $this->mockStmt;
            });

        $this->mockStmt->method('execute');
        $this->mockStmt->method('fetchAll')->willReturn([]);

        $service->getLogs(['limit' => 9999]);
    }

    public function testAuditLogServiceGetLogsMinLimit(): void
    {
        $service = new AuditLogService(db: $this->mockDb, skipDbAutoConnect: true);

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function (string $sql) {
                $this->assertStringContainsString('LIMIT 1', $sql);
                return $this->mockStmt;
            });

        $this->mockStmt->method('execute');
        $this->mockStmt->method('fetchAll')->willReturn([]);

        $service->getLogs(['limit' => -5]);
    }
}
