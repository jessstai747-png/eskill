<?php
/**
 * Fix Items Table Schema
 * Adds missing columns for proper functionality
 */

$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_PORT'] = '3306';
$_ENV['DB_DATABASE'] = 'meli';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = 'Tr1unf0@';

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Database.php';

echo "=== FIXING ITEMS TABLE SCHEMA ===\n\n";

try {
    $db = \App\Database::getInstance();
    
    // 1. Add visits column if missing
    echo "[1/2] Adding 'visits' column...\n";
    try {
        $db->exec("ALTER TABLE items ADD COLUMN visits INT DEFAULT 0");
        echo "✓ Added 'visits' column\n";
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "⚠ Column 'visits' already exists\n";
        } else {
            throw $e;
        }
    }
    
    // 2. Add category_name column if missing
    echo "[2/2] Adding 'category_name' column...\n";
    try {
        $db->exec("ALTER TABLE items ADD COLUMN category_name VARCHAR(255) NULL AFTER category_id");
        echo "✓ Added 'category_name' column\n";
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "⚠ Column 'category_name' already exists\n";
        } else {
            throw $e;
        }
    }
    
    echo "\n✓ Schema fixes applied successfully!\n";
    
} catch (\Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
