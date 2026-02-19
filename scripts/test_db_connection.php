<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Carregar .env manualmente
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
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

echo "Testando conexão...\n";
echo "Host: {$dbHost}\n";
echo "Port: {$dbPort}\n";
echo "Database: {$dbName}\n";
echo "User: {$dbUser}\n\n";

try {
    // Conectar sem especificar banco
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Conexão com MySQL OK\n\n";
    
    // Verificar se banco existe
    $stmt = $pdo->query("SHOW DATABASES LIKE '{$dbName}'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Banco '{$dbName}' existe\n";
        
        // Selecionar banco
        $pdo->exec("USE `{$dbName}`");
        
        // Listar tabelas
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "\nTabelas encontradas: " . count($tables) . "\n";
        foreach ($tables as $t) {
            echo "  - {$t}\n";
        }
        
        if (count($tables) === 0) {
            echo "\n⚠️  Nenhuma tabela encontrada. Executando migrations...\n";
            
            // Executar uma migration de teste
            $testSql = "CREATE TABLE IF NOT EXISTS test_table (id INT PRIMARY KEY AUTO_INCREMENT)";
            $pdo->exec($testSql);
            echo "✅ Tabela de teste criada\n";
            
            // Verificar novamente
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "\nTabelas após teste: " . count($tables) . "\n";
        }
        
    } else {
        echo "❌ Banco '{$dbName}' não existe\n";
        echo "Criando banco...\n";
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "✅ Banco criado\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
