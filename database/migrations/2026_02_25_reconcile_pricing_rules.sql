-- =============================================================================
-- Reconciliation migration for pricing_rules table
-- =============================================================================
-- This table has 3 conflicting CREATE TABLE IF NOT EXISTS migrations:
--   2024_06_01 (English columns: name, rule_type, is_active, config, items...)
--   2025_01_01 (English/simple: item_id, rules, active...)
--   2026_01_29 (Portuguese: nome, estrategia, ativo, margem_minima...)
-- Plus 4 services that INSERT different column sets.
-- This migration ensures ALL columns exist regardless of which CREATE ran first.
-- =============================================================================

DELIMITER //

DROP PROCEDURE IF EXISTS reconcile_pricing_rules //

CREATE PROCEDURE reconcile_pricing_rules()
BEGIN
    -- ==========================================
    -- Columns from 2024_06_01 migration (English)
    -- ==========================================
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'name') THEN
        ALTER TABLE pricing_rules ADD COLUMN name VARCHAR(255) NULL AFTER account_id;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'rule_type') THEN
        ALTER TABLE pricing_rules ADD COLUMN rule_type VARCHAR(50) NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'priority') THEN
        ALTER TABLE pricing_rules ADD COLUMN priority INT DEFAULT 100;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'config') THEN
        ALTER TABLE pricing_rules ADD COLUMN config JSON NULL COMMENT 'Rule configuration based on type';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'items') THEN
        ALTER TABLE pricing_rules ADD COLUMN items JSON NULL COMMENT 'Item IDs to apply rule';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'categories') THEN
        ALTER TABLE pricing_rules ADD COLUMN categories JSON NULL COMMENT 'Category IDs to apply rule';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'is_active') THEN
        ALTER TABLE pricing_rules ADD COLUMN is_active TINYINT(1) DEFAULT 1;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'last_executed_at') THEN
        ALTER TABLE pricing_rules ADD COLUMN last_executed_at DATETIME NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'execution_count') THEN
        ALTER TABLE pricing_rules ADD COLUMN execution_count INT DEFAULT 0;
    END IF;

    -- ==========================================
    -- Columns from 2025_01_01 migration (simple)
    -- ==========================================
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'item_id') THEN
        ALTER TABLE pricing_rules ADD COLUMN item_id VARCHAR(50) NULL COMMENT 'MLB ID do produto';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'rules') THEN
        ALTER TABLE pricing_rules ADD COLUMN rules JSON NULL COMMENT 'Array de regras de precificação';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'active') THEN
        ALTER TABLE pricing_rules ADD COLUMN active BOOLEAN DEFAULT TRUE;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'last_evaluation_at') THEN
        ALTER TABLE pricing_rules ADD COLUMN last_evaluation_at TIMESTAMP NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'last_applied_at') THEN
        ALTER TABLE pricing_rules ADD COLUMN last_applied_at TIMESTAMP NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'evaluation_frequency_minutes') THEN
        ALTER TABLE pricing_rules ADD COLUMN evaluation_frequency_minutes INT DEFAULT 60;
    END IF;

    -- ============================================
    -- Columns from 2026_01_29 migration (Portuguese)
    -- ============================================
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'nome') THEN
        ALTER TABLE pricing_rules ADD COLUMN nome VARCHAR(100) NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'descricao') THEN
        ALTER TABLE pricing_rules ADD COLUMN descricao TEXT NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'aplica_categoria') THEN
        ALTER TABLE pricing_rules ADD COLUMN aplica_categoria VARCHAR(50) NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'aplica_marca') THEN
        ALTER TABLE pricing_rules ADD COLUMN aplica_marca VARCHAR(100) NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'aplica_item_ids') THEN
        ALTER TABLE pricing_rules ADD COLUMN aplica_item_ids JSON NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'estrategia') THEN
        ALTER TABLE pricing_rules ADD COLUMN estrategia VARCHAR(30) NULL DEFAULT 'competitivo';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'margem_minima') THEN
        ALTER TABLE pricing_rules ADD COLUMN margem_minima DECIMAL(5,2) DEFAULT 10.00;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'margem_alvo') THEN
        ALTER TABLE pricing_rules ADD COLUMN margem_alvo DECIMAL(5,2) DEFAULT 20.00;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'desconto_maximo') THEN
        ALTER TABLE pricing_rules ADD COLUMN desconto_maximo DECIMAL(5,2) DEFAULT 30.00;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'aumento_maximo') THEN
        ALTER TABLE pricing_rules ADD COLUMN aumento_maximo DECIMAL(5,2) DEFAULT 15.00;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'limite_aumento_ranking') THEN
        ALTER TABLE pricing_rules ADD COLUMN limite_aumento_ranking DECIMAL(5,2) DEFAULT 8.00;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'ativo') THEN
        ALTER TABLE pricing_rules ADD COLUMN ativo TINYINT(1) DEFAULT 1;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'execucao_automatica') THEN
        ALTER TABLE pricing_rules ADD COLUMN execucao_automatica TINYINT(1) DEFAULT 0;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'intervalo_verificacao') THEN
        ALTER TABLE pricing_rules ADD COLUMN intervalo_verificacao INT DEFAULT 24;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'ultima_execucao') THEN
        ALTER TABLE pricing_rules ADD COLUMN ultima_execucao TIMESTAMP NULL;
    END IF;

    -- ============================================
    -- Columns from PriceRulesEngineService (custom)
    -- ============================================
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'description') THEN
        ALTER TABLE pricing_rules ADD COLUMN description TEXT NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'conditions') THEN
        ALTER TABLE pricing_rules ADD COLUMN conditions JSON NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'actions') THEN
        ALTER TABLE pricing_rules ADD COLUMN actions JSON NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'applies_to') THEN
        ALTER TABLE pricing_rules ADD COLUMN applies_to VARCHAR(20) NULL DEFAULT 'all';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'item_ids') THEN
        ALTER TABLE pricing_rules ADD COLUMN item_ids JSON NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'category_ids') THEN
        ALTER TABLE pricing_rules ADD COLUMN category_ids JSON NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'start_date') THEN
        ALTER TABLE pricing_rules ADD COLUMN start_date DATETIME NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'end_date') THEN
        ALTER TABLE pricing_rules ADD COLUMN end_date DATETIME NULL;
    END IF;

    -- ============================================
    -- Columns from PricingRulesService (custom)
    -- ============================================
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'scope') THEN
        ALTER TABLE pricing_rules ADD COLUMN scope VARCHAR(20) NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'scope_id') THEN
        ALTER TABLE pricing_rules ADD COLUMN scope_id VARCHAR(50) NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'params') THEN
        ALTER TABLE pricing_rules ADD COLUMN params JSON NULL;
    END IF;

    -- ============================================
    -- Ensure created_at/updated_at exist
    -- ============================================
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'created_at') THEN
        ALTER TABLE pricing_rules ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pricing_rules' AND COLUMN_NAME = 'updated_at') THEN
        ALTER TABLE pricing_rules ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP;
    END IF;

END //

DELIMITER ;

CALL reconcile_pricing_rules();
DROP PROCEDURE IF EXISTS reconcile_pricing_rules;

-- Sync ativo ↔ is_active for existing rows
UPDATE pricing_rules SET ativo = is_active WHERE ativo IS NULL AND is_active IS NOT NULL;
UPDATE pricing_rules SET is_active = ativo WHERE is_active IS NULL AND ativo IS NOT NULL;
UPDATE pricing_rules SET active = is_active WHERE active IS NULL AND is_active IS NOT NULL;
