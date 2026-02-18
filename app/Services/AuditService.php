<?php

namespace App\Services;

use App\Database;
use PDO;

class AuditService
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTable();
    }

    /**
     * Log an action
     */
    public function log(int $userId, string $action, string $resource, string $details = '', ?array $oldValue = null, ?array $newValue = null): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (
                    user_id, action, resource, details, old_value, new_value, ip_address, created_at
                ) VALUES (
                    :user_id, :action, :resource, :details, :old_value, :new_value, :ip, NOW()
                )
            ");

            $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';

            return $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'resource' => $resource,
                'details' => $details,
                'old_value' => $oldValue ? json_encode($oldValue) : null,
                'new_value' => $newValue ? json_encode($newValue) : null,
                'ip' => $ip
            ]);
        } catch (\Exception $e) {
            log_error('Audit log failed', ['service' => 'AuditService', 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get recent logs
     */
    public function getLogs(int $limit = 50): array
    {
        // Join with users if table exists, otherwise just show ID
        // Assuming users table has 'name'
        $limitSql = max(1, min((int)$limit, 500));

        $sql = "
            SELECT l.*, u.name as user_name, u.email as user_email
            FROM audit_logs l
            LEFT JOIN users u ON l.user_id = u.id
            ORDER BY l.created_at DESC
            LIMIT {$limitSql}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function ensureTable(): void
    {
        // Force schema update by checking if columns exist, or for dev just drop/create if empty
        // Since we are in dev/implementation phase, let's ensure the table is correct.

        try {
            // Check if table exists and has 'details' column to avoid dropping valid data later
            // For now, simpler to attempt create. If insert fails, we might need to alter.
            // Let's just try to ADD the columns that might be missing from legacy versions

            $this->db->exec("
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    action VARCHAR(50) NOT NULL,
                    resource VARCHAR(100) NOT NULL,
                    details TEXT,
                    old_value JSON,
                    new_value JSON,
                    ip_address VARCHAR(45),
                    created_at DATETIME,
                    INDEX idx_user (user_id),
                    INDEX idx_action (action),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Patch for missing columns if table already existed
            $columns = ['resource', 'details', 'old_value', 'new_value', 'ip_address'];
            foreach ($columns as $col) {
                try {
                    $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
                    $type = ($col === 'details') ? 'TEXT' : (($col === 'old_value' || $col === 'new_value') ? 'JSON' : 'VARCHAR(255)');
                    $this->db->exec("ALTER TABLE audit_logs ADD COLUMN `{$safeCol}` {$type}");
                } catch (\Exception $e) {
                    // Ignore "Duplicate column name" error
                }
            }
        } catch (\Exception $e) {
            error_log('AuditService: failed to ensure table - ' . $e->getMessage());
        }
    }
}
