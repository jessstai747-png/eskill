<?php
declare(strict_types=1);

/**
 * Migration: Clone Sync and Management Tables
 * 
 * Tabelas para sincronização, métricas e gerenciamento de itens clonados.
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\Database;

echo "=== Clone Sync & Management Migration ===\n\n";

$db = Database::getInstance();

$tables = [
    // Tabela de configurações de sincronização
    'clone_sync_settings' => "
        CREATE TABLE IF NOT EXISTS clone_sync_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            sync_config JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_account (account_id),
            INDEX idx_account (account_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    // Tabela de logs de sincronização
    'clone_sync_logs' => "
        CREATE TABLE IF NOT EXISTS clone_sync_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            item_id VARCHAR(50) NOT NULL,
            sync_type VARCHAR(50) NOT NULL,
            sync_data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_account (account_id),
            INDEX idx_item (item_id),
            INDEX idx_type (sync_type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    // Tabela de alertas de sincronização
    'clone_sync_alerts' => "
        CREATE TABLE IF NOT EXISTS clone_sync_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            item_id VARCHAR(50) NOT NULL,
            alert_type VARCHAR(50) NOT NULL,
            alert_data JSON,
            status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL,
            INDEX idx_account (account_id),
            INDEX idx_item (item_id),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    // Tabela de métricas de itens clonados (se não existir)
    'clone_item_metrics' => "
        CREATE TABLE IF NOT EXISTS clone_item_metrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            item_id VARCHAR(50) NOT NULL,
            visits INT DEFAULT 0,
            sales INT DEFAULT 0,
            revenue DECIMAL(15,2) DEFAULT 0,
            conversion_rate DECIMAL(5,2) DEFAULT 0,
            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_item (account_id, item_id),
            INDEX idx_account (account_id),
            INDEX idx_item (item_id),
            INDEX idx_sales (sales),
            INDEX idx_synced (synced_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

// Adicionar colunas à tabela cloned_items se não existirem
$alterStatements = [
    "ALTER TABLE cloned_items ADD COLUMN IF NOT EXISTS last_synced_at TIMESTAMP NULL",
    "ALTER TABLE cloned_items ADD COLUMN IF NOT EXISTS closed_at TIMESTAMP NULL",
    "ALTER TABLE cloned_items ADD INDEX IF NOT EXISTS idx_last_synced (last_synced_at)",
    "ALTER TABLE cloned_items ADD INDEX IF NOT EXISTS idx_status_account (status, target_account_id)"
];

$success = 0;
$errors = 0;

// Criar tabelas
foreach ($tables as $name => $sql) {
    try {
        $db->exec($sql);
        echo "[OK] Tabela '$name' criada/verificada\n";
        $success++;
    } catch (PDOException $e) {
        echo "[ERRO] Tabela '$name': " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n";

// Alterações na tabela existente
foreach ($alterStatements as $sql) {
    try {
        $db->exec($sql);
        echo "[OK] ALTER executado com sucesso\n";
    } catch (PDOException $e) {
        // Ignorar erros de coluna já existente
        if (strpos($e->getMessage(), 'Duplicate column') === false &&
            strpos($e->getMessage(), 'Duplicate key') === false) {
            echo "[AVISO] " . $e->getMessage() . "\n";
        }
    }
}

echo "\n";
echo "=== Resultado ===\n";
echo "Tabelas criadas: $success\n";
echo "Erros: $errors\n";
echo "\nMigration concluída!\n";

/*
 * DOWN — Para reverter esta migration manualmente:
 *
//   $db->exec('DROP TABLE IF EXISTS clone_sync_settings;');
//   $db->exec('DROP TABLE IF EXISTS clone_sync_logs;');
//   $db->exec('DROP TABLE IF EXISTS clone_sync_alerts;');
//   $db->exec('DROP TABLE IF EXISTS clone_item_metrics;');
 *
 * ATENÇÃO: Isso apaga dados permanentemente. Faça backup antes.
 */
