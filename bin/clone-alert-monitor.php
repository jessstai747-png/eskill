#!/usr/bin/env php
<?php
/**
 * Clone Alert Monitor Worker
 * 
 * Monitora sistema de clonagem e envia alertas quando necessário
 * 
 * Uso:
 *   php bin/clone-alert-monitor.php
 *   php bin/clone-alert-monitor.php --once
 *   php bin/clone-alert-monitor.php --severity=critical
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\CloneAlertNotificationService;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Parse options
$options = getopt('', ['once', 'severity:', 'help']);

if (isset($options['help'])) {
    echo "Clone Alert Monitor Worker\n\n";
    echo "Uso:\n";
    echo "  php bin/clone-alert-monitor.php              # Loop contínuo\n";
    echo "  php bin/clone-alert-monitor.php --once       # Executar uma vez\n";
    echo "  php bin/clone-alert-monitor.php --severity=critical  # Apenas críticos\n";
    echo "\nOpções:\n";
    echo "  --once       Executar uma verificação e sair\n";
    echo "  --severity   Filtrar alertas (info|warning|error|critical)\n";
    echo "  --help       Mostrar esta ajuda\n";
    exit(0);
}

$runOnce = isset($options['once']);
$severity = $options['severity'] ?? null;

$alertService = new CloneAlertNotificationService();

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║                                                               ║\n";
echo "║          CLONE ALERT MONITOR - WORKER                        ║\n";
echo "║                                                               ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";
echo "\n";

if ($runOnce) {
    echo "Modo: Execução única\n";
} else {
    echo "Modo: Loop contínuo (CTRL+C para parar)\n";
}

if ($severity) {
    echo "Filtro de severidade: {$severity}\n";
}

echo "\n" . str_repeat("─", 65) . "\n\n";

$iteration = 0;

do {
    $iteration++;
    $timestamp = date('Y-m-d H:i:s');
    
    echo "[{$timestamp}] Iteração #{$iteration}\n";
    
    try {
        // Executar todas as verificações
        $results = $alertService->runAllChecks();
        
        $totalAlerts = 0;
        
        // Resumir resultados
        foreach ($results as $checkType => $alerts) {
            if ($checkType === 'timestamp') continue;
            
            $count = count($alerts);
            $totalAlerts += $count;
            
            if ($count > 0) {
                echo "  ⚠️  {$checkType}: {$count} alerta(s) criado(s)\n";
                
                foreach ($alerts as $alert) {
                    echo "      [{$alert['severity']}] {$alert['title']}\n";
                }
            } else {
                echo "  ✅ {$checkType}: OK\n";
            }
        }
        
        if ($totalAlerts === 0) {
            echo "  ✨ Sistema saudável - nenhum alerta\n";
        }
        
        // Mostrar alertas ativos se houver filtro de severidade
        if ($severity) {
            $activeAlerts = $alertService->getActiveAlerts($severity);
            
            if (!empty($activeAlerts)) {
                echo "\n  📋 Alertas ativos ({$severity}):\n";
                foreach ($activeAlerts as $alert) {
                    echo "      ID {$alert['id']}: {$alert['title']}\n";
                    echo "         Desde: {$alert['triggered_at']}\n";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "  ❌ Erro: " . $e->getMessage() . "\n";
        error_log("Clone Alert Monitor Error: " . $e->getMessage());
    }
    
    echo "\n";
    
    if (!$runOnce) {
        // Aguardar 5 minutos entre verificações
        echo "Aguardando 5 minutos até próxima verificação...\n";
        echo str_repeat("─", 65) . "\n\n";
        sleep(300);
    }
    
} while (!$runOnce);

echo "✅ Monitor finalizado\n\n";
