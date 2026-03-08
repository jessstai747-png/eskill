#!/usr/bin/env php
<?php

/**
 * 🏪 sync-now.php — Sync Imediato de Contas ML
 *
 * Diagnóstica o estado das contas e força sincronização imediata de
 * itens, pedidos e perguntas para todas as contas ativas.
 *
 * Uso:
 *   php bin/sync-now.php              # Sincroniza todas as contas ativas
 *   php bin/sync-now.php <accountId>  # Sincroniza uma conta específica
 *   php bin/sync-now.php --status     # Mostra status das contas sem sincronizar
 *   php bin/sync-now.php --help
 *
 * @package App\Bin
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

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
use App\Services\AccountSyncService;

// ─────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────

function syncLog(string $level, string $msg): void
{
    $ts     = date('Y-m-d H:i:s');
    $prefix = match(strtoupper($level)) {
        'ERROR' => '❌',
        'WARN'  => '⚠️ ',
        'OK'    => '✅',
        'INFO'  => 'ℹ️ ',
        default => '   ',
    };
    echo "[{$ts}] {$prefix} {$msg}" . PHP_EOL;
}

function showHelp(): void
{
    echo <<<HELP
🏪 sync-now.php — Sync Imediato de Contas ML

Uso:
  php bin/sync-now.php               Sincroniza todas as contas ativas
  php bin/sync-now.php <accountId>   Sincroniza uma conta específica
  php bin/sync-now.php --status      Mostra status sem sincronizar
  php bin/sync-now.php --help        Esta ajuda

HELP;
}

function showAccountStatus(PDO $db): void
{
    $accounts = $db->query(
        "SELECT id, nickname, ml_user_id, status, token_expires_at, last_synced_at,
                (SELECT COUNT(*) FROM ml_items WHERE account_id = ml_accounts.id) AS item_count,
                (SELECT COUNT(*) FROM ml_orders WHERE account_id = ml_accounts.id) AS order_count
         FROM ml_accounts
         ORDER BY id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    echo PHP_EOL;
    echo "┌────────┬──────────────────────────┬──────────────┬───────────────────────┬──────────┬─────────────┐" . PHP_EOL;
    echo "│ ID     │ Nickname                 │ Status       │ Token Expira          │ Itens    │ Pedidos     │" . PHP_EOL;
    echo "├────────┼──────────────────────────┼──────────────┼───────────────────────┼──────────┼─────────────┤" . PHP_EOL;

    if (empty($accounts)) {
        echo "│ Nenhuma conta cadastrada. Conecte via https://eskill.com.br/auth/authorize" . PHP_EOL;
    } else {
        foreach ($accounts as $a) {
            $expiry = $a['token_expires_at']
                ? date('d/m/Y H:i', strtotime($a['token_expires_at']))
                : 'N/A';
            $expired = $a['token_expires_at'] && strtotime($a['token_expires_at']) < time();
            $statusIcon = match($a['status']) {
                'active'       => '🟢',
                'inactive'     => '🟡',
                'expired'      => '🔴',
                'disconnected' => '⚫',
                default        => '❓',
            };
            printf(
                "│ %-6d │ %-24s │ %s %-10s│ %-21s │ %-8s │ %-11s│" . PHP_EOL,
                $a['id'],
                mb_substr($a['nickname'] ?? '', 0, 24),
                $statusIcon,
                $a['status'],
                $expiry . ($expired ? ' ⚠️ ' : ''),
                number_format((int)$a['item_count']),
                number_format((int)$a['order_count'])
            );
        }
    }

    echo "└────────┴──────────────────────────┴──────────────┴───────────────────────┴──────────┴─────────────┘" . PHP_EOL;
    echo PHP_EOL;

    $disconnected = array_filter($accounts, fn(array $a): bool => in_array($a['status'], ['disconnected', 'expired'], true));
    if (!empty($disconnected)) {
        echo "⚠️  CONTAS DESCONECTADAS — precisam de reautorização:" . PHP_EOL;
        foreach ($disconnected as $a) {
            echo "   • #{$a['id']} {$a['nickname']} → https://eskill.com.br/auth/authorize?reconnect={$a['id']}" . PHP_EOL;
        }
        echo PHP_EOL;
    }

    $noItems = array_filter($accounts, fn(array $a): bool => (int)$a['item_count'] === 0 && $a['status'] === 'active');
    if (!empty($noItems)) {
        echo "⚠️  CONTAS SEM ITENS SINCRONIZADOS — rodar sync:" . PHP_EOL;
        foreach ($noItems as $a) {
            echo "   • php bin/sync-now.php {$a['id']}" . PHP_EOL;
        }
        echo PHP_EOL;
    }
}

// ─────────────────────────────────────────────────────────────
// Parse args
// ─────────────────────────────────────────────────────────────

$args = $argv;
array_shift($args);

if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    showHelp();
    exit(0);
}

$db = Database::getInstance();

if (in_array('--status', $args, true)) {
    syncLog('INFO', 'Status das contas ML:');
    showAccountStatus($db);
    exit(0);
}

// ─────────────────────────────────────────────────────────────
// Resolve contas a sincronizar
// ─────────────────────────────────────────────────────────────

$specificId = !empty($args[0]) && is_numeric($args[0]) ? (int)$args[0] : null;

if ($specificId !== null) {
    $stmt = $db->prepare("SELECT * FROM ml_accounts WHERE id = :id");
    $stmt->execute(['id' => $specificId]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->query("SELECT * FROM ml_accounts WHERE status = 'active' ORDER BY id ASC");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (empty($accounts)) {
    syncLog('WARN', 'Nenhuma conta encontrada para sincronizar.');
    echo PHP_EOL;
    echo "  Para conectar contas, acesse:" . PHP_EOL;
    echo "    https://eskill.com.br/auth/authorize" . PHP_EOL . PHP_EOL;
    echo "  Status atual das contas:" . PHP_EOL;
    showAccountStatus($db);
    exit(0);
}

syncLog('INFO', count($accounts) . ' conta(s) para sincronizar.');
echo PHP_EOL;

$exitCode = 0;
$syncService = new AccountSyncService();

foreach ($accounts as $account) {
    $accountId = (int) $account['id'];
    $nick      = $account['nickname'] ?? "conta#{$accountId}";
    $status    = $account['status'] ?? 'unknown';

    echo "──────────────────────────────────────────────" . PHP_EOL;
    syncLog('INFO', "Sincronizando: {$nick} (#{$accountId}) [{$status}]");

    if (in_array($status, ['disconnected', 'expired'], true)) {
        syncLog('WARN', "Conta desconectada/expirada. Reconecte em:");
        echo "    → https://eskill.com.br/auth/authorize?reconnect={$accountId}" . PHP_EOL . PHP_EOL;
        $exitCode = 1;
        continue;
    }

    $start  = microtime(true);

    try {
        $result = $syncService->syncAccount($accountId);
        $elapsed = round(microtime(true) - $start, 1);

        if ($result['success']) {
            syncLog('OK', "Sync concluído em {$elapsed}s");

            foreach ($result['steps'] as $step) {
                $icon = match($step['status'] ?? '') {
                    'success' => '  ✅',
                    'warning' => '  ⚠️ ',
                    'error'   => '  ❌',
                    default   => '  ·',
                };
                $data = '';
                if (!empty($step['data'])) {
                    $data = ' — ' . json_encode($step['data'], JSON_UNESCAPED_UNICODE);
                }
                echo "{$icon} {$step['step']}{$data}" . PHP_EOL;
            }

            // Mostrar stats principais
            if (!empty($result['stats'])) {
                $s = $result['stats'];
                echo PHP_EOL;
                printf(
                    "  📦 Itens: %d novos, %d atualizados, %d total" . PHP_EOL,
                    (int)($s['new'] ?? $s['items_inserted'] ?? 0),
                    (int)($s['updated'] ?? $s['items_updated'] ?? 0),
                    (int)($s['total'] ?? $s['items_total'] ?? 0)
                );
            }
        } else {
            syncLog('ERROR', "Sync falhou: " . ($result['error'] ?? 'erro desconhecido'));

            if (!empty($result['needs_reconnect'])) {
                echo "  → Reconecte em: https://eskill.com.br/auth/authorize?reconnect={$accountId}" . PHP_EOL;
            }

            $exitCode = 1;
        }
    } catch (\Throwable $e) {
        syncLog('ERROR', "Exceção: " . $e->getMessage());
        $exitCode = 1;
    }

    echo PHP_EOL;
    sleep(2); // espaçamento entre contas
}

echo "──────────────────────────────────────────────" . PHP_EOL;
syncLog('INFO', 'Sync finalizado.');
echo PHP_EOL;
echo "  Status atual após sync:" . PHP_EOL;
showAccountStatus($db);

exit($exitCode);
