#!/usr/bin/env php
<?php
/**
 * Script para adicionar a coluna active_ml_account_id na tabela users
 */

require_once __DIR__ . '/../autoload.php';

use App\Database;

try {
    $db = Database::getInstance();
    
    echo "Verificando se a coluna active_ml_account_id existe...\n";
    
    // Verificar se a coluna já existe
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'active_ml_account_id'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "✓ A coluna active_ml_account_id já existe!\n";
        exit(0);
    }
    
    echo "Adicionando coluna active_ml_account_id à tabela users...\n";
    
    $db->exec("
        ALTER TABLE users 
        ADD COLUMN active_ml_account_id INT(11) NULL DEFAULT NULL,
        ADD INDEX idx_active_ml_account (active_ml_account_id)
    ");
    
    echo "✓ Coluna active_ml_account_id adicionada com sucesso!\n";
    
    // Verificar novamente
    $stmt = $db->query("DESCRIBE users");
    echo "\nColunas da tabela users:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
