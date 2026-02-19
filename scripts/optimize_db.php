<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "=== System Health: Database Optimization ===\n";

$db = Database::getInstance();

// List of recommended indexes
// Table => [IndexName => Columns]
$indexes = [
    'items' => [
        'idx_sku' => 'sku',
        'idx_status' => 'status',
        'idx_price' => 'price'
    ],
    'orders' => [
        'idx_date_created' => 'date_created',
        'idx_status' => 'status',
        'idx_seller' => 'seller_id'
    ],
    'audit_logs' => [
        'idx_action' => 'action',
        'idx_user' => 'user_id',
        'idx_created' => 'created_at'
    ],
    'competitor_logs' => [
        'idx_item' => 'ml_item_id',
        'idx_created' => 'created_at'
    ]
];

foreach ($indexes as $table => $tableIndexes) {
    echo "Checking table '$table'...\n";
    
    // Check if table exists
    try {
        $db->query("SELECT 1 FROM $table LIMIT 1");
    } catch (\Exception $e) {
        echo "  - Table not found, skipping.\n";
        continue;
    }

    foreach ($tableIndexes as $idxName => $columns) {
        try {
            // Check if index exists (MySQL specific)
            $stmt = $db->query("SHOW INDEX FROM $table WHERE Key_name = '$idxName'");
            if ($stmt->fetch()) {
                echo "  - Index '$idxName' exists.\n";
            } else {
                echo "  - Creating index '$idxName' on ($columns)... ";
                $db->exec("CREATE INDEX $idxName ON $table ($columns)");
                echo "DONE.\n";
            }
        } catch (\Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nOptimization Complete.\n";
