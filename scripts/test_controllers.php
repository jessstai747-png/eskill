<?php

$_GET['limit'] = 5;
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Simular sessão com conta ativa
session_start();
$_SESSION['user_id'] = 1;

// Testar OrderController
echo "=== TESTANDO OrderController::all() ===\n";
$ctrl = new App\Controllers\OrderController();
ob_start();
$ctrl->all();
$output = ob_get_clean();
$data = json_decode($output, true);
echo "Pedidos retornados: " . count($data['results'] ?? []) . "\n";
echo "Total: " . ($data['total'] ?? 0) . "\n\n";

// Testar ItemController
echo "=== TESTANDO ItemController::index() ===\n";
$_GET['account_id'] = 1;
$ctrl = new App\Controllers\ItemController();
ob_start();
$ctrl->index();
$output = ob_get_clean();
$data = json_decode($output, true);
echo "IDs retornados: " . count($data['results'] ?? []) . "\n";
echo "Total: " . ($data['paging']['total'] ?? 0) . "\n";
echo "Items: " . count($data['items'] ?? []) . "\n";
