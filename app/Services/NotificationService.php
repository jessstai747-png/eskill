<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Sistema de Alertas e Notificações para Monitoramento
 */
class NotificationService
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function safeLogError(string $message, array $context = []): void
    {
        try {
            if (function_exists('log_error')) {
                \log_error($message, $context);
                return;
            }

            (new StructuredLogService())->error($message, $context);
        } catch (\Throwable $e) {
            error_log('NotificationService safeLogError failed: ' . $e->getMessage());
            error_log($message . ' ' . json_encode($context));
        }
    }

    private function safeLogWarning(string $message, array $context = []): void
    {
        try {
            if (function_exists('log_warning')) {
                \log_warning($message, $context);
                return;
            }

            (new StructuredLogService())->warning($message, $context);
        } catch (\Throwable $e) {
            error_log('NotificationService safeLogWarning failed: ' . $e->getMessage());
            error_log($message . ' ' . json_encode($context));
        }
    }

    /**
     * Envia alerta crítico para todos os canais configurados
     */
    public function sendAlert(string $title, string $message, string $severity = 'HIGH'): array
    {
        $results = [];

        // Telegram (se configurado)
        if ($this->isTelegramConfigured()) {
            $results['telegram'] = $this->sendTelegram($title, $message, $severity);
        }

        // Email (se configurado)
        if ($this->isEmailConfigured()) {
            $results['email'] = $this->sendEmailAlert($title, $message, $severity);
        }

        // Webhook (se configurado)
        if ($this->isWebhookConfigured()) {
            $results['webhook'] = $this->sendWebhook($title, $message, $severity);
        }

        return $results;
    }

    /**
     * Envia notificação via Telegram
     */
    public function sendTelegram(string $title, string $message, string $severity = 'MEDIUM'): bool
    {
        try {
            $telegramConfig = $this->getTelegramConfig();
            if (!$telegramConfig) {
                return false;
            }

            $text = $this->formatTelegramMessage($title, $message, $severity);

            $notificationId = $this->saveNotification('TELEGRAM', $telegramConfig['chat_id'], $title, $message, $severity);

            $url = "https://api.telegram.org/bot{$telegramConfig['bot_token']}/sendMessage";
            $data = [
                'chat_id' => $telegramConfig['chat_id'],
                'text' => $text,
                'parse_mode' => 'HTML'
            ];

            $result = $this->sendHttpRequest($url, $data);

            if ($result['success']) {
                $this->updateNotificationStatus($notificationId, 'SENT');
                return true;
            } else {
                $this->updateNotificationStatus($notificationId, 'FAILED', $result['error']);
                return false;
            }
        } catch (\Exception $e) {
            $this->safeLogError('Erro ao enviar notificação Telegram', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Métodos de configuração e utilitários
     */
    private function isTelegramConfigured(): bool
    {
        return !empty($_ENV['TELEGRAM_BOT_TOKEN']) && !empty($_ENV['TELEGRAM_CHAT_ID']);
    }

    private function isEmailConfigured(): bool
    {
        // Check for basic SMTP config or Mailgun/Sendgrid
        return !empty($_ENV['SMTP_HOST']) || !empty($_ENV['MAILGUN_API_KEY']);
    }

    private function isWebhookConfigured(): bool
    {
        return !empty($_ENV['ALERT_WEBHOOK_URL']);
    }

    private function getTelegramConfig(): ?array
    {
        if (!$this->isTelegramConfigured()) {
            return null;
        }

        return [
            'bot_token' => $_ENV['TELEGRAM_BOT_TOKEN'],
            'chat_id' => $_ENV['TELEGRAM_CHAT_ID']
        ];
    }

    public function sendEmailAlert(string $title, string $message, string $severity = 'MEDIUM'): bool
    {
        if (empty($_ENV['SMTP_HOST'])) {
            // Using PHP mail() as fallback if SMTP not defined but configured to send
            $to = $_ENV['ALERT_EMAIL_RECIPIENT'] ?? 'admin@localhost';
            $subject = "[$severity] $title";
            $headers = "From: no-reply@eskill.com.br\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            return mail($to, $subject, nl2br($message), $headers);
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USERNAME'];
            $mail->Password   = $_ENV['SMTP_PASSWORD'];
            $mail->SMTPSecure = $_ENV['SMTP_ENCRYPTION'] ?? 'tls';
            $mail->Port       = $_ENV['SMTP_PORT'] ?? 587;

            // Recipients
            $mail->setFrom($_ENV['SMTP_FROM_EMAIL'] ?? 'no-reply@eskill.com.br', $_ENV['SMTP_FROM_NAME'] ?? 'Eskill System');
            $recipient = $_ENV['ALERT_EMAIL_RECIPIENT'] ?? 'admin@localhost';
            $mail->addAddress($recipient);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "[$severity] $title";
            $mail->Body    = nl2br($message);
            $mail->AltBody = strip_tags($message);

            $mail->send();

            $this->saveNotification('EMAIL', $recipient, $title, $message, $severity);

            return true;
        } catch (\Exception $e) {
            $this->safeLogError('Falha ao enviar e-mail de alerta', [
                'mailer_error' => $mail->ErrorInfo ?? $e->getMessage(),
            ]);
            return false;
        }
    }

    public function sendWebhook(string $title, string $message, string $severity = 'MEDIUM'): bool
    {
        $url = $_ENV['ALERT_WEBHOOK_URL'] ?? null;
        if (!$url) return false;

        $payload = [
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'timestamp' => date('c')
        ];

        $result = $this->sendHttpRequest($url, $payload);

        $status = $result['success'] ? 'SENT' : 'FAILED';
        $error = $result['error'] ?? null;

        $this->saveNotification('WEBHOOK', $url, $title, $message, $severity);

        return $result['success'];
    }

    /**
     * Salva notificação no banco de logs
     */
    private function saveNotification(string $type, string $recipient, string $title, string $message, string $severity): int
    {
        // Usa a nova tabela notification_logs
        // Mapeia colunas: type, recipient, subject (title), metadata (message + severity), status
        $stmt = $this->db->prepare("
            INSERT INTO notification_logs (type, recipient, subject, metadata, status)
            VALUES (:type, :recipient, :title, :metadata, 'PENDING')
        ");

        $metadata = json_encode([
            'message' => $message,
            'severity' => $severity,
        ]);

        try {
            $stmt->execute([
                'type' => $type,
                'recipient' => $recipient,
                'title' => $title,
                'metadata' => $metadata
            ]);
        } catch (\Exception $e) {
            $this->safeLogWarning('Falha ao salvar notificação no banco', [
                'type' => $type,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);
            // Não relançar erro para não parar o fluxo crítico, apenas logar
            return 0;
        }

        return (int)$this->db->lastInsertId();
    }

    /**
     * Atualiza status da notificação
     */
    private function updateNotificationStatus(int $id, string $status, ?string $error = null): void
    {
        if ($id <= 0) return;

        $sql = "UPDATE notification_logs SET status = ?, error_message = ?";
        // Tabela nova não tem attempts ou updated_at explicito no migration fornecido, 
        // mas vamos assumir que só status e error importam agora para compatibilidade.

        $params = [$status, $error];
        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Formata mensagem para Telegram
     */
    private function formatTelegramMessage(string $title, string $message, string $severity): string
    {
        $emoji = $this->getSeverityEmoji($severity);
        $time = date('d/m/Y H:i:s');

        return "<b>{$emoji} {$title}</b>\n\n{$message}\n\n<i>🕒 {$time}</i>";
    }

    /**
     * Obtém emoji da severidade
     */
    private function getSeverityEmoji(string $severity): string
    {
        return match ($severity) {
            'CRITICAL' => '🚨',
            'HIGH' => '⚠️',
            'MEDIUM' => 'ℹ️',
            'LOW' => '📝',
            default => 'ℹ️'
        };
    }

    /**
     * Envia requisição HTTP
     */
    private function sendHttpRequest(string $url, array $data, array $headers = []): array
    {
        try {
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
                // Security: enable SSL verification in production (C3)
                CURLOPT_SSL_VERIFYPEER => ($_ENV['APP_ENV'] ?? 'production') === 'production',
                CURLOPT_SSL_VERIFYHOST => ($_ENV['APP_ENV'] ?? 'production') === 'production' ? 2 : 0
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            curl_close($ch);

            if ($error) {
                return ['success' => false, 'error' => $error];
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'response' => $response];
            } else {
                return ['success' => false, 'error' => "HTTP {$httpCode}: {$response}"];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // === MÉTODOS LEGADOS PARA COMPATIBILIDADE ===

    /**
     * Criar nova notificação (método legado)
     */
    public function create(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?array $data = null,
        bool $sendEmail = true,
        bool $sendWhatsApp = false
    ): int {
        // Inserir na tabela legada
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (user_id, type, title, message, data, is_email_sent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $dataJson = $data ? json_encode($data) : null;
            $stmt->execute([$userId, $type, $title, $message, $dataJson, false]);

            return (int)$this->db->lastInsertId();
        } catch (\Exception $e) {
            $this->safeLogError('Erro ao criar notificação', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Marca notificação como lida - Implementação real
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE id = :id AND user_id = :user_id
            ");
            $result = $stmt->execute([
                'id' => $notificationId,
                'user_id' => $userId
            ]);
            return $result && $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            $this->safeLogWarning('Erro ao marcar notificação como lida', [
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Marca todas as notificações do usuário como lidas
     */
    public function markAllAsRead(int $userId): int
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE user_id = :user_id AND is_read = 0
            ");
            $stmt->execute(['user_id' => $userId]);
            return $stmt->rowCount();
        } catch (\Exception $e) {
            $this->safeLogWarning('Erro ao marcar todas notificações como lidas', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Obtém notificações do usuário - Implementação real
     */
    public function getUserNotifications(int $userId, int $limit = 50, int $offset = 0): array
    {
        try {
            // Garantir que a tabela existe
            $this->ensureNotificationsTable();

            $limitSql = max(1, min((int)$limit, 200));
            $offsetSql = max(0, (int)$offset);

            $stmt = $this->db->prepare("
                SELECT
                    id,
                    type,
                    title,
                    message,
                    data,
                    is_read,
                    read_at,
                    created_at,
                    CASE
                        WHEN type IN ('order_new', 'order_update') THEN 'order'
                        WHEN type IN ('stock_low', 'stock_out') THEN 'stock'
                        WHEN type IN ('question_new', 'question_response') THEN 'question'
                        WHEN type IN ('claim_new', 'claim_update') THEN 'claim'
                        WHEN type IN ('promotion_active', 'promotion_ending') THEN 'promotion'
                        WHEN type IN ('alert_critical', 'alert_warning') THEN 'alert'
                        ELSE 'general'
                    END as category
                FROM notifications
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT {$limitSql} OFFSET {$offsetSql}
            ");

            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decodificar data JSON
            foreach ($notifications as &$notification) {
                if (!empty($notification['data'])) {
                    $notification['data'] = json_decode($notification['data'], true);
                }
                $notification['is_read'] = (bool)$notification['is_read'];
                $notification['time_ago'] = $this->timeAgo($notification['created_at']);
            }

            return $notifications;
        } catch (\Exception $e) {
            $this->safeLogError('Erro ao buscar notificações do usuário', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Conta notificações não lidas do usuário
     */
    public function getUnreadCount(int $userId): int
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM notifications
                WHERE user_id = :user_id AND is_read = 0
            ");
            $stmt->execute(['user_id' => $userId]);
            return (int)$stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Obtém resumo de notificações por categoria
     */
    public function getNotificationSummary(int $userId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    CASE
                        WHEN type IN ('order_new', 'order_update') THEN 'orders'
                        WHEN type IN ('stock_low', 'stock_out') THEN 'stock'
                        WHEN type IN ('question_new', 'question_response') THEN 'questions'
                        WHEN type IN ('claim_new', 'claim_update') THEN 'claims'
                        WHEN type IN ('alert_critical', 'alert_warning') THEN 'alerts'
                        ELSE 'other'
                    END as category,
                    COUNT(*) as total,
                    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread
                FROM notifications
                WHERE user_id = :user_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY category
            ");
            $stmt->execute(['user_id' => $userId]);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $summary = [
                'orders' => ['total' => 0, 'unread' => 0],
                'stock' => ['total' => 0, 'unread' => 0],
                'questions' => ['total' => 0, 'unread' => 0],
                'claims' => ['total' => 0, 'unread' => 0],
                'alerts' => ['total' => 0, 'unread' => 0],
                'other' => ['total' => 0, 'unread' => 0],
            ];

            foreach ($results as $row) {
                $summary[$row['category']] = [
                    'total' => (int)$row['total'],
                    'unread' => (int)$row['unread']
                ];
            }

            $summary['total_unread'] = array_sum(array_column($summary, 'unread'));

            return $summary;
        } catch (\Exception $e) {
            return ['total_unread' => 0];
        }
    }

    /**
     * Deleta notificações antigas (mais de 30 dias e lidas)
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM notifications
                WHERE is_read = 1
                AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute(['days' => $daysOld]);
            return $stmt->rowCount();
        } catch (\Exception $e) {
            $this->safeLogWarning('Erro ao limpar notificações antigas', [
                'days_old' => $daysOld,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Garante que a tabela de notificações existe e tem o schema correto
     */
    private function ensureNotificationsTable(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS notifications (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    type VARCHAR(50) NOT NULL DEFAULT 'general',
                    title VARCHAR(255) NOT NULL,
                    message TEXT,
                    data JSON,
                    is_read TINYINT(1) DEFAULT 0,
                    is_email_sent TINYINT(1) DEFAULT 0,
                    read_at DATETIME NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_read (user_id, is_read),
                    INDEX idx_user_created (user_id, created_at),
                    INDEX idx_type (type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Fix schema drift: if 'type' column is ENUM or too small, widen to VARCHAR(50)
            $stmt = $this->db->query("SHOW COLUMNS FROM notifications LIKE 'type'");
            $col = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($col && stripos($col['Type'], 'varchar') === false) {
                $this->db->exec("
                    ALTER TABLE notifications MODIFY COLUMN type VARCHAR(50) NOT NULL DEFAULT 'general'
                ");
            }

            // Ensure required columns exist (schema may have been created by migrate_notifications.php)
            $requiredColumns = ['title', 'message', 'is_read', 'is_email_sent', 'read_at'];
            $existing = $this->db->query("SHOW COLUMNS FROM notifications");
            $existingNames = array_column($existing->fetchAll(\PDO::FETCH_ASSOC), 'Field');
            $alterStatements = [
                'title' => "ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT '' AFTER type",
                'message' => "ADD COLUMN message TEXT AFTER title",
                'is_read' => "ADD COLUMN is_read TINYINT(1) DEFAULT 0 AFTER data",
                'is_email_sent' => "ADD COLUMN is_email_sent TINYINT(1) DEFAULT 0 AFTER is_read",
                'read_at' => "ADD COLUMN read_at DATETIME NULL AFTER is_email_sent",
            ];
            foreach ($requiredColumns as $colName) {
                if (!in_array($colName, $existingNames, true) && isset($alterStatements[$colName])) {
                    $this->db->exec("ALTER TABLE notifications " . $alterStatements[$colName]);
                }
            }
        } catch (\Exception $e) {
            // Log but don't crash — table may be locked or permissions issue
            try {
                log_warning('NotificationService: ensureNotificationsTable failed', [
                    'error' => $e->getMessage(),
                ]);
            } catch (\Exception $inner) {
                // Avoid recursion
            }
        }
    }

    /**
     * Formata tempo relativo
     */
    private function timeAgo(string $datetime): string
    {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 60) return 'agora mesmo';
        if ($diff < 3600) return floor($diff / 60) . ' min atrás';
        if ($diff < 86400) return floor($diff / 3600) . 'h atrás';
        if ($diff < 604800) return floor($diff / 86400) . ' dias atrás';

        return date('d/m/Y', $time);
    }

    // === MÉTODOS DE PREFERÊNCIAS ===

    public function getNotificationPreferences(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prefs) {
            // Default preferences
            return [
                'email_alerts' => 1,
                'whatsapp_alerts' => 0,
                'sms_alerts' => 0,
                'alert_priority_threshold' => 'medium',
                'weekly_report' => 1,
                'monthly_report' => 1
            ];
        }

        return $prefs;
    }

    public function updateNotificationPreferences(int $userId, array $prefs): bool
    {
        // Simple UPSERT
        $fields = [
            'email_alerts',
            'whatsapp_alerts',
            'sms_alerts',
            'alert_priority_threshold',
            'quiet_hours_start',
            'quiet_hours_end',
            'daily_report',
            'weekly_report',
            'monthly_report'
        ];

        $updates = [];
        $params = [];

        foreach ($fields as $field) {
            if (isset($prefs[$field])) {
                $updates[] = "$field = ?";
                $params[] = $prefs[$field];
            }
        }

        if (empty($updates)) return false;

        // Check exists
        $stmt = $this->db->prepare("SELECT id FROM notification_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $exists = $stmt->fetch();

        if ($exists) {
            $sql = "UPDATE notification_preferences SET " . implode(', ', $updates) . " WHERE user_id = ?";
            $params[] = $userId;
        } else {
            // Build Insert
            // Simplifying for now: create default row then update, or full insert logic.
            // Let's do simple Insert Ignore then Update for robustness
            $this->db->prepare("INSERT IGNORE INTO notification_preferences (user_id) VALUES (?)")->execute([$userId]);
            $sql = "UPDATE notification_preferences SET " . implode(', ', $updates) . " WHERE user_id = ?";
            $params[] = $userId;
        }

        return $this->db->prepare($sql)->execute($params);
    }
}
