<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

// Mock session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_email'] = 'test@example.com';

// Simular account ativo
$db = App\Database::getInstance();
$stmt = $db->query("SELECT id FROM ml_accounts WHERE user_id = 1 LIMIT 1");
$account = $stmt->fetch(PDO::FETCH_ASSOC);
if ($account) {
    $_SESSION['current_account_id'] = $account['id'];
}

echo "=== TESTE FINAL DE ANALYTICS ===\n\n";

// Teste 1: Analytics Service
try {
    $analytics = new App\Services\AnalyticsService($account['id'] ?? null);
    $metrics = $analytics->getSalesMetrics('30d');
    
    echo "✅ AnalyticsService funcionando\n";
    echo "   - Total pedidos: " . ($metrics['total_orders'] ?? 0) . "\n";
    echo "   - Receita total: R$ " . number_format($metrics['total_revenue'] ?? 0, 2, ',', '.') . "\n";
    echo "   - Chart data presente: " . (isset($metrics['chart_data']) ? 'SIM' : 'NÃO') . "\n";
    
    if (isset($metrics['chart_data'])) {
        echo "   - Labels: " . count($metrics['chart_data']['labels']) . " itens\n";
        echo "   - Values: " . count($metrics['chart_data']['values']) . " itens\n";
    }
} catch (Exception $e) {
    echo "❌ AnalyticsService: " . $e->getMessage() . "\n";
}

echo "\n=== TESTE DE API (simulado) ===\n\n";

// Teste 2: Simular chamada ao controller
try {
    $controller = new App\Controllers\AnalyticsController();
    
    // Capturar output do salesMetrics
    ob_start();
    $controller->salesMetrics();
    $output = ob_get_clean();
    
    $response = json_decode($output, true);
    
    if ($response['success'] ?? false) {
        echo "✅ /api/analytics/sales respondendo\n";
        echo "   - success: true\n";
        echo "   - data.chart_data presente: " . (isset($response['data']['chart_data']) ? 'SIM' : 'NÃO') . "\n";
        
        if (isset($response['data']['chart_data'])) {
            echo "   - Labels: " . json_encode(array_slice($response['data']['chart_data']['labels'], 0, 5)) . "...\n";
            echo "   - Values: " . json_encode(array_slice($response['data']['chart_data']['values'], 0, 5)) . "...\n";
        }
    } else {
        echo "❌ Resposta inválida\n";
    }
} catch (Exception $e) {
    echo "❌ Controller: " . $e->getMessage() . "\n";
}

echo "\n=== TODOS OS TESTES CONCLUÍDOS ===\n";
