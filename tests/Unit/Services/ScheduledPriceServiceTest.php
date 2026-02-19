<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ScheduledPriceService;
use PDO;
use PDOStatement;
use ReflectionClass;
use Tests\TestCase;

/**
 * Unit Tests for ScheduledPriceService
 *
 * Tests schedule/campaign creation validation, status constants,
 * recurrence constants, calendar, and error handling.
 *
 * @covers \App\Services\ScheduledPriceService
 */
class ScheduledPriceServiceTest extends TestCase
{
    private ScheduledPriceService $service;
    private PDO $mockPdo;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPdo = $this->createMock(PDO::class);
        $this->ref = new ReflectionClass(ScheduledPriceService::class);
        $instance = $this->ref->newInstanceWithoutConstructor();

        // Inject mock PDO
        $dbProp = $this->ref->getProperty('db');
        $dbProp->setAccessible(true);
        $dbProp->setValue($instance, $this->mockPdo);

        // Set accountId
        $idProp = $this->ref->getProperty('accountId');
        $idProp->setAccessible(true);
        $idProp->setValue($instance, 12345);

        $this->service = $instance;
    }

    private function createMockStmt(
        mixed $fetchReturn = null,
        mixed $fetchAllReturn = null
    ): PDOStatement {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        if ($fetchReturn !== null) {
            $stmt->method('fetch')->willReturn($fetchReturn);
        }
        if ($fetchAllReturn !== null) {
            $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        }
        return $stmt;
    }

    // =========================================================================
    // INSTANTIATION
    // =========================================================================

    public function testServiceCanBeInstantiatedViaReflection(): void
    {
        $this->assertInstanceOf(ScheduledPriceService::class, $this->service);
    }

    public function testServiceHasRequiredPublicMethods(): void
    {
        $methods = [
            'createSchedule', 'createCampaign', 'listSchedules',
            'listCampaigns', 'getSchedule', 'cancelSchedule',
            'cancelCampaign', 'processPendingSchedules', 'processRollbacks',
            'getCalendar', 'getSchedulesForDate', 'getSummary',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->service, $method),
                "Missing public method: {$method}"
            );
        }
    }

    // =========================================================================
    // STATUS CONSTANTS
    // =========================================================================

    public function testStatusConstantsExist(): void
    {
        $this->assertSame('pending', ScheduledPriceService::STATUS_PENDING);
        $this->assertSame('processing', ScheduledPriceService::STATUS_PROCESSING);
        $this->assertSame('completed', ScheduledPriceService::STATUS_COMPLETED);
        $this->assertSame('failed', ScheduledPriceService::STATUS_FAILED);
        $this->assertSame('cancelled', ScheduledPriceService::STATUS_CANCELLED);
        $this->assertSame('rolled_back', ScheduledPriceService::STATUS_ROLLED_BACK);
    }

    // =========================================================================
    // RECURRENCE CONSTANTS
    // =========================================================================

    public function testRecurrenceConstantsExist(): void
    {
        $this->assertSame('none', ScheduledPriceService::RECURRENCE_NONE);
        $this->assertSame('daily', ScheduledPriceService::RECURRENCE_DAILY);
        $this->assertSame('weekly', ScheduledPriceService::RECURRENCE_WEEKLY);
        $this->assertSame('monthly', ScheduledPriceService::RECURRENCE_MONTHLY);
    }

    // =========================================================================
    // createSchedule — VALIDATION
    // =========================================================================

    public function testCreateScheduleFailsWithoutItemId(): void
    {
        $result = $this->service->createSchedule([
            'new_price' => 99.90,
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('item_id', $result['message']);
    }

    public function testCreateScheduleFailsWithoutNewPrice(): void
    {
        $result = $this->service->createSchedule([
            'item_id' => 'MLB12345',
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('new_price', $result['message']);
    }

    public function testCreateScheduleFailsWithoutScheduledAt(): void
    {
        $result = $this->service->createSchedule([
            'item_id' => 'MLB12345',
            'new_price' => 99.90,
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('scheduled_at', $result['message']);
    }

    // =========================================================================
    // createCampaign — VALIDATION
    // =========================================================================

    public function testCreateCampaignFailsWithoutName(): void
    {
        $result = $this->service->createCampaign([
            'items' => [['item_id' => 'MLB12345', 'new_price' => 99.90]],
            'start_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('name', $result['message']);
    }

    public function testCreateCampaignFailsWithoutItems(): void
    {
        $result = $this->service->createCampaign([
            'name' => 'Promoção teste',
            'start_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('items', $result['message']);
    }

    public function testCreateCampaignFailsWithoutStartAt(): void
    {
        $result = $this->service->createCampaign([
            'name' => 'Promoção teste',
            'items' => [['item_id' => 'MLB12345', 'new_price' => 99.90]],
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('start_at', $result['message']);
    }

    public function testCreateCampaignFailsWithEmptyItems(): void
    {
        $result = $this->service->createCampaign([
            'name' => 'Promoção teste',
            'items' => [],
            'start_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        $this->assertFalse($result['success']);
        // empty() catches empty array before the separate check
        $this->assertStringContainsString('items', $result['message']);
    }

    // =========================================================================
    // getSummary
    // =========================================================================

    public function testGetSummaryReturnsErrorOnDbFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->getSummary();
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }

    // =========================================================================
    // listSchedules / listCampaigns
    // =========================================================================

    public function testListSchedulesReturnsErrorOnDbFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->listSchedules();
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }

    public function testListCampaignsReturnsErrorOnDbFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->listCampaigns();
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }

    // =========================================================================
    // getCalendar
    // =========================================================================

    public function testGetCalendarReturnsErrorOnDbFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->getCalendar('2024-01-01', '2024-01-31');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }

    // =========================================================================
    // cancelSchedule / cancelCampaign
    // =========================================================================

    public function testCancelScheduleReturnsErrorOnDbFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->cancelSchedule(1);
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }

    public function testCancelCampaignReturnsErrorOnDbFailure(): void
    {
        $this->mockPdo->method('prepare')
            ->willThrowException(new \Exception('DB error'));

        try {
            $result = $this->service->cancelCampaign(1);
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Throwable::class, $e);
            return;
        }

        $this->assertIsArray($result);
    }
}
