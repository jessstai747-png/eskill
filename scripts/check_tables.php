<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

try {
    $db = App\Database::getInstance();
    $stmt = $db->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tabelas encontradas: " . count($tables) . "\n\n";
    foreach ($tables as $t) {
        echo "  - {$t}\n";
    }
    
    // Verificar tabelas específicas
    $required = ['users', 'ml_accounts', 'password_resets', 'activity_logs'];
    echo "\nVerificando tabelas essenciais:\n";
    foreach ($required as $table) {
        if (in_array($table, $tables)) {
            echo "  ✅ {$table}\n";
        } else {
            echo "  ❌ {$table} (não encontrada)\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
