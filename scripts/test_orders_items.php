<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

echo "=== TESTE DAS ROTAS DE PEDIDOS E ANÚNCIOS ===\n\n";

// Verificar se há contas configuradas
$db = App\Database::getInstance();
$stmt = $db->query("SELECT id, nickname, status FROM ml_accounts WHERE status = 'active' LIMIT 1");
$account = $stmt->fetch();

if (!$account) {
    echo "❌ PROBLEMA: Não há contas ativas no sistema!\n";
    echo "   Configure uma conta do Mercado Livre primeiro.\n\n";
    exit(1);
}

echo "✅ Conta encontrada: {$account['nickname']} (ID: {$account['id']})\n\n";

// Testar OrderService
echo "1️⃣ TESTANDO ORDERSERVICE...\n";
try {
    $orderService = new App\Services\OrderService($account['id']);
    $orders = $orderService->getOrders(['limit' => 5]);
    
    if (isset($orders['error'])) {
        echo "   ⚠️  Erro na API: {$orders['message']}\n";
        if (isset($orders['status'])) {
            echo "   Status HTTP: {$orders['status']}\n";
        }
        if (isset($orders['cause'])) {
            echo "   Causa: " . json_encode($orders['cause']) . "\n";
        }
    } else {
        $total = $orders['paging']['total'] ?? 0;
        $count = count($orders['results'] ?? []);
        echo "   ✅ Total de pedidos: $total (retornou $count)\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro: {$e->getMessage()}\n";
}

echo "\n2️⃣ TESTANDO ITEMSERVICE...\n";
try {
    $itemService = new App\Services\ItemService($account['id']);
    $items = $itemService->listItems(['limit' => 5]);
    
    if (isset($items['error'])) {
        echo "   ⚠️  Erro na API: " . ($items['message'] ?? 'Desconhecido') . "\n";
        if (isset($items['status'])) {
            echo "   Status HTTP: {$items['status']}\n";
        }
        if (isset($items['cause'])) {
            echo "   Causa: " . json_encode($items['cause']) . "\n";
        }
    } else {
        $total = $items['paging']['total'] ?? 0;
        $count = count($items['results'] ?? []);
        echo "   ✅ Total de anúncios: $total (retornou $count IDs)\n";
        if ($count > 0) {
            echo "   ℹ️  IDs: " . implode(", ", array_slice($items['results'], 0, 3)) . "...\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Erro: {$e->getMessage()}\n";
}

echo "\n3️⃣ VERIFICANDO TOKEN DE ACESSO...\n";
$stmt = $db->prepare("SELECT access_token, token_expires_at FROM ml_accounts WHERE id = :id");
$stmt->execute(['id' => $account['id']]);
$token = $stmt->fetch();

if (!$token['access_token']) {
    echo "   ❌ Token não encontrado - precisa autenticar!\n";
} else {
    $expires = strtotime($token['token_expires_at']);
    $now = time();
    if ($expires < $now) {
        echo "   ⚠️  Token expirado em {$token['token_expires_at']}\n";
        echo "   Precisa renovar o token!\n";
    } else {
        $remaining = round(($expires - $now) / 3600, 1);
        echo "   ✅ Token válido (expira em {$remaining}h)\n";
    }
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
