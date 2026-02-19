<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

$pdo = \App\Database::getInstance();

echo "Creating financial_settlements table...\n";

$sql = "
CREATE TABLE IF NOT EXISTS financial_settlements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL DEFAULT 1,
    ml_record_id VARCHAR(50) UNIQUE, -- ID único da transação no ML
    order_id VARCHAR(50) NULL,       -- ID do pedido (se aplicável)
    external_reference VARCHAR(100) NULL,
    date_released DATETIME NOT NULL,
    description VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,       -- sale, refund, shipping, tax
    gross_amount DECIMAL(10, 2) NOT NULL,
    net_amount DECIMAL(10, 2) NOT NULL,
    balance DECIMAL(10, 2) NULL,
    status ENUM('PENDING', 'CONCILIATED', 'MISMATCH', 'Ignored') DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    INDEX idx_date (date_released),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql);
    echo "Table 'financial_settlements' created successfully.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
    exit(1);
}
