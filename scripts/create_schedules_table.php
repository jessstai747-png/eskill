<?php
/**
 * Script para criar a tabela de agendamentos de clonagem
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;

try {
    $db = Database::getInstance();
    
    echo "Criando tabela clone_schedules...\n";
    
    $sql = file_get_contents(__DIR__ . '/../database/migrations/create_clone_schedules_table.sql');
    $db->exec($sql);
    
    echo "✅ Tabela clone_schedules criada com sucesso!\n";
    
    // Verificar se a tabela foi criada
    $result = $db->query("DESCRIBE clone_schedules")->fetchAll();
    echo "📋 Estrutura da tabela:\n";
    foreach ($result as $row) {
        echo "  - {$row['Field']}: {$row['Type']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}