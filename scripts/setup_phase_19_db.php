<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;

try {
    $db = Database::getInstance();
} catch (Exception $e) {
    die("DB Connection Error: " . $e->getMessage() . "\n");
}

echo "Setting up Phase 19 DB Tables...\n";

// 1. ml_questions
// Ensure it exists and has account_id
// OLD Schema likely: id (pk), question_id (bigint), ...
// We need to ensure it matches API fields.

$sqlQuestions = "
CREATE TABLE IF NOT EXISTS ml_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id VARCHAR(50) NOT NULL UNIQUE,
    account_id INT NOT NULL,
    item_id VARCHAR(50),
    status VARCHAR(20),
    text TEXT,
    answer TEXT,
    from_user_id VARCHAR(50),
    date_created DATETIME,
    date_answered DATETIME,
    sentiment VARCHAR(20) DEFAULT NULL,
    intent VARCHAR(50) DEFAULT NULL,
    urgency INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (account_id),
    INDEX (status),
    INDEX (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sqlQuestions);
    echo "✅ ml_questions table checked/created.\n";
} catch (PDOException $e) {
    echo "⚠️ Error creating ml_questions: " . $e->getMessage() . "\n";
}

// 2. ml_messages (Post-Sale)
$sqlMessages = "
CREATE TABLE IF NOT EXISTS ml_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(50) NOT NULL UNIQUE,
    account_id INT NOT NULL,
    order_id VARCHAR(50),
    pack_id VARCHAR(50),
    status VARCHAR(20),
    text TEXT,
    role VARCHAR(20), -- 'buyer' or 'seller'
    date_created DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (account_id),
    INDEX (order_id),
    INDEX (pack_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sqlMessages);
    echo "✅ ml_messages table checked/created.\n";
} catch (PDOException $e) {
    echo "⚠️ Error creating ml_messages: " . $e->getMessage() . "\n";
}

// Check columns in case table existed but old
// Simple check: describe
$stmt = $db->query("DESCRIBE ml_questions");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('account_id', $cols)) {
    echo "Adding account_id to ml_questions...\n";
    $db->exec("ALTER TABLE ml_questions ADD COLUMN account_id INT NOT NULL AFTER question_id");
}

echo "Done.\n";
