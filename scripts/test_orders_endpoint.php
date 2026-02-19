#!/usr/bin/env php
<?php
/**
 * Teste ESPECÍFICO do endpoint que a página chama
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

echo "═══════════════════════════════════════════════════════════\n";
echo "  TESTE DO ENDPOINT /api/orders/all (O QUE A PÁGINA USA)  \n";
echo "═══════════════════════════════════════════════════════════\n\n";

// TESTE 1: Verificar banco local
echo "📊 TESTE 1: Pedidos no Banco Local\n";
echo str_repeat("-", 60) . "\n";

$db = App\Database::getInstance();

$stmt = $db->query("
    SELECT 
        o.id,
        o.ml_order_id,
        o.status,
        o.total_amount,
        o.ml_account_id,
        a.nickname
    FROM ml_orders o
    LEFT JOIN ml_accounts a ON o.ml_account_id = a.id
    WHERE a.user_id = 1
    ORDER BY o.date_created DESC
    LIMIT 5
");

$dbOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Pedidos no banco local: " . count($dbOrders) . "\n";

if (!empty($dbOrders)) {
    echo "\nPrimeiros 3:\n";
    foreach (array_slice($dbOrders, 0, 3) as $i => $order) {
        echo "  " . ($i+1) . ". ML ID: {$order['ml_order_id']}, Status: {$order['status']}, Conta: {$order['nickname']}\n";
    }
}
echo "\n";

// TESTE 2: Simular requisição HTTP como se fosse o navegador
echo "🌐 TESTE 2: Simulando Requisição HTTP do Navegador\n";
echo str_repeat("-", 60) . "\n";

// Configurar ambiente como se fosse uma requisição real
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/orders/all?limit=200';
$_GET['limit'] = 200;

// Simular sessão autenticada
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_email'] = 'admin@eskill.com.br';

echo "GET /api/orders/all?limit=200\n";
echo "Session: user_id = {$_SESSION['user_id']}\n\n";

// Chamar o controller exatamente como o Router faria
$controller = new App\Controllers\OrderController();

echo "Executando OrderController->all()...\n\n";

ob_start();
$controller->all();
$jsonResponse = ob_get_clean();

// Analisar resposta
$data = json_decode($jsonResponse, true);

if ($data === null) {
    echo "❌ ERRO: Resposta não é JSON válido!\n";
    echo "Raw: " . substr($jsonResponse, 0, 200) . "\n";
    exit(1);
}

echo "✅ Resposta JSON válida\n";
echo "Total: " . ($data['total'] ?? 0) . "\n";
echo "Results array size: " . count($data['results'] ?? []) . "\n";

if (isset($data['error'])) {
    echo "❌ ERRO NA API: {$data['error']}\n";
    if (isset($data['debug'])) {
        echo "Debug: " . json_encode($data['debug']) . "\n";
    }
} else if (empty($data['results'])) {
    echo "❌ API respondeu mas results está VAZIO!\n";
    echo "\nResposta completa:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "✅ API retornou " . count($data['results']) . " pedidos\n\n";
    echo "Primeiros 3 pedidos:\n";
    foreach (array_slice($data['results'], 0, 3) as $i => $order) {
        echo "  " . ($i+1) . ". ID: " . ($order['id'] ?? 'N/A');
        echo ", Status: " . ($order['status'] ?? 'N/A');
        echo ", Comprador: " . ($order['buyer']['nickname'] ?? 'N/A') . "\n";
    }
}

echo "\n";

// TESTE 3: Verificar de onde vêm os pedidos (API vs Banco)
echo "🔍 TESTE 3: Origem dos Pedidos\n";
echo str_repeat("-", 60) . "\n";

// Contar pedidos no banco por conta
$stmt = $db->prepare("
    SELECT ml_account_id, COUNT(*) as count
    FROM ml_orders
    WHERE ml_account_id IN (1, 2)
    GROUP BY ml_account_id
");
$stmt->execute();
$bankCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

echo "No banco local:\n";
foreach ($bankCounts as $accountId => $count) {
    echo "  Conta $accountId: $count pedidos\n";
}

echo "\nDa API ao vivo:\n";
foreach ([1, 2] as $accountId) {
    try {
        $service = new App\Services\OrderService($accountId);
        $apiOrders = $service->getOrders(['limit' => 5]);
        
        if (isset($apiOrders['results'])) {
            echo "  Conta $accountId: " . count($apiOrders['results']) . " pedidos ✓\n";
        } else if (isset($apiOrders['error'])) {
            echo "  Conta $accountId: ERRO - {$apiOrders['error']} ✗\n";
        } else {
            echo "  Conta $accountId: Resposta inesperada\n";
        }
    } catch (Exception $e) {
        echo "  Conta $accountId: EXCEÇÃO - " . $e->getMessage() . " ✗\n";
    }
}

echo "\n";

// DIAGNÓSTICO FINAL
echo "═══════════════════════════════════════════════════════════\n";
echo "                    DIAGNÓSTICO FINAL                       \n";
echo "═══════════════════════════════════════════════════════════\n\n";

$hasData = !empty($data['results']);
$hasDbOrders = count($dbOrders) > 0;

if ($hasData) {
    echo "✅ TUDO OK! O endpoint retorna dados corretamente.\n\n";
    echo "Se ainda não aparece no navegador:\n";
    echo "  1. Limpe o cache: Ctrl+Shift+R\n";
    echo "  2. Abra o Console (F12) e veja erros JavaScript\n";
    echo "  3. Verifique se está logado: https://eskill.com.br/test_session.php\n";
    echo "  4. Teste a API direto: https://eskill.com.br/api/orders/all\n";
} else if ($hasDbOrders) {
    echo "⚠️ PROBLEMA: Banco tem pedidos mas API não retorna!\n\n";
    echo "Possíveis causas:\n";
    echo "  1. OrderService está buscando da API do ML, não do banco\n";
    echo "  2. API do ML está com erro 403\n";
    echo "  3. Lógica de getOrdersFromMultipleAccounts tem problema\n\n";
    echo "Verifique os logs: tail -f storage/logs/error-" . date('Y-m-d') . ".log\n";
} else {
    echo "❌ PROBLEMA: Não há pedidos nem no banco nem na API!\n\n";
    echo "Execute o sync: php scripts/cron_sync_orders.php\n";
}

echo "\n✅ Teste concluído!\n";
