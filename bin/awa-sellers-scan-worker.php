#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * AWA Sellers Scan Worker
 *
 * Executa varredura periódica de vendedores AWA Sellers em todas as contas ativas.
 * Gera alertas para vendedores novos detectados.
 *
 * Uso:
 *   php bin/awa-sellers-scan-worker.php [--account=ID] [--dry-run] [--verbose]
 *
 * Exemplos:
 *   php bin/awa-sellers-scan-worker.php                  # Todas as contas
 *   php bin/awa-sellers-scan-worker.php --account=1      # Conta específica
 *   php bin/awa-sellers-scan-worker.php --dry-run        # Simula sem salvar
 *   php bin/awa-sellers-scan-worker.php --verbose        # Saída detalhada
 *
 * Cron recomendado: Executar diariamente às 3h
 *   0 3 * * * php /path/to/bin/awa-sellers-scan-worker.php >> storage/logs/awa-sellers-scan.log 2>&1
 */

require_once __DIR__ . '/../autoload.php';

use App\Database;
use App\Services\AlertService;
use App\Services\AwaSellerDiscoveryService;
use App\Services\AwaSellerRegistryService;

// ---------------------------------------------------------------------------
// Parse options
// ---------------------------------------------------------------------------
$opts = getopt('', ['account:', 'dry-run', 'verbose', 'help']);

if (isset($opts['help'])) {
    echo <<<HELP
AWA Sellers Scan Worker
=======================

Uso: php bin/awa-sellers-scan-worker.php [opções]

Opções:
  --account=ID    Processar apenas conta específica
  --dry-run       Simula sem salvar resultados
  --verbose       Saída detalhada
  --help          Exibir esta ajuda

HELP;
    exit(0);
}

$targetAccountId = isset($opts['account']) ? (int) $opts['account'] : null;
$dryRun          = isset($opts['dry-run']);
$verbose         = isset($opts['verbose']);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function logWorker(string $msg, bool $verbose, string $level = 'INFO'): void
{
    $ts  = date('Y-m-d H:i:s');
    $out = "[$ts] [$level] $msg\n";

    if ($level !== 'DEBUG' || $verbose) {
        echo $out;
    }

    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/awa-sellers-scan-' . date('Y-m-d') . '.log';
    file_put_contents($logFile, $out, FILE_APPEND | LOCK_EX);
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
logWorker('=== AWA Sellers Scan Worker iniciado ===', $verbose);

if ($dryRun) {
    logWorker('MODO DRY-RUN: nenhum dado será salvo', $verbose, 'WARN');
}

try {
    $db = Database::getInstance();

    // Obter contas ativas
    if ($targetAccountId !== null) {
        $stmt = $db->prepare("SELECT id FROM ml_accounts WHERE id = :id AND status = 'active' LIMIT 1");
        $stmt->execute(['id' => $targetAccountId]);
    } else {
        $stmt = $db->query("SELECT id FROM ml_accounts WHERE status = 'active' ORDER BY id");
    }

    $accounts = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($accounts)) {
        logWorker('Nenhuma conta ativa encontrada', $verbose, 'WARN');
        exit(0);
    }

    logWorker('Contas a processar: ' . implode(', ', $accounts), $verbose);

    $globalNewSellers  = 0;
    $globalTotalScans  = 0;
    $globalErrors      = 0;

    foreach ($accounts as $accountId) {
        $accountId = (int) $accountId;
        logWorker("Processando conta #{$accountId} ...", $verbose);

        try {
            if ($dryRun) {
                logWorker("  [dry-run] Conta #{$accountId}: pulando execução real", $verbose, 'DEBUG');
                continue;
            }

            $registry  = new AwaSellerRegistryService($accountId);
            $discovery = new AwaSellerDiscoveryService($accountId, null, $registry);

            // Obtém snapshot dos seller_ids conhecidos antes do scan
            $knownIds = $registry->getKnownSellerIds();

            // Executa o scan
            $result = $discovery->runScan([]);
            $globalTotalScans++;

            $sellersFound = (int) ($result['sellers_found'] ?? 0);
            $itemsFound   = (int) ($result['items_found'] ?? 0);

            logWorker(
                "  Conta #{$accountId}: scan={$result['scan_id']} sellers={$sellersFound} items={$itemsFound}",
                $verbose
            );

            // Detectar vendedores novos e disparar alertas
            $currentIds = $registry->getKnownSellerIds();
            $newIds     = array_diff($currentIds, $knownIds);

            if (!empty($newIds)) {
                $globalNewSellers += count($newIds);
                logWorker(
                    '  ' . count($newIds) . ' novo(s) vendedor(es) detectado(s)',
                    $verbose,
                    'WARN'
                );

                $alertService = new AlertService();
                $alertService->createAlert($accountId, 'awa_new_seller', [
                    'scan_id'            => $result['scan_id'],
                    'new_seller_count'   => count($newIds),
                    'new_seller_ids'     => array_values($newIds),
                    'total_sellers'      => $sellersFound,
                ]);
            }
        } catch (\Throwable $e) {
            $globalErrors++;
            logWorker("  ERRO conta #{$accountId}: {$e->getMessage()}", $verbose, 'ERROR');
        }
    }

    logWorker(
        "=== Concluído: scans={$globalTotalScans} novos_vendedores={$globalNewSellers} erros={$globalErrors} ===",
        $verbose
    );
    exit($globalErrors > 0 ? 1 : 0);
} catch (\Throwable $e) {
    logWorker('ERRO FATAL: ' . $e->getMessage(), $verbose, 'ERROR');
    exit(2);
}
