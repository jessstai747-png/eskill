<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$db = Database::getInstance();

function describe($db, $table) {
    echo "--- Table: $table ---\n";
    $stmt = $db->query("DESCRIBE $table");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "{$col['Field']} | {$col['Type']} | Null: {$col['Null']}\n";
    }
    echo "\n";
}

describe($db, 'audit_logs');
describe($db, 'whatsapp_settings');
describe($db, 'whatsapp_logs');
describe($db, 'items');
describe($db, 'ai_ab_tests');
describe($db, 'ai_training_data');
describe($db, 'ai_success_patterns');
