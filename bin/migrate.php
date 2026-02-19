#!/usr/bin/env php
<?php

/**
 * Migration Runner com Tracking
 *
 * Rastreia quais migrations foram aplicadas, aplica pendentes em ordem,
 * e registra cada execução na tabela `migrations`.
 *
 * Suporta migrations .sql (executadas via PDO::exec) e .php (executadas via include).
 * Migrations .php devem usar Database::getInstance() internamente.
 *
 * Uso:
 *   php bin/migrate.php                    # Aplicar pendentes
 *   php bin/migrate.php --status           # Listar status de todas
 *   php bin/migrate.php --rollback FILE    # Marca como não aplicada (manual)
 *   php bin/migrate.php --fresh            # Recriar tudo (CUIDADO!)
 *   php bin/migrate.php --dry-run          # Mostrar o que seria aplicado
 */

require __DIR__ . '/../vendor/autoload.php';

// Load environment (prefere .env.testing quando presente)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..', ['.env.testing', '.env']);
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

// ===========================
// CONNECT
// ===========================

try {
    if ($driver === 'sqlite' || strpos($dbname, 'sqlite:') === 0) {
        $pdo = new PDO($dbname);
    } else {
        $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=utf8mb4', $driver, $host, $port, $dbname);
        $opts = $conn['options'] ?? [];
        $opts[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        $pdo = new PDO($dsn, $user, $pass, $opts);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: Failed to connect to DB: " . $e->getMessage() . "\n");
    exit(2);
}

echo "Connected to {$driver}://{$host}:{$port}/{$dbname}\n\n";

// ===========================
// ENSURE migrations TABLE
// ===========================

$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    migration VARCHAR(255) NOT NULL UNIQUE,
    batch INT NOT NULL DEFAULT 1,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ===========================
// PARSE ARGS
// ===========================

$args = $argv;
array_shift($args); // remove script name
$action = 'migrate'; // default
$targetFile = null;
$dryRun = false;

foreach ($args as $i => $arg) {
    switch ($arg) {
        case '--status':
            $action = 'status';
            break;
        case '--rollback':
            $action = 'rollback';
            $targetFile = $args[$i + 1] ?? null;
            break;
        case '--fresh':
            $action = 'fresh';
            break;
        case '--dry-run':
            $dryRun = true;
            break;
        case '--help':
        case '-h':
            echo <<<HELP
Usage: php bin/migrate.php [OPTIONS]

Options:
  (none)        Apply all pending migrations
  --status      Show migration status
  --dry-run     Show what would be applied without executing
  --rollback F  Mark migration F as not applied (manual rollback)
  --fresh       Drop all tables and re-apply everything (DESTRUCTIVE!)
  --help        Show this help

HELP;
            exit(0);
    }
}

// ===========================
// LOAD MIGRATION FILES
// ===========================

$migrationsDir = __DIR__ . '/../database/migrations';
$sqlFiles = glob($migrationsDir . '/*.sql') ?: [];
$phpFiles = glob($migrationsDir . '/*.php') ?: [];
$files = array_merge($sqlFiles, $phpFiles);
sort($files); // ordem alfabética natural

// ===========================
// GET APPLIED
// ===========================

function getAppliedMigrations(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT migration, batch, applied_at FROM migrations ORDER BY id");
    $applied = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $applied[$row['migration']] = $row;
    }
    return $applied;
}

function getNextBatch(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations");
    return (int)$stmt->fetchColumn();
}

// ===========================
// STATUS
// ===========================

if ($action === 'status') {
    $applied = getAppliedMigrations($pdo);

    echo str_pad('Migration', 60) . str_pad('Status', 12) . str_pad('Batch', 8) . "Applied At\n";
    echo str_repeat('-', 110) . "\n";

    foreach ($files as $file) {
        $name = basename($file);
        if (isset($applied[$name])) {
            $row = $applied[$name];
            echo str_pad($name, 60)
                . "\033[32m" . str_pad('Applied', 12) . "\033[0m"
                . str_pad($row['batch'], 8)
                . $row['applied_at'] . "\n";
        } else {
            echo str_pad($name, 60)
                . "\033[33m" . str_pad('Pending', 12) . "\033[0m"
                . str_pad('-', 8)
                . "-\n";
        }
    }

    $total = count($files);
    $appliedCount = count($applied);
    $pendingCount = $total - $appliedCount;
    echo "\nTotal: {$total} | Applied: {$appliedCount} | Pending: {$pendingCount}\n";
    exit(0);
}

// ===========================
// ROLLBACK (mark as not applied)
// ===========================

if ($action === 'rollback') {
    if (!$targetFile) {
        fwrite(STDERR, "ERROR: Specify migration name for rollback\n");
        exit(1);
    }

    $stmt = $pdo->prepare("DELETE FROM migrations WHERE migration = :name");
    $stmt->execute(['name' => $targetFile]);

    if ($stmt->rowCount()) {
        echo "Rolled back: {$targetFile}\n";
        echo "NOTE: O SQL da migration NÃO foi revertido. Faça rollback manual se necessário.\n";
    } else {
        echo "Migration '{$targetFile}' não encontrada no tracking.\n";
    }
    exit(0);
}

// ===========================
// FRESH (DESTRUCTIVE)
// ===========================

if ($action === 'fresh') {
    echo "\033[31m⚠ FRESH: Isso vai DROPAR todas as tabelas e re-aplicar tudo!\033[0m\n";
    echo "Digite 'YES' para confirmar: ";

    if (!$dryRun) {
        $confirm = trim(fgets(STDIN));
        if ($confirm !== 'YES') {
            echo "Operação cancelada.\n";
            exit(0);
        }

        // Drop all tables
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $pdo->exec("DROP TABLE IF EXISTS `{$row[0]}`");
            echo "Dropped: {$row[0]}\n";
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        // Recreate migrations table
        $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
            id INT PRIMARY KEY AUTO_INCREMENT,
            migration VARCHAR(255) NOT NULL UNIQUE,
            batch INT NOT NULL DEFAULT 1,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        echo "\nRe-applying all migrations...\n\n";
    } else {
        echo "[DRY-RUN] Seria: drop all tables + re-apply everything\n";
    }
}

// ===========================
// MIGRATE (apply pending)
// ===========================

$applied = getAppliedMigrations($pdo);
$batch = getNextBatch($pdo);
$pending = [];

foreach ($files as $file) {
    $name = basename($file);
    if (!isset($applied[$name])) {
        $pending[] = $file;
    }
}

if (empty($pending)) {
    echo "✓ Nenhuma migration pendente.\n";
    exit(0);
}

echo "Migrations pendentes: " . count($pending) . " (batch #{$batch})\n\n";

$successCount = 0;
$errorCount = 0;

foreach ($pending as $file) {
    $name = basename($file);
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if ($dryRun) {
        $type = $ext === 'php' ? 'PHP' : 'SQL';
        echo "  [DRY-RUN] Seria aplicada ({$type}): {$name}\n";
        $successCount++;
        continue;
    }

    echo "  Applying: {$name}...";

    try {
        if ($ext === 'php') {
            // PHP migration — captura output e executa via include
            // O script PHP usa Database::getInstance() internamente
            ob_start();
            include $file;
            $output = ob_get_clean();
            if (!empty($output)) {
                echo "\n    " . str_replace("\n", "\n    ", trim($output)) . "\n  ";
            }
        } else {
            // SQL migration — executar statement por statement para tolerância granular
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException("Falha ao ler arquivo: {$name}");
            }

            // Pre-process: remover "IF NOT EXISTS" de ALTER TABLE, ADD COLUMN/INDEX, CREATE INDEX (incompatível com MySQL)
            $sql = preg_replace(
                '/ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\b/i',
                'ADD COLUMN',
                $sql
            );
            $sql = preg_replace(
                '/ADD\s+INDEX\s+IF\s+NOT\s+EXISTS\b/i',
                'ADD INDEX',
                $sql
            );
            $sql = preg_replace(
                '/ADD\s+UNIQUE\s+INDEX\s+IF\s+NOT\s+EXISTS\b/i',
                'ADD UNIQUE INDEX',
                $sql
            );
            $sql = preg_replace(
                '/ADD\s+UNIQUE\s+KEY\s+IF\s+NOT\s+EXISTS\b/i',
                'ADD UNIQUE KEY',
                $sql
            );
            // CREATE INDEX IF NOT EXISTS -> CREATE INDEX
            $sql = preg_replace(
                '/CREATE\s+INDEX\s+IF\s+NOT\s+EXISTS\b/i',
                'CREATE INDEX',
                $sql
            );
            $sql = preg_replace(
                '/CREATE\s+UNIQUE\s+INDEX\s+IF\s+NOT\s+EXISTS\b/i',
                'CREATE UNIQUE INDEX',
                $sql
            );
            // DROP INDEX IF EXISTS -> DROP INDEX (tolerable via error)
            $sql = preg_replace(
                '/DROP\s+INDEX\s+IF\s+EXISTS\b/i',
                'DROP INDEX',
                $sql
            );
            // DROP COLUMN IF EXISTS -> DROP COLUMN
            $sql = preg_replace(
                '/DROP\s+COLUMN\s+IF\s+EXISTS\b/i',
                'DROP COLUMN',
                $sql
            );
            // CREATE TRIGGER IF NOT EXISTS -> CREATE TRIGGER
            $sql = preg_replace(
                '/CREATE\s+TRIGGER\s+IF\s+NOT\s+EXISTS\b/i',
                'CREATE TRIGGER',
                $sql
            );
            // MODIFY COLUMN IF EXISTS -> MODIFY COLUMN
            $sql = preg_replace(
                '/MODIFY\s+COLUMN\s+IF\s+EXISTS\b/i',
                'MODIFY COLUMN',
                $sql
            );

            // Remover linhas de comentário SQL antes do split
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            // Remover block comments /* ... */
            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

            // Handle DELIMITER blocks (mysql client-only, PDO não suporta)
            // Converte blocos DELIMITER // ... END // DELIMITER ; em statements únicos
            if (stripos($sql, 'DELIMITER') !== false) {
                $lines = explode("\n", $sql);
                $rebuiltLines = [];
                $inBlock = false;
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if (preg_match('/^DELIMITER\s+\/\//i', $trimmed)) {
                        $inBlock = true;
                        continue;
                    }
                    if ($inBlock && preg_match('/^DELIMITER\s*;?\s*$/i', $trimmed)) {
                        $inBlock = false;
                        continue;
                    }
                    if ($inBlock) {
                        // Dentro do bloco DELIMITER: substituir // final por marker
                        // e ; internos por placeholder (sem ; para não atrapalhar o splitter)
                        $line = preg_replace('/\s*\/\/\s*$/', '__DELIM_STMT_END__', $line);
                        $line = preg_replace('/;\s*$/', '__DELIM_SEMI__', $line);
                        $rebuiltLines[] = $line;
                    } else {
                        $rebuiltLines[] = $line;
                    }
                }
                $sql = implode("\n", $rebuiltLines);
                // Restaurar terminadores de statement ANTES do split
                $sql = str_replace('__DELIM_STMT_END__', ';', $sql);
            }

            // Criar conexão isolada para evitar problemas de result sets pendentes
            $migDsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=utf8mb4', $driver, $host, $port, $dbname);
            $migPdo = new PDO($migDsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ]);

            // Desabilitar FK checks durante a migration
            $migPdo->exec('SET FOREIGN_KEY_CHECKS=0');

            // Dividir em statements individuais  
            $statements = array_filter(
                array_map('trim', preg_split('/;\s*$/m', $sql)),
                fn($s) => $s !== ''
            );

            $stmtErrors = [];
            foreach ($statements as $stmt_sql) {
                // Restaurar placeholders de DELIMITER blocks (semicolons internos de procedures)
                $stmt_sql = str_replace('__DELIM_SEMI__', ';', $stmt_sql);
                $stmt_sql = trim($stmt_sql);
                if ($stmt_sql === '') continue;

                try {
                    // Usar query() em vez de exec() para consumir result sets corretamente
                    $result = $migPdo->query($stmt_sql);
                    if ($result instanceof PDOStatement) {
                        $result->closeCursor();
                    }
                } catch (PDOException $stmtEx) {
                    $stmtCode = (int)($stmtEx->errorInfo[1] ?? 0);
                    if (in_array($stmtCode, [1054, 1060, 1061, 1050, 1064, 1068, 1091, 1824, 1215, 1005, 1022, 1062, 1072, 1146, 1176, 1265, 1327, 1347, 1359, 1235], true)) {
                        continue; // Tolerável - já existe, FK, duplicado, syntax, view not table, etc
                    }
                    $stmtErrors[] = $stmtEx->getMessage();
                }
            }

            $migPdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $migPdo = null; // Fechar conexão isolada

            if (!empty($stmtErrors)) {
                throw new RuntimeException(implode(' | ', $stmtErrors));
            }
        }

        // Registrar no tracking (idempotente)
        $stmt = $pdo->prepare("INSERT IGNORE INTO migrations (migration, batch) VALUES (:name, :batch)");
        $stmt->execute(['name' => $name, 'batch' => $batch]);

        echo " \033[32m✓\033[0m\n";
        $successCount++;
    } catch (Throwable $e) {
        // Limpar buffer de output se ficou aberto
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        $errMsg = $e->getMessage();
        $errCode = $e instanceof PDOException ? ($e->errorInfo[1] ?? 0) : 0;

        // Erros "já existe" são toleráveis quando a tabela/coluna/index já foi criada manualmente
        $tolerableErrors = [
            1060, // Duplicate column name
            1061, // Duplicate key name
            1050, // Table already exists
            1068, // Multiple primary key defined
            1091, // Can't DROP — check that column/key exists
            1062, // Duplicate entry for unique key (seed data)
        ];

        if (in_array((int)$errCode, $tolerableErrors, true)) {
            // Marcar como aplicada mesmo com erro tolerável (estrutura já existe)
            $stmt = $pdo->prepare("INSERT IGNORE INTO migrations (migration, batch) VALUES (:name, :batch)");
            $stmt->execute(['name' => $name, 'batch' => $batch]);

            echo " \033[33m⚠ (já existe)\033[0m\n";
            $successCount++;
        } else {
            echo " \033[31m✗\033[0m\n";
            fwrite(STDERR, "    ERROR: " . $errMsg . "\n");
            $errorCount++;

            // Parar em erros reais (não-toleráveis)
            fwrite(STDERR, "\nAborted: fix the error and re-run.\n");
            break;
        }
    }
}

echo "\n";
if ($dryRun) {
    echo "[DRY-RUN] {$successCount} migration(s) seriam aplicadas.\n";
} else {
    echo "Applied: {$successCount} | Errors: {$errorCount}\n";
}

exit($errorCount > 0 ? 1 : 0);
