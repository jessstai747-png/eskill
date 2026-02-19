<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$db = App\Database::getInstance();
$stmt = $db->query("SELECT id, name, email, email_verified_at FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Users:\n";
foreach ($users as $user) {
    echo "ID: {$user['id']}, Name: {$user['name']}, Email: {$user['email']}, Verified: " . ($user['email_verified_at'] ? $user['email_verified_at'] : 'NO') . "\n";
}
