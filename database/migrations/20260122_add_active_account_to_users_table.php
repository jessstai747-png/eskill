<?php
declare(strict_types=1);

/**
 * Migração: Adiciona campo de conta ativa na tabela de usuários
 *
 * @version 1.0.0
 * @date 2026-01-22
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;

echo "=== Migração: Adicionar conta ativa aos usuários ===\n\n";

try {
    $db = Database::getInstance();

    echo "Verificando a tabela 'users'...\n";
    $db->exec(
        "ALTER TABLE users\n        ADD COLUMN active_ml_account_id INT NULL DEFAULT NULL,\n        ADD CONSTRAINT fk_active_ml_account\n            FOREIGN KEY (active_ml_account_id)\n            REFERENCES ml_accounts(id)\n            ON DELETE SET NULL;"
    );
    echo "✅ Coluna 'active_ml_account_id' adicionada à tabela 'users'.\n";

    echo "\n=== Migração concluída com sucesso! ===\n";
} catch (\Exception $e) {
    // Idempotência: ignorar erros de coluna/FK já existentes
    $msg = $e->getMessage();
    if (
        strpos($msg, 'Duplicate column name') !== false
        || strpos($msg, 'Duplicate key name') !== false
        || strpos($msg, 'errno: 121') !== false
        || strpos($msg, 'Error 121') !== false
        || strpos($msg, 'Duplicate foreign key constraint name') !== false
    ) {
        echo "✅ Coluna já existe. Nenhuma alteração necessária.\n";
        echo "\n=== Migração concluída com sucesso! ===\n";
    } else {
        echo "❌ Erro: " . $e->getMessage() . "\n";
        throw $e;
    }
}
