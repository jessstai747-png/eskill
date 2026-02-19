<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

echo "Iniciando criação da tabela clone_post_actions_log...\n";

try {
    $db = Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS clone_post_actions_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clone_job_id VARCHAR(50) NULL,
        cloned_item_id INT NULL,
        target_item_id VARCHAR(50) NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        status ENUM('pending', 'processing', 'completed', 'failed', 'skipped') DEFAULT 'pending',
        result JSON NULL,
        error_message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        processed_at DATETIME NULL,
        INDEX idx_status (status),
        INDEX idx_target (target_item_id),
        INDEX idx_job (clone_job_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $db->exec($sql);
    
    echo "Tabela clone_post_actions_log criada/verificada com sucesso!\n";
    
} catch (Exception $e) {
    echo "Erro ao criar tabela: " . $e->getMessage() . "\n";
    exit(1);
}
