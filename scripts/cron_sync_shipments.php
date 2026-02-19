<?php

/**
 * Cron Job: Sincronizar envios (shipments) de todas as contas ativas
 * 
 * Sincroniza dados de envio baseados nos pedidos recentes (últimos 30 dias).
 * Popula a tabela 'shipments' para permitir análise de performance logística.
 * 
 * Configuração crontab:
 * 0 * * * * php /home/eskill/htdocs/eskill.com.br/scripts/cron_sync_shipments.php >> /home/eskill/htdocs/eskill.com.br/storage/logs/cron_sync_shipments.log 2>&1
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;
use App\Services\ShipmentSyncService;
use App\Services\LoggingService;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$logger = new LoggingService();

function logToConsole(string $message, string $level = 'INFO'): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] [$level] $message\n";
}

try {
    logToConsole("=== Iniciando sincronização de envios (shipments) ===");

    $db = Database::getInstance();

    // Buscar todas as contas ativas
    $stmt = $db->query("
        SELECT id, ml_user_id, nickname 
        FROM ml_accounts 
        WHERE status = 'active' 
        AND access_token IS NOT NULL
        ORDER BY id
    ");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($accounts)) {
        logToConsole("Nenhuma conta ativa encontrada", 'WARNING');
        exit(0);
    }

    logToConsole("Encontradas " . count($accounts) . " contas ativas");

    $shipmentService = new ShipmentSyncService();

    foreach ($accounts as $account) {
        $accountId = (int)$account['id'];
        $nickname = $account['nickname'] ?? $account['ml_user_id'];

        logToConsole("--------------------------------------------------");
        logToConsole("Processando conta: $nickname (ID: $accountId)");

        try {
            // Sincroniza envios dos últimos 30 dias
            $stats = $shipmentService->syncForAccount($accountId, 30);

            $found = $stats['found'] ?? 0;
            $synced = $stats['synced'] ?? 0;
            $errors = $stats['errors'] ?? 0;

            logToConsole("✅ Conta $nickname: $synced/$found envios sincronizados. Erros: $errors");

            // Pequeno delay entre contas
            sleep(2);

        } catch (Exception $e) {
            $error = $e->getMessage();
            logToConsole("❌ Erro na conta $nickname: $error", 'ERROR');
            $logger->error('CRON_SHIPMENT_SYNC_ERROR', "Erro ao sincronizar envios da conta $accountId", ['error' => $error]);
        }
    }

    logToConsole("--------------------------------------------------");
    logToConsole("=== Sincronização concluída ===");

} catch (Exception $e) {
    logToConsole("❌ ERRO CRÍTICO: " . $e->getMessage(), 'CRITICAL');
    exit(1);
}
