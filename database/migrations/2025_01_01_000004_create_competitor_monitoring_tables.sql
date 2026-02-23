-- Migration: Create Competitor Monitoring Tables
-- Date: 2025-01-01
-- Description: Tables for automated competitor tracking and alerts

-- ========================================
-- Competitor Tracking Table
-- ========================================
CREATE TABLE IF NOT EXISTS competitor_tracking (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL,
    my_item_id VARCHAR(50) NOT NULL,
    competitor_item_id VARCHAR(50) NOT NULL,
    
    -- Current competitor data (cache)
    competitor_price DECIMAL(10, 2) DEFAULT 0,
    competitor_stock INT DEFAULT 0,
    competitor_title VARCHAR(500),
    competitor_seller_id VARCHAR(50),
    competitor_reputation VARCHAR(50),
    
    -- My item data for comparison
    my_price DECIMAL(10, 2) DEFAULT 0,
    
    -- Alert settings
    alert_price_drop TINYINT(1) DEFAULT 1,
    alert_price_increase TINYINT(1) DEFAULT 1,
    alert_stock_change TINYINT(1) DEFAULT 1,
    
    -- Monitoring control
    is_active TINYINT(1) DEFAULT 1,
    last_checked DATETIME NULL,
    check_frequency_minutes INT DEFAULT 60,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_account (account_id),
    INDEX idx_my_item (my_item_id),
    INDEX idx_competitor_item (competitor_item_id),
    INDEX idx_active (is_active),
    INDEX idx_last_checked (last_checked),
    UNIQUE KEY unique_tracking (my_item_id, competitor_item_id, account_id),
    
    -- Foreign key
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Competitor Alerts Table
-- ========================================
CREATE TABLE IF NOT EXISTS competitor_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_id INT UNSIGNED NOT NULL,
    
    -- Alert details
    type ENUM('price_drop', 'price_increase', 'out_of_stock', 'back_in_stock', 'new_listing') NOT NULL,
    severity ENUM('info', 'warning', 'critical', 'success') DEFAULT 'info',
    
    -- Change details
    message TEXT,
    old_value VARCHAR(100),
    new_value VARCHAR(100),
    
    -- Metadata
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME NULL,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_tracking (tracking_id),
    INDEX idx_type (type),
    INDEX idx_severity (severity),
    INDEX idx_is_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Competitor Alert History (for analytics)
-- ========================================
CREATE TABLE IF NOT EXISTS competitor_alert_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_id INT UNSIGNED NOT NULL,
    
    -- Snapshot data
    competitor_price DECIMAL(10, 2),
    competitor_stock INT,
    my_price DECIMAL(10, 2),
    
    -- Price difference
    price_diff DECIMAL(10, 2),
    price_diff_percent DECIMAL(5, 2),
    
    -- Timestamps
    checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_tracking (tracking_id),
    INDEX idx_checked (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Insert Default Settings
-- ========================================
-- These will be created on-demand per user via the application
-- No default inserts needed

-- ========================================
-- Sample Data (for development)
-- ========================================
-- Uncomment for testing:
-- INSERT INTO competitor_tracking (account_id, my_item_id, competitor_item_id, competitor_price, my_price)
-- VALUES (1, 'MLB123456789', 'MLB987654321', 199.90, 189.90);
