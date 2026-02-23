-- Migration: Tech Sheet Execution Log Table
-- Created: 2026-01-01

CREATE TABLE IF NOT EXISTS `tech_sheet_execution_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id` INT UNSIGNED NOT NULL,
    `item_id` VARCHAR(50) NULL COMMENT 'ML item ID (null for batch operations)',
    `action` VARCHAR(100) NOT NULL COMMENT 'generate, apply, auto_optimize, batch, etc',
    `result` VARCHAR(20) NOT NULL COMMENT 'success, failed, partial',
    `details` JSON NULL COMMENT 'Execution details',
    `error_message` TEXT NULL,
    `duration_ms` INT UNSIGNED NULL COMMENT 'Execution time in milliseconds',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_account_action` (`account_id`, `action`),
    KEY `idx_item` (`item_id`),
    KEY `idx_result` (`result`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Execution log for Tech Sheet operations';
