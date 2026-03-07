<?php

declare(strict_types=1);

namespace App\Services\AI\Core;

/**
 * Audit Log Service
 * Tracks all optimizations and changes for compliance and rollback
 */
class AuditLogService
{
    private \PDO $db;
    
    public function __construct()
    {
        $this->db = \App\Database::getInstance();
        $this->createAuditTables();
    }
    
    /**
     * Create audit tables
     */
    private function createAuditTables(): void
    {
        // Audit log table
        $sql = "CREATE TABLE IF NOT EXISTS ai_audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id VARCHAR(50) NOT NULL,
            action ENUM('optimize', 'preview', 'apply', 'rollback') NOT NULL,
            user_id INT,
            changes JSON NOT NULL,
            before_state JSON,
            after_state JSON,
            cost DECIMAL(10,4),
            ai_provider VARCHAR(50),
            ai_model VARCHAR(50),
            success BOOLEAN DEFAULT true,
            error_message TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_item_id (item_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        
        // Performance tracking table
        $sql2 = "CREATE TABLE IF NOT EXISTS ai_performance_tracking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            audit_log_id INT NOT NULL,
            item_id VARCHAR(50) NOT NULL,
            date DATE NOT NULL,
            views_before INT DEFAULT 0,
            views_after INT DEFAULT 0,
            visits_before INT DEFAULT 0,
            visits_after INT DEFAULT 0,
            sales_before INT DEFAULT 0,
            sales_after INT DEFAULT 0,
            revenue_before DECIMAL(10,2) DEFAULT 0,
            revenue_after DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (audit_log_id) REFERENCES ai_audit_log(id) ON DELETE CASCADE,
            INDEX idx_item_date (item_id, date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql2);
    }
    
    /**
     * Log an optimization action
     * 
     * @param string $itemId
     * @param string $action
     * @param array $changes
     * @param array $metadata
     * @return int Log ID
     */
    public function logAction(string $itemId, string $action, array $changes, array $metadata = []): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ai_audit_log 
            (item_id, action, user_id, changes, before_state, after_state, 
             cost, ai_provider, ai_model, success, error_message, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $itemId,
            $action,
            $metadata['user_id'] ?? null,
            json_encode($changes),
            json_encode($metadata['before_state'] ?? null),
            json_encode($metadata['after_state'] ?? null),
            $metadata['cost'] ?? 0,
            $metadata['ai_provider'] ?? null,
            $metadata['ai_model'] ?? null,
            $metadata['success'] ?? true,
            $metadata['error_message'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Get audit history for an item
     * 
     * @param string $itemId
     * @param int $limit
     * @return array
     */
    public function getItemHistory(string $itemId, int $limit = 50): array
    {
        $limitSql = max(1, min(200, (int)$limit));
        $stmt = $this->db->prepare(
            "SELECT * FROM ai_audit_log 
            WHERE item_id = ? 
            ORDER BY created_at DESC 
            LIMIT {$limitSql}"
        );
        
        $stmt->execute([$itemId]);
        $logs = $stmt->fetchAll();
        
        // Parse JSON fields
        foreach ($logs as &$log) {
            $log['changes'] = json_decode($log['changes'], true);
            $log['before_state'] = json_decode($log['before_state'], true);
            $log['after_state'] = json_decode($log['after_state'], true);
        }
        
        return $logs;
    }

    /**
     * Get recent audit log (global)
     */
    public function getRecentLog(int $limit = 50): array
    {
        $limitSql = max(1, min(200, (int)$limit));
        $stmt = $this->db->prepare(
            "SELECT * FROM ai_audit_log 
            ORDER BY created_at DESC 
            LIMIT {$limitSql}"
        );
        
        $stmt->execute();
        $logs = $stmt->fetchAll();
        
        // Parse JSON fields
        foreach ($logs as &$log) {
            $log['changes'] = json_decode($log['changes'], true);
            $log['before_state'] = json_decode($log['before_state'], true);
            $log['after_state'] = json_decode($log['after_state'], true);
        }
        
        return $logs;
    }
    
    /**
     * Rollback to a previous state
     * 
     * @param int $logId Audit log ID to rollback to
     * @return array Rollback result
     */
    public function rollback(int $logId): array
    {
        // Get the log entry
        $stmt = $this->db->prepare("SELECT * FROM ai_audit_log WHERE id = ?");
        $stmt->execute([$logId]);
        $log = $stmt->fetch();
        
        if (!$log) {
            return ['error' => 'Log entry not found'];
        }
        
        $beforeState = json_decode($log['before_state'], true);
        
        if (!$beforeState) {
            return ['error' => 'No previous state to rollback to'];
        }
        
        // Log the rollback action
        $rollbackLogId = $this->logAction(
            $log['item_id'],
            'rollback',
            ['rolled_back_from' => $logId],
            [
                'before_state' => json_decode($log['after_state'], true),
                'after_state' => $beforeState,
            ]
        );
        
        return [
            'success' => true,
            'item_id' => $log['item_id'],
            'rollback_log_id' => $rollbackLogId,
            'restored_state' => $beforeState,
            'message' => 'State restored successfully. Apply changes to ML to complete rollback.',
        ];
    }
    
    /**
     * Get optimization statistics
     * 
     * @param array $filters
     * @return array
     */
    public function getStatistics(array $filters = []): array
    {
        $where = [];
        $params = [];
        
        if (isset($filters['start_date'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (isset($filters['end_date'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (isset($filters['action'])) {
            $where[] = "action = ?";
            $params[] = $filters['action'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Overall stats
        $sql = "SELECT 
            COUNT(*) as total_actions,
            SUM(CASE WHEN action = 'optimize' THEN 1 ELSE 0 END) as total_optimizations,
            SUM(CASE WHEN action = 'apply' THEN 1 ELSE 0 END) as total_applied,
            SUM(CASE WHEN action = 'rollback' THEN 1 ELSE 0 END) as total_rollbacks,
            SUM(cost) as total_cost,
            AVG(cost) as avg_cost,
            SUM(CASE WHEN action = 'apply' OR action = 'optimize' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN action = 'rollback' THEN 1 ELSE 0 END) as failed
        FROM ai_audit_log 
        {$whereClause}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch();
        
        // Provider breakdown
        $sqlProvider = "SELECT 
            ai_provider,
            COUNT(*) as count,
            SUM(cost) as total_cost
        FROM ai_audit_log 
        {$whereClause}
        GROUP BY ai_provider";
        
        $stmt = $this->db->prepare($sqlProvider);
        $stmt->execute($params);
        $providerStats = $stmt->fetchAll();
        
        return [
            'overall' => $stats,
            'by_provider' => $providerStats,
            'success_rate' => $stats['total_actions'] > 0
                ? round(($stats['successful'] / $stats['total_actions']) * 100, 2)
                : 0,
        ];
    }
    
    /**
     * Track performance impact
     * 
     * @param int $auditLogId
     * @param string $itemId
     * @param array $metrics
     */
    public function trackPerformance(int $auditLogId, string $itemId, array $metrics): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ai_performance_tracking 
            (audit_log_id, item_id, date, views_before, views_after, 
             visits_before, visits_after, sales_before, sales_after,
             revenue_before, revenue_after) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $auditLogId,
            $itemId,
            $metrics['date'] ?? date('Y-m-d'),
            $metrics['views_before'] ?? 0,
            $metrics['views_after'] ?? 0,
            $metrics['visits_before'] ?? 0,
            $metrics['visits_after'] ?? 0,
            $metrics['sales_before'] ?? 0,
            $metrics['sales_after'] ?? 0,
            $metrics['revenue_before'] ?? 0,
            $metrics['revenue_after'] ?? 0,
        ]);
    }
    
    /**
     * Get performance impact report
     * 
     * @param string|null $itemId
     * @param int $days
     * @return array
     */
    public function getPerformanceReport(?string $itemId = null, int $days = 30): array
    {
        $where = "WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
        $params = [$days];
        
        if ($itemId) {
            $where .= " AND item_id = ?";
            $params[] = $itemId;
        }
        
        $sql = "SELECT 
            SUM(views_after - views_before) as total_views_gain,
            SUM(visits_after  - visits_before) as total_visits_gain,
            SUM(sales_after - sales_before) as total_sales_gain,
            SUM(revenue_after - revenue_before) as total_revenue_gain,
            AVG((views_after - views_before) / NULLIF(views_before, 0) * 100) as avg_views_improvement,
            AVG((visits_after - visits_before) / NULLIF(visits_before, 0) * 100) as avg_visits_improvement,
            AVG((sales_after - sales_before) / NULLIF(sales_before, 0) * 100) as avg_sales_improvement,
            COUNT(DISTINCT item_id) as items_tracked
        FROM ai_performance_tracking 
        {$where}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch();
    }
}
