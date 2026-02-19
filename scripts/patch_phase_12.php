<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "=== Atualizando Schema (Phase 12 Patch) ===\n";

try {
    $db = Database::getInstance();
    
    // Add columns if they don't exist
    $columns = [
        'sentiment' => "VARCHAR(20) DEFAULT NULL",
        'intent' => "VARCHAR(50) DEFAULT NULL",
        'urgency' => "INT DEFAULT 0",
        'analysis_raw' => "JSON DEFAULT NULL"
    ];
    
    foreach ($columns as $col => $def) {
        try {
            $db->exec("ALTER TABLE ml_questions ADD COLUMN $col $def");
            echo "✅ Coluna '$col' adicionada.\n";
        } catch (Exception $e) {
            echo "ℹ️  Coluna '$col' já existe ou erro: " . $e->getMessage() . "\n";
        }
    }
    
    // Check if we need to alias question_id to ml_question_id or just use question_id
    // The previous service used ml_question_id. Let's try to add it as a string column if strictly needed, 
    // or just update the Service to use question_id (BIGINT).
    // The API uses string IDs sometimes (e.g. "123456"), bigint is usually fine for numbers.
    // Let's stick to the existing `question_id` (bigint).
    
    echo "Schema atualizado com sucesso!\n";
    
} catch (Exception $e) {
    echo "❌ Erro Fatal: " . $e->getMessage() . "\n";
    exit(1);
}
