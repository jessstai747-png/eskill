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

    public function __construct()
    {
    }

    public function dispatch(string $type, array $payload, ?\DateTime $scheduledAt = null): int
    {
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
