<?php
/**
 * Script para executar migrations individuais
 */

// Carregar configuração do .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

require_once __DIR__ . '/../autoload.php';

use App\Database;

$migrationFile = $argv[1] ?? null;

if (!$migrationFile) {
    echo "Uso: php run_migration.php <arquivo_migration.php> [up|down]\n";
    exit(1);
}

$migrationPath = __DIR__ . '/migrations/' . $migrationFile;
if (!file_exists($migrationPath)) {
    echo "Migration não encontrada: $migrationPath\n";
    exit(1);
}

$action = $argv[2] ?? 'up';

echo "=== Executando Migration: $migrationFile ===\n\n";

require_once $migrationPath;

// Detectar nome da classe
$content = file_get_contents($migrationPath);
preg_match('/class\s+(\w+)/', $content, $matches);
$className = $matches[1] ?? null;

if ($className && class_exists($className)) {
    $migration = new $className();
    
    if ($action === 'down' && method_exists($migration, 'down')) {
        $migration->down();
    } elseif (method_exists($migration, 'up')) {
        $migration->up();
    }
    
    echo "\n✅ Migration executada com sucesso!\n";
} else {
    echo "Classe de migration não encontrada no arquivo.\n";
    exit(1);
}
