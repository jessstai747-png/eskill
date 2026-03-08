#!/usr/bin/env php
<?php

/**
 * 🔬 X-Ray Worker
 *
 * Worker CLI para execução assíncrona do diagnóstico Raio X de contas ML.
 * Roda em background liberando o HTTP request imediatamente.
 *
 * Uso:
 *   php bin/xray-worker.php <accountId>              # Roda X-Ray para uma conta
 *   php bin/xray-worker.php --queue                  # Processa fila de jobs pendentes
 *   php bin/xray-worker.php --all                    # Roda para todas as contas ativas
 *   php bin/xray-worker.php --apply <reportId>       # Aplica plano de recuperação (dry-run)
 *   php bin/xray-worker.php --apply <reportId> --force  # Aplica plano REAL
 *   php bin/xray-worker.php --help                   # Exibe ajuda
 *
 * @package App\Bin
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

// Carregar .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            putenv($line);
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Services\AccountXRayService;
use App\Services\AccountRecoveryApplierService;

// ─────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────

function xrayLog(string $level, string $msg): void
{
    $ts = date('Y-m-d H:i:s');
    $prefix = match(strtoupper($level)) {
        'ERROR' => '❌',
        'WARN'  => '⚠️ ',
        'OK'    => '✅',
        default => '  ',
    };
    echo "[{$ts}] [{$level}] {$prefix} {$msg}" . PHP_EOL;
}

function xrayHelp(): void
{
    echo <<<HELP
🔬 X-Ray Worker — Diagnóstico Raio X de Contas ML

Uso:
  php bin/xray-worker.php <accountId>               Diagnostica uma conta específica
  php bin/xray-worker.php --queue                   Processa todos os jobs na fila
  php bin/xray-worker.php --all                     Diagnostica todas as contas ativas
  php bin/xray-worker.php --apply <reportId>        Aplica plano (dry-run — simulação)
  php bin/xray-worker.php --apply <reportId> --force Aplica plano REAL via ML API
  php bin/xray-worker.php --help                    Esta ajuda

Exemplos:
  php bin/xray-worker.php 3
  php bin/xray-worker.php --apply 12 --force
  php bin/xray-worker.php --queue

HELP;
}

function ensureQueueTable(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS xray_job_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            status ENUM('queued','processing','completed','failed') DEFAULT 'queued',
            options_json TEXT,
            report_id INT DEFAULT NULL,
            error_message TEXT,
            pid INT DEFAULT NULL,
            queued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            started_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            INDEX idx_status (status),
            INDEX idx_account (account_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function updateJobStatus(PDO $db, int $jobId, string $status, array $extra = []): void
{
    $set = ['status = :status'];
    $params = ['status' => $status, 'id' => $jobId];

    if ($status === 'processing') {
        $set[] = 'started_at = NOW()';
        $set[] = 'pid = :pid';
        $params['pid'] = getmypid();
    } elseif (in_array($status, ['completed', 'failed'], true)) {
        $set[] = 'completed_at = NOW()';
    }

    if (isset($extra['report_id'])) {
        $set[] = 'report_id = :report_id';
        $params['report_id'] = $extra['report_id'];
    }

    if (isset($extra['error'])) {
        $set[] = 'error_message = :error_msg';
        $params['error_msg'] = $extra['error'];
    }

    $db->prepare('UPDATE xray_job_queue SET ' . implode(', ', $set) . ' WHERE id = :id')
       ->execute($params);
}

// ─────────────────────────────────────────────────────────────
// Parse args
// ─────────────────────────────────────────────────────────────

$args   = $argv;
array_shift($args); // remove script name

if (empty($args) || in_array('--help', $args, true) || in_array('-h', $args, true)) {
    xrayHelp();
    exit(0);
}

$db = Database::getInstance();
ensureQueueTable($db);

// ─────────────────────────────────────────────────────────────
// MODE: --apply <reportId> [--force]
// ─────────────────────────────────────────────────────────────

if (in_array('--apply', $args, true)) {
    $applyIdx = array_search('--apply', $args, true);
    $reportId = isset($args[$applyIdx + 1]) ? (int) $args[$applyIdx + 1] : 0;
    $isDryRun = !in_array('--force', $args, true);

    if ($reportId <= 0) {
        xrayLog('ERROR', 'Forneça um reportId válido após --apply');
        exit(1);
    }

    xrayLog('INFO', "Aplicando plano de recuperação para relatório #{$reportId}" . ($isDryRun ? ' [DRY RUN]' : ' [REAL]'));

    try {
        $applier = new AccountRecoveryApplierService();
        $result  = $applier->applyRecoveryPlan($reportId, $isDryRun);

        echo PHP_EOL . $result['summary'] . PHP_EOL . PHP_EOL;

        if (!empty($result['paused_items'])) {
            xrayLog('INFO', 'Itens pausados/a pausar:');
            foreach ($result['paused_items'] as $item) {
                $mark = $item['applied'] ? '✅' : ($isDryRun ? '🔍' : '❌');
                echo "  {$mark} {$item['item_id']} [{$item['classification']}] — {$item['title']}" . PHP_EOL;
            }
        }

        if (!empty($result['optimized_titles'])) {
            xrayLog('INFO', 'Títulos otimizados/a otimizar:');
            foreach ($result['optimized_titles'] as $item) {
                $mark = $item['applied'] ? '✅' : ($isDryRun ? '🔍' : '❌');
                echo "  {$mark} {$item['item_id']}" . PHP_EOL;
                echo "    ANTES:  {$item['original_title']}" . PHP_EOL;
                echo "    DEPOIS: {$item['optimized_title']}" . PHP_EOL;
            }
        }

        if (!empty($result['stock_alerts'])) {
            xrayLog('WARN', count($result['stock_alerts']) . ' itens SEM ESTOQUE precisam de atenção manual');
        }

        if (!empty($result['errors'])) {
            xrayLog('ERROR', 'Erros encontrados:');
            foreach ($result['errors'] as $err) {
                echo "  ❌ {$err}" . PHP_EOL;
            }
        }
    } catch (\Throwable $e) {
        xrayLog('ERROR', 'Falha ao aplicar plano: ' . $e->getMessage());
        exit(1);
    }

    exit(0);
}

// ─────────────────────────────────────────────────────────────
// MODE: --queue (processar fila)
// ─────────────────────────────────────────────────────────────

if (in_array('--queue', $args, true)) {
    xrayLog('INFO', 'Processando fila de jobs X-Ray...');

    $stmt = $db->query(
        "SELECT * FROM xray_job_queue WHERE status = 'queued' ORDER BY queued_at ASC LIMIT 10"
    );
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($jobs)) {
        xrayLog('INFO', 'Nenhum job na fila.');
        exit(0);
    }

    xrayLog('INFO', count($jobs) . ' job(s) encontrados na fila.');

    foreach ($jobs as $job) {
        $jobId     = (int) $job['id'];
        $accountId = (int) $job['account_id'];
        $options   = json_decode($job['options_json'] ?? '{}', true) ?? [];

        xrayLog('INFO', "Processando job #{$jobId} — conta #{$accountId}");
        updateJobStatus($db, $jobId, 'processing');

        try {
            $xray   = new AccountXRayService($accountId);
            $report = $xray->run($options);

            if (isset($report['error'])) {
                throw new \RuntimeException($report['error']);
            }

            $reportId = $report['report_id'] ?? null;
            updateJobStatus($db, $jobId, 'completed', ['report_id' => $reportId]);
            xrayLog('OK', "Job #{$jobId} concluído — relatório #{$reportId} — score {$report['overall_score']}");
        } catch (\Throwable $e) {
            updateJobStatus($db, $jobId, 'failed', ['error' => $e->getMessage()]);
            xrayLog('ERROR', "Job #{$jobId} falhou: " . $e->getMessage());
        }
    }

    exit(0);
}

// ─────────────────────────────────────────────────────────────
// MODE: --all (todas as contas ativas)
// ─────────────────────────────────────────────────────────────

if (in_array('--all', $args, true)) {
    xrayLog('INFO', 'Iniciando diagnóstico de todas as contas ativas...');

    $stmt = $db->query(
        "SELECT id, nickname FROM ml_accounts WHERE status = 'active' ORDER BY id ASC"
    );
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($accounts)) {
        xrayLog('WARN', 'Nenhuma conta ativa encontrada.');
        exit(0);
    }

    xrayLog('INFO', count($accounts) . ' conta(s) ativa(s) encontrada(s).');
    $exitCode = 0;

    foreach ($accounts as $account) {
        $accountId = (int) $account['id'];
        $nick      = $account['nickname'] ?? "conta#{$accountId}";

        xrayLog('INFO', "Diagnosticando: {$nick} (#{$accountId})");

        try {
            $xray   = new AccountXRayService($accountId);
            $report = $xray->run([]);

            if (isset($report['error'])) {
                throw new \RuntimeException($report['error']);
            }

            $score = $report['overall_score'] ?? 0;
            $acSt  = $report['account_status'] ?? 'UNKNOWN';
            xrayLog('OK', "{$nick} — Score: {$score}/100 — Status: {$acSt}");
        } catch (\Throwable $e) {
            xrayLog('ERROR', "{$nick} (#{$accountId}) falhou: " . $e->getMessage());
            $exitCode = 1;
        }

        sleep(3); // pausa entre contas
    }

    exit($exitCode);
}

// ─────────────────────────────────────────────────────────────
// MODE: <accountId> (conta específica)
// ─────────────────────────────────────────────────────────────

$accountId = (int) ($args[0] ?? 0);

if ($accountId <= 0) {
    xrayLog('ERROR', 'Forneça um accountId válido ou use --help para opções.');
    exit(1);
}

// Verificar se existe um job na fila para essa conta
$jobStmt = $db->prepare(
    "SELECT id FROM xray_job_queue WHERE account_id = :aid AND status IN ('queued','processing') LIMIT 1"
);
$jobStmt->execute(['aid' => $accountId]);
$existingJob = $jobStmt->fetchColumn();

if ($existingJob) {
    // Pegar job_id da fila e atualizar status
    $jobId = (int) $existingJob;
    updateJobStatus($db, $jobId, 'processing');
} else {
    // Criar novo job
    $ins = $db->prepare(
        "INSERT INTO xray_job_queue (account_id, status, options_json) VALUES (:aid, 'processing', '{}')"
    );
    $ins->execute(['aid' => $accountId]);
    $jobId = (int) $db->lastInsertId();
}

xrayLog('INFO', "Iniciando X-Ray para conta #{$accountId} (job #{$jobId})");

try {
    $xray   = new AccountXRayService($accountId);
    $report = $xray->run([]);

    if (isset($report['error'])) {
        throw new \RuntimeException($report['error']);
    }

    $score    = $report['overall_score'] ?? 0;
    $acStatus = $report['account_status'] ?? 'UNKNOWN';
    $items    = $report['items_analyzed'] ?? 0;
    $reportId = $report['report_id'] ?? null;

    updateJobStatus($db, $jobId, 'completed', ['report_id' => $reportId]);

    xrayLog('OK', "Diagnóstico concluído!");
    echo PHP_EOL;
    echo "  📊 Score Geral:     {$score}/100" . PHP_EOL;
    echo "  🏪 Status da Conta: {$acStatus}" . PHP_EOL;
    echo "  📦 Itens Analisados:{$items}" . PHP_EOL;
    echo "  📄 Relatório ID:    #{$reportId}" . PHP_EOL;

    $critical = $report['recovery_plan']['critical'] ?? [];
    if (!empty($critical)) {
        echo PHP_EOL . "  ⚠️  Ações CRÍTICAS necessárias:" . PHP_EOL;
        foreach (array_slice($critical, 0, 5) as $action) {
            echo "    • {$action['action']}" . PHP_EOL;
        }
    }

    echo PHP_EOL . "  Para aplicar o plano:" . PHP_EOL;
    echo "    [dry-run] php bin/xray-worker.php --apply {$reportId}" . PHP_EOL;
    echo "    [real]    php bin/xray-worker.php --apply {$reportId} --force" . PHP_EOL . PHP_EOL;
} catch (\Throwable $e) {
    updateJobStatus($db, $jobId, 'failed', ['error' => $e->getMessage()]);
    xrayLog('ERROR', 'Diagnóstico falhou: ' . $e->getMessage());
    xrayLog('ERROR', $e->getTraceAsString());
    exit(1);
}

exit(0);
