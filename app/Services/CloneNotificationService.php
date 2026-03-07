<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Clone Notification Service
 *
 * Sistema de notificações para alertas de clonagem:
 * - Slack webhooks
 * - Discord webhooks
 * - Configuração por conta/usuário
 * - Templates de mensagens
 * - Filtros por severidade
 */
class CloneNotificationService
{
    private PDO $db;
    private int $accountId;
    private array $config;
    private ?int $userId;

    // Tipos de eventos
    public const EVENT_JOB_STARTED = 'job.started';
    public const EVENT_JOB_COMPLETED = 'job.completed';
    public const EVENT_JOB_FAILED = 'job.failed';
    public const EVENT_ITEM_CLONED = 'item.cloned';
    public const EVENT_ITEM_FAILED = 'item.failed';
    public const EVENT_BATCH_PROGRESS = 'batch.progress';
    public const EVENT_ALERT_CRITICAL = 'alert.critical';
    public const EVENT_METRICS_DAILY = 'metrics.daily';

    // Severidades
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_CRITICAL = 'critical';

    public function __construct(int $accountId, ?int $userId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->userId = $userId;

        $this->config = [
            'timeout' => 10,
            'max_retries' => 3,
            'retry_delay' => 2,
        ];
    }

    /**
     * Configura webhook Slack para a conta
     */
    public function configureSlack(string $webhookUrl, array $options = []): int
    {
        return $this->saveWebhookConfig('slack', [
            'url' => $webhookUrl,
            'channel' => $options['channel'] ?? null,
            'username' => $options['username'] ?? 'Clone Bot',
            'icon_emoji' => $options['icon_emoji'] ?? ':robot_face:',
            'events' => $options['events'] ?? ['*'],
            'min_severity' => $options['min_severity'] ?? self::SEVERITY_INFO,
        ]);
    }

    /**
     * Configura webhook Discord para a conta
     */
    public function configureDiscord(string $webhookUrl, array $options = []): int
    {
        return $this->saveWebhookConfig('discord', [
            'url' => $webhookUrl,
            'username' => $options['username'] ?? 'Clone Bot',
            'avatar_url' => $options['avatar_url'] ?? null,
            'events' => $options['events'] ?? ['*'],
            'min_severity' => $options['min_severity'] ?? self::SEVERITY_INFO,
        ]);
    }

    /**
     * Salva configuração de webhook no banco
     */
    private function saveWebhookConfig(string $type, array $config): int
    {
        $this->ensureTableExists();

        // Verificar se já existe configuração
        $stmt = $this->db->prepare("
            SELECT id FROM clone_notification_webhooks
            WHERE account_id = :account_id AND type = :type
        ");
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':type' => $type,
        ]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE clone_notification_webhooks
                SET url = :url, config = :config, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $existing['id'],
                ':url' => $config['url'],
                ':config' => json_encode($config),
            ]);
            return (int) $existing['id'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO clone_notification_webhooks
            (account_id, user_id, type, url, config, status, created_at, updated_at)
            VALUES
            (:account_id, :user_id, :type, :url, :config, 'active', NOW(), NOW())
        ");
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':user_id' => $this->userId,
            ':type' => $type,
            ':url' => $config['url'],
            ':config' => json_encode($config),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Notifica sobre início de job
     */
    public function notifyJobStarted(int $jobId, array $jobData): array
    {
        return $this->notify(self::EVENT_JOB_STARTED, self::SEVERITY_INFO, [
            'job_id' => $jobId,
            'items_count' => $jobData['total_items'] ?? 0,
            'source_type' => $jobData['source_type'] ?? 'unknown',
            'target_account' => $jobData['target_account'] ?? null,
            'started_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Notifica sobre conclusão de job
     */
    public function notifyJobCompleted(int $jobId, array $stats): array
    {
        $success = ($stats['failed'] ?? 0) === 0;
        $severity = $success ? self::SEVERITY_INFO : self::SEVERITY_WARNING;

        return $this->notify(self::EVENT_JOB_COMPLETED, $severity, [
            'job_id' => $jobId,
            'total_items' => $stats['total'] ?? 0,
            'success' => $stats['success'] ?? 0,
            'failed' => $stats['failed'] ?? 0,
            'duration' => $stats['duration'] ?? 'N/A',
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Notifica sobre falha de job
     */
    public function notifyJobFailed(int $jobId, string $error): array
    {
        return $this->notify(self::EVENT_JOB_FAILED, self::SEVERITY_ERROR, [
            'job_id' => $jobId,
            'error' => $error,
            'failed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Notifica sobre item clonado com sucesso
     */
    public function notifyItemCloned(string $originalItemId, string $newItemId, array $data = []): array
    {
        return $this->notify(self::EVENT_ITEM_CLONED, self::SEVERITY_INFO, [
            'original_item_id' => $originalItemId,
            'new_item_id' => $newItemId,
            'title' => $data['title'] ?? null,
            'price' => $data['price'] ?? null,
        ]);
    }

    /**
     * Notifica sobre falha na clonagem de item
     */
    public function notifyItemFailed(string $itemId, string $error): array
    {
        return $this->notify(self::EVENT_ITEM_FAILED, self::SEVERITY_ERROR, [
            'item_id' => $itemId,
            'error' => $error,
            'failed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Notifica progresso do batch
     */
    public function notifyBatchProgress(int $jobId, int $processed, int $total): array
    {
        $percentage = $total > 0 ? round(($processed / $total) * 100, 1) : 0;

        // Só notifica em marcos específicos: 25%, 50%, 75%
        if (!in_array($percentage, [25, 50, 75])) {
            return ['skipped' => true, 'reason' => 'Not a milestone'];
        }

        return $this->notify(self::EVENT_BATCH_PROGRESS, self::SEVERITY_INFO, [
            'job_id' => $jobId,
            'processed' => $processed,
            'total' => $total,
            'percentage' => $percentage,
        ]);
    }

    /**
     * Notifica alerta crítico
     */
    public function notifyAlertCritical(string $alertType, string $message, array $data = []): array
    {
        return $this->notify(self::EVENT_ALERT_CRITICAL, self::SEVERITY_CRITICAL, [
            'alert_type' => $alertType,
            'message' => $message,
            'data' => $data,
            'triggered_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Notifica métricas diárias
     */
    public function notifyDailyMetrics(array $metrics): array
    {
        return $this->notify(self::EVENT_METRICS_DAILY, self::SEVERITY_INFO, [
            'date' => date('Y-m-d'),
            'total_jobs' => $metrics['total_jobs'] ?? 0,
            'items_cloned' => $metrics['items_cloned'] ?? 0,
            'success_rate' => $metrics['success_rate'] ?? 0,
            'avg_duration' => $metrics['avg_duration'] ?? 'N/A',
            'top_errors' => $metrics['top_errors'] ?? [],
        ]);
    }

    /**
     * Envia notificação para todos os webhooks configurados
     */
    public function notify(string $event, string $severity, array $payload): array
    {
        $webhooks = $this->getActiveWebhooks($event, $severity);

        if (empty($webhooks)) {
            return ['skipped' => true, 'reason' => 'No active webhooks'];
        }

        $results = [];

        foreach ($webhooks as $webhook) {
            $result = $this->sendWebhook($webhook, $event, $severity, $payload);
            $results[] = [
                'webhook_id' => $webhook['id'],
                'type' => $webhook['type'],
                'success' => $result['success'],
                'status_code' => $result['status_code'] ?? null,
                'error' => $result['error'] ?? null,
            ];

            // Log no banco
            $this->logNotification($webhook['id'], $event, $severity, $result);
        }

        return $results;
    }

    /**
     * Obtém webhooks ativos para evento e severidade
     */
    private function getActiveWebhooks(string $event, string $severity): array
    {
        $this->ensureTableExists();

        $stmt = $this->db->prepare("
            SELECT * FROM clone_notification_webhooks
            WHERE account_id = :account_id
            AND status = 'active'
        ");
        $stmt->execute([':account_id' => $this->accountId]);
        $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filtered = [];
        $severityOrder = [
            self::SEVERITY_INFO => 1,
            self::SEVERITY_WARNING => 2,
            self::SEVERITY_ERROR => 3,
            self::SEVERITY_CRITICAL => 4,
        ];

        foreach ($webhooks as $webhook) {
            $config = json_decode($webhook['config'], true) ?? [];

            // Verificar eventos
            $events = $config['events'] ?? ['*'];
            if (!in_array('*', $events) && !in_array($event, $events)) {
                continue;
            }

            // Verificar severidade mínima
            $minSeverity = $config['min_severity'] ?? self::SEVERITY_INFO;
            if (($severityOrder[$severity] ?? 0) < ($severityOrder[$minSeverity] ?? 0)) {
                continue;
            }

            $filtered[] = $webhook;
        }

        return $filtered;
    }

    /**
     * Envia para webhook específico
     */
    private function sendWebhook(array $webhook, string $event, string $severity, array $payload): array
    {
        $config = json_decode($webhook['config'], true) ?? [];

        try {
            switch ($webhook['type']) {
                case 'slack':
                    return $this->sendSlackNotification($webhook['url'], $event, $severity, $payload, $config);

                case 'discord':
                    return $this->sendDiscordNotification($webhook['url'], $event, $severity, $payload, $config);

                default:
                    throw new \Exception("Tipo de webhook não suportado: {$webhook['type']}");
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Envia notificação para Slack
     */
    private function sendSlackNotification(
        string $webhookUrl,
        string $event,
        string $severity,
        array $payload,
        array $config
    ): array {
        $message = $this->formatSlackMessage($event, $severity, $payload);

        $data = [
            'text' => $message['text'],
            'attachments' => $message['attachments'] ?? [],
        ];

        if (!empty($config['channel'])) {
            $data['channel'] = $config['channel'];
        }
        if (!empty($config['username'])) {
            $data['username'] = $config['username'];
        }
        if (!empty($config['icon_emoji'])) {
            $data['icon_emoji'] = $config['icon_emoji'];
        }

        return $this->sendHttpRequest($webhookUrl, $data);
    }

    /**
     * Envia notificação para Discord
     */
    private function sendDiscordNotification(
        string $webhookUrl,
        string $event,
        string $severity,
        array $payload,
        array $config
    ): array {
        $embed = $this->formatDiscordEmbed($event, $severity, $payload);

        $data = [
            'embeds' => [$embed],
        ];

        if (!empty($config['username'])) {
            $data['username'] = $config['username'];
        }
        if (!empty($config['avatar_url'])) {
            $data['avatar_url'] = $config['avatar_url'];
        }

        return $this->sendHttpRequest($webhookUrl, $data);
    }

    /**
     * Formata mensagem para Slack
     */
    private function formatSlackMessage(string $event, string $severity, array $payload): array
    {
        $eventLabels = [
            self::EVENT_JOB_STARTED => '🚀 Job de Clonagem Iniciado',
            self::EVENT_JOB_COMPLETED => '✅ Job de Clonagem Concluído',
            self::EVENT_JOB_FAILED => '❌ Job de Clonagem Falhou',
            self::EVENT_ITEM_CLONED => '📦 Item Clonado',
            self::EVENT_ITEM_FAILED => '⚠️ Falha ao Clonar Item',
            self::EVENT_BATCH_PROGRESS => '📊 Progresso do Batch',
            self::EVENT_ALERT_CRITICAL => '🚨 Alerta Crítico',
            self::EVENT_METRICS_DAILY => '📈 Métricas Diárias',
        ];

        $severityColors = [
            self::SEVERITY_INFO => '#36a64f',
            self::SEVERITY_WARNING => '#FFA500',
            self::SEVERITY_ERROR => '#FF6B6B',
            self::SEVERITY_CRITICAL => '#dc3545',
        ];

        $title = $eventLabels[$event] ?? "Clone Event: $event";
        $color = $severityColors[$severity] ?? '#808080';

        $fields = $this->buildSlackFields($event, $payload);

        return [
            'text' => $title,
            'attachments' => [
                [
                    'color' => $color,
                    'fields' => $fields,
                    'footer' => 'Eskill Clone Bot',
                    'ts' => time(),
                ],
            ],
        ];
    }

    /**
     * Constrói campos para mensagem Slack
     */
    private function buildSlackFields(string $event, array $payload): array
    {
        $fields = [];

        switch ($event) {
            case self::EVENT_JOB_STARTED:
                $fields[] = ['title' => 'Job ID', 'value' => (string)($payload['job_id'] ?? 'N/A'), 'short' => true];
                $fields[] = ['title' => 'Itens', 'value' => (string)($payload['items_count'] ?? 0), 'short' => true];
                $fields[] = ['title' => 'Fonte', 'value' => $payload['source_type'] ?? 'N/A', 'short' => true];
                break;

            case self::EVENT_JOB_COMPLETED:
                $fields[] = ['title' => 'Job ID', 'value' => (string)($payload['job_id'] ?? 'N/A'), 'short' => true];
                $fields[] = ['title' => 'Total', 'value' => (string)($payload['total_items'] ?? 0), 'short' => true];
                $fields[] = ['title' => 'Sucesso', 'value' => (string)($payload['success'] ?? 0), 'short' => true];
                $fields[] = ['title' => 'Falhas', 'value' => (string)($payload['failed'] ?? 0), 'short' => true];
                $fields[] = ['title' => 'Duração', 'value' => $payload['duration'] ?? 'N/A', 'short' => true];
                break;

            case self::EVENT_JOB_FAILED:
                $fields[] = ['title' => 'Job ID', 'value' => (string)($payload['job_id'] ?? 'N/A'), 'short' => true];
                $fields[] = ['title' => 'Erro', 'value' => $payload['error'] ?? 'Desconhecido', 'short' => false];
                break;

            case self::EVENT_BATCH_PROGRESS:
                $fields[] = ['title' => 'Job ID', 'value' => (string)($payload['job_id'] ?? 'N/A'), 'short' => true];
                $fields[] = ['title' => 'Progresso', 'value' => "{$payload['processed']}/{$payload['total']} ({$payload['percentage']}%)", 'short' => true];
                break;

            case self::EVENT_ALERT_CRITICAL:
                $fields[] = ['title' => 'Tipo', 'value' => $payload['alert_type'] ?? 'N/A', 'short' => true];
                $fields[] = ['title' => 'Mensagem', 'value' => $payload['message'] ?? 'N/A', 'short' => false];
                break;

            case self::EVENT_METRICS_DAILY:
                $fields[] = ['title' => 'Data', 'value' => $payload['date'] ?? date('Y-m-d'), 'short' => true];
                $fields[] = ['title' => 'Jobs', 'value' => (string)($payload['total_jobs'] ?? 0), 'short' => true];
                $fields[] = ['title' => 'Itens Clonados', 'value' => (string)($payload['items_cloned'] ?? 0), 'short' => true];
                $fields[] = ['title' => 'Taxa de Sucesso', 'value' => ($payload['success_rate'] ?? 0) . '%', 'short' => true];
                break;

            default:
                // Campos genéricos
                foreach ($payload as $key => $value) {
                    if (!is_array($value)) {
                        $fields[] = ['title' => ucfirst(str_replace('_', ' ', $key)), 'value' => (string)$value, 'short' => true];
                    }
                }
        }

        return $fields;
    }

    /**
     * Formata embed para Discord
     */
    private function formatDiscordEmbed(string $event, string $severity, array $payload): array
    {
        $eventLabels = [
            self::EVENT_JOB_STARTED => '🚀 Job de Clonagem Iniciado',
            self::EVENT_JOB_COMPLETED => '✅ Job de Clonagem Concluído',
            self::EVENT_JOB_FAILED => '❌ Job de Clonagem Falhou',
            self::EVENT_ITEM_CLONED => '📦 Item Clonado',
            self::EVENT_ITEM_FAILED => '⚠️ Falha ao Clonar Item',
            self::EVENT_BATCH_PROGRESS => '📊 Progresso do Batch',
            self::EVENT_ALERT_CRITICAL => '🚨 Alerta Crítico',
            self::EVENT_METRICS_DAILY => '📈 Métricas Diárias',
        ];

        $severityColors = [
            self::SEVERITY_INFO => 0x36A64F,
            self::SEVERITY_WARNING => 0xFFA500,
            self::SEVERITY_ERROR => 0xFF6B6B,
            self::SEVERITY_CRITICAL => 0xDC3545,
        ];

        $title = $eventLabels[$event] ?? "Clone Event: $event";
        $color = $severityColors[$severity] ?? 0x808080;

        $fields = $this->buildDiscordFields($event, $payload);

        return [
            'title' => $title,
            'color' => $color,
            'fields' => $fields,
            'footer' => [
                'text' => 'Eskill Clone Bot',
            ],
            'timestamp' => date('c'),
        ];
    }

    /**
     * Constrói campos para embed Discord
     */
    private function buildDiscordFields(string $event, array $payload): array
    {
        $fields = [];

        switch ($event) {
            case self::EVENT_JOB_STARTED:
                $fields[] = ['name' => 'Job ID', 'value' => (string)($payload['job_id'] ?? 'N/A'), 'inline' => true];
                $fields[] = ['name' => 'Itens', 'value' => (string)($payload['items_count'] ?? 0), 'inline' => true];
                $fields[] = ['name' => 'Fonte', 'value' => $payload['source_type'] ?? 'N/A', 'inline' => true];
                break;

            case self::EVENT_JOB_COMPLETED:
                $fields[] = ['name' => 'Job ID', 'value' => (string)($payload['job_id'] ?? 'N/A'), 'inline' => true];
                $fields[] = ['name' => 'Total', 'value' => (string)($payload['total_items'] ?? 0), 'inline' => true];
                $fields[] = ['name' => 'Sucesso', 'value' => (string)($payload['success'] ?? 0), 'inline' => true];
                $fields[] = ['name' => 'Falhas', 'value' => (string)($payload['failed'] ?? 0), 'inline' => true];
                $fields[] = ['name' => 'Duração', 'value' => $payload['duration'] ?? 'N/A', 'inline' => true];
                break;

            case self::EVENT_JOB_FAILED:
                $fields[] = ['name' => 'Job ID', 'value' => (string)($payload['job_id'] ?? 'N/A'), 'inline' => true];
                $fields[] = ['name' => 'Erro', 'value' => mb_substr($payload['error'] ?? 'Desconhecido', 0, 1024), 'inline' => false];
                break;

            case self::EVENT_BATCH_PROGRESS:
                $fields[] = ['name' => 'Job ID', 'value' => (string)($payload['job_id'] ?? 'N/A'), 'inline' => true];
                $fields[] = ['name' => 'Progresso', 'value' => "{$payload['processed']}/{$payload['total']} ({$payload['percentage']}%)", 'inline' => true];
                break;

            case self::EVENT_ALERT_CRITICAL:
                $fields[] = ['name' => 'Tipo', 'value' => $payload['alert_type'] ?? 'N/A', 'inline' => true];
                $fields[] = ['name' => 'Mensagem', 'value' => mb_substr($payload['message'] ?? 'N/A', 0, 1024), 'inline' => false];
                break;

            case self::EVENT_METRICS_DAILY:
                $fields[] = ['name' => 'Data', 'value' => $payload['date'] ?? date('Y-m-d'), 'inline' => true];
                $fields[] = ['name' => 'Jobs', 'value' => (string)($payload['total_jobs'] ?? 0), 'inline' => true];
                $fields[] = ['name' => 'Itens Clonados', 'value' => (string)($payload['items_cloned'] ?? 0), 'inline' => true];
                $fields[] = ['name' => 'Taxa de Sucesso', 'value' => ($payload['success_rate'] ?? 0) . '%', 'inline' => true];
                break;

            default:
                foreach ($payload as $key => $value) {
                    if (!is_array($value)) {
                        $fields[] = ['name' => ucfirst(str_replace('_', ' ', $key)), 'value' => (string)$value, 'inline' => true];
                    }
                }
        }

        return $fields;
    }

    /**
     * Envia requisição HTTP
     */
    private function sendHttpRequest(string $url, array $data): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => $error,
            ];
        }

        $success = $statusCode >= 200 && $statusCode < 300;

        return [
            'success' => $success,
            'status_code' => $statusCode,
            'response' => $response,
            'error' => $success ? null : "HTTP $statusCode",
        ];
    }

    /**
     * Log de notificação enviada
     */
    private function logNotification(int $webhookId, string $event, string $severity, array $result): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO clone_notification_logs
                (webhook_id, account_id, event, severity, success, status_code, error, created_at)
                VALUES
                (:webhook_id, :account_id, :event, :severity, :success, :status_code, :error, NOW())
            ");
            $stmt->execute([
                ':webhook_id' => $webhookId,
                ':account_id' => $this->accountId,
                ':event' => $event,
                ':severity' => $severity,
                ':success' => $result['success'] ? 1 : 0,
                ':status_code' => $result['status_code'] ?? null,
                ':error' => $result['error'] ?? null,
            ]);
        } catch (\Exception $e) {
            // Ignora erro de log
        }
    }

    /**
     * Obtém histórico de notificações
     */
    public function getNotificationHistory(int $limit = 100, ?string $event = null): array
    {
        $limitSql = max(1, min(200, (int)$limit));

        $sql = "
            SELECT l.*, w.type as webhook_type, w.url as webhook_url
            FROM clone_notification_logs l
            JOIN clone_notification_webhooks w ON l.webhook_id = w.id
            WHERE l.account_id = :account_id
        ";

        $params = [':account_id' => $this->accountId];

        if ($event) {
            $sql .= " AND l.event = :event";
            $params[':event'] = $event;
        }

        $sql .= " ORDER BY l.created_at DESC LIMIT {$limitSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        if ($event) {
            $stmt->bindValue(':event', $event, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista webhooks configurados
     */
    public function listWebhooks(): array
    {
        $this->ensureTableExists();

        $stmt = $this->db->prepare("
            SELECT id, type, url, status,
                   JSON_UNQUOTE(JSON_EXTRACT(config, '$.events')) as events,
                   JSON_UNQUOTE(JSON_EXTRACT(config, '$.min_severity')) as min_severity,
                   created_at, updated_at
            FROM clone_notification_webhooks
            WHERE account_id = :account_id
            ORDER BY created_at DESC
        ");
        $stmt->execute([':account_id' => $this->accountId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Desativa webhook
     */
    public function disableWebhook(int $webhookId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE clone_notification_webhooks
            SET status = 'inactive', updated_at = NOW()
            WHERE id = :id AND account_id = :account_id
        ");

        return $stmt->execute([
            ':id' => $webhookId,
            ':account_id' => $this->accountId,
        ]);
    }

    /**
     * Ativa webhook
     */
    public function enableWebhook(int $webhookId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE clone_notification_webhooks
            SET status = 'active', updated_at = NOW()
            WHERE id = :id AND account_id = :account_id
        ");

        return $stmt->execute([
            ':id' => $webhookId,
            ':account_id' => $this->accountId,
        ]);
    }

    /**
     * Remove webhook
     */
    public function deleteWebhook(int $webhookId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM clone_notification_webhooks
            WHERE id = :id AND account_id = :account_id
        ");

        return $stmt->execute([
            ':id' => $webhookId,
            ':account_id' => $this->accountId,
        ]);
    }

    /**
     * Testa conexão com webhook
     */
    public function testWebhook(int $webhookId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM clone_notification_webhooks
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute([
            ':id' => $webhookId,
            ':account_id' => $this->accountId,
        ]);
        $webhook = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$webhook) {
            return ['success' => false, 'error' => 'Webhook não encontrado'];
        }

        return $this->sendWebhook($webhook, 'test', self::SEVERITY_INFO, [
            'message' => '🧪 Teste de conexão do Clone Bot',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Garante que as tabelas existem
     */
    private function ensureTableExists(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_notification_webhooks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT NOT NULL,
                user_id INT NULL,
                type ENUM('slack', 'discord') NOT NULL,
                url VARCHAR(500) NOT NULL,
                config JSON,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_account (account_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_notification_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                webhook_id INT NOT NULL,
                account_id INT NOT NULL,
                event VARCHAR(50) NOT NULL,
                severity VARCHAR(20) NOT NULL,
                success TINYINT(1) DEFAULT 0,
                status_code INT NULL,
                error TEXT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_account (account_id),
                INDEX idx_webhook (webhook_id),
                INDEX idx_event (event),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $checked = true;
    }
}
