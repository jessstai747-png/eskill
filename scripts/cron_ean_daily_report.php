#!/usr/bin/env php
<?php
/**
 * Script de Cron para Relatório Diário de EANs
 * 
 * Uso: php scripts/cron_ean_daily_report.php
 * Crontab: 0 8 * * * /usr/bin/php /path/to/scripts/cron_ean_daily_report.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\EanNotificationService;
use App\Services\EanReportService;
use App\Database;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

echo "===========================================\n";
echo "EAN Daily Report - " . date('Y-m-d H:i:s') . "\n";
echo "===========================================\n\n";

try {
    $db = Database::getInstance();
    
    // Gerar estatísticas do dia anterior
    $reportService = new EanReportService();
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    $report = $reportService->getSalesReport($yesterday, $yesterday);
    
    echo "📊 Relatório de " . $yesterday . "\n";
    echo "-------------------------------------------\n";
    echo "Pedidos: " . ($report['totals']['total_orders'] ?? 0) . "\n";
    echo "EANs Vendidos: " . ($report['totals']['total_eans'] ?? 0) . "\n";
    echo "Receita: R$ " . number_format($report['totals']['total_revenue'] ?? 0, 2, ',', '.') . "\n\n";
    
    // Verificar estoque
    $inventory = $reportService->getInventoryReport();
    echo "📦 Inventário Atual\n";
    echo "-------------------------------------------\n";
    echo "Disponíveis: " . ($inventory['current']['available'] ?? 0) . "\n";
    echo "Reservados: " . ($inventory['current']['reserved'] ?? 0) . "\n";
    echo "Vendidos: " . ($inventory['current']['sold'] ?? 0) . "\n\n";
    
    // Enviar email
    $notificationService = new EanNotificationService();
    $sent = $notificationService->sendDailySalesReport();
    
    if ($sent) {
        echo "✅ Relatório enviado por email com sucesso!\n";
    } else {
        echo "⚠️ Não foi possível enviar o relatório por email\n";
        echo "   Verifique as configurações de admin_email e SMTP\n";
    }
    
    // Verificar alerta de estoque baixo
    $available = $inventory['current']['available'] ?? 0;
    $threshold = 100; // Limite configurável
    
    if ($available < $threshold) {
        echo "\n⚠️ ALERTA: Estoque baixo ({$available} EANs disponíveis)\n";
        $notificationService->sendLowStockAlert($available, $threshold);
    }
    
    echo "\n✅ Script concluído com sucesso!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
