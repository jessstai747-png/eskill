#!/usr/bin/env php
<?php
/**
 * Sincronização Completa - Mercado Livre
 * 
 * Executa todas as sincronizações de uma vez:
 * - Renovar tokens
 * - Sincronizar pedidos
 * - Sincronizar anúncios
 * - Sincronizar perguntas
 * 
 * Uso: php scripts/ml_sync_all.php [--force]
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use App\Database;
use App\Services\MercadoLivreAuthService;
use App\Services\PollingService;
use App\Services\ItemService;
use App\Services\JobService;

$force = in_array('--force', $argv);
$startTime = microtime(true);

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║      🔄 SINCRONIZAÇÃO COMPLETA - MERCADO LIVRE              ║\n";
echo "║      " . date('Y-m-d H:i:s') . "                                 ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

try {
    $db = Database::getInstance();
    $authService = new MercadoLivreAuthService();
    $pollingService = new PollingService();
    $jobService = new JobService();
    
    // Buscar contas ativas
    $stmt = $db->query("SELECT id, nickname, ml_user_id, token_expires_at FROM ml_accounts WHERE status = 'active'");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($accounts)) {
        echo "❌ Nenhuma conta ativa encontrada!\n";
        exit(1);
    }
    
    echo "📊 Contas ativas: " . count($accounts) . "\n\n";
    
    // ==========================================
    // ETAPA 1: RENOVAR TOKENS
    // ==========================================
    echo "1️⃣  RENOVAÇÃO DE TOKENS\n";
    echo "────────────────────────────────────────\n";
    
    $tokensRenewed = 0;
    foreach ($accounts as $account) {
        $hoursLeft = (strtotime($account['token_expires_at']) - time()) / 3600;
        
        if ($hoursLeft < 2 || $force) {
            echo "   [{$account['nickname']}] Renovando... ";
            try {
                $authService->ensureValidToken((int)$account['id']);
                echo "✅ OK\n";
                $tokensRenewed++;
            } catch (\Exception $e) {
                echo "❌ " . $e->getMessage() . "\n";
            }
        } else {
            echo "   [{$account['nickname']}] Token válido ({$hoursLeft}h) - Pulando\n";
        }
    }
    
    echo "   ✅ Tokens renovados: {$tokensRenewed}\n\n";
    
    // ==========================================
    // ETAPA 2: SINCRONIZAR PEDIDOS
    // ==========================================
    echo "2️⃣  SINCRONIZAÇÃO DE PEDIDOS\n";
    echo "────────────────────────────────────────\n";
    
    $result = $pollingService->pollOrders();
    echo "   📦 Contas processadas: {$result['total_accounts']}\n";
    echo "   📦 Jobs criados: {$result['jobs_created']}\n";
    
    // Processar jobs
    $processed = $jobService->process(100);
    echo "   ⚙️  Jobs processados: " . count($processed) . "\n\n";
    
    // ==========================================
    // ETAPA 3: SINCRONIZAR ANÚNCIOS
    // ==========================================
    echo "3️⃣  SINCRONIZAÇÃO DE ANÚNCIOS\n";
    echo "────────────────────────────────────────\n";
    
    $totalItems = 0;
    $totalErrors = 0;
    
    foreach ($accounts as $account) {
        echo "   [{$account['nickname']}] ";
        
        try {
            $itemService = new ItemService((int)$account['id']);
            $result = $itemService->syncItems(100);
            
            if ($result['success']) {
                echo "✅ {$result['synced']} itens";
                if ($result['errors'] > 0) {
                    echo " ({$result['errors']} erros)";
                }
                $totalItems += $result['synced'];
                $totalErrors += $result['errors'];
            } else {
                echo "❌ " . ($result['error'] ?? 'Erro');
            }
        } catch (\Exception $e) {
            echo "❌ " . $e->getMessage();
            $totalErrors++;
        }
        
        echo "\n";
    }
    
    echo "   📝 Total sincronizado: {$totalItems}\n\n";
    
    // ==========================================
    // ETAPA 4: SINCRONIZAR PERGUNTAS
    // ==========================================
    echo "4️⃣  SINCRONIZAÇÃO DE PERGUNTAS\n";
    echo "────────────────────────────────────────\n";
    
    $totalQuestions = 0;
    
    foreach ($accounts as $account) {
        echo "   [{$account['nickname']}] ";
        
        try {
            // Chamar diretamente o serviço de perguntas
            $client = new \App\Services\MercadoLivreClient((int)$account['id']);
            
            // Buscar perguntas
            $questions = $client->get("/questions/search", [
                'seller_id' => $account['ml_user_id'],
                'status' => 'UNANSWERED',
                'sort_fields' => 'date_created',
                'sort_types' => 'DESC'
            ]);
            
            $count = $questions['total'] ?? 0;
            echo "✅ {$count} perguntas pendentes";
            $totalQuestions += $count;
            
        } catch (\Exception $e) {
            echo "❌ " . $e->getMessage();
        }
        
        echo "\n";
    }
    
    echo "   ❓ Total pendentes: {$totalQuestions}\n\n";
    
    // ==========================================
    // RESUMO
    // ==========================================
    $elapsed = round(microtime(true) - $startTime, 2);
    
    echo "════════════════════════════════════════════════════════════════\n";
    echo "✅ SINCRONIZAÇÃO CONCLUÍDA\n";
    echo "════════════════════════════════════════════════════════════════\n\n";
    
    echo "   ⏱️  Tempo: {$elapsed}s\n";
    echo "   🔑 Tokens renovados: {$tokensRenewed}\n";
    echo "   📝 Anúncios: {$totalItems}\n";
    echo "   ❓ Perguntas pendentes: {$totalQuestions}\n";
    
    if ($totalErrors > 0) {
        echo "   ⚠️  Erros: {$totalErrors}\n";
    }
    
    echo "\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
