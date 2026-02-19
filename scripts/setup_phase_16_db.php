<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

$pdo = \App\Database::getInstance();

echo "Creating competitor history table...\n";

$sql = "
CREATE TABLE IF NOT EXISTS competitor_price_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    competitor_item_id INT NOT NULL,     -- Internal ID from competitor_items
    price DECIMAL(10, 2) NOT NULL,
    recorded_at DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (competitor_item_id) REFERENCES competitor_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_daily (competitor_item_id, recorded_at),
    INDEX idx_date (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql);
    echo "Table 'competitor_price_history' created successfully.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
    exit(1);
}
