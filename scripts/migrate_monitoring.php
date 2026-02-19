<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;

try {
    $db = Database::getInstance();
    
    echo "Creating monitoring tables...\n";
    
    // Error logs table
    $db->exec("
    CREATE TABLE IF NOT EXISTS error_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(255),
        message TEXT,
        file VARCHAR(500),
        line INT,
        trace TEXT,
        context JSON,
        environment VARCHAR(50),
        user_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at),
        INDEX idx_type (type),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    echo "✅ error_logs table created\n";
    
    // Performance metrics table
    $db->exec("
    CREATE TABLE IF NOT EXISTS performance_metrics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        duration_ms DECIMAL(10,2),
        memory_mb DECIMAL(10,2),
        query_count INT,
        url VARCHAR(500),
        method VARCHAR(10),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at),
        INDEX idx_url (url(255))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    echo "✅ performance_metrics table created\n";
    
    echo "\n✅ All monitoring tables created successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
