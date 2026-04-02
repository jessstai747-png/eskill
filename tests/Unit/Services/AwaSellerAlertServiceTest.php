<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AlertService;
use App\Services\AwaSellerAlertService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\AwaSellerAlertService
 */
class AwaSellerAlertServiceTest extends TestCase
{
    /** @param array<int, array<string, mixed>> $alertCalls */
    private function makeFakeAlert(array &$alertCalls): AlertService
    {
        return new class($alertCalls) extends AlertService {
            /** @var array<int, mixed> */
            private array $calls;
            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
            }
            public function createAlert(?int $accountId, string $type, array $data): void
            {
                $this->calls[] = ['account_id' => $accountId, 'type' => $type, 'data' => $data];
            }
        };
    }

    private function makeStmt(): PDOStatement
    {
        return $this->getMockBuilder(PDOStatement::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function makePdo(): PDO
    {
        return $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    // -----------------------------------------------------------
    // checkUnidentifiedSellers
    // -----------------------------------------------------------

    public function testUnidentifiedFiresAlertWhenMoreThanZero(): void
    {
        $stmt = $this->makeStmt();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(5);
        $pdo = $this->makePdo();
        $pdo->method('prepare')->willReturn($stmt);

        $calls = [];
        $svc   = new AwaSellerAlertService(1, $pdo, $this->makeFakeAlert($calls));

        $this->assertSame(5, $svc->checkUnidentifiedSellers(7));
        $this->assertCount(1, $calls);
        $this->assertSame('awa_unidentified_seller', $calls[0]['type']);
        $this->assertSame(5, $calls[0]['data']['unidentified_count']);
    }

    public function testUnidentifiedDoesNotFireWhenZero(): void
    {
        $stmt = $this->makeStmt();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(0);
        $pdo = $this->makePdo();
        $pdo->method('prepare')->willReturn($stmt);

        $calls = [];
        $svc   = new AwaSellerAlertService(1, $pdo, $this->makeFakeAlert($calls));

        $this->assertSame(0, $svc->checkUnidentifiedSellers(7));
        $this->assertCount(0, $calls);
    }

    public function testUnidentifiedClampsDaysMinimum(): void
    {
        $stmt = $this->makeStmt();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(0);
        $pdo = $this->makePdo();
        $pdo->method('prepare')->willReturn($stmt);

        $calls = [];
        $svc   = new AwaSellerAlertService(1, $pdo, $this->makeFakeAlert($calls));

        $this->assertSame(0, $svc->checkUnidentifiedSellers(-5));
    }

    // -----------------------------------------------------------
    // createVolumeSpikeAlerts (snapshot-based API)
    // -----------------------------------------------------------

    public function testCreateVolumeSpikeAlertsReturnsZeroForEmptySnapshots(): void
    {
        $pdo = $this->makePdo();

        $calls = [];
        $svc   = new AwaSellerAlertService(1, $pdo, $this->makeFakeAlert($calls));

        $this->assertSame(0, $svc->createVolumeSpikeAlerts([], [], 0.5));
        $this->assertCount(0, $calls);
    }

    public function testCreateVolumeSpikeAlertsIgnoresNewSellers(): void
    {
        // Seller 999 has no before-snapshot, should be ignored
        $before = [];
        $after  = [999 => ['registry_id' => 1, 'nickname' => 'New', 'items_count' => 10]];
        $pdo    = $this->makePdo();

        $calls = [];
        $svc   = new AwaSellerAlertService(1, $pdo, $this->makeFakeAlert($calls));

        $this->assertSame(0, $svc->createVolumeSpikeAlerts($before, $after, 0.5));
        $this->assertCount(0, $calls);
    }

    public function testCreateVolumeSpikeAlertsIgnoresBelowThreshold(): void
    {
        $before = [123 => ['registry_id' => 1, 'nickname' => 'Loja', 'items_count' => 10]];
        $after  = [123 => ['registry_id' => 1, 'nickname' => 'Loja', 'items_count' => 11]]; // 10% < 50%
        $pdo    = $this->makePdo();

        $calls = [];
        $svc   = new AwaSellerAlertService(1, $pdo, $this->makeFakeAlert($calls));

        $this->assertSame(0, $svc->createVolumeSpikeAlerts($before, $after, 0.5));
        $this->assertCount(0, $calls);
    }

    public function testCreateVolumeSpikeAlertsFiresForAboveThreshold(): void
    {
        $before = [123 => ['registry_id' => 5, 'nickname' => 'Loja Spike', 'items_count' => 10]];
        $after  = [123 => ['registry_id' => 5, 'nickname' => 'Loja Spike', 'items_count' => 20]]; // 100% > 50%
        $pdo    = $this->makePdo();

        $calls = [];
        $svc   = new AwaSellerAlertService(1, $pdo, $this->makeFakeAlert($calls));

        $result = $svc->createVolumeSpikeAlerts($before, $after, 0.5, 99);

        $this->assertSame(1, $result);
        $this->assertCount(1, $calls);
        $this->assertSame('awa_volume_spike', $calls[0]['type']);
        $this->assertSame('Loja Spike', $calls[0]['data']['nickname']);
        $this->assertSame(99, $calls[0]['data']['scan_id']);
    }

    // -----------------------------------------------------------
    // checkVolumeSpikesSinceLastScan (deprecated wrapper)
    // -----------------------------------------------------------

    public function testCheckSpikeReturnsZeroBecauseHistoricalSnapshotsAreDeprecated(): void
    {
        $pdo = $this->makePdo();
        $pdo->expects($this->never())->method('prepare');

        $calls = [];
        $svc   = new AwaSellerAlertService(1, $pdo, $this->makeFakeAlert($calls));

        $this->assertSame(0, $svc->checkVolumeSpikesSinceLastScan(0.5));
        $this->assertEmpty($calls);
    }

    // -----------------------------------------------------------
    // getAwaAlerts
    // -----------------------------------------------------------

    public function testGetAwaAlertsDecodesJsonData(): void
    {
        $rows = [[
            'id'         => 1,
            'type'       => 'awa_new_seller',
            'severity'   => 'warning',
            'message'    => 'test',
            'data'       => '{"scan_id":5}',
            'read_at'    => null,
            'created_at' => '2026-04-01 09:00:00',
        ]];

        $stmt = $this->makeStmt();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);
        $pdo = $this->makePdo();
        $pdo->method('prepare')->willReturn($stmt);

        $calls  = [];
        $svc    = new AwaSellerAlertService(1, $pdo, $this->makeFakeAlert($calls));
        $alerts = $svc->getAwaAlerts(10);

        $this->assertCount(1, $alerts);
        $this->assertIsArray($alerts[0]['data']);
        $this->assertSame(5, $alerts[0]['data']['scan_id']);
    }

    public function testGetAwaAlertsReturnsEmptyArray(): void
    {
        $stmt = $this->makeStmt();
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);
        $pdo = $this->makePdo();
        $pdo->method('prepare')->willReturn($stmt);

        $calls  = [];
        $svc    = new AwaSellerAlertService(1, $pdo, $this->makeFakeAlert($calls));
        $this->assertSame([], $svc->getAwaAlerts(10));
    }

    public function testGetAwaAlertsReturnsEmptyArrayWhenQueryFails(): void
    {
        $pdo = $this->makePdo();
        $pdo->method('prepare')->willThrowException(new \PDOException('Table alerts is unavailable'));

        $calls = [];
        $svc   = new AwaSellerAlertService(1, $pdo, $this->makeFakeAlert($calls));

        $this->assertSame([], $svc->getAwaAlerts(10));
    }
}
