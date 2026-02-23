-- Migration: Tech Sheet Scheduled Jobs Table
-- Created: 2026-01-01

CREATE TABLE IF NOT EXISTS `tech_sheet_scheduled_jobs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id` INT UNSIGNED NOT NULL,
    `job_type` VARCHAR(50) NOT NULL COMMENT 'auto_optimizer, email_report, batch_analysis, cleanup',
    `schedule_cron` VARCHAR(100) NOT NULL COMMENT 'Cron expression',
    `config` JSON NULL COMMENT 'Job configuration',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, paused, failed',
    `last_run_at` DATETIME NULL,
    `next_run_at` DATETIME NULL,
    `last_result` JSON NULL COMMENT 'Last execution result',
    `run_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_account_status` (`account_id`, `status`),
    KEY `idx_next_run` (`next_run_at`),
    KEY `idx_job_type` (`job_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Scheduled jobs for Tech Sheet automation';
