#!/usr/bin/env php
<?php
/**
 * Cleanup Clone Data
 * 
 * Remove dados antigos de jobs de clonagem completados
 * 
 * Uso:
 *   php bin/cleanup-clone-data.php
 *   php bin/cleanup-clone-data.php --days=30
 *   php bin/cleanup-clone-data.php --dry-run
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Options
$options = getopt('', ['days:', 'dry-run', 'verbose', 'help']);

if (isset($options['help'])) {
    echo "Uso: php bin/cleanup-clone-data.php [options]\n";
    echo "\nOpções:\n";
    echo "  --days=N      Remover dados com mais de N dias (padrão: 30)\n";
    echo "  --dry-run     Simular sem fazer alterações\n";
    echo "  --verbose     Mostrar detalhes\n";
    echo "  --help        Mostrar esta ajuda\n";
    exit(0);
}

$days = isset($options['days']) ? (int)$options['days'] : 30;
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

function log_message($msg, $level = 'INFO') {
    global $verbose;
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[$timestamp] [$level] $msg\n";
    
    if ($verbose || $level !== 'INFO') {
        echo $formatted;
    }
    
    // Log to file
    $logFile = __DIR__ . '/../storage/logs/cleanup-clone-data.log';
    $dir = dirname($logFile);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    file_put_contents($logFile, $formatted, FILE_APPEND);
}

try {
    $db = Database::getInstance();
    
    log_message("Iniciando limpeza de dados (> $days dias)");
    
    if ($dryRun) {
        log_message("MODO DRY-RUN: Nenhuma alteração será feita", 'WARN');
    }
    
    // 1. Contar jobs a serem removidos
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM catalog_clone_jobs
        WHERE status IN ('completed', 'failed', 'cancelled')
        AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
    ");
    $stmt->execute(['days' => $days]);
    $jobCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    log_message("Jobs a remover: $jobCount");
    
    if ($jobCount > 0 && !$dryRun) {
        // 2. Remover itens dos jobs
        $stmt = $db->prepare("
            DELETE FROM catalog_clone_job_items
            WHERE job_id IN (
                SELECT job_id FROM catalog_clone_jobs
                WHERE status IN ('completed', 'failed', 'cancelled')
                AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            )
        ");
        $stmt->execute(['days' => $days]);
        $itemsRemoved = $stmt->rowCount();
        log_message("Itens removidos: $itemsRemoved");
        
        // 3. Remover jobs
        $stmt = $db->prepare("
            DELETE FROM catalog_clone_jobs
            WHERE status IN ('completed', 'failed', 'cancelled')
            AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['days' => $days]);
        $jobsRemoved = $stmt->rowCount();
        log_message("Jobs removidos: $jobsRemoved");
    }
    
    // 4. Limpar post-actions antigas
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM clone_post_actions_log
        WHERE status IN ('completed', 'failed')
        AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
    ");
    $stmt->execute(['days' => $days]);
    $postActionsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    log_message("Post-actions a remover: $postActionsCount");
    
    if ($postActionsCount > 0 && !$dryRun) {
        $stmt = $db->prepare("
            DELETE FROM clone_post_actions_log
            WHERE status IN ('completed', 'failed')
            AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['days' => $days]);
        $removed = $stmt->rowCount();
        log_message("Post-actions removidas: $removed");
    }
    
    // 5. Limpar métricas antigas (manter apenas recentes)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM clone_metrics
        WHERE metric_date < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute();
    $metricsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    log_message("Métricas antigas a remover (>90 dias): $metricsCount");
    
    if ($metricsCount > 0 && !$dryRun) {
        $stmt = $db->prepare("
            DELETE FROM clone_metrics
            WHERE metric_date < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $stmt->execute();
        $removed = $stmt->rowCount();
        log_message("Métricas removidas: $removed");
    }
    
    // 6. Otimizar tabelas
    if (!$dryRun) {
        log_message("Otimizando tabelas...");
        $db->exec("OPTIMIZE TABLE catalog_clone_jobs");
        $db->exec("OPTIMIZE TABLE catalog_clone_job_items");
        $db->exec("OPTIMIZE TABLE clone_post_actions_log");
        $db->exec("OPTIMIZE TABLE clone_metrics");
        log_message("Tabelas otimizadas");
    }
    
    log_message("Limpeza concluída com sucesso", 'SUCCESS');
    
    // Resumo
    echo "\n";
    echo "========================================\n";
    echo "RESUMO DA LIMPEZA\n";
    echo "========================================\n";
    echo "Período: > $days dias\n";
    echo "Jobs: $jobCount\n";
    echo "Post-actions: $postActionsCount\n";
    echo "Métricas antigas: $metricsCount\n";
    echo "Modo: " . ($dryRun ? "DRY-RUN (simulação)" : "EXECUÇÃO REAL") . "\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    log_message("ERRO: " . $e->getMessage(), 'ERROR');
    exit(1);
}
