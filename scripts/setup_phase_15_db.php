<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

$pdo = \App\Database::getInstance();

echo "Creating returns table...\n";

$sql = "
CREATE TABLE IF NOT EXISTS returns (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ml_order_id BIGINT NOT NULL,     -- Link to ML Order ID directly for easier lookup
    claim_id VARCHAR(50) NULL,       -- Optional link to claim
    status ENUM('WAITING_ARRIVAL', 'RECEIVED', 'CHECKING', 'RESTOCKED', 'DISCARDED', 'RETURNED_TO_BUYER') DEFAULT 'WAITING_ARRIVAL',
    condition_rating TINYINT NULL,   -- 1 (Trash) to 5 (New)
    inspection_notes TEXT NULL,
    inspector_id INT NULL,           -- User who inspected
    sku VARCHAR(50) NULL,            -- SKU of returned item
    quantity INT DEFAULT 1,
    refunded_amount DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    reentry_at DATETIME NULL,        -- When stock was added back
    
    INDEX idx_order (ml_order_id),
    INDEX idx_status (status),
    INDEX idx_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql);
    echo "Table 'returns' created successfully.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
    exit(1);
}
