<?php

/**
 * Migration: Clone Advanced Features Tables
 * 
 * Tabelas para:
 * - Histórico de preços
 * - Operações em lote
 * - Logs de export
 * - Health checks
 */

use App\Database;

class Migration_2025_06_Clone_Advanced_Tables
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    public function up(): void
    {
        // Tabela de histórico de preços
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_price_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                account_id INT UNSIGNED NOT NULL,
                item_id VARCHAR(50) NOT NULL,
                old_price DECIMAL(15,2) NOT NULL,
                new_price DECIMAL(15,2) NOT NULL,
                change_percent DECIMAL(8,2) GENERATED ALWAYS AS (
                    ((new_price - old_price) / old_price) * 100
                ) STORED,
                source ENUM('manual', 'batch', 'sync', 'rule', 'api') DEFAULT 'manual',
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_account_item (account_id, item_id),
                INDEX idx_changed_at (changed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabela de operações em lote
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_batch_operations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                account_id INT UNSIGNED NOT NULL,
                operation_type VARCHAR(50) NOT NULL,
                total_items INT UNSIGNED DEFAULT 0,
                success_count INT UNSIGNED DEFAULT 0,
                error_count INT UNSIGNED DEFAULT 0,
                results JSON,
                status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'completed',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                INDEX idx_account (account_id),
                INDEX idx_type (operation_type),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabela de exports
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_exports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                account_id INT UNSIGNED NOT NULL,
                export_type VARCHAR(50) NOT NULL,
                format ENUM('csv', 'json', 'html', 'xlsx') NOT NULL DEFAULT 'csv',
                filename VARCHAR(255) NOT NULL,
                file_size INT UNSIGNED DEFAULT 0,
                records_count INT UNSIGNED DEFAULT 0,
                filters JSON,
                status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'completed',
                download_count INT UNSIGNED DEFAULT 0,
                last_downloaded_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                INDEX idx_account (account_id),
                INDEX idx_type (export_type),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabela de health checks
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_health_checks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                account_id INT UNSIGNED NOT NULL,
                check_type VARCHAR(50) NOT NULL,
                status ENUM('healthy', 'warning', 'critical') NOT NULL,
                value DECIMAL(15,4) DEFAULT NULL,
                threshold_warning DECIMAL(15,4) DEFAULT NULL,
                threshold_critical DECIMAL(15,4) DEFAULT NULL,
                message TEXT,
                checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_account (account_id),
                INDEX idx_type (check_type),
                INDEX idx_checked (checked_at),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabela de SEO analysis cache
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_seo_analysis (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                account_id INT UNSIGNED NOT NULL,
                item_id VARCHAR(50) NOT NULL,
                score_title INT UNSIGNED DEFAULT 0,
                score_description INT UNSIGNED DEFAULT 0,
                score_attributes INT UNSIGNED DEFAULT 0,
                score_images INT UNSIGNED DEFAULT 0,
                score_shipping INT UNSIGNED DEFAULT 0,
                score_total INT UNSIGNED DEFAULT 0,
                risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
                analysis_data JSON,
                recommendations JSON,
                analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_account_item (account_id, item_id),
                INDEX idx_score (score_total),
                INDEX idx_risk (risk_level),
                INDEX idx_analyzed (analyzed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabela de regras de repricing
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_repricing_rules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                account_id INT UNSIGNED NOT NULL,
                name VARCHAR(100) NOT NULL,
                category_id VARCHAR(50) DEFAULT NULL,
                rule_type ENUM('percentage', 'fixed_increase', 'fixed_decrease', 'set_price', 'round') NOT NULL,
                value DECIMAL(15,4) NOT NULL,
                min_price DECIMAL(15,2) DEFAULT NULL,
                max_price DECIMAL(15,2) DEFAULT NULL,
                round_strategy ENUM('up', 'down', 'nearest') DEFAULT 'nearest',
                round_precision INT DEFAULT 0,
                conditions JSON,
                is_active TINYINT(1) DEFAULT 1,
                priority INT UNSIGNED DEFAULT 0,
                last_run_at TIMESTAMP NULL,
                items_affected INT UNSIGNED DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_account (account_id),
                INDEX idx_active (is_active),
                INDEX idx_category (category_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "Migration clone_advanced_tables executada com sucesso!\n";
    }
    
    public function down(): void
    {
        $tables = [
            'clone_repricing_rules',
            'clone_seo_analysis',
            'clone_health_checks',
            'clone_exports',
            'clone_batch_operations',
            'clone_price_history'
        ];
        
        foreach ($tables as $table) {
            $this->db->exec("DROP TABLE IF EXISTS $table");
        }
        
        echo "Rollback clone_advanced_tables executado!\n";
    }
}

// Executar se chamado diretamente
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    require_once __DIR__ . '/../autoload.php';
    
    $migration = new Migration_2025_06_Clone_Advanced_Tables();
    
    $action = $argv[1] ?? 'up';
    
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
