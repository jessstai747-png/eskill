<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;

try {
    $db = Database::getInstance();
} catch (Exception $e) {
    die("DB Error: " . $e->getMessage());
}

echo "Setting up Phase 20 Tables...\n";

// 1. ai_agents (Registry)
$sqlAgents = "
CREATE TABLE IF NOT EXISTS ai_agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'paused', 'error') DEFAULT 'active',
    config JSON DEFAULT NULL,
    last_run_at DATETIME DEFAULT NULL,
    next_run_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sqlAgents);
    echo "✅ ai_agents created.\n";
} catch (PDOException $e) {
    echo "⚠️ Error ai_agents: " . $e->getMessage() . "\n";
}

// 2. ai_agent_logs (Audit)
$sqlLogs = "
CREATE TABLE IF NOT EXISTS ai_agent_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_code VARCHAR(50) NOT NULL,
    level ENUM('info', 'warning', 'error', 'action') DEFAULT 'info',
    message TEXT NOT NULL,
    context JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (agent_code),
    INDEX (level),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sqlLogs);
    echo "✅ ai_agent_logs created.\n";
} catch (PDOException $e) {
    echo "⚠️ Error ai_agent_logs: " . $e->getMessage() . "\n";
}

// Seed Default Guardian Agent
$sqlSeed = "
INSERT INTO ai_agents (code, name, description, status, config)
VALUES (
    'guardian', 
    'The Guardian', 
    'Monitora saúde do estoque e anúncios parados.', 
    'active', 
    '{\"stock_threshold_days\": 5, \"zombie_views_limit\": 100}'
)
ON DUPLICATE KEY UPDATE name = VALUES(name);
";
$db->exec($sqlSeed);
echo "🌱 Guardian Agent seeded.\n";

echo "Done.\n";
