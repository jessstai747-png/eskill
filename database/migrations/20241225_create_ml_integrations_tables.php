<?php
/**
 * Migration: Criar tabelas para ReputationService, ItemMetricsService e FulfillmentService
 * 
 * Tabelas:
 * - reputation_history: Histórico de reputação dos vendedores
 * - item_metrics_history: Histórico de métricas de anúncios  
 * - fulfillment_inbound_shipments: Envios para centros de fulfillment
 * 
 * Data: 25/12/2024
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

try {
    $db = Database::getInstance();
    echo "Iniciando migration de novas integrações ML...\n\n";

    // ========================================
    // 1. Tabela: reputation_history
    // ========================================
    echo "[1/3] Criando tabela 'reputation_history'...\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS reputation_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            date DATE NOT NULL,
            level_id VARCHAR(20),
            power_seller_status VARCHAR(20),
            thermometer INT DEFAULT 0,
            total_transactions INT DEFAULT 0,
            completed_transactions INT DEFAULT 0,
            cancellations_rate DECIMAL(5,2) DEFAULT 0.00,
            claims_rate DECIMAL(5,2) DEFAULT 0.00,
            delayed_handling_time_rate DECIMAL(5,2) DEFAULT 0.00,
            positive_rating DECIMAL(5,2) DEFAULT 0.00,
            neutral_rating DECIMAL(5,2) DEFAULT 0.00,
            negative_rating DECIMAL(5,2) DEFAULT 0.00,
            average_rating DECIMAL(3,2) DEFAULT 0.00,
            data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_account_date (account_id, date),
            INDEX idx_account_id (account_id),
            INDEX idx_date (date),
            INDEX idx_level_id (level_id),
            
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    echo "   ✓ Tabela 'reputation_history' criada com sucesso!\n\n";

    // ========================================
    // 2. Tabela: item_metrics_history
    // ========================================
    echo "[2/3] Criando tabela 'item_metrics_history'...\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS item_metrics_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            item_id VARCHAR(50) NOT NULL,
            date DATE NOT NULL,
            visits INT DEFAULT 0,
            sold_quantity INT DEFAULT 0,
            conversion_rate DECIMAL(5,2) DEFAULT 0.00,
            health_score INT DEFAULT 0,
            price DECIMAL(15,2) DEFAULT 0.00,
            data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_account_item_date (account_id, item_id, date),
            INDEX idx_account_id (account_id),
            INDEX idx_item_id (item_id),
            INDEX idx_date (date),
            INDEX idx_health_score (health_score),
            
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    echo "   ✓ Tabela 'item_metrics_history' criada com sucesso!\n\n";

    // ========================================
    // 3. Tabela: fulfillment_inbound_shipments
    // ========================================
    echo "[3/3] Criando tabela 'fulfillment_inbound_shipments'...\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS fulfillment_inbound_shipments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            shipment_id VARCHAR(50) UNIQUE,
            tracking_number VARCHAR(100),
            warehouse_id VARCHAR(50),
            status VARCHAR(30) DEFAULT 'pending',
            items_data JSON,
            estimated_delivery DATE,
            actual_delivery DATE,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_account_id (account_id),
            INDEX idx_shipment_id (shipment_id),
            INDEX idx_status (status),
            INDEX idx_warehouse_id (warehouse_id),
            INDEX idx_created_at (created_at),
            
            FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    echo "   ✓ Tabela 'fulfillment_inbound_shipments' criada com sucesso!\n\n";

    // ========================================
    // Verificação final
    // ========================================
    echo "========================================\n";
    echo "✅ MIGRATION CONCLUÍDA COM SUCESSO!\n";
    echo "========================================\n\n";
    
    echo "Tabelas criadas:\n";
    echo "  ✓ reputation_history\n";
    echo "  ✓ item_metrics_history\n";
    echo "  ✓ fulfillment_inbound_shipments\n\n";
    
    echo "Índices criados para otimização de queries.\n";
    echo "Relacionamentos (FK) configurados com CASCADE.\n\n";
    
    echo "Próximos passos:\n";
    echo "  1. Configure snapshots automáticos via CRON\n";
    echo "  2. Teste os novos serviços: ReputationService, ItemMetricsService, FulfillmentService\n";
    echo "  3. Monitore o uso de disco - dados históricos crescem diariamente\n\n";

} catch (PDOException $e) {
    echo "❌ ERRO NA MIGRATION: " . $e->getMessage() . "\n";
    echo "Stacktrace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
