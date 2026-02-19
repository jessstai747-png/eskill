<?php

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

            // Validação opcional de assinatura do webhook (produção)
            $webhookSecret = trim((string)($_ENV['ML_WEBHOOK_SECRET'] ?? ''));
            if ($webhookSecret !== '' && !$this->validateWebhookSignature($rawPayload, $webhookSecret)) {
                http_response_code(401);
                $logger->warning('ML_WEBHOOK_INVALID_SIGNATURE', [
                    'message' => 'Invalid webhook signature',
                    'request_id' => $requestId,
                ]);
                echo json_encode(['success' => false, 'error' => 'Invalid signature', 'request_id' => $requestId]);
                return;
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

            // Deduplicação persistente de evento para evitar reprocessamento
            $eventHash = $this->generateEventHash($payload);
            $inbox = new WebhookInboxService();
            $accepted = $inbox->registerIncoming('mercadolivre', $eventHash, $payload, [
                'request_id' => $requestId,
                'topic' => $payload['topic'] ?? null,
                'resource' => $payload['resource'] ?? null,
            ]);

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
                $inbox->markProcessed('mercadolivre', $eventHash, [
                    'queued' => true,
                    'job_id' => $jobId,
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
        $parts = [
            (string)($payload['topic'] ?? ''),
            (string)($payload['resource'] ?? ''),
            (string)($payload['user_id'] ?? ''),
            (string)($payload['application_id'] ?? ''),
            (string)($payload['sent'] ?? ''),
            (string)($payload['attempts'] ?? ''),
        ];

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Valida assinatura HMAC SHA-256 do webhook com segredo compartilhado.
     */
    private function validateWebhookSignature(string $rawPayload, string $secret): bool
    {
        $header = $this->getRequestHeader('X-Signature')
            ?? $this->getRequestHeader('X-Hub-Signature-256');

        if (!$header) {
            return false;
        }

        $parts = explode('=', trim($header), 2);
        $received = count($parts) === 2 ? trim($parts[1]) : trim($parts[0]);

        if ($received === '') {
            return false;
        }

        $calculated = hash_hmac('sha256', $rawPayload, $secret);
        return hash_equals($calculated, $received);
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
