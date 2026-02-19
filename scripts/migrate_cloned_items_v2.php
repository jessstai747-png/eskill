<?php
/**
 * Migration: Adiciona campos extras à tabela cloned_items para rastreamento avançado
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$db = App\Database::getInstance();

echo "Atualizando tabela cloned_items...\n";

try {
    // Verificar colunas existentes
    $stmt = $db->query("DESCRIBE cloned_items");
    $cols = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    
    // Adicionar campos se não existirem
    $migrations = [];
    
    if (!in_array('job_id', $cols)) {
        $migrations[] = "ADD COLUMN job_id INT NULL AFTER id";
    }
    
    if (!in_array('pricing_strategy', $cols)) {
        $migrations[] = "ADD COLUMN pricing_strategy VARCHAR(50) NULL AFTER catalog_product_id";
    }
    
    if (!in_array('original_price', $cols)) {
        $migrations[] = "ADD COLUMN original_price DECIMAL(12,2) NULL AFTER pricing_strategy";
    }
    
    if (!in_array('final_price', $cols)) {
        $migrations[] = "ADD COLUMN final_price DECIMAL(12,2) NULL AFTER original_price";
    }
    
    if (!in_array('processing_time_ms', $cols)) {
        $migrations[] = "ADD COLUMN processing_time_ms INT NULL AFTER final_price";
    }
    
    if (!in_array('retry_count', $cols)) {
        $migrations[] = "ADD COLUMN retry_count TINYINT DEFAULT 0 AFTER processing_time_ms";
    }
    
    if (empty($migrations)) {
        echo "✅ Tabela já está atualizada!\n";
    } else {
        $sql = "ALTER TABLE cloned_items " . implode(", ", $migrations);
        $db->exec($sql);
        echo "✅ Campos adicionados: " . count($migrations) . "\n";
        foreach ($migrations as $m) {
            echo "   - " . substr($m, 11, strpos($m, ' ', 12) - 11) . "\n";
        }
    }
    
    // Adicionar índice para job_id se não existir
    $indexStmt = $db->query("SHOW INDEX FROM cloned_items WHERE Key_name = 'idx_job_id'");
    if ($indexStmt->rowCount() === 0 && in_array('job_id', $cols) || !in_array('job_id', $cols)) {
        try {
            $db->exec("CREATE INDEX idx_job_id ON cloned_items(job_id)");
            echo "✅ Índice idx_job_id criado\n";
        } catch (Exception $e) {
            // Pode já existir
        }
    }

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nMigração concluída!\n";
