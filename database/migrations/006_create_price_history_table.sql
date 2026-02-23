-- Tabela de histórico de preços
CREATE TABLE IF NOT EXISTS price_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id VARCHAR(50) NOT NULL,
    brand VARCHAR(100) NOT NULL,
    avg_price DECIMAL(10,2) NOT NULL,
    min_price DECIMAL(10,2) NOT NULL,
    max_price DECIMAL(10,2) NOT NULL,
    total_items INT DEFAULT 0,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category_brand (category_id, brand),
    INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

