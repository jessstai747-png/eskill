<?php

declare(strict_types=1);

// Simulate environment
$_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? (getenv('DB_HOST') ?: 'localhost');
$_ENV['DB_PORT'] = $_ENV['DB_PORT'] ?? (getenv('DB_PORT') ?: '3306');
$_ENV['DB_DATABASE'] = $_ENV['DB_DATABASE'] ?? (getenv('DB_DATABASE') ?: 'meli');
$_ENV['DB_USERNAME'] = $_ENV['DB_USERNAME'] ?? (getenv('DB_USERNAME') ?: 'root');

$dbPassword = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
if ($dbPassword !== false && $dbPassword !== null) {
    $_ENV['DB_PASSWORD'] = (string)$dbPassword;
}

require_once __DIR__ . '/test_settlement.php';
