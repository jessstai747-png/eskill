<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\JobService;
use App\Services\MercadoLivreWebhookReplayService;
use App\Services\WebhookInboxService;
use PHPUnit\Framework\TestCase;

class MercadoLivreWebhookReplayServiceTest extends TestCase
{
    public function testReplayFailedEventsDispatchesJobAndMarksQueued(): void
    {
        $inbox = new FakeWebhookInboxService([
            [
                'event_key' => 'evt-001',
                'payload_json' => json_encode([
                    'topic' => 'orders_v2',
                    'resource' => '/orders/123',
                    'internal_account_id' => 99,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'delivery_id' => 'delivery-abc',
                'processed_at' => date('Y-m-d H:i:s', time() - 600),
            ],
        ]);
        $jobs = new FakeJobService();
        $service = new MercadoLivreWebhookReplayService($inbox, $jobs);

        $result = $service->replayFailedEvents(10, null, false, 'phpunit', 60);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['attempted']);
        $this->assertSame(1, $result['replayed']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $result['failed']);

        $this->assertCount(1, $jobs->dispatchedJobs);
        $dispatchedPayload = $jobs->dispatchedJobs[0]['payload'];
        $this->assertSame('evt-001', $dispatchedPayload['event_hash']);
        $this->assertSame('delivery-abc', $dispatchedPayload['delivery_id']);

        $this->assertCount(1, $inbox->queuedEvents);
        $this->assertSame('evt-001', $inbox->queuedEvents[0]['event_key']);
        $this->assertSame('replay_queued_auto', $inbox->queuedEvents[0]['meta']['queue_status'] ?? null);
    }

    public function testReplayFailedEventsSkipsRecentFailuresByAgePolicy(): void
    {
        $inbox = new FakeWebhookInboxService([
            [
                'event_key' => 'evt-002',
                'payload_json' => json_encode(['topic' => 'items', 'internal_account_id' => 99], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'processed_at' => date('Y-m-d H:i:s', time() - 5),
            ],
        ]);
        $jobs = new FakeJobService();
        $service = new MercadoLivreWebhookReplayService($inbox, $jobs);

        $result = $service->replayFailedEvents(10, null, false, 'phpunit', 120);

        $this->assertSame(1, $result['attempted']);
        $this->assertSame(0, $result['replayed']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['failed']);
        $this->assertCount(0, $jobs->dispatchedJobs);
        $this->assertCount(0, $inbox->queuedEvents);
    }

    public function testReplayFailedEventsMarksFailedWhenPayloadIsInvalid(): void
    {
        $inbox = new FakeWebhookInboxService([
            [
                'event_key' => 'evt-003',
                'payload_json' => '{invalid-json',
                'processed_at' => date('Y-m-d H:i:s', time() - 600),
            ],
        ]);
        $jobs = new FakeJobService();
        $service = new MercadoLivreWebhookReplayService($inbox, $jobs);

        $result = $service->replayFailedEvents(10, null, false, 'phpunit', 0);

        $this->assertSame(1, $result['attempted']);
        $this->assertSame(0, $result['replayed']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(1, $result['failed']);
        $this->assertCount(1, $inbox->failedEventsMarked);
        $this->assertSame('evt-003', $inbox->failedEventsMarked[0]['event_key']);
    }

    public function testDryRunDoesNotDispatchJobsButCountsAsReplayed(): void
    {
        $inbox = new FakeWebhookInboxService([
            [
                'event_key' => 'evt-dry',
                'payload_json' => json_encode(['topic' => 'orders_v2', 'resource' => '/orders/1', 'internal_account_id' => 1]),
                'processed_at' => date('Y-m-d H:i:s', time() - 300),
            ],
        ]);
        $jobs = new FakeJobService();
        $service = new MercadoLivreWebhookReplayService($inbox, $jobs);

        $result = $service->replayFailedEvents(10, null, true, 'phpunit', 0);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['dry_run']);
        $this->assertSame(1, $result['replayed']);
        $this->assertSame(0, $result['failed']);
        $this->assertCount(0, $jobs->dispatchedJobs, 'dry_run must not dispatch real jobs');
        $this->assertCount(0, $inbox->queuedEvents, 'dry_run must not mark events as queued');
        $this->assertSame('dry_run_ready', $result['details'][0]['status']);
    }

    public function testEventKeyFilterLimitsReplayToMatchingEvent(): void
    {
        $inbox = new FakeWebhookInboxService([
            [
                'event_key' => 'evt-A',
                'payload_json' => json_encode(['topic' => 'orders_v2', 'internal_account_id' => 1]),
                'processed_at' => date('Y-m-d H:i:s', time() - 300),
            ],
            [
                'event_key' => 'evt-B',
                'payload_json' => json_encode(['topic' => 'items', 'internal_account_id' => 1]),
                'processed_at' => date('Y-m-d H:i:s', time() - 300),
            ],
        ]);
        $jobs = new FakeJobService();
        $service = new MercadoLivreWebhookReplayService($inbox, $jobs);

        $result = $service->replayFailedEvents(10, 'evt-A', false, 'phpunit', 0);

        $this->assertSame(1, $result['replayed']);
        $this->assertSame('evt-A', $result['event_key_filter']);
        $this->assertCount(1, $jobs->dispatchedJobs);
        $this->assertSame('evt-A', $jobs->dispatchedJobs[0]['payload']['event_hash']);
    }

    public function testRowWithMissingEventKeyIsSkipped(): void
    {
        $inbox = new FakeWebhookInboxService([
            [
                'event_key' => '',
                'payload_json' => json_encode(['topic' => 'orders_v2']),
                'processed_at' => date('Y-m-d H:i:s', time() - 300),
            ],
        ]);
        $jobs = new FakeJobService();
        $service = new MercadoLivreWebhookReplayService($inbox, $jobs);

        $result = $service->replayFailedEvents(10, null, false, 'phpunit', 0);

        $this->assertSame(1, $result['attempted']);
        $this->assertSame(0, $result['replayed']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame('skipped_missing_event_key', $result['details'][0]['status']);
    }

    public function testDispatchExceptionMarksEventAsFailed(): void
    {
        $inbox = new FakeWebhookInboxService([
            [
                'event_key' => 'evt-err',
                'payload_json' => json_encode(['topic' => 'orders_v2', 'internal_account_id' => 1]),
                'processed_at' => date('Y-m-d H:i:s', time() - 300),
            ],
        ]);
        $jobs = new FakeJobService('DB connection lost');
        $service = new MercadoLivreWebhookReplayService($inbox, $jobs);

        $result = $service->replayFailedEvents(10, null, false, 'phpunit', 0);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['replayed']);
        $this->assertSame(1, $result['failed']);
        $this->assertCount(1, $inbox->failedEventsMarked);
        $this->assertSame('evt-err', $inbox->failedEventsMarked[0]['event_key']);
        $this->assertStringContainsString('DB connection lost', $inbox->failedEventsMarked[0]['error_message']);
    }

    public function testEmptyInboxReturnsSuccessWithZeroCounts(): void
    {
        $inbox = new FakeWebhookInboxService([]);
        $jobs = new FakeJobService();
        $service = new MercadoLivreWebhookReplayService($inbox, $jobs);

        $result = $service->replayFailedEvents(10, null, false, 'phpunit', 0);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['attempted']);
        $this->assertSame(0, $result['replayed']);
        $this->assertSame(0, $result['failed']);
        $this->assertEmpty($result['details']);
    }

    public function testResultSuccessIsFalseWhenThereAreFailures(): void
    {
        $inbox = new FakeWebhookInboxService([
            [
                'event_key' => 'evt-bad',
                'payload_json' => 'NOT_JSON',
                'processed_at' => date('Y-m-d H:i:s', time() - 300),
            ],
        ]);
        $jobs = new FakeJobService();
        $service = new MercadoLivreWebhookReplayService($inbox, $jobs);

        $result = $service->replayFailedEvents(10, null, false, 'phpunit', 0);

        $this->assertFalse($result['success']);
        $this->assertSame(1, $result['failed']);
    }

    public function testEventHashBackfilledFromEventKeyWhenAbsentInPayload(): void
    {
        $inbox = new FakeWebhookInboxService([
            [
                'event_key' => 'evt-hash',
                'payload_json' => json_encode(['topic' => 'orders_v2', 'internal_account_id' => 1]),
                // No event_hash in payload
                'processed_at' => date('Y-m-d H:i:s', time() - 300),
            ],
        ]);
        $jobs = new FakeJobService();
        $service = new MercadoLivreWebhookReplayService($inbox, $jobs);

        $service->replayFailedEvents(10, null, false, 'phpunit', 0);

        $this->assertCount(1, $jobs->dispatchedJobs);
        $this->assertSame('evt-hash', $jobs->dispatchedJobs[0]['payload']['event_hash']);
    }

    public function testDeliveryIdPropagatedFromRowToPayloadWhenAbsent(): void
    {
        $inbox = new FakeWebhookInboxService([
            [
                'event_key' => 'evt-delivery',
                'payload_json' => json_encode(['topic' => 'orders_v2', 'internal_account_id' => 1]),
                'delivery_id' => 'dlv-xyz',
                'processed_at' => date('Y-m-d H:i:s', time() - 300),
            ],
        ]);
        $jobs = new FakeJobService();
        $service = new MercadoLivreWebhookReplayService($inbox, $jobs);

        $service->replayFailedEvents(10, null, false, 'phpunit', 0);

        $this->assertCount(1, $jobs->dispatchedJobs);
        $this->assertSame('dlv-xyz', $jobs->dispatchedJobs[0]['payload']['delivery_id']);
    }
}

class FakeWebhookInboxService extends WebhookInboxService
{
    /** @var array<int, array<string, mixed>> */
    public array $failedRows;

    /** @var array<int, array<string, mixed>> */
    public array $queuedEvents = [];

    /** @var array<int, array<string, mixed>> */
    public array $failedEventsMarked = [];

    /**
     * @param array<int, array<string, mixed>> $failedRows
     */
    public function __construct(array $failedRows = [])
    {
        $this->failedRows = $failedRows;
    }

    public function getFailedEvents(string $provider, int $limit = 100): array
    {
        return array_slice($this->failedRows, 0, max(1, min(1000, $limit)));
    }

    public function markQueued(string $provider, string $eventKey, int $jobId, array $queueMeta = []): void
    {
        $this->queuedEvents[] = [
            'provider' => $provider,
            'event_key' => $eventKey,
            'job_id' => $jobId,
            'meta' => $queueMeta,
        ];
    }

    public function markFailed(string $provider, string $eventKey, string $errorMessage): void
    {
        $this->failedEventsMarked[] = [
            'provider' => $provider,
            'event_key' => $eventKey,
            'error_message' => $errorMessage,
        ];
    }
}

class FakeJobService extends JobService
{
    /** @var array<int, array<string, mixed>> */
    public array $dispatchedJobs = [];

    private ?string $throwMessage;

    public function __construct(?string $throwOnDispatch = null)
    {
        $this->throwMessage = $throwOnDispatch;
    }

    public function dispatch(string $type, array $payload, ?\DateTime $scheduledAt = null): int
    {
        if ($this->throwMessage !== null) {
            throw new \RuntimeException($this->throwMessage);
        }

        $jobId = count($this->dispatchedJobs) + 1001;
        $this->dispatchedJobs[] = [
            'job_id' => $jobId,
            'type' => $type,
            'payload' => $payload,
            'scheduled_at' => $scheduledAt?->format('Y-m-d H:i:s'),
        ];

        return $jobId;
    }
}
