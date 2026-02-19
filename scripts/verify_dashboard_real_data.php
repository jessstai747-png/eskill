<?php
require 'vendor/autoload.php';

use App\Services\DashboardService;
use App\Database;

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "🔍 Iniciando Verificação de Dados Reais do Dashboard...\n";

// 1. Limpar tabelas para teste limpo
$db = Database::getInstance();
$db->exec("DROP TABLE IF EXISTS market_analyses"); // Forçar recriação
$db->exec("DELETE FROM ml_orders");

echo "✅ Tabelas limpas.\n";

// 2. Instanciar DashboardService (isso deve recriar a tabela market_analyses se não existir no getAnalysisStats)
$dashboard = new DashboardService();
$statsBefore = $dashboard->getAnalysisStats();

if ($statsBefore['total_analyses'] === 0) {
    echo "✅ Tabela market_analyses recriada e vazia.\n";
} else {
    echo "❌ Erro: Tabela não estava vazia.\n";
}

// 3. Inserir dados simulados de Análise
echo "📝 Inserindo dados de análise...\n";
$db->exec("
    INSERT INTO market_analyses (account_id, category_id, brand, analysis_data, created_at)
    VALUES 
    (1, 'MLB1051', 'Samsung', '{}', NOW()),
    (1, 'MLB1051', 'Apple', '{}', NOW()),
    (1, 'MLB1234', 'Xiaomi', '{}', NOW())
");

// 4. Inserir dados simulados de Pedidos
echo "📝 Inserindo dados de pedidos...\n";
$db->exec("
    INSERT INTO ml_orders (ml_order_id, ml_account_id, order_data, status, total_amount, date_created, synced_at)
    VALUES
    (1001, 1, '{}', 'paid', 100.00, NOW(), NOW()),
    (1002, 1, '{}', 'paid', 200.00, NOW(), NOW()),
    (1003, 1, '{}', 'shipped', 150.00, DATE_SUB(NOW(), INTERVAL 1 DAY), NOW())
");

// 5. Verificar métricas
$stats = $dashboard->getAnalysisStats();
$metrics = $dashboard->getMetrics(1);

echo "\n📊 Resultados:\n";
echo "Análises Total: " . $stats['total_analyses'] . " (Esperado: 3)\n";
echo "Marcas Analisadas: " . $stats['brands_analyzed'] . " (Esperado: 3)\n";
echo "Pedidos Recentes: " . $metrics['recent_orders_count'] . " (Esperado: 3)\n";
echo "Receita Total: " . $metrics['total_revenue'] . " (Esperado: 450.00)\n";

if ($stats['total_analyses'] == 3 && $metrics['recent_orders_count'] == 3 && $metrics['total_revenue'] == 450.00) {
    echo "\n✅ SUCESSO! O dashboard está lendo dados reais do banco de dados.\n";
} else {
    echo "\n❌ FALHA! Os dados não correspondem ao esperado.\n";
    exit(1);
}
