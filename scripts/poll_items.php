<?php
/**
 * Script de sincronização de anúncios do Mercado Livre
 * 
 * Este script sincroniza todos os anúncios de todas as contas
 * para o banco de dados local.
 * 
 * Exemplo de CRON (a cada 4 horas):
 * 0 0,4,8,12,16,20 * * * php /home/eskill/htdocs/eskill.com.br/scripts/poll_items.php
 */

// Carregar autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use App\Services\ItemService;
use App\Database;

try {
    $db = Database::getInstance();
    
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando sincronização de anúncios...\n";
    
    // Buscar todas as contas ativas
    $stmt = $db->query("
        SELECT id, nickname 
        FROM ml_accounts 
        WHERE status = 'active'
    ");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($accounts)) {
        echo "Nenhuma conta ativa encontrada.\n";
        exit(0);
    }
    
    $totalSynced = 0;
    $totalErrors = 0;
    
    foreach ($accounts as $account) {
        echo "\n[Conta: {$account['nickname']} (ID: {$account['id']})]\n";
        
        try {
            $itemService = new ItemService((int) $account['id']);
            $result = $itemService->syncItems(50);
            
            if ($result['success']) {
                echo "  ✓ Sincronizados: {$result['synced']}\n";
                echo "  ✗ Erros: {$result['errors']}\n";
                echo "  Total encontrados: {$result['total_found']}\n";
                
                $totalSynced += $result['synced'];
                $totalErrors += $result['errors'];
            } else {
                echo "  ✗ Erro: " . ($result['error'] ?? 'Desconhecido') . "\n";
                $totalErrors++;
            }
        } catch (\Exception $e) {
            echo "  ✗ Exceção: " . $e->getMessage() . "\n";
            $totalErrors++;
        }
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Sincronização concluída.\n";
    echo "Total de contas: " . count($accounts) . "\n";
    echo "Total sincronizados: {$totalSynced}\n";
    echo "Total de erros: {$totalErrors}\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
