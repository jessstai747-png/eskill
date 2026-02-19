<?php
require_once __DIR__ . '/app/Database.php';

try {
    $db = \App\Database::getInstance();
    $stmt = $db->query("SHOW TABLES LIKE 'financial_settlements'");
    $table = $stmt->fetchColumn();
    
    if ($table) {
        echo "Table 'financial_settlements' exists.\n";
        $stmt = $db->query("DESCRIBE financial_settlements");
        print_r($stmt->fetchAll(\PDO::FETCH_ASSOC));
    } else {
        echo "Table 'financial_settlements' DOES NOT exist.\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
