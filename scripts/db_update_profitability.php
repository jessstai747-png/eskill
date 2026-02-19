<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use App\Database;

echo "\n📦 Updating Database Schema for Profitability...\n";

try {
    $db = Database::getInstance();
    
    // Check columns in 'items' table
    $columns = $db->query("DESCRIBE items")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('cost_price', $columns)) {
        echo "➕ Adding 'cost_price' column...\n";
        $db->exec("ALTER TABLE items ADD COLUMN cost_price DECIMAL(10,2) DEFAULT NULL AFTER price");
    } else {
        echo "✅ 'cost_price' already exists.\n";
    }

    if (!in_array('tax_rate', $columns)) {
        echo "➕ Adding 'tax_rate' column...\n";
        $db->exec("ALTER TABLE items ADD COLUMN tax_rate DECIMAL(5,2) DEFAULT 0.00 AFTER cost_price");
    } else {
        echo "✅ 'tax_rate' already exists.\n";
    }

    echo "🎉 Database update complete.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
