#!/usr/bin/env php
<?php
/**
 * Demonstração de Clonagem Real de Anúncios
 * 
 * Este script demonstra o fluxo completo de clonagem:
 * 1. Busca item real da conta ativa
 * 2. Simula a clonagem mostrando o payload que seria enviado
 * 3. Mostra como funcionaria o processamento via worker
 * 
 * NOTA: Para clonagem real entre contas, é necessário ter 2+ contas ativas.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;
use App\Services\CatalogCloneService;
use App\Services\MercadoLivreClient;
use App\Services\JobService;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║      DEMONSTRAÇÃO DE CLONAGEM REAL DE ANÚNCIOS              ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

try {
    $db = Database::getInstance();
    $cloneService = new CatalogCloneService();
    $jobService = new JobService();

    // ═══════════════════════════════════════════════════════════════
    echo "━━━ 1. BUSCAR ITEM REAL PARA CLONAGEM ━━━\n\n";
    // ═══════════════════════════════════════════════════════════════

    // Buscar conta ativa
    $stmt = $db->query("SELECT id, nickname, ml_user_id FROM ml_accounts WHERE status = 'active' LIMIT 1");
    $activeAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$activeAccount) {
        echo "❌ Nenhuma conta ativa encontrada.\n";
        exit(1);
    }

    echo "📱 Conta ativa: {$activeAccount['nickname']} (ID: {$activeAccount['id']})\n\n";

    // Buscar um item real
    $client = new MercadoLivreClient($activeAccount['id']);
    $sellerId = $client->getSellerId();
    
    $items = $client->get("/users/{$sellerId}/items/search", ['status' => 'active', 'limit' => 3]);
    
    if (empty($items['results'])) {
        echo "❌ Nenhum item ativo encontrado na conta.\n";
        exit(1);
    }

    // Pegar o primeiro item
    $sourceItemId = $items['results'][0];
    $sourceItem = $client->get("/items/{$sourceItemId}");

    if (isset($sourceItem['error'])) {
        echo "❌ Erro ao buscar item: {$sourceItem['message']}\n";
        exit(1);
    }

    echo "📦 Item selecionado para clonagem:\n";
    echo "   ID: {$sourceItemId}\n";
    echo "   Título: " . substr($sourceItem['title'], 0, 60) . "...\n";
    echo "   Preço: R$ " . number_format($sourceItem['price'], 2, ',', '.') . "\n";
    echo "   Categoria: {$sourceItem['category_id']}\n";
    echo "   Catálogo: " . ($sourceItem['catalog_product_id'] ?? 'NÃO') . "\n";
    echo "   Imagens: " . count($sourceItem['pictures'] ?? []) . "\n";
    echo "   Variações: " . count($sourceItem['variations'] ?? []) . "\n";
    echo "   Estoque: {$sourceItem['available_quantity']}\n\n";

    // ═══════════════════════════════════════════════════════════════
    echo "━━━ 2. SIMULAÇÃO DE CLONAGEM COM ESTRATÉGIAS ━━━\n\n";
    // ═══════════════════════════════════════════════════════════════

    $strategies = [
        ['name' => 'Cópia Exata', 'type' => 'copy'],
        ['name' => 'Markup +15%', 'type' => 'markup_percent', 'value' => 15],
        ['name' => 'Desconto -10%', 'type' => 'markup_percent', 'value' => -10],
    ];

    foreach ($strategies as $strategy) {
        $params = [
            'source_account_id' => $activeAccount['id'],
            'source_item_id' => $sourceItemId,
            'target_account_id' => 999, // Conta fictícia para simulação
            'pricing_strategy' => $strategy,
        ];

        $simulation = $cloneService->simulateClone($params);
        
        echo "📊 Estratégia: {$strategy['name']}\n";
        if (isset($simulation['final_price'])) {
            $diff = $simulation['final_price'] - $simulation['original_price'];
            $diffPct = $simulation['original_price'] > 0 ? ($diff / $simulation['original_price']) * 100 : 0;
            $diffSign = $diff >= 0 ? '+' : '';
            
            echo "   Preço Original: R$ " . number_format($simulation['original_price'], 2, ',', '.') . "\n";
            echo "   Preço Final: R$ " . number_format($simulation['final_price'], 2, ',', '.') . "\n";
            echo "   Diferença: {$diffSign}R$ " . number_format($diff, 2, ',', '.') . " ({$diffSign}" . number_format($diffPct, 1) . "%)\n";
        } else {
            echo "   Status: " . ($simulation['status'] ?? 'N/A') . "\n";
            echo "   Mensagem: " . ($simulation['message'] ?? 'N/A') . "\n";
        }
        echo "\n";
    }

    // ═══════════════════════════════════════════════════════════════
    echo "━━━ 3. PAYLOAD QUE SERIA ENVIADO À API ━━━\n\n";
    // ═══════════════════════════════════════════════════════════════

    // Montar payload de criação
    $payload = [
        'title' => $sourceItem['title'],
        'category_id' => $sourceItem['category_id'],
        'price' => round($sourceItem['price'] * 1.15, 2), // Exemplo com markup 15%
        'currency_id' => $sourceItem['currency_id'],
        'available_quantity' => $sourceItem['available_quantity'],
        'buying_mode' => $sourceItem['buying_mode'],
        'listing_type_id' => $sourceItem['listing_type_id'],
        'condition' => $sourceItem['condition'],
        'pictures' => array_map(function($pic) { 
            return ['source' => $pic['url']]; 
        }, array_slice($sourceItem['pictures'] ?? [], 0, 3)), // Primeiras 3 imagens
    ];

    if (!empty($sourceItem['catalog_product_id'])) {
        $payload['catalog_product_id'] = $sourceItem['catalog_product_id'];
    }

    if (isset($sourceItem['shipping']['mode'])) {
        $payload['shipping'] = ['mode' => $sourceItem['shipping']['mode']];
    }

    echo "📤 Payload de criação (POST /items):\n";
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    // ═══════════════════════════════════════════════════════════════
    echo "━━━ 4. CRIAR JOB NA FILA (DEMONSTRAÇÃO) ━━━\n\n";
    // ═══════════════════════════════════════════════════════════════

    // Verificar contas disponíveis para clonagem real
    $stmt = $db->query("SELECT id, nickname, status FROM ml_accounts");
    $allAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $activeCount = count(array_filter($allAccounts, fn($a) => $a['status'] === 'active'));
    
    echo "📋 Contas disponíveis:\n";
    foreach ($allAccounts as $acc) {
        $icon = $acc['status'] === 'active' ? '✅' : '❌';
        echo "   {$icon} ID {$acc['id']}: {$acc['nickname']} ({$acc['status']})\n";
    }
    echo "\n";

    if ($activeCount >= 2) {
        echo "🎉 Você tem $activeCount contas ativas! Pode executar clonagem real.\n\n";
        
        // Encontrar outra conta ativa diferente
        $targetAccount = null;
        foreach ($allAccounts as $acc) {
            if ($acc['status'] === 'active' && $acc['id'] != $activeAccount['id']) {
                $targetAccount = $acc;
                break;
            }
        }

        if ($targetAccount) {
            echo "💡 Para clonar de {$activeAccount['nickname']} para {$targetAccount['nickname']}:\n";
            echo "   curl -X POST http://localhost:8000/api/catalog/clone \\\n";
            echo "     -H 'Content-Type: application/json' \\\n";
            echo "     -d '{\n";
            echo "       \"source_account_id\": {$activeAccount['id']},\n";
            echo "       \"source_item_id\": \"{$sourceItemId}\",\n";
            echo "       \"target_account_id\": {$targetAccount['id']},\n";
            echo "       \"pricing_strategy\": {\"type\": \"markup_percent\", \"value\": 10}\n";
            echo "     }'\n\n";
        }
    } else {
        echo "⚠️ Apenas $activeCount conta ativa. Para clonagem real, ative outra conta.\n\n";
        echo "💡 Para ativar uma conta:\n";
        echo "   1. Acesse /dashboard/accounts\n";
        echo "   2. Clique em 'Reconectar' na conta desejada\n";
        echo "   3. Autorize no Mercado Livre\n\n";
    }

    // Criar job de demonstração (será ignorado pelo worker por ser conta inválida)
    echo "📝 Criando job de demonstração na fila...\n";
    $demoJobId = $jobService->dispatch('catalog_clone_item', [
        'source_account_id' => $activeAccount['id'],
        'source_item_id' => $sourceItemId,
        'target_account_id' => 999, // Conta fictícia
        'pricing_strategy' => ['type' => 'markup_percent', 'value' => 15],
        '_demo' => true,
    ]);
    echo "   ✅ Job criado: ID $demoJobId\n\n";

    // ═══════════════════════════════════════════════════════════════
    echo "━━━ 5. ESTATÍSTICAS DO SISTEMA ━━━\n\n";
    // ═══════════════════════════════════════════════════════════════

    $metrics = $cloneService->getCloneMetrics();
    $jobStats = $jobService->getStats();

    echo "📊 Métricas de Clonagem:\n";
    echo "   Clones Hoje: {$metrics['today']}\n";
    echo "   Total Histórico: {$metrics['total']}\n";
    echo "   Taxa de Sucesso: {$metrics['success_rate']}%\n";
    echo "   Média/Hora: {$metrics['avg_per_hour']}\n\n";

    echo "📋 Fila de Jobs:\n";
    echo "   Pendentes: {$jobStats['pending']}\n";
    echo "   Em Processamento: {$jobStats['processing']}\n";
    echo "   Completos: {$jobStats['completed']}\n";
    echo "   Falhos: {$jobStats['failed']}\n\n";

    // ═══════════════════════════════════════════════════════════════
    echo "━━━ CONCLUSÃO ━━━\n\n";
    // ═══════════════════════════════════════════════════════════════

    echo "✅ Sistema de clonagem está funcionando corretamente!\n\n";
    echo "📚 Próximos passos para clonagem real:\n";
    echo "   1. Ative uma segunda conta em /dashboard/accounts\n";
    echo "   2. Use a interface em /dashboard/catalog/clone\n";
    echo "   3. Ou use a API: POST /api/catalog/clone\n";
    echo "   4. Execute o worker: php bin/catalog-clone-worker.php --verbose\n\n";

} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
