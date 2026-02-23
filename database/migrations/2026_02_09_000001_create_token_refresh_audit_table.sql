-- Migration: Token Refresh Audit Table
-- Created: 2026-02-09
-- Purpose: Rastrear histórico de tentativas de renovação de tokens do Mercado Livre
-- Related: FASE 2 - Sistema de Monitoramento de Tokens

CREATE TABLE IF NOT EXISTS token_refresh_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    action ENUM(
        'refresh_attempt',
        'refresh_success',
        'refresh_failed',
        'authorization_granted',
        'token_expired',
        'lock_acquired',
        'lock_timeout'
    ) NOT NULL,
    details JSON DEFAULT NULL COMMENT 'Detalhes adicionais em formato JSON',
    http_code INT DEFAULT NULL COMMENT 'Código HTTP da resposta da API ML',
    error_message TEXT DEFAULT NULL COMMENT 'Mensagem de erro se aplicável',
    expires_at_before DATETIME DEFAULT NULL COMMENT 'Data de expiração antes do refresh',
    expires_at_after DATETIME DEFAULT NULL COMMENT 'Data de expiração após o refresh',
    execution_time_ms INT DEFAULT NULL COMMENT 'Tempo de execução em milissegundos',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_account_id (account_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_account_action (account_id, action),
    INDEX idx_account_created (account_id, created_at),
    
    CONSTRAINT fk_tra_account_id 
        FOREIGN KEY (account_id) 
        REFERENCES ml_accounts(id) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Auditoria de renovações de tokens do Mercado Livre';

-- Índice para queries de dashboard (últimas 24h, últimos 7 dias, etc)
CREATE INDEX idx_dashboard_queries ON token_refresh_audit(action, created_at, account_id);

-- Índice para análise de taxa de falha
CREATE INDEX idx_failure_analysis ON token_refresh_audit(account_id, action, created_at);
