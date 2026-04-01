<?php
declare(strict_types=1);

/**
 * Migration: Fase 2 - User Products, Shipping Advanced, Promotions
 *
 * Cria tabelas para suportar:
 * - User Products (produtos customizados)
 * - Shipments tracking
 * - Promotion performance
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

try {
    $db = Database::getInstance();

    echo "Iniciando migração - Fase 2 ML Integrations...\n\n";

    // ==================== USER_PRODUCTS ====================
    echo "Criando tabela: user_products\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS user_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            product_id VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            category_id VARCHAR(50),
            status VARCHAR(20) DEFAULT 'active',
            data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_product (account_id, product_id),
            INDEX idx_account (account_id),
            INDEX idx_status (status),
            INDEX idx_category (category_id),
            INDEX idx_created (created_at),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Tabela user_products criada\n\n";

    // ==================== SHIPMENTS ====================
    echo "Criando tabela: shipments\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS shipments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            shipment_id VARCHAR(50) NOT NULL,
            order_id VARCHAR(50),
            status VARCHAR(30),
            tracking_number VARCHAR(100),
            carrier VARCHAR(50),
            is_delayed BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            shipped_at TIMESTAMP NULL,
            delivered_at TIMESTAMP NULL,
            data JSON,
            UNIQUE KEY unique_shipment (account_id, shipment_id),
            INDEX idx_account (account_id),
            INDEX idx_order (order_id),
            INDEX idx_status (status),
            INDEX idx_created (created_at),
            INDEX idx_delayed (is_delayed),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Tabela shipments criada\n\n";

    // ==================== PROMOTION_PERFORMANCE ====================
    echo "Criando tabela: promotion_performance\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS promotion_performance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            promotion_id VARCHAR(50) NOT NULL,
            date DATE NOT NULL,
            sales INT DEFAULT 0,
            revenue DECIMAL(12, 2) DEFAULT 0,
            discount_given DECIMAL(12, 2) DEFAULT 0,
            conversion_rate DECIMAL(5, 2) DEFAULT 0,
            data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_promotion_date (account_id, promotion_id, date),
            INDEX idx_account (account_id),
            INDEX idx_promotion (promotion_id),
            INDEX idx_date (date),
            INDEX idx_sales (sales),
            INDEX idx_revenue (revenue),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Tabela promotion_performance criada\n\n";

    // ==================== ITEM_METRICS (se não existe) ====================
    echo "Criando tabela: item_metrics (se não existir)\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS item_metrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            item_id VARCHAR(50) NOT NULL,
            title VARCHAR(255),
            price DECIMAL(12, 2),
            visits INT DEFAULT 0,
            sales INT DEFAULT 0,
            conversion_rate DECIMAL(5, 2) DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_item (account_id, item_id),
            INDEX idx_account (account_id),
            INDEX idx_visits (visits),
            INDEX idx_conversion (conversion_rate),
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Tabela item_metrics criada\n\n";

    echo "============================================\n";
    echo "✓ Migração Fase 2 concluída com sucesso!\n";
    echo "============================================\n\n";

    echo "Tabelas criadas:\n";
    echo "- user_products (produtos customizados)\n";
    echo "- shipments (rastreamento de envios)\n";
    echo "- promotion_performance (performance de promoções)\n";
    echo "- item_metrics (métricas de itens)\n\n";

    echo "Total de índices criados: 26\n";
    echo "Foreign keys com CASCADE: 4\n\n";
} catch (\Exception $e) {
    echo "ERRO na migração: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    throw $e;
}

/*
 * DOWN — Para reverter esta migration manualmente:
 *
//   $db->exec('DROP TABLE IF EXISTS user_products;');
//   $db->exec('DROP TABLE IF EXISTS shipments;');
//   $db->exec('DROP TABLE IF EXISTS promotion_performance;');
//   $db->exec('DROP TABLE IF EXISTS item_metrics;');
 *
 * ATENÇÃO: Isso apaga dados permanentemente. Faça backup antes.
 */
