#!/usr/bin/env php
<?php
/**
 * Diagnóstico COMPLETO do Sistema de Pedidos
 * Identifica EXATAMENTE onde está o problema
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_email'] = 'admin@eskill.com.br';

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║     DIAGNÓSTICO COMPLETO - SISTEMA DE PEDIDOS            ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

$db = App\Database::getInstance();

// PASSO 1: Verificar banco de dados
echo "📊 PASSO 1: Verificando Banco de Dados\n";
echo str_repeat("-", 60) . "\n";

$stmt = $db->query("SELECT COUNT(*) FROM ml_orders");
$totalOrders = $stmt->fetchColumn();
echo "✓ Total de pedidos no banco: $totalOrders\n";

$stmt = $db->query("SELECT COUNT(*) FROM ml_accounts WHERE user_id = 1");
$totalAccounts = $stmt->fetchColumn();
echo "✓ Contas do usuário ID 1: $totalAccounts\n\n";

// PASSO 2: Verificar tokens
echo "🔑 PASSO 2: Verificando Tokens de Acesso\n";
echo str_repeat("-", 60) . "\n";

$stmt = $db->query("SELECT id, nickname, token_expires_at FROM ml_accounts WHERE user_id = 1");
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allTokensValid = true;
foreach ($accounts as $acc) {
    $expires = strtotime($acc['token_expires_at']);
    $now = time();
    $diff = $expires - $now;
    
    if ($diff > 0) {
        $hours = round($diff/3600, 1);
        echo "✓ Conta {$acc['id']} ({$acc['nickname']}): Válido por {$hours}h\n";
    } else {
        $hours = abs(round($diff/3600, 1));
        echo "✗ Conta {$acc['id']} ({$acc['nickname']}): EXPIRADO há {$hours}h\n";
        $allTokensValid = false;
    }
}
echo "\n";

// PASSO 3: Testar API do Mercado Livre diretamente
echo "🌐 PASSO 3: Testando API do Mercado Livre\n";
echo str_repeat("-", 60) . "\n";

$accountsToTest = [1, 2];
$apiResults = [];

foreach ($accountsToTest as $accountId) {
    try {
        $service = new App\Services\OrderService($accountId);
        echo "Testando conta ID $accountId... ";
        
        $orders = $service->getOrders(['limit' => 5]);
        
        if (isset($orders['error'])) {
            echo "❌ ERRO\n";
            echo "  Mensagem: {$orders['error']}\n";
            $apiResults[$accountId] = ['success' => false, 'error' => $orders['error']];
        } elseif (isset($orders['results'])) {
            $count = count($orders['results']);
            echo "✓ OK ($count pedidos)\n";
            $apiResults[$accountId] = ['success' => true, 'count' => $count];
        } else {
            echo "⚠ RESPOSTA INESPERADA\n";
            echo "  Debug: " . json_encode($orders) . "\n";
            $apiResults[$accountId] = ['success' => false, 'error' => 'Resposta inesperada'];
        }
    } catch (Exception $e) {
        echo "❌ EXCEÇÃO\n";
        echo "  Erro: " . $e->getMessage() . "\n";
        $apiResults[$accountId] = ['success' => false, 'error' => $e->getMessage()];
    }
}
echo "\n";

// PASSO 4: Testar OrderService.getOrdersFromMultipleAccounts()
echo "🔄 PASSO 4: Testando OrderService->getOrdersFromMultipleAccounts()\n";
echo str_repeat("-", 60) . "\n";

try {
    $service = new App\Services\OrderService();
    echo "Buscando pedidos de múltiplas contas [1, 2]...\n";
    
    $result = $service->getOrdersFromMultipleAccounts([1, 2], ['limit' => 10]);
    
    if (isset($result['results']) && !empty($result['results'])) {
        $total = $result['total'] ?? count($result['results']);
        echo "✓ Retornou $total pedidos\n";
        echo "\nPrimeiro pedido:\n";
        $order = $result['results'][0];
        echo "  ID: " . ($order['id'] ?? 'N/A') . "\n";
        echo "  Status: " . ($order['status'] ?? 'N/A') . "\n";
        echo "  Comprador: " . ($order['buyer']['nickname'] ?? 'N/A') . "\n";
    } else {
        echo "✗ Retornou 0 pedidos\n";
        echo "Debug: " . json_encode($result) . "\n";
    }
} catch (Exception $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
}
echo "\n";

// PASSO 5: Verificar se pedidos estão no banco mas não vêm da API
echo "🔍 PASSO 5: Comparando Banco vs API\n";
echo str_repeat("-", 60) . "\n";

$stmt = $db->prepare("
    SELECT ml_account_id, COUNT(*) as count 
    FROM ml_orders 
    WHERE ml_account_id IN (1, 2)
    GROUP BY ml_account_id
");
$stmt->execute();
$dbCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

echo "Pedidos no banco:\n";
foreach ($dbCounts as $accountId => $count) {
    echo "  Conta $accountId: $count pedidos\n";
}

echo "\nPedidos da API:\n";
foreach ($apiResults as $accountId => $result) {
    if ($result['success']) {
        echo "  Conta $accountId: {$result['count']} pedidos ✓\n";
    } else {
        echo "  Conta $accountId: ERRO - {$result['error']} ✗\n";
    }
}
echo "\n";

// DIAGNÓSTICO FINAL
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║                    DIAGNÓSTICO FINAL                     ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

$hasApiErrors = false;
foreach ($apiResults as $result) {
    if (!$result['success']) {
        $hasApiErrors = true;
        break;
    }
}

if ($hasApiErrors) {
    echo "❌ PROBLEMA IDENTIFICADO: API do Mercado Livre\n\n";
    echo "Os tokens estão aparentemente válidos, mas a API está retornando erro.\n";
    echo "Isso pode ser causado por:\n";
    echo "  1. Tokens sem as permissões corretas (scope)\n";
    echo "  2. Conta do ML sem acesso à API de pedidos\n";
    echo "  3. App do ML não configurado corretamente\n";
    echo "  4. Necessidade de refresh dos tokens\n\n";
    echo "🔧 SOLUÇÕES:\n";
    echo "  1. Reautorizar as contas em: /dashboard/accounts\n";
    echo "  2. Verificar scopes do app no Mercado Livre\n";
    echo "  3. Verificar logs detalhados em: storage/logs/error-" . date('Y-m-d') . ".log\n\n";
} elseif (!$allTokensValid) {
    echo "❌ PROBLEMA IDENTIFICADO: Tokens Expirados\n\n";
    echo "🔧 SOLUÇÃO:\n";
    echo "  Reautorize as contas em: https://eskill.com.br/dashboard/accounts\n\n";
} else {
    echo "✅ SISTEMA FUNCIONANDO CORRETAMENTE!\n\n";
    echo "A API está retornando pedidos normalmente.\n";
    echo "Se ainda não aparecem no dashboard, o problema pode ser:\n";
    echo "  1. Você não está logado (acesse /auth/login)\n";
    echo "  2. JavaScript com erro no navegador (F12 > Console)\n";
    echo "  3. Cache do navegador (Ctrl+Shift+R)\n\n";
}

echo "📝 Acesse https://eskill.com.br/test_session.php para verificar seu login.\n";
echo "✅ Teste concluído!\n";
