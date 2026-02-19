#!/usr/bin/env php
<?php

/**
 * 📊 SEO Killer - Worker de Relatórios Automatizados
 * 
 * Envia relatórios agendados (diários/semanais/mensais)
 * 
 * Uso:
 * php bin/automated-reports-worker.php daily
 * php bin/automated-reports-worker.php weekly
 * php bin/automated-reports-worker.php monthly
 * 
 * CRON Sugerido:
 * 0 8 * * * php /path/to/bin/automated-reports-worker.php daily
 * 0 9 * * 1 php /path/to/bin/automated-reports-worker.php weekly
 * 0 10 1 * * php /path/to/bin/automated-reports-worker.php monthly
 * 
 * @author AI Development Team
 * @version 1.0.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Services\AI\SEO\AutomatedReporting;

$reportType = $argv[1] ?? 'daily';

if (!in_array($reportType, ['daily', 'weekly', 'monthly'])) {
    echo "❌ Tipo inválido. Use: daily, weekly ou monthly\n";
    exit(1);
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "📊 SEO KILLER - AUTOMATED REPORTS WORKER\n";
echo "Report Type: " . strtoupper($reportType) . "\n";
echo str_repeat("=", 70) . "\n\n";

$startTime = microtime(true);
$db = Database::getInstance();

try {
    // Buscar todas as contas ativas que têm notificações habilitadas
    $stmt = $db->query("
        SELECT DISTINCT ma.id, ma.nickname, u.name, u.email
        FROM ml_accounts ma
        JOIN users u ON u.id = ma.user_id
        LEFT JOIN notification_preferences np ON np.account_id = ma.id
        WHERE ma.status = 'active'
          AND (
            (np.daily_report = 1 AND '{$reportType}' = 'daily')
            OR (np.weekly_report = 1 AND '{$reportType}' = 'weekly')
            OR (np.monthly_report = 1 AND '{$reportType}' = 'monthly')
            OR np.id IS NULL
          )
    ");
    
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalAccounts = count($accounts);
    
    echo "📧 Encontradas {$totalAccounts} contas para processar\n\n";
    
    if ($totalAccounts === 0) {
        echo "✅ Nenhuma conta configurada para receber relatórios {$reportType}\n";
        exit(0);
    }
    
    $successCount = 0;
    $failureCount = 0;
    $skippedCount = 0;
    
    foreach ($accounts as $index => $account) {
        $accountNumber = $index + 1;
        echo "[{$accountNumber}/{$totalAccounts}] Processando conta: {$account['nickname']} ({$account['email']})\n";
        
        try {
            $reporter = new AutomatedReporting($account['id']);
            
            // Enviar relatório apropriado
            switch ($reportType) {
                case 'daily':
                    $result = $reporter->sendDailyReport();
                    break;
                case 'weekly':
                    $result = $reporter->sendWeeklyReport();
                    break;
                case 'monthly':
                    $result = $reporter->sendMonthlyReport();
                    break;
                default:
                    throw new \Exception("Tipo de relatório inválido");
            }
            
            if ($result['success']) {
                if (isset($result['skipped']) && $result['skipped']) {
                    echo "   ⏭️  Pulado: {$result['reason']}\n";
                    $skippedCount++;
                } else {
                    echo "   ✅ Enviado com sucesso\n";
                    $successCount++;
                }
            } else {
                echo "   ❌ Erro: {$result['error']}\n";
                $failureCount++;
            }
            
        } catch (\Exception $e) {
            echo "   ❌ Exceção: {$e->getMessage()}\n";
            $failureCount++;
            error_log("Automated Report Error for Account {$account['id']}: " . $e->getMessage());
        }
        
        // Rate limiting para não sobrecarregar SMTP
        if ($accountNumber < $totalAccounts) {
            usleep(500000); // 0.5 segundos entre envios
        }
        
        echo "\n";
    }
    
    $duration = round(microtime(true) - $startTime, 2);
    
    echo str_repeat("=", 70) . "\n";
    echo "📊 RESUMO DA EXECUÇÃO\n";
    echo str_repeat("=", 70) . "\n";
    echo "Total de Contas:    {$totalAccounts}\n";
    echo "✅ Enviados:        {$successCount}\n";
    echo "❌ Falhas:          {$failureCount}\n";
    echo "⏭️  Pulados:         {$skippedCount}\n";
    echo "⏱️  Duração:         {$duration}s\n";
    echo str_repeat("=", 70) . "\n\n";
    
    // Salvar log consolidado
    $stmt = $db->prepare("
        INSERT INTO system_logs (account_id, type, message, metadata, created_at)
        VALUES (NULL, 'automated_reports_batch', :message, :metadata, NOW())
    ");
    $stmt->execute([
        'message' => "Automated {$reportType} reports sent",
        'metadata' => json_encode([
            'report_type' => $reportType,
            'total_accounts' => $totalAccounts,
            'success' => $successCount,
            'failures' => $failureCount,
            'skipped' => $skippedCount,
            'duration' => $duration,
        ]),
    ]);
    
    if ($failureCount > 0) {
        echo "⚠️  Alguns relatórios falharam. Verifique os logs.\n";
        exit(1);
    }
    
    echo "✅ Todos os relatórios foram processados com sucesso!\n\n";
    exit(0);
    
} catch (\Exception $e) {
    echo "\n❌ ERRO FATAL: {$e->getMessage()}\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}
