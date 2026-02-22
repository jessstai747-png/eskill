<?php

declare(strict_types=1);

namespace App\Services;

/**
 * ClawdbotWebhookService
 *
 * Valida e normaliza requisições de webhook do CLAWDBOT.
 * - Assinatura HMAC-SHA256 com segredo compartilhado
 * - Suporte opcional a timestamp para proteção contra replay
 *
 * Headers suportados:
 * - X-Clawdbot-Signature: sha256=<hex>  (ou somente <hex>)
 * - X-Clawdbot-Timestamp: <unix_seconds>
 */
final class ClawdbotWebhookService
{
    public const PROVIDER = 'clawdbot';

    public const HEADER_SIGNATURE = 'X-Clawdbot-Signature';
    public const HEADER_TIMESTAMP = 'X-Clawdbot-Timestamp';

    /**
     * Compat: headers alternativos aceitos para assinatura.
     * @var string[]
     */
    private const SIGNATURE_HEADER_FALLBACKS = [
        'X-Signature',
        'X-Webhook-Signature',
        'X-Hub-Signature-256',
    ];

    private string $secret;
    private int $toleranceSeconds;

    public function __construct(string $secret, int $toleranceSeconds = 300)
    {
        $this->secret = trim($secret);
        $this->toleranceSeconds = max(0, $toleranceSeconds);
    }

    public function isConfigured(): bool
    {
        return $this->secret !== '';
    }

    /**
     * Valida request completa (timestamp opcional + assinatura).
     *
     * @param array<string, string|int|float|bool|null> $headers
     */
    public function validateRequest(string $rawPayload, array $headers): bool
    {
        $signature = $this->extractSignature($headers);
        if ($signature === null || $signature === '') {
            return false;
        }

        $timestamp = $this->extractTimestamp($headers);
        if ($timestamp !== null && $timestamp !== '') {
            // Replay protection (best-effort)
            if (!$this->isTimestampWithinTolerance($timestamp)) {
                return false;
            }
        }

        return $this->validateSignature($rawPayload, $signature, $timestamp);
    }

    public function validateSignature(string $rawPayload, string $signatureHeader, ?string $timestampHeader): bool
    {
        if ($this->secret === '') {
            return false;
        }

        $received = $this->normalizeSignatureValue($signatureHeader);
        if ($received === '') {
            return false;
        }

        $base = $rawPayload;
        $timestamp = $timestampHeader !== null ? trim($timestampHeader) : '';
        if ($timestamp !== '') {
            $base = $timestamp . '.' . $rawPayload;
        }

        $calculated = hash_hmac('sha256', $base, $this->secret);
        return hash_equals($calculated, $received);
    }

    /**
     * @param array<string, string|int|float|bool|null> $headers
     */
    public function extractSignature(array $headers): ?string
    {
        $value = $this->getHeader($headers, self::HEADER_SIGNATURE);
        if ($value !== null && $value !== '') {
            return $value;
        }

        foreach (self::SIGNATURE_HEADER_FALLBACKS as $name) {
            $value = $this->getHeader($headers, $name);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, string|int|float|bool|null> $headers
     */
    public function extractTimestamp(array $headers): ?string
    {
        $value = $this->getHeader($headers, self::HEADER_TIMESTAMP);
        if ($value === null || $value === '') {
            // Compat: alguns sistemas usam X-Timestamp
            $value = $this->getHeader($headers, 'X-Timestamp');
        }

        return $value;
    }

    /**
     * Normaliza comando do CLAWDBOT.
     *
     * Payload suportado:
     * 1) {"action":"run_agent","agent":"guardian","account_id":123,"event_id":"..."}
     * 2) {"action":"dispatch_job","job_type":"run_agent","job_payload":{...},"event_id":"..."}
     *
     * @param array<string, mixed> $payload
     * @return array{action:string,event_id:string,job_type:string,job_payload:array<string,mixed>}
     */
    public function normalizeCommand(array $payload): array
    {
        $action = isset($payload['action']) ? strtolower((string)$payload['action']) : '';
        if ($action === '') {
            throw new \InvalidArgumentException('Campo "action" é obrigatório');
        }

        $eventId = (string)($payload['event_id'] ?? $payload['eventId'] ?? $payload['id'] ?? '');
        if ($eventId === '') {
            // Melhor ter idempotência estável do que exigir UUID.
            $eventId = 'evt_' . substr(hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 32);
        }

        if ($action === 'ping' || $action === 'health') {
            return [
                'action' => 'ping',
                'event_id' => $eventId,
                'job_type' => '',
                'job_payload' => [],
            ];
        }

        if ($action === 'run_agent') {
            $agent = (string)($payload['agent'] ?? '');
            if ($agent === '') {
                throw new \InvalidArgumentException('Campo "agent" é obrigatório para action=run_agent');
            }

            $jobPayload = [
                'agent' => $agent,
            ];

            if (isset($payload['account_id']) || isset($payload['accountId'])) {
                $accountId = (int)($payload['account_id'] ?? $payload['accountId']);
                if ($accountId > 0) {
                    $jobPayload['account_id'] = $accountId;
                }
            }

            return [
                'action' => 'dispatch_job',
                'event_id' => $eventId,
                'job_type' => 'run_agent',
                'job_payload' => $jobPayload,
            ];
        }

        if ($action === 'dispatch_job') {
            $jobType = (string)($payload['job_type'] ?? $payload['jobType'] ?? '');
            if ($jobType === '') {
                throw new \InvalidArgumentException('Campo "job_type" é obrigatório para action=dispatch_job');
            }

            $jobPayload = $payload['job_payload'] ?? $payload['jobPayload'] ?? null;
            if (!is_array($jobPayload)) {
                throw new \InvalidArgumentException('Campo "job_payload" deve ser um objeto JSON');
            }

            /** @var array<string,mixed> $jobPayload */
            return [
                'action' => 'dispatch_job',
                'event_id' => $eventId,
                'job_type' => $jobType,
                'job_payload' => $jobPayload,
            ];
        }

        throw new \InvalidArgumentException('Action não suportada: ' . $action);
    }

    public function deriveEventKey(string $eventId): string
    {
        return hash('sha256', $eventId);
    }

    private function normalizeSignatureValue(string $header): string
    {
        $value = trim($header);
        if ($value === '') {
            return '';
        }

        // Aceita "sha256=<hex>" ou só <hex>
        $parts = explode('=', $value, 2);
        if (count($parts) === 2 && strtolower(trim($parts[0])) === 'sha256') {
            $value = trim($parts[1]);
        }

        // Remover espaços acidentais
        $value = preg_replace('/\s+/', '', $value);
        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string, string|int|float|bool|null> $headers
     */
    private function getHeader(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (strcasecmp($key, $name) !== 0) {
                continue;
            }

            if ($value === null) {
                return null;
            }
            return is_string($value) ? $value : (string)$value;
        }

        return null;
    }

    private function isTimestampWithinTolerance(string $timestampHeader): bool
    {
        $ts = trim($timestampHeader);
        if ($ts === '') {
            return false;
        }

        if (!preg_match('/^\d{9,12}$/', $ts)) {
            return false;
        }

        $timestamp = (int)$ts;
        $now = time();
        $diff = abs($now - $timestamp);

        return $diff <= $this->toleranceSeconds;
    }
}
