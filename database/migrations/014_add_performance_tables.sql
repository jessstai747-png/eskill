-- Migration: Tabelas de Performance e Monitoramento
-- Data: 2024-01-XX
-- Descrição: Cria tabelas para logging de queries e estatísticas de performance

-- Tabela de log de queries
CREATE TABLE IF NOT EXISTS query_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sql_text TEXT NOT NULL,
    params JSON,
    duration DECIMAL(10, 6) NOT NULL COMMENT 'Duração em segundos',
    row_count INT UNSIGNED DEFAULT 0,
    error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_duration (duration),
    INDEX idx_created_at (created_at),
    INDEX idx_slow_queries (created_at, duration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de estatísticas de cache
CREATE TABLE IF NOT EXISTS cache_stats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    driver VARCHAR(50) NOT NULL,
    hits BIGINT UNSIGNED DEFAULT 0,
    misses BIGINT UNSIGNED DEFAULT 0,
    writes BIGINT UNSIGNED DEFAULT 0,
    memory_usage BIGINT UNSIGNED DEFAULT 0 COMMENT 'Bytes',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_recorded_at (recorded_at),
    INDEX idx_driver (driver)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de métricas de API do ML
CREATE TABLE IF NOT EXISTS api_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    status_code SMALLINT UNSIGNED,
    response_time DECIMAL(10, 4) NOT NULL COMMENT 'Segundos',
    request_size INT UNSIGNED DEFAULT 0 COMMENT 'Bytes',
    response_size INT UNSIGNED DEFAULT 0 COMMENT 'Bytes',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_account_endpoint (account_id, endpoint),
    INDEX idx_status (status_code),
    INDEX idx_response_time (response_time),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de jobs em background
CREATE TABLE IF NOT EXISTS background_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_type VARCHAR(100) NOT NULL,
    payload JSON,
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    priority TINYINT UNSIGNED DEFAULT 5 COMMENT '1-10, menor = maior prioridade',
    attempts TINYINT UNSIGNED DEFAULT 0,
    max_attempts TINYINT UNSIGNED DEFAULT 3,
    result JSON,
    error_message TEXT,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    scheduled_for TIMESTAMP NULL COMMENT 'Execução agendada',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_status_priority (status, priority),
    INDEX idx_job_type (job_type),
    INDEX idx_scheduled (scheduled_for, status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de configurações de sistema
CREATE TABLE IF NOT EXISTS system_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value JSON,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configurações padrão
INSERT INTO system_config (config_key, config_value, description) VALUES
('cache_enabled', 'true', 'Habilita/desabilita cache global'),
('cache_ttl_default', '3600', 'TTL padrão do cache em segundos'),
('rate_limit_enabled', 'true', 'Habilita rate limiting'),
('rate_limit_max', '100', 'Máximo de requisições por janela'),
('rate_limit_window', '60', 'Janela de rate limit em segundos'),
('slow_query_threshold', '1.0', 'Limite para considerar query lenta (segundos)'),
('query_logging_enabled', 'false', 'Habilita log de queries'),
('maintenance_mode', 'false', 'Modo de manutenção')
ON DUPLICATE KEY UPDATE config_key = config_key;

-- Eventos de limpeza automática (30 dias)
DROP EVENT IF EXISTS cleanup_performance_logs_event;
DROP PROCEDURE IF EXISTS cleanup_performance_logs;

DROP EVENT IF EXISTS cleanup_query_log;
CREATE EVENT IF NOT EXISTS cleanup_query_log
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
    DELETE FROM query_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

DROP EVENT IF EXISTS cleanup_cache_stats;
CREATE EVENT IF NOT EXISTS cleanup_cache_stats
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
    DELETE FROM cache_stats WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

DROP EVENT IF EXISTS cleanup_api_metrics;
CREATE EVENT IF NOT EXISTS cleanup_api_metrics
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
    DELETE FROM api_metrics WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

DROP EVENT IF EXISTS cleanup_background_jobs;
CREATE EVENT IF NOT EXISTS cleanup_background_jobs
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
    DELETE FROM background_jobs
    WHERE status IN ('completed', 'failed', 'cancelled')
      AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- View para dashboard de performance
CREATE OR REPLACE VIEW v_performance_summary AS
SELECT 
    'queries' as metric_type,
    COUNT(*) as total_count,
    ROUND(AVG(duration) * 1000, 2) as avg_ms,
    ROUND(MAX(duration) * 1000, 2) as max_ms,
    SUM(CASE WHEN duration > 1.0 THEN 1 ELSE 0 END) as slow_count,
    DATE(created_at) as date
FROM query_log
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at)

UNION ALL

SELECT 
    'api_calls' as metric_type,
    COUNT(*) as total_count,
    ROUND(AVG(response_time) * 1000, 2) as avg_ms,
    ROUND(MAX(response_time) * 1000, 2) as max_ms,
    SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as slow_count,
    DATE(created_at) as date
FROM api_metrics
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at);
