<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

echo "Iniciando criação da tabela cloned_items...\n";

try {
    $db = Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS cloned_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_account_id INT NOT NULL,
        source_item_id VARCHAR(50) NOT NULL,
        target_account_id INT NOT NULL,
        target_item_id VARCHAR(50) NULL,
        catalog_product_id VARCHAR(50) NULL,
        status ENUM('created', 'skipped_duplicate', 'error') NOT NULL,
        error_message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_source (source_account_id, source_item_id),
        INDEX idx_target (target_account_id),
        INDEX idx_catalog (catalog_product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $db->exec($sql);
    
    echo "Tabela cloned_items criada/verificada com sucesso!\n";
    
} catch (Exception $e) {
    echo "Erro ao criar tabela: " . $e->getMessage() . "\n";
    exit(1);
}
