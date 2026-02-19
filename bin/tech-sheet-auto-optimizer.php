#!/usr/bin/env php
<?php
/**
 * CLI Worker para Auto-Optimizer de Ficha Técnica
 * 
 * Executa otimização automática de sugestões com alta confiança
 * 
 * Uso:
 *   php bin/tech-sheet-auto-optimizer.php [options]
 * 
 * Opções:
 *   --account=ID    ID da conta (obrigatório)
 *   --limit=N       Processar N itens (default: 50)
 *   --dry-run       Simular sem aplicar
 *   --force         Forçar execução mesmo se desabilitado
 *   --help          Exibir ajuda
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\TechSheetAutoOptimizerService;

// Parse argumentos
$options = getopt('', ['account:', 'limit:', 'dry-run', 'force', 'help']);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

if (!isset($options['account'])) {
    error("❌ Erro: --account=ID é obrigatório\n");
    showHelp();
    exit(1);
}

$accountId = (int) $options['account'];
$limit = (int) ($options['limit'] ?? 50);
$dryRun = isset($options['dry-run']);
$force = isset($options['force']);

// Banner
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║       Tech Sheet Auto-Optimizer                          ║\n";
echo "║       Sistema de Auto-Aplicação de Sugestões             ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

info("Iniciando auto-optimizer...");
info("Conta: $accountId");
info("Limite: $limit itens");
info("Modo: " . ($dryRun ? 'Simulação (DRY-RUN)' : 'Produção'));
echo "\n";

try {
    $optimizer = new TechSheetAutoOptimizerService($accountId);
    
    // Verificar estatísticas antes
    info("Verificando itens elegíveis...");
    $stats = $optimizer->getStats();
    
    echo "📊 Estatísticas:\n";
    echo "   • Auto-optimize: " . ($stats['enabled'] ? '✅ Habilitado' : '❌ Desabilitado') . "\n";
    echo "   • Confiança mínima: {$stats['min_confidence']}%\n";
    echo "   • Itens elegíveis: {$stats['eligible_items']}\n";
    echo "   • Sugestões elegíveis: {$stats['total_eligible_suggestions']}\n";
    
    if (!empty($stats['by_source'])) {
        echo "\n   Por fonte:\n";
        foreach ($stats['by_source'] as $source) {
            echo "   • {$source['source']}: {$source['count']} sugestões ";
            echo "(confiança média: " . round($source['avg_confidence'], 1) . "%)\n";
        }
    }
    
    echo "\n";
    
    if ($stats['eligible_items'] == 0) {
        success("✅ Nenhum item elegível para processar");
        exit(0);
    }
    
    if (!$stats['enabled'] && !$force) {
        error("❌ Auto-optimize desabilitado. Use --force para forçar.");
        exit(1);
    }
    
    // Executar otimização
    info("Executando otimização...");
    $startTime = microtime(true);
    
    $result = $optimizer->autoOptimize([
        'dry_run' => $dryRun,
        'limit' => $limit,
        'force' => $force,
    ]);
    
    $duration = round(microtime(true) - $startTime, 2);
    
    echo "\n";
    echo "╔══════════════════════════════════════════════════════════╗\n";
    echo "║  RESULTADOS                                              ║\n";
    echo "╚══════════════════════════════════════════════════════════╝\n\n";
    
    $results = $result['results'];
    
    echo "📈 Resumo:\n";
    echo "   • Total processado: {$results['processed']}\n";
    echo "   • Aprovados: {$results['approved']}\n";
    echo "   • Aplicados: {$results['applied']}\n";
    echo "   • Ignorados: {$results['skipped']}\n";
    echo "   • Erros: {$results['errors']}\n";
    echo "   • Tempo: {$duration}s\n";
    
    if (!empty($results['items'])) {
        echo "\n📋 Detalhes por item:\n";
        foreach ($results['items'] as $item) {
            $icon = match($item['status']) {
                'approved' => '✅',
                'applied' => '✅',
                'skipped' => '⏭️',
                'error' => '❌',
                'dry_run' => '🔍',
                default => '•',
            };
            
            echo "   $icon {$item['item_id']}: {$item['status']}";
            
            if (isset($item['approved_count'])) {
                echo " ({$item['approved_count']} atributos)";
            } elseif (isset($item['suggestions_count'])) {
                echo " ({$item['suggestions_count']} sugestões)";
            } elseif (isset($item['error'])) {
                echo " - {$item['error']}";
            }
            
            echo "\n";
        }
    }
    
    echo "\n";
    
    if ($dryRun) {
        info("🔍 Simulação concluída. Nenhuma alteração foi aplicada.");
    } else {
        success("✅ Auto-otimização concluída com sucesso!");
    }
    
    exit(0);
    
} catch (Exception $e) {
    echo "\n";
    error("❌ ERRO: " . $e->getMessage());
    exit(1);
}

// Funções auxiliares
function showHelp(): void {
    echo <<<HELP
    
Tech Sheet Auto-Optimizer - Sistema de Auto-Aplicação de Sugestões

USO:
    php bin/tech-sheet-auto-optimizer.php [options]

OPÇÕES:
    --account=ID    ID da conta ML (obrigatório)
    --limit=N       Processar no máximo N itens (default: 50, max: 50)
    --dry-run       Simular sem aplicar alterações
    --force         Forçar execução mesmo se desabilitado no config
    --help          Exibir esta ajuda

EXEMPLOS:
    # Simular otimização
    php bin/tech-sheet-auto-optimizer.php --account=123 --dry-run
    
    # Executar otimização (5 itens)
    php bin/tech-sheet-auto-optimizer.php --account=123 --limit=5
    
    # Forçar execução
    php bin/tech-sheet-auto-optimizer.php --account=123 --force

FUNCIONAMENTO:
    Este script identifica e auto-aprova sugestões com:
    • Confiança >= 90% (configurável)
    • Fonte segura (title, benchmark)
    • Item ativo
    • Categoria válida

HELP;
}

function info(string $msg): void {
    echo "\033[34mℹ\033[0m  $msg\n";
}

function success(string $msg): void {
    echo "\033[32m✓\033[0m  $msg\n";
}

function error(string $msg): void {
    echo "\033[31m✗\033[0m  $msg\n";
}
