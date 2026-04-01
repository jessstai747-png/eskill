#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Gerador de Migration
 *
 * Cria um novo arquivo de migration com naming padronizado e estrutura pronta.
 *
 * Uso:
 *   php bin/make-migration.php create_nome_da_tabela
 *   php bin/make-migration.php add_coluna_to_tabela
 *   php bin/make-migration.php alter_tabela_descricao
 *   php bin/make-migration.php --php create_nome_da_tabela   (cria .php ao invés de .sql)
 */

$args = array_slice($argv, 1);

$usePHP = false;
$filteredArgs = [];
foreach ($args as $arg) {
    if ($arg === '--php') {
        $usePHP = true;
    } else {
        $filteredArgs[] = $arg;
    }
}

$name = trim($filteredArgs[0] ?? '');

if ($name === '' || in_array($name, ['--help', '-h'], true)) {
    echo <<<HELP
Uso: php bin/make-migration.php [--php] <nome_descritivo>

Exemplos:
  php bin/make-migration.php create_ml_items_table
  php bin/make-migration.php add_active_column_to_users
  php bin/make-migration.php alter_pricing_rules_add_margin
  php bin/make-migration.php --php create_complex_feature_tables

Convenções:
  - Prefixo: create_, add_, alter_, drop_, rename_
  - Nome em snake_case
  - O arquivo será criado em database/migrations/

HELP;
    exit(0);
}

// Normalizar nome: apenas letras, números e underscore
$name = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name));
$name = preg_replace('/_+/', '_', trim($name, '_'));

$timestamp = date('Y_m_d_His');
$ext = $usePHP ? 'php' : 'sql';
$filename = "{$timestamp}_{$name}.{$ext}";
$migrationsDir = __DIR__ . '/../database/migrations';
$filepath = "{$migrationsDir}/{$filename}";

if (!is_dir($migrationsDir)) {
    fwrite(STDERR, "ERROR: Diretório de migrations não encontrado: {$migrationsDir}\n");
    exit(2);
}

if (file_exists($filepath)) {
    fwrite(STDERR, "ERROR: Arquivo já existe: {$filepath}\n");
    exit(2);
}

$dateFormatted = date('Y-m-d');

if ($usePHP) {
    $content = <<<PHP
<?php

declare(strict_types=1);

/**
 * Migration: {$name}
 * Data: {$dateFormatted}
 */

use App\\Database;

\$db = Database::getInstance();

// ============================================================================
// UP — Aplicar migration
// ============================================================================

\$db->exec("
    -- TODO: Escrever SQL aqui
    CREATE TABLE IF NOT EXISTS nome_da_tabela (
        id INT PRIMARY KEY AUTO_INCREMENT,
        -- colunas aqui
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

echo "✅ Migration '{$name}' aplicada com sucesso!\\n";

// ============================================================================
// DOWN — Para reverter manualmente se necessário, execute:
// ALTER TABLE nome_da_tabela DROP COLUMN ...;
// DROP TABLE IF EXISTS nome_da_tabela;
// ============================================================================

PHP;
} else {
    $content = <<<SQL
-- ============================================================================
-- Migration: {$name}
-- Data: {$dateFormatted}
-- ============================================================================

-- TODO: Escrever SQL aqui

-- Exemplo de CREATE TABLE:
-- CREATE TABLE IF NOT EXISTS nome_da_tabela (
--     id INT PRIMARY KEY AUTO_INCREMENT,
--     -- colunas aqui
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--     INDEX idx_exemplo (coluna)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- COMMENT='Descrição da tabela';

-- Exemplo de ALTER TABLE:
-- ALTER TABLE nome_da_tabela ADD COLUMN nova_coluna VARCHAR(255) NULL AFTER coluna_anterior;
-- ALTER TABLE nome_da_tabela ADD INDEX idx_nova_coluna (nova_coluna);

-- ============================================================================
-- Para reverter manualmente:
-- ALTER TABLE nome_da_tabela DROP COLUMN nova_coluna;
-- DROP TABLE IF EXISTS nome_da_tabela;
-- ============================================================================

SQL;
}

file_put_contents($filepath, $content);

echo "✅ Migration criada: database/migrations/{$filename}\n";
echo "   Edite o arquivo e execute: php bin/migrate.php\n";
