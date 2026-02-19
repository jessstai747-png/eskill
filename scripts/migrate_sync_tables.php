<?php
/**
 * Migration: Criar tabelas para sincronização automática
 * 
 * Tabelas:
 * - sync_status: Controla status de sincronização por conta e recurso
 * - ml_items: Cache local de anúncios do Mercado Livre
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

try {
    $db = Database::getInstance();
    echo "Iniciando migrations...\n\n";
    
    // 1. Tabela sync_status
    echo "Criando tabela sync_status...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS sync_status (
            id INT AUTO_INCREMENT PRIMARY KEY,
            resource_type VARCHAR(50) NOT NULL COMMENT 'orders, items, questions',
            account_id INT NOT NULL,
            last_sync_at DATETIME NULL,
            status ENUM('success', 'error', 'running') DEFAULT 'success',
            last_sync_id VARCHAR(100) NULL COMMENT 'Último ID sincronizado',
            items_count INT NULL COMMENT 'Total de itens',
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_resource_account (resource_type, account_id),
            KEY idx_account (account_id),
            KEY idx_status (status),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Controle de sincronizações automáticas'
    ");
    echo "✅ Tabela sync_status criada\n\n";
    
    // 2. Tabela ml_items
    echo "Criando tabela ml_items...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS ml_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ml_item_id VARCHAR(50) NOT NULL UNIQUE,
            ml_account_id INT NOT NULL,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            category_id VARCHAR(50) NULL,
            category_name VARCHAR(255) NULL,
            price DECIMAL(10,2) NOT NULL,
            available_quantity INT NOT NULL DEFAULT 0,
            sold_quantity INT NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL COMMENT 'active, paused, closed',
            listing_type VARCHAR(50) NULL COMMENT 'gold_special, gold_pro, free',
            permalink VARCHAR(500) NULL,
            thumbnail VARCHAR(500) NULL,
            condition VARCHAR(20) NULL COMMENT 'new, used',
            pictures_count INT DEFAULT 0,
            accepts_mercadopago BOOLEAN DEFAULT 1,
            shipping_mode VARCHAR(50) NULL COMMENT 'me2, not_specified',
            shipping_free BOOLEAN DEFAULT 0,
            date_created DATETIME NULL,
            last_updated DATETIME NULL,
            item_data JSON NULL COMMENT 'Dados completos do item',
            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_account (ml_account_id),
            KEY idx_user (user_id),
            KEY idx_status (status),
            KEY idx_category (category_id),
            KEY idx_created (date_created),
            KEY idx_synced (synced_at),
            KEY idx_stock (available_quantity),
            FOREIGN KEY (ml_account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Cache local de anúncios do Mercado Livre'
    ");
    echo "✅ Tabela ml_items criada\n\n";
    
    // 3. Adicionar índices de performance na ml_orders (se não existir)
    echo "Adicionando índices de performance...\n";
    try {
        $db->exec("ALTER TABLE ml_orders ADD INDEX idx_account_date (ml_account_id, date_created)");
        echo "✅ Índice idx_account_date adicionado em ml_orders\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "ℹ️  Índice idx_account_date já existe\n";
        } else {
            throw $e;
        }
    }
    
    try {
        $db->exec("ALTER TABLE ml_orders ADD INDEX idx_synced (synced_at)");
        echo "✅ Índice idx_synced adicionado em ml_orders\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "ℹ️  Índice idx_synced já existe\n";
        } else {
            throw $e;
        }
    }
    
    echo "\n✅ Todas as migrations foram executadas com sucesso!\n";
    
} catch (Exception $e) {
    echo "\n❌ Erro ao executar migrations: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
