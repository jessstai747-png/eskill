-- Tabela de pedidos sincronizados do Mercado Livre
CREATE TABLE IF NOT EXISTS ml_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ml_order_id BIGINT UNIQUE NOT NULL,
    ml_account_id INT NOT NULL,
    order_data JSON NOT NULL,
    status VARCHAR(50) NOT NULL,
    total_amount DECIMAL(10,2) DEFAULT 0,
    date_created DATETIME NOT NULL,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ml_account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE,
    INDEX idx_account_id (ml_account_id),
    INDEX idx_status (status),
    INDEX idx_date_created (date_created),
    INDEX idx_synced_at (synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

