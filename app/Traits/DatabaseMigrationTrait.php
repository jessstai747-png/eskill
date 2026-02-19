<?php

namespace App\Traits;

use PDO;

/**
 * Trait para operações de migração de schema compartilhadas entre Services.
 * Substitui as múltiplas cópias de addColumnIfMissing em ItemSyncService,
 * AccountSyncService, BrevoPersistenceRepository, etc.
 */
trait DatabaseMigrationTrait
{
    /**
     * Verifica se coluna existe em uma tabela
     */
    protected function columnExists(PDO $db, string $table, string $column): bool
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $stmt = $db->query("SHOW COLUMNS FROM `{$safeTable}` LIKE " . $db->quote($column));
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Adiciona coluna se não existir, com sanitização de segurança
     */
    protected function addColumnIfMissing(PDO $db, string $table, string $column, string $definition): void
    {
        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $columnName = preg_replace('/[^a-zA-Z0-9_]/', '', $column);

        // Security: validate $definition starts with a valid SQL column type
        if (!preg_match('/^(VARCHAR|INT|BIGINT|TEXT|LONGTEXT|MEDIUMTEXT|TINYINT|SMALLINT|DECIMAL|FLOAT|DOUBLE|DATE|DATETIME|TIMESTAMP|BOOLEAN|ENUM|JSON|BLOB)/i', trim($definition))) {
            throw new \InvalidArgumentException('Invalid column definition type');
        }

        if ($this->columnExists($db, $tableName, $columnName)) {
            return;
        }

        $db->exec("ALTER TABLE `{$tableName}` ADD COLUMN `{$columnName}` {$definition}");
    }
}
