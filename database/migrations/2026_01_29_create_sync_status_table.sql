-- Migration: Criar tabela sync_status
-- Data: 2026-01-29
-- Descrição: Tabela oficial para controle de sincronização por conta e recurso

CREATE TABLE IF NOT EXISTS sync_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_type VARCHAR(50) NOT NULL COMMENT 'orders, items, questions, messages',
    account_id INT NOT NULL,
    last_sync_at DATETIME NULL,
    status ENUM('success', 'error', 'running') DEFAULT 'success',
    last_sync_id VARCHAR(100) NULL COMMENT 'Último ID sincronizado (scroll_id, offset)',
    items_count INT NULL COMMENT 'Total de itens sincronizados',
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Garante unicidade por recurso/conta
    UNIQUE KEY uk_resource_account (resource_type, account_id),
    
    -- Índices para consultas frequentes
    KEY idx_account (account_id),
    KEY idx_status (status),
    KEY idx_last_sync (last_sync_at),
    
    -- FK para ml_accounts
    CONSTRAINT fk_sync_status_account 
        FOREIGN KEY (account_id) 
        REFERENCES ml_accounts(id) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Controle de sincronizações automáticas por recurso/conta';

-- Inserir registros iniciais para contas existentes (opcional)
-- INSERT IGNORE INTO sync_status (resource_type, account_id, status)
-- SELECT 'items', id, 'success' FROM ml_accounts WHERE id NOT IN (SELECT account_id FROM sync_status WHERE resource_type = 'items');
