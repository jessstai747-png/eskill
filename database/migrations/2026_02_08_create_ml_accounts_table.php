<?php

declare(strict_types=1);

/**
 * Migration: Consolidar ml_accounts — garantir colunas e índices canônicos
 *
 * O CREATE TABLE base está em 002_create_ml_accounts_table.sql.
 * Este script garante que todas as colunas e índices necessários existam,
 * independentemente da ordem em que a tabela foi criada.
 */

use App\Database;

$db = Database::getInstance();

// Garantir tokens_encrypted (adicionado em 2026_01_11)
try {
    $db->exec("ALTER TABLE ml_accounts ADD COLUMN tokens_encrypted TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Se 1, tokens estão criptografados' AFTER refresh_token");
} catch (Throwable $e) { /* já existe */ }

// Garantir scopes (adicionado em 2026_01_11)
try {
    $db->exec("ALTER TABLE ml_accounts ADD COLUMN scopes VARCHAR(255) NULL COMMENT 'Escopos OAuth autorizados' AFTER last_synced_at");
} catch (Throwable $e) { /* já existe */ }

// Garantir status ENUM inclui 'disconnected'
try {
    $db->exec("ALTER TABLE ml_accounts MODIFY COLUMN status ENUM('active', 'inactive', 'expired', 'disconnected') DEFAULT 'active'");
} catch (Throwable $e) { /* já atualizado */ }

// Garantir índice em token_expires_at (para queries de expiração)
try {
    $db->exec("CREATE INDEX idx_token_expires_at ON ml_accounts(token_expires_at)");
} catch (Throwable $e) { /* já existe */ }

echo "✅ ml_accounts consolidada com sucesso!\n";
