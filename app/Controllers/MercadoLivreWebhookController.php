<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MercadoLivreWebhookService;
use App\Database;
use App\Services\StructuredLogService;
use App\Services\JobService;
use App\Services\WebhookInboxService;

/**
 * Controller para receber webhooks do Mercado Livre
 */
class MercadoLivreWebhookController
{
    private const WEBHOOK_SIGNATURE_MAX_SKEW_SECONDS = 300;
    private const WEBHOOK_SIGNATURE_REPLAY_WINDOW_SECONDS = 300;

    /** @var array<string, string|int|null> */
    private array $lastWebhookSignatureMetadata = [];
    private ?string $lastWebhookSignatureError = null;

    /**
     * Endpoint público para receber notificações de webhook
     * URL: /webhook/mercadolivre
     */
    public function receive(): void
    {
        header('Content-Type: application/json');

        $requestId = bin2hex(random_bytes(8));
        $logger = new StructuredLogService();

        try {
            // Ler payload
            $rawPayload = (string)file_get_contents('php://input', false, null, 0, 262144);
            $payload = json_decode($rawPayload, true);
            $signatureMeta = [];

            // Validação opcional de assinatura do webhook (produção)
            $envWebhookSecret = getenv('ML_WEBHOOK_SECRET');
            $webhookSecretRaw = $envWebhookSecret !== false
                ? $envWebhookSecret
                : ($_ENV['ML_WEBHOOK_SECRET'] ?? '');
            $webhookSecret = trim((string)$webhookSecretRaw);
            if ($webhookSecret !== '' && !$this->validateWebhookSignature($rawPayload, $webhookSecret)) {
                http_response_code(401);
                $logger->warning('ML_WEBHOOK_INVALID_SIGNATURE', [
                    'message' => 'Invalid webhook signature',
                    'request_id' => $requestId,
                    'reason' => $this->lastWebhookSignatureError,
                ]);
                echo json_encode(['success' => false, 'error' => 'Invalid signature', 'request_id' => $requestId]);
                return;
            }
            if ($webhookSecret !== '') {
                $signatureMeta = $this->lastWebhookSignatureMetadata;
            }

            if (!$payload || json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                $logger->warning('ML_WEBHOOK_INVALID_JSON', [
                    'message' => 'Invalid JSON payload',
                    'request_id' => $requestId,
                    'json_error' => json_last_error_msg(),
                ]);
                echo json_encode(['success' => false, 'error' => 'Invalid payload', 'request_id' => $requestId]);
                return;
            }

            // Identificar conta pelo user_id
            $userId = $payload['user_id'] ?? null;

            if (!$userId) {
                $logger->warning('ML_WEBHOOK_MISSING_USER_ID', [
                    'message' => 'Missing user_id',
                    'request_id' => $requestId,
                ]);
                http_response_code(200);
                echo json_encode(['success' => false, 'error' => 'Missing user_id', 'request_id' => $requestId]);
                return;
            }

            // Buscar conta correspondente
            $accountId = $this->getAccountByMlUserId($userId);

            if (!$accountId) {
                $logger->warning('ML_WEBHOOK_ACCOUNT_NOT_FOUND', [
                    'message' => 'Account not found for ml_user_id',
                    'request_id' => $requestId,
                    'ml_user_id' => (int)$userId,
                ]);
                http_response_code(200);
                echo json_encode(['success' => false, 'error' => 'Account not found', 'request_id' => $requestId]);
                return;
            }

            // Injetar ID da conta interna no payload para facilitar processamento
            $payload['internal_account_id'] = $accountId;
            if (!empty($signatureMeta['delivery_id']) && empty($payload['delivery_id'])) {
                $payload['delivery_id'] = (string)$signatureMeta['delivery_id'];
            }

            // Deduplicação persistente de evento para evitar reprocessamento
            $eventHash = $this->generateEventHash($payload);
            $inbox = new WebhookInboxService();
            $accepted = $inbox->registerIncoming('mercadolivre', $eventHash, $payload, array_merge([
                'request_id' => $requestId,
                'topic' => $payload['topic'] ?? null,
                'resource' => $payload['resource'] ?? null,
            ], $signatureMeta));

            if (!$accepted) {
                $logger->info('ML_WEBHOOK_DUPLICATE_IGNORED', [
                    'message' => 'Duplicate webhook ignored',
                    'request_id' => $requestId,
                    'account_id' => $accountId,
                    'event_hash' => $eventHash,
                    'topic' => $payload['topic'] ?? null,
                    'resource' => $payload['resource'] ?? null,
                ]);
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Duplicate event ignored',
                    'request_id' => $requestId,
                ]);
                return;
            }

            // Processar webhook de forma assíncrona via jobs (worker consome por job_id)
            $jobService = new JobService();
            $payload['event_hash'] = $eventHash;
            $jobId = $jobService->dispatch('ml_webhook', $payload);

            if ($jobId) {
                $inbox->markQueued('mercadolivre', $eventHash, $jobId, [
                    'request_id' => $requestId,
                    'topic' => $payload['topic'] ?? null,
                    'resource' => $payload['resource'] ?? null,
                ]);
                http_response_code(202);
                echo json_encode([
                    'success' => true,
                    'message' => 'Event queued for processing',
                    'job_id' => $jobId,
                    'request_id' => $requestId
                ]);
            } else {
                // Fallback síncrono ou erro
                $logger->warning('ML_WEBHOOK_QUEUE_FAILED', [
                    'message' => 'Queue push failed; processing synchronously',
                    'request_id' => $requestId,
                    'account_id' => $accountId,
                ]);
                $webhookService = new MercadoLivreWebhookService($accountId);
                $result = $webhookService->processWebhookEvent($payload);
                if ((bool)($result['success'] ?? false)) {
                    $inbox->markProcessed('mercadolivre', $eventHash, [
                        'queued' => false,
                        'fallback_processed' => true,
                    ]);
                } else {
                    $inbox->markFailed('mercadolivre', $eventHash, (string)($result['error'] ?? 'Erro no fallback de webhook ML'));
                }
                
                http_response_code(200); // Sempre retorna 200 para o ML não retentar infinitamente se for erro de lógica
                 echo json_encode([
                    'success' => $result['success'],
                    'fallback_processed' => true,
                    'request_id' => $requestId
                ]);
            }
        } catch (\Exception $e) {
            $logger = $logger ?? new StructuredLogService();
            $logger->error('ML_WEBHOOK_ERROR', [
                'message' => 'Unhandled webhook error',
                'request_id' => $requestId ?? null,
                'error' => $e->getMessage(),
            ]);
            http_response_code(200);
            echo json_encode([
                'success' => false,
                'error' => 'Webhook processing error',
                'request_id' => $requestId
            ]);
        }
    }

    /**
     * Busca account_id pelo ml_user_id
     */
    private function getAccountByMlUserId(int $mlUserId): ?int
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT id FROM ml_accounts
            WHERE ml_user_id = :ml_user_id AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute(['ml_user_id' => $mlUserId]);
        $account = $stmt->fetch();

        return $account['id'] ?? null;
    }

    /**
     * Gera hash estável para deduplicação do webhook.
     */
    private function generateEventHash(array $payload): string
    {
        $deliveryId = $payload['delivery_id'] ?? $payload['notification_id'] ?? $payload['id'] ?? null;
        $parts = [
            (string)($payload['topic'] ?? ''),
            (string)($payload['resource'] ?? ''),
            (string)($payload['user_id'] ?? ''),
            (string)($payload['application_id'] ?? ''),
            (string)($deliveryId ?? ''),
            (string)($payload['sent'] ?? ''),
        ];

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Valida assinatura HMAC SHA-256 do webhook com segredo compartilhado.
     */
    private function validateWebhookSignature(string $rawPayload, string $secret): bool
    {
        $this->lastWebhookSignatureMetadata = [];
        $this->lastWebhookSignatureError = null;

        $header = $this->getRequestHeader('X-Signature')
            ?? $this->getRequestHeader('X-Hub-Signature-256');

        if (!$header) {
            $this->lastWebhookSignatureError = 'missing_signature_header';
            return false;
        }

        $parsed = $this->parseWebhookSignatureHeader($header);
        if ($parsed === null) {
            $this->lastWebhookSignatureError = 'invalid_signature_header_format';
            return false;
        }

        $received = (string)($parsed['digest'] ?? '');
        if ($received === '') {
            $this->lastWebhookSignatureError = 'missing_signature_digest';
            return false;
        }

        $signatureTs = isset($parsed['ts']) ? (int)$parsed['ts'] : null;
        if ($signatureTs !== null && !$this->isWebhookSignatureTimestampFresh($signatureTs)) {
            $this->lastWebhookSignatureError = 'signature_timestamp_expired';
            return false;
        }

        $deliveryId = $this->getRequestHeader('X-Delivery-Id')
            ?? $this->getRequestHeader('X-Request-Id')
            ?? $this->getRequestHeader('X-Webhook-Id')
            ?? $this->getRequestHeader('X-Notification-Id');
        $deliveryId = is_string($deliveryId) ? trim($deliveryId) : null;
        if ($deliveryId === '') {
            $deliveryId = null;
        }

        $signatureNonce = isset($parsed['nonce']) ? trim((string)$parsed['nonce']) : null;
        if ($signatureNonce === '') {
            $signatureNonce = null;
        }

        $candidates = [
            hash_hmac('sha256', $rawPayload, $secret),
        ];
        if ($signatureTs !== null) {
            $candidates[] = hash_hmac('sha256', $signatureTs . '.' . $rawPayload, $secret);
        }

        $valid = false;
        foreach (array_unique($candidates) as $candidate) {
            if (hash_equals($candidate, $received)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            $this->lastWebhookSignatureError = 'signature_mismatch';
            return false;
        }

        if ($deliveryId !== null || $signatureNonce !== null || $signatureTs !== null) {
            try {
                $inbox = new WebhookInboxService();
                if ($inbox->hasSignatureReplay(
                    'mercadolivre',
                    $deliveryId,
                    $signatureNonce,
                    $signatureTs,
                    self::WEBHOOK_SIGNATURE_REPLAY_WINDOW_SECONDS
                )) {
                    $this->lastWebhookSignatureError = 'signature_replay_detected';
                    return false;
                }
            } catch (\Throwable $e) {
                $this->lastWebhookSignatureError = 'signature_replay_check_failed';
                return false;
            }
        }

        $this->lastWebhookSignatureMetadata = [
            'delivery_id' => $deliveryId,
            'signature_ts' => $signatureTs,
            'signature_nonce' => $signatureNonce,
        ];

        return true;
    }

    /**
     * @return array{digest: string, ts?: int, nonce?: string}|null
     */
    private function parseWebhookSignatureHeader(string $header): ?array
    {
        $header = trim($header);
        if ($header === '') {
            return null;
        }

        $map = [];
        foreach (explode(',', $header) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '' || !str_contains($chunk, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $chunk, 2));
            if ($key === '' || $value === '') {
                continue;
            }

            $value = trim($value, "\"' ");
            $map[strtolower($key)] = $value;
        }

        if (!empty($map)) {
            $digest = $map['v1'] ?? $map['sha256'] ?? null;
            $normalized = is_string($digest) ? $this->normalizeSignatureDigest($digest) : null;
            if ($normalized === null) {
                return null;
            }

            $parsed = ['digest' => $normalized];
            if (isset($map['t']) || isset($map['ts'])) {
                $tsRaw = $map['t'] ?? $map['ts'];
                if (is_string($tsRaw) && ctype_digit($tsRaw)) {
                    $parsed['ts'] = (int)$tsRaw;
                } else {
                    return null;
                }
            }

            if (isset($map['nonce']) && $map['nonce'] !== '') {
                $parsed['nonce'] = $map['nonce'];
            } elseif (isset($map['n']) && $map['n'] !== '') {
                $parsed['nonce'] = $map['n'];
            }

            return $parsed;
        }

        $normalized = $this->normalizeSignatureDigest($header);
        if ($normalized === null) {
            return null;
        }

        return ['digest' => $normalized];
    }

    private function normalizeSignatureDigest(string $value): ?string
    {
        $value = trim($value);
        if (str_contains($value, '=')) {
            $parts = explode('=', $value, 2);
            $value = trim((string)($parts[1] ?? ''));
        }

        $value = strtolower($value);
        if ($value === '' || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private function isWebhookSignatureTimestampFresh(int $timestamp): bool
    {
        $now = time();
        return abs($now - $timestamp) <= self::WEBHOOK_SIGNATURE_MAX_SKEW_SECONDS;
    }

    /**
     * Busca header de forma case-insensitive com fallback para $_SERVER.
     */
    private function getRequestHeader(string $name): ?string
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strcasecmp($key, $name) === 0) {
                    return is_string($value) ? $value : null;
                }
            }
        }

        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$serverKey]) && is_string($_SERVER[$serverKey])) {
            return $_SERVER[$serverKey];
        }

        return null;
    }

    /**
     * Endpoint para testar webhook (apenas desenvolvimento)
     */
    public function test(): void
    {
        if ($_ENV['APP_ENV'] !== 'development') {
            http_response_code(403);
            echo json_encode(['error' => 'Not available in production']);
            return;
        }

        $testPayload = [
            'topic' => 'orders',
            'resource' => '/orders/123456789',
            'user_id' => 123456,
            'application_id' => '757032559637450',
            'sent' => date('Y-m-d\TH:i:s.000\Z'),
            'attempts' => 1
        ];

        echo json_encode([
            'test_payload' => $testPayload,
            'message' => 'Use este payload para testar o endpoint'
        ]);
    }
}
