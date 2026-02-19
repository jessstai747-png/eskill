<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$db = Database::getInstance();

$tables = ['whatsapp_settings', 'whatsapp_logs', 'audit_logs'];
echo "Checking tables...\n";

foreach ($tables as $table) {
    try {
        $stmt = $db->query("SELECT 1 FROM $table LIMIT 1");
        echo "[OK] Table '$table' exists.\n";
    } catch (PDOException $e) {
        echo "[MISSING] Table '$table' NOT found.\n";
        // If missing, we might need to recreate it based on assumptions or manual definition since we can't read sql files easily if blocked
    }
}
