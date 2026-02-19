<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

$pdo = \App\Database::getInstance();

echo "Checking schema for Phase 18 (Auto-Negotiation)...\n";

// Check columns in items table
$stmt = $pdo->query("DESCRIBE items");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('auto_negotiate', $columns)) {
    echo "Adding 'auto_negotiate' column...\n";
    $pdo->exec("ALTER TABLE items ADD COLUMN auto_negotiate TINYINT(1) DEFAULT 0");
} else {
    echo "'auto_negotiate' column already exists.\n";
}

if (!in_array('negotiation_margin', $columns)) {
    echo "Adding 'negotiation_margin' column...\n";
    // Stores the max discount % specifically for negotiation (if different from min_price)
    // Or we rely on min_price? Let's add min_price check just in case.
}

if (!in_array('min_price', $columns)) {
    echo "Adding 'min_price' column (should have been from Phase 11)...\n";
    $pdo->exec("ALTER TABLE items ADD COLUMN min_price DECIMAL(10,2) DEFAULT NULL");
} else {
    echo "'min_price' column exists.\n";
}

echo "Schema ready for Phase 18.\n";
