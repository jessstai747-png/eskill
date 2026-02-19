<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$pdo = \App\Database::getInstance();

echo "--- ml_orders Columns ---\n";
$stmt = $pdo->query("DESCRIBE ml_orders");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n--- Checking for ml_order_items ---\n";
$stmt = $pdo->query("SHOW TABLES LIKE 'ml_order_items'");
$exists = $stmt->fetch();
if ($exists) {
    echo "ml_order_items table EXISTS.\n";
    $stmt = $pdo->query("DESCRIBE ml_order_items");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} else {
    echo "ml_order_items table does NOT exist.\n";
}
