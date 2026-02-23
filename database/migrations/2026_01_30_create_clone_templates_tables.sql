-- =============================================================================
-- Migration: Clone Templates and Post-Clone Actions
-- Description: Templates de clonagem + ações pós-clone + métricas
-- Date: 2026-01-30
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Table: clone_templates
-- Purpose: Stores reusable clone configuration templates
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clone_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    icon VARCHAR(50) DEFAULT 'bi-files' COMMENT 'Bootstrap icon class',
    color VARCHAR(20) DEFAULT 'primary' COMMENT 'Bootstrap color class',
    
    -- Pricing rules
    pricing_type ENUM('copy', 'markup_percent', 'markdown_percent', 'fixed', 'competitive', 'aggressive', 'premium') NOT NULL DEFAULT 'copy',
    pricing_value DECIMAL(10,2) NULL COMMENT 'Percentage or fixed value',
    pricing_round_to DECIMAL(10,2) NULL COMMENT 'Round to nearest (e.g., 0.99, 0.90)',
    
    -- Stock rules
    stock_type ENUM('copy', 'fixed', 'zero', 'percentage') NOT NULL DEFAULT 'copy',
    stock_value INT NULL COMMENT 'Fixed value or percentage',
    
    -- Title rules
    title_prefix VARCHAR(50) NULL,
    title_suffix VARCHAR(50) NULL,
    title_remove_patterns JSON NULL COMMENT 'Regex patterns to remove from title',
    
    -- Status and options
    initial_status ENUM('active', 'paused') NOT NULL DEFAULT 'paused',
    clone_description TINYINT(1) NOT NULL DEFAULT 1,
    clone_variations TINYINT(1) NOT NULL DEFAULT 1,
    skip_catalog_items TINYINT(1) NOT NULL DEFAULT 0,
    skip_non_catalog_items TINYINT(1) NOT NULL DEFAULT 0,
    
    -- Post-clone actions
    post_clone_actions JSON NULL COMMENT 'Array of actions: tech_sheet, seo_optimize, pricing_apply',
    
    -- Metadata
    is_system TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'System template, cannot be deleted',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_slug (slug),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Insert default system templates
-- -----------------------------------------------------------------------------
INSERT INTO clone_templates (name, slug, description, icon, color, pricing_type, stock_type, initial_status, is_system, post_clone_actions) VALUES
('Replicação Exata', 'replication', 'Copia o anúncio exatamente como está, mantendo preço e estoque', 'bi-files', 'primary', 'copy', 'copy', 'paused', 1, NULL),
('Dropshipping', 'dropshipping', 'Anúncio pausado, estoque zero, preço +30% para margem', 'bi-box-arrow-down', 'warning', 'markup_percent', 'zero', 'paused', 1, NULL),
('Competitivo', 'competitive', 'Usa IA para definir preço competitivo baseado no mercado', 'bi-graph-up-arrow', 'success', 'competitive', 'copy', 'paused', 1, NULL),
('SEO Otimizado', 'seo-first', 'Clona e dispara otimização SEO automática via Tech Sheet', 'bi-search-heart', 'info', 'copy', 'copy', 'paused', 1, '["tech_sheet", "seo_optimize"]'),
('Premium', 'premium', 'Preço +15% e ativa imediatamente para produtos de alto valor', 'bi-gem', 'danger', 'markup_percent', 'copy', 'active', 1, NULL)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Set specific values for templates
UPDATE clone_templates SET pricing_value = 30 WHERE slug = 'dropshipping';
UPDATE clone_templates SET pricing_value = 15 WHERE slug = 'premium';

-- -----------------------------------------------------------------------------
-- Table: clone_post_actions_log
-- Purpose: Log of post-clone actions executed
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clone_post_actions_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clone_job_id VARCHAR(64) NULL COMMENT 'Reference to catalog_clone_jobs.job_id',
    cloned_item_id INT UNSIGNED NULL COMMENT 'Reference to cloned_items.id',
    target_item_id VARCHAR(50) NOT NULL COMMENT 'ML item ID',
    action_type ENUM('tech_sheet', 'seo_optimize', 'pricing_apply', 'activate', 'custom') NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
    result JSON NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    
    INDEX idx_job (clone_job_id),
    INDEX idx_item (target_item_id),
    INDEX idx_status (status),
    INDEX idx_action (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: clone_metrics
-- Purpose: Aggregated metrics for clone operations (hourly/daily)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clone_metrics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    metric_date DATE NOT NULL,
    metric_hour TINYINT UNSIGNED NULL COMMENT 'Hour (0-23) for hourly metrics, NULL for daily',
    account_id INT UNSIGNED NULL COMMENT 'NULL for global metrics',
    
    -- Counters
    total_jobs INT UNSIGNED NOT NULL DEFAULT 0,
    completed_jobs INT UNSIGNED NOT NULL DEFAULT 0,
    failed_jobs INT UNSIGNED NOT NULL DEFAULT 0,
    total_items INT UNSIGNED NOT NULL DEFAULT 0,
    successful_items INT UNSIGNED NOT NULL DEFAULT 0,
    failed_items INT UNSIGNED NOT NULL DEFAULT 0,
    skipped_items INT UNSIGNED NOT NULL DEFAULT 0,
    
    -- Performance
    avg_job_duration_seconds INT UNSIGNED NULL,
    avg_items_per_job DECIMAL(10,2) NULL,
    
    -- Error breakdown
    error_counts JSON NULL COMMENT 'Count by error type',
    
    -- Template usage
    template_usage JSON NULL COMMENT 'Count by template slug',
    
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_date_hour_account (metric_date, metric_hour, account_id),
    INDEX idx_date (metric_date),
    INDEX idx_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Add template reference to jobs table
-- -----------------------------------------------------------------------------
ALTER TABLE catalog_clone_jobs
    ADD COLUMN IF NOT EXISTS template_id INT UNSIGNED NULL COMMENT 'Reference to clone_templates.id',
    ADD COLUMN IF NOT EXISTS template_slug VARCHAR(50) NULL COMMENT 'Template slug for quick reference',
    ADD INDEX IF NOT EXISTS idx_template (template_id);

-- -----------------------------------------------------------------------------
-- Add post-clone action fields to job items
-- -----------------------------------------------------------------------------
ALTER TABLE catalog_clone_job_items
    ADD COLUMN IF NOT EXISTS post_actions_status ENUM('pending', 'processing', 'completed', 'failed', 'skipped') NULL,
    ADD COLUMN IF NOT EXISTS post_actions_result JSON NULL;
