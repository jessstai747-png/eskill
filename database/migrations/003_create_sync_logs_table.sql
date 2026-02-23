-- Tabela de logs de sincronização
CREATE TABLE IF NOT EXISTS sync_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ml_account_id INT NOT NULL,
    sync_type VARCHAR(50) NOT NULL,
    status ENUM('success', 'error', 'pending') DEFAULT 'pending',
    message TEXT NULL,
    data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ml_account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE,
    INDEX idx_account_id (ml_account_id),
    INDEX idx_sync_type (sync_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

