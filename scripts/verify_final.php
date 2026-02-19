<?php
require_once __DIR__ . '/../vendor/autoload.php';

echo "🔍 Verificação Final do Sistema\n\n";

// Verificar usando Database::getInstance
try {
    $db = App\Database::getInstance();
    $stmt = $db->query('SELECT DATABASE() as db');
    $currentDb = $stmt->fetch();
    echo "Banco atual (via Database::getInstance): " . ($currentDb['db'] ?? 'N/A') . "\n";
    
    $stmt = $db->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tabelas encontradas: " . count($tables) . "\n";
    foreach ($tables as $t) {
        echo "  - {$t}\n";
    }
    
    // Verificar tabelas específicas
    $required = ['users', 'ml_accounts', 'password_resets', 'activity_logs'];
    echo "\nVerificando tabelas essenciais:\n";
    foreach ($required as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            echo "  ✅ {$table}\n";
        } else {
            echo "  ❌ {$table} (não encontrada)\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
