<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * CloneSlackDiscordNotificationService
 *
 * Serviço para envio de notificações para Slack e Discord
 * sobre eventos de clonagem (jobs stuck, failures, completions, etc.)
 *
 * @version 1.0.0
 */
class CloneSlackDiscordNotificationService
{
    private PDO $db;
    private int $accountId;

    // Tipos de alerta suportados
    public const ALERT_JOB_STARTED = 'job_started';
    public const ALERT_JOB_COMPLETED = 'job_completed';
    public const ALERT_JOB_FAILED = 'job_failed';
    public const ALERT_JOB_STUCK = 'job_stuck';
    public const ALERT_HIGH_FAILURE_RATE = 'high_failure_rate';
    public const ALERT_RATE_LIMIT = 'rate_limit';
    public const ALERT_DAILY_SUMMARY = 'daily_summary';
    public const ALERT_MILESTONE = 'milestone';

    // Severidades
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_SUCCESS = 'success';

    // Cores para Slack/Discord por severidade
    private const COLORS = [
        self::SEVERITY_INFO => '#17a2b8',
        self::SEVERITY_WARNING => '#ffc107',
        self::SEVERITY_ERROR => '#dc3545',
        self::SEVERITY_CRITICAL => '#6f42c1',
        self::SEVERITY_SUCCESS => '#28a745',
    ];

    // Emojis por tipo de alerta
    private const EMOJIS = [
        self::ALERT_JOB_STARTED => '🚀',
        self::ALERT_JOB_COMPLETED => '✅',
        self::ALERT_JOB_FAILED => '❌',
        self::ALERT_JOB_STUCK => '⚠️',
        self::ALERT_HIGH_FAILURE_RATE => '📉',
        self::ALERT_RATE_LIMIT => '🚦',
        self::ALERT_DAILY_SUMMARY => '📊',
        self::ALERT_MILESTONE => '🎉',
    ];

    public function __construct(int $accountId = 0)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * Envia notificação para Slack
     */
    public function sendToSlack(
        string $alertType,
        string $title,
        string $message,
        array $fields = [],
        string $severity = self::SEVERITY_INFO,
        ?string $webhookUrl = null
    ): array {
        $webhookUrl = $webhookUrl ?? $this->getSlackWebhook();

        if (!$webhookUrl) {
            return [
                'success' => false,
                'error' => 'Slack webhook URL not configured',
            ];
        }

        $emoji = self::EMOJIS[$alertType] ?? '📢';
        $color = self::COLORS[$severity] ?? self::COLORS[self::SEVERITY_INFO];

        // Montar payload Slack
        $payload = [
            'username' => 'Clone Bot',
            'icon_emoji' => ':robot_face:',
            'attachments' => [
                [
                    'color' => $color,
                    'title' => "{$emoji} {$title}",
                    'text' => $message,
                    'fields' => $this->formatSlackFields($fields),
                    'footer' => 'eskill.com.br Clone System',
                    'footer_icon' => 'https://eskill.com.br/assets/img/logo-icon.png',
                    'ts' => time(),
                ],
            ],
        ];

        // Adicionar menções para alertas críticos
        if ($severity === self::SEVERITY_CRITICAL) {
            $payload['text'] = '<!channel> Alerta Crítico!';
        } elseif ($severity === self::SEVERITY_ERROR) {
            $payload['text'] = '<!here> Atenção necessária';
        }

        $result = $this->sendWebhook($webhookUrl, $payload);

        // Log da notificação
        $this->logNotification('slack', $alertType, $severity, $result['success']);

        return $result;
    }

    /**
     * Envia notificação para Discord
     */
    public function sendToDiscord(
        string $alertType,
        string $title,
        string $message,
        array $fields = [],
        string $severity = self::SEVERITY_INFO,
        ?string $webhookUrl = null
    ): array {
        $webhookUrl = $webhookUrl ?? $this->getDiscordWebhook();

        if (!$webhookUrl) {
            return [
                'success' => false,
                'error' => 'Discord webhook URL not configured',
            ];
        }

        $emoji = self::EMOJIS[$alertType] ?? '📢';
        $color = hexdec(ltrim(self::COLORS[$severity] ?? self::COLORS[self::SEVERITY_INFO], '#'));

        // Montar payload Discord (embed)
        $payload = [
            'username' => 'Clone Bot',
            'avatar_url' => 'https://eskill.com.br/assets/img/logo-icon.png',
            'embeds' => [
                [
                    'title' => "{$emoji} {$title}",
                    'description' => $message,
                    'color' => $color,
                    'fields' => $this->formatDiscordFields($fields),
                    'footer' => [
                        'text' => 'eskill.com.br Clone System',
                        'icon_url' => 'https://eskill.com.br/assets/img/logo-icon.png',
                    ],
                    'timestamp' => date('c'),
                ],
            ],
        ];

        // Adicionar menções para alertas críticos
        if ($severity === self::SEVERITY_CRITICAL) {
            $payload['content'] = '@everyone Alerta Crítico!';
        } elseif ($severity === self::SEVERITY_ERROR) {
            $payload['content'] = '@here Atenção necessária';
        }

        $result = $this->sendWebhook($webhookUrl, $payload);

        // Log da notificação
        $this->logNotification('discord', $alertType, $severity, $result['success']);

        return $result;
    }

    /**
     * Envia para todos os canais configurados
     */
    public function broadcast(
        string $alertType,
        string $title,
        string $message,
        array $fields = [],
        string $severity = self::SEVERITY_INFO
    ): array {
        $results = [
            'slack' => null,
            'discord' => null,
        ];

        // Verificar se deve enviar para Slack
        if ($this->isSlackEnabled()) {
            $results['slack'] = $this->sendToSlack($alertType, $title, $message, $fields, $severity);
        }

        // Verificar se deve enviar para Discord
        if ($this->isDiscordEnabled()) {
            $results['discord'] = $this->sendToDiscord($alertType, $title, $message, $fields, $severity);
        }

        return [
            'success' => ($results['slack']['success'] ?? false) || ($results['discord']['success'] ?? false),
            'results' => $results,
        ];
    }

    // =========================================================================
    // Métodos de Alerta Específicos
    // =========================================================================

    /**
     * Notifica início de job
     */
    public function notifyJobStarted(int $jobId, string $jobName, int $totalItems, string $sourceType): array
    {
        return $this->broadcast(
            self::ALERT_JOB_STARTED,
            'Job de Clonagem Iniciado',
            "Job **{$jobName}** (#{$jobId}) foi iniciado",
            [
                ['name' => 'Total de Itens', 'value' => number_format($totalItems), 'inline' => true],
                ['name' => 'Origem', 'value' => ucfirst($sourceType), 'inline' => true],
                ['name' => 'Conta', 'value' => $this->getAccountName(), 'inline' => true],
            ],
            self::SEVERITY_INFO
        );
    }

    /**
     * Notifica conclusão de job
     */
    public function notifyJobCompleted(
        int $jobId,
        string $jobName,
        int $successCount,
        int $failedCount,
        int $durationSeconds
    ): array {
        $totalItems = $successCount + $failedCount;
        $successRate = $totalItems > 0 ? round(($successCount / $totalItems) * 100, 1) : 0;
        $duration = $this->formatDuration($durationSeconds);

        $severity = $failedCount === 0 ? self::SEVERITY_SUCCESS : ($successRate < 80 ? self::SEVERITY_WARNING : self::SEVERITY_SUCCESS);

        return $this->broadcast(
            self::ALERT_JOB_COMPLETED,
            'Job de Clonagem Concluído',
            "Job **{$jobName}** (#{$jobId}) foi concluído com sucesso!",
            [
                ['name' => '✅ Sucesso', 'value' => number_format($successCount), 'inline' => true],
                ['name' => '❌ Falhas', 'value' => number_format($failedCount), 'inline' => true],
                ['name' => '📊 Taxa', 'value' => "{$successRate}%", 'inline' => true],
                ['name' => '⏱️ Duração', 'value' => $duration, 'inline' => true],
                ['name' => '📈 Velocidade', 'value' => $this->calculateSpeed($totalItems, $durationSeconds), 'inline' => true],
            ],
            $severity
        );
    }

    /**
     * Notifica falha de job
     */
    public function notifyJobFailed(int $jobId, string $jobName, string $errorMessage, int $processedItems): array
    {
        return $this->broadcast(
            self::ALERT_JOB_FAILED,
            'Job de Clonagem Falhou',
            "Job **{$jobName}** (#{$jobId}) falhou durante execução",
            [
                ['name' => 'Erro', 'value' => mb_substr($errorMessage, 0, 200), 'inline' => false],
                ['name' => 'Itens Processados', 'value' => number_format($processedItems), 'inline' => true],
                ['name' => 'Ação Recomendada', 'value' => 'Verificar logs e reprocessar', 'inline' => false],
            ],
            self::SEVERITY_ERROR
        );
    }

    /**
     * Notifica job travado
     */
    public function notifyJobStuck(int $jobId, string $jobName, int $minutesStuck, int $currentProgress): array
    {
        $severity = $minutesStuck > 60 ? self::SEVERITY_CRITICAL : self::SEVERITY_WARNING;

        return $this->broadcast(
            self::ALERT_JOB_STUCK,
            'Job de Clonagem Travado',
            "Job **{$jobName}** (#{$jobId}) está sem progresso há {$minutesStuck} minutos",
            [
                ['name' => 'Progresso Atual', 'value' => "{$currentProgress}%", 'inline' => true],
                ['name' => 'Tempo Parado', 'value' => "{$minutesStuck} min", 'inline' => true],
                ['name' => 'Ação Recomendada', 'value' => 'Verificar worker e conexão com API', 'inline' => false],
            ],
            $severity
        );
    }

    /**
     * Notifica taxa de falha alta
     */
    public function notifyHighFailureRate(float $failureRate, int $failedCount, int $totalCount, string $period): array
    {
        $severity = $failureRate > 50 ? self::SEVERITY_CRITICAL : self::SEVERITY_WARNING;

        return $this->broadcast(
            self::ALERT_HIGH_FAILURE_RATE,
            'Taxa de Falha Alta Detectada',
            "A taxa de falha nas últimas {$period} está em **{$failureRate}%**",
            [
                ['name' => 'Itens Falhados', 'value' => number_format($failedCount), 'inline' => true],
                ['name' => 'Total de Itens', 'value' => number_format($totalCount), 'inline' => true],
                ['name' => 'Período', 'value' => $period, 'inline' => true],
                ['name' => 'Ação Recomendada', 'value' => 'Analisar erros mais comuns e ajustar configurações', 'inline' => false],
            ],
            $severity
        );
    }

    /**
     * Notifica rate limit da API
     */
    public function notifyRateLimit(int $retryAfterSeconds, string $endpoint): array
    {
        return $this->broadcast(
            self::ALERT_RATE_LIMIT,
            'Rate Limit da API Atingido',
            "O limite de requisições da API do Mercado Livre foi atingido",
            [
                ['name' => 'Endpoint', 'value' => $endpoint, 'inline' => true],
                ['name' => 'Retry After', 'value' => "{$retryAfterSeconds}s", 'inline' => true],
                ['name' => 'Status', 'value' => 'Jobs pausados temporariamente', 'inline' => false],
            ],
            self::SEVERITY_WARNING
        );
    }

    /**
     * Envia resumo diário
     */
    public function sendDailySummary(array $metrics): array
    {
        $successRate = $metrics['total_items'] > 0
            ? round(($metrics['successful_items'] / $metrics['total_items']) * 100, 1)
            : 0;

        $severity = $successRate >= 90 ? self::SEVERITY_SUCCESS : ($successRate >= 70 ? self::SEVERITY_INFO : self::SEVERITY_WARNING);

        return $this->broadcast(
            self::ALERT_DAILY_SUMMARY,
            'Resumo Diário de Clonagem',
            "Relatório de atividades das últimas 24 horas",
            [
                ['name' => '📋 Total de Jobs', 'value' => number_format($metrics['total_jobs']), 'inline' => true],
                ['name' => '📦 Itens Clonados', 'value' => number_format($metrics['successful_items']), 'inline' => true],
                ['name' => '❌ Falhas', 'value' => number_format($metrics['failed_items']), 'inline' => true],
                ['name' => '📊 Taxa de Sucesso', 'value' => "{$successRate}%", 'inline' => true],
                ['name' => '⏱️ Tempo Total', 'value' => $this->formatDuration($metrics['total_duration'] ?? 0), 'inline' => true],
                ['name' => '🏆 Melhor Template', 'value' => $metrics['top_template'] ?? 'N/A', 'inline' => true],
            ],
            $severity
        );
    }

    /**
     * Notifica marco alcançado
     */
    public function notifyMilestone(string $milestoneType, int $value, string $description): array
    {
        return $this->broadcast(
            self::ALERT_MILESTONE,
            'Marco Alcançado!',
            $description,
            [
                ['name' => 'Tipo', 'value' => $milestoneType, 'inline' => true],
                ['name' => 'Valor', 'value' => number_format($value), 'inline' => true],
            ],
            self::SEVERITY_SUCCESS
        );
    }

    // =========================================================================
    // Configuração de Webhooks
    // =========================================================================

    /**
     * Salva configuração de webhook
     */
    public function saveWebhookConfig(string $platform, string $webhookUrl, array $settings = []): array
    {
        // Validar URL
        if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'URL de webhook inválida'];
        }

        // Validar plataforma
        if (!in_array($platform, ['slack', 'discord'])) {
            return ['success' => false, 'error' => 'Plataforma inválida'];
        }

        // Testar webhook
        $testResult = $this->testWebhook($platform, $webhookUrl);
        if (!$testResult['success']) {
            return ['success' => false, 'error' => 'Webhook não respondeu corretamente: ' . ($testResult['error'] ?? 'Unknown error')];
        }

        $configKey = "{$platform}_webhook";
        $config = [
            'url' => $webhookUrl,
            'enabled' => $settings['enabled'] ?? true,
            'alert_types' => $settings['alert_types'] ?? array_keys(self::EMOJIS),
            'min_severity' => $settings['min_severity'] ?? self::SEVERITY_INFO,
            'quiet_hours' => $settings['quiet_hours'] ?? null, // ['start' => '22:00', 'end' => '08:00']
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Salvar em configuração da conta
        $sql = "INSERT INTO account_settings (account_id, setting_key, setting_value, updated_at)
                VALUES (:account_id, :key, :value, NOW())
                ON DUPLICATE KEY UPDATE setting_value = :value2, updated_at = NOW()";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'account_id' => $this->accountId,
            'key' => $configKey,
            'value' => json_encode($config),
            'value2' => json_encode($config),
        ]);

        return [
            'success' => true,
            'message' => "Webhook {$platform} configurado com sucesso",
            'test_result' => $testResult,
        ];
    }

    /**
     * Obtém configuração de webhook
     */
    public function getWebhookConfig(string $platform): ?array
    {
        $configKey = "{$platform}_webhook";

        $sql = "SELECT setting_value FROM account_settings
                WHERE account_id = :account_id AND setting_key = :key";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'account_id' => $this->accountId,
            'key' => $configKey,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return json_decode($row['setting_value'], true);
    }

    /**
     * Testa webhook
     */
    public function testWebhook(string $platform, string $webhookUrl): array
    {
        $testPayload = $platform === 'slack'
            ? $this->buildSlackTestPayload()
            : $this->buildDiscordTestPayload();

        return $this->sendWebhook($webhookUrl, $testPayload);
    }

    /**
     * Lista configurações de webhooks
     */
    public function listWebhookConfigs(): array
    {
        $configs = [
            'slack' => $this->getWebhookConfig('slack'),
            'discord' => $this->getWebhookConfig('discord'),
        ];

        // Ocultar URL completa por segurança
        foreach ($configs as $platform => &$config) {
            if ($config && isset($config['url'])) {
                $config['url_masked'] = $this->maskWebhookUrl($config['url']);
            }
        }

        return $configs;
    }

    /**
     * Desabilita webhook
     */
    public function disableWebhook(string $platform): array
    {
        $config = $this->getWebhookConfig($platform);

        if (!$config) {
            return ['success' => false, 'error' => 'Webhook não configurado'];
        }

        $config['enabled'] = false;
        $config['updated_at'] = date('Y-m-d H:i:s');

        $sql = "UPDATE account_settings SET setting_value = :value, updated_at = NOW()
                WHERE account_id = :account_id AND setting_key = :key";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'account_id' => $this->accountId,
            'key' => "{$platform}_webhook",
            'value' => json_encode($config),
        ]);

        return ['success' => true, 'message' => "Webhook {$platform} desabilitado"];
    }

    // =========================================================================
    // Histórico de Notificações
    // =========================================================================

    /**
     * Obtém histórico de notificações
     */
    public function getNotificationHistory(int $limit = 50, array $filters = []): array
    {
        $limitSql = max(1, min(500, (int)$limit));
        $sql = "SELECT * FROM clone_notification_logs
                WHERE account_id = :account_id";
        $params = ['account_id' => $this->accountId];

        if (!empty($filters['platform'])) {
            $sql .= " AND platform = :platform";
            $params['platform'] = $filters['platform'];
        }

        if (!empty($filters['alert_type'])) {
            $sql .= " AND alert_type = :alert_type";
            $params['alert_type'] = $filters['alert_type'];
        }

        if (!empty($filters['success'])) {
            $sql .= " AND success = :success";
            $params['success'] = $filters['success'] === 'true' ? 1 : 0;
        }

        $sql .= " ORDER BY created_at DESC LIMIT {$limitSql}";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém estatísticas de notificações
     */
    public function getNotificationStats(string $period = '7d'): array
    {
        $days = match ($period) {
            '24h' => 1,
            '7d' => 7,
            '30d' => 30,
            default => 7,
        };

        $sql = "SELECT
                    platform,
                    alert_type,
                    COUNT(*) as total,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed
                FROM clone_notification_logs
                WHERE account_id = :account_id
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY platform, alert_type
                ORDER BY total DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'account_id' => $this->accountId,
            'days' => $days,
        ]);

        $byPlatformType = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Totais
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed
                FROM clone_notification_logs
                WHERE account_id = :account_id
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'account_id' => $this->accountId,
            'days' => $days,
        ]);

        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'period' => $period,
            'totals' => $totals,
            'by_platform_type' => $byPlatformType,
        ];
    }

    // =========================================================================
    // Métodos Privados
    // =========================================================================

    /**
     * Envia request para webhook
     */
    private function sendWebhook(string $url, array $payload): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => $error,
                'http_code' => 0,
            ];
        }

        // Slack retorna "ok", Discord retorna 204 No Content
        $success = ($httpCode >= 200 && $httpCode < 300) || $response === 'ok';

        return [
            'success' => $success,
            'http_code' => $httpCode,
            'response' => $response,
        ];
    }

    /**
     * Formata campos para Slack
     */
    private function formatSlackFields(array $fields): array
    {
        return array_map(function ($field) {
            return [
                'title' => $field['name'],
                'value' => $field['value'],
                'short' => $field['inline'] ?? false,
            ];
        }, $fields);
    }

    /**
     * Formata campos para Discord
     */
    private function formatDiscordFields(array $fields): array
    {
        return array_map(function ($field) {
            return [
                'name' => $field['name'],
                'value' => (string) $field['value'],
                'inline' => $field['inline'] ?? false,
            ];
        }, $fields);
    }

    /**
     * Obtém URL do webhook Slack
     */
    private function getSlackWebhook(): ?string
    {
        $config = $this->getWebhookConfig('slack');

        if (!$config || !($config['enabled'] ?? false)) {
            return null;
        }

        // Verificar quiet hours
        if (!$this->isWithinActiveHours($config['quiet_hours'] ?? null)) {
            return null;
        }

        return $config['url'] ?? null;
    }

    /**
     * Obtém URL do webhook Discord
     */
    private function getDiscordWebhook(): ?string
    {
        $config = $this->getWebhookConfig('discord');

        if (!$config || !($config['enabled'] ?? false)) {
            return null;
        }

        // Verificar quiet hours
        if (!$this->isWithinActiveHours($config['quiet_hours'] ?? null)) {
            return null;
        }

        return $config['url'] ?? null;
    }

    /**
     * Verifica se Slack está habilitado
     */
    private function isSlackEnabled(): bool
    {
        return $this->getSlackWebhook() !== null;
    }

    /**
     * Verifica se Discord está habilitado
     */
    private function isDiscordEnabled(): bool
    {
        return $this->getDiscordWebhook() !== null;
    }

    /**
     * Verifica se está dentro do horário ativo
     */
    private function isWithinActiveHours(?array $quietHours): bool
    {
        if (!$quietHours || !isset($quietHours['start'], $quietHours['end'])) {
            return true;
        }

        $now = new \DateTime();
        $start = \DateTime::createFromFormat('H:i', $quietHours['start']);
        $end = \DateTime::createFromFormat('H:i', $quietHours['end']);

        if (!$start || !$end) {
            return true;
        }

        // Se start > end, significa que quiet hours cruza meia-noite
        if ($start > $end) {
            return $now < $start && $now > $end;
        }

        return $now < $start || $now > $end;
    }

    /**
     * Log de notificação
     */
    private function logNotification(string $platform, string $alertType, string $severity, bool $success): void
    {
        $sql = "INSERT INTO clone_notification_logs
                (account_id, platform, alert_type, severity, success, created_at)
                VALUES (:account_id, :platform, :alert_type, :severity, :success, NOW())";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'account_id' => $this->accountId,
                'platform' => $platform,
                'alert_type' => $alertType,
                'severity' => $severity,
                'success' => $success ? 1 : 0,
            ]);
        } catch (\PDOException $e) {
            // Silently fail - não queremos que log falhe a notificação
            log_warning('Falha ao registrar log de notificação Slack/Discord', [
                'platform' => $platform,
                'alert_type' => $alertType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtém nome da conta
     */
    private function getAccountName(): string
    {
        $sql = "SELECT nickname FROM contas WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $this->accountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['nickname'] ?? "Conta #{$this->accountId}";
    }

    /**
     * Formata duração
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;

        if ($minutes < 60) {
            return "{$minutes}m {$secs}s";
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return "{$hours}h {$mins}m";
    }

    /**
     * Calcula velocidade de processamento
     */
    private function calculateSpeed(int $items, int $seconds): string
    {
        if ($seconds <= 0) {
            return 'N/A';
        }

        $itemsPerMinute = round(($items / $seconds) * 60, 1);

        return "{$itemsPerMinute} items/min";
    }

    /**
     * Mascara URL do webhook
     */
    private function maskWebhookUrl(string $url): string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';

        if (strlen($path) > 20) {
            $path = substr($path, 0, 10) . '...' . substr($path, -6);
        }

        return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'unknown') . $path;
    }

    /**
     * Payload de teste para Slack
     */
    private function buildSlackTestPayload(): array
    {
        return [
            'username' => 'Clone Bot',
            'icon_emoji' => ':robot_face:',
            'text' => '🧪 Teste de conexão do Clone Bot - eskill.com.br',
            'attachments' => [
                [
                    'color' => self::COLORS[self::SEVERITY_INFO],
                    'text' => 'Este é um teste de configuração. Se você está vendo esta mensagem, o webhook está funcionando corretamente!',
                    'footer' => 'eskill.com.br Clone System',
                    'ts' => time(),
                ],
            ],
        ];
    }

    /**
     * Payload de teste para Discord
     */
    private function buildDiscordTestPayload(): array
    {
        return [
            'username' => 'Clone Bot',
            'embeds' => [
                [
                    'title' => '🧪 Teste de Conexão',
                    'description' => 'Este é um teste de configuração. Se você está vendo esta mensagem, o webhook está funcionando corretamente!',
                    'color' => hexdec(ltrim(self::COLORS[self::SEVERITY_INFO], '#')),
                    'footer' => [
                        'text' => 'eskill.com.br Clone System',
                    ],
                    'timestamp' => date('c'),
                ],
            ],
        ];
    }
}
