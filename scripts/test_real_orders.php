#!/usr/bin/env php
<?php
/**
 * Teste REAL da API de pedidos
 * Simula exatamente o que acontece no navegador
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Iniciar sessão (como se estivesse logado)
session_start();

echo "=== TESTE REAL DO SISTEMA DE PEDIDOS ===\n\n";

// CENÁRIO 1: SEM AUTENTICAÇÃO (como está agora)
echo "🔴 CENÁRIO 1: Sem autenticação (sessão vazia)\n";
echo "   Simulando acesso sem login...\n\n";

$_SESSION = []; // Limpar sessão

require __DIR__ . '/../app/Router.php';
require __DIR__ . '/../app/Controllers/OrderController.php';
require __DIR__ . '/../app/Services/OrderService.php';
require __DIR__ . '/../app/Services/MercadoLivreClient.php';
require __DIR__ . '/../app/Helpers/SessionHelper.php';
require __DIR__ . '/../app/Database.php';

$controller = new App\Controllers\OrderController();

echo "   Chamando /api/orders/all...\n";
ob_start();
$controller->all();
$output1 = ob_get_clean();
$result1 = json_decode($output1, true);

echo "   Resposta: " . json_encode($result1, JSON_PRETTY_PRINT) . "\n\n";

if (empty($result1['results'])) {
    echo "   ❌ CONFIRMADO: Sem autenticação, retorna 0 pedidos\n";
    if (isset($result1['error'])) {
        echo "   📝 Mensagem de erro: {$result1['error']}\n";
    }
} else {
    echo "   ✅ Pedidos retornados: " . count($result1['results']) . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// CENÁRIO 2: COM AUTENTICAÇÃO (como deveria ser)
echo "🟢 CENÁRIO 2: Com autenticação (usuário logado)\n";
echo "   Simulando login do admin@eskill.com.br...\n\n";

// Buscar ID do usuário real
$db = App\Database::getInstance();
$stmt = $db->query("SELECT id FROM users WHERE email = 'admin@eskill.com.br' LIMIT 1");
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "   ❌ ERRO: Usuário admin@eskill.com.br não encontrado!\n";
    exit(1);
}

// Simular sessão autenticada
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_email'] = 'admin@eskill.com.br';

echo "   ✅ Sessão criada: user_id = {$_SESSION['user_id']}\n";
echo "   Chamando /api/orders/all novamente...\n\n";

$controller2 = new App\Controllers\OrderController();
ob_start();
$controller2->all();
$output2 = ob_get_clean();
$result2 = json_decode($output2, true);

echo "   Resposta:\n";
if (isset($result2['results'])) {
    echo "   ✅ Total de pedidos: " . count($result2['results']) . "\n";
    echo "   ✅ Total geral: " . ($result2['total'] ?? count($result2['results'])) . "\n";
    
    if (!empty($result2['results'])) {
        $order = $result2['results'][0];
        echo "\n   📦 Exemplo do primeiro pedido:\n";
        echo "      ID: " . ($order['id'] ?? 'N/A') . "\n";
        echo "      Status: " . ($order['status'] ?? 'N/A') . "\n";
        echo "      Valor: R$ " . number_format($order['total_amount'] ?? 0, 2, ',', '.') . "\n";
        echo "      Comprador: " . ($order['buyer']['nickname'] ?? 'N/A') . "\n";
    }
} else {
    echo "   ❌ Nenhum pedido retornado\n";
    if (isset($result2['error'])) {
        echo "   📝 Erro: {$result2['error']}\n";
    }
    echo "   📋 Debug: " . json_encode($result2['debug'] ?? [], JSON_PRETTY_PRINT) . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// DIAGNÓSTICO FINAL
echo "📊 DIAGNÓSTICO FINAL:\n\n";

if (empty($result1['results']) && !empty($result2['results'])) {
    echo "✅ PROBLEMA IDENTIFICADO:\n";
    echo "   O sistema funciona PERFEITAMENTE quando autenticado.\n";
    echo "   Sem login, retorna 0 pedidos (comportamento correto).\n\n";
    echo "🔑 SOLUÇÃO:\n";
    echo "   1. Acesse: https://eskill.com.br/auth/login\n";
    echo "   2. Faça login com: admin@eskill.com.br\n";
    echo "   3. Depois acesse: https://eskill.com.br/dashboard/orders\n\n";
    echo "💡 O que pode estar acontecendo:\n";
    echo "   • Você não está logado no site\n";
    echo "   • A sessão expirou\n";
    echo "   • Cookies estão bloqueados\n";
    echo "   • Está acessando em modo anônimo/privado\n\n";
} else if (empty($result2['results'])) {
    echo "❌ PROBLEMA REAL ENCONTRADO:\n";
    echo "   Mesmo COM autenticação, não retorna pedidos.\n";
    echo "   Isso indica um problema no código.\n\n";
    
    // Verificar contas
    $stmt = $db->prepare("SELECT id, nickname FROM ml_accounts WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($accounts)) {
        echo "   ⚠️ CAUSA: Usuário sem contas do ML vinculadas!\n";
        echo "   📝 Vincule uma conta em: /dashboard/accounts\n";
    } else {
        echo "   ✅ Contas vinculadas: " . count($accounts) . "\n";
        foreach ($accounts as $acc) {
            $stmt2 = $db->prepare("SELECT COUNT(*) FROM ml_orders WHERE ml_account_id = ?");
            $stmt2->execute([$acc['id']]);
            $orderCount = $stmt2->fetchColumn();
            echo "      - {$acc['nickname']}: {$orderCount} pedidos\n";
        }
        echo "\n   🔍 Investigar: OrderService->getOrdersFromMultipleAccounts()\n";
    }
}

echo "\n✅ Teste concluído!\n";
