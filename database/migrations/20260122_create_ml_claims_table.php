<?php
declare(strict_types=1);

/**
 * Migração: Tabela para módulo de Reclamações (Claims)
 *
 * Cria a tabela ml_claims para armazenar dados de reclamações do ML
 *
 * @version 1.0.0
 * @date 2026-01-22
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;

echo "=== Migração: Tabela de Reclamações (ml_claims) ===\n\n";

try {
    $db = Database::getInstance();

    echo "Criando tabela ml_claims...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS ml_claims (
            id BIGINT UNSIGNED PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            account_id INT NOT NULL,
            platform VARCHAR(50) NOT NULL DEFAULT 'mercadolivre',
            type VARCHAR(50) NULL,
            status VARCHAR(50) NULL,
            stage VARCHAR(50) NULL,
            reason VARCHAR(100) NULL,
            amount DECIMAL(12, 2) NULL,
            currency_id VARCHAR(3) NULL,
            date_created DATETIME NULL,
            last_updated DATETIME NULL,
            raw_data JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_order_id (order_id),
            INDEX idx_account_status (account_id, status),
            INDEX idx_type (type),
            INDEX idx_date_created (date_created)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ ml_claims OK\n";

    echo "\n=== Migração concluída com sucesso! ===\n";
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    throw $e;
}

/*
 * DOWN — Para reverter esta migration manualmente:
 *
//   $db->exec('DROP TABLE IF EXISTS ml_claims;');
 *
 * ATENÇÃO: Isso apaga dados permanentemente. Faça backup antes.
 */
