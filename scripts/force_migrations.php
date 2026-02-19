<?php
/**
 * Força execução de todas as migrações diretamente
 */

// Carregar .env manualmente
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbPort = $_ENV['DB_PORT'] ?? '3306';
$dbName = $_ENV['DB_NAME'] ?? 'mercadolivre_db';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASS'] ?? '';

echo "🔄 Executando migrations diretamente...\n\n";

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    $migrationsDir = __DIR__ . '/../database/migrations';
    $files = glob($migrationsDir . '/*.sql');
    sort($files);
    
    foreach ($files as $file) {
        $filename = basename($file);
        echo "Executando: {$filename}...\n";
        
        $sql = file_get_contents($file);
        
        try {
            // Executar SQL completo (PDO exec pode executar múltiplos statements)
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Se falhar, tentar dividir em statements
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) {
                    $trimmed = trim($stmt);
                    return !empty($trimmed) && !preg_match('/^--/', $trimmed);
                }
            );
            
            foreach ($statements as $statement) {
                $trimmed = trim($statement);
                if (!empty($trimmed)) {
                    try {
                        $pdo->exec($trimmed . ';');
                    } catch (PDOException $e2) {
                        // Ignorar erros de "já existe"
                        if (strpos($e2->getMessage(), 'already exists') === false &&
                            strpos($e2->getMessage(), 'Duplicate') === false &&
                            strpos($e2->getMessage(), 'already exist') === false) {
                            echo "  ⚠️  Aviso: " . $e2->getMessage() . "\n";
                        }
                    }
                }
            }
        }
        
        echo "  ✅ Concluído\n";
    }
    
    // Verificar tabelas criadas
    echo "\n📊 Verificando tabelas criadas...\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Total de tabelas: " . count($tables) . "\n";
    foreach ($tables as $t) {
        echo "  - {$t}\n";
    }
    
    echo "\n✅ Migrations concluídas!\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
