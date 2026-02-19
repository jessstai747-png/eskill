<?php
// Bypass Login for QA
// This file should be deleted after use.

// Safety guard: never allow this endpoint in production.
// Keep the file for controlled QA only.

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
$dotenv->safeLoad();

$env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';
if ($env === 'production') {
	http_response_code(404);
	echo 'Not Found';
	exit;
}

// Require explicit token to use this bypass.
$expectedToken = $_ENV['BYPASS_LOGIN_TOKEN'] ?? '';
$providedToken = $_GET['token'] ?? '';
if (empty($expectedToken) || !hash_equals($expectedToken, (string)$providedToken)) {
	http_response_code(404);
	echo 'Not Found';
	exit;
}

session_start();

$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Administrador';
$_SESSION['user_email'] = 'admin@eskill.com.br';
$_SESSION['user_role'] = 'admin';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_SESSION['csrf_token_time'] = time();

// Log
error_log("Bypass login executable for user 1");

header('Location: /dashboard/seo/ficha-tecnica');
exit;
