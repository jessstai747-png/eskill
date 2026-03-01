-- =============================================================================
-- Migration: Clone Health and Alerts Tables
-- Description: Creates tables for health monitoring and alerting system
-- Date: 2026-01-31
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Table: clone_health_metrics
-- Purpose: Stores per-metric health data for trend analysis
-- Columns match CloneMonitoringService::recordMetric() expectations
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clone_health_metrics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL COMMENT 'Metric identifier: clone_status_error, api_blocks, etc.',
    metric_value DECIMAL(12,4) NOT NULL DEFAULT 0 COMMENT 'Numeric value of the metric',
    metric_unit VARCHAR(50) NOT NULL DEFAULT 'count' COMMENT 'Unit: count, ms, percent, etc.',
    context JSON NULL COMMENT 'Additional context data',
    recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When this metric was recorded',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_metric_name (metric_name),
    INDEX idx_recorded_at (recorded_at),
    INDEX idx_metric_recorded (metric_name, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Clone system health metrics';

-- -----------------------------------------------------------------------------
-- Table: clone_alerts
-- Purpose: Tracks alerting events for anomalies and issues
-- Columns match CloneMonitoringService::createAlert() expectations
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clone_alerts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alert_type VARCHAR(100) NOT NULL COMMENT 'Type of alert: stuck_job, high_failure_rate, disk_space, etc.',
    severity ENUM('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'warning',
    message TEXT NOT NULL COMMENT 'Detailed alert message',
    context JSON NULL COMMENT 'Additional context data (job_id, metrics, etc.)',
    acknowledged TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether this alert has been acknowledged',
    acknowledged_by INT UNSIGNED NULL COMMENT 'User who acknowledged',
    acknowledged_at TIMESTAMP NULL COMMENT 'When the alert was acknowledged',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_alert_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_acknowledged (acknowledged),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Alerting system for clone operations';

-- -----------------------------------------------------------------------------
-- View: active_alerts
-- Purpose: Quick access to unacknowledged alerts
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW active_clone_alerts AS
SELECT
    id,
    alert_type,
    severity,
    message,
    context,
    created_at,
    TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS minutes_active
FROM clone_alerts
WHERE acknowledged = FALSE
ORDER BY
    CASE severity
        WHEN 'critical' THEN 1
        WHEN 'error' THEN 2
        WHEN 'warning' THEN 3
        ELSE 4
    END,
    created_at DESC;

-- -----------------------------------------------------------------------------
-- View: health_trends
-- Purpose: 7-day metric trends
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW clone_health_trends AS
SELECT
    DATE(recorded_at) AS health_date,
    metric_name,
    SUM(metric_value) AS total_value,
    AVG(metric_value) AS avg_value,
    COUNT(*) AS data_points
FROM clone_health_metrics
WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(recorded_at), metric_name
ORDER BY health_date DESC;
