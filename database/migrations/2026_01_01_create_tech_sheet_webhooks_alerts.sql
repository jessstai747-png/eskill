-- Migration: Tech Sheet Webhooks Table
-- Created: 2026-01-01

CREATE TABLE IF NOT EXISTS `tech_sheet_webhooks` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id` INT UNSIGNED NOT NULL,
    `type` VARCHAR(20) NOT NULL COMMENT 'slack, telegram, http',
    `url` VARCHAR(500) NOT NULL,
    `config` JSON NULL COMMENT 'Bot tokens, channels, headers, etc',
    `events` JSON NOT NULL COMMENT 'Array of events to listen: ["*"] or ["suggestions.generated", "alert.critical"]',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, paused, failed',
    `last_triggered_at` DATETIME NULL,
    `last_error` TEXT NULL,
    `last_error_at` DATETIME NULL,
    `success_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `failure_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_account_status` (`account_id`, `status`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Webhooks configuration for Tech Sheet notifications';

-- Migration: Tech Sheet Alert Rules Table
CREATE TABLE IF NOT EXISTS `tech_sheet_alert_rules` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `type` VARCHAR(50) NOT NULL COMMENT 'completeness, suggestions, performance, etc',
    `conditions` JSON NOT NULL COMMENT 'Array of conditions: [{"field": "completeness", "operator": "<", "value": 50}]',
    `channels` JSON NOT NULL COMMENT 'Array of channels: ["email", "webhook", "slack"]',
    `cooldown_minutes` INT UNSIGNED NOT NULL DEFAULT 60,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `trigger_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_triggered_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_account_type` (`account_id`, `type`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Custom alert rules for Tech Sheet';

-- Migration: Tech Sheet Alert Recipients Table
CREATE TABLE IF NOT EXISTS `tech_sheet_alert_recipients` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rule_id` INT UNSIGNED NOT NULL,
    `email` VARCHAR(200) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_rule_email` (`rule_id`, `email`),
    KEY `idx_rule` (`rule_id`),
    CONSTRAINT `fk_alert_recipients_rule` 
        FOREIGN KEY (`rule_id`) 
        REFERENCES `tech_sheet_alert_rules` (`id`) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Recipients for alert rules';

-- Migration: Tech Sheet Alerts Table (history)
CREATE TABLE IF NOT EXISTS `tech_sheet_alerts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id` INT UNSIGNED NOT NULL,
    `rule_id` INT UNSIGNED NOT NULL,
    `data` JSON NOT NULL COMMENT 'Alert data that triggered the rule',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_account_created` (`account_id`, `created_at`),
    KEY `idx_rule` (`rule_id`),
    CONSTRAINT `fk_alerts_rule` 
        FOREIGN KEY (`rule_id`) 
        REFERENCES `tech_sheet_alert_rules` (`id`) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='History of triggered alerts';
