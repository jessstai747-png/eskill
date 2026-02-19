<?php
/**
 * Script para processar agendamentos de clonagem
 * Deve ser executado via cron a cada minuto
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\CatalogCloneService;

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

try {
    $service = new CatalogCloneService();
    
    echo "[" . date('Y-m-d H:i:s') . "] Verificando agendamentos...\n";
    
    $results = $service->processScheduledClones();
    
    if (empty($results)) {
        echo "  Nenhum agendamento para executar agora.\n";
    } else {
        echo "  Processados " . count($results) . " agendamentos:\n";
        
        foreach ($results as $result) {
            $status = $result['status'] === 'success' ? '✅' : '❌';
            echo "  {$status} Agendamento {$result['schedule_id']}: {$result['status']}";
            
            if (isset($result['jobs_created'])) {
                echo " ({$result['jobs_created']} jobs criados)";
            }
            
            if (isset($result['message'])) {
                echo " - {$result['message']}";
            }
            
            echo "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "📍 " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}