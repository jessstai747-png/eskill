#!/usr/bin/env php
<?php

/**
 * Rules Engine Worker
 *
 * Processa regras de precificação automáticas
 *
 * Usage:
 *   php bin/rules-engine-worker.php [--once] [--account=ID] [--verbose]
 *
 * Options:
 *   --once      Executa uma vez e sai
 *   --account   Processa apenas uma conta específica
 *   --verbose   Mostra logs detalhados
 */

require_once __DIR__ . '/../autoload.php';

use App\Database;
use App\Services\PriceRulesEngineService;
use App\Services\PriceNotificationService;

// Parse arguments
$options = getopt('', ['once', 'account:', 'verbose', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Rules Engine Worker - Processa regras de precificação automáticas

Usage:
  php bin/rules-engine-worker.php [options]

Options:
  --once       Executa uma vez e sai
  --account=ID Processa apenas uma conta específica
  --verbose    Mostra logs detalhados
  --help       Mostra esta ajuda

HELP;
    exit(0);
}

$runOnce = isset($options['once']);
$specificAccount = $options['account'] ?? null;
$verbose = isset($options['verbose']);

// Logger
function logMsg(string $message, bool $verbose = false): void
{
    global $verbose;
    if ($verbose || !$verbose) {
        echo "[" . date('Y-m-d H:i:s') . "] {$message}\n";
    }
}

logMsg("🚀 Rules Engine Worker iniciado");
logMsg("Mode: " . ($runOnce ? "Single run" : "Continuous"));

$db = Database::getInstance();

// Loop principal
do {
    try {
        // Obter contas ativas
        $where = "WHERE status = 'active'";
        $params = [];

        if ($specificAccount) {
            $where .= ' AND id = :account_id';
            $params['account_id'] = $specificAccount;
        }

        $stmt = $db->prepare("
            SELECT id, nickname FROM ml_accounts
            {$where}
            ORDER BY id
        ");
        $stmt->execute($params);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($accounts as $account) {
            $accountId = $account['id'];
            $accountName = $account['nickname'] ?? "Account #{$accountId}";

            if ($verbose) {
                logMsg("📊 Processando conta: {$accountName}");
            }

            try {
                // Verificar regras ativas
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count
                    FROM pricing_rules
                    WHERE account_id = :account_id AND is_active = 1
                ");
                $stmt->execute(['account_id' => $accountId]);
                $ruleCount = $stmt->fetchColumn();

                if ($ruleCount == 0) {
                    if ($verbose) {
                        logMsg("  ⏭️  Nenhuma regra ativa");
                    }
                    continue;
                }

                logMsg("  🔧 Executando {$ruleCount} regras...");

                // Executar regras
                $service = new PriceRulesEngineService($accountId);
                $result = $service->executeAllRules(true, 50); // Aplica em lotes de 50

                if ($result['success']) {
                    $applied = $result['applied'] ?? 0;
                    $skipped = $result['skipped'] ?? 0;

                    logMsg("  ✅ Concluído: {$applied} aplicados, {$skipped} ignorados");

                    // Enviar notificação se houver mudanças
                    if ($applied > 0) {
                        try {
                            $notificationService = new PriceNotificationService($accountId);
                            $notificationService->notify(
                                PriceNotificationService::EVENT_RULE_EXECUTED,
                                [
                                    'applied_count' => $applied,
                                    'skipped_count' => $skipped,
                                    'timestamp' => date('Y-m-d H:i:s')
                                ],
                                PriceNotificationService::SEVERITY_INFO
                            );
                        } catch (\Throwable $e) {
                            // Notificação não é crítica
                            if ($verbose) {
                                logMsg("  ⚠️  Falha na notificação: " . $e->getMessage());
                            }
                        }
                    }
                } else {
                    logMsg("  ❌ Erro: " . ($result['message'] ?? 'Desconhecido'));
                }
            } catch (\Throwable $e) {
                logMsg("  ❌ Erro na conta {$accountId}: " . $e->getMessage());
            }
        }

        if (!$runOnce) {
            // Aguardar antes do próximo ciclo (5 minutos)
            $sleepTime = 300;
            logMsg("💤 Aguardando {$sleepTime}s até próximo ciclo...");
            sleep($sleepTime);
        }
    } catch (\Throwable $e) {
        logMsg("❌ Erro crítico: " . $e->getMessage());

        if (!$runOnce) {
            sleep(60); // Aguardar 1 minuto em caso de erro
        }
    }
} while (!$runOnce);

logMsg("🏁 Rules Engine Worker finalizado");
