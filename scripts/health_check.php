<?php

/**
 * Script de Verificação de Saúde do Sistema
 * Executar via CRON a cada 5 minutos
 * php scripts/health_check.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

$config = require __DIR__ . '/../config/app.php';
$isHealthy = true;
$issues = [];

// 1. Verificar banco de dados
try {
    $db = App\Database::getInstance();
    $db->query("SELECT 1");
} catch (Exception $e) {
    $isHealthy = false;
    $issues[] = "Banco de dados: " . $e->getMessage();
}

// 2. Verificar espaço em disco
$diskFree = disk_free_space(__DIR__ . '/..');
$diskTotal = disk_total_space(__DIR__ . '/..');
$diskPercent = ($diskTotal - $diskFree) / $diskTotal * 100;

if ($diskPercent > 90) {
    $isHealthy = false;
    $issues[] = "Espaço em disco: {$diskPercent}% usado";
}

// 3. Verificar logs de erro recentes
$logFile = __DIR__ . '/../storage/logs/php_errors.log';
if (file_exists($logFile)) {
    $recentErrors = shell_exec("tail -n 100 " . escapeshellarg($logFile) . " | grep -i error | wc -l");
    if ((int)$recentErrors > 50) {
        $issues[] = "Muitos erros recentes: {$recentErrors}";
    }
}

// 4. Verificar tokens expirando
try {
    $db = App\Database::getInstance();
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM ml_accounts 
        WHERE token_expires_at < DATE_ADD(NOW(), INTERVAL 2 HOUR)
        AND status = 'active'
    ");
    $stmt->execute();
    $expiring = $stmt->fetch()['count'];

    if ($expiring > 0) {
        $issues[] = "{$expiring} token(s) expirando em menos de 2 horas";
    }
} catch (Exception $e) {
    // Ignorar se tabela não existir
}

// 5. Enviar alerta se necessário
if (!$isHealthy || count($issues) > 0) {
    $message = "⚠️ Problemas detectados no sistema:\n\n";
    foreach ($issues as $issue) {
        $message .= "• {$issue}\n";
    }

    // Enviar via Telegram se configurado
    if ($config['telegram']['enabled'] ?? false) {
        try {
            $telegram = new App\Services\TelegramService();
            $telegram->sendMessage($message);
        } catch (Exception $e) {
            error_log("Erro ao enviar alerta Telegram: " . $e->getMessage());
        }
    }

    // Log e saída
    echo $message;
    error_log("Health check failed: " . implode(", ", $issues));
    exit(1);
}

echo "✅ Health check OK - Sistema saudável\n";
echo "   Disco: " . round($diskPercent, 1) . "% usado\n";
exit(0);
