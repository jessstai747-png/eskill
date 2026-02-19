<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$db = App\Database::getInstance();
$db->exec("UPDATE users SET email_verified_at = NOW() WHERE email = 'admin@eskill.com.br'");
echo "Admin user verified.\n";
