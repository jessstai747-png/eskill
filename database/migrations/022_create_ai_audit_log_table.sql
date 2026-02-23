-- AI Audit Log Table
-- Complete audit trail with rollback capability

CREATE TABLE IF NOT EXISTS ai_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL COMMENT 'optimize, rollback, apply, etc',
    changes JSON NOT NULL COMMENT 'Detailed changes made',
    metadata JSON NULL COMMENT 'Additional context',
    before_state JSON NULL COMMENT 'Complete state before change',
    after_state JSON NULL COMMENT 'Complete state after change',
    score_before INT NULL,
    score_after INT NULL,
    cost DECIMAL(10, 4) NULL,
    ai_provider VARCHAR(50) NULL,
    ai_model VARCHAR(100) NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_item_id (item_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit log for all AI optimization actions';
