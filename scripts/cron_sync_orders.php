<?php

/**
 * Script de Sincronização de Pedidos (CRON)
 * Executar a cada 5-10 minutos
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Database;
use App\Services\OrderService;
use App\Services\SyncStatusService;
use Dotenv\Dotenv;

// Inicialização do ambiente
$rootDir = dirname(__DIR__);
if (file_exists($rootDir . '/.env')) {
    $dotenv = Dotenv::createImmutable($rootDir);
    $dotenv->load();
}

// Configuração de Log
$logFile = $rootDir . '/storage/logs/cron_sync_orders.log';
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    echo $formattedMessage; 
}

// Lock file para evitar execuções sobrepostas
$lockFile = $rootDir . '/storage/cron_sync_orders.lock';
$fp = fopen($lockFile, 'w+');

if (!flock($fp, LOCK_EX | LOCK_NB)) {
    logMessage("CRITICAL: O script já está em execução. Encerrando.");
    exit(0);
}

try {
    logMessage("INFO: Iniciando sincronização de pedidos...");

    // Obter conexão com banco
    $db = Database::getInstance();
    $syncStatus = new SyncStatusService();

    // Buscar contas ativas
    $stmt = $db->query("SELECT id, nickname, user_id FROM ml_accounts WHERE status = 'active'");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($accounts)) {
        logMessage("WARNING: Nenhuma conta ativa encontrada.");
    } else {
        logMessage("INFO: Encontradas " . count($accounts) . " contas para sincronizar.");

        foreach ($accounts as $account) {
            $accountId = $account['id'];
            $nickname = $account['nickname'];
            
            logMessage("INFO: Sincronizando conta: $nickname (ID: $accountId)");
            
            // Marcar início
            $syncStatus->markRunning(SyncStatusService::RESOURCE_ORDERS, $accountId);

            try {
                // Instanciar OrderService para esta conta
                $orderService = new OrderService($accountId);
                
                // Sincronizar últimos 50 pedidos (incremental deve ser tratado dentro do Service)
                $result = $orderService->syncOrders(null, 50);
                
                $syncedCount = $result['synced'] ?? 0;
                $duration = $result['duration'] ?? 0; // Se houver
                
                logMessage("SUCCESS: Conta $nickname sincronizada. Pedidos processados: " . print_r($result, true));
                
                // Marcar sucesso
                $syncStatus->markSuccess(
                    SyncStatusService::RESOURCE_ORDERS,
                    $accountId,
                    $syncedCount
                );
                
                // Sleep para evitar rate limit global se houver muitas contas
                sleep(2);
                
            } catch (Exception $e) {
                logMessage("ERROR: Falha ao sincronizar conta $nickname: " . $e->getMessage());
                
                // Marcar erro
                $syncStatus->markError(SyncStatusService::RESOURCE_ORDERS, $accountId, $e->getMessage());
            }
        }
    }

    logMessage("INFO: Sincronização finalizada com sucesso.");

} catch (Exception $e) {
    logMessage("CRITICAL: Erro fatal no script: " . $e->getMessage());
} finally {
    flock($fp, LOCK_UN);
    fclose($fp);
}
