#!/usr/bin/env php
<?php

/**
 * 📅 X-Ray Scheduler — Monitoramento Automático Diário de Contas ML
 *
 * Roda X-Ray em todas as contas ativas e:
 *  - Compara score com o relatório anterior
 *  - Alerta quando score cai > 10 pontos
 *  - Alerta quando conta passa para status TRAVADA ou PENALIZADA
 *  - Gera resumo diário no log
 *  - Envia notificação (email/webhook se configurado)
 *
 * Recomendado: crontab diário às 2h da manhã
 *   0 2 * * * /usr/bin/php /home/eskill/htdocs/eskill.com.br/bin/xray-scheduler.php >> /home/eskill/htdocs/eskill.com.br/storage/logs/xray-scheduler.log 2>&1
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

// ─────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────

function schedLog(string $level, string $msg): void
{
    $ts = date('Y-m-d H:i:s');
    echo "[{$ts}] [{$level}] {$msg}" . PHP_EOL;
}

function sendAlert(string $accountNick, string $message, string $severity, array $details = []): void
{
    $webhookUrl = $_ENV['XRAY_ALERT_WEBHOOK'] ?? '';

    schedLog($severity, "ALERTA [{$accountNick}]: {$message}");

    if (empty($webhookUrl)) {
        return;
    }

    try {
        $payload = json_encode([
            'account' => $accountNick,
            'message' => $message,
            'severity'=> $severity,
            'details' => $details,
            'time'    => date('c'),
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/json',
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        @file_get_contents($webhookUrl, false, $ctx);
    } catch (\Throwable) {
        // alert dispatch is best-effort
    }
}

function getPreviousScore(PDO $db, int $accountId): ?int
{
    $stmt = $db->prepare(
        'SELECT score_overall
         FROM account_xray_reports
         WHERE account_id = :aid
           AND status = "completed"
         ORDER BY completed_at DESC
         LIMIT 1 OFFSET 1'  // penúltimo relatório
    );
    $stmt->execute(['aid' => $accountId]);
    $val = $stmt->fetchColumn();
    return $val !== false ? (int) $val : null;
}

function getPreviousAccountStatus(PDO $db, int $accountId): ?string
{
    $stmt = $db->prepare(
        'SELECT account_status
         FROM account_xray_reports
         WHERE account_id = :aid
           AND status = "completed"
         ORDER BY completed_at DESC
         LIMIT 1 OFFSET 1'
    );
    $stmt->execute(['aid' => $accountId]);
    $val = $stmt->fetchColumn();
    return $val !== false ? (string) $val : null;
}

// ─────────────────────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────────────────────

schedLog('INFO', '=== X-Ray Scheduler iniciado ===');

$db = Database::getInstance();

$stmt = $db->query(
    "SELECT id, nickname FROM ml_accounts WHERE status = 'active' ORDER BY id ASC"
);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($accounts)) {
    schedLog('WARN', 'Nenhuma conta ativa encontrada. Encerrando.');
    exit(0);
}

schedLog('INFO', count($accounts) . ' conta(s) para processar.');

$summary = [
    'processed' => 0,
    'errors'    => 0,
    'alerts'    => 0,
    'scores'    => [],
];

foreach ($accounts as $account) {
    $accountId = (int) $account['id'];
    $nick      = $account['nickname'] ?? "conta#{$accountId}";

    schedLog('INFO', "Processando: {$nick} (#{$accountId})");

    $prevScore  = getPreviousScore($db, $accountId);
    $prevStatus = getPreviousAccountStatus($db, $accountId);

    try {
        $xray   = new AccountXRayService($accountId);
        $report = $xray->run([]);

        if (isset($report['error'])) {
            throw new \RuntimeException($report['error']);
        }

        $newScore  = (int) ($report['overall_score'] ?? 0);
        $newStatus = $report['account_status'] ?? 'UNKNOWN';

        $summary['processed']++;
        $summary['scores'][$nick] = [
            'prev'   => $prevScore,
            'now'    => $newScore,
            'status' => $newStatus,
        ];

        schedLog('INFO', "{$nick} — Score: {$newScore}/100 — Status: {$newStatus}");

        // ── Alertas ──

        // Score caiu mais de 10 pontos
        if ($prevScore !== null && ($prevScore - $newScore) >= 10) {
            $drop = $prevScore - $newScore;
            sendAlert(
                $nick,
                "Score caiu {$drop} pontos! De {$prevScore} para {$newScore}/100.",
                'WARN',
                ['prev_score' => $prevScore, 'new_score' => $newScore]
            );
            $summary['alerts']++;
        }

        // Conta ficou TRAVADA
        if ($newStatus === 'TRAVADA' && $prevStatus !== 'TRAVADA') {
            sendAlert(
                $nick,
                "CONTA TRAVADA! Vendas provavelmente bloqueadas. Score: {$newScore}/100.",
                'ERROR',
                ['status' => $newStatus, 'score' => $newScore, 'report_id' => $report['report_id'] ?? null]
            );
            $summary['alerts']++;
        }

        // Conta ficou PENALIZADA
        if ($newStatus === 'PENALIZADA' && $prevStatus !== 'PENALIZADA') {
            sendAlert(
                $nick,
                "Conta recebeu penalidade! Ação urgente necessária. Score: {$newScore}/100.",
                'ERROR',
                ['status' => $newStatus, 'score' => $newScore, 'report_id' => $report['report_id'] ?? null]
            );
            $summary['alerts']++;
        }

        // Score muito baixo (< 35) — independente de tendência
        if ($newScore < 35) {
            sendAlert(
                $nick,
                "Score crítico: {$newScore}/100. Intervenção imediata necessária.",
                'WARN',
                ['score' => $newScore, 'status' => $newStatus]
            );
            $summary['alerts']++;
        }
    } catch (\Throwable $e) {
        schedLog('ERROR', "{$nick}: " . $e->getMessage());
        $summary['errors']++;
    }

    sleep(5); // espaçamento entre contas para não sobrecarregar ML API
}

// ─────────────────────────────────────────────────────────────
// Resumo final
// ─────────────────────────────────────────────────────────────

schedLog('INFO', '=== Resumo do X-Ray Scheduler ===');
schedLog('INFO', "Contas processadas: {$summary['processed']}");
schedLog('INFO', "Erros: {$summary['errors']}");
schedLog('INFO', "Alertas disparados: {$summary['alerts']}");

echo PHP_EOL;
foreach ($summary['scores'] as $nick => $data) {
    $arrow = '';
    if ($data['prev'] !== null) {
        $diff  = $data['now'] - $data['prev'];
        $arrow = $diff > 0 ? "↑{$diff}" : ($diff < 0 ? "↓" . abs($diff) : '→');
    }
    printf("  %-30s %3d/100  %s  [%s]%s", $nick, $data['now'], $arrow, $data['status'], PHP_EOL);
}

schedLog('INFO', '=== X-Ray Scheduler finalizado ===');

exit($summary['errors'] > 0 ? 1 : 0);
