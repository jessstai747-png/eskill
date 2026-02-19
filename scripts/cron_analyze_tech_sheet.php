#!/usr/bin/env php
<?php
/**
 * Cron: Análise em lote de Ficha Técnica
 * 
 * Analisa itens sem resumo ou com análise antiga para preencher
 * a tabela tech_sheet_item_summary com as lacunas de atributos.
 * 
 * Uso: php scripts/cron_analyze_tech_sheet.php [--account=ID] [--limit=N] [--force]
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoload.php';

use Dotenv\Dotenv;
use App\Database;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
use App\Services\TechSheetService;
use App\Services\LoggingService;

// Parse argumentos
$options = getopt('', ['account::', 'limit::', 'force']);
$specificAccountId = isset($options['account']) ? (int)$options['account'] : null;
$limit = isset($options['limit']) ? (int)$options['limit'] : 50;
$force = isset($options['force']);

$logger = new LoggingService();
$db = Database::getInstance();

echo "[" . date('Y-m-d H:i:s') . "] === Iniciando análise de Ficha Técnica ===" . PHP_EOL;

try {
    // Buscar contas ativas
    if ($specificAccountId) {
        $accounts = $db->query("SELECT id, nickname as name FROM ml_accounts WHERE id = {$specificAccountId} AND status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $accounts = $db->query("SELECT id, nickname as name FROM ml_accounts WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($accounts)) {
        echo "[" . date('Y-m-d H:i:s') . "] Nenhuma conta ativa encontrada." . PHP_EOL;
        exit(0);
    }

    echo "[" . date('Y-m-d H:i:s') . "] Encontradas " . count($accounts) . " conta(s) ativa(s)" . PHP_EOL;

    $totalAnalyzed = 0;
    $totalErrors = 0;

    foreach ($accounts as $account) {
        $accountId = (int)$account['id'];
        $accountName = $account['name'];

        echo "[" . date('Y-m-d H:i:s') . "] --------------------------------------------------" . PHP_EOL;
        echo "[" . date('Y-m-d H:i:s') . "] Processando conta: {$accountName} (ID: {$accountId})" . PHP_EOL;

        // Buscar itens sem análise ou com análise antiga (> 24h)
        $freshnessHours = $force ? 0 : 24;
        $limitSql = max(1, min(500, (int)$limit));
        $sql = "
            SELECT i.ml_item_id, i.title, i.category_id
            FROM items i
            LEFT JOIN tech_sheet_item_summary s 
                ON s.account_id = i.account_id AND s.item_id = i.ml_item_id
            WHERE i.account_id = :account_id
              AND i.status = 'active'
              AND (
                  s.item_id IS NULL 
                  OR s.total_available = 0
                  OR s.last_analyzed_at < DATE_SUB(NOW(), INTERVAL :hours HOUR)
              )
            ORDER BY s.item_id IS NULL DESC, s.last_analyzed_at ASC
            LIMIT {$limitSql}
        ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $stmt->bindValue(':hours', $freshnessHours, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            echo "[" . date('Y-m-d H:i:s') . "] ✓ Todos os itens já estão analisados." . PHP_EOL;
            continue;
        }

        echo "[" . date('Y-m-d H:i:s') . "] Encontrados " . count($items) . " itens para análise" . PHP_EOL;

        // Inicializar service para esta conta
        $techSheetService = new TechSheetService($accountId);
        $analyzed = 0;
        $errors = 0;

        foreach ($items as $item) {
            $itemId = $item['ml_item_id'];
            $title = mb_substr($item['title'], 0, 40);

            try {
                // Chamar getItem que internamente faz analyzeGaps + getOrComputeSummary
                $result = $techSheetService->getItem($itemId);

                if (!($result['success'] ?? false)) {
                    echo "  ⚠ {$itemId}: " . ($result['error'] ?? 'Erro desconhecido') . PHP_EOL;
                    $errors++;
                    continue;
                }

                $summary = $result['summary'] ?? [];
                $totalGaps = ($summary['missing_required'] ?? 0) 
                           + ($summary['missing_filter'] ?? 0) 
                           + ($summary['missing_hidden'] ?? 0);

                echo "  ✓ {$itemId}: {$title}... (gaps: {$totalGaps})" . PHP_EOL;
                $analyzed++;

                // Rate limiting para não sobrecarregar a API
                usleep(200000); // 200ms entre requisições
            } catch (\Exception $e) {
                echo "  ✗ {$itemId}: Erro - " . $e->getMessage() . PHP_EOL;
                $errors++;
                $totalErrors++;
            }
        }

        $totalAnalyzed += $analyzed;
        echo "[" . date('Y-m-d H:i:s') . "] ✅ Conta {$accountName}: {$analyzed} itens analisados, {$errors} erros" . PHP_EOL;
    }

    echo "[" . date('Y-m-d H:i:s') . "] --------------------------------------------------" . PHP_EOL;
    echo "[" . date('Y-m-d H:i:s') . "] === Análise concluída ===" . PHP_EOL;
    echo "[" . date('Y-m-d H:i:s') . "] Total analisado: {$totalAnalyzed} itens" . PHP_EOL;
    if ($totalErrors > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Total de erros: {$totalErrors}" . PHP_EOL;
    }

} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ ERRO FATAL: " . $e->getMessage() . PHP_EOL;
    $logger->error('TECH_SHEET_ANALYZE_ERROR', 'Erro na análise de ficha técnica', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}
