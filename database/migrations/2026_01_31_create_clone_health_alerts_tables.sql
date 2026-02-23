-- =============================================================================
-- Migration: Clone Health and Alerts Tables
-- Description: Creates tables for health monitoring and alerting system
-- Date: 2026-01-31
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Table: clone_health_metrics
-- Purpose: Stores system health snapshots for trend analysis
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clone_health_metrics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    check_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When this health check occurred',
    health_score INT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'Overall health score (0-100)',
    status ENUM('healthy', 'degraded', 'critical') NOT NULL DEFAULT 'healthy',
    issues_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of critical issues found',
    warnings_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of warnings found',
    metrics_json JSON NULL COMMENT 'Detailed metrics data',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_timestamp (check_timestamp),
    INDEX idx_status (status),
    INDEX idx_score (health_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='System health monitoring snapshots';

-- -----------------------------------------------------------------------------
-- Table: clone_alerts
-- Purpose: Tracks alerting events for anomalies and issues
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clone_alerts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alert_type VARCHAR(100) NOT NULL COMMENT 'Type of alert: stuck_job, high_failure_rate, disk_space, etc.',
    severity ENUM('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'warning',
    title VARCHAR(255) NOT NULL COMMENT 'Short alert title',
    message TEXT NOT NULL COMMENT 'Detailed alert message',
    context_json JSON NULL COMMENT 'Additional context data (job_id, metrics, etc.)',
    triggered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL COMMENT 'When this alert was resolved/acknowledged',
    resolved_by_user_id INT UNSIGNED NULL,
    resolution_notes TEXT NULL COMMENT 'How this was resolved',
    notification_sent TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether notification was dispatched',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_alert_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_triggered (triggered_at),
    INDEX idx_resolved (resolved_at),
    INDEX idx_active_alerts (resolved_at, severity) -- Find unresolved critical alerts
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Alerting system for clone operations';

-- -----------------------------------------------------------------------------
-- View: active_alerts
-- Purpose: Quick access to unresolved alerts
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW active_clone_alerts AS
SELECT 
    id,
    alert_type,
    severity,
    title,
    message,
    context_json,
    triggered_at,
    TIMESTAMPDIFF(MINUTE, triggered_at, NOW()) AS minutes_active
FROM clone_alerts
WHERE resolved_at IS NULL
ORDER BY 
    CASE severity
        WHEN 'critical' THEN 1
        WHEN 'error' THEN 2
        WHEN 'warning' THEN 3
        ELSE 4
    END,
    triggered_at DESC;

-- -----------------------------------------------------------------------------
-- View: health_trends
-- Purpose: 7-day health score trends
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW clone_health_trends AS
SELECT 
    DATE(check_timestamp) AS health_date,
    MIN(health_score) AS min_score,
    MAX(health_score) AS max_score,
    AVG(health_score) AS avg_score,
    COUNT(*) AS checks_count,
    SUM(issues_count) AS total_issues,
    SUM(warnings_count) AS total_warnings
FROM clone_health_metrics
WHERE check_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(check_timestamp)
ORDER BY health_date DESC;
