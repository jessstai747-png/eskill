-- ============================================================================
-- Migration: SEO Killer - Tabelas faltantes
-- 
-- Cria tabelas usadas pelos Services do SEO Killer que ainda não tinham
-- migration formal (apenas CREATE TABLE IF NOT EXISTS inline nos Services).
--
-- Tabelas:
--   1. seo_autopilot_config    - Configuração do AutoPilot por conta
--   2. seo_autopilot_runs      - Histórico de execuções do AutoPilot
--   3. seo_item_scores         - Scores SEO diários por item
--   4. seo_optimization_events - Eventos de otimização (antes/depois)
--   5. seo_scores_history      - Histórico de scores para tracking
--   6. seo_category_benchmarks - Benchmarks de score por categoria
--   7. seo_score_alerts        - Alertas de degradação de score
-- ============================================================================

-- 1. AutoPilot Configuration
CREATE TABLE IF NOT EXISTS seo_autopilot_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL UNIQUE,
    config JSON NOT NULL,
    last_run TIMESTAMP NULL,
    next_run TIMESTAMP NULL,
    total_runs INT DEFAULT 0,
    total_optimizations INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_account (account_id),
    INDEX idx_next_run (next_run)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. AutoPilot Run History
CREATE TABLE IF NOT EXISTS seo_autopilot_runs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    status ENUM('scheduled', 'running', 'completed', 'failed') DEFAULT 'scheduled',
    items_analyzed INT DEFAULT 0,
    items_optimized INT DEFAULT 0,
    items_skipped INT DEFAULT 0,
    items_failed INT DEFAULT 0,
    avg_score_before DECIMAL(5,2) DEFAULT 0,
    avg_score_after DECIMAL(5,2) DEFAULT 0,
    details JSON,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account (account_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. SEO Item Scores (daily tracking)
CREATE TABLE IF NOT EXISTS seo_item_scores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    score_date DATE NOT NULL,
    overall_score INT DEFAULT 0,
    title_score INT DEFAULT 0,
    description_score INT DEFAULT 0,
    attributes_score INT DEFAULT 0,
    images_score INT DEFAULT 0,
    visibility_score INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_item_date (item_id, score_date),
    INDEX idx_account (account_id),
    INDEX idx_item (item_id),
    INDEX idx_date (score_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. SEO Optimization Events (before/after tracking)
CREATE TABLE IF NOT EXISTS seo_optimization_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    optimization_type ENUM('title', 'description', 'attributes', 'full') NOT NULL,
    old_value TEXT,
    new_value TEXT,
    score_before INT DEFAULT 0,
    score_after INT DEFAULT 0,
    optimized_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account (account_id),
    INDEX idx_item (item_id),
    INDEX idx_date (optimized_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. SEO Scores History (historical score tracking)
CREATE TABLE IF NOT EXISTS seo_scores_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    overall_score DECIMAL(5,2) NOT NULL,
    breakdown_json TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_item_date (item_id, created_at),
    INDEX idx_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. SEO Category Benchmarks
CREATE TABLE IF NOT EXISTS seo_category_benchmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    category_id VARCHAR(50) NOT NULL,
    average_score DECIMAL(5,2),
    top_10_percent_score DECIMAL(5,2),
    sample_size INT,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_category (account_id, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. SEO Score Alerts
CREATE TABLE IF NOT EXISTS seo_score_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    alert_type VARCHAR(50),
    message TEXT,
    severity ENUM('low', 'medium', 'high'),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account_unread (account_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
