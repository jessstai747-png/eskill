-- Performance Indexes V2
-- Stabilization Phase 3: Scalability
-- Idempotent: Verifica existência antes de criar

-- Competitor Tracking: Improve dashboard performance (filtering by account + sorting by last checked)
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'competitor_tracking' AND INDEX_NAME = 'idx_tracking_account_last_checked');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_tracking_account_last_checked ON competitor_tracking (account_id, last_checked)', 'SELECT ''Index exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Audit Logs: Improve history filtering performance
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND INDEX_NAME = 'idx_audit_account_created');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_audit_account_created ON audit_logs (ml_account_id, created_at)', 'SELECT ''Index exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Error Monitoring: Improve dashboard stats loading
SET @exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'error_monitoring' AND INDEX_NAME = 'idx_error_monitor_account_created');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_error_monitor_account_created ON error_monitoring (account_id, created_at)', 'SELECT ''Index exists''');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;
