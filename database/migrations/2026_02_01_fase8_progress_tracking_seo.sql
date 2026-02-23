-- Migration: Fase 8 - Progress Tracking e SEO Integration
-- Data: 2026-02-01
-- Descrição: Cria tabelas para tracking granular de progresso e otimizações SEO

-- ============================================
-- Tabela: clone_progress_tracking
-- Descrição: Tracking atual de progresso por job
-- ============================================
CREATE TABLE IF NOT EXISTS clone_progress_tracking (
    job_id INT UNSIGNED PRIMARY KEY,
    total_items INT UNSIGNED NOT NULL,
    current_phase VARCHAR(50) NOT NULL COMMENT 'validation, preparation, publication, post_actions, completed',
    phase_progress DECIMAL(5,2) DEFAULT 0 COMMENT 'Progresso da fase atual (0-100)',
    overall_progress DECIMAL(5,2) DEFAULT 0 COMMENT 'Progresso geral ponderado (0-100)',
    eta_seconds INT NULL COMMENT 'Tempo estimado restante em segundos',
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phase (current_phase),
    INDEX idx_started (started_at),
    FOREIGN KEY (job_id) REFERENCES catalog_clone_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracking de progresso granular dos jobs de clonagem';

-- ============================================
-- Tabela: clone_progress_history
-- Descrição: Histórico de progresso para análise
-- ============================================
CREATE TABLE IF NOT EXISTS clone_progress_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    phase VARCHAR(50) NOT NULL COMMENT 'Fase do progresso',
    progress DECIMAL(5,2) NOT NULL COMMENT 'Porcentagem de progresso',
    items_processed INT UNSIGNED NOT NULL COMMENT 'Items processados até aqui',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job_phase (job_id, phase),
    INDEX idx_created (created_at),
    FOREIGN KEY (job_id) REFERENCES catalog_clone_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de progresso dos jobs para análise de performance';

-- ============================================
-- Tabela: clone_seo_optimizations (OPCIONAL)
-- Descrição: Log de otimizações SEO aplicadas
-- ============================================
CREATE TABLE IF NOT EXISTS clone_seo_optimizations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    item_id VARCHAR(50) NOT NULL COMMENT 'ID do item original (MLB...)',
    score_before INT UNSIGNED NOT NULL COMMENT 'Score SEO antes da otimização',
    score_after INT UNSIGNED NOT NULL COMMENT 'Score SEO depois da otimização',
    changes_applied JSON COMMENT 'Detalhes das mudanças aplicadas',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job (job_id),
    INDEX idx_item (item_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (job_id) REFERENCES catalog_clone_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Log de otimizações SEO aplicadas durante clonagem';

-- ============================================
-- Tabela: scheduled_reports (OPCIONAL)
-- Descrição: Agendamento de relatórios periódicos
-- ============================================
CREATE TABLE IF NOT EXISTS scheduled_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    format VARCHAR(20) NOT NULL COMMENT 'pdf, excel, csv',
    filters JSON COMMENT 'Filtros aplicados ao relatório',
    schedule_type VARCHAR(20) NOT NULL COMMENT 'daily, weekly, monthly',
    recipients JSON NOT NULL COMMENT 'Array de emails',
    last_run_at DATETIME NULL,
    next_run_at DATETIME NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_schedule (schedule_type, is_active),
    INDEX idx_next_run (next_run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Agendamento de relatórios periódicos';

-- ============================================
-- Exemplos de Dados (para testes)
-- ============================================

-- Exemplo: Progress tracking de um job
-- INSERT INTO clone_progress_tracking 
-- (job_id, total_items, current_phase, phase_progress, overall_progress, eta_seconds, started_at, updated_at)
-- VALUES (1, 100, 'publication', 45.5, 62.75, 180, NOW(), NOW());

-- Exemplo: História de progresso
-- INSERT INTO clone_progress_history 
-- (job_id, phase, progress, items_processed, created_at)
-- VALUES 
--     (1, 'validation', 100, 100, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
--     (1, 'preparation', 100, 100, DATE_SUB(NOW(), INTERVAL 7 MINUTE)),
--     (1, 'publication', 45.5, 46, NOW());

-- ============================================
-- Índices de Performance
-- ============================================

-- Otimizar queries de dashboard (jobs ativos)
-- ALTER TABLE clone_progress_tracking 
-- ADD INDEX idx_active_jobs (current_phase, updated_at);

-- Otimizar queries de histórico por período
-- ALTER TABLE clone_progress_history 
-- ADD INDEX idx_period (created_at, job_id);

-- ============================================
-- Limpeza Automática (OPCIONAL)
-- ============================================

-- Event para limpar histórico antigo (> 90 dias)
-- DELIMITER //
-- CREATE EVENT IF NOT EXISTS cleanup_old_progress_history
-- ON SCHEDULE EVERY 1 DAY
-- STARTS CURRENT_TIMESTAMP
-- DO
-- BEGIN
--     DELETE FROM clone_progress_history 
--     WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
-- END//
-- DELIMITER ;

-- Event para limpar tracking de jobs completados (> 30 dias)
-- DELIMITER //
-- CREATE EVENT IF NOT EXISTS cleanup_old_progress_tracking
-- ON SCHEDULE EVERY 1 DAY
-- STARTS CURRENT_TIMESTAMP
-- DO
-- BEGIN
--     DELETE FROM clone_progress_tracking 
--     WHERE completed_at IS NOT NULL 
--     AND completed_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
-- END//
-- DELIMITER ;
