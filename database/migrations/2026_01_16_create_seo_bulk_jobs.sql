CREATE TABLE IF NOT EXISTS seo_bulk_jobs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    job_type ENUM('full', 'title', 'description', 'attributes') DEFAULT 'full',
    status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
    total_items INT DEFAULT 0,
    processed_items INT DEFAULT 0,
    successful_items INT DEFAULT 0,
    failed_items INT DEFAULT 0,
    item_ids JSON,
    results JSON,
    options JSON,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account (account_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
