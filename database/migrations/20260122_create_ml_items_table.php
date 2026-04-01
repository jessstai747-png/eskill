<?php
declare(strict_types=1);

/**
 * Migração: Tabela para armazenar itens (produtos) do Mercado Livre
 *
 * @version 1.0.0
 * @date 2026-01-22
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;

echo "=== Migração: Tabela de Itens (ml_items) ===\n\n";

try {
    $db = Database::getInstance();

    echo "Criando tabela ml_items...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS ml_items (
            id VARCHAR(20) PRIMARY KEY,
            account_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            sku VARCHAR(100) NULL,
            category_id VARCHAR(50) NULL,
            price DECIMAL(12, 2) NOT NULL,
            currency_id VARCHAR(3) NOT NULL,
            available_quantity INT NOT NULL,
            sold_quantity INT NOT NULL,
            status VARCHAR(50) NOT NULL,
            permalink VARCHAR(255) NULL,
            thumbnail VARCHAR(255) NULL,
            raw_data JSON NULL,
            last_synced_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_account_status (account_id, status),
            INDEX idx_sku (sku),
            INDEX idx_title (title)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ ml_items OK\n";

    echo "\n=== Migração concluída com sucesso! ===\n";
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    throw $e;
}

/*
 * DOWN — Para reverter esta migration manualmente:
 *
//   $db->exec('DROP TABLE IF EXISTS ml_items;');
 *
 * ATENÇÃO: Isso apaga dados permanentemente. Faça backup antes.
 */
