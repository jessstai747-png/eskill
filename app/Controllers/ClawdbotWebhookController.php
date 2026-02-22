<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ClawdbotWebhookService;
use App\Services\JobService;
use App\Services\WebhookInboxService;

/**
 * ClawdbotWebhookController
 *
 * Endpoint público (sem sessão) para integração com CLAWDBOT.
 * Rotas:
 *  - POST /api/webhook/clawdbot
 *  - GET  /api/webhook/clawdbot/health
 */
final class ClawdbotWebhookController extends BaseController
{
    private const MAX_PAYLOAD_BYTES = 262144; // 256KB

    public function health(): void
    {
        $this->json([
            'success' => true,
            'integration' => 'clawdbot',
            'provider' => ClawdbotWebhookService::PROVIDER,
            'time' => date('c'),
        ], 200);
    }

    public function receive(): void
    {
        $requestId = bin2hex(random_bytes(8));
        $rawPayload = (string)file_get_contents('php://input', false, null, 0, self::MAX_PAYLOAD_BYTES);

        /** @var array<string, string|int|float|bool|null> $headers */
        $headers = $this->getAllHeadersSafe();

        $secret = (string)($_ENV['CLAWDBOT_WEBHOOK_SECRET'] ?? '');
        $webhook = new ClawdbotWebhookService($secret, (int)($_ENV['CLAWDBOT_WEBHOOK_TOLERANCE'] ?? 300));

        $isProduction = (string)($_ENV['APP_ENV'] ?? 'production') === 'production';
        if ($isProduction && !$webhook->isConfigured()) {
            log_error('CLAWDBOT webhook secret missing in production', [
                'controller' => __CLASS__,
                'request_id' => $requestId,
            ]);
            $this->json([
                'success' => false,
                'error' => 'Webhook não configurado',
                'request_id' => $requestId,
            ], 500);
        }

        // Se houver secret configurado, exigir assinatura válida.
        if ($webhook->isConfigured() && !$webhook->validateRequest($rawPayload, $headers)) {
            log_warning('CLAWDBOT webhook invalid signature', [
                'controller' => __CLASS__,
                'request_id' => $requestId,
            ]);
            $this->json([
                'success' => false,
                'error' => 'Invalid signature',
                'request_id' => $requestId,
            ], 401);
        }

        $payload = json_decode($rawPayload, true);
        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            $this->json([
                'success' => false,
                'error' => 'Invalid JSON payload',
                'request_id' => $requestId,
            ], 400);
        }

        /** @var array<string, mixed> $payload */
        $command = null;
        try {
            $command = $webhook->normalizeCommand($payload);
        } catch (\InvalidArgumentException $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'request_id' => $requestId,
            ], 400);
        }

        $eventId = $command['event_id'];
        $eventKey = $webhook->deriveEventKey($eventId);

        // Idempotência persistente (best-effort). Se DB cair, ainda respondemos.
        $accepted = true;
        try {
            $inbox = new WebhookInboxService();
            $accepted = $inbox->registerIncoming(ClawdbotWebhookService::PROVIDER, $eventKey, $payload, [
                'request_id' => $requestId,
                'event_id' => $eventId,
                'action' => $command['action'],
            ]);

            if (!$accepted) {
                $status = $inbox->getEventStatus(ClawdbotWebhookService::PROVIDER, $eventKey, null);
                $this->json([
                    'success' => true,
                    'message' => 'Duplicate event ignored',
                    'request_id' => $requestId,
                    'event_id' => $eventId,
                    'event_key' => $eventKey,
                    'status' => $status,
                ], 200);
            }
        } catch (\Throwable $e) {
            log_warning('CLAWDBOT webhook inbox unavailable (continuing)', [
                'controller' => __CLASS__,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
        }

        if ($command['action'] === 'ping') {
            $this->json([
                'success' => true,
                'message' => 'pong',
                'request_id' => $requestId,
                'event_id' => $eventId,
            ], 200);
        }

        $jobType = (string)$command['job_type'];
        /** @var array<string,mixed> $jobPayload */
        $jobPayload = $command['job_payload'];

        // Segurança: allowlist de jobs disparáveis externamente
        $allowed = $this->getAllowedJobTypes();
        if (!in_array($jobType, $allowed, true)) {
            log_warning('CLAWDBOT webhook job_type not allowed', [
                'controller' => __CLASS__,
                'request_id' => $requestId,
                'event_id' => $eventId,
                'job_type' => $jobType,
                'allowed' => $allowed,
            ]);
            $this->json([
                'success' => false,
                'error' => 'job_type not allowed',
                'request_id' => $requestId,
            ], 403);
        }

        $jobPayload = array_merge($jobPayload, [
            'source' => 'clawdbot',
            'event_id' => $eventId,
            'event_key' => $eventKey,
            'request_id' => $requestId,
        ]);

        try {
            $jobService = new JobService();
            $jobId = $jobService->dispatch($jobType, $jobPayload);

            // Best-effort update da inbox (se disponível)
            try {
                $inbox = new WebhookInboxService();
                $inbox->markQueued(ClawdbotWebhookService::PROVIDER, $eventKey, $jobId, [
                    'job_type' => $jobType,
                ]);
            } catch (\Throwable $e) {
                // não bloqueia
            }

            $this->json([
                'success' => true,
                'message' => 'Event accepted and queued',
                'job_id' => $jobId,
                'request_id' => $requestId,
                'event_id' => $eventId,
                'event_key' => $eventKey,
            ], 202);
        } catch (\Throwable $e) {
            log_error('CLAWDBOT webhook failed to dispatch job', [
                'controller' => __CLASS__,
                'request_id' => $requestId,
                'event_id' => $eventId,
                'job_type' => $jobType,
                'error' => $e->getMessage(),
            ]);

            // Se inbox existir, marca falha
            try {
                $inbox = new WebhookInboxService();
                $inbox->markFailed(ClawdbotWebhookService::PROVIDER, $eventKey, $e->getMessage());
            } catch (\Throwable $e2) {
                // ignore
            }

            $this->json([
                'success' => false,
                'error' => 'Failed to queue job',
                'request_id' => $requestId,
                'event_id' => $eventId,
            ], 503);
        }
    }

    /**
     * @return array<int, string>
     */
    private function getAllowedJobTypes(): array
    {
        $raw = (string)($_ENV['CLAWDBOT_ALLOWED_JOB_TYPES'] ?? '');
        if (trim($raw) === '') {
            return ['run_agent'];
        }

        $parts = array_map('trim', explode(',', $raw));
        $parts = array_values(array_filter($parts, static fn($v): bool => is_string($v) && $v !== ''));

        // Safety default
        return empty($parts) ? ['run_agent'] : $parts;
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    private function getAllHeadersSafe(): array
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            $all = getallheaders();
            if (is_array($all)) {
                foreach ($all as $k => $v) {
                    if (is_string($k)) {
                        $headers[$k] = is_scalar($v) ? $v : null;
                    }
                }
                return $headers;
            }
        }

        // Fallback para ambientes sem getallheaders()
        foreach ($_SERVER as $k => $v) {
            if (!is_string($k) || strpos($k, 'HTTP_') !== 0) {
                continue;
            }
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $headers[$name] = is_scalar($v) ? $v : null;
        }

        return $headers;
    }
}
