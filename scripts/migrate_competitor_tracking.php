<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;

try {
    $db = Database::getInstance();
    
    echo "Creating competitor_tracking table...\n";
    
    $sql = "
    CREATE TABLE IF NOT EXISTS competitor_tracking (
        id INT AUTO_INCREMENT PRIMARY KEY,
        my_item_id VARCHAR(50) NOT NULL,
        competitor_item_id VARCHAR(50) NOT NULL,
        account_id INT NOT NULL,
        competitor_price DECIMAL(10,2),
        competitor_stock INT,
        competitor_seller_id BIGINT,
        last_checked TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_tracking (my_item_id, competitor_item_id, account_id),
        INDEX idx_account (account_id),
        INDEX idx_my_item (my_item_id),
        INDEX idx_last_checked (last_checked)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $db->exec($sql);
    
    echo "✅ Competitor tracking table created successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
