<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

// Carregar .env
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        putenv(trim($key) . '=' . trim($value));
    }
}

use App\Services\OrderService;

// Simular sessão básica
session_start();
$_SESSION['user_id'] = 1;

// Simular query params do frontend
$_GET['limit'] = 200;
$_GET['date_from'] = '2025-12-22';
$_GET['date_to'] = '2026-01-21';

$orderService = new OrderService(null);
$result = $orderService->listOrders([
    'limit' => 200,
    'date_from' => '2025-12-22',
    'date_to' => '2026-01-21'
]);

// Simular resposta JSON como o frontend receberia
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);
