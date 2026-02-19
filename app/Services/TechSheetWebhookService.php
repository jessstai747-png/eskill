<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Tech Sheet Webhook Service
 * 
 * Sistema de webhooks para integrações customizadas:
 * - Slack notifications
 * - Telegram alerts
 * - Custom HTTP webhooks
 * - Retry logic com exponential backoff
 */
class TechSheetWebhookService
{
    private PDO $db;
    private int $accountId;
    private array $config;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        
        $appConfig = \App\Core\Config::getInstance()->all();
        $this->config = [
            'timeout' => 10,
            'max_retries' => 3,
            'retry_delay' => 2, // segundos
        ];
    }

    /**
     * Registra um novo webhook
     * 
     * @param string $type slack|telegram|http
     * @param array $config
     * @return int webhookId
     */
    public function registerWebhook(string $type, array $config): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO tech_sheet_webhooks 
            (account_id, type, url, config, events, status, created_at)
            VALUES 
            (:account_id, :type, :url, :config, :events, 'active', NOW())
        ");
        
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':type' => $type,
            ':url' => $config['url'] ?? '',
            ':config' => json_encode($config),
            ':events' => json_encode($config['events'] ?? ['*']),
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Envia notificação via webhook
     * 
     * @param string $event
     * @param array $payload
     * @return array
     */
    public function notify(string $event, array $payload): array
    {
        // Buscar webhooks ativos para este evento
        $webhooks = $this->getActiveWebhooksForEvent($event);
        
        $results = [];
        
        foreach ($webhooks as $webhook) {
            $result = $this->sendWebhook($webhook, $event, $payload);
            $results[] = [
                'webhook_id' => $webhook['id'],
                'type' => $webhook['type'],
                'success' => $result['success'],
                'status_code' => $result['status_code'] ?? null,
                'error' => $result['error'] ?? null,
            ];
        }
        
        return $results;
    }

    /**
     * Envia para webhook específico
     */
    private function sendWebhook(array $webhook, string $event, array $payload): array
    {
        $config = json_decode($webhook['config'], true);
        
        try {
            switch ($webhook['type']) {
                case 'slack':
                    return $this->sendSlackNotification($webhook['url'], $event, $payload, $config);
                    
                case 'telegram':
                    return $this->sendTelegramNotification($config, $event, $payload);
                    
                case 'http':
                    return $this->sendHttpWebhook($webhook['url'], $event, $payload, $config);
                    
                default:
                    throw new \Exception("Tipo de webhook desconhecido: {$webhook['type']}");
            }
            
        } catch (\Exception $e) {
            $this->logWebhookFailure($webhook['id'], $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Envia notificação para Slack
     */
    private function sendSlackNotification(string $webhookUrl, string $event, array $payload, array $config): array
    {
        $message = $this->formatSlackMessage($event, $payload);
        
        $data = [
            'text' => $message['text'],
            'attachments' => $message['attachments'] ?? [],
        ];
        
        if (isset($config['channel'])) {
            $data['channel'] = $config['channel'];
        }
        
        if (isset($config['username'])) {
            $data['username'] = $config['username'];
        }
        
        return $this->sendHttpRequest($webhookUrl, 'POST', $data);
    }

    /**
     * Formata mensagem para Slack
     */
    private function formatSlackMessage(string $event, array $payload): array
    {
        $eventLabels = [
            'suggestions.generated' => '💡 Novas Sugestões Geradas',
            'suggestions.applied' => '✅ Sugestões Aplicadas',
            'analysis.completed' => '📊 Análise Concluída',
            'alert.critical' => '🚨 Alerta Crítico',
            'optimization.completed' => '⚡ Otimização Concluída',
        ];
        
        $title = $eventLabels[$event] ?? "Tech Sheet: $event";
        
        $message = [
            'text' => $title,
            'attachments' => [],
        ];
        
        // Formatação específica por evento
        switch ($event) {
            case 'suggestions.generated':
                $message['attachments'][] = [
                    'color' => '#36a64f',
                    'fields' => [
                        [
                            'title' => 'Item',
                            'value' => $payload['item_id'] ?? 'N/A',
                            'short' => true,
                        ],
                        [
                            'title' => 'Sugestões',
                            'value' => $payload['suggestions_count'] ?? 0,
                            'short' => true,
                        ],
                        [
                            'title' => 'Completude',
                            'value' => ($payload['completeness'] ?? 0) . '%',
                            'short' => true,
                        ],
                    ],
                ];
                break;
                
            case 'alert.critical':
                $message['attachments'][] = [
                    'color' => 'danger',
                    'fields' => [
                        [
                            'title' => 'Tipo',
                            'value' => $payload['alert_type'] ?? 'N/A',
                            'short' => true,
                        ],
                        [
                            'title' => 'Prioridade',
                            'value' => $payload['priority'] ?? 'HIGH',
                            'short' => true,
                        ],
                        [
                            'title' => 'Mensagem',
                            'value' => $payload['message'] ?? '',
                            'short' => false,
                        ],
                    ],
                ];
                break;
        }
        
        return $message;
    }

    /**
     * Envia notificação para Telegram
     */
    private function sendTelegramNotification(array $config, string $event, array $payload): array
    {
        if (empty($config['bot_token']) || empty($config['chat_id'])) {
            throw new \Exception("Telegram: bot_token e chat_id são obrigatórios");
        }
        
        $url = "https://api.telegram.org/bot{$config['bot_token']}/sendMessage";
        
        $message = $this->formatTelegramMessage($event, $payload);
        
        $data = [
            'chat_id' => $config['chat_id'],
            'text' => $message,
            'parse_mode' => 'HTML',
        ];
        
        return $this->sendHttpRequest($url, 'POST', $data);
    }

    /**
     * Formata mensagem para Telegram
     */
    private function formatTelegramMessage(string $event, array $payload): string
    {
        $eventEmojis = [
            'suggestions.generated' => '💡',
            'suggestions.applied' => '✅',
            'analysis.completed' => '📊',
            'alert.critical' => '🚨',
            'optimization.completed' => '⚡',
        ];
        
        $emoji = $eventEmojis[$event] ?? '📌';
        
        $message = "<b>{$emoji} Tech Sheet - " . ucfirst(str_replace('.', ' ', $event)) . "</b>\n\n";
        
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $label = ucfirst(str_replace('_', ' ', $key));
            $message .= "<b>{$label}:</b> {$value}\n";
        }
        
        return $message;
    }

    /**
     * Envia webhook HTTP genérico
     */
    private function sendHttpWebhook(string $url, string $event, array $payload, array $config): array
    {
        $data = [
            'event' => $event,
            'timestamp' => date('c'),
            'account_id' => $this->accountId,
            'payload' => $payload,
        ];
        
        $headers = $config['headers'] ?? [];
        
        return $this->sendHttpRequest($url, 'POST', $data, $headers);
    }

    /**
     * Envia requisição HTTP com retry
     */
    private function sendHttpRequest(string $url, string $method, array $data, array $headers = []): array
    {
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < $this->config['max_retries']) {
            try {
                $ch = curl_init();
                
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $this->config['timeout'],
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_POSTFIELDS => json_encode($data),
                    CURLOPT_HTTPHEADER => array_merge([
                        'Content-Type: application/json',
                    ], $headers),
                ]);
                
                $response = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                
                curl_close($ch);
                
                if ($error) {
                    throw new \Exception("cURL error: $error");
                }
                
                if ($statusCode >= 200 && $statusCode < 300) {
                    return [
                        'success' => true,
                        'status_code' => $statusCode,
                        'response' => $response,
                    ];
                }
                
                throw new \Exception("HTTP $statusCode");
                
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                $attempt++;
                
                if ($attempt < $this->config['max_retries']) {
                    sleep($this->config['retry_delay'] * $attempt); // Exponential backoff
                }
            }
        }
        
        return [
            'success' => false,
            'error' => $lastError,
            'attempts' => $attempt,
        ];
    }

    /**
     * Busca webhooks ativos para um evento
     */
    private function getActiveWebhooksForEvent(string $event): array
    {
        $stmt = $this->db->prepare("
            SELECT id, type, url, config, events
            FROM tech_sheet_webhooks
            WHERE account_id = :account_id
              AND status = 'active'
        ");
        
        $stmt->execute([':account_id' => $this->accountId]);
        
        $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Filtrar por evento
        return array_filter($webhooks, function($webhook) use ($event) {
            $events = json_decode($webhook['events'], true);
            return in_array('*', $events) || in_array($event, $events);
        });
    }

    /**
     * Lista webhooks configurados
     */
    public function listWebhooks(array $filters = []): array
    {
        $where = ['account_id = :account_id'];
        $params = [':account_id' => $this->accountId];
        
        if (isset($filters['type'])) {
            $where[] = 'type = :type';
            $params[':type'] = $filters['type'];
        }
        
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        
        $sql = "
            SELECT 
                id,
                type,
                url,
                config,
                events,
                status,
                last_triggered_at,
                success_count,
                failure_count,
                created_at
            FROM tech_sheet_webhooks
            WHERE " . implode(' AND ', $where) . "
            ORDER BY created_at DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return array_map(function($row) {
            $row['config'] = json_decode($row['config'], true);
            $row['events'] = json_decode($row['events'], true);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Atualiza webhook
     */
    public function updateWebhook(int $webhookId, array $data): bool
    {
        $updates = [];
        $params = [':id' => $webhookId, ':account_id' => $this->accountId];
        
        if (isset($data['url'])) {
            $updates[] = 'url = :url';
            $params[':url'] = $data['url'];
        }
        
        if (isset($data['config'])) {
            $updates[] = 'config = :config';
            $params[':config'] = json_encode($data['config']);
        }
        
        if (isset($data['events'])) {
            $updates[] = 'events = :events';
            $params[':events'] = json_encode($data['events']);
        }
        
        if (isset($data['status'])) {
            $updates[] = 'status = :status';
            $params[':status'] = $data['status'];
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $sql = "
            UPDATE tech_sheet_webhooks
            SET " . implode(', ', $updates) . "
            WHERE id = :id AND account_id = :account_id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Deleta webhook
     */
    public function deleteWebhook(int $webhookId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM tech_sheet_webhooks
            WHERE id = :id AND account_id = :account_id
        ");
        
        $stmt->execute([
            ':id' => $webhookId,
            ':account_id' => $this->accountId,
        ]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Testa webhook
     */
    public function testWebhook(int $webhookId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM tech_sheet_webhooks
            WHERE id = :id AND account_id = :account_id
        ");
        
        $stmt->execute([
            ':id' => $webhookId,
            ':account_id' => $this->accountId,
        ]);
        
        $webhook = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$webhook) {
            throw new \Exception("Webhook não encontrado");
        }
        
        return $this->sendWebhook($webhook, 'test', [
            'message' => 'Test notification from Tech Sheet',
            'timestamp' => date('c'),
        ]);
    }

    /**
     * Registra falha de webhook
     */
    private function logWebhookFailure(int $webhookId, string $error): void
    {
        $stmt = $this->db->prepare("
            UPDATE tech_sheet_webhooks
            SET 
                failure_count = failure_count + 1,
                last_error = :error,
                last_error_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':id' => $webhookId,
            ':error' => $error,
        ]);
    }

    /**
     * Registra sucesso de webhook
     */
    public function logWebhookSuccess(int $webhookId): void
    {
        $stmt = $this->db->prepare("
            UPDATE tech_sheet_webhooks
            SET 
                success_count = success_count + 1,
                last_triggered_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([':id' => $webhookId]);
    }
}
