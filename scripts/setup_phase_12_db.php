<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "=== Configurando Banco de Dados (Phase 12) ===\n";

try {
    $db = Database::getInstance();
    
    echo "[1] Criando tabela ml_questions... ";
    $sql = "CREATE TABLE IF NOT EXISTS ml_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ml_question_id VARCHAR(50) UNIQUE NOT NULL,
        ml_item_id VARCHAR(50),
        ml_seller_id INT,
        text TEXT,
        status VARCHAR(50),
        date_created DATETIME,
        
        -- AI Analysis Columns
        sentiment VARCHAR(20) DEFAULT NULL, -- positive, neutral, negative, angry
        intent VARCHAR(50) DEFAULT NULL,    -- shipping, technical, price, stock, other
        urgency INT DEFAULT 0,              -- 0-100
        analysis_raw JSON DEFAULT NULL,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_ml_question_id (ml_question_id),
        INDEX idx_sentiment (sentiment),
        INDEX idx_urgency (urgency)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $db->exec($sql);
    echo "✅ Feito\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Configuração concluída!\n";
