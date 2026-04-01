-- Tabela para agendamentos de clonagem
CREATE TABLE IF NOT EXISTS clone_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_account_id INT NOT NULL,
    target_account_id INT NOT NULL,
    source_account_name VARCHAR(100),
    target_account_name VARCHAR(100),
    scheduled_datetime DATETIME NOT NULL,
    frequency ENUM('once', 'daily', 'weekly', 'monthly') DEFAULT 'once',
    filters TEXT,
    status ENUM('active', 'completed', 'canceled', 'error') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    executed_at TIMESTAMP NULL,
    INDEX idx_scheduled_datetime (scheduled_datetime),
    INDEX idx_status (status),
    INDEX idx_source_account (source_account_id),
    INDEX idx_target_account (target_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;