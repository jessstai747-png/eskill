<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

class AuditService
{
    private ?PDO $db;

    public function __construct(
        ?PDO $db = null,
        bool $skipDbAutoConnect = false
    ) {
        if ($db !== null) {
            $this->db = $db;
        } elseif (!$skipDbAutoConnect) {
            $this->db = Database::getInstance();
            $this->ensureTable();
        } else {
            $this->db = null;
        }
    }

    /**
     * Log an action
     */
    public function log(int $userId, string $action, string $resource, string $details = '', ?array $oldValue = null, ?array $newValue = null): bool
    {
        if ($this->db === null) {
            return false;
        }

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO audit_logs (
                    user_id,
                    ml_account_id,
                    action,
                    resource,
                    details,
                    old_value,
                    new_value,
                    ip_address,
                    user_agent,
                    data,
                    created_at
                ) VALUES (
                    :user_id,
                    :ml_account_id,
                    :action,
                    :resource,
                    :details,
                    :old_value,
                    :new_value,
                    :ip_address,
                    :user_agent,
                    :data,
                    :created_at
                )"
            );

            $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';

            $payload = [
                'details' => $details,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ];

            return $stmt->execute([
                'user_id' => $userId,
                'ml_account_id' => null,
                'action' => $action,
                'resource' => $resource,
                'details' => $details !== '' ? $details : null,
                'old_value' => $oldValue !== null ? json_encode($oldValue) : null,
                'new_value' => $newValue !== null ? json_encode($newValue) : null,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'data' => json_encode($payload),
                'created_at' => date('Y-m-d H:i:s'),
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
        if ($this->db === null) {
            return [];
        }

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

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            log_warning('AuditService: falha ao buscar logs', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function ensureTable(): void
    {
        if ($this->db === null) {
            return;
        }

        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $this->db->exec("CREATE TABLE IF NOT EXISTS audit_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NULL,
                    ml_account_id INTEGER NULL,
                    action TEXT NOT NULL,
                    resource TEXT DEFAULT 'system',
                    details TEXT NULL,
                    old_value TEXT NULL,
                    new_value TEXT NULL,
                    ip_address TEXT NULL,
                    user_agent TEXT NULL,
                    data TEXT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );");

                $this->db->exec("CREATE INDEX IF NOT EXISTS idx_audit_user_id ON audit_logs(user_id);");
                $this->db->exec("CREATE INDEX IF NOT EXISTS idx_audit_account_id ON audit_logs(ml_account_id);");
                $this->db->exec("CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_logs(action);");
                $this->db->exec("CREATE INDEX IF NOT EXISTS idx_audit_created_at ON audit_logs(created_at);");
                return;
            }

            // MySQL schema (unified with AuditLogService)
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NULL,
                    ml_account_id INT NULL,
                    action VARCHAR(100) NOT NULL,
                    resource VARCHAR(100) DEFAULT 'system',
                    details TEXT NULL,
                    old_value JSON NULL,
                    new_value JSON NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent TEXT NULL,
                    data JSON NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_account_id (ml_account_id),
                    INDEX idx_action (action),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $alterStatements = [
                "ALTER TABLE audit_logs ADD COLUMN resource VARCHAR(100) DEFAULT 'system'",
                "ALTER TABLE audit_logs ADD COLUMN ml_account_id INT NULL",
                "ALTER TABLE audit_logs ADD COLUMN details TEXT NULL",
                "ALTER TABLE audit_logs ADD COLUMN old_value JSON NULL",
                "ALTER TABLE audit_logs ADD COLUMN new_value JSON NULL",
                "ALTER TABLE audit_logs ADD COLUMN ip_address VARCHAR(45) NULL",
                "ALTER TABLE audit_logs ADD COLUMN user_agent TEXT NULL",
                "ALTER TABLE audit_logs ADD COLUMN data JSON NULL",
            ];

            foreach ($alterStatements as $sql) {
                try {
                    $this->db->exec($sql);
                } catch (\Throwable $e) {
                    // Ignore if column exists or JSON not supported
                }
            }
        } catch (\Throwable $e) {
            log_error('AuditService: failed to ensure table', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
