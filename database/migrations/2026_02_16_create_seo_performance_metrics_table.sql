-- ============================================================================
-- Migration: Create seo_performance_metrics and seo_optimization_events tables
-- Date: 2026-02-16
-- Description: Formal migration for tables previously created inline in
--              App\Services\AI\SEO\PerformanceTracker::ensureTableExists()
--              Referenced by MLAnalyticsIntelligenceService, PerformanceTracker
-- ============================================================================

CREATE TABLE IF NOT EXISTS seo_performance_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL COMMENT 'ML item ID (e.g., MLB123456)',
    metric_date DATE NOT NULL,
    views INT DEFAULT 0 COMMENT 'Total page views',
    visits INT DEFAULT 0 COMMENT 'Unique visits',
    sold_quantity INT DEFAULT 0 COMMENT 'Units sold',
    revenue DECIMAL(12,2) DEFAULT 0 COMMENT 'Total revenue',
    conversion_rate DECIMAL(5,2) DEFAULT 0 COMMENT 'Calculated conversion rate',
    position_avg DECIMAL(5,2) DEFAULT 0 COMMENT 'Average search position',
    was_optimized TINYINT(1) DEFAULT 0 COMMENT 'Whether item was SEO-optimized',
    optimization_date DATE NULL COMMENT 'Date of last optimization',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_item_date (item_id, metric_date),
    INDEX idx_account (account_id),
    INDEX idx_item (item_id),
    INDEX idx_date (metric_date),
    INDEX idx_account_date (account_id, metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_optimization_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL COMMENT 'ML item ID',
    optimization_type ENUM('title', 'description', 'attributes', 'full') NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    score_before INT DEFAULT 0,
    score_after INT DEFAULT 0,
    optimized_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_account (account_id),
    INDEX idx_item (item_id),
    INDEX idx_date (optimized_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
