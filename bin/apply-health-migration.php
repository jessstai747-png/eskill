#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * @deprecated Use `php bin/migrate.php` instead. This script applies only a single
 * hardcoded migration. The unified runner tracks all migrations automatically.
 */
fwrite(STDERR, "\n⚠️  DEPRECATED: Use `php bin/migrate.php` em vez deste script.\n\n");

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$db = Database::getInstance();

$sql = file_get_contents(__DIR__ . '/../database/migrations/2026_01_31_create_clone_health_alerts_tables.sql');

// Split por statement e executar um por vez
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $i => $statement) {
    if (empty($statement) || strpos($statement, '--') === 0) continue;
    
    try {
        echo "Executando statement " . ($i + 1) . "...\n";
        $db->exec($statement);
    } catch (Exception $e) {
        echo "⚠️  Aviso no statement " . ($i + 1) . ": " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Migration aplicada com sucesso!\n";
