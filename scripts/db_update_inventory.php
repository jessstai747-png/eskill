<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;

try {
    $db = Database::getInstance();
    
    echo "Updating 'items' table for Inventory Management...\n";
    
    // Add columns if they don't exist
    $columns = [
        "ADD COLUMN sku VARCHAR(100) DEFAULT NULL AFTER pricing_strategy",
        "ADD INDEX idx_sku (sku)"
    ];
    
    foreach ($columns as $colSql) {
        try {
            $db->exec("ALTER TABLE items $colSql");
            echo "Executed: ALTER TABLE items $colSql\n";
        } catch (PDOException $e) {
            // Ignore if column/index exists
            echo "Skipped (likely exists): $colSql - " . $e->getMessage() . "\n";
        }
    }
    
    echo "Database update completed successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
