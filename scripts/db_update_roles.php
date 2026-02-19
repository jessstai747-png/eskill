<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;

try {
    $db = Database::getInstance();
    
    echo "Adding role column to users table...\n";
    
    // Check if column exists
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN role ENUM('admin', 'manager', 'support') DEFAULT 'admin' AFTER email");
        echo "Column 'role' added successfully.\n";
    } else {
        echo "Column 'role' already exists.\n";
    }
    
    // Create admin user if not exists (seed)
    // This is just a safety measure for dev
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
