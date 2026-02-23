-- @deprecated Use 2026_02_08_create_ml_accounts_table.php (versão mais recente e completa)
-- Migration: Create ml_accounts table for Mercado Livre OAuth credentials
-- Created: 2026-01-11

CREATE TABLE IF NOT EXISTS `ml_accounts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `ml_user_id` VARCHAR(64) NOT NULL,
    `nickname` VARCHAR(255) NULL,
    `email` VARCHAR(255) NULL,
    `site_id` VARCHAR(10) NOT NULL DEFAULT 'MLB',
    `access_token` TEXT NULL,
    `refresh_token` TEXT NULL,
    `token_expires_at` DATETIME NULL,
    `last_synced_at` DATETIME NULL,
    `scopes` VARCHAR(255) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `tokens_encrypted` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_ml_user` (`ml_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Contas vinculadas do Mercado Livre e credenciais (tokens criptografados)';
