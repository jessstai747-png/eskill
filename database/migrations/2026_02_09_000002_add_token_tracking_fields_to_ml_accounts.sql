-- Migration: Add Token Tracking Fields to ml_accounts
-- Created: 2026-02-09
-- Purpose: Adicionar campos para rastrear histórico de refresh e diagnóstico de problemas
-- Related: FASE 2 - Sistema de Monitoramento de Tokens

-- Verificar se as colunas já existem antes de adicionar
SET @dbname = DATABASE();
SET @tablename = 'ml_accounts';

-- Adicionar last_refresh_at se não existir
SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND COLUMN_NAME = 'last_refresh_at'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE ml_accounts ADD COLUMN last_refresh_at DATETIME NULL COMMENT ''Última renovação bem-sucedida de token''',
    'SELECT ''Column last_refresh_at already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar refresh_failure_count se não existir
SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND COLUMN_NAME = 'refresh_failure_count'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE ml_accounts ADD COLUMN refresh_failure_count INT UNSIGNED DEFAULT 0 NOT NULL COMMENT ''Contador de falhas consecutivas de renovação''',
    'SELECT ''Column refresh_failure_count already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar last_refresh_error se não existir
SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND COLUMN_NAME = 'last_refresh_error'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE ml_accounts ADD COLUMN last_refresh_error TEXT NULL COMMENT ''Última mensagem de erro em tentativa de refresh''',
    'SELECT ''Column last_refresh_error already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar last_oauth_connection_at se não existir
SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND COLUMN_NAME = 'last_oauth_connection_at'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE ml_accounts ADD COLUMN last_oauth_connection_at DATETIME NULL COMMENT ''Última autorização OAuth realizada pelo usuário''',
    'SELECT ''Column last_oauth_connection_at already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar índices para otimizar queries de monitoramento
SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND INDEX_NAME = 'idx_last_refresh_at'
);

SET @sql = IF(@index_exists = 0,
    'CREATE INDEX idx_last_refresh_at ON ml_accounts(last_refresh_at)',
    'SELECT ''Index idx_last_refresh_at already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice composto para dashboard de saúde
SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = @dbname 
    AND TABLE_NAME = @tablename 
    AND INDEX_NAME = 'idx_health_dashboard'
);

SET @sql = IF(@index_exists = 0,
    'CREATE INDEX idx_health_dashboard ON ml_accounts(status, token_expires_at, refresh_failure_count)',
    'SELECT ''Index idx_health_dashboard already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
