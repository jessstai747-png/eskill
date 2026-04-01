<?php

/**
 * Migration: Create stock sync tables
 *
 * Tables for automatic stock synchronization between Mercado Livre accounts.
 * Supports queue-based sync with priority, retry, and full history tracking.
 */

declare(strict_types=1);

use App\Database;

$db = Database::getInstance();

// 1. Stock sync rules — defines which items sync between which accounts
$db->exec("
CREATE TABLE IF NOT EXISTS stock_sync_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    source_account_id INT NOT NULL,
    target_account_id INT NOT NULL,
    source_item_id VARCHAR(50) NOT NULL,
    target_item_id VARCHAR(50) NOT NULL,
    sync_mode ENUM('mirror', 'offset', 'percentage', 'custom') NOT NULL DEFAULT 'mirror',
    offset_value INT DEFAULT 0 COMMENT 'For offset mode: target = source + offset',
    percentage_value DECIMAL(5,2) DEFAULT 100.00 COMMENT 'For percentage mode: target = source * pct/100',
    min_stock INT DEFAULT 0 COMMENT 'Minimum stock to keep on target',
    max_stock INT DEFAULT NULL COMMENT 'Maximum stock allowed on target',
    priority TINYINT UNSIGNED DEFAULT 5 COMMENT '1=highest, 10=lowest',
    is_active TINYINT(1) DEFAULT 1,
    last_synced_at DATETIME DEFAULT NULL,
    last_source_quantity INT DEFAULT NULL,
    last_target_quantity INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_source_account (source_account_id),
    INDEX idx_target_account (target_account_id),
    INDEX idx_source_item (source_item_id),
    INDEX idx_target_item (target_item_id),
    INDEX idx_active_priority (is_active, priority),
    INDEX idx_last_synced (last_synced_at),
    UNIQUE KEY uk_source_target (source_item_id, target_item_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (source_account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (target_account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "✅ Table 'stock_sync_rules' created successfully!\n";

// 2. Stock sync queue — items pending synchronization
$db->exec("
CREATE TABLE IF NOT EXISTS stock_sync_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT NOT NULL,
    source_quantity INT NOT NULL,
    target_quantity_before INT DEFAULT NULL,
    target_quantity_calculated INT NOT NULL,
    trigger_type ENUM('webhook', 'full_sync', 'incremental', 'manual') NOT NULL DEFAULT 'incremental',
    status ENUM('pending', 'processing', 'completed', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
    priority TINYINT UNSIGNED DEFAULT 5,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    last_error TEXT DEFAULT NULL,
    next_retry_at DATETIME DEFAULT NULL,
    processed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_priority (status, priority, created_at),
    INDEX idx_rule_id (rule_id),
    INDEX idx_next_retry (status, next_retry_at),
    INDEX idx_trigger_type (trigger_type),
    INDEX idx_processed (processed_at),
    FOREIGN KEY (rule_id) REFERENCES stock_sync_rules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "✅ Table 'stock_sync_queue' created successfully!\n";

// 3. Stock sync history — full audit trail of all changes
$db->exec("
CREATE TABLE IF NOT EXISTS stock_sync_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT NOT NULL,
    queue_id INT DEFAULT NULL,
    source_account_id INT NOT NULL,
    target_account_id INT NOT NULL,
    source_item_id VARCHAR(50) NOT NULL,
    target_item_id VARCHAR(50) NOT NULL,
    source_quantity INT NOT NULL,
    target_quantity_before INT DEFAULT NULL,
    target_quantity_after INT NOT NULL,
    sync_mode VARCHAR(20) NOT NULL,
    trigger_type VARCHAR(20) NOT NULL,
    status ENUM('success', 'failed', 'skipped') NOT NULL,
    error_message TEXT DEFAULT NULL,
    api_response TEXT DEFAULT NULL COMMENT 'ML API response JSON',
    duration_ms INT DEFAULT NULL COMMENT 'Duration in milliseconds',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rule_id (rule_id),
    INDEX idx_source_item (source_item_id),
    INDEX idx_target_item (target_item_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_trigger_type (trigger_type),
    FOREIGN KEY (rule_id) REFERENCES stock_sync_rules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "✅ Table 'stock_sync_history' created successfully!\n";

// 4. Stock sync settings — per-user configuration
$db->exec("
CREATE TABLE IF NOT EXISTS stock_sync_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    is_enabled TINYINT(1) DEFAULT 1,
    rate_limit_per_minute INT DEFAULT 30 COMMENT 'Max API calls per minute',
    full_sync_interval_minutes INT DEFAULT 60 COMMENT 'How often to run full sync',
    webhook_enabled TINYINT(1) DEFAULT 1,
    notify_on_error TINYINT(1) DEFAULT 1,
    notify_on_sync TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "✅ Table 'stock_sync_settings' created successfully!\n";

echo "\n🎉 Stock sync migration completed successfully!\n";
echo "\nTables created:\n";
echo "- stock_sync_rules: sync rules between accounts\n";
echo "- stock_sync_queue: pending sync items with priority\n";
echo "- stock_sync_history: full audit trail of changes\n";
echo "- stock_sync_settings: per-user configuration\n";

/*
 * DOWN — Para reverter esta migration manualmente:
 *
//   $db->exec('DROP TABLE IF EXISTS stock_sync_queue;');
//   $db->exec('DROP TABLE IF EXISTS stock_sync_history;');
//   $db->exec('DROP TABLE IF EXISTS stock_sync_rules;');
//   $db->exec('DROP TABLE IF EXISTS stock_sync_settings;');
 *
 * ATENÇÃO: Isso apaga dados permanentemente. Faça backup antes.
 */
