-- Pricing Intelligence Phase 3 - Advanced Features
-- Migrations for Rules Engine, Scheduling, Analytics, Bulk Editor, and Notifications

-- =====================================================
-- PRICING RULES ENGINE
-- =====================================================

CREATE TABLE IF NOT EXISTS pricing_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    rule_type ENUM('match_competitor', 'floor_ceiling', 'time_based', 'margin_based', 'stock_based', 'velocity_based', 'category_position') NOT NULL,
    priority INT DEFAULT 100,
    config JSON NOT NULL COMMENT 'Rule configuration based on type',
    items JSON NULL COMMENT 'Item IDs to apply rule (null = all items)',
    categories JSON NULL COMMENT 'Category IDs to apply rule',
    is_active TINYINT(1) DEFAULT 1,
    last_executed_at DATETIME NULL,
    execution_count INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_account_active (account_id, is_active),
    INDEX idx_rule_type (rule_type),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pricing_rule_executions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT NOT NULL,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    old_price DECIMAL(12,2) NOT NULL,
    new_price DECIMAL(12,2) NOT NULL,
    change_percent DECIMAL(8,4) NOT NULL,
    applied TINYINT(1) DEFAULT 0,
    skipped_reason VARCHAR(255) NULL,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rule_id (rule_id),
    INDEX idx_account_item (account_id, item_id),
    INDEX idx_executed_at (executed_at),
    FOREIGN KEY (rule_id) REFERENCES pricing_rules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- SCHEDULED PRICES
-- =====================================================

CREATE TABLE IF NOT EXISTS pricing_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    schedule_type ENUM('single', 'recurrent') DEFAULT 'single',
    recurrence_pattern ENUM('daily', 'weekly', 'monthly') NULL,
    recurrence_config JSON NULL COMMENT 'days_of_week, day_of_month, etc',
    scheduled_price DECIMAL(12,2) NOT NULL,
    original_price DECIMAL(12,2) NULL,
    scheduled_at DATETIME NOT NULL,
    ends_at DATETIME NULL COMMENT 'For recurrent schedules',
    rollback_price DECIMAL(12,2) NULL,
    rollback_at DATETIME NULL,
    status ENUM('pending', 'executed', 'cancelled', 'failed', 'rolled_back') DEFAULT 'pending',
    campaign_id INT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    executed_at DATETIME NULL,
    INDEX idx_account_status (account_id, status),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_item_id (item_id),
    INDEX idx_campaign_id (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pricing_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    discount_type ENUM('percent', 'fixed') NOT NULL,
    discount_value DECIMAL(12,2) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    rollback_enabled TINYINT(1) DEFAULT 1,
    status ENUM('draft', 'scheduled', 'active', 'completed', 'cancelled') DEFAULT 'draft',
    items JSON NOT NULL COMMENT 'Array of item_ids with original prices',
    total_items INT DEFAULT 0,
    executed_items INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_account_status (account_id, status),
    INDEX idx_date_range (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- BULK PRICE EDITOR
-- =====================================================

CREATE TABLE IF NOT EXISTS pricing_bulk_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    operation_type ENUM('percent_increase', 'percent_decrease', 'fixed_increase', 'fixed_decrease', 'set_price', 'match_competitor', 'set_margin', 'round_price') NOT NULL,
    operation_value DECIMAL(12,4) NULL COMMENT 'Value for the operation',
    items JSON NOT NULL COMMENT 'Array of item details before/after',
    total_items INT NOT NULL,
    success_count INT DEFAULT 0,
    error_count INT DEFAULT 0,
    status ENUM('preview', 'pending', 'processing', 'completed', 'failed', 'rolled_back') DEFAULT 'preview',
    rollback_data JSON NULL COMMENT 'Original prices for rollback',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    INDEX idx_account_status (account_id, status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- PRICE ANALYTICS
-- =====================================================

CREATE TABLE IF NOT EXISTS pricing_analytics_snapshots (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    competitor_min_price DECIMAL(12,2) NULL,
    competitor_avg_price DECIMAL(12,2) NULL,
    competitor_count INT DEFAULT 0,
    sales_velocity DECIMAL(10,4) NULL COMMENT 'Units per day',
    conversion_rate DECIMAL(8,4) NULL,
    position INT NULL,
    snapshot_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_item_date (account_id, item_id, snapshot_date),
    INDEX idx_account_date (account_id, snapshot_date),
    INDEX idx_item_date (item_id, snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pricing_elasticity_data (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    price_point DECIMAL(12,2) NOT NULL,
    quantity_sold INT NOT NULL,
    revenue DECIMAL(14,2) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account_item (account_id, item_id),
    INDEX idx_period (period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- NOTIFICATIONS
-- =====================================================

CREATE TABLE IF NOT EXISTS notification_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('email', 'webhook', 'push', 'slack', 'discord', 'log') NOT NULL,
    config JSON NOT NULL COMMENT 'Channel-specific configuration',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_account_type (account_id, type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    event_type ENUM('price_change', 'competitor_alert', 'margin_alert', 'rule_executed', 'schedule_executed', 'bulk_completed', 'ab_test_complete', 'optimization_suggestion') NOT NULL,
    min_severity TINYINT DEFAULT 1 COMMENT '1=info, 2=warning, 3=error, 4=critical',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_channel_event (channel_id, event_type),
    FOREIGN KEY (channel_id) REFERENCES notification_channels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    channel_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    data JSON NOT NULL,
    success TINYINT(1) NOT NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account_event (account_id, event_type),
    INDEX idx_channel_id (channel_id),
    INDEX idx_created_at (created_at),
    INDEX idx_success (success)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_notification_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    config JSON NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    data JSON NOT NULL,
    severity VARCHAR(20) NOT NULL,
    status ENUM('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    last_attempt_at DATETIME NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- ADDITIONAL INDEXES FOR PERFORMANCE
-- =====================================================

-- For frequently queried joins
ALTER TABLE pricing_schedules ADD CONSTRAINT fk_schedule_campaign 
    FOREIGN KEY (campaign_id) REFERENCES pricing_campaigns(id) ON DELETE SET NULL;

-- For analytics queries
CREATE INDEX idx_analytics_account_item_date ON pricing_analytics_snapshots(account_id, item_id, snapshot_date);

-- For rule execution lookups
CREATE INDEX idx_rule_exec_date_range ON pricing_rule_executions(account_id, executed_at);
