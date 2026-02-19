<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

try {
    $db = App\Database::getInstance();

    echo "Adding 2FA columns to users table...\n";

    $sql = "ALTER TABLE users 
            ADD COLUMN two_factor_secret VARCHAR(255) NULL AFTER email_verified_at,
            ADD COLUMN two_factor_recovery_codes TEXT NULL AFTER two_factor_secret,
            ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0 AFTER two_factor_recovery_codes";

    $db->exec($sql);

    echo "Columns added successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
