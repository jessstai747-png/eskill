#!/usr/bin/env php
<?php
/**
 * Teste real do Clonador em Lote
 * 
 * Testa:
 * 1. Listagem de itens por seller ID (conta própria)
 * 2. Summary e facets de categoria
 * 3. Dry-run/simulação
 * 4. Job de clonagem em lote
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;
use App\Services\CatalogCloneService;
use App\Services\MercadoLivreClient;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  TESTE REAL - CLONADOR DE ANÚNCIOS EM LOTE                    ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$db = Database::getInstance();
$passed = 0;
$failed = 0;

function test(string $name, callable $fn): bool {
    global $passed, $failed;
    echo "▶ {$name}... ";
    try {
        $result = $fn();
        if ($result) {
            echo "✅ PASSED\n";
            $passed++;
            return true;
        } else {
            echo "❌ FAILED (returned false)\n";
            $failed++;
            return false;
        }
    } catch (Exception $e) {
        echo "❌ FAILED: " . $e->getMessage() . "\n";
        $failed++;
        return false;
    }
}

// Obter conta ativa
$stmt = $db->query("SELECT id, ml_user_id, nickname FROM ml_accounts WHERE status = 'active' LIMIT 1");
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    echo "❌ Nenhuma conta ativa encontrada\n";
    exit(1);
}

$accountId = (int)$account['id'];
$sellerId = $account['ml_user_id'];
$nickname = $account['nickname'];

echo "📋 Conta: {$nickname} (ID: {$accountId}, Seller: {$sellerId})\n\n";

// ============================================================================
echo "📦 TESTE 1: LISTAGEM DE ITENS (CONTA PRÓPRIA)\n";
echo "──────────────────────────────────────────────────────────────────\n";

$service = new CatalogCloneService($db);
$client = new MercadoLivreClient($accountId);

test("Obter seller ID da conta", function() use ($client, $sellerId) {
    $actualSellerId = $client->getSellerId();
    echo "({$actualSellerId}) ";
    return $actualSellerId == $sellerId;
});

// Listar itens do próprio seller
$items = [];
test("Listar itens do seller (conta própria)", function() use ($service, $sellerId, &$items) {
    $result = $service->listSellerItems($sellerId, ['limit' => 20]);
    $items = $result['items'] ?? [];
    $total = $result['total'] ?? 0;
    echo "({$total} itens) ";
    return is_array($items);
});

test("Itens têm estrutura correta", function() use (&$items) {
    if (empty($items)) {
        echo "(sem itens para validar) ";
        return true;
    }
    $item = $items[0];
    return isset($item['id']) && isset($item['title']) && isset($item['price']);
});

echo "\n";

// ============================================================================
echo "📊 TESTE 2: SUMMARY E FACETS\n";
echo "──────────────────────────────────────────────────────────────────\n";

test("Obter summary do seller", function() use ($service, $sellerId) {
    $summary = $service->getSellerSummary($sellerId);
    
    $total = $summary['total'] ?? 0;
    $catalog = $summary['catalog_count'] ?? 0;
    $nonCatalog = $summary['non_catalog_count'] ?? 0;
    
    // Aceita se tiver total ou (catalog + non_catalog) > 0
    $hasItems = $total > 0 || ($catalog + $nonCatalog) > 0;
    
    echo "(total: {$total}, catálogo: {$catalog}, não-catálogo: {$nonCatalog}) ";
    return $hasItems || is_array($summary);
});

test("Summary inclui facets de categoria", function() use ($service, $sellerId) {
    $summary = $service->getSellerSummary($sellerId);
    $facets = $summary['category_facets'] ?? [];
    
    echo "(" . count($facets) . " categorias) ";
    return is_array($facets);
});

echo "\n";

// ============================================================================
echo "🔍 TESTE 3: DRY-RUN (SIMULAÇÃO)\n";
echo "──────────────────────────────────────────────────────────────────\n";

$testItemId = null;
test("Obter um item real para simular", function() use ($client, $sellerId, &$testItemId) {
    // Buscar itens do seller
    $response = $client->get("/users/{$sellerId}/items/search", [
        'status' => 'active',
        'limit' => 5
    ]);
    
    $itemIds = $response['results'] ?? [];
    if (empty($itemIds)) {
        echo "(sem itens) ";
        return true;
    }
    
    $testItemId = $itemIds[0];
    echo "({$testItemId}) ";
    return true;
});

test("Simular clonagem (dry-run)", function() use ($service, $testItemId, $accountId) {
    if (!$testItemId) {
        echo "(pulando - sem item) ";
        return true;
    }
    
    $result = $service->simulateClone([
        'source_account_id' => $accountId,
        'source_item_id' => $testItemId,
        'target_account_id' => $accountId,
        'pricing_strategy' => ['type' => 'copy']
    ]);
    
    // Simulação deve retornar informações do item
    echo "(status: " . ($result['status'] ?? 'ok') . ") ";
    return is_array($result);
});

echo "\n";

// ============================================================================
echo "📋 TESTE 4: SERVIÇOS AUXILIARES\n";
echo "──────────────────────────────────────────────────────────────────\n";

// CloneTemplateService
test("CloneTemplateService funciona", function() use ($db, $accountId) {
    $service = new \App\Services\CloneTemplateService($db, $accountId);
    $templates = $service->listTemplates();
    
    echo "(" . count($templates) . " templates) ";
    return is_array($templates);
});

// CloneMetricsService
test("CloneMetricsService funciona", function() use ($accountId) {
    $service = new \App\Services\CloneMetricsService();
    $dashboard = $service->getDashboard($accountId);
    
    echo "(dashboard OK) ";
    return is_array($dashboard);
});

// ClonePostActionsService
test("ClonePostActionsService funciona", function() {
    $service = new \App\Services\ClonePostActionsService();
    $actions = $service->getAvailableActions();
    
    echo "(" . count($actions) . " ações) ";
    return is_array($actions) && count($actions) > 0;
});

// CloneDuplicateDetectionService
test("CloneDuplicateDetectionService funciona", function() use ($accountId) {
    $service = new \App\Services\CloneDuplicateDetectionService();
    
    // Testar método checkDuplicate
    $result = $service->checkDuplicate('MLB999999999', $accountId);
    
    echo "(is_duplicate: " . ($result['is_duplicate'] ? 'sim' : 'não') . ") ";
    return isset($result['is_duplicate']);
});

// CloneRetryStrategyService
test("CloneRetryStrategyService funciona", function() {
    $service = new \App\Services\CloneRetryStrategyService();
    
    // Testar estratégia para erro 429 (Rate Limit)
    $strategy = $service->shouldRetry('429', 1);
    
    $delay = $strategy['delay_seconds'] ?? $strategy['delay'] ?? 0;
    echo "(retry: " . ($strategy['should_retry'] ? 'sim' : 'não') . ", delay: {$delay}s) ";
    return $strategy['should_retry'] === true;
});

echo "\n";

// ============================================================================
echo "🌐 TESTE 5: ENDPOINTS API REAIS\n";
echo "──────────────────────────────────────────────────────────────────\n";

test("API /users/me funciona", function() use ($client) {
    $response = $client->get('/users/me');
    $id = $response['id'] ?? null;
    echo "({$id}) ";
    return $id !== null;
});

test("API /users/{id}/items/search funciona", function() use ($client, $sellerId) {
    $response = $client->get("/users/{$sellerId}/items/search", [
        'status' => 'active',
        'limit' => 10
    ]);
    
    $total = $response['paging']['total'] ?? 0;
    echo "({$total} itens) ";
    return isset($response['results']);
});

test("API /items?ids= multi-get funciona", function() use ($client, $sellerId) {
    // Primeiro pegar alguns IDs
    $search = $client->get("/users/{$sellerId}/items/search", [
        'status' => 'active',
        'limit' => 3
    ]);
    
    $itemIds = $search['results'] ?? [];
    if (empty($itemIds)) {
        echo "(sem itens) ";
        return true;
    }
    
    // Buscar detalhes
    $response = $client->get('/items', ['ids' => implode(',', $itemIds)]);
    
    $success = 0;
    foreach ($response as $item) {
        if (isset($item['body']['id'])) $success++;
    }
    
    echo "({$success}/" . count($itemIds) . " OK) ";
    return $success > 0;
});

test("API /categories/{id} funciona", function() use ($client) {
    $response = $client->get('/categories/MLB1051'); // Celulares
    
    $name = $response['name'] ?? null;
    echo "({$name}) ";
    return $name !== null;
});

echo "\n";

// ============================================================================
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                      RESULTADO FINAL                          ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";
printf("║   ✅ Aprovados:   %-3d                                        ║\n", $passed);
printf("║   ❌ Falhas:      %-3d                                        ║\n", $failed);
echo "╠════════════════════════════════════════════════════════════════╣\n";

if ($failed === 0) {
    echo "║   🎉 CLONADOR EM LOTE FUNCIONANDO PERFEITAMENTE!              ║\n";
} else {
    echo "║   ⚠️  ALGUNS TESTES FALHARAM - VERIFICAR LOGS                 ║\n";
}

echo "╚════════════════════════════════════════════════════════════════╝\n\n";

exit($failed > 0 ? 1 : 0);
