<?php
/**
 * Script de polling automático para pedidos
 * 
 * Este script deve ser executado periodicamente via CRON
 * Exemplo de CRON (a cada 30 minutos):
 * 0,30 * * * * php /caminho/para/eskill/scripts/poll_orders.php
 */

// Carregar autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use App\Services\PollingService;
use App\Services\JobService;

try {
    $pollingService = new PollingService();
    $jobService = new JobService();
    
    // Verificar se polling está habilitado
    if (!$pollingService->isPollingEnabled()) {
        echo "Polling está desabilitado. Configure em config/app.php\n";
        exit(0);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando polling de pedidos...\n";
    
    // Executar polling
    $result = $pollingService->pollOrders();
    
    echo "Contas processadas: {$result['total_accounts']}\n";
    echo "Jobs criados: {$result['jobs_created']}\n";
    
    // Processar jobs pendentes
    echo "Processando jobs...\n";
    $processed = $jobService->process(50);
    
    echo "Jobs processados: " . count($processed) . "\n";
    
    // Limpar jobs antigos (mais de 30 dias)
    $deleted = $jobService->cleanOldJobs(30);
    if ($deleted > 0) {
        echo "Jobs antigos removidos: {$deleted}\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Polling concluído com sucesso.\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
