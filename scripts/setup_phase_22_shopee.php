<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;

$db = Database::getInstance();

echo "Creating Shopee tables...\n";

// Auth Table
$db->exec("CREATE TABLE IF NOT EXISTS shopee_auth (
    shop_id BIGINT PRIMARY KEY,
    shop_name VARCHAR(255),
    access_token TEXT,
    refresh_token TEXT,
    token_expiry TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;");

// Items Table
$db->exec("CREATE TABLE IF NOT EXISTS shopee_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shopee_item_id BIGINT UNIQUE,
    shop_id BIGINT,
    name VARCHAR(255),
    sku VARCHAR(100),
    price DECIMAL(10,2),
    stock INT,
    status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sku (sku)
) ENGINE=InnoDB;");

// Orders Table
$db->exec("CREATE TABLE IF NOT EXISTS shopee_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shopee_order_sn VARCHAR(50) UNIQUE, -- Shopee Order ID
    shop_id BIGINT,
    status VARCHAR(50),
    total_amount DECIMAL(10,2),
    created_at DATETIME,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;");

echo "✅ Shopee tables created.\n";
