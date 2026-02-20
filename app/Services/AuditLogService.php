<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

class AuditLogService
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
        } else {
            $this->db = null;
        }
    }

    /**
     * Registra ação de auditoria
     */
    public function log(string $action, ?int $userId = null, ?int $accountId = null, array $data = [], string $resource = 'system'): void
    {
        if ($this->db === null) {
            return;
        }

        $this->ensureAuditLogsTable();

        try {
            $stmt = $this->db->prepare("\n                INSERT INTO audit_logs \n                (user_id, ml_account_id, action, resource, ip_address, user_agent, data, created_at)\n                VALUES \n                (:user_id, :account_id, :action, :resource, :ip, :user_agent, :data, :created_at)\n            ");

            $stmt->execute([
                'user_id' => $userId,
                'account_id' => $accountId,
                'action' => $action,
                'resource' => $resource,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'data' => json_encode($data),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            log_warning('Falha ao registrar log de auditoria', [
                'action' => $action,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtém logs de auditoria
     */
    public function getLogs(array $filters = []): array
    {
        if ($this->db === null) {
            return [];
        }

        $sql = "SELECT * FROM audit_logs WHERE 1=1";
        $params = [];

        if (isset($filters['user_id'])) {
            $sql .= " AND user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }

        if (isset($filters['account_id'])) {
            $sql .= " AND ml_account_id = :account_id";
            $params['account_id'] = $filters['account_id'];
        }

        if (isset($filters['action'])) {
            $sql .= " AND action = :action";
            $params['action'] = $filters['action'];
        }

        if (isset($filters['date_from'])) {
            $sql .= " AND created_at >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $sql .= " AND created_at <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $limitSql = max(1, min((int) ($filters['limit'] ?? 100), 500));
        $sql .= " ORDER BY created_at DESC LIMIT " . $limitSql;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $logs = $stmt->fetchAll();

        foreach ($logs as &$log) {
            if (array_key_exists('data', $log) && is_string($log['data']) && $log['data'] !== '') {
                $log['data'] = json_decode($log['data'], true);
            }
        }

        return $logs;
    }

    /**
     * Garante que tabela existe
     */
    private function ensureAuditLogsTable(): void
    {
        if ($this->db === null) {
            return;
        }

        // Use dialect-specific SQL to support both MySQL and SQLite in tests
        $driver = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            // SQLite compatible table
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

            // Create indexes if they don't exist
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_user_id ON audit_logs(user_id);");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_account_id ON audit_logs(ml_account_id);");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_action ON audit_logs(action);");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_created_at ON audit_logs(created_at);");
        } else {
            // Default to MySQL-compatible schema
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

            // Ensure legacy tables get missing columns (idempotent best-effort)
            $alterStatements = [
                "ALTER TABLE audit_logs ADD COLUMN resource VARCHAR(100) DEFAULT 'system'",
                "ALTER TABLE audit_logs ADD COLUMN ml_account_id INT NULL",
                "ALTER TABLE audit_logs ADD COLUMN user_agent TEXT NULL",
                "ALTER TABLE audit_logs ADD COLUMN data JSON NULL",
                "ALTER TABLE audit_logs ADD COLUMN details TEXT NULL",
                "ALTER TABLE audit_logs ADD COLUMN old_value JSON NULL",
                "ALTER TABLE audit_logs ADD COLUMN new_value JSON NULL",
            ];

            foreach ($alterStatements as $sql) {
                try {
                    $this->db->exec($sql);
                } catch (\Throwable $e) {
                    // Ignore if column already exists or JSON not supported
                }
            }
        }
    }
}
