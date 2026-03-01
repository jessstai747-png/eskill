<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * Reprocessamento automático de eventos de webhook ML que ficaram em failed na inbox.
 */
class MercadoLivreWebhookReplayService
{
    private const PROVIDER = 'mercadolivre';

    private WebhookInboxService $inbox;
    private JobService $jobs;

    public function __construct(?WebhookInboxService $inbox = null, ?JobService $jobs = null)
    {
        $this->inbox = $inbox ?? new WebhookInboxService();
        $this->jobs = $jobs ?? new JobService();
    }

    public function replayFailedEvents(
        int $limit = 25,
        ?string $eventKey = null,
        bool $dryRun = false,
        string $replayedBy = 'ml-webhook-replay-service',
        int $minFailedAgeSeconds = 60
    ): array {
        $limit = max(1, min(1000, $limit));
        $minFailedAgeSeconds = max(0, min(86400, $minFailedAgeSeconds));
        $eventKey = $eventKey !== null ? trim($eventKey) : null;
        $eventKey = $eventKey === '' ? null : $eventKey;
        $replayedBy = trim($replayedBy) !== '' ? mb_substr(trim($replayedBy), 0, 80) : 'ml-webhook-replay-service';

        $rows = $this->filterEvents($this->inbox->getFailedEvents(self::PROVIDER, $limit), $eventKey);

        $result = [
            'success' => true,
            'provider' => self::PROVIDER,
            'requested_limit' => $limit,
            'min_failed_age_seconds' => $minFailedAgeSeconds,
            'event_key_filter' => $eventKey,
            'dry_run' => $dryRun,
            'attempted' => 0,
            'replayed' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
            'timestamp' => date('c'),
        ];

        foreach ($rows as $row) {
            $result['attempted']++;

            $currentEventKey = (string)($row['event_key'] ?? '');
            if ($currentEventKey === '') {
                $result['skipped']++;
                $result['details'][] = ['event_key' => null, 'status' => 'skipped_missing_event_key'];
                continue;
            }

            if ($this->isFailureTooRecent($row, $minFailedAgeSeconds)) {
                $result['skipped']++;
                $result['details'][] = ['event_key' => $currentEventKey, 'status' => 'skipped_recent_failure'];
                continue;
            }

            $payload = json_decode((string)($row['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                if (!$dryRun) {
                    $this->inbox->markFailed(self::PROVIDER, $currentEventKey, 'payload_json inválido para replay automático');
                }
                $result['failed']++;
                $result['details'][] = ['event_key' => $currentEventKey, 'status' => 'failed_invalid_payload'];
                continue;
            }

            if (empty($payload['event_hash'])) {
                $payload['event_hash'] = $currentEventKey;
            }
            if (!empty($row['delivery_id']) && empty($payload['delivery_id'])) {
                $payload['delivery_id'] = (string)$row['delivery_id'];
            }

            if ($dryRun) {
                $result['replayed']++;
                $result['details'][] = ['event_key' => $currentEventKey, 'status' => 'dry_run_ready'];
                continue;
            }

            try {
                $jobId = $this->jobs->dispatch('ml_webhook', $payload);
                $this->inbox->markQueued(self::PROVIDER, $currentEventKey, $jobId, [
                    'queue_status' => 'replay_queued_auto',
                    'replayed_at' => (new DateTimeImmutable())->format(DATE_ATOM),
                    'replayed_by' => $replayedBy,
                    'previous_status' => 'failed',
                ]);

                $result['replayed']++;
                $result['details'][] = [
                    'event_key' => $currentEventKey,
                    'status' => 'replay_queued',
                    'job_id' => $jobId,
                ];
            } catch (\Throwable $e) {
                $this->inbox->markFailed(self::PROVIDER, $currentEventKey, $e->getMessage());
                $result['failed']++;
                $result['details'][] = [
                    'event_key' => $currentEventKey,
                    'status' => 'failed_dispatch',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $result['success'] = (int)$result['failed'] === 0;

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isFailureTooRecent(array $row, int $minFailedAgeSeconds): bool
    {
        if ($minFailedAgeSeconds <= 0) {
            return false;
        }

        $processedAt = (string)($row['processed_at'] ?? '');
        if ($processedAt === '') {
            return false;
        }

        $processedTs = strtotime($processedAt);
        if ($processedTs === false) {
            return false;
        }

        return (time() - $processedTs) < $minFailedAgeSeconds;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function filterEvents(array $rows, ?string $eventKey): array
    {
        if ($eventKey === null) {
            return $rows;
        }

        return array_values(array_filter($rows, static function (array $row) use ($eventKey): bool {
            return (string)($row['event_key'] ?? '') === $eventKey;
        }));
    }
}
