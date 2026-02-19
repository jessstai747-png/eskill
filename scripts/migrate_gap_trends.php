<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;

try {
    $db = Database::getInstance();
    
    echo "Migrating gap_trend_snapshots table...\n";
    
    $sql = "
    CREATE TABLE IF NOT EXISTS gap_trend_snapshots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id VARCHAR(50) NOT NULL,
        keyword VARCHAR(255) NOT NULL,
        gap_score FLOAT NOT NULL,
        price_avg DECIMAL(10,2) DEFAULT 0.00,
        price_std_dev DECIMAL(10,2) DEFAULT 0.00,
        supply_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cat_date (category_id, created_at),
        INDEX idx_keyword (keyword)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $db->exec($sql);
    
    echo "Migration successful!\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
