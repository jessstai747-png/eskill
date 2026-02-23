-- AI A/B Testing Tables
-- Statistical A/B testing for optimizations

CREATE TABLE IF NOT EXISTS seo_ab_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    test_name VARCHAR(255) NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    type ENUM('title', 'price', 'picture') NOT NULL,
    variant_a_data JSON NOT NULL COMMENT 'Original/Control variant',
    variant_b_data JSON NOT NULL COMMENT 'Optimized variant',
    status ENUM('active', 'running', 'paused', 'completed', 'stopped') DEFAULT 'active',
    winner VARCHAR(1) NULL COMMENT 'a or b',
    confidence_level INT DEFAULT 95,
    is_significant BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    start_date TIMESTAMP NULL,
    end_date TIMESTAMP NULL,
    duration_days INT DEFAULT 14,
    winner_variant ENUM('A', 'B') NULL,
    confidence_score DECIMAL(5,2) DEFAULT 0,
    auto_apply_winner BOOLEAN DEFAULT TRUE,
    
    INDEX idx_item_id (item_id),
    INDEX idx_account (account_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='A/B test definitions';

CREATE TABLE IF NOT EXISTS seo_ab_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    variant VARCHAR(1) NOT NULL COMMENT 'a or b',
    date DATE NOT NULL,
    views INT DEFAULT 0,
    visits INT DEFAULT 0,
    sales INT DEFAULT 0,
    revenue DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (test_id) REFERENCES ai_ab_tests(id) ON DELETE CASCADE,
    UNIQUE KEY unique_test_variant_date (test_id, variant, date),
    INDEX idx_test_id (test_id),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='A/B test metrics tracking';
