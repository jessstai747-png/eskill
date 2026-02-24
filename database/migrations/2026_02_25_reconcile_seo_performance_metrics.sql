-- =============================================================================
-- Reconciliation migration for seo_performance_metrics table
-- =============================================================================
-- Two conflicting CREATE TABLE IF NOT EXISTS migrations:
--   030_create_seo_intelligence_tables.sql (visits, unique_visitors, questions, sales, seo_score, search_rank)
--   2026_02_16_create_seo_performance_metrics_table.sql (views, visits, sold_quantity, position_avg, was_optimized)
-- Plus PerformanceTracker.php inline (same as 2026_02_16).
-- Code also references traffic_sources (SEOMonitoringService).
-- This migration ensures ALL columns exist regardless of which CREATE ran first.
-- =============================================================================

DELIMITER //

DROP PROCEDURE IF EXISTS reconcile_seo_performance_metrics //

CREATE PROCEDURE reconcile_seo_performance_metrics()
BEGIN
    -- From 030 migration (not in 2026_02_16)
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seo_performance_metrics' AND COLUMN_NAME = 'unique_visitors') THEN
        ALTER TABLE seo_performance_metrics ADD COLUMN unique_visitors INT UNSIGNED DEFAULT 0 AFTER visits;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seo_performance_metrics' AND COLUMN_NAME = 'questions') THEN
        ALTER TABLE seo_performance_metrics ADD COLUMN questions INT UNSIGNED DEFAULT 0;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seo_performance_metrics' AND COLUMN_NAME = 'sales') THEN
        ALTER TABLE seo_performance_metrics ADD COLUMN sales INT UNSIGNED DEFAULT 0;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seo_performance_metrics' AND COLUMN_NAME = 'seo_score') THEN
        ALTER TABLE seo_performance_metrics ADD COLUMN seo_score TINYINT UNSIGNED NULL COMMENT 'SEO score on this date';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seo_performance_metrics' AND COLUMN_NAME = 'search_rank') THEN
        ALTER TABLE seo_performance_metrics ADD COLUMN search_rank INT UNSIGNED NULL COMMENT 'Average search ranking position';
    END IF;

    -- From 2026_02_16 migration (not in 030)
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seo_performance_metrics' AND COLUMN_NAME = 'views') THEN
        ALTER TABLE seo_performance_metrics ADD COLUMN views INT DEFAULT 0 COMMENT 'Total page views';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seo_performance_metrics' AND COLUMN_NAME = 'sold_quantity') THEN
        ALTER TABLE seo_performance_metrics ADD COLUMN sold_quantity INT DEFAULT 0 COMMENT 'Units sold';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seo_performance_metrics' AND COLUMN_NAME = 'position_avg') THEN
        ALTER TABLE seo_performance_metrics ADD COLUMN position_avg DECIMAL(5,2) DEFAULT 0 COMMENT 'Average search position';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seo_performance_metrics' AND COLUMN_NAME = 'was_optimized') THEN
        ALTER TABLE seo_performance_metrics ADD COLUMN was_optimized TINYINT(1) DEFAULT 0 COMMENT 'Whether item was SEO-optimized';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seo_performance_metrics' AND COLUMN_NAME = 'optimization_date') THEN
        ALTER TABLE seo_performance_metrics ADD COLUMN optimization_date DATE NULL COMMENT 'Date of last optimization';
    END IF;

    -- Common columns needed by both
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seo_performance_metrics' AND COLUMN_NAME = 'revenue') THEN
        ALTER TABLE seo_performance_metrics ADD COLUMN revenue DECIMAL(12,2) DEFAULT 0 COMMENT 'Total revenue';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seo_performance_metrics' AND COLUMN_NAME = 'conversion_rate') THEN
        ALTER TABLE seo_performance_metrics ADD COLUMN conversion_rate DECIMAL(5,2) DEFAULT 0 COMMENT 'Calculated conversion rate';
    END IF;

    -- Referenced in code but not in any migration
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seo_performance_metrics' AND COLUMN_NAME = 'traffic_sources') THEN
        ALTER TABLE seo_performance_metrics ADD COLUMN traffic_sources JSON NULL COMMENT 'Breakdown of traffic sources';
    END IF;

END //

DELIMITER ;

CALL reconcile_seo_performance_metrics();
DROP PROCEDURE IF EXISTS reconcile_seo_performance_metrics;

-- Sync sales ↔ sold_quantity for existing rows
UPDATE seo_performance_metrics SET sold_quantity = sales WHERE sold_quantity = 0 AND sales > 0;
UPDATE seo_performance_metrics SET sales = sold_quantity WHERE sales = 0 AND sold_quantity > 0;
