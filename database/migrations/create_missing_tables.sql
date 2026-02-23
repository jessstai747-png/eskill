-- Create missing settlements table
CREATE TABLE IF NOT EXISTS settlements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    file_name VARCHAR(255),
    file_path VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,
    total_amount DECIMAL(15,2) DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'BRL',
    notes TEXT,
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE,
    INDEX idx_account (account_id),
    INDEX idx_processed (processed),
    INDEX idx_uploaded (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create missing ai_optimization_logs table
CREATE TABLE IF NOT EXISTS ai_optimization_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id VARCHAR(50) NOT NULL,
    optimization_type VARCHAR(50) NOT NULL,
    field_name VARCHAR(50),
    before_data TEXT,
    after_data TEXT,
    ai_model VARCHAR(50),
    tokens_used INT DEFAULT 0,
    cost_usd DECIMAL(10,4) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'pending',
    error_message TEXT,
    user_id INT NOT NULL,
    account_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE SET NULL,
    INDEX idx_item (item_id),
    INDEX idx_type (optimization_type),
    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
