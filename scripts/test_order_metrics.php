#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Services\OrderService;
use App\Database;

echo "=== TESTE DE MÉTRICAS FINANCEIRAS NOS PEDIDOS ===\n\n";

try {
    $db = Database::getInstance();
    
    // Pegar primeiro pedido
    $stmt = $db->query("SELECT * FROM ml_orders LIMIT 1");
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo "❌ Nenhum pedido encontrado no banco\n";
        exit(1);
    }
    
    echo "📦 Pedido ID: {$order['ml_order_id']}\n";
    echo "💰 Total: R$ {$order['total_amount']}\n\n";
    
    echo "📊 MÉTRICAS FINANCEIRAS:\n";
    echo "  • Subtotal: R$ " . ($order['subtotal'] ?? 'NULL') . "\n";
    echo "  • Comissão ML: R$ " . ($order['ml_commission'] ?? 'NULL') . "\n";
    echo "  • Taxa pagamento: R$ " . ($order['payment_fee'] ?? 'NULL') . "\n";
    echo "  • Taxa fixa: R$ " . ($order['fixed_fee'] ?? 'NULL') . "\n";
    echo "  • Frete: R$ " . ($order['shipping_cost'] ?? 'NULL') . "\n";
    echo "  • Lucro líquido: R$ " . ($order['net_profit'] ?? 'NULL') . "\n";
    echo "  • Margem: " . ($order['gross_margin'] ?? 'NULL') . "%\n";
    echo "  • Lucrativo: " . ($order['is_profitable'] ? 'SIM' : 'NÃO') . "\n";
    echo "  • Frete grátis: " . ($order['free_shipping'] ? 'SIM' : 'NÃO') . "\n";
    echo "\n";
    
    // Testar getOrdersFromMultipleAccounts
    echo "🔍 Testando getOrdersFromMultipleAccounts...\n";
    $orderService = new OrderService(1);
    
    // Pegar IDs das contas
    $stmt = $db->query("SELECT id FROM ml_accounts LIMIT 2");
    $accountIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($accountIds)) {
        echo "❌ Nenhuma conta encontrada\n";
        exit(1);
    }
    
    echo "  Contas: " . implode(', ', $accountIds) . "\n";
    
    $result = $orderService->getOrdersFromMultipleAccounts($accountIds, ['limit' => 1]);
    
    if (empty($result['results'])) {
        echo "❌ Nenhum resultado retornado\n";
        print_r($result);
        exit(1);
    }
    
    $firstOrder = $result['results'][0];
    
    echo "\n✅ DADOS RETORNADOS PELA API:\n";
    echo "  • ID: " . ($firstOrder['id'] ?? 'NULL') . "\n";
    echo "  • Total: R$ " . ($firstOrder['total_amount'] ?? 'NULL') . "\n";
    echo "  • Comissão ML: R$ " . ($firstOrder['ml_commission'] ?? 'NULL') . "\n";
    echo "  • Taxa pagamento: R$ " . ($firstOrder['payment_fee'] ?? 'NULL') . "\n";
    echo "  • Lucro líquido: R$ " . ($firstOrder['net_profit'] ?? 'NULL') . "\n";
    echo "  • Margem: " . ($firstOrder['gross_margin'] ?? 'NULL') . "%\n";
    echo "  • Lucrativo: " . ($firstOrder['is_profitable'] ? 'SIM' : 'NÃO') . "\n";
    echo "  • Frete grátis: " . ($firstOrder['free_shipping'] ? 'SIM' : 'NÃO') . "\n";
    
    if (isset($firstOrder['ml_commission']) && $firstOrder['ml_commission'] !== null) {
        echo "\n✅ SUCESSO! Métricas estão sendo retornadas corretamente!\n";
    } else {
        echo "\n❌ FALHA! Métricas não estão sendo incluídas na resposta\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "📍 " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
