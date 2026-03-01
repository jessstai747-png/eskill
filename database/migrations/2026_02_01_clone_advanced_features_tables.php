<?php

/**
 * Migration: Create Advanced Clone Module Tables
 *
 * Creates tables for:
 * - A/B Testing system
 * - Notification logs (Slack/Discord)
 * - Automation rules
 * - Seller recommendations
 * - ROI analysis
 *
 * @version 1.0.0
 * @date 2026-02-01
 */

declare(strict_types=1);

$db = App\Database::getInstance();

// Desabilitar FK checks para evitar problemas de ordem
$db->exec("SET FOREIGN_KEY_CHECKS = 0");

echo "=== Migration: Advanced Clone Module Tables ===\n\n";

// -----------------------------------------------------------------------------
// Table: clone_ab_tests
// -----------------------------------------------------------------------------
echo "Creating table: clone_ab_tests...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_ab_tests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    variation_type VARCHAR(50) NOT NULL COMMENT 'template, seo_level, price_rule, etc',
    primary_metric VARCHAR(50) NOT NULL DEFAULT 'clone_success_rate',
    secondary_metrics JSON NULL,
    min_sample_size INT UNSIGNED NOT NULL DEFAULT 100,
    status ENUM('draft', 'running', 'paused', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    winner_variation_id INT UNSIGNED NULL,
    final_results JSON NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_account_id (account_id),
    INDEX idx_status (status),
    INDEX idx_variation_type (variation_type),
    INDEX idx_created_at (created_at),

    CONSTRAINT fk_ab_test_account FOREIGN KEY (account_id)
        REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='A/B Testing experiments for clone strategies'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_ab_tests created successfully\n";
} catch (PDOException $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Table: clone_ab_test_variations
// -----------------------------------------------------------------------------
echo "\nCreating table: clone_ab_test_variations...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_ab_test_variations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    test_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_control TINYINT(1) NOT NULL DEFAULT 0,
    traffic_percentage TINYINT UNSIGNED NOT NULL DEFAULT 50,
    configuration JSON NOT NULL COMMENT 'Template ID, SEO level, price rules, etc',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_test_id (test_id),
    INDEX idx_is_control (is_control),

    CONSTRAINT fk_ab_variation_test FOREIGN KEY (test_id)
        REFERENCES clone_ab_tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Variations within A/B tests'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_ab_test_variations created successfully\n";
} catch (PDOException $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Table: clone_ab_test_entries
// -----------------------------------------------------------------------------
echo "\nCreating table: clone_ab_test_entries...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_ab_test_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    test_id INT UNSIGNED NOT NULL,
    variation_id INT UNSIGNED NOT NULL,
    source_item_id VARCHAR(50) NOT NULL,
    target_item_id VARCHAR(50) NULL,
    clone_success TINYINT(1) NULL,
    metric_value DECIMAL(15,4) NULL,
    metrics_data JSON NULL COMMENT 'Additional metrics collected',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,

    INDEX idx_test_id (test_id),
    INDEX idx_variation_id (variation_id),
    INDEX idx_source_item (source_item_id),
    INDEX idx_target_item (target_item_id),

    CONSTRAINT fk_ab_entry_test FOREIGN KEY (test_id)
        REFERENCES clone_ab_tests(id) ON DELETE CASCADE,
    CONSTRAINT fk_ab_entry_variation FOREIGN KEY (variation_id)
        REFERENCES clone_ab_test_variations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual items assigned to A/B test variations'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_ab_test_entries created successfully\n";
} catch (PDOException $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Table: clone_notification_logs
// -----------------------------------------------------------------------------
echo "\nCreating table: clone_notification_logs...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_notification_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    platform ENUM('slack', 'discord', 'email', 'webhook') NOT NULL,
    alert_type VARCHAR(50) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'info',
    title VARCHAR(200) NULL,
    message TEXT NULL,
    payload JSON NULL,
    success TINYINT(1) NOT NULL DEFAULT 1,
    error_message TEXT NULL,
    http_code INT NULL,
    response_time_ms INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_account_id (account_id),
    INDEX idx_platform (platform),
    INDEX idx_alert_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_success (success),
    INDEX idx_created_at (created_at),
    INDEX idx_account_platform (account_id, platform, created_at),

    CONSTRAINT fk_notif_log_account FOREIGN KEY (account_id)
        REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Log of all notification attempts (Slack, Discord, etc)'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_notification_logs created successfully\n";
} catch (PDOException $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Table: clone_automation_rules
// -----------------------------------------------------------------------------
echo "\nCreating table: clone_automation_rules...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_automation_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    priority INT UNSIGNED NOT NULL DEFAULT 100,

    -- Triggers
    trigger_type ENUM('schedule', 'seller_update', 'category_trend', 'price_change', 'stock_change', 'manual') NOT NULL,
    trigger_config JSON NOT NULL COMMENT 'Cron expression, seller IDs, category IDs, thresholds',

    -- Conditions
    conditions JSON NULL COMMENT 'Array of condition objects',

    -- Actions
    action_type ENUM('clone', 'update', 'pause', 'activate', 'notify') NOT NULL,
    action_config JSON NOT NULL COMMENT 'Template ID, target account, options',

    -- Limits
    max_items_per_run INT UNSIGNED NOT NULL DEFAULT 100,
    cooldown_minutes INT UNSIGNED NOT NULL DEFAULT 60,

    -- Stats
    last_run_at TIMESTAMP NULL,
    last_run_status VARCHAR(50) NULL,
    total_runs INT UNSIGNED NOT NULL DEFAULT 0,
    total_items_processed INT UNSIGNED NOT NULL DEFAULT 0,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_account_id (account_id),
    INDEX idx_is_active (is_active),
    INDEX idx_trigger_type (trigger_type),
    INDEX idx_priority (priority),
    INDEX idx_last_run (last_run_at),

    CONSTRAINT fk_auto_rule_account FOREIGN KEY (account_id)
        REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Automation rules for scheduled/triggered cloning'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_automation_rules created successfully\n";
} catch (PDOException $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Table: clone_automation_runs
// -----------------------------------------------------------------------------
echo "\nCreating table: clone_automation_runs...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_automation_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_id INT UNSIGNED NOT NULL,
    account_id INT NOT NULL,
    job_id INT UNSIGNED NULL COMMENT 'Related clone job if created',
    status ENUM('running', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'running',
    trigger_reason VARCHAR(200) NULL,
    items_matched INT UNSIGNED NOT NULL DEFAULT 0,
    items_processed INT UNSIGNED NOT NULL DEFAULT 0,
    items_succeeded INT UNSIGNED NOT NULL DEFAULT 0,
    items_failed INT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    execution_log JSON NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,

    INDEX idx_rule_id (rule_id),
    INDEX idx_account_id (account_id),
    INDEX idx_job_id (job_id),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at),

    CONSTRAINT fk_auto_run_rule FOREIGN KEY (rule_id)
        REFERENCES clone_automation_rules(id) ON DELETE CASCADE,
    CONSTRAINT fk_auto_run_account FOREIGN KEY (account_id)
        REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Execution history of automation rules'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_automation_runs created successfully\n";
} catch (PDOException $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Table: clone_seller_recommendations
// -----------------------------------------------------------------------------
echo "\nCreating table: clone_seller_recommendations...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_seller_recommendations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    seller_id VARCHAR(50) NOT NULL,
    seller_nickname VARCHAR(200) NULL,
    category_id VARCHAR(50) NULL,

    -- Metrics
    total_items INT UNSIGNED NOT NULL DEFAULT 0,
    avg_price DECIMAL(15,2) NULL,
    avg_sales_velocity DECIMAL(10,4) NULL COMMENT 'Sales per day',
    reputation_score DECIMAL(5,2) NULL,

    -- Recommendation scores
    relevance_score DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT '0-100',
    opportunity_score DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT '0-100',
    competition_score DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT '0-100, lower is better',
    overall_score DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'Weighted average',

    -- Analysis
    recommendation_reason TEXT NULL,
    top_items JSON NULL COMMENT 'Array of best items to clone',
    categories_breakdown JSON NULL,

    -- Status
    is_followed TINYINT(1) NOT NULL DEFAULT 0,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    last_analyzed_at TIMESTAMP NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_account_seller (account_id, seller_id),
    INDEX idx_account_id (account_id),
    INDEX idx_seller_id (seller_id),
    INDEX idx_overall_score (overall_score DESC),
    INDEX idx_category (category_id),
    INDEX idx_is_followed (is_followed),

    CONSTRAINT fk_seller_rec_account FOREIGN KEY (account_id)
        REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='ML-powered seller recommendations for cloning'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_seller_recommendations created successfully\n";
} catch (PDOException $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Table: clone_roi_analysis
// -----------------------------------------------------------------------------
echo "\nCreating table: clone_roi_analysis...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_roi_analysis (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    job_id INT UNSIGNED NULL,
    source_item_id VARCHAR(50) NOT NULL,
    target_item_id VARCHAR(50) NOT NULL,

    -- Costs
    clone_cost DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Estimated cost to clone',
    listing_fee DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_investment DECIMAL(15,2) NOT NULL DEFAULT 0,

    -- Revenue
    total_sales INT UNSIGNED NOT NULL DEFAULT 0,
    total_revenue DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_profit DECIMAL(15,2) NOT NULL DEFAULT 0,
    avg_margin_percent DECIMAL(5,2) NULL,

    -- Performance
    days_to_first_sale INT UNSIGNED NULL,
    total_views INT UNSIGNED NOT NULL DEFAULT 0,
    total_visits INT UNSIGNED NOT NULL DEFAULT 0,
    conversion_rate DECIMAL(5,4) NULL,

    -- ROI Calculation
    roi_percent DECIMAL(10,2) NULL COMMENT '((revenue - investment) / investment) * 100',
    roi_status ENUM('positive', 'negative', 'neutral', 'pending') NOT NULL DEFAULT 'pending',
    break_even_date DATE NULL,

    -- Period
    analysis_period_days INT UNSIGNED NOT NULL DEFAULT 30,
    first_sale_at TIMESTAMP NULL,
    last_sale_at TIMESTAMP NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_target_item (target_item_id),
    INDEX idx_account_id (account_id),
    INDEX idx_job_id (job_id),
    INDEX idx_source_item (source_item_id),
    INDEX idx_roi_status (roi_status),
    INDEX idx_roi_percent (roi_percent DESC),
    INDEX idx_created_at (created_at),

    CONSTRAINT fk_roi_account FOREIGN KEY (account_id)
        REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='ROI tracking for cloned items'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_roi_analysis created successfully\n";
} catch (PDOException $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Table: clone_seo_optimizations
// -----------------------------------------------------------------------------
echo "\nCreating table: clone_seo_optimizations...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_seo_optimizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    job_id INT UNSIGNED NULL,
    source_item_id VARCHAR(50) NOT NULL,
    target_item_id VARCHAR(50) NULL,

    -- SEO Scores
    score_before INT UNSIGNED NOT NULL,
    score_after INT UNSIGNED NOT NULL,
    score_improvement INT GENERATED ALWAYS AS (score_after - score_before) STORED,

    -- Optimization details
    optimization_level VARCHAR(20) NOT NULL COMMENT 'none, basic, advanced, aggressive',
    changes_applied JSON NOT NULL COMMENT 'Array of changes made',
    warnings JSON NULL,

    -- Before/After snapshots
    title_before VARCHAR(200) NULL,
    title_after VARCHAR(200) NULL,
    description_length_before INT UNSIGNED NULL,
    description_length_after INT UNSIGNED NULL,
    attributes_added JSON NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_account_id (account_id),
    INDEX idx_job_id (job_id),
    INDEX idx_source_item (source_item_id),
    INDEX idx_target_item (target_item_id),
    INDEX idx_score_improvement (score_improvement DESC),
    INDEX idx_optimization_level (optimization_level),

    CONSTRAINT fk_seo_opt_account FOREIGN KEY (account_id)
        REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='SEO optimization tracking for cloned items'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_seo_optimizations created successfully\n";
} catch (PDOException $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Table: clone_progress_tracking
// -----------------------------------------------------------------------------
echo "\nCreating table: clone_progress_tracking...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_progress_tracking (
    job_id INT UNSIGNED PRIMARY KEY,
    total_items INT UNSIGNED NOT NULL,
    current_phase VARCHAR(50) NOT NULL DEFAULT 'validation',
    phase_progress DECIMAL(5,2) NOT NULL DEFAULT 0,
    overall_progress DECIMAL(5,2) NOT NULL DEFAULT 0,
    eta_seconds INT UNSIGNED NULL,
    items_per_second DECIMAL(10,4) NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_current_phase (current_phase),
    INDEX idx_overall_progress (overall_progress)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Real-time progress tracking for clone jobs'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_progress_tracking created successfully\n";
} catch (PDOException $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Table: clone_progress_history
// -----------------------------------------------------------------------------
echo "\nCreating table: clone_progress_history...\n";

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS clone_progress_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    phase VARCHAR(50) NOT NULL,
    progress DECIMAL(5,2) NOT NULL,
    items_processed INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_job_id (job_id),
    INDEX idx_job_phase (job_id, phase),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historical progress data for analytics'
SQL;

try {
    $db->exec($sql);
    echo "  ✓ Table clone_progress_history created successfully\n";
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
echo "  - clone_ab_tests\n";
echo "  - clone_ab_test_variations\n";
echo "  - clone_ab_test_entries\n";
echo "  - clone_notification_logs\n";
echo "  - clone_automation_rules\n";
echo "  - clone_automation_runs\n";
echo "  - clone_seller_recommendations\n";
echo "  - clone_roi_analysis\n";
echo "  - clone_seo_optimizations\n";
echo "  - clone_progress_tracking\n";
echo "  - clone_progress_history\n";
echo "\n";
