-- AI Optimization Queue Table
-- Manages batch optimization processing with priority queue

CREATE TABLE IF NOT EXISTS ai_optimization_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(100) NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    options JSON NOT NULL,
    priority INT DEFAULT 5 COMMENT 'Higher number = higher priority',
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    result JSON NULL COMMENT 'Optimization results',
    error TEXT NULL,
    duration_seconds DECIMAL(10, 2) NULL,
    cost DECIMAL(10, 4) NULL COMMENT 'Cost in USD',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    
    INDEX idx_batch_id (batch_id),
    INDEX idx_item_id (item_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority, created_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Queue for AI optimization batch processing';
