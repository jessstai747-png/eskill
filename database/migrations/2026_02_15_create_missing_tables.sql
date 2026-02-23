-- ============================================================================
-- Migration: Create order_items table
-- Date: 2026-02-15
-- Description: Stores individual line items from ML orders.
--              Referenced by TrendsService, FinancialService, DeepDemandPredictor
-- ============================================================================

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL COMMENT 'References ml_orders.ml_order_id',
    item_id VARCHAR(50) NOT NULL COMMENT 'ML item ID (e.g., MLB123456)',
    title VARCHAR(255) NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_price DECIMAL(10,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    category_id VARCHAR(30) NULL,
    variation_id BIGINT NULL,
    sku VARCHAR(100) NULL,
    condition_type VARCHAR(20) DEFAULT 'new' COMMENT 'new, used, refurbished',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_order_id (order_id),
    INDEX idx_item_id (item_id),
    INDEX idx_sku (sku),
    INDEX idx_category_id (category_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Migration: Create notification_settings table
-- Description: Stores per-account notification preferences.
--              Referenced by RealTimeNotificationService
-- ============================================================================

CREATE TABLE IF NOT EXISTS notification_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    user_id INT NULL,
    channel VARCHAR(30) NOT NULL DEFAULT 'web' COMMENT 'web, email, telegram, push, webhook',
    event_type VARCHAR(100) NOT NULL COMMENT 'price_change, stock_alert, order_new, etc.',
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    config JSON NULL COMMENT 'Channel-specific config (thresholds, recipients, etc.)',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_account_channel_event (account_id, channel, event_type),
    INDEX idx_account_id (account_id),
    INDEX idx_event_type (event_type),
    INDEX idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Migration: Create competitor_pricing_cache table alias
-- Description: Some services reference competitor_prices_cache (wrong name).
--              The real table is competitor_pricing_cache. Create a VIEW alias.
-- ============================================================================

-- Create view only if it doesn't exist as a table
SET @tbl_exists = (SELECT COUNT(*) FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'competitor_prices_cache');
SET @sqlstmt = IF(@tbl_exists = 0, 
    'CREATE OR REPLACE VIEW competitor_prices_cache AS SELECT * FROM competitor_pricing_cache', 
    'SELECT ''competitor_prices_cache already exists as table''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Migration: Create ml_customers table
-- Description: Stores customer/buyer data aggregated from orders.
--              Referenced by ReportService for customer reports.
-- ============================================================================

CREATE TABLE IF NOT EXISTS ml_customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    ml_buyer_id BIGINT NOT NULL COMMENT 'ML buyer ID',
    name VARCHAR(255) NULL,
    nickname VARCHAR(100) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    state VARCHAR(10) NULL COMMENT 'UF do comprador',
    city VARCHAR(100) NULL,
    total_orders INT NOT NULL DEFAULT 0,
    total_purchases DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Total gasto',
    first_purchase_at DATETIME NULL,
    last_purchase_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_account_buyer (account_id, ml_buyer_id),
    INDEX idx_account_id (account_id),
    INDEX idx_total_purchases (total_purchases),
    INDEX idx_last_purchase (last_purchase_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
