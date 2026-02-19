#!/usr/bin/env php
<?php
/**
 * Auto Pricing Optimizer Worker
 * 
 * Executa otimização automática de preços baseada em concorrência
 * 
 * Uso:
 *   php bin/auto-pricing-optimizer.php [--account=ID] [--dry-run] [--verbose]
 * 
 * Exemplos:
 *   php bin/auto-pricing-optimizer.php                    # Todas as contas
 *   php bin/auto-pricing-optimizer.php --account=1        # Conta específica
 *   php bin/auto-pricing-optimizer.php --dry-run          # Simular sem aplicar
 *   php bin/auto-pricing-optimizer.php --verbose          # Output detalhado
 * 
 * Cron recomendado: Executar a cada 6 horas
 *   0 0,6,12,18 * * * php /path/to/bin/auto-pricing-optimizer.php >> storage/logs/auto-optimizer.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Database;
use App\Services\AutoPricingOptimizerService;

// Parse argumentos
$options = getopt('', ['account:', 'dry-run', 'verbose', 'help']);

if (isset($options['help'])) {
    echo "Auto Pricing Optimizer Worker\n";
    echo "=============================\n\n";
    echo "Uso: php bin/auto-pricing-optimizer.php [opções]\n\n";
    echo "Opções:\n";
    echo "  --account=ID    Processar apenas conta específica\n";
    echo "  --dry-run       Simular sem aplicar mudanças\n";
    echo "  --verbose       Output detalhado\n";
    echo "  --help          Exibir esta ajuda\n\n";
    exit(0);
}

$accountId = isset($options['account']) ? (int) $options['account'] : null;
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

// Funções auxiliares
function logMsg(string $message, bool $verbose, string $level = 'INFO'): void
{
    $timestamp = date('Y-m-d H:i:s');
    $output = "[$timestamp] [$level] $message\n";
    
    if ($level === 'ERROR' || $verbose) {
        echo $output;
    }
    
    // Também gravar em arquivo de log
    $logFile = __DIR__ . '/../storage/logs/auto-optimizer-' . date('Y-m-d') . '.log';
    file_put_contents($logFile, $output, FILE_APPEND);
}

function formatCurrency(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

// Início
logMsg("=== Auto Pricing Optimizer Iniciado ===", $verbose);
if ($dryRun) {
    logMsg("MODO DRY-RUN: Nenhuma mudança será aplicada", $verbose, 'WARN');
}

try {
    $db = Database::getInstance();
    
    // Obter contas para processar
    if ($accountId) {
        $stmt = $db->prepare("SELECT id, name FROM ml_accounts WHERE id = :id AND status = 'active'");
        $stmt->execute(['id' => $accountId]);
    } else {
        $stmt = $db->query("SELECT id, name FROM ml_accounts WHERE status = 'active'");
    }
    
    $accounts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    if (empty($accounts)) {
        logMsg("Nenhuma conta ativa encontrada", $verbose, 'WARN');
        exit(0);
    }
    
    logMsg("Processando " . count($accounts) . " conta(s)", $verbose);
    
    $totalStats = [
        'accounts_processed' => 0,
        'items_analyzed' => 0,
        'prices_applied' => 0,
        'suggestions_created' => 0,
        'errors' => 0
    ];
    
    foreach ($accounts as $account) {
        $accId = (int) $account['id'];
        $accName = $account['name'];
        
        logMsg("--- Processando conta: $accName (ID: $accId) ---", $verbose);
        
        try {
            $optimizer = new AutoPricingOptimizerService($accId);
            
            // Verificar se otimizador está ativo para esta conta
            $config = $optimizer->getConfig();
            
            if (!($config['enabled'] ?? false)) {
                logMsg("Otimizador desativado para esta conta, pulando...", $verbose);
                continue;
            }
            
            // Se dry-run, forçar modo sugestão
            if ($dryRun && ($config['mode'] ?? 'suggest') === 'auto_apply') {
                logMsg("Dry-run: Modo alterado para 'suggest'", $verbose);
                $config['mode'] = 'suggest';
                // Não salvar, apenas usar temporariamente
            }
            
            // Executar otimização
            logMsg("Iniciando análise de preços...", $verbose);
            $result = $optimizer->runOptimization();
            
            if ($result['success']) {
                $itemsAnalyzed = $result['items_analyzed'] ?? 0;
                $results = $result['results'] ?? [];
                
                $applied = array_filter($results, fn($r) => ($r['status'] ?? '') === 'applied');
                $suggested = array_filter($results, fn($r) => ($r['status'] ?? '') === 'suggested');
                $errors = array_filter($results, fn($r) => ($r['status'] ?? '') === 'error');
                
                $totalStats['accounts_processed']++;
                $totalStats['items_analyzed'] += $itemsAnalyzed;
                $totalStats['prices_applied'] += count($applied);
                $totalStats['suggestions_created'] += count($suggested);
                $totalStats['errors'] += count($errors);
                
                logMsg("Resultado: $itemsAnalyzed itens analisados", $verbose);
                logMsg("  - Preços aplicados: " . count($applied), $verbose);
                logMsg("  - Sugestões: " . count($suggested), $verbose);
                logMsg("  - Erros: " . count($errors), $verbose);
                
                // Log detalhado
                if ($verbose) {
                    foreach ($applied as $item) {
                        $oldPrice = formatCurrency($item['current_price'] ?? 0);
                        $newPrice = formatCurrency($item['new_price'] ?? 0);
                        logMsg("  [APLICADO] {$item['item_id']}: $oldPrice → $newPrice", $verbose);
                    }
                    
                    foreach ($suggested as $item) {
                        $oldPrice = formatCurrency($item['current_price'] ?? 0);
                        $suggestedPrice = formatCurrency($item['suggested_price'] ?? 0);
                        logMsg("  [SUGESTÃO] {$item['item_id']}: $oldPrice → $suggestedPrice ({$item['reason']})", $verbose);
                    }
                    
                    foreach ($errors as $item) {
                        logMsg("  [ERRO] {$item['item_id']}: {$item['error']}", $verbose, 'ERROR');
                    }
                }
            } else {
                logMsg("Erro na otimização: " . ($result['message'] ?? 'Erro desconhecido'), $verbose, 'ERROR');
                $totalStats['errors']++;
            }
            
        } catch (\Exception $e) {
            logMsg("Exceção na conta $accName: " . $e->getMessage(), $verbose, 'ERROR');
            $totalStats['errors']++;
        }
        
        // Pequena pausa entre contas
        usleep(500000); // 500ms
    }
    
    // Resumo final
    logMsg("", $verbose);
    logMsg("=== RESUMO FINAL ===", $verbose);
    logMsg("Contas processadas: {$totalStats['accounts_processed']}", $verbose);
    logMsg("Itens analisados: {$totalStats['items_analyzed']}", $verbose);
    logMsg("Preços aplicados: {$totalStats['prices_applied']}", $verbose);
    logMsg("Sugestões criadas: {$totalStats['suggestions_created']}", $verbose);
    logMsg("Erros: {$totalStats['errors']}", $verbose);
    logMsg("=== Auto Pricing Optimizer Finalizado ===", $verbose);
    
    // Código de saída
    exit($totalStats['errors'] > 0 ? 1 : 0);
    
} catch (\Exception $e) {
    logMsg("Erro fatal: " . $e->getMessage(), true, 'ERROR');
    logMsg("Stack trace: " . $e->getTraceAsString(), true, 'ERROR');
    exit(1);
}
