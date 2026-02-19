#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Catalog Clone Worker
 * 
 * Processor especializado para jobs de clonagem (type='catalog_clone_item')
 * da tabela 'jobs'.
 * 
 * Uso:
 *   php bin/catalog-clone-worker.php
 *   php bin/catalog-clone-worker.php --once
 *   php bin/catalog-clone-worker.php --verbose
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;
use App\Services\JobService;
use App\Services\CloneNotificationService;

// Setup
define('WORKER_NAME', 'catalog-clone-worker');
define('LOG_FILE', __DIR__ . '/../storage/logs/catalog-clone-worker.log');
define('LOCK_FILE', __DIR__ . '/../storage/locks/catalog-clone-worker.lock');

// Options
$options = getopt('', ['once', 'job:', 'recover-stuck', 'dry-run', 'help', 'verbose']);

if (isset($options['help'])) {
    echo "Usage: php bin/catalog-clone-worker.php [--once] [--job=ID] [--verbose] [--recover-stuck]\n";
    exit(0);
}

if (isset($options['recover-stuck'])) {
    $db = Database::getInstance();
    $stmt = $db->query("UPDATE jobs SET status='pending', attempts = attempts + 1 WHERE status='processing' AND type='catalog_clone_item' AND updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    echo "Recovered stuck jobs: " . $stmt->rowCount() . "\n";
    exit(0);
}

$runOnce = isset($options['once']);
$specificJobId = $options['job'] ?? null;
$verbose = isset($options['verbose']);

function logMessage($msg, $level='INFO') {
    global $verbose;
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[$timestamp] [$level] $msg";
    // Ensure directory exists
    $dir = dirname(LOG_FILE);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    
    file_put_contents(LOG_FILE, $formatted . PHP_EOL, FILE_APPEND);
    if ($verbose || $level === 'ERROR') {
        echo $formatted . PHP_EOL;
    }
}

try {
    $db = Database::getInstance();
    $jobService = new JobService();

    logMessage("Worker Iniciado");

    while (true) {
        $job = null;
        
        // 1. Fetch Job
        if ($specificJobId) {
             $stmt = $db->prepare("SELECT * FROM jobs WHERE id = :id AND type = 'catalog_clone_item'");
             $stmt->execute(['id' => $specificJobId]);
             $job = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
             // Fetch pending catalog_clone_item
             // Priority to older jobs
             $stmt = $db->query("SELECT * FROM jobs WHERE type = 'catalog_clone_item' AND status = 'pending' AND (scheduled_at IS NULL OR scheduled_at <= NOW()) ORDER BY created_at ASC LIMIT 1");
             $job = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$job) {
             if ($runOnce) {
                 logMessage("Nenhum job encontrado. Encerrando (--once).");
                 break;
             }
             if ($verbose) echo ".";
             sleep(5);
             continue;
        }
        
        logMessage("Processando Job ID: {$job['id']}");
        
        // Obter account_id do job
        $accountId = (int)($job['account_id'] ?? 0);
        $notifier = $accountId > 0 ? new CloneNotificationService($accountId) : null;
        
        // Notificar início (apenas se houver notifier configurado)
        $jobPayload = json_decode($job['payload'] ?? '{}', true);
        if ($notifier) {
            try {
                $notifier->notifyJobStarted((int)$job['id'], [
                    'total_items' => count($jobPayload['item_ids'] ?? []),
                    'source_type' => $jobPayload['source_type'] ?? 'items',
                    'target_account' => $jobPayload['target_account_id'] ?? null,
                ]);
            } catch (Exception $e) {
                logMessage("Erro ao notificar início: " . $e->getMessage(), 'WARN');
            }
        }
        
        // 2. Process via JobService
        try {
            // JobService handles status updates
            $result = $jobService->processJob($job);
            
            if ($result['status'] === 'completed') {
                 logMessage("Job {$job['id']} Sucesso!");
                 
                 // Notificar conclusão
                 if ($notifier) {
                     try {
                         $notifier->notifyJobCompleted((int)$job['id'], [
                             'total' => $result['total'] ?? count($jobPayload['item_ids'] ?? []),
                             'success' => $result['success'] ?? $result['total'] ?? 0,
                             'failed' => $result['failed'] ?? 0,
                             'duration' => $result['duration'] ?? 'N/A',
                         ]);
                     } catch (Exception $e) {
                         logMessage("Erro ao notificar conclusão: " . $e->getMessage(), 'WARN');
                     }
                 }
            } else {
                 logMessage("Job {$job['id']} Falhou/Retry: " . ($result['error'] ?? 'Unknown'), 'ERROR');
                 
                 // Notificar falha
                 if ($notifier) {
                     try {
                         $notifier->notifyJobFailed((int)$job['id'], $result['error'] ?? 'Unknown error');
                     } catch (Exception $e) {
                         logMessage("Erro ao notificar falha: " . $e->getMessage(), 'WARN');
                     }
                 }
            }
        } catch (Exception $e) {
            logMessage("Exception processando Job {$job['id']}: " . $e->getMessage(), 'ERROR');
            
            // Notificar exceção
            if ($notifier) {
                try {
                    $notifier->notifyJobFailed((int)$job['id'], $e->getMessage());
                } catch (Exception $notifyEx) {
                    // Ignora
                }
            }
        }
        
        // Rate limit: 0.5s pause
        usleep(500000);
        
        if ($runOnce) break;
    }

} catch (Exception $e) {
    logMessage("Fatal Error: " . $e->getMessage(), 'ERROR');
    exit(1);
}
