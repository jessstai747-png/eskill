<?php
/**
 * Teste Completo do SEO Killer
 * Testa todas as funcionalidades com dados reais
 */

require_once __DIR__ . '/../vendor/autoload.php';

session_start();

// Configurar variáveis de ambiente manualmente para o teste
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_DATABASE'] = 'meli';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = 'Tr1unf0@';
$_ENV['APP_KEY'] = 'dbcb4ee5a3c9c67c6e2b315025a4ff7d6a2cfb47ef66132ba865502ef528b29e';

// Setup ambiente
$_SESSION['user_id'] = 1;
$_SESSION['active_ml_account_id'] = 1; // ID da conta de teste

use App\Controllers\SEOKillerController;
use App\Services\UserService;

echo "🔥 INICIANDO TESTE COMPLETO DO SEO KILLER\n";
echo str_repeat("=", 60) . "\n";

try {
    // Criar controller
    $controller = new SEOKillerController();
    
    echo "✅ Controller criado com sucesso\n";
    
    // Teste 1: Diagnóstico
    echo "\n📊 TESTE 1: Diagnóstico Completo\n";
    echo str_repeat("-", 40) . "\n";
    
    ob_start();
    $controller->diagnose();
    $output = ob_get_clean();
    
    $result = json_decode($output, true);
    
    if (isset($result['success']) && $result['success']) {
        echo "✅ Diagnóstico funcionando!\n";
        echo "📈 Stats: " . json_encode($result['stats'], JSON_PRETTY_PRINT) . "\n";
        echo "🔍 Problemas encontrados: " . count($result['problems'] ?? []) . "\n";
        echo "💡 Oportunidades: " . count($result['opportunities'] ?? []) . "\n";
    } else {
        echo "❌ Erro no diagnóstico: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
    
    // Teste 2: Top Performers
    echo "\n🏆 TESTE 2: Top Performers\n";
    echo str_repeat("-", 40) . "\n";
    
    ob_start();
    $controller->getTopPerformingItems();
    $output = ob_get_clean();
    
    $result = json_decode($output, true);
    
    if (isset($result['success']) && $result['success']) {
        echo "✅ Top performers funcionando!\n";
        echo "📊 Items retornados: " . count($result['items'] ?? []) . "\n";
        
        if (!empty($result['items'])) {
            $item = $result['items'][0];
            echo "🎯 Primeiro item: {$item['title']}\n";
            echo "⭐ Score: " . ($item['score'] ?? 'N/A') . "/100\n";
        }
    } else {
        echo "❌ Erro no top performers: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
    
    // Teste 3: Status AutoPilot
    echo "\n🤖 TESTE 3: AutoPilot Status\n";
    echo str_repeat("-", 40) . "\n";
    
    ob_start();
    $controller->getAutopilotRealStatus();
    $output = ob_get_clean();
    
    $result = json_decode($output, true);
    
    if (isset($result['success']) && $result['success']) {
        echo "✅ AutoPilot status funcionando!\n";
        echo "📊 Status: " . ($result['status']['enabled'] ? 'Ativo' : 'Inativo') . "\n";
    } else {
        echo "❌ Erro no AutoPilot status: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
    
    // Teste 4: Histórico AutoPilot
    echo "\n📜 TESTE 4: Histórico AutoPilot\n";
    echo str_repeat("-", 40) . "\n";
    
    ob_start();
    $controller->autopilotHistory();
    $output = ob_get_clean();
    
    $result = json_decode($output, true);
    
    if (isset($result['success']) && $result['success']) {
        echo "✅ Histórico AutoPilot funcionando!\n";
        $history = $result['history'] ?? $result['runs'] ?? [];
        echo "📊 Execuções encontradas: " . count($history) . "\n";
    } else {
        echo "❌ Erro no histórico: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO GERAL: " . $e->getMessage() . "\n";
    echo "📍 Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🏁 FIM DOS TESTES\n";