<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

try {
    $db = Database::getInstance();
    $stmt = $db->query("SHOW TABLES LIKE 'shipments'");
    if ($stmt->rowCount() > 0) {
        echo "Table 'shipments' exists.\n";
        $stmt = $db->query("DESCRIBE shipments");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "{$col['Field']} | {$col['Type']} | Null: {$col['Null']}\n";
        }
    } else {
        echo "Table 'shipments' DOES NOT EXIST.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
