<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Database;

$db = Database::getInstance();

$sqlSeed = "
INSERT INTO ai_agents (code, name, description, status, config)
VALUES (
    'sniper', 
    'The Sniper', 
    'Monitora concorrentes e ajusta preços para ganhar o BuyBox.', 
    'active', 
    '{\"undercut_amount\": 0.10, \"max_drop_percent\": 10}'
)
ON DUPLICATE KEY UPDATE name = VALUES(name);
";
$db->exec($sqlSeed);
echo "🎯 Sniper Agent seeded.\n";
