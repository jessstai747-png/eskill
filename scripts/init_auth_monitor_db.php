#!/usr/bin/env php
<?php
/**
 * Initialize Auth Monitor Database Tables
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

try {
    // Load environment
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
    
    // Connect to database - use exact same logic as the main script
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $dbname = $_ENV['DB_DATABASE'] ?? $_ENV['DB_NAME'] ?? 'meli';
    $username = $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? '';
    
    echo "Connecting to {$host}:{$port}/{$dbname} as {$username}...\n";
    
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $db = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "✓ Connected to database: {$dbname}\n";
    
    // Create auth_blocked_ips table
    $db->exec("
        CREATE TABLE IF NOT EXISTS auth_blocked_ips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            reason TEXT,
            failure_count INT NOT NULL DEFAULT 0,
            blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            INDEX idx_ip (ip_address),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Table auth_blocked_ips ready\n";
    
    // Create auth_failure_log table
    $db->exec("
        CREATE TABLE IF NOT EXISTS auth_failure_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            username VARCHAR(255),
            failure_type VARCHAR(100),
            detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip (ip_address),
            INDEX idx_detected (detected_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Table auth_failure_log ready\n";
    
    // Show table stats
    $stmt = $db->query("SELECT COUNT(*) as count FROM auth_blocked_ips");
    $blockedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM auth_failure_log");
    $failureCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "\nDatabase Statistics:\n";
    echo "  • Blocked IPs: {$blockedCount}\n";
    echo "  • Logged Failures: {$failureCount}\n";
    
    echo "\n✓ Database initialization completed successfully!\n";
    exit(0);
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
