<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;

echo "🔄 Running Q&A Bot Migration...\n";

$db = Database::getInstance();

// 1. Create Table if not exists
$sql = "
CREATE TABLE IF NOT EXISTS ml_questions (
    question_id BIGINT PRIMARY KEY,
    account_id INT,
    seller_id BIGINT,
    item_id VARCHAR(50),
    status VARCHAR(50),
    question_text TEXT,
    answer_text TEXT,
    from_user_id BIGINT,
    date_created DATETIME,
    answer_date DATETIME,
    updated_at DATETIME,
    INDEX idx_status (status),
    INDEX idx_item (item_id),
    INDEX idx_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql);
    echo "✅ Table 'ml_questions' checked/created.\n";
} catch (Exception $e) {
    echo "❌ Error creating table: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Add Columns if not exist
$columnsToAdd = [
    'ai_draft' => 'TEXT',
    'sentiment' => 'VARCHAR(20)',
    'intent' => 'VARCHAR(50)',
    'urgency' => 'INT DEFAULT 0',
    'confidence_score' => 'INT DEFAULT 0'
];

foreach ($columnsToAdd as $col => $def) {
    try {
        // Validate column name (alphanumeric + underscore only)
        if (!preg_match('/^[a-z_]+$/', $col)) {
            echo "❌ Nome de coluna inválido: '$col'\n";
            continue;
        }
        // Check if column exists
        $stmt = $db->prepare("SHOW COLUMNS FROM ml_questions LIKE :col");
        $stmt->execute(['col' => $col]);
        if (!$stmt->fetch()) {
            echo "➕ Adding column '$col'...\n";
            // DDL cannot use parameterized columns, but $col is validated above
            $db->exec("ALTER TABLE ml_questions ADD COLUMN `{$col}` {$def}");
            echo "✅ Column '$col' added.\n";
        } else {
            echo "ℹ️ Column '$col' already exists.\n";
        }
    } catch (Exception $e) {
        echo "❌ Error adding column '$col': " . $e->getMessage() . "\n";
    }
}

echo "✅ Migration completed successfully!\n";
