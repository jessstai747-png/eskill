<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MercadoLivre;

use App\Services\MercadoLivre\MlObservabilityService;
use Tests\TestCase;

/**
 * @covers \App\Services\MercadoLivre\MlObservabilityService
 */
class MlObservabilityServiceTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a PDO mock that returns $accountRows for the first prepare()
     * call (ml_accounts query) and $webhookRow for the second (webhook_event_inbox
     * aggregate query).
     *
     * @param list<array<string,mixed>> $accountRows  Rows returned by fetchAll() for ml_accounts.
     * @param array<string,mixed>       $webhookRow   Row returned by fetch() for webhook aggregate.
     */
    private function buildPdoMock(array $accountRows, array $webhookRow): \PDO
    {
        $accountStmt = $this->createMock(\PDOStatement::class);
        $accountStmt->method('execute')->willReturn(true);
        $accountStmt->method('fetchAll')->willReturn($accountRows);

        $webhookStmt = $this->createMock(\PDOStatement::class);
        $webhookStmt->method('execute')->willReturn(true);
        $webhookStmt->method('fetch')->willReturn($webhookRow);

        $db = $this->createMock(\PDO::class);
        $db->expects($this->exactly(2))
           ->method('prepare')
           ->willReturnOnConsecutiveCalls($accountStmt, $webhookStmt);

        return $db;
    }

    /** Build a clean webhook aggregate row (no pending, no failed, no backlog). */
    private function cleanWebhookRow(): array
    {
        return [
            'pending'            => 0,
            'failed'             => 0,
            'processed_last_hour' => 0,
            'oldest_pending_at'  => null,
        ];
    }

    // -----------------------------------------------------------------------
    // getSummary — accounts
    // -----------------------------------------------------------------------

    public function testGetSummaryNoAccountsHealthIsOk(): void
    {
        $db      = $this->buildPdoMock([], $this->cleanWebhookRow());
        $service = new MlObservabilityService($db);

        $result = $service->getSummary();

        $this->assertSame('ok', $result['health']);
        $this->assertSame(0, $result['accounts']['total']);
        $this->assertSame(0, $result['accounts']['active']);
        $this->assertSame(0, $result['accounts']['disconnected']);
        $this->assertSame([], $result['alerts']);
    }

    public function testGetSummaryActiveAccountNoFailuresHealthIsOk(): void
    {
        $accounts = [
            [
                'id'                    => '7',
                'nickname'              => 'awa_motos',
                'status'                => 'active',
                'token_expires_at'      => '2030-01-01 00:00:00',
                'refresh_failure_count' => '0',
                'last_refresh_error'    => null,
                'last_refresh_at'       => '2025-04-01 10:00:00',
            ],
        ];

        $db      = $this->buildPdoMock($accounts, $this->cleanWebhookRow());
        $service = new MlObservabilityService($db);

        $result = $service->getSummary(userId: 1);

        $this->assertSame('ok', $result['health']);
        $this->assertSame(1, $result['accounts']['total']);
        $this->assertSame(1, $result['accounts']['active']);
        $this->assertSame(0, $result['accounts']['disconnected']);
        $this->assertSame([], $result['alerts']);
    }

    public function testGetSummaryDisconnectedAccountCreatesErrorAlertHealthCritical(): void
    {
        $accounts = [
            [
                'id'                    => '3',
                'nickname'              => 'loja_awa',
                'status'                => 'inactive',
                'token_expires_at'      => null,
                'refresh_failure_count' => '0',
                'last_refresh_error'    => null,
                'last_refresh_at'       => null,
            ],
        ];

        $db      = $this->buildPdoMock($accounts, $this->cleanWebhookRow());
        $service = new MlObservabilityService($db);

        $result = $service->getSummary();

        $this->assertSame('critical', $result['health']);
        $this->assertSame(1, $result['accounts']['disconnected']);
        $this->assertCount(1, $result['alerts']);
        $this->assertSame('error', $result['alerts'][0]['level']);
        $this->assertSame('ACCOUNT_DISCONNECTED', $result['alerts'][0]['code']);
        $this->assertSame(3, $result['alerts'][0]['account_id']);
    }

    public function testGetSummaryRefreshFailureCreatesWarningAlertHealthDegraded(): void
    {
        $accounts = [
            [
                'id'                    => '5',
                'nickname'              => 'conta_test',
                'status'                => 'active',
                'token_expires_at'      => '2030-01-01 00:00:00',
                'refresh_failure_count' => '3',
                'last_refresh_error'    => 'invalid_grant',
                'last_refresh_at'       => '2025-04-01 08:00:00',
            ],
        ];

        $db      = $this->buildPdoMock($accounts, $this->cleanWebhookRow());
        $service = new MlObservabilityService($db);

        $result = $service->getSummary();

        $this->assertSame('degraded', $result['health']);
        $this->assertSame(1, $result['accounts']['refresh_failures_gt0']);
        $this->assertCount(1, $result['alerts']);
        $this->assertSame('warning', $result['alerts'][0]['level']);
        $this->assertSame('REFRESH_FAILURES', $result['alerts'][0]['code']);
        $this->assertStringContainsString('invalid_grant', $result['alerts'][0]['message']);
    }

    public function testGetSummaryDisconnectedAndRefreshFailureProducesTwoAlerts(): void
    {
        $accounts = [
            [
                'id'                    => '1',
                'nickname'              => 'abc',
                'status'                => 'inactive',
                'token_expires_at'      => null,
                'refresh_failure_count' => '2',
                'last_refresh_error'    => 'network_error',
                'last_refresh_at'       => null,
            ],
        ];

        $db      = $this->buildPdoMock($accounts, $this->cleanWebhookRow());
        $service = new MlObservabilityService($db);

        $result = $service->getSummary();

        $this->assertSame('critical', $result['health']);
        $this->assertCount(2, $result['alerts']);

        $codes = array_column($result['alerts'], 'code');
        $this->assertContains('ACCOUNT_DISCONNECTED', $codes);
        $this->assertContains('REFRESH_FAILURES', $codes);
    }

    // -----------------------------------------------------------------------
    // getSummary — webhooks
    // -----------------------------------------------------------------------

    public function testGetSummaryWebhookFailedCreatesErrorAlert(): void
    {
        $webhookRow = [
            'pending'            => 0,
            'failed'             => 5,
            'processed_last_hour' => 10,
            'oldest_pending_at'  => null,
        ];

        $db      = $this->buildPdoMock([], $webhookRow);
        $service = new MlObservabilityService($db);

        $result = $service->getSummary();

        $this->assertSame('critical', $result['health']);
        $this->assertSame(5, $result['webhooks']['failed']);

        $codes = array_column($result['alerts'], 'code');
        $this->assertContains('WEBHOOK_FAILURES', $codes);
    }

    public function testGetSummaryWebhookBacklogByCountCreatesWarning(): void
    {
        $webhookRow = [
            'pending'            => 150,
            'failed'             => 0,
            'processed_last_hour' => 0,
            'oldest_pending_at'  => date('Y-m-d H:i:s', time() - 60), // 1 min old — below age threshold
        ];

        $db      = $this->buildPdoMock([], $webhookRow);
        $service = new MlObservabilityService($db);

        $result = $service->getSummary();

        $this->assertSame('degraded', $result['health']);
        $this->assertSame(150, $result['webhooks']['pending']);

        $codes = array_column($result['alerts'], 'code');
        $this->assertContains('WEBHOOK_BACKLOG', $codes);
        $this->assertNotContains('WEBHOOK_FAILURES', $codes);
    }

    public function testGetSummaryWebhookBacklogByAgeCreatesWarning(): void
    {
        $webhookRow = [
            'pending'            => 1, // below count threshold
            'failed'             => 0,
            'processed_last_hour' => 0,
            'oldest_pending_at'  => date('Y-m-d H:i:s', time() - 3700), // > 30 min old
        ];

        $db      = $this->buildPdoMock([], $webhookRow);
        $service = new MlObservabilityService($db);

        $result = $service->getSummary();

        $this->assertSame('degraded', $result['health']);

        $codes = array_column($result['alerts'], 'code');
        $this->assertContains('WEBHOOK_BACKLOG', $codes);
    }

    public function testGetSummaryWebhookNoPendingNoBelowThresholdNoAlert(): void
    {
        $webhookRow = [
            'pending'            => 10,  // below count threshold of 100
            'failed'             => 0,
            'processed_last_hour' => 50,
            'oldest_pending_at'  => date('Y-m-d H:i:s', time() - 120), // 2 min — below age threshold
        ];

        $db      = $this->buildPdoMock([], $webhookRow);
        $service = new MlObservabilityService($db);

        $result = $service->getSummary();

        $this->assertSame('ok', $result['health']);
        $this->assertSame([], $result['alerts']);
    }

    public function testGetSummaryOldestPendingAgeIsCalculated(): void
    {
        $ageSec     = 500;
        $webhookRow = [
            'pending'            => 5,
            'failed'             => 0,
            'processed_last_hour' => 0,
            'oldest_pending_at'  => date('Y-m-d H:i:s', time() - $ageSec),
        ];

        $db      = $this->buildPdoMock([], $webhookRow);
        $service = new MlObservabilityService($db);

        $result = $service->getSummary();

        /** @var int|null $age */
        $age = $result['webhooks']['oldest_pending_age_seconds'];
        $this->assertNotNull($age);
        // Allow ±2 seconds for test execution time
        $this->assertGreaterThanOrEqual($ageSec - 2, $age);
        $this->assertLessThanOrEqual($ageSec + 2, $age);
    }

    public function testGetSummaryWebhookNullOldestPendingReturnsNullAge(): void
    {
        $webhookRow = [
            'pending'            => 0,
            'failed'             => 0,
            'processed_last_hour' => 0,
            'oldest_pending_at'  => null,
        ];

        $db      = $this->buildPdoMock([], $webhookRow);
        $service = new MlObservabilityService($db);

        $result = $service->getSummary();

        $this->assertNull($result['webhooks']['oldest_pending_age_seconds']);
    }

    // -----------------------------------------------------------------------
    // getSummary — scoping params
    // -----------------------------------------------------------------------

    public function testGetSummaryUserIdIsPassedToAccountQuery(): void
    {
        $accountStmt = $this->createMock(\PDOStatement::class);
        $accountStmt->method('fetchAll')->willReturn([]);

        // Capture the params passed to execute()
        $capturedParams = null;
        $accountStmt->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;
                return true;
            });

        $webhookStmt = $this->createMock(\PDOStatement::class);
        $webhookStmt->method('execute')->willReturn(true);
        $webhookStmt->method('fetch')->willReturn($this->cleanWebhookRow());

        $db = $this->createMock(\PDO::class);
        $db->expects($this->exactly(2))
           ->method('prepare')
           ->willReturnOnConsecutiveCalls($accountStmt, $webhookStmt);

        $service = new MlObservabilityService($db);
        $service->getSummary(userId: 42);

        $this->assertIsArray($capturedParams);
        $this->assertArrayHasKey(':user_id', $capturedParams);
        $this->assertSame(42, $capturedParams[':user_id']);
        $this->assertArrayNotHasKey(':account_id', $capturedParams);
    }

    public function testGetSummaryAccountIdTakesPrecedenceOverUserId(): void
    {
        $accountStmt = $this->createMock(\PDOStatement::class);
        $accountStmt->method('fetchAll')->willReturn([]);

        $capturedParams = null;
        $accountStmt->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;
                return true;
            });

        $webhookStmt = $this->createMock(\PDOStatement::class);
        $webhookStmt->method('execute')->willReturn(true);
        $webhookStmt->method('fetch')->willReturn($this->cleanWebhookRow());

        $db = $this->createMock(\PDO::class);
        $db->expects($this->exactly(2))
           ->method('prepare')
           ->willReturnOnConsecutiveCalls($accountStmt, $webhookStmt);

        $service = new MlObservabilityService($db);
        $service->getSummary(userId: 42, accountId: 7);

        $this->assertIsArray($capturedParams);
        $this->assertArrayHasKey(':account_id', $capturedParams);
        $this->assertSame(7, $capturedParams[':account_id']);
        $this->assertArrayNotHasKey(':user_id', $capturedParams);
    }

    // -----------------------------------------------------------------------
    // getSummary — response structure
    // -----------------------------------------------------------------------

    public function testGetSummaryResponseContainsAllTopLevelKeys(): void
    {
        $db      = $this->buildPdoMock([], $this->cleanWebhookRow());
        $service = new MlObservabilityService($db);

        $result = $service->getSummary();

        $this->assertArrayHasKey('generated_at', $result);
        $this->assertArrayHasKey('accounts', $result);
        $this->assertArrayHasKey('webhooks', $result);
        $this->assertArrayHasKey('alerts', $result);
        $this->assertArrayHasKey('health', $result);
    }

    public function testGetSummaryGeneratedAtIsISO8601Format(): void
    {
        $db      = $this->buildPdoMock([], $this->cleanWebhookRow());
        $service = new MlObservabilityService($db);

        $result = $service->getSummary();

        // ISO 8601 date string, e.g. "2025-04-01T10:00:00+00:00"
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            (string)$result['generated_at']
        );
    }

    public function testGetSummaryAccountsItemsMappedCorrectly(): void
    {
        $accounts = [
            [
                'id'                    => '99',
                'nickname'              => 'test_seller',
                'status'                => 'active',
                'token_expires_at'      => '2030-12-31 23:59:59',
                'refresh_failure_count' => '0',
                'last_refresh_error'    => null,
                'last_refresh_at'       => '2025-04-01 12:00:00',
            ],
        ];

        $db      = $this->buildPdoMock($accounts, $this->cleanWebhookRow());
        $service = new MlObservabilityService($db);

        $result = $service->getSummary();

        $this->assertCount(1, $result['accounts']['items']);
        $item = $result['accounts']['items'][0];
        $this->assertSame(99, $item['id']);
        $this->assertSame('test_seller', $item['nickname']);
        $this->assertSame('active', $item['status']);
        $this->assertSame(0, $item['refresh_failure_count']);
    }
}
