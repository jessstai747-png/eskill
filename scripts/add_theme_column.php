<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

try {
    $db = App\Database::getInstance();

    echo "Adding theme column to users table...\n";

    // Check if column exists
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'theme'");
    if ($stmt->rowCount() == 0) {
        $sql = "ALTER TABLE users ADD COLUMN theme VARCHAR(20) DEFAULT 'light' AFTER dashboard_preferences";
        $db->exec($sql);
        echo "Column added successfully.\n";
    } else {
        echo "Column already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
