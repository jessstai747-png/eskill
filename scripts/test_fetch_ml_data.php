<?php
// scripts/test_fetch_ml_data.php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "🔧 Testando a busca de dados reais do Mercado Livre (com mock)...\n";

// --- Início da Simulação ---

// 1. Simular usuário logado
$userId = 2;
$_SESSION['user_id'] = $userId;
$accountId = 3; // ID da conta ML que acabamos de criar no teste anterior

echo "Passo 1: Usuário ID {$userId} e Conta ML ID {$accountId} selecionados.\n";

// 2. Preparar o Mock do Guzzle para a busca de pedidos
$mock = new MockHandler([
    // A primeira chamada será para buscar os pedidos, já que o token é considerado válido
    new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'query' => 'MLB-MOCK-QUERY',
        'results' => [
            ['id' => 1, 'date_created' => '2025-12-21T10:00:00Z', 'total_amount' => 150.50, 'status' => 'paid'],
            ['id' => 2, 'date_created' => '2025-12-21T11:30:00Z', 'total_amount' => 75.00, 'status' => 'paid'],
        ],
        'paging' => ['total' => 2, 'offset' => 0, 'limit' => 50],
    ])),
]);
$handlerStack = HandlerStack::create($mock);
$mockClient = new Client(['handler' => $handlerStack]);

echo "Passo 2: Mock da API do ML para busca de pedidos configurado.\n";

// 3. Criar o serviço de pedidos e injetar o mock
$orderService = new App\Services\OrderService($accountId);
$orderService->setHttpClient($mockClient);

echo "Passo 3: Serviço de Pedidos instanciado e mock injetado.\n";

// 4. Executar a busca de pedidos
echo "Passo 4: Buscando pedidos recentes...\n";
try {
    $orders = $orderService->getRecentOrders();

    if (!empty($orders) && isset($orders['results'])) {
        echo "✅ Sucesso! Pedidos recebidos da API (mockada).\n";
        echo "Total de pedidos encontrados: " . count($orders['results']) . "\n";
        foreach ($orders['results'] as $order) {
            echo "  - Pedido ID: {$order['id']}, Valor: {$order['total_amount']}\n";
        }
    } else {
        echo "❌ ERRO: Nenhum pedido foi retornado ou o formato da resposta é inválido.\n";
        print_r($orders);
    }
} catch (Exception $e) {
    echo "❌ ERRO CRÍTICO ao buscar pedidos: " . $e->getMessage() . "\n";
}

echo "\n🔧 Teste de busca de dados concluído.\n";
