-- Migration: Create bulk_seo_jobs table
-- Date: 2026-01-30
-- Description: Tabela oficial para jobs assíncronos do Bulk SEO da Ficha Técnica
-- 
-- NOTA: Esta é a tabela canônica usada por:
--   - app/Services/BulkSEOService.php
--   - app/Controllers/TechnicalSheetController.php
--   - bin/bulk-seo-worker.php
--
-- NÃO confundir com seo_bulk_jobs (usada pelo fluxo AI/Legacy em SEOController/BulkOptimizer)

CREATE TABLE IF NOT EXISTS bulk_seo_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Identificador único do job (formato: bulk_seo_<uniqid>_<timestamp>)
    job_id VARCHAR(100) NOT NULL,
    
    -- Relacionamentos
    account_id INT NOT NULL,
    user_id INT NULL,
    
    -- Status do job (transições: pending → queued → processing → completed/failed)
    -- pending: job criado, aguardando dispatch
    -- queued: dispatch falhou, aguardando cron/worker
    -- processing: worker está processando
    -- completed: processamento finalizado com sucesso
    -- failed: processamento finalizado com erro
    status ENUM('pending', 'queued', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    
    -- Contadores de progresso
    total_items INT NOT NULL DEFAULT 0,
    processed_items INT NOT NULL DEFAULT 0,
    successful_items INT NOT NULL DEFAULT 0,
    failed_items INT NOT NULL DEFAULT 0,
    
    -- Dados do job (itens a processar, metadados)
    job_data JSON NULL,
    
    -- Resultados do processamento (por item: success/error, version_ids)
    results JSON NULL,
    
    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Constraints
    UNIQUE KEY uk_job_id (job_id),
    
    -- Indexes para queries comuns
    INDEX idx_account_status (account_id, status),
    INDEX idx_status_created (status, created_at),
    INDEX idx_account_created (account_id, created_at DESC)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Jobs assíncronos do Bulk SEO (Ficha Técnica)';

-- Adicionar colunas faltantes se tabela já existe (idempotente)
-- Isso garante compatibilidade com ambientes onde a tabela foi criada on-demand

-- Verificar e adicionar updated_at se não existir (compatível com MySQL via PDO)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bulk_seo_jobs' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE bulk_seo_jobs ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
