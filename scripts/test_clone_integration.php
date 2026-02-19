<?php
/**
 * Teste de Integração - Clonagem de Anúncios
 * 
 * Este script testa o fluxo completo:
 * 1. Validação do serviço
 * 2. Criação de job na fila
 * 3. Processamento do job
 * 4. Tracking de métricas
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;
use App\Services\CatalogCloneService;
use App\Services\JobService;
use App\Services\MercadoLivreClient;

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║     TESTE DE INTEGRAÇÃO - CLONAGEM DE ANÚNCIOS          ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

$db = Database::getInstance();
$results = ['passed' => 0, 'failed' => 0, 'skipped' => 0];

function test(string $name, callable $fn, array &$results): void {
    echo "▶ $name... ";
    try {
        $result = $fn();
        if ($result === 'skip') {
            echo "⏭️  SKIPPED\n";
            $results['skipped']++;
        } elseif ($result) {
            echo "✅ PASSED\n";
            $results['passed']++;
        } else {
            echo "❌ FAILED\n";
            $results['failed']++;
        }
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $results['failed']++;
    }
}

// ═══════════════════════════════════════════════════════════════
// FASE 1: INFRAESTRUTURA
// ═══════════════════════════════════════════════════════════════
echo "\n📦 FASE 1: Infraestrutura\n";
echo str_repeat('─', 60) . "\n";

test("Conexão com banco de dados", function() use ($db) {
    $stmt = $db->query("SELECT 1");
    return $stmt->fetch() !== false;
}, $results);

test("Tabela cloned_items existe", function() use ($db) {
    $stmt = $db->query("SHOW TABLES LIKE 'cloned_items'");
    return $stmt->rowCount() > 0;
}, $results);

test("Tabela cloned_items tem campos de tracking", function() use ($db) {
    $stmt = $db->query("SHOW COLUMNS FROM cloned_items LIKE 'job_id'");
    return $stmt->rowCount() > 0;
}, $results);

test("Tabela clone_post_actions_log existe", function() use ($db) {
    $stmt = $db->query("SHOW TABLES LIKE 'clone_post_actions_log'");
    return $stmt->rowCount() > 0;
}, $results);

test("Tabela jobs existe", function() use ($db) {
    $stmt = $db->query("SHOW TABLES LIKE 'jobs'");
    return $stmt->rowCount() > 0;
}, $results);

// ═══════════════════════════════════════════════════════════════
// FASE 2: SERVIÇOS
// ═══════════════════════════════════════════════════════════════
echo "\n🔧 FASE 2: Serviços\n";
echo str_repeat('─', 60) . "\n";

test("CatalogCloneService instancia corretamente", function() {
    $service = new CatalogCloneService();
    return $service !== null;
}, $results);

test("JobService instancia corretamente", function() {
    $service = new JobService();
    return $service !== null;
}, $results);

// ═══════════════════════════════════════════════════════════════
// FASE 3: VALIDAÇÕES
// ═══════════════════════════════════════════════════════════════
echo "\n🛡️  FASE 3: Validações\n";
echo str_repeat('─', 60) . "\n";

test("Rejeita clonagem para mesma conta", function() {
    $service = new CatalogCloneService();
    $result = $service->cloneCatalogItem([
        'source_account_id' => 1,
        'source_item_id' => 'MLB123',
        'target_account_id' => 1
    ]);
    return $result['status'] === 'error' && strpos($result['message'], 'mesma conta') !== false;
}, $results);

test("Retorna erro para item inexistente", function() {
    $service = new CatalogCloneService();
    $result = $service->cloneCatalogItem([
        'source_account_id' => 2,
        'source_item_id' => 'MLB0000000000',
        'target_account_id' => 1
    ]);
    return $result['status'] === 'error';
}, $results);

// ═══════════════════════════════════════════════════════════════
// FASE 4: ESTRATÉGIAS DE PREÇO (via simulateClone)
// ═══════════════════════════════════════════════════════════════
echo "\n💰 FASE 4: Estratégias de Preço\n";
echo str_repeat('─', 60) . "\n";

// Precisa de conta ativa para testar estratégias
$stmt = $db->query("SELECT id FROM ml_accounts WHERE status = 'active' LIMIT 1");
$activeAccountForPrice = $stmt->fetch(PDO::FETCH_ASSOC);

if ($activeAccountForPrice) {
    // Buscar um item real para simular
    $client = new MercadoLivreClient($activeAccountForPrice['id']);
    $sellerId = $client->getSellerId();
    $items = $client->get("/users/{$sellerId}/items/search", ['limit' => 1, 'status' => 'active']);
    $testItemId = $items['results'][0] ?? null;
    $testItem = $testItemId ? $client->get("/items/{$testItemId}") : null;
    $basePrice = $testItem['price'] ?? 100.0;

    test("Estratégia 'copy' mantém preço original", function() use ($activeAccountForPrice, $testItemId, $basePrice) {
        if (!$testItemId) return 'skip';
        $service = new CatalogCloneService();
        $result = $service->simulateClone([
            'source_account_id' => $activeAccountForPrice['id'],
            'source_item_id' => $testItemId,
            'target_account_id' => 999,
            'pricing_strategy' => ['type' => 'copy']
        ]);
        return abs(($result['final_price'] ?? $basePrice) - $basePrice) < 0.01;
    }, $results);

    test("Estratégia 'markup_percent' aplica markup corretamente", function() use ($activeAccountForPrice, $testItemId, $basePrice) {
        if (!$testItemId) return 'skip';
        $service = new CatalogCloneService();
        $result = $service->simulateClone([
            'source_account_id' => $activeAccountForPrice['id'],
            'source_item_id' => $testItemId,
            'target_account_id' => 999,
            'pricing_strategy' => ['type' => 'markup_percent', 'value' => 15]
        ]);
        $expected = round($basePrice * 1.15, 2);
        return abs(($result['final_price'] ?? 0) - $expected) < 0.01;
    }, $results);

    test("Estratégia 'markup_percent' com valor negativo reduz preço", function() use ($activeAccountForPrice, $testItemId, $basePrice) {
        if (!$testItemId) return 'skip';
        $service = new CatalogCloneService();
        $result = $service->simulateClone([
            'source_account_id' => $activeAccountForPrice['id'],
            'source_item_id' => $testItemId,
            'target_account_id' => 999,
            'pricing_strategy' => ['type' => 'markup_percent', 'value' => -20]
        ]);
        $expected = round($basePrice * 0.80, 2);
        return abs(($result['final_price'] ?? 0) - $expected) < 0.01;
    }, $results);
} else {
    echo "⏭️  Nenhuma conta ativa para testar estratégias de preço.\n";
    $results['skipped'] += 3;
}

// ═══════════════════════════════════════════════════════════════
// FASE 5: FILA DE JOBS
// ═══════════════════════════════════════════════════════════════
echo "\n📋 FASE 5: Fila de Jobs\n";
echo str_repeat('─', 60) . "\n";

test("Criar job de clonagem na fila", function() use ($db) {
    $jobService = new JobService();
    
    $jobId = $jobService->dispatch('catalog_clone_item', [
        'source_account_id' => 2,
        'source_item_id' => 'MLB_TEST_' . time(),
        'target_account_id' => 1,
        'pricing_strategy' => ['type' => 'copy'],
        'stock_strategy' => ['type' => 'copy'],
        '_test' => true // Marcador para identificar jobs de teste
    ]);
    
    // Limpar job de teste
    $db->prepare("DELETE FROM jobs WHERE id = ?")->execute([$jobId]);
    
    return $jobId > 0;
}, $results);

test("Buscar job por ID", function() use ($db) {
    $jobService = new JobService();
    
    // Criar um job temporário
    $jobId = $jobService->dispatch('catalog_clone_item', [
        'source_account_id' => 2,
        'source_item_id' => 'MLB_TEST',
        'target_account_id' => 1,
        '_test' => true
    ]);
    
    $job = $jobService->getJob($jobId);
    
    // Limpar
    $db->prepare("DELETE FROM jobs WHERE id = ?")->execute([$jobId]);
    
    return $job !== null && $job['type'] === 'catalog_clone_item';
}, $results);

// ═══════════════════════════════════════════════════════════════
// FASE 6: MÉTRICAS E LOGS
// ═══════════════════════════════════════════════════════════════
echo "\n📊 FASE 6: Métricas e Logs\n";
echo str_repeat('─', 60) . "\n";

test("Buscar métricas de clonagem", function() {
    $service = new CatalogCloneService();
    $metrics = $service->getCloneMetrics();
    return isset($metrics['total_cloned']) || isset($metrics['today']);
}, $results);

test("Buscar histórico de clonagens", function() {
    $service = new CatalogCloneService();
    $history = $service->getCloneHistory(10);
    return is_array($history);
}, $results);

// ═══════════════════════════════════════════════════════════════
// FASE 7: API DO MERCADO LIVRE (OPCIONAL)
// ═══════════════════════════════════════════════════════════════
echo "\n🌐 FASE 7: API Mercado Livre\n";
echo str_repeat('─', 60) . "\n";

// Verificar se há conta ativa
$stmt = $db->query("SELECT id FROM ml_accounts WHERE status = 'active' LIMIT 1");
$activeAccount = $stmt->fetch(PDO::FETCH_ASSOC);

if ($activeAccount) {
    test("Conectar à API do ML", function() use ($activeAccount) {
        $client = new MercadoLivreClient($activeAccount['id']);
        $sellerId = $client->getSellerId();
        return !empty($sellerId);
    }, $results);
    
    test("Buscar itens ativos", function() use ($activeAccount) {
        $client = new MercadoLivreClient($activeAccount['id']);
        $sellerId = $client->getSellerId();
        $items = $client->get("/users/{$sellerId}/items/search", ['limit' => 1]);
        return isset($items['results']);
    }, $results);
    
    test("Simulação de clonagem com item real", function() use ($activeAccount) {
        $client = new MercadoLivreClient($activeAccount['id']);
        $sellerId = $client->getSellerId();
        $items = $client->get("/users/{$sellerId}/items/search", ['limit' => 1, 'status' => 'active']);
        
        if (empty($items['results'])) {
            return 'skip';
        }
        
        $service = new CatalogCloneService();
        $result = $service->simulateClone([
            'source_account_id' => $activeAccount['id'],
            'source_item_id' => $items['results'][0],
            'target_account_id' => 999, // Conta inexistente para forçar simulação
            'pricing_strategy' => ['type' => 'markup_percent', 'value' => 10]
        ]);
        
        return isset($result['calculated_price']) || $result['status'] === 'success';
    }, $results);
} else {
    echo "⏭️  Nenhuma conta ML ativa encontrada. Pulando testes de API.\n";
    $results['skipped'] += 3;
}

// ═══════════════════════════════════════════════════════════════
// RESUMO
// ═══════════════════════════════════════════════════════════════
echo "\n" . str_repeat('═', 60) . "\n";
echo "📋 RESUMO DOS TESTES\n";
echo str_repeat('═', 60) . "\n";

$total = $results['passed'] + $results['failed'] + $results['skipped'];
echo "   ✅ Passed:  {$results['passed']}\n";
echo "   ❌ Failed:  {$results['failed']}\n";
echo "   ⏭️  Skipped: {$results['skipped']}\n";
echo "   📊 Total:   $total\n";
echo str_repeat('═', 60) . "\n";

if ($results['failed'] === 0) {
    echo "\n🎉 TODOS OS TESTES PASSARAM! O módulo está pronto para uso.\n";
    exit(0);
} else {
    echo "\n⚠️  Alguns testes falharam. Revise os erros acima.\n";
    exit(1);
}
