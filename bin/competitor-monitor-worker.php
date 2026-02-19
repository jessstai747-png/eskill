#!/usr/bin/env php
<?php
/**
 * Competitor Monitor Worker
 * 
 * Cron job para monitoramento automático de concorrentes.
 * Escaneia a watchlist e gera alertas de preço.
 * 
 * Sugestão de cron: Executar 3x ao dia (8h, 14h, 20h)
 * 0 8,14,20 * * * php /path/to/bin/competitor-monitor-worker.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

use App\Database;
use App\Services\CompetitorMonitorService;

// Configuração de ambiente
set_time_limit(0);
ini_set('memory_limit', '512M');

echo "=== Competitor Monitor Worker ===" . PHP_EOL;
echo "Iniciado em: " . date('Y-m-d H:i:s') . PHP_EOL;
echo str_repeat('-', 50) . PHP_EOL;

try {
    $db = Database::getInstance();
    
    // Buscar todas as contas ativas com items na watchlist
    $stmt = $db->query("
        SELECT DISTINCT account_id 
        FROM pricing_watchlist 
        WHERE active = 1
    ");
    $accounts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($accounts)) {
        echo "Nenhuma conta com items na watchlist." . PHP_EOL;
        exit(0);
    }
    
    echo "Contas a processar: " . count($accounts) . PHP_EOL;
    
    $totalScanned = 0;
    $totalAlerts = 0;
    
    foreach ($accounts as $accountId) {
        echo PHP_EOL . "Processando conta: {$accountId}" . PHP_EOL;
        
        try {
            $service = new CompetitorMonitorService($accountId);
            
            // Buscar items da watchlist
            $stmt = $db->prepare("
                SELECT item_id, keywords 
                FROM pricing_watchlist 
                WHERE account_id = :account_id AND active = 1
            ");
            $stmt->execute(['account_id' => $accountId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "  Items na watchlist: " . count($items) . PHP_EOL;
            
            foreach ($items as $item) {
                try {
                    echo "  Escaneando: {$item['item_id']}...";
                    
                    // Escanear concorrentes
                    $result = $service->scanCompetitors(
                        $item['item_id'],
                        $item['keywords']
                    );
                    
                    if ($result['success']) {
                        $competitorCount = count($result['competitors'] ?? []);
                        echo " OK ({$competitorCount} concorrentes)" . PHP_EOL;
                        $totalScanned++;
                        
                        // Verificar alertas gerados
                        if (!empty($result['alerts_generated'])) {
                            $totalAlerts += count($result['alerts_generated']);
                            foreach ($result['alerts_generated'] as $alert) {
                                echo "    [ALERTA] {$alert['type']}: {$alert['message']}" . PHP_EOL;
                            }
                        }
                    } else {
                        echo " ERRO: " . ($result['error'] ?? 'Desconhecido') . PHP_EOL;
                    }
                    
                    // Rate limiting - aguardar entre scans
                    usleep(500000); // 500ms
                    
                } catch (Exception $e) {
                    echo " EXCEPTION: {$e->getMessage()}" . PHP_EOL;
                }
            }
            
        } catch (Exception $e) {
            echo "  ERRO na conta {$accountId}: {$e->getMessage()}" . PHP_EOL;
        }
    }
    
    echo PHP_EOL . str_repeat('-', 50) . PHP_EOL;
    echo "Resumo:" . PHP_EOL;
    echo "  Items escaneados: {$totalScanned}" . PHP_EOL;
    echo "  Alertas gerados: {$totalAlerts}" . PHP_EOL;
    echo "  Finalizado em: " . date('Y-m-d H:i:s') . PHP_EOL;
    
} catch (Exception $e) {
    echo "ERRO FATAL: {$e->getMessage()}" . PHP_EOL;
    exit(1);
}

exit(0);
