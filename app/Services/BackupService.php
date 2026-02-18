<?php

namespace App\Services;

use App\Database;
use PDO;

class BackupService
{
    private \PDO $db;
    private string $backupDir;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->backupDir = __DIR__ . '/../../storage/backups';

        // Criar diretório se não existir
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * Cria backup completo do banco de dados
     */
    public function createDatabaseBackup(): array
    {
        $config = require __DIR__ . '/../../config/database.php';
        $dbConfig = $config['connections']['mysql'];

        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $this->backupDir . '/' . $filename;

        try {
            // Usar mysqldump se disponível
            $command = sprintf(
                'mysqldump -h%s -u%s -p%s %s > %s',
                escapeshellarg($dbConfig['host']),
                escapeshellarg($dbConfig['username']),
                escapeshellarg($dbConfig['password']),
                escapeshellarg($dbConfig['database']),
                escapeshellarg($filepath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($filepath)) {
                // Comprimir backup
                $compressed = $this->compressBackup($filepath);

                return [
                    'success' => true,
                    'filename' => basename($compressed),
                    'filepath' => $compressed,
                    'size' => filesize($compressed),
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            } else {
                // Fallback: backup manual via PHP
                return $this->createManualBackup($filepath);
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cria backup manual via PHP
     */
    private function createManualBackup(string $filepath): array
    {
        $config = require __DIR__ . '/../../config/database.php';
        $dbConfig = $config['connections']['mysql'];

        $sql = "-- Backup do banco de dados\n";
        $sql .= "-- Data: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        // Listar todas as tabelas
        $tables = $this->db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $sql .= "-- Estrutura da tabela `{$safeTable}`\n";
            $sql .= "DROP TABLE IF EXISTS `{$safeTable}`;\n";

            $createTable = $this->db->query("SHOW CREATE TABLE `{$safeTable}`")->fetch();
            $sql .= $createTable['Create Table'] . ";\n\n";

            // Dados da tabela
            $rows = $this->db->query("SELECT * FROM `{$safeTable}`")->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $sql .= "-- Dados da tabela `{$safeTable}`\n";
                $columns = array_keys($rows[0]);

                foreach ($rows as $row) {
                    $values = array_map(function ($value) {
                        if ($value === null) {
                            return 'NULL';
                        }
                        return $this->db->quote($value);
                    }, array_values($row));

                    $sql .= "INSERT INTO `{$safeTable}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        file_put_contents($filepath, $sql);

        // Comprimir
        $compressed = $this->compressBackup($filepath);

        return [
            'success' => true,
            'filename' => basename($compressed),
            'filepath' => $compressed,
            'size' => filesize($compressed),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Comprime arquivo de backup
     */
    private function compressBackup(string $filepath): string
    {
        if (extension_loaded('zlib')) {
            $compressedPath = $filepath . '.gz';
            $data = file_get_contents($filepath);
            file_put_contents($compressedPath, gzencode($data, 9));
            unlink($filepath); // Remover arquivo não comprimido
            return $compressedPath;
        }

        return $filepath;
    }

    /**
     * Lista backups disponíveis
     */
    public function listBackups(): array
    {
        $backups = [];
        $files = glob($this->backupDir . '/*.{sql,sql.gz}', GLOB_BRACE);

        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'filepath' => $file,
                'size' => filesize($file),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }

        // Ordenar por data (mais recente primeiro)
        usort($backups, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

        return $backups;
    }

    /**
     * Remove backups antigos (mais de X dias)
     */
    public function cleanOldBackups(int $days = 30): int
    {
        $deleted = 0;
        $files = glob($this->backupDir . '/*.{sql,sql.gz}', GLOB_BRACE);
        $cutoffTime = time() - ($days * 24 * 60 * 60);

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Restaura backup do banco de dados
     */
    public function restoreBackup(string $filename): array
    {
        $filepath = $this->backupDir . '/' . $filename;

        if (!file_exists($filepath)) {
            return [
                'success' => false,
                'error' => 'Arquivo de backup não encontrado',
            ];
        }

        try {
            $sql = file_get_contents($filepath);

            // Descomprimir se necessário
            if (substr($filename, -3) === '.gz') {
                $sql = gzdecode($sql);
            }

            // Executar SQL
            $this->db->exec($sql);

            return [
                'success' => true,
                'message' => 'Backup restaurado com sucesso',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
