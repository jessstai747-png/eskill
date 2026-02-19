<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "DEBUGGING SCHEMA\n";
$db = Database::getInstance();
$stmt = $db->query("DESCRIBE ml_questions");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}
