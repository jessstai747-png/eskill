<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;

try {
    $db = Database::getInstance();
    
    echo "Updating 'items' table for Dynamic Pricing...\n";
    
    // Add columns if they don't exist
    $columns = [
        "ADD COLUMN pricing_strategy VARCHAR(50) DEFAULT NULL AFTER tax_rate",
        "ADD COLUMN min_price DECIMAL(10,2) DEFAULT NULL AFTER pricing_strategy",
        "ADD COLUMN max_price DECIMAL(10,2) DEFAULT NULL AFTER min_price",
        "ADD COLUMN auto_reprice TINYINT(1) DEFAULT 0 AFTER max_price"
    ];
    
    foreach ($columns as $colSql) {
        try {
            $db->exec("ALTER TABLE items $colSql");
            echo "Executed: ALTER TABLE items $colSql\n";
        } catch (PDOException $e) {
            // Ignore if column exists (Code 42S21 or similar, but generic catch is okay for this script)
            echo "Skipped (likely exists): $colSql - " . $e->getMessage() . "\n";
        }
    }
    
    echo "Database update completed successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
