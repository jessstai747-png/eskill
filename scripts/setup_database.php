<?php
/**
 * Script para Configurar Banco de Dados
 * Cria o banco se não existir e executa migrations
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar .env
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    die("❌ Arquivo .env não encontrado! Copie .env.example para .env e configure suas credenciais.");
}

$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbPort = $_ENV['DB_PORT'] ?? '3306';
$dbName = $_ENV['DB_DATABASE'] ?? 'mercadolivre_db';
$dbUser = $_ENV['DB_USERNAME'] ?? 'root';
$dbPass = $_ENV['DB_PASSWORD'] ?? '';

echo "🔧 Configurando banco de dados...\n";
echo "Host: {$dbHost}\n";
echo "Database: {$dbName}\n";
echo "User: {$dbUser}\n\n";

try {
    // Conectar sem especificar banco para criar se necessário
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Verificar se banco existe
    $stmt = $pdo->query("SHOW DATABASES LIKE '{$dbName}'");
    $exists = $stmt->rowCount() > 0;
    
    if (!$exists) {
        echo "📦 Criando banco de dados '{$dbName}'...\n";
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "✅ Banco de dados criado!\n\n";
    } else {
        echo "✅ Banco de dados já existe.\n\n";
    }
    
    // Agora executar migrations
    echo "🔄 Executando migrations...\n";
    
    $db = App\Database::getInstance();
    $migrationsDir = __DIR__ . '/../database/migrations';
    
    $files = glob($migrationsDir . '/*.sql');
    sort($files);
    
function execute_sql_file($pdo, $filepath) {
    $sql = file_get_contents($filepath);
    $delimiter = ';';
    $statements = [];
    $buffer = '';

    $lines = explode("\n", $sql);
    foreach ($lines as $line) {
        $trimmed_line = trim($line);
        if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed_line, $matches)) {
            if ($buffer) {
                $statements[] = $buffer;
            }
            $delimiter = trim($matches[1]);
            $buffer = '';
        } elseif (str_ends_with(trim($line), $delimiter)) {
            $buffer .= $line;
            $statements[] = str_replace($delimiter, '', $buffer);
            $buffer = '';
        } else {
            $buffer .= $line . "\n";
        }
    }

    if ($buffer) {
        $statements[] = $buffer;
    }

    foreach ($statements as $statement) {
        $trimmed_statement = trim($statement);
        if ($trimmed_statement) {
            $pdo->exec($trimmed_statement);
        }
    }
}

// ... (código anterior)

    $executed = 0;
    foreach ($files as $file) {
        $filename = basename($file);
        echo "Executando: {$filename}...\n";
        
        try {
            execute_sql_file($db, $file);
            echo "✅ {$filename} executado com sucesso\n";
            $executed++;
        } catch (Exception $e) {
            // Ignorar erros de "já existe" ou "duplicado"
            $message = $e->getMessage();
            if (strpos($message, 'already exists') !== false || 
                strpos($message, 'Duplicate') !== false ||
                strpos($message, 'already exist') !== false) {
                echo "⚠️  {$filename} já executado (ignorando)\n";
            } else {
                echo "❌ Erro em {$filename}: {$message}\n";
            }
        }
    }
    
    echo "\n✅ Setup concluído! {$executed} migrations executadas.\n";
    
} catch (PDOException $e) {
    die("❌ Erro ao conectar ao banco: " . $e->getMessage() . "\n");
}
