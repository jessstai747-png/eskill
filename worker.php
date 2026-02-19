<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Database.php';

// Carrega variáveis de ambiente (.env) para execução em CLI
try {
    if (file_exists(__DIR__ . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();
    }
} catch (Throwable $e) {
    echo "[Worker] Aviso: não foi possível carregar .env (" . $e->getMessage() . ")\n";
}

// Carregar variáveis de ambiente manualmente se necessário, ou assumir que o sistema injeta
// Para simplificar neste ambiente, vamos assumir que as classes carregam o que precisam.

use App\Services\JobService;

// Configuração
$maxMemory = 128 * 1024 * 1024; // 128MB
$startTime = time();
$maxTime = 300; // 5 minutos de execução por worker para evitar memory leaks (restart via supervisor)

echo "[Worker] Iniciando processamento de fila...\n";

use App\Services\QueueService;

echo "[Worker] Iniciando processamento de fila (Redis Driver)...\n";

$jobService = new JobService();
$queueService = new QueueService();

while (true) {
    // 1. Verificar memória
    if (memory_get_usage() > $maxMemory) {
        echo "[Worker] Limite de memória atingido. Reiniciando...\n";
        exit;
    }

    // 2. Verificar tempo
    if ((time() - $startTime) > $maxTime) {
        echo "[Worker] Tempo limite atingido. Reiniciando...\n";
        exit;
    }

    // 3. Processar Jobs via Redis
    try {
        // Bloqueia por até 5 segundos esperando job
        $payload = $queueService->pop('default', 5);
        
        if ($payload && isset($payload['payload']['job_id'])) {
            $jobId = $payload['payload']['job_id'];
            echo "[Worker] Recebido Job #{$jobId} do Redis.\n";
            
            $job = $jobService->getJob($jobId);
            if ($job && $job['status'] === 'pending') {
                $result = $jobService->processJob($job);
                echo "[Worker] Job #{$jobId} ({$job['type']}) concluído.\n";
            } else {
                echo "[Worker] Job #{$jobId} ignorado (não encontrado ou não pendente).\n";
            }
        } else {
            // Fallback: polling no DB para garantir que jobs pendentes não fiquem presos
            $pendingPriority = $jobService->getPendingJobs(5);
            $priorityProcessed = false;

            foreach ($pendingPriority as $pendingJob) {
                if (($pendingJob['type'] ?? '') === 'ean_mp_webhook') {
                    $jobService->processJob($pendingJob);
                    echo "[Worker] Fallback DB priorizou job ean_mp_webhook #" . (int)$pendingJob['id'] . "\n";
                    $priorityProcessed = true;
                    break;
                }
            }

            if (!$priorityProcessed) {
                $fallback = $jobService->process(1);
                if (!empty($fallback)) {
                    echo "[Worker] Fallback DB processou " . count($fallback) . " job(s).\n";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "[Worker] Erro Redis/fila: " . $e->getMessage() . " | ativando fallback DB...\n";
        try {
            $fallback = $jobService->process(2);
            if (!empty($fallback)) {
                echo "[Worker] Fallback DB processou " . count($fallback) . " job(s).\n";
            }
        } catch (Exception $fallbackError) {
            echo "[Worker] Fallback DB também falhou: " . $fallbackError->getMessage() . "\n";
        }
        sleep(2);
    }
}
