#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Apply Pricing Intelligence Migration
 * 
 * @deprecated Use `php bin/migrate.php` instead. This script applies only a single
 * hardcoded migration. The unified runner tracks all migrations automatically.
 *
 * Aplica a migração das tabelas do módulo de precificação inteligente.
 * Usage: php bin/apply-pricing-migration.php
 */
fwrite(STDERR, "\n⚠️  DEPRECATED: Use `php bin/migrate.php` em vez deste script.\n\n");

require __DIR__ . '/../vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$configDb = require __DIR__ . '/../config/database.php';
$default = $configDb['default'] ?? 'mysql';
$conn = $configDb['connections'][$default] ?? $configDb['connections']['mysql'];

$driver = $conn['driver'] ?? 'mysql';
$host = $conn['host'] ?? '127.0.0.1';
$port = $conn['port'] ?? 3306;
$dbname = $conn['database'] ?? '';
$user = $conn['username'] ?? $conn['user'] ?? 'root';
$pass = $conn['password'] ?? '';

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║     APLICAR MIGRAÇÃO - PRECIFICAÇÃO INTELIGENTE                 ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

echo "Conectando ao banco de dados...\n";
echo "  Driver: {$driver}\n";
echo "  Host: {$host}:{$port}\n";
echo "  Database: {$dbname}\n\n";

try {
    if ($driver === 'sqlite' || strpos($dbname, 'sqlite:') === 0) {
        $pdo = new PDO($dbname);
    } else {
        $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=utf8mb4', $driver, $host, $port, $dbname);
        $pdo = new PDO($dsn, $user, $pass, $conn['options'] ?? []);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Conexão estabelecida\n\n";
} catch (Throwable $e) {
    echo "✗ Falha na conexão: " . $e->getMessage() . "\n";
    exit(2);
}

// Verificar tabelas existentes
$requiredTables = ['product_costs', 'pricing_history', 'pricing_rules', 'competitor_pricing_cache', 'promotion_simulations', 'pricing_ranking_alerts'];
$existingTables = [];

echo "Verificando tabelas existentes...\n";
foreach ($requiredTables as $table) {
    $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
    $stmt->execute(['table' => $table]);
    if ($stmt->rowCount() > 0) {
        $existingTables[] = $table;
        echo "  ⚠ {$table} já existe\n";
    } else {
        echo "  ○ {$table} não existe\n";
    }
}

if (count($existingTables) === count($requiredTables)) {
    echo "\n✓ Todas as tabelas já existem. Nada a fazer.\n";
    exit(0);
}

// Ler arquivo de migração
$migrationFile = __DIR__ . '/../database/migrations/2026_01_29_create_pricing_intelligence_tables.sql';
if (!file_exists($migrationFile)) {
    echo "\n✗ Arquivo de migração não encontrado: {$migrationFile}\n";
    exit(2);
}

$sql = file_get_contents($migrationFile);
if ($sql === false) {
    echo "\n✗ Falha ao ler arquivo de migração.\n";
    exit(2);
}

echo "\nAplicando migração...\n";

// Dividir SQL em statements individuais e executar
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    fn($s) => !empty($s) && !preg_match('/^--/', $s)
);

$applied = 0;
$skipped = 0;
$errors = 0;

foreach ($statements as $statement) {
    if (empty($statement)) {
        continue;
    }

    // Extrair nome da tabela do CREATE TABLE
    if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
        $tableName = $matches[1];

        if (in_array($tableName, $existingTables)) {
            echo "  ⚠ Pulando {$tableName} (já existe)\n";
            $skipped++;
            continue;
        }
    }

    try {
        $pdo->exec($statement);

        if (preg_match('/CREATE TABLE/i', $statement)) {
            if (isset($tableName)) {
                echo "  ✓ Criada tabela {$tableName}\n";
            }
            $applied++;
        } elseif (preg_match('/CREATE INDEX/i', $statement)) {
            echo "  ✓ Criado índice\n";
            $applied++;
        }
    } catch (PDOException $e) {
        // Ignorar erro de tabela já existe
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "  ⚠ Tabela já existe, pulando...\n";
            $skipped++;
        } else {
            echo "  ✗ Erro: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                      RESULTADO                                  ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "  Aplicados: {$applied}\n";
echo "  Pulados: {$skipped}\n";
echo "  Erros: {$errors}\n\n";

if ($errors === 0) {
    echo "✓ Migração concluída com sucesso!\n";
    exit(0);
} else {
    echo "⚠ Migração concluída com erros.\n";
    exit(1);
}
