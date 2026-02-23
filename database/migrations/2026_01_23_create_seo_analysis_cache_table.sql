-- Migration: Create SEO Analysis Cache Table
-- Date: 2026-01-23
-- Purpose: Cache SEO strategy analyses for performance optimization

CREATE TABLE IF NOT EXISTS `seo_analysis_cache` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_id` VARCHAR(50) NOT NULL,
    `account_id` INT UNSIGNED NOT NULL,
    `category_id` VARCHAR(50) NULL,
    
    -- Cached analysis data
    `overall_score` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `strategies_json` JSON NOT NULL COMMENT 'Individual strategy scores and data',
    `suggestions_json` JSON NULL COMMENT 'Generated suggestions',
    `title_analysis_json` JSON NULL COMMENT 'Title optimization data',
    `description_analysis_json` JSON NULL COMMENT 'Description optimization data',
    
    -- Metadata
    `item_title` VARCHAR(255) NULL,
    `item_price` DECIMAL(12,2) NULL,
    `analysis_version` VARCHAR(20) DEFAULT '1.0.0',
    
    -- Cache control
    `expires_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    UNIQUE KEY `uk_item_account` (`item_id`, `account_id`),
    KEY `idx_account_id` (`account_id`),
    KEY `idx_category_id` (`category_id`),
    KEY `idx_expires_at` (`expires_at`),
    KEY `idx_overall_score` (`overall_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index for cleaning expired cache
CREATE INDEX IF NOT EXISTS `idx_cache_cleanup` ON `seo_analysis_cache` (`expires_at`, `updated_at`);

-- Table to track SEO analysis jobs
CREATE TABLE IF NOT EXISTS `seo_analysis_jobs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT UNSIGNED NOT NULL,
    `job_type` ENUM('single', 'batch', 'scheduled') DEFAULT 'single',
    `status` ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
    
    -- Job data
    `item_ids_json` JSON NOT NULL,
    `total_items` INT UNSIGNED DEFAULT 0,
    `processed_items` INT UNSIGNED DEFAULT 0,
    `failed_items` INT UNSIGNED DEFAULT 0,
    
    -- Results
    `results_json` JSON NULL,
    `error_message` TEXT NULL,
    
    -- Timing
    `started_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    KEY `idx_account_status` (`account_id`, `status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
