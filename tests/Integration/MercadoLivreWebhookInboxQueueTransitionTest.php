<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Database;
use App\Services\JobService;
use App\Services\WebhookInboxService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\WebhookInboxService
 * @covers \App\Services\JobService
 */
class MercadoLivreWebhookInboxQueueTransitionTest extends TestCase
{
    private \PDO $db;

    /** @var list<string> */
    private array $eventKeys = [];

    /** @var list<int> */
    private array $jobIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::getInstance();
        $this->db->exec('DELETE FROM jobs');

        // Garante tabelas (criação lazy pelos services)
        new WebhookInboxService();
        new JobService();
    }

    protected function tearDown(): void
    {
        foreach ($this->jobIds as $jobId) {
            try {
                $this->db->prepare('DELETE FROM jobs WHERE id = :id')->execute(['id' => $jobId]);
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        foreach ($this->eventKeys as $eventKey) {
            try {
                $this->db->prepare('DELETE FROM webhook_event_inbox WHERE provider = :provider AND event_key = :event_key')
                    ->execute([
                        'provider' => 'mercadolivre',
                        'event_key' => $eventKey,
                    ]);
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        parent::tearDown();
    }

    public function testQueuedWebhookStaysQueuedUntilJobProcessingAndThenBecomesProcessed(): void
    {
        $inbox = new WebhookInboxService();
        $jobs = new JobService();

        $eventKey = $this->newEventKey('success');
        $payload = [
            'topic' => 'unknown_topic_for_test',
            'resource' => '/test/123',
            'user_id' => 123456,
            'application_id' => 'app-test',
            'internal_account_id' => 999,
            'event_hash' => $eventKey,
        ];

        $accepted = $inbox->registerIncoming('mercadolivre', $eventKey, $payload, [
            'request_id' => 'req-' . bin2hex(random_bytes(4)),
        ]);
        $this->assertTrue($accepted);

        $jobId = $jobs->dispatch('ml_webhook', $payload);
        $this->jobIds[] = $jobId;

        $inbox->markQueued('mercadolivre', $eventKey, $jobId);

        $queued = $inbox->getEventStatus('mercadolivre', $eventKey);
        $this->assertIsArray($queued);
        $this->assertSame('queued', $queued['status']);
        $this->assertSame($jobId, $queued['job_id']);

        $job = $jobs->getJob($jobId);
        $this->assertIsArray($job);

        $result = $jobs->processJob($job);
        $this->assertSame('completed', $result['status']);

        $processed = $inbox->getEventStatus('mercadolivre', $eventKey);
        $this->assertIsArray($processed);
        $this->assertSame('processed', $processed['status']);
        $this->assertSame($jobId, $processed['job_id']);
        $this->assertSame('completed', $processed['result']['job_status'] ?? null);
    }

    public function testMlWebhookTerminalFailureMarksInboxAsFailed(): void
    {
        $inbox = new WebhookInboxService();
        $jobs = new JobService();

        $eventKey = $this->newEventKey('failure');
        $payload = [
            'topic' => 'orders',
            'resource' => '/orders/1',
            // Sem internal_account_id para forçar falha no JobService::mlWebhookJob()
            'event_hash' => $eventKey,
        ];

        $accepted = $inbox->registerIncoming('mercadolivre', $eventKey, $payload);
        $this->assertTrue($accepted);

        $jobId = $jobs->dispatch('ml_webhook', $payload);
        $this->jobIds[] = $jobId;

        $this->db->prepare('UPDATE jobs SET max_attempts = 1 WHERE id = :id')->execute(['id' => $jobId]);
        $inbox->markQueued('mercadolivre', $eventKey, $jobId);

        $job = $jobs->getJob($jobId);
        $this->assertIsArray($job);

        $result = $jobs->processJob($job);
        $this->assertSame('failed', $result['status']);

        $failed = $inbox->getEventStatus('mercadolivre', $eventKey);
        $this->assertIsArray($failed);
        $this->assertSame('failed', $failed['status']);
        $this->assertSame($jobId, $failed['job_id']);
        $this->assertNotEmpty($failed['error_message']);
    }

    public function testDuplicateWebhookRegistrationIsRejected(): void
    {
        $inbox = new WebhookInboxService();

        $eventKey = $this->newEventKey('dup');
        $payload = [
            'topic' => 'items',
            'resource' => '/items/MLB123',
            'user_id' => 42,
            'event_hash' => $eventKey,
        ];

        $this->assertTrue($inbox->registerIncoming('mercadolivre', $eventKey, $payload));
        $this->assertFalse($inbox->registerIncoming('mercadolivre', $eventKey, $payload));
    }

    public function testNonTerminalFailureSchedulesRetryAndBlocksImmediateReclaim(): void
    {
        $jobs = new JobService();

        $jobId = $jobs->dispatch('ml_webhook', [
            'topic' => 'orders',
            'resource' => '/orders/1',
            // Sem internal_account_id para forçar retry não-terminal
            'event_hash' => $this->newEventKey('retry'),
        ]);
        $this->jobIds[] = $jobId;

        $this->db->prepare('UPDATE jobs SET max_attempts = 2 WHERE id = :id')->execute(['id' => $jobId]);

        $job = $jobs->getJob($jobId);
        $this->assertIsArray($job);

        $startedAt = time();
        $firstAttempt = $jobs->processJob($job);
        $this->assertSame('pending', $firstAttempt['status']);

        $afterRetrySchedule = $jobs->getJob($jobId);
        $this->assertIsArray($afterRetrySchedule);
        $this->assertSame('pending', $afterRetrySchedule['status']);
        $this->assertSame(1, (int)$afterRetrySchedule['attempts']);
        $this->assertNotEmpty($afterRetrySchedule['next_attempt_at']);

        $nextAttemptTs = strtotime((string)$afterRetrySchedule['next_attempt_at']);
        $this->assertNotFalse($nextAttemptTs);
        $this->assertGreaterThanOrEqual($startedAt + 4, $nextAttemptTs);

        $immediateBatch = $jobs->process(1);
        $this->assertSame([], $immediateBatch);

        $this->db->prepare('UPDATE jobs SET next_attempt_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE id = :id')
            ->execute(['id' => $jobId]);

        $secondAttempt = $jobs->process(1);
        $this->assertCount(1, $secondAttempt);
        $this->assertSame('failed', $secondAttempt[0]['status']);
    }

    public function testReclaimStaleProcessingJobMovesItBackToPending(): void
    {
        $jobs = new JobService();

        $jobId = $jobs->dispatch('sync_questions', [
            'account_id' => 123456,
            'limit' => 1,
        ]);
        $this->jobIds[] = $jobId;

        $this->db->prepare(
            "UPDATE jobs
             SET status = 'processing',
                 claim_token = 'stale-claim',
                 claimed_by = 'phpunit-worker',
                 claimed_at = DATE_SUB(NOW(), INTERVAL 2 HOUR),
                 next_attempt_at = NULL
             WHERE id = :id"
        )->execute(['id' => $jobId]);

        $reclaimed = $jobs->reclaimStaleProcessingJobs(60, 10);
        $this->assertGreaterThanOrEqual(1, $reclaimed);

        $job = $jobs->getJob($jobId);
        $this->assertIsArray($job);
        $this->assertSame('pending', $job['status']);
        $this->assertNull($job['claim_token']);
        $this->assertNull($job['claimed_by']);
        $this->assertNull($job['claimed_at']);
        $this->assertNotEmpty($job['next_attempt_at']);
    }

    private function newEventKey(string $prefix): string
    {
        $key = 'test-ml-webhook-' . $prefix . '-' . bin2hex(random_bytes(8));
        $this->eventKeys[] = $key;
        return $key;
    }
}
