<?php
/**
 * Migração: Tabelas para módulo de Ficha Técnica
 * 
 * Cria as tabelas tech_sheet_item_summary e tech_sheet_suggestions
 * 
 * @version 1.0.0
 * @date 2026-01-01
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;

echo "=== Migração: Ficha Técnica ===\n\n";

try {
    $db = Database::getInstance();
    
    // Tabela: tech_sheet_item_summary
    echo "Criando tabela tech_sheet_item_summary...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS tech_sheet_item_summary (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            item_id VARCHAR(50) NOT NULL,
            category_id VARCHAR(50) NULL,
            total_available INT DEFAULT 0,
            filled INT DEFAULT 0,
            missing INT DEFAULT 0,
            completeness_percent DECIMAL(5,2) DEFAULT 0.00,
            missing_required INT DEFAULT 0,
            missing_filter INT DEFAULT 0,
            missing_hidden INT DEFAULT 0,
            missing_recommended INT DEFAULT 0,
            last_analyzed_at DATETIME NULL,
            meta JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_account_item (account_id, item_id),
            INDEX idx_account_category (account_id, category_id),
            INDEX idx_completeness (completeness_percent),
            INDEX idx_missing_required (missing_required)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ tech_sheet_item_summary OK\n";
    
    // Tabela: tech_sheet_suggestions
    echo "Criando tabela tech_sheet_suggestions...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS tech_sheet_suggestions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            item_id VARCHAR(50) NOT NULL,
            category_id VARCHAR(50) NULL,
            attribute_id VARCHAR(100) NOT NULL,
            attribute_name VARCHAR(255) NULL,
            suggested_value TEXT NULL,
            source VARCHAR(50) DEFAULT 'inference' COMMENT 'title, benchmark, ai, inference, default, manual',
            confidence TINYINT UNSIGNED DEFAULT 0 COMMENT '0-100',
            status ENUM('pending', 'approved', 'rejected', 'applied') DEFAULT 'pending',
            decided_by_user_id INT NULL,
            decided_at DATETIME NULL,
            applied_at DATETIME NULL,
            meta JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_account_item_attr (account_id, item_id, attribute_id),
            INDEX idx_account_item (account_id, item_id),
            INDEX idx_status (status),
            INDEX idx_confidence (confidence),
            INDEX idx_source (source),
            INDEX idx_auto_optimize (account_id, status, confidence, source)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ tech_sheet_suggestions OK\n";
    
    // Tabela: tech_sheet_execution_log (opcional - para auditoria detalhada)
    echo "Criando tabela tech_sheet_execution_log...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS tech_sheet_execution_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            item_id VARCHAR(50) NOT NULL,
            action ENUM('generate', 'approve', 'reject', 'apply', 'batch_generate', 'batch_apply') NOT NULL,
            user_id INT NULL,
            job_id INT NULL,
            details JSON NULL,
            result ENUM('success', 'partial', 'failed') DEFAULT 'success',
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_account_item (account_id, item_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ tech_sheet_execution_log OK\n";
    
    echo "\n=== Migração concluída com sucesso! ===\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    throw $e;
}
