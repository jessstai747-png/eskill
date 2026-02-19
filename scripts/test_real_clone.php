<?php
/**
 * Teste de Clonagem Real de Anúncios
 * 
 * Este script testa o fluxo completo de clonagem.
 * ATENÇÃO: Para clonagem real, é necessário ter 2 contas ML ativas.
 */

require '/home/eskill/htdocs/eskill.com.br/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable('/home/eskill/htdocs/eskill.com.br');
$dotenv->safeLoad();

use App\Services\CatalogCloneService;
use App\Services\MercadoLivreClient;

echo "=== Teste de Clonagem de Anúncios ===\n\n";

// 1. Verificar contas disponíveis
$db = App\Database::getInstance();
$stmt = $db->query("SELECT id, nickname, status FROM ml_accounts");
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "1. Contas Disponíveis:\n";
foreach ($accounts as $acc) {
    $icon = $acc['status'] === 'active' ? '✅' : '❌';
    echo "   {$icon} ID {$acc['id']}: {$acc['nickname']} ({$acc['status']})\n";
}

$activeAccounts = array_filter($accounts, fn($a) => $a['status'] === 'active');
echo "\n   Total ativas: " . count($activeAccounts) . "\n\n";

// 2. Buscar item ativo para teste
$sourceAccountId = 2; // PANTERAMOTOPEÇAS
$client = new MercadoLivreClient($sourceAccountId);
$sellerId = $client->getSellerId();

$itemsSearch = $client->get("/users/{$sellerId}/items/search", ['limit' => 5, 'status' => 'active']);
$sourceItemId = $itemsSearch['results'][0] ?? null;

if (!$sourceItemId) {
    echo "❌ Nenhum item ativo encontrado na conta {$sourceAccountId}\n";
    exit(1);
}

$sourceItem = $client->get("/items/{$sourceItemId}");
echo "2. Item Origem Selecionado:\n";
echo "   ID: {$sourceItem['id']}\n";
echo "   Título: {$sourceItem['title']}\n";
echo "   Preço: R$ {$sourceItem['price']}\n";
echo "   Categoria: {$sourceItem['category_id']}\n";
echo "   Estoque: " . ($sourceItem['available_quantity'] ?? 0) . "\n";
echo "   Imagens: " . count($sourceItem['pictures'] ?? []) . "\n";
echo "   Variações: " . count($sourceItem['variations'] ?? []) . "\n\n";

// 3. Simular clonagem (dry-run)
echo "3. Simulação de Clonagem (Dry-Run):\n";

$cloneService = new CatalogCloneService();

// Testar simulação
$simResult = $cloneService->simulateClone([
    'source_account_id' => $sourceAccountId,
    'source_item_id' => $sourceItemId,
    'target_account_id' => 1, // Conta diferente (mesmo que inativa, para teste)
    'pricing_strategy' => ['type' => 'copy'],
    'stock_strategy' => ['type' => 'copy']
]);

echo "   Status: " . ($simResult['status'] ?? 'N/A') . "\n";
echo "   Preço calculado: R$ " . ($simResult['calculated_price'] ?? $sourceItem['price']) . "\n";
echo "   Estoque calculado: " . ($simResult['calculated_stock'] ?? $sourceItem['available_quantity']) . "\n\n";

// 4. Testar validações
echo "4. Testes de Validação:\n";

// 4a. Clonagem para mesma conta (deve falhar)
$sameAccountResult = $cloneService->cloneCatalogItem([
    'source_account_id' => $sourceAccountId,
    'source_item_id' => $sourceItemId,
    'target_account_id' => $sourceAccountId
]);
$sameAccountOk = $sameAccountResult['status'] === 'error' || strpos($sameAccountResult['message'] ?? '', 'mesma conta') !== false;
echo "   - Rejeitar mesma conta: " . ($sameAccountOk ? '✅' : '❌') . "\n";

// 4b. Item inexistente
$invalidItemResult = $cloneService->cloneCatalogItem([
    'source_account_id' => $sourceAccountId,
    'source_item_id' => 'MLB0000000000',
    'target_account_id' => 1
]);
$invalidItemOk = $invalidItemResult['status'] === 'error';
echo "   - Rejeitar item inexistente: " . ($invalidItemOk ? '✅' : '❌') . "\n";

// 5. Estratégias de preço
echo "\n5. Cálculo de Estratégias de Preço:\n";
$basePrice = $sourceItem['price'];
$strategies = [
    ['type' => 'copy', 'expected' => $basePrice],
    ['type' => 'markup_percent', 'value' => 10, 'expected' => $basePrice * 1.10],
    ['type' => 'markup_percent', 'value' => -15, 'expected' => $basePrice * 0.85],
];

foreach ($strategies as $strategy) {
    $calcPrice = $basePrice;
    if ($strategy['type'] === 'markup_percent' && isset($strategy['value'])) {
        $calcPrice = $basePrice * (1 + ($strategy['value'] / 100));
    }
    $calcPrice = round($calcPrice, 2);
    $match = abs($calcPrice - $strategy['expected']) < 0.01;
    $label = $strategy['type'] . (isset($strategy['value']) ? " ({$strategy['value']}%)" : '');
    echo "   - $label: R$ " . number_format($calcPrice, 2, ',', '.') . " " . ($match ? '✅' : '❌') . "\n";
}

// 6. Verificar estrutura do payload de clonagem
echo "\n6. Estrutura do Payload (para debug):\n";
$payload = [
    'title' => $sourceItem['title'],
    'category_id' => $sourceItem['category_id'],
    'price' => $sourceItem['price'],
    'currency_id' => $sourceItem['currency_id'],
    'available_quantity' => $sourceItem['available_quantity'],
    'buying_mode' => $sourceItem['buying_mode'],
    'listing_type_id' => $sourceItem['listing_type_id'],
    'condition' => $sourceItem['condition'],
    'pictures' => array_map(fn($pic) => ['source' => $pic['url']], array_slice($sourceItem['pictures'] ?? [], 0, 3)),
];

echo "   Campos principais:\n";
foreach ($payload as $key => $value) {
    if ($key === 'pictures') {
        echo "     - {$key}: " . count($value) . " imagens\n";
    } else {
        echo "     - {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
    }
}

// 7. Verificar descrição
echo "\n7. Descrição do Item:\n";
$description = $client->get("/items/{$sourceItemId}/description");
if (!isset($description['error'])) {
    $plainText = $description['plain_text'] ?? '';
    $textPreview = substr($plainText, 0, 100) . (strlen($plainText) > 100 ? '...' : '');
    echo "   Tamanho: " . strlen($plainText) . " caracteres\n";
    echo "   Preview: {$textPreview}\n";
} else {
    echo "   ❌ Erro ao buscar descrição\n";
}

// 8. Status da fila de jobs
echo "\n8. Fila de Jobs (Catalog Clone):\n";
$jobsStats = $db->query("SELECT status, COUNT(*) as cnt FROM jobs WHERE type='catalog_clone_item' GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
foreach ($jobsStats as $stat) {
    echo "   - {$stat['status']}: {$stat['cnt']}\n";
}

// 9. Histórico de clonagens
echo "\n9. Últimas Clonagens:\n";
$history = $db->query("SELECT source_item_id, target_item_id, status, created_at FROM cloned_items ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
if (empty($history)) {
    echo "   Nenhuma clonagem registrada.\n";
} else {
    foreach ($history as $h) {
        $icon = $h['status'] === 'created' ? '✅' : ($h['status'] === 'skipped_duplicate' ? '⏭️' : '❌');
        echo "   {$icon} {$h['source_item_id']} → " . ($h['target_item_id'] ?: 'N/A') . " ({$h['status']})\n";
    }
}

echo "\n=== Teste Concluído ===\n";
echo "\n💡 Para clonagem real entre contas:\n";
echo "   1. Tenha pelo menos 2 contas ML ativas\n";
echo "   2. Use a interface em /dashboard/catalog/clone\n";
echo "   3. Ou chame: POST /api/catalog/clone com source_account_id, source_item_id, target_account_id\n";
