<?php
// scripts/test_dashboard_service.php

require 'vendor/autoload.php';

use App\Services\DashboardService;
use App\Database;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "🔧 Testando o DashboardService...\n";

// --- Início da Simulação ---

// 1. Simular usuário logado e conta ML selecionada
$userId = 2;
$accountId = 3;
$_SESSION['user_id'] = $userId;
$_SESSION['active_ml_account'] = $accountId;

echo "Passo 1: Usuário e conta ML simulados.\n";

// 2. Inserir dados de teste no banco de dados
$db = Database::getInstance();
echo "Passo 2: Inserindo dados de teste (pedidos) no banco...\n";

try {
    // Limpar dados antigos para um teste limpo
    $db->exec("DELETE FROM ml_orders");

    // Inserir pedidos de teste
    $stmt = $db->prepare("
        INSERT INTO ml_orders (ml_order_id, ml_account_id, order_data, status, total_amount, date_created)
        VALUES
        (?, ?, '{}', 'paid', 150.50, NOW()),
        (?, ?, '{}', 'paid', 75.00, DATE_SUB(NOW(), INTERVAL 1 DAY)),
        (?, ?, '{}', 'shipped', 200.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),
        (?, ?, '{}', 'cancelled', 50.00, DATE_SUB(NOW(), INTERVAL 3 DAY))
    ");
    $stmt->execute([
        time() + 1, $accountId,
        time() + 2, $accountId,
        time() + 3, $accountId,
        time() + 4, $accountId,
    ]);
    echo "  - 4 pedidos de teste inseridos.\n";
} catch (Exception $e) {
    echo "❌ ERRO ao inserir dados de teste: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Chamar o serviço de dashboard
$dashboardService = new DashboardService();
echo "Passo 3: Buscando métricas do DashboardService...\n";

try {
    $metrics = $dashboardService->getMetrics();

    echo "✅ Métricas recebidas com sucesso!\n";
    echo "---------------------------------\n";
    print_r($metrics);
    echo "---------------------------------\n";

    // Validações
    if (($metrics['recent_orders_count'] ?? 0) > 0 && ($metrics['total_revenue'] ?? 0) > 0) {
        echo "✅ Validação bem-sucedida: Métricas de pedidos e receita parecem corretas.\n";
    } else {
        echo "❌ Validação falhou: Métricas de pedidos e receita estão zeradas ou ausentes.\n";
    }

} catch (Exception $e) {
    echo "❌ ERRO CRÍTICO ao buscar métricas: " . $e->getMessage() . "\n";
}

echo "\n🔧 Teste do DashboardService concluído.\n";
