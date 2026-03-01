<?php

/**
 * Migration: Create Compliance and Analytics Tables for Clone Module
 *
 * Creates the following tables:
 * - clone_audit_logs: Audit trail for all clone operations
 * - clone_policy_violations: Policy violation records
 * - clone_analytics_events: Analytics event tracking
 *
 * @version 1.0.0
 * @date 2025-01-08
 */

declare(strict_types=1);

$db = App\Database::getInstance();

// Desabilitar FK checks para evitar problemas de ordem
$db->exec("SET FOREIGN_KEY_CHECKS = 0");

echo "=== Migration: Clone Compliance & Analytics Tables ===\n\n";

// -----------------------------------------------------------------------------
// Table: clone_audit_logs
// -----------------------------------------------------------------------------
echo "Creating table: clone_audit_logs...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    user_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    item_id VARCHAR(50) NULL,
    event_type VARCHAR(50) NOT NULL COMMENT 'job_started, item_cloned, error_occurred, etc',
    event_category VARCHAR(30) NOT NULL DEFAULT 'operation' COMMENT 'operation, security, system, user',
    severity VARCHAR(20) NOT NULL DEFAULT 'info' COMMENT 'info, warning, error, critical',
    action VARCHAR(100) NULL COMMENT 'Specific action performed',
    resource_type VARCHAR(50) NULL COMMENT 'job, item, seller, category, etc',
    resource_id VARCHAR(100) NULL COMMENT 'ID of the affected resource',
    old_value JSON NULL COMMENT 'Previous value (for changes)',
    new_value JSON NULL COMMENT 'New value (for changes)',
    details JSON NULL COMMENT 'Additional event details',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    session_id VARCHAR(100) NULL,
    request_id VARCHAR(50) NULL COMMENT 'Unique request identifier for tracing',
    duration_ms INT UNSIGNED NULL COMMENT 'Operation duration in milliseconds',
    success TINYINT(1) NOT NULL DEFAULT 1,
    error_message TEXT NULL,
    error_code VARCHAR(50) NULL,
    stack_trace TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_account_id (account_id),
    INDEX idx_user_id (user_id),
    INDEX idx_job_id (job_id),
    INDEX idx_item_id (item_id),
    INDEX idx_event_type (event_type),
    INDEX idx_event_category (event_category),
    INDEX idx_severity (severity),
    INDEX idx_created_at (created_at),
    INDEX idx_account_created (account_id, created_at),
    INDEX idx_job_created (job_id, created_at),
    INDEX idx_severity_created (severity, created_at),

    CONSTRAINT fk_audit_account FOREIGN KEY (account_id)
        REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail for clone operations - compliance and security tracking'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_audit_logs created successfully\n";
} catch (PDOException $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Table: clone_policy_violations
// -----------------------------------------------------------------------------
echo "\nCreating table: clone_policy_violations...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_policy_violations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    job_id INT UNSIGNED NULL,
    item_id VARCHAR(50) NULL,
    audit_log_id BIGINT UNSIGNED NULL COMMENT 'Reference to audit log entry',
    policy_name VARCHAR(100) NOT NULL COMMENT 'Name of the violated policy',
    policy_code VARCHAR(50) NOT NULL COMMENT 'Unique policy code',
    policy_category VARCHAR(50) NOT NULL COMMENT 'rate_limit, content, seller, category, etc',
    violation_type VARCHAR(50) NOT NULL COMMENT 'exceeded, blocked, restricted, etc',
    severity VARCHAR(20) NOT NULL DEFAULT 'warning' COMMENT 'low, medium, high, critical',
    description TEXT NOT NULL COMMENT 'Human-readable description',
    threshold_value VARCHAR(100) NULL COMMENT 'Policy threshold value',
    actual_value VARCHAR(100) NULL COMMENT 'Actual measured value',
    context JSON NULL COMMENT 'Additional context data',
    auto_resolved TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether violation was auto-resolved',
    resolved_at TIMESTAMP NULL,
    resolved_by INT UNSIGNED NULL,
    resolution_notes TEXT NULL,
    action_taken VARCHAR(100) NULL COMMENT 'Action taken to resolve',
    acknowledged TINYINT(1) NOT NULL DEFAULT 0,
    acknowledged_at TIMESTAMP NULL,
    acknowledged_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_account_id (account_id),
    INDEX idx_job_id (job_id),
    INDEX idx_item_id (item_id),
    INDEX idx_policy_code (policy_code),
    INDEX idx_policy_category (policy_category),
    INDEX idx_severity (severity),
    INDEX idx_created_at (created_at),
    INDEX idx_account_unresolved (account_id, auto_resolved, resolved_at),
    INDEX idx_severity_unresolved (severity, auto_resolved, resolved_at),

    CONSTRAINT fk_violation_account FOREIGN KEY (account_id)
        REFERENCES ml_accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_violation_audit FOREIGN KEY (audit_log_id)
        REFERENCES clone_audit_logs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Policy violation records for clone module compliance'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_policy_violations created successfully\n";
} catch (PDOException $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Table: clone_analytics_events
// -----------------------------------------------------------------------------
echo "\nCreating table: clone_analytics_events...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_analytics_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    user_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    event_name VARCHAR(100) NOT NULL COMMENT 'Name of the analytics event',
    event_category VARCHAR(50) NOT NULL COMMENT 'performance, usage, conversion, error',
    event_action VARCHAR(100) NULL COMMENT 'Specific action',
    event_label VARCHAR(200) NULL COMMENT 'Additional label/identifier',
    event_value DECIMAL(15,4) NULL COMMENT 'Numeric value associated with event',
    properties JSON NULL COMMENT 'Additional event properties',
    dimensions JSON NULL COMMENT 'Dimension values for analysis',
    metrics JSON NULL COMMENT 'Metric values',
    session_id VARCHAR(100) NULL,
    source VARCHAR(50) NULL COMMENT 'Event source (web, api, worker, etc)',
    device_type VARCHAR(30) NULL,
    browser VARCHAR(50) NULL,
    os VARCHAR(50) NULL,
    country VARCHAR(2) NULL,
    region VARCHAR(100) NULL,
    processing_time_ms INT UNSIGNED NULL,
    is_sampled TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether this is a sampled event',
    sample_rate DECIMAL(5,4) NULL COMMENT 'Sample rate if sampled',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    event_date DATE GENERATED ALWAYS AS (DATE(created_at)) STORED,
    event_hour TINYINT GENERATED ALWAYS AS (HOUR(created_at)) STORED,

    INDEX idx_account_id (account_id),
    INDEX idx_job_id (job_id),
    INDEX idx_event_name (event_name),
    INDEX idx_event_category (event_category),
    INDEX idx_created_at (created_at),
    INDEX idx_event_date (event_date),
    INDEX idx_account_date (account_id, event_date),
    INDEX idx_account_category_date (account_id, event_category, event_date),
    INDEX idx_event_name_date (event_name, event_date),

    CONSTRAINT fk_analytics_account FOREIGN KEY (account_id)
        REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Analytics event tracking for clone module - metrics and insights'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_analytics_events created successfully\n";
} catch (PDOException $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Table: clone_compliance_reports (for generated reports)
// -----------------------------------------------------------------------------
echo "\nCreating table: clone_compliance_reports...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_compliance_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    report_type VARCHAR(50) NOT NULL COMMENT 'daily, weekly, monthly, custom, audit',
    report_name VARCHAR(200) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    filters JSON NULL COMMENT 'Filters applied to generate report',
    summary JSON NOT NULL COMMENT 'Summary statistics',
    total_events INT UNSIGNED NOT NULL DEFAULT 0,
    total_violations INT UNSIGNED NOT NULL DEFAULT 0,
    compliance_score DECIMAL(5,2) NULL COMMENT 'Overall compliance score 0-100',
    file_path VARCHAR(500) NULL COMMENT 'Path to exported file if any',
    file_format VARCHAR(20) NULL COMMENT 'csv, pdf, xlsx, json',
    file_size INT UNSIGNED NULL COMMENT 'File size in bytes',
    generated_by INT UNSIGNED NULL,
    generation_time_ms INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, generating, completed, failed',
    error_message TEXT NULL,
    expires_at TIMESTAMP NULL COMMENT 'When the report file expires',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,

    INDEX idx_account_id (account_id),
    INDEX idx_report_type (report_type),
    INDEX idx_period (period_start, period_end),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_account_type_date (account_id, report_type, created_at),

    CONSTRAINT fk_report_account FOREIGN KEY (account_id)
        REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Generated compliance reports storage'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_compliance_reports created successfully\n";
} catch (PDOException $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Table: clone_analytics_aggregates (pre-aggregated metrics for performance)
// -----------------------------------------------------------------------------
echo "\nCreating table: clone_analytics_aggregates...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_analytics_aggregates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    period_type VARCHAR(20) NOT NULL COMMENT 'hourly, daily, weekly, monthly',
    period_start DATETIME NOT NULL,
    period_end DATETIME NOT NULL,
    metric_name VARCHAR(100) NOT NULL,
    dimension_name VARCHAR(100) NULL COMMENT 'Optional dimension for breakdown',
    dimension_value VARCHAR(200) NULL,

    -- Aggregate values
    count_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    sum_value DECIMAL(20,4) NULL,
    avg_value DECIMAL(15,4) NULL,
    min_value DECIMAL(15,4) NULL,
    max_value DECIMAL(15,4) NULL,
    p50_value DECIMAL(15,4) NULL COMMENT 'Median',
    p90_value DECIMAL(15,4) NULL,
    p99_value DECIMAL(15,4) NULL,
    stddev_value DECIMAL(15,4) NULL,

    -- Additional metadata
    sample_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of samples used',
    is_complete TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether period is complete',
    computed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_aggregate (account_id, period_type, period_start, metric_name, dimension_name, dimension_value),
    INDEX idx_account_period (account_id, period_type, period_start),
    INDEX idx_metric_period (metric_name, period_type, period_start),
    INDEX idx_computed (computed_at),

    CONSTRAINT fk_aggregate_account FOREIGN KEY (account_id)
        REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pre-aggregated analytics metrics for fast dashboard queries'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_analytics_aggregates created successfully\n";
} catch (PDOException $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Reabilitar FK checks
$db->exec("SET FOREIGN_KEY_CHECKS = 1");

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n=== Migration Complete ===\n";
echo "Tables created:\n";
echo "  - clone_audit_logs\n";
echo "  - clone_policy_violations\n";
echo "  - clone_analytics_events\n";
echo "  - clone_compliance_reports\n";
echo "  - clone_analytics_aggregates\n";
echo "\n";
