-- Migration: Production stabilization schema reconciliation
-- Date: 2026-02-26
-- Purpose:
-- 1) Create missing operational tables used by monitoring/workers
-- 2) Align enum values with runtime usage (disconnected/refresh_disconnected)
-- 3) Mark invalid_grant accounts as disconnected to force OAuth re-authorization

CREATE TABLE IF NOT EXISTS worker_execution_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    worker_name VARCHAR(120) NOT NULL,
    stats JSON NULL,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_worker_execution_logs_name (worker_name),
    INDEX idx_worker_execution_logs_executed_at (executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clone_health_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status ENUM('healthy', 'warning', 'critical') NOT NULL DEFAULT 'healthy',
    issues_count INT UNSIGNED NOT NULL DEFAULT 0,
    check_data JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_clone_health_logs_created_at (created_at),
    INDEX idx_clone_health_logs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clone_duplicate_registry (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    source_item_id VARCHAR(50) NOT NULL,
    target_item_id VARCHAR(50) NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    job_id VARCHAR(64) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_source_account_status (source_item_id, account_id, status),
    INDEX idx_target_status (target_item_id, status),
    INDEX idx_account_created (account_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clone sync event log (fallback usado por CloneHealthMonitorService via checkApiConnectivityViaLogs).
-- Idempotente: usa CREATE TABLE IF NOT EXISTS para compatibilidade com 2025_06_clone_sync_tables.php.
CREATE TABLE IF NOT EXISTS clone_sync_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    sync_type VARCHAR(50) NOT NULL,
    sync_data JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_clone_sync_logs_account (account_id),
    INDEX idx_clone_sync_logs_item (item_id),
    INDEX idx_clone_sync_logs_type (sync_type),
    INDEX idx_clone_sync_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure ml_accounts.status supports disconnected state used by refresh flow.
SET @db_name := DATABASE();
SET @ml_status_data_type := (
    SELECT LOWER(DATA_TYPE)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'ml_accounts'
      AND COLUMN_NAME = 'status'
    LIMIT 1
);
SET @ml_status_column_type := (
    SELECT LOWER(COLUMN_TYPE)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'ml_accounts'
      AND COLUMN_NAME = 'status'
    LIMIT 1
);

SET @sql := IF(
    @ml_status_data_type = 'enum' AND @ml_status_column_type NOT LIKE '%''disconnected''%',
    'ALTER TABLE ml_accounts MODIFY COLUMN status ENUM(''active'',''inactive'',''expired'',''disconnected'') DEFAULT ''active''',
    'SELECT ''ml_accounts.status already compatible'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure token_refresh_audit.action supports refresh_disconnected action.
SET @audit_action_data_type := (
    SELECT LOWER(DATA_TYPE)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'token_refresh_audit'
      AND COLUMN_NAME = 'action'
    LIMIT 1
);
SET @audit_action_column_type := (
    SELECT LOWER(COLUMN_TYPE)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'token_refresh_audit'
      AND COLUMN_NAME = 'action'
    LIMIT 1
);

SET @sql := IF(
    @audit_action_data_type = 'enum' AND @audit_action_column_type NOT LIKE '%''refresh_disconnected''%',
    'ALTER TABLE token_refresh_audit MODIFY COLUMN action ENUM(''refresh_attempt'',''refresh_success'',''refresh_failed'',''authorization_granted'',''token_expired'',''lock_acquired'',''lock_timeout'',''refresh_disconnected'') NOT NULL',
    'SELECT ''token_refresh_audit.action already compatible'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Force re-authorization path for accounts with invalid_grant history.
SET @sql := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'ml_accounts'
    ),
    'UPDATE ml_accounts SET status = ''disconnected'', updated_at = NOW() WHERE last_refresh_error LIKE ''%invalid_grant%''',
    'SELECT ''ml_accounts table not found'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
