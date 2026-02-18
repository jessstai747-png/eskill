<?php

namespace App\Services;

use App\Database;

class AuditLogService
{
    /**
     * Registra ação de auditoria
     */
    public function log(string $action, ?int $userId = null, ?int $accountId = null, array $data = [], string $resource = 'system'): void
    {
        $db = Database::getInstance();

        $this->ensureAuditLogsTable();

        try {
            $stmt = $db->prepare("\n                INSERT INTO audit_logs \n                (user_id, ml_account_id, action, resource, ip_address, user_agent, data, created_at)\n                VALUES \n                (:user_id, :account_id, :action, :resource, :ip, :user_agent, :data, :created_at)\n            ");

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
        $db = Database::getInstance();

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

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $logs = $stmt->fetchAll();

        foreach ($logs as &$log) {
            $log['data'] = json_decode($log['data'], true);
        }

        return $logs;
    }

    /**
     * Garante que tabela existe
     */
    private function ensureAuditLogsTable(): void
    {
        $db = Database::getInstance();
        // Use dialect-specific SQL to support both MySQL and SQLite in tests
        $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            // SQLite compatible table
            $db->exec("CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                ml_account_id INTEGER NULL,
                action TEXT NOT NULL,
                resource TEXT DEFAULT 'system',
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                data TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );");

            // Create indexes if they don't exist
            $db->exec("CREATE INDEX IF NOT EXISTS idx_user_id ON audit_logs(user_id);");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_account_id ON audit_logs(ml_account_id);");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_action ON audit_logs(action);");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_created_at ON audit_logs(created_at);");
        } else {
            // Default to MySQL-compatible schema
            $db->exec("
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NULL,
                    ml_account_id INT NULL,
                    action VARCHAR(100) NOT NULL,
                    resource VARCHAR(100) DEFAULT 'system',
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

            // Ensure legacy tables get the resource column
            try {
                $db->exec("ALTER TABLE audit_logs ADD COLUMN resource VARCHAR(100) DEFAULT 'system'");
            } catch (\Throwable $e) {
                // Ignore if column already exists
            }
        }
    }
}
