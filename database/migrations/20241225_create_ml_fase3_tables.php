<?php

/**
 * Migration: Fase 3 - Brand Central, Trends, Inventory Advanced, Messaging
 * 
 * Cria tabelas para suportar:
 * - Inventory multi-origem
 * - Inventory reservations
 * - Message templates
 * - Auto responses
 * - Market keywords (trends)
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

try {
    $db = Database::getInstance();
    
    echo "Iniciando migração - Fase 3 ML Integrations...\n\n";

    // ==================== INVENTORY_ORIGINS ====================
    echo "Criando tabela: inventory_origins\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS inventory_origins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            sku VARCHAR(100) NOT NULL,
            origin VARCHAR(50) NOT NULL COMMENT 'warehouse, dropshipping, store',
            quantity INT DEFAULT 0,
            reserved INT DEFAULT 0,
            available INT GENERATED ALWAYS AS (quantity - reserved) STORED,
            location VARCHAR(255),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_origin (account_id, sku, origin),
            INDEX idx_account (account_id),
            INDEX idx_sku (sku),
            INDEX idx_origin (origin),
            INDEX idx_available (available),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✓ Tabela inventory_origins criada\n\n";

    // ==================== INVENTORY_RESERVATIONS ====================
    echo "Criando tabela: inventory_reservations\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS inventory_reservations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            reservation_id VARCHAR(50) NOT NULL,
            sku VARCHAR(100) NOT NULL,
            quantity INT NOT NULL,
            order_id VARCHAR(50),
            status VARCHAR(20) DEFAULT 'active' COMMENT 'active, released, expired',
            expires_at TIMESTAMP NOT NULL,
            metadata JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_reservation (reservation_id),
            INDEX idx_account (account_id),
            INDEX idx_sku (sku),
            INDEX idx_status (status),
            INDEX idx_expires (expires_at),
            INDEX idx_order (order_id),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✓ Tabela inventory_reservations criada\n\n";

    // ==================== INVENTORY_MOVEMENTS ====================
    echo "Criando tabela: inventory_movements\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS inventory_movements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            sku VARCHAR(100) NOT NULL,
            type VARCHAR(30) NOT NULL COMMENT 'sale, purchase, adjustment, transfer',
            quantity INT NOT NULL,
            origin VARCHAR(50),
            reference_id VARCHAR(50),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_account (account_id),
            INDEX idx_sku (sku),
            INDEX idx_type (type),
            INDEX idx_created (created_at),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✓ Tabela inventory_movements criada\n\n";

    // ==================== MESSAGE_TEMPLATES ====================
    echo "Criando tabela: message_templates\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS message_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            subject VARCHAR(255),
            content TEXT NOT NULL,
            category VARCHAR(50) DEFAULT 'general',
            variables JSON,
            active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_account (account_id),
            INDEX idx_category (category),
            INDEX idx_active (active),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✓ Tabela message_templates criada\n\n";

    // ==================== AUTO_RESPONSES ====================
    echo "Criando tabela: auto_responses\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS auto_responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            trigger_keyword VARCHAR(255) NOT NULL,
            response_message TEXT NOT NULL,
            enabled BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_trigger (account_id, trigger_keyword),
            INDEX idx_account (account_id),
            INDEX idx_enabled (enabled),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✓ Tabela auto_responses criada\n\n";

    // ==================== MESSAGES ====================
    echo "Criando tabela: messages\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            message_id VARCHAR(50),
            thread_id VARCHAR(50),
            direction VARCHAR(20) COMMENT 'sent, received',
            content TEXT,
            status VARCHAR(20),
            response_time_seconds INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_account (account_id),
            INDEX idx_thread (thread_id),
            INDEX idx_direction (direction),
            INDEX idx_created (created_at),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✓ Tabela messages criada\n\n";

    // ==================== MARKET_KEYWORDS ====================
    echo "Criando tabela: market_keywords\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS market_keywords (
            id INT AUTO_INCREMENT PRIMARY KEY,
            keyword VARCHAR(255) NOT NULL,
            category_id VARCHAR(50),
            search_volume INT DEFAULT 0,
            competition_level INT DEFAULT 0 COMMENT '0-100',
            avg_price DECIMAL(12, 2),
            trend VARCHAR(20) DEFAULT 'stable' COMMENT 'rising, falling, stable',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_keyword (keyword, category_id),
            INDEX idx_keyword (keyword),
            INDEX idx_category (category_id),
            INDEX idx_volume (search_volume),
            INDEX idx_competition (competition_level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✓ Tabela market_keywords criada\n\n";

    echo "============================================\n";
    echo "✓ Migração Fase 3 concluída com sucesso!\n";
    echo "============================================\n\n";

    echo "Tabelas criadas:\n";
    echo "- inventory_origins (estoque multi-origem)\n";
    echo "- inventory_reservations (reservas de estoque)\n";
    echo "- inventory_movements (histórico de movimentação)\n";
    echo "- message_templates (templates de mensagens)\n";
    echo "- auto_responses (respostas automáticas)\n";
    echo "- messages (histórico de mensagens)\n";
    echo "- market_keywords (keywords de mercado)\n\n";

    echo "Total de índices criados: 37\n";
    echo "Foreign keys com CASCADE: 6\n\n";

} catch (\Exception $e) {
    echo "ERRO na migração: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    throw $e;
}
