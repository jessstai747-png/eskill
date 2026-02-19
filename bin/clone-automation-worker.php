#!/usr/bin/env php
<?php

/**
 * Clone Automation Worker
 * 
 * Executa regras de auto-clonagem programadas
 * 
 * Uso:
 *   php clone-automation-worker.php
 * 
 * Cron recomendado:
 *   * * * * * /usr/bin/php /path/to/clone-automation-worker.php >> /var/log/clone-automation.log 2>&1
 */

require_once __DIR__ . '/../autoload.php';

use App\Services\CloneAutomationService;
use App\Services\CloneNotificationService;
use App\Database;

class CloneAutomationWorker
{
    private $db;
    private $startTime;
    private bool $verbose;

    public function __construct(bool $verbose = false)
    {
        $this->db = Database::getInstance();
        $this->startTime = microtime(true);
        $this->verbose = $verbose;
    }

    public function run(): void
    {
        $this->log("=== Clone Automation Worker Iniciado ===");

        try {
            // Buscar contas ativas
            $accounts = $this->getActiveAccounts();
            $this->log("Encontradas " . count($accounts) . " contas ativas");

            $totalExecuted = 0;
            $totalCloned = 0;

            foreach ($accounts as $account) {
                $result = $this->processAccount($account['id']);
                $totalExecuted += $result['executed'];
                $totalCloned += $result['cloned'];
            }

            $elapsed = round(microtime(true) - $this->startTime, 2);
            $this->log("=== Worker Finalizado ===");
            $this->log("Regras executadas: {$totalExecuted}");
            $this->log("Itens clonados: {$totalCloned}");
            $this->log("Tempo: {$elapsed}s");

        } catch (Exception $e) {
            $this->log("ERRO: " . $e->getMessage(), 'error');
        }
    }

    private function processAccount(int $accountId): array
    {
        $result = ['executed' => 0, 'cloned' => 0];

        try {
            $automationService = new CloneAutomationService($accountId);
            $rules = $automationService->getRulesDueForExecution();

            if (empty($rules)) {
                return $result;
            }

            $this->log("Conta #{$accountId}: " . count($rules) . " regra(s) para executar");

            // Tentar carregar notificador (pode não estar configurado)
            $notifier = null;
            try {
                $notifier = new CloneNotificationService($accountId);
            } catch (Exception $e) {
                // Notificações não configuradas
            }

            foreach ($rules as $rule) {
                try {
                    $this->log("  Executando regra: {$rule['name']} (ID: {$rule['id']})");

                    // Executar regra
                    $execResult = $automationService->executeRule($rule['id']);

                    // Marcar como executada
                    $automationService->markAsExecuted($rule['id']);

                    $cloned = $execResult['items_cloned'] ?? 0;
                    $failed = $execResult['items_failed'] ?? 0;

                    $this->log("    Clonados: {$cloned}, Falhas: {$failed}");

                    $result['executed']++;
                    $result['cloned'] += $cloned;

                    // Notificar se configurado
                    if ($notifier && $cloned > 0) {
                        try {
                            $notifier->notify(
                                'automation_executed',
                                "Automação executada: {$rule['name']}",
                                [
                                    'rule_name' => $rule['name'],
                                    'items_cloned' => $cloned,
                                    'items_failed' => $failed,
                                ],
                                $failed > 0 ? 'warning' : 'info'
                            );
                        } catch (Exception $e) {
                            // Ignorar erro de notificação
                        }
                    }

                } catch (Exception $e) {
                    $this->log("    ERRO na regra {$rule['id']}: " . $e->getMessage(), 'error');

                    // Notificar erro
                    if ($notifier) {
                        try {
                            $notifier->notifyAlertCritical(
                                "Erro na automação: {$rule['name']}",
                                $e->getMessage()
                            );
                        } catch (Exception $ne) {
                            // Ignorar
                        }
                    }
                }
            }

        } catch (Exception $e) {
            $this->log("ERRO na conta #{$accountId}: " . $e->getMessage(), 'error');
        }

        return $result;
    }

    private function getActiveAccounts(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT account_id as id 
            FROM clone_automation_rules 
            WHERE status = 'active'
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function log(string $message, string $level = 'info'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $prefix = strtoupper($level);

        if ($this->verbose || $level === 'error') {
            echo "[{$timestamp}] [{$prefix}] {$message}\n";
        }

        // Log em arquivo
        $logDir = __DIR__ . '/../storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/clone-automation-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, "[{$timestamp}] [{$prefix}] {$message}\n", FILE_APPEND);
    }
}

// Executar worker
$verbose = in_array('-v', $argv) || in_array('--verbose', $argv);
$worker = new CloneAutomationWorker($verbose);
$worker->run();
