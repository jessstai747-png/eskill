#!/usr/bin/env php
<?php
/**
 * A/B Test Price Updater Worker
 *
 * Atualiza preços de testes A/B em execução e coleta métricas
 *
 * Uso:
 *   php bin/ab-test-worker.php [--account=ID] [--rotate] [--collect-metrics] [--verbose]
 *
 * Exemplos:
 *   php bin/ab-test-worker.php --rotate              # Rotacionar preços
 *   php bin/ab-test-worker.php --collect-metrics     # Coletar métricas de vendas
 *   php bin/ab-test-worker.php --rotate --collect-metrics --verbose
 *
 * Cron recomendado (a cada 4 horas para rotação, diário para métricas):
 *   0 0,4,8,12,16,20 * * * php /path/to/bin/ab-test-worker.php --rotate
 *   0 23 * * * php /path/to/bin/ab-test-worker.php --collect-metrics
 */

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Database;
use App\Services\PriceAbTestService;
use App\Services\MercadoLivreClient;

// Parse argumentos
$options = getopt('', ['account:', 'rotate', 'collect-metrics', 'verbose', 'help']);

if (isset($options['help'])) {
    echo "A/B Test Price Updater Worker\n";
    echo "==============================\n\n";
    echo "Uso: php bin/ab-test-updater.php [opções]\n\n";
    echo "Opções:\n";
    echo "  --account=ID       Processar apenas conta específica\n";
    echo "  --rotate           Rotacionar preços dos testes ativos\n";
    echo "  --collect-metrics  Coletar métricas de vendas do dia\n";
    echo "  --verbose          Output detalhado\n";
    echo "  --help             Exibir esta ajuda\n\n";
    exit(0);
}

$accountId = isset($options['account']) ? (int) $options['account'] : null;
$doRotate = isset($options['rotate']);
$doCollectMetrics = isset($options['collect-metrics']);
$verbose = isset($options['verbose']);

if (!$doRotate && !$doCollectMetrics) {
    echo "Especifique --rotate e/ou --collect-metrics\n";
    echo "Use --help para ver as opções disponíveis\n";
    exit(1);
}

// Funções auxiliares
function logMsg(string $message, bool $verbose, string $level = 'INFO'): void
{
    $timestamp = date('Y-m-d H:i:s');
    $output = "[$timestamp] [$level] $message\n";

    if ($level === 'ERROR' || $verbose) {
        echo $output;
    }

    $logFile = __DIR__ . '/../storage/logs/ab-test-' . date('Y-m-d') . '.log';
    file_put_contents($logFile, $output, FILE_APPEND);
}

// Início
logMsg("=== A/B Test Worker Iniciado ===", $verbose);

try {
    $db = Database::getInstance();

    // Obter contas para processar
    if ($accountId) {
        $stmt = $db->prepare("SELECT id, nickname FROM ml_accounts WHERE id = :id AND status = 'active'");
        $stmt->execute(['id' => $accountId]);
    } else {
        $stmt = $db->query("SELECT id, nickname FROM ml_accounts WHERE status = 'active'");
    }

    $accounts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($accounts)) {
        logMsg("Nenhuma conta ativa encontrada", $verbose, 'WARN');
        exit(0);
    }

    logMsg("Processando " . count($accounts) . " conta(s)", $verbose);

    foreach ($accounts as $account) {
        $accId = (int) $account['id'];
        $accName = $account['nickname'];

        logMsg("--- Processando conta: $accName (ID: $accId) ---", $verbose);

        try {
            $abService = new PriceAbTestService($accId);

            // Obter testes em execução
            $runningTests = $abService->listTests(['status' => 'running']);

            if (empty($runningTests)) {
                logMsg("Nenhum teste A/B ativo nesta conta", $verbose);
                continue;
            }

            logMsg(count($runningTests) . " teste(s) A/B em execução", $verbose);

            foreach ($runningTests as $test) {
                $testId = (int) $test['id'];
                $testName = $test['name'];
                $itemId = $test['item_id'];

                logMsg("Processando teste: $testName (Item: $itemId)", $verbose);

                // Rotacionar preços
                if ($doRotate) {
                    try {
                        $result = $abService->rotatePrice($testId);
                        if ($result['success']) {
                            logMsg("  Preço rotacionado para {$result['variant']}: R$ {$result['price']}", $verbose);
                        } else {
                            logMsg("  Erro ao rotacionar: " . ($result['message'] ?? 'Erro desconhecido'), $verbose, 'WARN');
                        }
                    } catch (\Exception $e) {
                        logMsg("  Exceção ao rotacionar: " . $e->getMessage(), $verbose, 'ERROR');
                    }
                }

                // Coletar métricas
                if ($doCollectMetrics) {
                    try {
                        $mlClient = new MercadoLivreClient($accId);

                        // Obter visitas do item
                        $visits = $mlClient->getItemVisits($itemId, 1);

                        // Obter vendas do item
                        $orders = $mlClient->getOrders([
                            'item' => $itemId,
                            'order.date_created.from' => date('Y-m-d', strtotime('-1 day')) . 'T00:00:00.000-00:00',
                            'order.date_created.to' => date('Y-m-d') . 'T00:00:00.000-00:00'
                        ]);

                        // Determinar qual variante estava ativa (simplificação: baseado no preço atual)
                        $item = $mlClient->getItem($itemId);
                        $currentPrice = (float) ($item['price'] ?? 0);

                        // Determinar variante pelo preço mais próximo
                        $diffControl = abs($currentPrice - (float) $test['control_price']);
                        $diffVariant = abs($currentPrice - (float) $test['variant_price']);
                        $variant = $diffControl < $diffVariant ? 'control' : 'variant';

                        // Calcular métricas
                        $totalVisits = (int) ($visits['total_visits'] ?? 0);
                        $totalOrders = count($orders['results'] ?? []);
                        $totalRevenue = 0;
                        $totalUnits = 0;

                        foreach (($orders['results'] ?? []) as $order) {
                            foreach (($order['order_items'] ?? []) as $orderItem) {
                                if ($orderItem['item']['id'] === $itemId) {
                                    $totalUnits += (int) ($orderItem['quantity'] ?? 0);
                                    $totalRevenue += (float) ($orderItem['unit_price'] ?? 0) * (int) ($orderItem['quantity'] ?? 0);
                                }
                            }
                        }

                        // Registrar resultados
                        $abService->recordResults($testId, $variant, [
                            'date' => date('Y-m-d', strtotime('-1 day')),
                            'visits' => $totalVisits,
                            'conversions' => $totalOrders,
                            'units_sold' => $totalUnits,
                            'revenue' => $totalRevenue
                        ]);

                        logMsg("  Métricas registradas ($variant): $totalVisits visitas, $totalOrders conversões, R$ " . number_format($totalRevenue, 2), $verbose);
                    } catch (\Exception $e) {
                        logMsg("  Erro ao coletar métricas: " . $e->getMessage(), $verbose, 'ERROR');
                    }
                }
            }
        } catch (\Exception $e) {
            logMsg("Exceção na conta $accName: " . $e->getMessage(), $verbose, 'ERROR');
        }

        usleep(500000); // 500ms entre contas
    }

    logMsg("=== A/B Test Worker Finalizado ===", $verbose);
    exit(0);
} catch (\Exception $e) {
    logMsg("Erro fatal: " . $e->getMessage(), true, 'ERROR');
    exit(1);
}
