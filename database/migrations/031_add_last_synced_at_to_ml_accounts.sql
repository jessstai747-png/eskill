-- Migration: add last_synced_at to ml_accounts (idempotent)
-- Created: 2026-01-27

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ml_accounts'
      AND COLUMN_NAME = 'last_synced_at'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE `ml_accounts` ADD COLUMN `last_synced_at` DATETIME NULL AFTER `token_expires_at`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

