<?php

/**
 * Cron Job: Sincronizar anúncios de todas as contas ativas
 * 
 * Executa sincronização completa de itens (preço, estoque, status, etc.)
 * Utiliza o ItemSyncService para garantir que TODOS os itens sejam processados.
 * 
 * Configuração crontab:
 * 0 * * * * php /home/eskill/htdocs/eskill.com.br/scripts/cron_sync_items.php >> /home/eskill/htdocs/eskill.com.br/storage/logs/cron_sync_items.log 2>&1
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;
use App\Services\ItemSyncService;
use App\Services\LoggingService;
use App\Services\SyncStatusService;

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
    logToConsole("=== Iniciando sincronização completa de anúncios ===");

    $db = Database::getInstance();
    $syncStatus = new SyncStatusService();

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

    $itemSyncService = new ItemSyncService();

    foreach ($accounts as $account) {
        $accountId = (int)$account['id'];
        $nickname = $account['nickname'] ?? $account['ml_user_id'];

        logToConsole("--------------------------------------------------");
        logToConsole("Processando conta: $nickname (ID: $accountId)");

        // Marcar início da sincronização
        $syncStatus->markRunning(SyncStatusService::RESOURCE_ITEMS, $accountId);

        try {
            // Executa a sincronização completa
            $stats = $itemSyncService->syncForAccount($accountId);

            $found = $stats['total_found'] ?? 0;
            $synced = $stats['total_synced'] ?? 0;
            $batches = $stats['batches'] ?? 0;

            logToConsole("✅ Conta $nickname: $synced/$found anúncios sincronizados em $batches lotes.");
            
            // Marcar sucesso
            $syncStatus->markSuccess(
                SyncStatusService::RESOURCE_ITEMS,
                $accountId,
                $synced,
                $stats['last_scroll_id'] ?? null
            );

            // Pequeno delay entre contas
            sleep(2);

        } catch (Exception $e) {
            $error = $e->getMessage();
            logToConsole("❌ Erro na conta $nickname: $error", 'ERROR');
            $logger->error('CRON_ITEM_SYNC_ERROR', "Erro ao sincronizar conta $accountId", ['error' => $error]);
            
            // Marcar erro
            $syncStatus->markError(SyncStatusService::RESOURCE_ITEMS, $accountId, $error);
        }
    }

    logToConsole("--------------------------------------------------");
    logToConsole("=== Sincronização concluída ===");

} catch (Exception $e) {
    logToConsole("❌ ERRO CRÍTICO: " . $e->getMessage(), 'CRITICAL');
    exit(1);
}
