<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$db = Database::getInstance();

echo "Updating notification_settings table...\n";

// Add channel preference columns if they don't exist
$columns = [
    'email_orders' => "BOOLEAN DEFAULT TRUE",
    'email_questions' => "BOOLEAN DEFAULT TRUE",
    'whatsapp_orders' => "BOOLEAN DEFAULT FALSE",
    'whatsapp_questions' => "BOOLEAN DEFAULT FALSE",
    'whatsapp_low_stock' => "BOOLEAN DEFAULT FALSE"
];

foreach ($columns as $col => $def) {
    $stmt = $db->query("SHOW COLUMNS FROM notification_settings LIKE '$col'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE notification_settings ADD COLUMN $col $def");
        echo "Added column $col\n";
    }
}

echo "Migration complete.\n";
