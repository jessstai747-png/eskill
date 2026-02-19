<?php
http_response_code(404);
exit;

// 1. Check error log
$logFile = dirname(__DIR__) . '/storage/logs/error.log';
if (file_exists($logFile)) {
    $size = filesize($logFile);
    echo "=== ERROR LOG (last 3KB) ===\n";
    $fp = fopen($logFile, 'r');
    if ($size > 3000) fseek($fp, -3000, SEEK_END);
    echo fread($fp, 3000);
    fclose($fp);
} else {
    echo "No error.log found\n";
}

echo "\n\n=== PHP ERRORS LOG ===\n";
$phpLog = dirname(__DIR__) . '/storage/logs/php_errors.log';
if (file_exists($phpLog)) {
    $size = filesize($phpLog);
    $fp = fopen($phpLog, 'r');
    if ($size > 3000) fseek($fp, -3000, SEEK_END);
    echo fread($fp, 3000);
    fclose($fp);
} else {
    echo "No php_errors.log found\n";
}

echo "\n\n=== APP_ENV ===\n";
require_once dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists(dirname(__DIR__) . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
    echo "APP_ENV=" . ($_ENV['APP_ENV'] ?? 'NOT SET') . "\n";
    echo "APP_DEBUG=" . ($_ENV['APP_DEBUG'] ?? 'NOT SET') . "\n";
    echo "FORCE_HTTPS=" . ($_ENV['FORCE_HTTPS'] ?? 'NOT SET') . "\n";
    echo "APP_URL=" . ($_ENV['APP_URL'] ?? 'NOT SET') . "\n";
} else {
    echo ".env NOT FOUND\n";
}

echo "\n=== MAINTENANCE ===\n";
echo file_exists(dirname(__DIR__) . '/storage/maintenance.lock') ? "ACTIVE" : "inactive";

echo "\n\n=== NGINX CONFIG TEST ===\n";
// Try making internal request to /login
echo "Trying internal curl to https://eskill.com.br/login...\n";
$ch = curl_init('https://eskill.com.br/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);
echo "HTTP Code: $httpCode\n";
if ($error) echo "cURL Error: $error\n";
echo "Headers:\n$result\n";

echo "\n=== AUTH/AUTHORIZE TEST ===\n";
$ch = curl_init('https://eskill.com.br/auth/authorize');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP Code: $httpCode\n";
echo "Headers:\n$result\n";

echo "\n=== USERS TABLE ===\n";
try {
    $db = new PDO(
        'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . ($_ENV['DB_DATABASE'] ?? 'meli'),
        $_ENV['DB_USERNAME'] ?? 'root',
        $_ENV['DB_PASSWORD'] ?? ''
    );
    $stmt = $db->query("SELECT id, name, email, status, created_at FROM users LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($users)) {
        echo "NO USERS FOUND - need to register first!\n";
    } else {
        foreach ($users as $u) {
            echo "#{$u['id']} {$u['name']} ({$u['email']}) status={$u['status']}\n";
        }
    }
    
    // Check if users table exists
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    echo "Users table exists: " . ($stmt->rowCount() > 0 ? 'YES' : 'NO') . "\n";
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
