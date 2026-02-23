-- Migration: Criar tabela de items (anúncios)
-- Data: 2024-12-15

CREATE TABLE IF NOT EXISTS items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ml_item_id VARCHAR(50) NOT NULL UNIQUE,
    account_id INT NOT NULL,
    title VARCHAR(500) NOT NULL,
    category_id VARCHAR(50),
    price DECIMAL(10, 2),
    currency_id VARCHAR(10) DEFAULT 'BRL',
    available_quantity INT DEFAULT 0,
    status ENUM('active', 'paused', 'closed', 'unknown') DEFAULT 'unknown',
    condition_type VARCHAR(50),
    catalog_product_id VARCHAR(50) NULL,
    data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_account_id (account_id),
    INDEX idx_category_id (category_id),
    INDEX idx_status (status),
    INDEX idx_catalog_product_id (catalog_product_id),
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
