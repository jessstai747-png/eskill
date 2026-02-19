<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

try {
    $db = App\Database::getInstance();

    echo "Adding dashboard_preferences column to users table...\n";

    // Check if column exists
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'dashboard_preferences'");
    if ($stmt->rowCount() == 0) {
        $sql = "ALTER TABLE users ADD COLUMN dashboard_preferences JSON NULL AFTER two_factor_enabled";
        $db->exec($sql);
        echo "Column added successfully.\n";
    } else {
        echo "Column already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
