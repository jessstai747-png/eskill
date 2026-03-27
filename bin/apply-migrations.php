#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * @deprecated Use `php bin/migrate.php` instead. This script applies only a single
 * hardcoded migration. The unified runner (`bin/migrate.php`) tracks all .sql and .php
 * migrations automatically.
 */
fwrite(STDERR, "\n⚠️  DEPRECATED: Este script aplica apenas 1 migration hardcoded.\n");
fwrite(STDERR, "   Use: php bin/migrate.php            (aplicar todas pendentes)\n");
fwrite(STDERR, "   Use: php bin/migrate.php --status   (ver status)\n\n");

// Apply specific migration(s) safely via PDO
// Usage: php bin/apply-migrations.php

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

echo "Connecting to database (driver={$driver}) on host={$host} db={$dbname}...\n";

try {
    if ($driver === 'sqlite' || strpos($dbname, 'sqlite:') === 0) {
        $pdo = new PDO($dbname);
    } else {
        $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=utf8mb4', $driver, $host, $port, $dbname);
        $pdo = new PDO($dsn, $user, $pass, $conn['options'] ?? []);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    echo "ERROR: Failed to connect to DB: " . $e->getMessage() . "\n";
    exit(2);
}

$migration = __DIR__ . '/../database/migrations/2026_01_22_create_seo_synonyms_tables.sql';
if (!file_exists($migration)) {
    echo "Migration file not found: $migration\n";
    exit(2);
}

$sql = file_get_contents($migration);
if ($sql === false) {
    echo "Failed to read migration file.\n";
    exit(2);
}

try {
    // Execute migration SQL directly (DDL statements are auto-committed on many engines)
    $pdo->exec($sql);
    echo "Migration applied successfully.\n";
} catch (Throwable $e) {
    echo "ERROR: Migration failed: " . $e->getMessage() . "\n";
    exit(3);
}

// Validate schema: show create table (MySQL) or PRAGMA table_info (SQLite)
try {
    if ($driver === 'mysql') {
        // --- seo_synonym_hierarchy ---
        $stmt = $pdo->query("SHOW CREATE TABLE seo_synonym_hierarchy");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "\n--- SHOW CREATE TABLE seo_synonym_hierarchy ---\n";
        echo ($row['Create Table'] ?? json_encode($row)) . "\n";

        $idx = $pdo->query("SHOW INDEX FROM seo_synonym_hierarchy")->fetchAll(PDO::FETCH_ASSOC);
        echo "\n--- INDEXES ---\n";
        foreach ($idx as $i) {
            echo sprintf("%s: Column=%s, KeyName=%s, Non_unique=%s\n", $i['Table'], $i['Column_name'], $i['Key_name'], $i['Non_unique']);
        }

        // --- seo_use_contexts ---
        $stmt = $pdo->query("SHOW CREATE TABLE seo_use_contexts");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "\n--- SHOW CREATE TABLE seo_use_contexts ---\n";
        echo ($row['Create Table'] ?? json_encode($row)) . "\n";

        $idx = $pdo->query("SHOW INDEX FROM seo_use_contexts")->fetchAll(PDO::FETCH_ASSOC);
        echo "\n--- INDEXES ---\n";
        foreach ($idx as $i) {
            echo sprintf("%s: Column=%s, KeyName=%s, Non_unique=%s\n", $i['Table'], $i['Column_name'], $i['Key_name'], $i['Non_unique']);
        }

    } else {
        // --- seo_synonym_hierarchy ---
        echo "\n--- SQLite schema (seo_synonym_hierarchy) ---\n";
        $stmt = $pdo->query("PRAGMA table_info('seo_synonym_hierarchy')");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            echo sprintf("%s %s %s\n", $c['name'], $c['type'], $c['notnull'] ? 'NOT NULL' : 'NULL');
        }

        $idx = $pdo->query("PRAGMA index_list('seo_synonym_hierarchy')")->fetchAll(PDO::FETCH_ASSOC);
        echo "\n--- INDEXES ---\n";
        foreach ($idx as $i) {
            echo sprintf("%s: name=%s, unique=%s\n", 'seo_synonym_hierarchy', $i['name'], $i['unique']);
        }

        // --- seo_use_contexts ---
        echo "\n--- SQLite schema (seo_use_contexts) ---\n";
        $stmt = $pdo->query("PRAGMA table_info('seo_use_contexts')");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            echo sprintf("%s %s %s\n", $c['name'], $c['type'], $c['notnull'] ? 'NOT NULL' : 'NULL');
        }

        $idx = $pdo->query("PRAGMA index_list('seo_use_contexts')")->fetchAll(PDO::FETCH_ASSOC);
        echo "\n--- INDEXES ---\n";
        foreach ($idx as $i) {
            echo sprintf("%s: name=%s, unique=%s\n", 'seo_use_contexts', $i['name'], $i['unique']);
        }
    }
} catch (Throwable $e) {
    echo "ERROR while validating schema: " . $e->getMessage() . "\n";
    exit(4);
}

echo "\nMigration verification complete.\n";

return 0;
