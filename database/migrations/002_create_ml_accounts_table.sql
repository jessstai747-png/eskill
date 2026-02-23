-- Tabela de contas do Mercado Livre vinculadas
CREATE TABLE IF NOT EXISTS ml_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    ml_user_id VARCHAR(50) NOT NULL,
    nickname VARCHAR(100) NOT NULL,
    email VARCHAR(255) NULL,
    site_id VARCHAR(10) DEFAULT 'MLB',
    access_token TEXT NOT NULL,
    refresh_token TEXT NOT NULL,
    token_expires_at DATETIME NOT NULL,
    last_synced_at DATETIME NULL,
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_ml_user (ml_user_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_token_expires (token_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

