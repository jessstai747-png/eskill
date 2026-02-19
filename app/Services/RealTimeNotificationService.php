<?php

namespace App\Services;

use App\Database;
use PDO;

class RealTimeNotificationService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get available notification sounds
     */
    public static function getAvailableSounds(): array
    {
        return [
            ['id' => 'order_notification', 'name' => 'Nova Venda', 'file' => '/sounds/order.mp3'],
            ['id' => 'question_notification', 'name' => 'Nova Pergunta', 'file' => '/sounds/question.mp3'],
            ['id' => 'message_notification', 'name' => 'Nova Mensagem', 'file' => '/sounds/message.mp3'],
            ['id' => 'alert_notification', 'name' => 'Alerta do Sistema', 'file' => '/sounds/alert.mp3'],
            ['id' => 'success_notification', 'name' => 'Sucesso', 'file' => '/sounds/success.mp3'],
            ['id' => 'warning_notification', 'name' => 'Aviso', 'file' => '/sounds/warning.mp3'],
            ['id' => 'error_notification', 'name' => 'Erro', 'file' => '/sounds/error.mp3']
        ];
    }

    /**
     * Get or create settings for an account
     */
    public function getSettings(int $accountId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM notification_settings WHERE account_id = ?");
        $stmt->execute([$accountId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings) {
            // Default settings
            return [
                'sound_enabled' => true,
                'sound_volume' => 80,
                'sound_order' => 'order_notification',
                'sound_question' => 'question_notification',
                'sound_message' => 'message_notification',
                'desktop_enabled' => true,
                'polling_interval' => 30,
                'quiet_hours_start' => null,
                'quiet_hours_end' => null,
                'email_orders' => true,
                'email_questions' => true,
                'whatsapp_orders' => false,
                'whatsapp_questions' => false,
                'whatsapp_low_stock' => false
            ];
        }

        // Convert boolean integers to booleans
        $boolFields = ['sound_enabled', 'desktop_enabled', 'email_orders', 'email_questions', 'whatsapp_orders', 'whatsapp_questions', 'whatsapp_low_stock'];
        foreach ($boolFields as $field) {
            if (isset($settings[$field])) {
                $settings[$field] = (bool)$settings[$field];
            }
        }
        
        // Convert integer fields
        $intFields = ['sound_volume', 'polling_interval'];
        foreach ($intFields as $field) {
            if (isset($settings[$field])) {
                $settings[$field] = (int)$settings[$field];
            }
        }

        return $settings;
    }

    /**
     * Save settings for an account
     */
    public function saveSettings(int $accountId, array $settings): bool
    {
        $existing = $this->getSettings($accountId);
        
        // Merge with existing/defaults to ensure all fields are present
        $merged = array_merge($existing, $settings);
        
        // Check if record exists
        $stmt = $this->db->prepare("SELECT id FROM notification_settings WHERE account_id = ?");
        $stmt->execute([$accountId]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $sql = "UPDATE notification_settings SET 
                sound_enabled = :sound_enabled,
                sound_volume = :sound_volume,
                sound_order = :sound_order,
                sound_question = :sound_question,
                sound_message = :sound_message,
                desktop_enabled = :desktop_enabled,
                polling_interval = :polling_interval,
                quiet_hours_start = :quiet_hours_start,
                quiet_hours_end = :quiet_hours_end,
                updated_at = NOW()
                WHERE account_id = :account_id";
        } else {
            $sql = "INSERT INTO notification_settings (
                account_id,
                sound_enabled,
                sound_volume,
                sound_order,
                sound_question,
                sound_message,
                desktop_enabled,
                polling_interval,
                quiet_hours_start,
                quiet_hours_end
            ) VALUES (
                :account_id,
                :sound_enabled,
                :sound_volume,
                :sound_order,
                :sound_question,
                :sound_message,
                :desktop_enabled,
                :polling_interval,
                :quiet_hours_start,
                :quiet_hours_end
            )";
        }

        $params = [
            'account_id' => $accountId,
            'sound_enabled' => $merged['sound_enabled'] ? 1 : 0,
            'sound_volume' => $merged['sound_volume'],
            'sound_order' => $merged['sound_order'],
            'sound_question' => $merged['sound_question'],
            'sound_message' => $merged['sound_message'],
            'desktop_enabled' => $merged['desktop_enabled'] ? 1 : 0,
            'polling_interval' => $merged['polling_interval'],
            'quiet_hours_start' => $merged['quiet_hours_start'] ?: null,
            'quiet_hours_end' => $merged['quiet_hours_end'] ?: null
        ];

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Check if current time is within quiet hours
     */
    public function isQuietHours(int $accountId): bool
    {
        $settings = $this->getSettings($accountId);
        
        if (empty($settings['quiet_hours_start']) || empty($settings['quiet_hours_end'])) {
            return false;
        }

        $now = date('H:i:s');
        $start = $settings['quiet_hours_start'];
        $end = $settings['quiet_hours_end'];

        if ($start <= $end) {
            return $now >= $start && $now <= $end;
        } else {
            // Crosses midnight (e.g. 22:00 to 07:00)
            return $now >= $start || $now <= $end;
        }
    }

    /**
     * Get pending notifications (not yet pushed to client)
     * Uses the data JSON column to check for 'pushed' flag
     */
    public function getPendingNotifications(int $accountId): array
    {
        // JSON_EXTRACT to check if pushed flag is absent or false
        // Note: Logic assumes notifications table maps user_id to accountId
        $sql = "SELECT * FROM notifications 
                WHERE user_id = ? 
                AND (data IS NULL OR JSON_EXTRACT(data, '$.pushed') IS NULL OR JSON_EXTRACT(data, '$.pushed') = false)
                ORDER BY created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$accountId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON data
        foreach ($notifications as &$n) {
            if ($n['data']) {
                $n['data'] = json_decode($n['data'], true);
            } else {
                $n['data'] = [];
            }
        }

        return $notifications;
    }

    /**
     * Mark notifications as pushed to client
     */
    public function markAsPushed(array $ids): void
    {
        if (empty($ids)) return;

        // We need to update the JSON data column to include "pushed": true
        // MySQL 5.7+ supports JSON_SET.
        // Assuming MySQL 5.7+
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        // Use JSON_SET to add/update 'pushed' property, preserving other data
        // If data is null, treat as empty object
        $sql = "UPDATE notifications 
                SET data = JSON_SET(COALESCE(data, '{}'), '$.pushed', true) 
                WHERE id IN ($placeholders)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);
    }

    /**
     * Count unread notifications
     */
    public function countUnread(int $accountId, ?string $type = null): int
    {
        $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND (is_read = 0 OR is_read IS NULL)";
        $params = [$accountId];

        if ($type) {
            $sql .= " AND type = ?";
            $params[] = $type;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get unread notifications for list
     */
    public function getUnreadNotifications(int $accountId, int $limit = 50): array
    {
        $limitSql = max(1, min(200, (int)$limit));
        $sql = "SELECT * FROM notifications 
            WHERE user_id = ? AND (is_read = 0 OR is_read IS NULL) 
            ORDER BY created_at DESC LIMIT {$limitSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $accountId, PDO::PARAM_INT);
        $stmt->execute();
        
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON data
        foreach ($notifications as &$n) {
            if ($n['data']) {
                $n['data'] = json_decode($n['data'], true);
            }
        }

        return $notifications;
    }

    /**
     * Mark a single notification as read
     */
    public function markAsRead(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Mark all notifications as read for an account
     */
    public function markAllAsRead(int $accountId, ?string $type = null): int
    {
        $sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND (is_read = 0 OR is_read IS NULL)";
        $params = [$accountId];

        if ($type) {
            $sql .= " AND type = ?";
            $params[] = $type;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Get stats
     */
    public function getStats(int $accountId): array
    {
        // Example stats
        $total = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
        $total->execute([$accountId]);
        $totalCount = (int)$total->fetchColumn();

        $unread = $this->countUnread($accountId);

        return [
            'total' => $totalCount,
            'unread' => $unread,
            'read' => $totalCount - $unread
        ];
    }
}
