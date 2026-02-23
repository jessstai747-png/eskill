-- AI Performance Tracking Table
-- Track performance improvements after optimization

CREATE TABLE IF NOT EXISTS ai_performance_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id VARCHAR(50) NOT NULL,
    optimization_id INT NULL COMMENT 'Reference to audit_log id',
    date DATE NOT NULL,
    views INT DEFAULT 0,
    visits INT DEFAULT 0,
    sales INT DEFAULT 0,
    revenue DECIMAL(10, 2) DEFAULT 0.00,
    questions INT DEFAULT 0,
    favorites INT DEFAULT 0,
    position INT NULL COMMENT 'Search ranking position',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_item_date (item_id, date),
    INDEX idx_item_id (item_id),
    INDEX idx_optimization_id (optimization_id),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily performance metrics for optimized items';
