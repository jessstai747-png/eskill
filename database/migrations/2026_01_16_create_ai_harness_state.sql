CREATE TABLE IF NOT EXISTS ai_harness_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_name VARCHAR(50) NOT NULL,
    session_id VARCHAR(50) NOT NULL,
    current_feature_id INT NULL,
    status ENUM('initializing', 'idle', 'working', 'verifying', 'cleaning') DEFAULT 'initializing',
    context_size INT DEFAULT 0,
    memory_usage INT DEFAULT 0,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_heartbeat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    meta_data JSON NULL,
    INDEX idx_agent_status (agent_name, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
