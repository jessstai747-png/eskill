#!/usr/bin/env php
<?php
/**
 * Clone Post Actions Worker
 * 
 * Processa ações pós-clone como Tech Sheet, SEO, Pricing
 * 
 * Uso:
 *   php bin/clone-post-actions-worker.php                 # Processar todas as ações pendentes
 *   php bin/clone-post-actions-worker.php --once          # Processar um lote e sair
 *   php bin/clone-post-actions-worker.php --job=clone_xxx # Ações de um job específico
 *   php bin/clone-post-actions-worker.php --limit=100     # Limitar quantidade
 *   php bin/clone-post-actions-worker.php --dry-run       # Simular sem executar
 * 
 * @package App\Workers
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Services\ClonePostActionsService;

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/..');
$dotenv->load();

// Configurações
define('WORKER_NAME', 'clone-post-actions-worker');
define('LOG_FILE', __DIR__ . '/../storage/logs/clone-post-actions-worker.log');
define('LOCK_FILE', __DIR__ . '/../storage/locks/clone-post-actions-worker.lock');
define('DEFAULT_BATCH_SIZE', 50);
define('RATE_LIMIT_DELAY_MS', 1000); // 1s entre ações

// Parse argumentos
$options = getopt('', ['once', 'job:', 'limit:', 'dry-run', 'help', 'verbose']);

if (isset($options['help'])) {
    echo <<<HELP
Clone Post Actions Worker

Processa ações pós-clone como Tech Sheet, SEO, Pricing

Uso:
  php bin/clone-post-actions-worker.php [opções]

Opções:
  --once            Processa um lote e sai
  --job=JOB_ID      Ações de um job específico
  --limit=N         Limitar quantidade de ações (padrão: 50)
  --retry-failed    Reprocessar ações que falharam anteriormente
  --dry-run         Simula processamento sem executar
  --verbose         Modo verboso
  --help            Mostra esta ajuda

Exemplos:
  php bin/clone-post-actions-worker.php
  php bin/clone-post-actions-worker.php --once --verbose
  php bin/clone-post-actions-worker.php --job=clone_20260130120000_abc123

Tipos de ações suportadas:
  - tech_sheet: Dispara análise de Ficha Técnica
  - seo_optimize: Dispara otimização SEO
  - pricing_apply: Aplica precificação competitiva
  - activate: Ativa o anúncio

HELP;
    exit(0);
}

$runOnce = isset($options['once']);
$specificJobId = $options['job'] ?? null;
$limit = isset($options['limit']) ? (int)$options['limit'] : DEFAULT_BATCH_SIZE;
$retryFailed = isset($options['retry-failed']);
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

/**
 * Logger simples
 */
function logMessage(string $message, string $level = 'INFO'): void
{
    global $verbose;
    
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] [{$level}] {$message}\n";
    
    // Escrever no arquivo de log
    @file_put_contents(LOG_FILE, $logLine, FILE_APPEND);
    
    // Exibir no console se verbose ou se for erro/warning
    if ($verbose || in_array($level, ['ERROR', 'WARNING'])) {
        $color = match($level) {
            'ERROR' => "\033[31m",   // Vermelho
            'WARNING' => "\033[33m", // Amarelo
            'SUCCESS' => "\033[32m", // Verde
            default => "\033[0m"      // Reset
        };
        echo "{$color}[{$level}]\033[0m {$message}\n";
    }
}

/**
 * Adquirir lock
 */
function acquireLock(): bool
{
    $lockDir = dirname(LOCK_FILE);
    if (!is_dir($lockDir)) {
        mkdir($lockDir, 0755, true);
    }
    
    $fp = fopen(LOCK_FILE, 'c+');
    if (!$fp) {
        return false;
    }
    
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        fclose($fp);
        return false;
    }
    
    ftruncate($fp, 0);
    fwrite($fp, (string) getmypid());
    
    $GLOBALS['lockHandle'] = $fp;
    return true;
}

/**
 * Liberar lock
 */
function releaseLock(): void
{
    if (isset($GLOBALS['lockHandle']) && is_resource($GLOBALS['lockHandle'])) {
        flock($GLOBALS['lockHandle'], LOCK_UN);
        fclose($GLOBALS['lockHandle']);
        @unlink(LOCK_FILE);
        unset($GLOBALS['lockHandle']);
    }
}

/**
 * Obter ações pendentes
 */
function getPendingActions(PDO $db, ?string $jobId, int $limit, bool $retryFailed = false): array
{
    $statusCondition = $retryFailed ? "status IN ('pending', 'failed')" : "status = 'pending'";

    $limitSql = max(1, min(200, (int)$limit));

    $sql = "SELECT * FROM clone_post_actions_log WHERE $statusCondition";
    $params = [];

    if ($jobId) {
        $sql .= " AND clone_job_id = :job_id";
        $params['job_id'] = $jobId;
    }

    $sql .= " ORDER BY created_at ASC LIMIT {$limitSql}";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Processar ações
 */
function processActions(array $actions, ClonePostActionsService $service, bool $dryRun): array
{
    $stats = ['processed' => 0, 'success' => 0, 'failed' => 0, 'skipped' => 0];

    foreach ($actions as $action) {
        $actionId = $action['id'];
        $actionType = $action['action_type'];
        $targetItemId = $action['target_item_id'];

        logMessage("  Processando ação #{$actionId}: {$actionType} para item {$targetItemId}");

        if ($dryRun) {
            logMessage("  [DRY-RUN] Ação simulada", 'SUCCESS');
            $stats['success']++;
            $stats['processed']++;
            continue;
        }

        try {
            $result = $service->executeAction($action);

            if ($result['status'] === 'success' || $result['status'] === 'completed') {
                $stats['success']++;
                logMessage("  Ação concluída com sucesso", 'SUCCESS');
            } elseif ($result['status'] === 'skipped') {
                $stats['skipped']++;
                logMessage("  Ação ignorada: " . ($result['message'] ?? 'N/A'), 'WARNING');
            } else {
                $stats['failed']++;
                logMessage("  Ação falhou: " . ($result['error'] ?? 'Erro desconhecido'), 'ERROR');
            }

        } catch (\Exception $e) {
            $stats['failed']++;
            logMessage("  Erro: " . $e->getMessage(), 'ERROR');
        }

        $stats['processed']++;

        // Rate limiting
        usleep(RATE_LIMIT_DELAY_MS * 1000);
    }

    return $stats;
}

// ============================================================================
// MAIN
// ============================================================================

logMessage("Worker iniciado (PID: " . getmypid() . ")");

// Verificar lock
if (!acquireLock()) {
    logMessage("Outra instância do worker já está em execução. Saindo.", 'WARNING');
    exit(0);
}

// Registrar shutdown
register_shutdown_function('releaseLock');

// Tratar sinais
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() {
        logMessage("Recebido SIGTERM, finalizando...", 'WARNING');
        releaseLock();
        exit(0);
    });
    pcntl_signal(SIGINT, function() {
        logMessage("Recebido SIGINT, finalizando...", 'WARNING');
        releaseLock();
        exit(0);
    });
}

try {
    $db = Database::getInstance();
    $service = new ClonePostActionsService();

    do {
        // Obter ações pendentes
        $actions = getPendingActions($db, $specificJobId, $limit, $retryFailed);

        if (empty($actions)) {
            if ($runOnce || $specificJobId) {
                logMessage("Nenhuma ação pendente encontrada");
                break;
            }
            logMessage("Aguardando novas ações...");
            sleep(30);
            continue;
        }

        logMessage("Encontradas " . count($actions) . " ações pendentes");

        $stats = processActions($actions, $service, $dryRun);

        logMessage("Lote concluído: {$stats['success']} sucesso, {$stats['failed']} falhas, {$stats['skipped']} ignoradas");

        if ($runOnce) {
            break;
        }

        // Pausa entre lotes
        sleep(5);

        // Processar sinais
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

    } while (true);

} catch (\Exception $e) {
    logMessage("Erro fatal: " . $e->getMessage(), 'ERROR');
    exit(1);
}

logMessage("Worker finalizado");
releaseLock();
exit(0);
