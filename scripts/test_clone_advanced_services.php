#!/usr/bin/env php
<?php
/**
 * Teste dos serviços avançados do módulo Clone
 * - CloneTrendChartService
 * - CloneEventTriggerService
 * - CloneAutoSchedulerService
 * - CloneMLRecommendationsService
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Services\CloneTrendChartService;
use App\Services\CloneEventTriggerService;
use App\Services\CloneAutoSchedulerService;
use App\Services\CloneMLRecommendationsService;
use App\Services\MercadoLivreClient;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  TESTE - SERVIÇOS AVANÇADOS DE CLONAGEM (API REAL)            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$db = App\Database::getInstance();
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

// Obter uma conta ativa para testes
$stmt = $db->query("SELECT id, ml_user_id FROM ml_accounts WHERE status = 'active' LIMIT 1");
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    echo "❌ Nenhuma conta ativa encontrada para teste\n";
    exit(1);
}

$accountId = (int)$account['id'];
$sellerId = $account['ml_user_id'];

echo "📋 Usando conta ID: {$accountId}, Seller ID: {$sellerId}\n\n";

// ============================================================================
echo "📊 TREND CHART SERVICE\n";
echo "──────────────────────────────────────────────────────────────────\n";

test("CloneTrendChartService instancia corretamente", function() use ($accountId) {
    $service = new CloneTrendChartService($accountId);
    return $service !== null;
});

test("getClonesPerDayChart retorna dados formatados para Chart.js", function() use ($accountId) {
    $service = new CloneTrendChartService($accountId);
    $data = $service->getClonesPerDayChart(7);
    
    // Deve ter estrutura de Chart.js com 'data' aninhado
    return isset($data['data']['labels']) && isset($data['data']['datasets']) && is_array($data['data']['datasets']);
});

test("getStatusDistributionChart retorna dados de status", function() use ($accountId) {
    $service = new CloneTrendChartService($accountId);
    $data = $service->getStatusDistributionChart();
    
    return isset($data['data']['labels']) && isset($data['data']['datasets']);
});

test("getSellerPerformanceChart retorna dados de contas", function() use ($accountId) {
    $service = new CloneTrendChartService($accountId);
    $data = $service->getSellerPerformanceChart();
    
    return isset($data['data']['labels']) && isset($data['data']['datasets']);
});

test("getQualityMetricsChart retorna dados de qualidade", function() use ($accountId) {
    $service = new CloneTrendChartService($accountId);
    $data = $service->getQualityMetricsChart();
    
    return isset($data['data']['labels']) && isset($data['data']['datasets']);
});

test("getDashboardCharts retorna todos os gráficos", function() use ($accountId) {
    $service = new CloneTrendChartService($accountId);
    $data = $service->getDashboardCharts();
    
    // Deve ter múltiplos gráficos
    return is_array($data) && count($data) > 0;
});

echo "\n";

// ============================================================================
echo "⚡ EVENT TRIGGER SERVICE\n";
echo "──────────────────────────────────────────────────────────────────\n";

test("CloneEventTriggerService instancia corretamente", function() use ($accountId) {
    $service = new CloneEventTriggerService($accountId);
    return $service !== null;
});

test("createTrigger cria trigger no banco", function() use ($accountId, $db) {
    $service = new CloneEventTriggerService($accountId);
    
    $result = $service->createTrigger([
        'name' => 'Test Trigger ' . date('H:i:s'),
        'event_type' => 'new_items',
        'source_type' => 'seller',
        'source_value' => '12345',
        'conditions' => ['min_price' => 50],
        'actions' => [['type' => 'notify', 'config' => []]],
    ]);
    
    $triggerId = $result['trigger_id'] ?? null;
    
    // Limpar após teste
    if ($triggerId) {
        $db->prepare("DELETE FROM clone_event_triggers WHERE trigger_id = ?")->execute([$triggerId]);
    }
    
    return !empty($triggerId);
});

test("listTriggers retorna lista de triggers", function() use ($accountId) {
    $service = new CloneEventTriggerService($accountId);
    $triggers = $service->listTriggers();
    
    return is_array($triggers);
});

echo "\n";

// ============================================================================
echo "📅 AUTO SCHEDULER SERVICE\n";
echo "──────────────────────────────────────────────────────────────────\n";

test("CloneAutoSchedulerService instancia corretamente", function() use ($accountId) {
    $service = new CloneAutoSchedulerService($accountId);
    return $service !== null;
});

test("createSchedule cria agendamento no banco", function() use ($accountId, $db) {
    $service = new CloneAutoSchedulerService($accountId);
    
    $result = $service->createSchedule([
        'name' => 'Test Schedule ' . date('H:i:s'),
        'source_type' => 'seller_id',
        'source_value' => '12345',
        'frequency' => 'daily',
        'run_at_hour' => 3,
        'run_at_minute' => 0,
        'max_items_per_run' => 10,
        'is_active' => true,
    ]);
    
    $scheduleId = $result['schedule_id'] ?? null;
    
    // Limpar após teste
    if ($scheduleId) {
        $db->exec("DELETE FROM clone_schedules WHERE id = {$scheduleId}");
    }
    
    return $scheduleId > 0;
});

test("listSchedules retorna lista de agendamentos", function() use ($accountId) {
    $service = new CloneAutoSchedulerService($accountId);
    $schedules = $service->listSchedules();
    
    return is_array($schedules);
});

test("getStats retorna estatísticas", function() use ($accountId) {
    $service = new CloneAutoSchedulerService($accountId);
    $stats = $service->getStats();
    
    return is_array($stats);
});

echo "\n";

// ============================================================================
echo "🤖 ML RECOMMENDATIONS SERVICE (API REAL)\n";
echo "──────────────────────────────────────────────────────────────────\n";

test("CloneMLRecommendationsService instancia corretamente", function() use ($accountId) {
    $service = new CloneMLRecommendationsService($accountId);
    return $service !== null;
});

test("getSellerRecommendations retorna recomendações de sellers", function() use ($accountId) {
    $service = new CloneMLRecommendationsService($accountId);
    $result = $service->getSellerRecommendations(['limit' => 5]);
    
    return is_array($result);
});

test("getProductRecommendations retorna recomendações de produtos", function() use ($accountId) {
    $service = new CloneMLRecommendationsService($accountId);
    $result = $service->getProductRecommendations(['limit' => 5]);
    
    return is_array($result);
});

test("getCategoryRecommendations retorna categorias recomendadas", function() use ($accountId) {
    $service = new CloneMLRecommendationsService($accountId);
    $result = $service->getCategoryRecommendations(5);
    
    return is_array($result);
});

test("getTrendAnalysis retorna análise de tendências", function() use ($accountId) {
    $service = new CloneMLRecommendationsService($accountId);
    $result = $service->getTrendAnalysis();
    
    return is_array($result);
});

echo "\n";

// ============================================================================
echo "🌐 TESTE DE API REAL - VALIDAÇÃO DE ENDPOINTS\n";
echo "──────────────────────────────────────────────────────────────────\n";

$testCategory = 'MLB1051'; // Celulares e Telefones

test("API: /users/me funciona", function() use ($accountId) {
    $client = new MercadoLivreClient($accountId);
    $response = $client->get('/users/me');
    
    $userId = $response['id'] ?? null;
    echo "(" . ($userId ? "user: {$userId}" : "sem user") . ") ";
    return $userId !== null;
});

test("API: /users/{id}/items/search funciona", function() use ($accountId, $sellerId) {
    $client = new MercadoLivreClient($accountId);
    $response = $client->get("/users/{$sellerId}/items/search", [
        'status' => 'active',
        'limit' => 10
    ]);
    
    $total = $response['paging']['total'] ?? 0;
    $items = $response['results'] ?? [];
    echo "({$total} itens) ";
    return is_array($items);
});

test("API: /highlights/MLB/category/{id} funciona", function() use ($accountId, $testCategory) {
    $client = new MercadoLivreClient($accountId);
    $response = $client->get("/highlights/MLB/category/{$testCategory}");
    
    $items = $response['content'] ?? [];
    echo "(" . count($items) . " itens) ";
    return count($items) > 0;
});

test("API: /items?ids= funciona (multi-get)", function() use ($accountId, $testCategory) {
    $client = new MercadoLivreClient($accountId);
    
    // Primeiro buscar IDs
    $highlights = $client->get("/highlights/MLB/category/{$testCategory}");
    $itemIds = array_slice($highlights['content'] ?? [], 0, 3);
    
    if (empty($itemIds)) {
        echo "(sem itens para testar) ";
        return true;
    }
    
    $response = $client->get('/items', ['ids' => implode(',', $itemIds)]);
    
    $successCount = 0;
    foreach ($response as $item) {
        if (isset($item['body']['id'])) {
            $successCount++;
        }
    }
    
    echo "({$successCount}/" . count($itemIds) . " OK) ";
    return $successCount > 0;
});

echo "\n";

// ============================================================================
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                      RESULTADO FINAL                          ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";
echo "║   ✅ Aprovados:   " . str_pad($passed, 3) . "                                        ║\n";
echo "║   ❌ Falhas:      " . str_pad($failed, 3) . "                                        ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";

if ($failed === 0) {
    echo "║   🎉 SERVIÇOS AVANÇADOS FUNCIONANDO PERFEITAMENTE!            ║\n";
} else {
    echo "║   ⚠️  ALGUNS TESTES FALHARAM - VERIFICAR LOGS                 ║\n";
}

echo "╚════════════════════════════════════════════════════════════════╝\n\n";

exit($failed > 0 ? 1 : 0);
