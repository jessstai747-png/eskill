<?php
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
use App\Database;

// Simular sessão básica
session_start();
$_SESSION['user_id'] = 1; // ID do usuário padrão

// Testar sem account_id específico
echo "=== Teste 1: Sem account_id específico ===\n";
try {
    $orderService = new OrderService(null);
    $result = $orderService->listOrders([
        'limit' => 10,
        'date_from' => '2025-12-22',
        'date_to' => '2026-01-21'
    ]);
    echo "Sucesso! Total de pedidos: " . $result['total'] . "\n";
    echo "Pedidos retornados: " . count($result['orders']) . "\n";
    if (!empty($result['orders'])) {
        echo "Primeiro pedido: ID=" . $result['orders'][0]['id'] . ", Status=" . $result['orders'][0]['status'] . "\n";
    }
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}

echo "\n=== Teste 2: Verificar contas ML disponíveis ===\n";
$db = Database::getInstance();
$stmt = $db->query("SELECT id, ml_user_id, nickname FROM ml_accounts WHERE status = 'active' LIMIT 5");
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Contas ativas encontradas: " . count($accounts) . "\n";
foreach ($accounts as $account) {
    echo "  - ID: {$account['id']}, ML User: {$account['ml_user_id']}, Nickname: {$account['nickname']}\n";
}

if (!empty($accounts)) {
    echo "\n=== Teste 3: Com account_id específico ===\n";
    $accountId = $accounts[0]['id'];
    try {
        $orderService = new OrderService($accountId);
        $result = $orderService->listOrders([
            'limit' => 10,
            'date_from' => '2025-12-22',
            'date_to' => '2026-01-21'
        ]);
        echo "Sucesso! Total de pedidos para conta {$accountId}: " . $result['total'] . "\n";
        echo "Pedidos retornados: " . count($result['orders']) . "\n";
    } catch (Exception $e) {
        echo "ERRO: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Teste 4: Query SQL Direta ===\n";
$stmt = $db->query("
    SELECT COUNT(*) as total 
    FROM ml_orders 
    WHERE date_created >= '2025-12-22' 
    AND date_created <= '2026-01-21 23:59:59'
");
$count = $stmt->fetchColumn();
echo "Pedidos no período (SQL direto): {$count}\n";

echo "\n=== Teste 5: Ver estrutura de um pedido ===\n";
$stmt = $db->query("SELECT ml_order_id, ml_account_id, user_id, status, total_amount FROM ml_orders LIMIT 1");
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if ($order) {
    echo "Exemplo de pedido:\n";
    print_r($order);
}

echo "\n✅ Testes concluídos!\n";
