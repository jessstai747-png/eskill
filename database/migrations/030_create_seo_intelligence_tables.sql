-- Migration: Create SEO Intelligence Module Tables
-- Date: 2026-01-02
-- Description: Comprehensive schema for SEO auditing, competitor analysis, hidden attributes, and optimization tracking

-- ============================================
-- 1. SEO Audits Table
-- ============================================
CREATE TABLE IF NOT EXISTS seo_audits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id VARCHAR(50) NOT NULL,
    account_id INT NOT NULL,
    audit_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Overall Scores
    overall_score TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Overall SEO score 0-100',
    title_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    description_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    attributes_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    images_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    pricing_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    category_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    
    -- Completeness Metrics
    required_attributes_pct TINYINT UNSIGNED DEFAULT 0 COMMENT 'Percentage of required attributes filled',
    optional_attributes_pct TINYINT UNSIGNED DEFAULT 0 COMMENT 'Percentage of optional attributes filled',
    hidden_attributes_pct TINYINT UNSIGNED DEFAULT 0 COMMENT 'Percentage of hidden attributes filled',
    
    -- Recommendations (JSON array)
    recommendations JSON COMMENT 'Array of recommendation objects with type, priority, message, impact',
    
    -- Metadata
    processing_time_ms INT UNSIGNED COMMENT 'Time taken to complete audit in milliseconds',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_item_id (item_id),
    INDEX idx_account_id (account_id),
    INDEX idx_audit_date (audit_date),
    INDEX idx_overall_score (overall_score),
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores SEO audit results for listings';

-- ============================================
-- 2. SEO Hidden Attributes Table
-- ============================================
CREATE TABLE IF NOT EXISTS seo_hidden_attributes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id VARCHAR(50) NOT NULL,
    account_id INT NOT NULL,
    category_id VARCHAR(50) NOT NULL,
    
    -- Attribute Details
    attribute_id VARCHAR(100) NOT NULL COMMENT 'ML attribute ID',
    attribute_name VARCHAR(255) NOT NULL COMMENT 'Human-readable attribute name',
    attribute_type VARCHAR(50) COMMENT 'string, number, boolean, list, etc.',
    
    -- Detection Metrics
    frequency TINYINT UNSIGNED NOT NULL COMMENT 'Percentage frequency in top competitors (0-100)',
    competitor_count INT UNSIGNED NOT NULL COMMENT 'Number of competitors analyzed',
    impact_level ENUM('high', 'medium', 'low') NOT NULL DEFAULT 'medium',
    
    -- Suggested Values
    suggested_values JSON COMMENT 'Array of suggested values from competitors',
    value_distribution JSON COMMENT 'Frequency distribution of values',
    
    -- Safety Flags
    requires_validation BOOLEAN DEFAULT TRUE COMMENT 'Whether human validation is required',
    is_technical BOOLEAN DEFAULT FALSE COMMENT 'Whether this is a technical specification',
    
    -- Status
    status ENUM('detected', 'applied', 'rejected', 'pending') DEFAULT 'detected',
    applied_value VARCHAR(500) COMMENT 'Value that was applied (if any)',
    applied_at TIMESTAMP NULL,
    applied_by INT COMMENT 'User ID who applied the value',
    
    -- Metadata
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_item_id (item_id),
    INDEX idx_account_id (account_id),
    INDEX idx_category_id (category_id),
    INDEX idx_impact_level (impact_level),
    INDEX idx_status (status),
    INDEX idx_detected_at (detected_at),
    UNIQUE KEY unique_item_attribute (item_id, attribute_id),
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks hidden attributes detected from competitor analysis';

-- ============================================
-- 3. SEO Competitors Table
-- ============================================
CREATE TABLE IF NOT EXISTS seo_competitors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id VARCHAR(50) NOT NULL COMMENT 'Our item ID',
    competitor_item_id VARCHAR(50) NOT NULL COMMENT 'Competitor item ID',
    account_id INT NOT NULL,
    
    -- Competitor Data
    title VARCHAR(500),
    price DECIMAL(10, 2),
    currency_id VARCHAR(10) DEFAULT 'BRL',
    condition_type VARCHAR(50),
    sold_quantity INT UNSIGNED DEFAULT 0,
    available_quantity INT UNSIGNED DEFAULT 0,
    
    -- Quality Metrics
    image_count TINYINT UNSIGNED DEFAULT 0,
    attribute_count TINYINT UNSIGNED DEFAULT 0,
    has_free_shipping BOOLEAN DEFAULT FALSE,
    listing_type VARCHAR(50) COMMENT 'gold_special, gold_pro, free, etc.',
    
    -- Relevance
    relevance_score DECIMAL(5, 2) COMMENT 'Similarity score to our item',
    rank_position INT UNSIGNED COMMENT 'Position in search results',
    
    -- Full Data Snapshot
    data JSON COMMENT 'Complete item data from ML API',
    
    -- Metadata
    discovered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether competitor is still active',
    
    INDEX idx_item_id (item_id),
    INDEX idx_competitor_item_id (competitor_item_id),
    INDEX idx_account_id (account_id),
    INDEX idx_relevance_score (relevance_score),
    INDEX idx_discovered_at (discovered_at),
    INDEX idx_is_active (is_active),
    UNIQUE KEY unique_item_competitor (item_id, competitor_item_id),
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores competitor data for benchmarking';

-- ============================================
-- 4. SEO Optimization History Table
-- ============================================
CREATE TABLE IF NOT EXISTS seo_optimization_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id VARCHAR(50) NOT NULL,
    account_id INT NOT NULL,
    version INT UNSIGNED NOT NULL COMMENT 'Version number for this item',
    
    -- Change Details
    change_type ENUM('title', 'description', 'attributes', 'images', 'price', 'category', 'bulk') NOT NULL,
    changed_by ENUM('user', 'ai', 'automation') NOT NULL,
    user_id INT COMMENT 'User who made the change (if applicable)',
    
    -- Before/After State
    before_data JSON NOT NULL COMMENT 'State before change',
    after_data JSON NOT NULL COMMENT 'State after change',
    diff TEXT COMMENT 'Human-readable diff description',
    
    -- Impact Tracking
    estimated_impact VARCHAR(255) COMMENT 'Estimated impact description',
    actual_impact JSON COMMENT 'Measured impact after change (visits, conversions, etc.)',
    
    -- Rollback Info
    can_rollback BOOLEAN DEFAULT TRUE,
    rolled_back BOOLEAN DEFAULT FALSE,
    rolled_back_at TIMESTAMP NULL,
    rollback_reason TEXT,
    
    -- Metadata
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    snapshot_path VARCHAR(500) COMMENT 'Path to full JSON snapshot file',
    
    INDEX idx_item_id (item_id),
    INDEX idx_account_id (account_id),
    INDEX idx_version (version),
    INDEX idx_change_type (change_type),
    INDEX idx_changed_by (changed_by),
    INDEX idx_applied_at (applied_at),
    INDEX idx_can_rollback (can_rollback),
    UNIQUE KEY unique_item_version (item_id, version),
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Version control and rollback for all optimizations';

-- ============================================
-- 5. SEO Optimization Queue Table
-- ============================================
CREATE TABLE IF NOT EXISTS seo_optimization_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id VARCHAR(50) NOT NULL,
    account_id INT NOT NULL,
    
    -- Optimization Details
    optimization_type ENUM('title', 'description', 'attributes', 'images', 'bulk') NOT NULL,
    priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
    
    -- Proposed Changes
    current_value JSON COMMENT 'Current state',
    proposed_value JSON COMMENT 'Proposed new state',
    diff TEXT COMMENT 'Human-readable diff',
    
    -- AI Context
    ai_justification TEXT COMMENT 'AI explanation for the change',
    estimated_impact VARCHAR(255) COMMENT 'Estimated impact',
    confidence_score DECIMAL(3, 2) COMMENT 'AI confidence 0.00-1.00',
    
    -- Status
    status ENUM('pending', 'approved', 'rejected', 'applied', 'failed') DEFAULT 'pending',
    reviewed_by INT COMMENT 'User ID who reviewed',
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT,
    
    -- Application
    applied_at TIMESTAMP NULL,
    error_message TEXT COMMENT 'Error if application failed',
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP COMMENT 'When this suggestion expires',
    
    INDEX idx_item_id (item_id),
    INDEX idx_account_id (account_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_created_at (created_at),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Queue of pending optimizations for review/approval';

-- ============================================
-- 6. SEO Automation Config Table
-- ============================================
CREATE TABLE IF NOT EXISTS seo_automation_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL UNIQUE,
    
    -- Automation Mode
    mode ENUM('manual', 'semi_automatic', 'automatic') DEFAULT 'manual',
    enabled BOOLEAN DEFAULT FALSE,
    
    -- Safety Limits
    max_changes_per_item_per_day TINYINT UNSIGNED DEFAULT 10,
    max_changes_per_account_per_day SMALLINT UNSIGNED DEFAULT 100,
    cooldown_hours TINYINT UNSIGNED DEFAULT 24 COMMENT 'Hours between changes to same item',
    
    -- Auto-Apply Rules
    auto_apply_title BOOLEAN DEFAULT FALSE,
    auto_apply_description BOOLEAN DEFAULT FALSE,
    auto_apply_attributes BOOLEAN DEFAULT FALSE,
    auto_apply_images BOOLEAN DEFAULT FALSE,
    
    -- Thresholds
    min_confidence_score DECIMAL(3, 2) DEFAULT 0.80 COMMENT 'Minimum AI confidence for auto-apply',
    min_seo_score_for_auto TINYINT UNSIGNED DEFAULT 50 COMMENT 'Only auto-optimize items below this score',
    
    -- Notifications
    notify_on_apply BOOLEAN DEFAULT TRUE,
    notify_on_error BOOLEAN DEFAULT TRUE,
    notification_email VARCHAR(255),
    
    -- Schedule
    audit_schedule VARCHAR(50) DEFAULT 'daily' COMMENT 'daily, weekly, manual',
    audit_time TIME DEFAULT '02:00:00' COMMENT 'Preferred time for audits',
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_audit_at TIMESTAMP NULL,
    
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Automation configuration per account';

-- ============================================
-- 7. SEO Performance Metrics Table
-- ============================================
CREATE TABLE IF NOT EXISTS seo_performance_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id VARCHAR(50) NOT NULL,
    account_id INT NOT NULL,
    metric_date DATE NOT NULL,
    
    -- Traffic Metrics
    visits INT UNSIGNED DEFAULT 0,
    unique_visitors INT UNSIGNED DEFAULT 0,
    
    -- Conversion Metrics
    questions INT UNSIGNED DEFAULT 0,
    sales INT UNSIGNED DEFAULT 0,
    conversion_rate DECIMAL(5, 2) COMMENT 'Percentage',
    
    -- Revenue Metrics
    revenue DECIMAL(10, 2) DEFAULT 0.00,
    
    -- SEO Metrics
    seo_score TINYINT UNSIGNED COMMENT 'SEO score on this date',
    search_rank INT UNSIGNED COMMENT 'Average search ranking position',
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_item_id (item_id),
    INDEX idx_account_id (account_id),
    INDEX idx_metric_date (metric_date),
    UNIQUE KEY unique_item_date (item_id, metric_date),
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily performance metrics for ROI tracking';


-- ============================================
-- 8. SEO Change Limits Tracking Table
-- ============================================
CREATE TABLE IF NOT EXISTS seo_change_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    change_date DATE NOT NULL,
    change_count TINYINT UNSIGNED DEFAULT 0,
    last_change_at TIMESTAMP NULL,
    
    INDEX idx_account_id (account_id),
    INDEX idx_item_id (item_id),
    INDEX idx_change_date (change_date),
    UNIQUE KEY unique_item_date (item_id, change_date),
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks daily change counts for rate limiting';

-- ============================================
-- Insert Default Automation Config for Existing Accounts
-- ============================================
INSERT INTO seo_automation_config (account_id, mode, enabled)
SELECT id, 'manual', FALSE
FROM ml_accounts
WHERE id NOT IN (SELECT account_id FROM seo_automation_config)
ON DUPLICATE KEY UPDATE account_id = account_id;
