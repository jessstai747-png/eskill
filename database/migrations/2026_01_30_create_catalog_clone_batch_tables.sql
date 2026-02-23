-- =============================================================================
-- Migration: Catalog Clone Batch Tables
-- Description: Creates tables for batch cloning operations (multi-account)
-- Date: 2026-01-30
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Table: catalog_clone_jobs
-- Purpose: Stores batch cloning job metadata for tracking and reporting
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS catalog_clone_jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id VARCHAR(64) NOT NULL UNIQUE COMMENT 'Unique identifier for the job',
    target_account_id INT UNSIGNED NOT NULL COMMENT 'Destination ML account ID',
    source_type ENUM('seller', 'item_ids', 'account') NOT NULL DEFAULT 'seller' COMMENT 'Origin type',
    source_seller_id VARCHAR(50) NULL COMMENT 'ML Seller ID when source_type is seller',
    source_account_id INT UNSIGNED NULL COMMENT 'Internal account ID when source is connected account',
    status ENUM('pending', 'queued', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    total_items INT UNSIGNED NOT NULL DEFAULT 0,
    processed_items INT UNSIGNED NOT NULL DEFAULT 0,
    successful_items INT UNSIGNED NOT NULL DEFAULT 0,
    failed_items INT UNSIGNED NOT NULL DEFAULT 0,
    skipped_items INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Duplicates or validation failures',
    options JSON NULL COMMENT 'Template, pricing rules, stock rules, flags',
    error_message TEXT NULL COMMENT 'General error message if job failed',
    created_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_status (status),
    INDEX idx_target_account (target_account_id),
    INDEX idx_source_seller (source_seller_id),
    INDEX idx_created_at (created_at),
    INDEX idx_user (created_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: catalog_clone_job_items
-- Purpose: Stores individual items within a batch cloning job
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS catalog_clone_job_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id VARCHAR(64) NOT NULL COMMENT 'Reference to catalog_clone_jobs.job_id',
    source_item_id VARCHAR(50) NOT NULL COMMENT 'Original ML item ID',
    source_snapshot JSON NULL COMMENT 'Captured data: title, category, brand, price, is_catalog, etc.',
    target_item_id VARCHAR(50) NULL COMMENT 'Created ML item ID on success',
    status ENUM('pending', 'processing', 'completed', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
    is_catalog TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether source item is catalog',
    brand VARCHAR(255) NULL COMMENT 'Extracted brand for facets',
    error_message TEXT NULL,
    error_code VARCHAR(100) NULL COMMENT 'Structured error code for reporting',
    result JSON NULL COMMENT 'Additional result data, warnings, etc.',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of processing attempts',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_job_id (job_id),
    INDEX idx_status (status),
    INDEX idx_source_item (source_item_id),
    INDEX idx_brand (brand),
    INDEX idx_is_catalog (is_catalog),
    INDEX idx_job_status (job_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Migrate existing cloned_items to new structure (if needed)
-- This adds missing columns to support non-catalog items
-- -----------------------------------------------------------------------------
ALTER TABLE cloned_items
    ADD COLUMN IF NOT EXISTS is_catalog TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether cloned item is catalog',
    ADD COLUMN IF NOT EXISTS brand VARCHAR(255) NULL COMMENT 'Source item brand',
    ADD COLUMN IF NOT EXISTS source_snapshot JSON NULL COMMENT 'Snapshot of source item at clone time',
    ADD COLUMN IF NOT EXISTS clone_job_id VARCHAR(64) NULL COMMENT 'Reference to batch job if applicable',
    ADD INDEX IF NOT EXISTS idx_clone_job (clone_job_id),
    ADD INDEX IF NOT EXISTS idx_is_catalog (is_catalog);

-- -----------------------------------------------------------------------------
-- View for quick job statistics
-- -----------------------------------------------------------------------------
CREATE OR REPLACE VIEW catalog_clone_job_stats AS
SELECT 
    ccj.id,
    ccj.job_id,
    ccj.target_account_id,
    ma.nickname AS target_account_name,
    ccj.source_type,
    ccj.source_seller_id,
    ccj.status,
    ccj.total_items,
    ccj.successful_items,
    ccj.failed_items,
    ccj.skipped_items,
    ROUND((ccj.successful_items / NULLIF(ccj.total_items, 0)) * 100, 1) AS success_rate,
    ccj.created_at,
    ccj.completed_at,
    TIMESTAMPDIFF(SECOND, ccj.started_at, ccj.completed_at) AS duration_seconds
FROM catalog_clone_jobs ccj
LEFT JOIN ml_accounts ma ON ma.id = ccj.target_account_id;
