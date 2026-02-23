-- =====================================================
-- Migration: Create seo_monitoring_schedule table
-- Description: Agenda de verificações automáticas do SEO Monitoring
-- Date: 2026-01-22
-- =====================================================

CREATE TABLE IF NOT EXISTS `seo_monitoring_schedule` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_id` VARCHAR(50) NOT NULL,
    `account_id` INT UNSIGNED NULL,
    `interval_days` TINYINT UNSIGNED NOT NULL DEFAULT 7,
    `next_check` DATETIME NOT NULL,
    `last_checked` DATETIME NULL,
    `status` ENUM('active', 'paused', 'error') NOT NULL DEFAULT 'active',
    `priority` ENUM('low', 'normal', 'high') NOT NULL DEFAULT 'normal',
    `last_result` JSON NULL,
    `error_message` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_item_id` (`item_id`),
    INDEX `idx_status_next_check` (`status`, `next_check`),
    INDEX `idx_account_status` (`account_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exemplo de agendamento inicial (opcional)
INSERT INTO `seo_monitoring_schedule` (`item_id`, `account_id`, `interval_days`, `next_check`, `priority`)
VALUES ('MLB123456789', 1, 7, DATE_ADD(NOW(), INTERVAL 1 DAY), 'normal')
ON DUPLICATE KEY UPDATE
    `interval_days` = VALUES(`interval_days`),
    `next_check` = VALUES(`next_check`),
    `priority` = VALUES(`priority`);
