<?php
/**
 * Script para criar as tabelas do sistema de EAN
 * Execute: php scripts/setup_ean_tables.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use App\Database;

echo "🚀 Criando tabelas do Sistema de EAN...\n\n";

try {
    $db = Database::getInstance();
    
    // Ler o arquivo SQL
    $sqlFile = __DIR__ . '/../database/migrations/create_ean_tables.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Arquivo SQL não encontrado: {$sqlFile}");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Separar comandos (ignorando comentários e linhas vazias)
    $commands = [];
    $currentCommand = '';
    $inComment = false;
    
    foreach (explode("\n", $sql) as $line) {
        $trimmedLine = trim($line);
        
        // Ignorar comentários de linha
        if (strpos($trimmedLine, '--') === 0) {
            continue;
        }
        
        // Ignorar linhas vazias
        if (empty($trimmedLine)) {
            continue;
        }
        
        $currentCommand .= $line . "\n";
        
        // Se termina com ; é fim do comando
        if (substr($trimmedLine, -1) === ';') {
            $commands[] = trim($currentCommand);
            $currentCommand = '';
        }
    }
    
    // Executar cada comando
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($commands as $command) {
        if (empty(trim($command))) {
            continue;
        }
        
        try {
            $db->exec($command);
            $successCount++;
            
            // Mostrar o que foi criado
            if (preg_match('/CREATE TABLE.*?(\w+)/i', $command, $matches)) {
                echo "✅ Tabela criada: {$matches[1]}\n";
            } elseif (preg_match('/CREATE.*?VIEW.*?(\w+)/i', $command, $matches)) {
                echo "✅ View criada: {$matches[1]}\n";
            } elseif (preg_match('/INSERT INTO\s+(\w+)/i', $command, $matches)) {
                echo "✅ Dados inseridos em: {$matches[1]}\n";
            }
        } catch (PDOException $e) {
            // Ignorar erros de "já existe"
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "⚠️  Já existe (ignorado)\n";
            } else {
                echo "❌ Erro: " . $e->getMessage() . "\n";
                $errorCount++;
            }
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "📊 Resumo:\n";
    echo "   ✅ Comandos executados: {$successCount}\n";
    echo "   ❌ Erros: {$errorCount}\n";
    echo str_repeat("=", 50) . "\n";
    
    // Verificar tabelas criadas
    echo "\n📋 Verificando tabelas criadas:\n";
    $tables = ['ean_packages', 'ean_inventory', 'ean_purchases', 'ean_assignments', 'ean_balances', 'ean_transactions', 'ean_settings'];
    
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->fetch()) {
            $countStmt = $db->query("SELECT COUNT(*) as total FROM {$table}");
            $count = $countStmt->fetch()['total'];
            echo "   ✅ {$table} ({$count} registros)\n";
        } else {
            echo "   ❌ {$table} NÃO ENCONTRADA\n";
        }
    }
    
    echo "\n🎉 Setup do Sistema de EAN concluído!\n";
    
} catch (Exception $e) {
    echo "❌ Erro fatal: " . $e->getMessage() . "\n";
    exit(1);
}
