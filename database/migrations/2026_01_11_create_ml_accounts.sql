-- Migration: Adicionar colunas extras em ml_accounts (scopes, tokens_encrypted)
-- Criado: 2026-01-11
-- Nota: CREATE TABLE foi consolidado em 002_create_ml_accounts_table.sql
--       Este arquivo apenas adiciona colunas que a versão inicial não tinha.

ALTER TABLE ml_accounts
    ADD COLUMN IF NOT EXISTS scopes VARCHAR(255) NULL COMMENT 'Escopos OAuth autorizados' AFTER last_synced_at,
    ADD COLUMN IF NOT EXISTS tokens_encrypted TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Se 1, access/refresh tokens estão criptografados' AFTER scopes;

-- Ampliar ENUM status para incluir 'disconnected'
ALTER TABLE ml_accounts
    MODIFY COLUMN status ENUM('active', 'inactive', 'expired', 'disconnected') DEFAULT 'active';
