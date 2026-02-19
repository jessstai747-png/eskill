<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Price Notification Service
 * 
 * Sistema de notificações para eventos de preço:
 * - Email
 * - Webhook
 * - Push notifications
 * - Slack/Discord
 * - Log interno
 * 
 * @package App\Services
 */
class PriceNotificationService
{
    private int $accountId;
    private PDO $db;

    // Tipos de notificação
    public const TYPE_EMAIL = 'email';
    public const TYPE_WEBHOOK = 'webhook';
    public const TYPE_PUSH = 'push';
    public const TYPE_SLACK = 'slack';
    public const TYPE_DISCORD = 'discord';
    public const TYPE_LOG = 'log';

    // Eventos que disparam notificações
    public const EVENT_PRICE_CHANGE = 'price_change';
    public const EVENT_COMPETITOR_ALERT = 'competitor_alert';
    public const EVENT_MARGIN_ALERT = 'margin_alert';
    public const EVENT_RULE_EXECUTED = 'rule_executed';
    public const EVENT_SCHEDULE_EXECUTED = 'schedule_executed';
    public const EVENT_BULK_COMPLETED = 'bulk_completed';
    public const EVENT_AB_TEST_COMPLETE = 'ab_test_complete';
    public const EVENT_OPTIMIZATION_SUGGESTION = 'optimization_suggestion';

    // Severidades
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_CRITICAL = 'critical';

    public function __construct(int $accountId)
    {
        $this->accountId = $accountId;
        $this->db = Database::getInstance();
    }

    /**
     * Enviar notificação
     */
    public function notify(string $event, array $data, string $severity = self::SEVERITY_INFO): array
    {
        // Buscar configurações de notificação
        $channels = $this->getEnabledChannels($event, $severity);

        if (count($channels) === 0) {
            return [
                'success' => true,
                'message' => 'Nenhum canal configurado para este evento',
                'sent' => []
            ];
        }

        $results = [];

        foreach ($channels as $channel) {
            $result = $this->sendToChannel($channel, $event, $data, $severity);
            $results[] = [
                'channel' => $channel['type'],
                'success' => $result['success'],
                'message' => $result['message'] ?? null
            ];

            // Registrar log
            $this->logNotification($channel['id'], $event, $data, $severity, $result['success']);
        }

        return [
            'success' => true,
            'sent' => $results
        ];
    }

    /**
     * Enviar para canal específico
     */
    private function sendToChannel(array $channel, string $event, array $data, string $severity): array
    {
        return match ($channel['type']) {
            self::TYPE_EMAIL => $this->sendEmail($channel, $event, $data, $severity),
            self::TYPE_WEBHOOK => $this->sendWebhook($channel, $event, $data, $severity),
            self::TYPE_SLACK => $this->sendSlack($channel, $event, $data, $severity),
            self::TYPE_DISCORD => $this->sendDiscord($channel, $event, $data, $severity),
            self::TYPE_PUSH => $this->sendPush($channel, $event, $data, $severity),
            self::TYPE_LOG => $this->sendLog($channel, $event, $data, $severity),
            default => ['success' => false, 'message' => 'Tipo de canal desconhecido']
        };
    }

    /**
     * Enviar email
     */
    private function sendEmail(array $channel, string $event, array $data, string $severity): array
    {
        $config = json_decode($channel['config'], true);
        $email = $config['email'] ?? null;

        if (!$email) {
            return ['success' => false, 'message' => 'Email não configurado'];
        }

        $subject = $this->getEmailSubject($event, $severity, $data);
        $body = $this->getEmailBody($event, $data, $severity);

        // Usar mail() ou serviço externo
        $headers = [
            'From' => 'noreply@eskill.com.br',
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Priority' => $severity === self::SEVERITY_CRITICAL ? '1' : '3'
        ];

        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= "{$key}: {$value}\r\n";
        }

        $sent = @mail($email, $subject, $body, $headerString);

        return [
            'success' => $sent,
            'message' => $sent ? 'Email enviado' : 'Falha ao enviar email'
        ];
    }

    /**
     * Enviar webhook
     */
    private function sendWebhook(array $channel, string $event, array $data, string $severity): array
    {
        $config = json_decode($channel['config'], true);
        $url = $config['url'] ?? null;
        $secret = $config['secret'] ?? null;

        if (!$url) {
            return ['success' => false, 'message' => 'URL não configurada'];
        }

        $payload = [
            'event' => $event,
            'severity' => $severity,
            'data' => $data,
            'timestamp' => date('c'),
            'account_id' => $this->accountId
        ];

        $jsonPayload = json_encode($payload);

        $headers = [
            'Content-Type: application/json',
            'X-Webhook-Event: ' . $event
        ];

        // Assinar payload se secret configurado
        if ($secret) {
            $signature = hash_hmac('sha256', $jsonPayload, $secret);
            $headers[] = 'X-Webhook-Signature: ' . $signature;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $success = $httpCode >= 200 && $httpCode < 300;

        return [
            'success' => $success,
            'message' => $success ? 'Webhook enviado' : ($error ?: "HTTP {$httpCode}")
        ];
    }

    /**
     * Enviar Slack
     */
    private function sendSlack(array $channel, string $event, array $data, string $severity): array
    {
        $config = json_decode($channel['config'], true);
        $webhookUrl = $config['webhook_url'] ?? null;

        if (!$webhookUrl) {
            return ['success' => false, 'message' => 'Webhook Slack não configurado'];
        }

        $color = match ($severity) {
            self::SEVERITY_ERROR, self::SEVERITY_CRITICAL => '#dc3545',
            self::SEVERITY_WARNING => '#ffc107',
            default => '#28a745'
        };

        $payload = [
            'attachments' => [
                [
                    'color' => $color,
                    'title' => $this->getEventTitle($event),
                    'text' => $this->getSlackText($event, $data),
                    'fields' => $this->getSlackFields($data),
                    'footer' => 'Pricing Intelligence | eskill.com.br',
                    'ts' => time()
                ]
            ]
        ];

        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $httpCode === 200,
            'message' => $httpCode === 200 ? 'Slack enviado' : 'Falha no Slack'
        ];
    }

    /**
     * Enviar Discord
     */
    private function sendDiscord(array $channel, string $event, array $data, string $severity): array
    {
        $config = json_decode($channel['config'], true);
        $webhookUrl = $config['webhook_url'] ?? null;

        if (!$webhookUrl) {
            return ['success' => false, 'message' => 'Webhook Discord não configurado'];
        }

        $color = match ($severity) {
            self::SEVERITY_ERROR, self::SEVERITY_CRITICAL => 0xdc3545,
            self::SEVERITY_WARNING => 0xffc107,
            default => 0x28a745
        };

        $payload = [
            'embeds' => [
                [
                    'title' => $this->getEventTitle($event),
                    'description' => $this->getSlackText($event, $data),
                    'color' => $color,
                    'fields' => array_map(fn($f) => [
                        'name' => $f['title'],
                        'value' => $f['value'],
                        'inline' => $f['short'] ?? true
                    ], $this->getSlackFields($data)),
                    'footer' => [
                        'text' => 'Pricing Intelligence'
                    ],
                    'timestamp' => date('c')
                ]
            ]
        ];

        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'message' => $httpCode >= 200 && $httpCode < 300 ? 'Discord enviado' : 'Falha no Discord'
        ];
    }

    /**
     * Enviar Push notification
     */
    private function sendPush(array $channel, string $event, array $data, string $severity): array
    {
        // Implementação depende do serviço de push (Firebase, OneSignal, etc)
        $config = json_decode($channel['config'], true);

        // Registrar para processamento posterior ou usar API específica
        $this->queuePushNotification($config, $event, $data, $severity);

        return [
            'success' => true,
            'message' => 'Push enfileirado'
        ];
    }

    /**
     * Registrar log interno
     */
    private function sendLog(array $channel, string $event, array $data, string $severity): array
    {
        // Sempre salva no banco - já feito em logNotification
        return ['success' => true, 'message' => 'Log registrado'];
    }

    /**
     * Obter canais habilitados para evento/severidade
     */
    private function getEnabledChannels(string $event, string $severity): array
    {
        $stmt = $this->db->prepare("
            SELECT nc.* 
            FROM notification_channels nc
            JOIN notification_subscriptions ns ON nc.id = ns.channel_id
            WHERE nc.account_id = :account_id
            AND nc.is_active = 1
            AND ns.event_type = :event
            AND (ns.min_severity IS NULL OR ns.min_severity <= :severity_level)
        ");

        $severityLevel = match ($severity) {
            self::SEVERITY_INFO => 1,
            self::SEVERITY_WARNING => 2,
            self::SEVERITY_ERROR => 3,
            self::SEVERITY_CRITICAL => 4,
            default => 1
        };

        $stmt->execute([
            'account_id' => $this->accountId,
            'event' => $event,
            'severity_level' => $severityLevel
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Criar canal de notificação
     */
    public function createChannel(array $data): array
    {
        $required = ['name', 'type', 'config'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Campo {$field} é obrigatório"];
            }
        }

        $validTypes = [
            self::TYPE_EMAIL, self::TYPE_WEBHOOK, self::TYPE_SLACK,
            self::TYPE_DISCORD, self::TYPE_PUSH, self::TYPE_LOG
        ];

        if (!in_array($data['type'], $validTypes)) {
            return ['success' => false, 'message' => 'Tipo de canal inválido'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO notification_channels
            (account_id, name, type, config, is_active, created_at)
            VALUES
            (:account_id, :name, :type, :config, :is_active, NOW())
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'name' => $data['name'],
            'type' => $data['type'],
            'config' => json_encode($data['config']),
            'is_active' => $data['is_active'] ?? true
        ]);

        return [
            'success' => true,
            'channel_id' => $this->db->lastInsertId(),
            'message' => 'Canal criado com sucesso'
        ];
    }

    /**
     * Listar canais
     */
    public function listChannels(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM notification_channels
            WHERE account_id = :account_id
            ORDER BY created_at DESC
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($channels as &$channel) {
            $channel['config'] = json_decode($channel['config'], true);
            
            // Ocultar secrets
            if (isset($channel['config']['secret'])) {
                $channel['config']['secret'] = '***';
            }
        }

        return ['success' => true, 'channels' => $channels];
    }

    /**
     * Atualizar canal
     */
    public function updateChannel(int $channelId, array $data): array
    {
        $updates = [];
        $params = ['id' => $channelId, 'account_id' => $this->accountId];

        if (isset($data['name'])) {
            $updates[] = 'name = :name';
            $params['name'] = $data['name'];
        }

        if (isset($data['config'])) {
            $updates[] = 'config = :config';
            $params['config'] = json_encode($data['config']);
        }

        if (isset($data['is_active'])) {
            $updates[] = 'is_active = :is_active';
            $params['is_active'] = $data['is_active'];
        }

        if (empty($updates)) {
            return ['success' => false, 'message' => 'Nada para atualizar'];
        }

        $updates[] = 'updated_at = NOW()';
        $updateClause = implode(', ', $updates);

        $stmt = $this->db->prepare("
            UPDATE notification_channels
            SET {$updateClause}
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute($params);

        return ['success' => true, 'message' => 'Canal atualizado'];
    }

    /**
     * Deletar canal
     */
    public function deleteChannel(int $channelId): array
    {
        $stmt = $this->db->prepare("
            DELETE FROM notification_channels
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute(['id' => $channelId, 'account_id' => $this->accountId]);

        return ['success' => true, 'message' => 'Canal excluído'];
    }

    /**
     * Criar subscrição
     */
    public function subscribe(int $channelId, string $eventType, int $minSeverity = 1): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO notification_subscriptions
            (channel_id, event_type, min_severity, created_at)
            VALUES (:channel_id, :event_type, :min_severity, NOW())
            ON DUPLICATE KEY UPDATE min_severity = :min_severity2
        ");

        $stmt->execute([
            'channel_id' => $channelId,
            'event_type' => $eventType,
            'min_severity' => $minSeverity,
            'min_severity2' => $minSeverity
        ]);

        return ['success' => true, 'message' => 'Inscrito com sucesso'];
    }

    /**
     * Remover subscrição
     */
    public function unsubscribe(int $channelId, string $eventType): array
    {
        $stmt = $this->db->prepare("
            DELETE FROM notification_subscriptions
            WHERE channel_id = :channel_id AND event_type = :event_type
        ");
        $stmt->execute([
            'channel_id' => $channelId,
            'event_type' => $eventType
        ]);

        return ['success' => true, 'message' => 'Inscrição removida'];
    }

    /**
     * Listar subscrições de um canal
     */
    public function getSubscriptions(int $channelId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM notification_subscriptions
            WHERE channel_id = :channel_id
        ");
        $stmt->execute(['channel_id' => $channelId]);

        return [
            'success' => true,
            'subscriptions' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Obter histórico de notificações
     */
    public function getHistory(array $filters = []): array
    {
        $where = ['nl.account_id = :account_id'];
        $params = ['account_id' => $this->accountId];

        if (isset($filters['event'])) {
            $where[] = 'nl.event_type = :event';
            $params['event'] = $filters['event'];
        }

        if (isset($filters['channel_id'])) {
            $where[] = 'nl.channel_id = :channel_id';
            $params['channel_id'] = $filters['channel_id'];
        }

        if (isset($filters['success'])) {
            $where[] = 'nl.success = :success';
            $params['success'] = $filters['success'];
        }

        $whereClause = implode(' AND ', $where);

        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        $limitSql = max(1, min((int)$limit, 200));
        $offsetSql = max(0, (int)$offset);

        $stmt = $this->db->prepare("
            SELECT nl.*, nc.name as channel_name, nc.type as channel_type
            FROM notification_logs nl
            LEFT JOIN notification_channels nc ON nl.channel_id = nc.id
            WHERE {$whereClause}
            ORDER BY nl.created_at DESC
            LIMIT {$limitSql} OFFSET {$offsetSql}
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        $stmt->execute();

        return [
            'success' => true,
            'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Testar canal
     */
    public function testChannel(int $channelId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM notification_channels
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute(['id' => $channelId, 'account_id' => $this->accountId]);
        $channel = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$channel) {
            return ['success' => false, 'message' => 'Canal não encontrado'];
        }

        $testData = [
            'message' => 'Esta é uma notificação de teste',
            'item_id' => 'TEST123',
            'price' => 99.90,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $result = $this->sendToChannel($channel, 'test', $testData, self::SEVERITY_INFO);

        return [
            'success' => $result['success'],
            'message' => $result['success'] 
                ? 'Teste enviado com sucesso!' 
                : 'Falha no teste: ' . ($result['message'] ?? 'Erro desconhecido')
        ];
    }

    /**
     * Log de notificação
     */
    private function logNotification(int $channelId, string $event, array $data, string $severity, bool $success): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO notification_logs
            (account_id, channel_id, event_type, severity, data, success, created_at)
            VALUES
            (:account_id, :channel_id, :event_type, :severity, :data, :success, NOW())
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'channel_id' => $channelId,
            'event_type' => $event,
            'severity' => $severity,
            'data' => json_encode($data),
            'success' => $success
        ]);
    }

    /**
     * Enfileirar push notification
     */
    private function queuePushNotification(array $config, string $event, array $data, string $severity): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO push_notification_queue
            (account_id, config, event_type, data, severity, status, created_at)
            VALUES
            (:account_id, :config, :event_type, :data, :severity, 'pending', NOW())
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'config' => json_encode($config),
            'event_type' => $event,
            'data' => json_encode($data),
            'severity' => $severity
        ]);
    }

    /**
     * Obter título do evento
     */
    private function getEventTitle(string $event): string
    {
        return match ($event) {
            self::EVENT_PRICE_CHANGE => '💰 Mudança de Preço',
            self::EVENT_COMPETITOR_ALERT => '⚠️ Alerta de Concorrente',
            self::EVENT_MARGIN_ALERT => '📉 Alerta de Margem',
            self::EVENT_RULE_EXECUTED => '⚙️ Regra Executada',
            self::EVENT_SCHEDULE_EXECUTED => '📅 Agendamento Executado',
            self::EVENT_BULK_COMPLETED => '📦 Edição em Massa Concluída',
            self::EVENT_AB_TEST_COMPLETE => '🧪 Teste A/B Concluído',
            self::EVENT_OPTIMIZATION_SUGGESTION => '💡 Sugestão de Otimização',
            'test' => '🔔 Teste de Notificação',
            default => '📢 Notificação'
        };
    }

    /**
     * Obter subject do email
     */
    private function getEmailSubject(string $event, string $severity, array $data): string
    {
        $prefix = match ($severity) {
            self::SEVERITY_CRITICAL => '[CRÍTICO] ',
            self::SEVERITY_ERROR => '[ERRO] ',
            self::SEVERITY_WARNING => '[AVISO] ',
            default => ''
        };

        return $prefix . $this->getEventTitle($event);
    }

    /**
     * Obter corpo do email
     */
    private function getEmailBody(string $event, array $data, string $severity): string
    {
        $color = match ($severity) {
            self::SEVERITY_CRITICAL, self::SEVERITY_ERROR => '#dc3545',
            self::SEVERITY_WARNING => '#ffc107',
            default => '#28a745'
        };

        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: {$color}; color: white; padding: 15px; border-radius: 5px 5px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                .footer { text-align: center; padding: 10px; font-size: 12px; color: #666; }
                table { width: 100%; border-collapse: collapse; }
                td { padding: 8px; border-bottom: 1px solid #eee; }
                .label { font-weight: bold; width: 40%; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin:0;'>" . $this->getEventTitle($event) . "</h2>
                </div>
                <div class='content'>
                    <table>";

        foreach ($data as $key => $value) {
            $label = ucwords(str_replace('_', ' ', $key));
            $displayValue = is_array($value) ? json_encode($value) : $value;
            $html .= "<tr><td class='label'>{$label}:</td><td>{$displayValue}</td></tr>";
        }

        $html .= "
                    </table>
                </div>
                <div class='footer'>
                    Pricing Intelligence | eskill.com.br<br>
                    " . date('d/m/Y H:i:s') . "
                </div>
            </div>
        </body>
        </html>";

        return $html;
    }

    /**
     * Obter texto para Slack
     */
    private function getSlackText(string $event, array $data): string
    {
        return match ($event) {
            self::EVENT_PRICE_CHANGE => "Preço alterado de R$ " . ($data['old_price'] ?? '?') . " para R$ " . ($data['new_price'] ?? '?'),
            self::EVENT_COMPETITOR_ALERT => "Concorrente com preço " . ($data['competitor_price'] ?? '?') . " detectado",
            self::EVENT_MARGIN_ALERT => "Margem atual: " . ($data['margin'] ?? '?') . "%",
            self::EVENT_RULE_EXECUTED => "Regra '" . ($data['rule_name'] ?? 'Desconhecida') . "' executada",
            self::EVENT_SCHEDULE_EXECUTED => "Agendamento executado para item " . ($data['item_id'] ?? '?'),
            self::EVENT_BULK_COMPLETED => ($data['success_count'] ?? 0) . " itens atualizados com sucesso",
            'test' => 'Teste de notificação funcionando!',
            default => json_encode($data)
        };
    }

    /**
     * Obter campos para Slack
     */
    private function getSlackFields(array $data): array
    {
        $fields = [];

        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $fields[] = [
                    'title' => ucwords(str_replace('_', ' ', $key)),
                    'value' => (string)$value,
                    'short' => strlen((string)$value) < 30
                ];
            }
        }

        return array_slice($fields, 0, 10); // Máximo 10 campos
    }

    /**
     * Obter eventos disponíveis
     */
    public function getAvailableEvents(): array
    {
        return [
            self::EVENT_PRICE_CHANGE => 'Mudança de Preço',
            self::EVENT_COMPETITOR_ALERT => 'Alerta de Concorrente',
            self::EVENT_MARGIN_ALERT => 'Alerta de Margem',
            self::EVENT_RULE_EXECUTED => 'Regra Executada',
            self::EVENT_SCHEDULE_EXECUTED => 'Agendamento Executado',
            self::EVENT_BULK_COMPLETED => 'Edição em Massa Concluída',
            self::EVENT_AB_TEST_COMPLETE => 'Teste A/B Concluído',
            self::EVENT_OPTIMIZATION_SUGGESTION => 'Sugestão de Otimização'
        ];
    }

    /**
     * Obter severidades disponíveis
     */
    public function getAvailableSeverities(): array
    {
        return [
            1 => self::SEVERITY_INFO,
            2 => self::SEVERITY_WARNING,
            3 => self::SEVERITY_ERROR,
            4 => self::SEVERITY_CRITICAL
        ];
    }
}
