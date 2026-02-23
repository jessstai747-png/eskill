-- Migration: Error Monitoring System
-- Criado em: 2025-12-25

CREATE TABLE IF NOT EXISTS `error_monitoring` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `error_type` VARCHAR(100) NOT NULL,
    `error_message` TEXT NOT NULL,
    `file` VARCHAR(500) DEFAULT NULL,
    `line` INT DEFAULT NULL,
    `trace` TEXT DEFAULT NULL COMMENT 'JSON stack trace',
    `context` TEXT DEFAULT NULL COMMENT 'JSON context data',
    `user_id` INT UNSIGNED DEFAULT NULL,
    `account_id` INT UNSIGNED DEFAULT NULL,
    `url` VARCHAR(500) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `severity` ENUM('critical', 'error', 'warning', 'notice') DEFAULT 'error',
    `resolved` TINYINT(1) DEFAULT 0,
    `resolved_at` DATETIME DEFAULT NULL,
    `resolved_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_severity` (`severity`),
    INDEX `idx_error_type` (`error_type`),
    INDEX `idx_file_line` (`file`, `line`),
    INDEX `idx_account_id` (`account_id`),
    INDEX `idx_resolved` (`resolved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
