<?php

/**
 * Migration: Clone Auto-Scheduler Tables
 * 
 * Tabelas para:
 * - Agendamentos automáticos de clonagem
 * - Histórico de execuções
 * - Logs de ações
 */

use App\Database;

class Migration_2026_02_Clone_Scheduler_Tables
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    public function up(): void
    {
        // Tabela principal de agendamentos
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_schedules (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                account_id INT UNSIGNED NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                
                -- Configuração de origem
                source_type ENUM('seller_id', 'category_id', 'search_query', 'item_list') NOT NULL,
                source_value VARCHAR(255) NOT NULL,
                
                -- Configuração de frequência
                frequency ENUM('once', 'hourly', 'daily', 'weekly', 'monthly') NOT NULL DEFAULT 'daily',
                run_at_hour TINYINT UNSIGNED DEFAULT 3,
                run_at_minute TINYINT UNSIGNED DEFAULT 0,
                run_on_days JSON COMMENT 'Array de dias da semana [1-7]',
                
                -- Trigger type
                trigger_type ENUM('scheduled', 'new_items', 'price_drop', 'stock_available') DEFAULT 'scheduled',
                trigger_conditions JSON,
                
                -- Configuração de clonagem
                template_id INT UNSIGNED NULL,
                max_items_per_run INT UNSIGNED DEFAULT 50,
                filters JSON,
                seo_level ENUM('none', 'basic', 'advanced', 'aggressive') DEFAULT 'basic',
                
                -- Status e controle
                is_active TINYINT(1) DEFAULT 1,
                status ENUM('active', 'paused', 'running', 'completed', 'failed') DEFAULT 'active',
                next_run_at DATETIME NULL,
                last_run_at DATETIME NULL,
                
                -- Timestamps
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_account (account_id),
                INDEX idx_active (is_active),
                INDEX idx_next_run (next_run_at),
                INDEX idx_status (status),
                FOREIGN KEY (template_id) REFERENCES clone_templates(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabela de execuções (runs)
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_schedule_runs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                schedule_id INT UNSIGNED NOT NULL,
                job_id INT UNSIGNED NULL,
                
                status ENUM('pending', 'running', 'completed', 'failed') NOT NULL DEFAULT 'pending',
                items_found INT UNSIGNED DEFAULT 0,
                items_cloned INT UNSIGNED DEFAULT 0,
                error_message TEXT,
                
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME NULL,
                
                INDEX idx_schedule (schedule_id),
                INDEX idx_job (job_id),
                INDEX idx_status (status),
                INDEX idx_started (started_at),
                FOREIGN KEY (schedule_id) REFERENCES clone_schedules(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabela de logs de ações
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_schedule_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                schedule_id INT UNSIGNED NOT NULL,
                account_id INT UNSIGNED NOT NULL,
                action VARCHAR(50) NOT NULL,
                data JSON,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_schedule (schedule_id),
                INDEX idx_account (account_id),
                INDEX idx_action (action),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabela de recomendações cacheadas
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_recommendations_cache (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                account_id INT UNSIGNED NOT NULL,
                recommendation_type ENUM('seller', 'product', 'category', 'trend') NOT NULL,
                category_id VARCHAR(50) NULL,
                data JSON NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_account_type (account_id, recommendation_type),
                INDEX idx_expires (expires_at),
                INDEX idx_category (category_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "Migration clone_scheduler_tables executada com sucesso!\n";
    }
    
    public function down(): void
    {
        $tables = [
            'clone_recommendations_cache',
            'clone_schedule_logs',
            'clone_schedule_runs',
            'clone_schedules'
        ];
        
        // Desabilitar foreign key checks temporariamente
        $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($tables as $table) {
            $this->db->exec("DROP TABLE IF EXISTS $table");
        }
        
        $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        echo "Rollback clone_scheduler_tables executado!\n";
    }
}

// Executar se chamado diretamente
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $migration = new Migration_2026_02_Clone_Scheduler_Tables();
    
    $action = $argv[1] ?? 'up';
    
    if ($action === 'down') {
        $migration->down();
    } else {
        $migration->up();
    }
}
