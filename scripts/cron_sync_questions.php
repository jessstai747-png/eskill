<?php

/**
 * Script de Sincronização de Perguntas (CRON)
 * Executar a cada 10-15 minutos
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Services\QuestionService;
use App\Services\SyncStatusService;
use Dotenv\Dotenv;

// Inicialização do ambiente
$rootDir = dirname(__DIR__);
if (file_exists($rootDir . '/.env')) {
    $dotenv = Dotenv::createImmutable($rootDir);
    $dotenv->load();
}

// Configuração de Log
$logFile = $rootDir . '/storage/logs/cron_sync_questions.log';
function logQMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    echo $formattedMessage; 
}

// Lock file
$lockFile = $rootDir . '/storage/cron_sync_questions.lock';
$fp = fopen($lockFile, 'w+');

if (!flock($fp, LOCK_EX | LOCK_NB)) {
    logQMessage("CRITICAL: O script já está em execução. Encerrando.");
    exit(0);
}

try {
    logQMessage("INFO: Iniciando sincronização de perguntas...");

    // Obter conexão com banco
    $db = Database::getInstance();
    $syncStatus = new SyncStatusService();

    // Buscar contas ativas
    $stmt = $db->query("SELECT id, nickname FROM ml_accounts WHERE status = 'active'");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($accounts)) {
        logQMessage("WARNING: Nenhuma conta ativa encontrada.");
    } else {
        logQMessage("INFO: Encontradas " . count($accounts) . " contas para sincronizar.");

        foreach ($accounts as $account) {
            $accountId = $account['id'];
            $nickname = $account['nickname'];
            
            logQMessage("INFO: Sincronizando perguntas: $nickname (ID: $accountId)");
            
            // Marcar início
            $syncStatus->markRunning(SyncStatusService::RESOURCE_QUESTIONS, $accountId);

            try {
                $service = new QuestionService($accountId);
                $result = $service->syncQuestions(50);
                
                $syncedCount = $result['synced'] ?? $result['total'] ?? 0;
                
                logQMessage("SUCCESS: Conta $nickname sincronizada. Perguntas: " . print_r($result, true));
                
                // Marcar sucesso
                $syncStatus->markSuccess(
                    SyncStatusService::RESOURCE_QUESTIONS,
                    $accountId,
                    $syncedCount
                );
                
                sleep(2); // Rate limit protection
                
            } catch (Exception $e) {
                logQMessage("ERROR: Falha ao sincronizar conta $nickname: " . $e->getMessage());
                
                // Marcar erro
                $syncStatus->markError(SyncStatusService::RESOURCE_QUESTIONS, $accountId, $e->getMessage());
            }
        }
    }

    logQMessage("INFO: Sincronização finalizada.");

} catch (Exception $e) {
    logQMessage("CRITICAL: Erro fatal no script: " . $e->getMessage());
} finally {
    flock($fp, LOCK_UN);
    fclose($fp);
}
