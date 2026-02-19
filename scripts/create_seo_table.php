<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/vendor/autoload.php';
if (file_exists(ROOT_PATH . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
    $dotenv->load();
}
require_once ROOT_PATH . '/app/Database.php';

try {
    $db = App\Database::getInstance();
    $sql = "CREATE TABLE IF NOT EXISTS seo_killer_settings (
        account_id INT NOT NULL,
        settings LONGTEXT,
        updated_at DATETIME,
        PRIMARY KEY (account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $db->exec($sql);
    echo "Table seo_killer_settings created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
