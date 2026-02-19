<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;

try {
    $db = Database::getInstance();
    
    echo "Creating Messaging tables...\n";
    
    // Message Templates
    $db->exec("
        CREATE TABLE IF NOT EXISTS message_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            name VARCHAR(100) NOT NULL COMMENT 'Internal name for template',
            event_trigger VARCHAR(50) NOT NULL COMMENT 'paid, shipped, delivered, manual',
            content TEXT NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE,
            INDEX idx_account_trigger (account_id, event_trigger)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Created 'message_templates' table.\n";

    // Message Logs (to prevent duplicate sending)
    $db->exec("
        CREATE TABLE IF NOT EXISTS message_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ml_order_id VARCHAR(50) NOT NULL,
            template_id INT,
            event_trigger VARCHAR(50) NOT NULL,
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'success',
            error_message TEXT,
            FOREIGN KEY (template_id) REFERENCES message_templates(id) ON DELETE SET NULL,
            INDEX idx_order_trigger (ml_order_id, event_trigger)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Created 'message_logs' table.\n";
    
    echo "Database setup completed.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
