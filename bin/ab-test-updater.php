#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * 🧪 A/B TEST UPDATER - Atualizador de Testes A/B
 * 
 * Rotaciona variantes e coleta métricas dos testes A/B ativos
 * Deve ser executado diariamente via CRON
 * 
 * Uso:
 *   php bin/ab-test-updater.php [--account=ID] [--dry-run]
 * 
 * Opções:
 *   --account=ID  Processa apenas uma conta específica
 *   --dry-run     Não aplica mudanças, apenas mostra o que faria
 *   --verbose     Modo verbose
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Services\AI\SEO\ABTester;
use App\Database;

// Parse argumentos
$accountId = null;
$dryRun = in_array('--dry-run', $argv);
$verbose = in_array('--verbose', $argv);

foreach ($argv as $arg) {
    if (strpos($arg, '--account=') === 0) {
        $accountId = (int)str_replace('--account=', '', $arg);
    }
}

echo "\n🧪 SEO KILLER - Atualizador de Testes A/B\n";
echo str_repeat("=", 60) . "\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n";
if ($dryRun) {
    echo "⚠️  Modo DRY RUN (sem aplicar mudanças)\n";
}
echo str_repeat("=", 60) . "\n\n";

try {
    $db = Database::getInstance();
    
    // Buscar contas com testes ativos
    $query = "SELECT DISTINCT account_id FROM seo_ab_tests WHERE status = 'running'";
    if ($accountId) {
        $query .= " AND account_id = {$accountId}";
    }
    
    $stmt = $db->query($query);
    $accounts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($accounts)) {
        echo "ℹ️  Nenhum teste A/B ativo encontrado\n\n";
        exit(0);
    }
    
    echo "📊 Encontradas " . count($accounts) . " conta(s) com testes ativos\n\n";
    
    $totalUpdated = 0;
    $totalFailed = 0;
    
    foreach ($accounts as $accId) {
        echo "🔄 Processando conta #{$accId}...\n";
        
        try {
            $tester = new ABTester((int)$accId);
            
            if ($dryRun) {
                // Listar testes apenas
                $tests = $tester->listTests();
                $activeTests = array_filter($tests, fn($t) => $t['status'] === 'running');
                
                echo "  Testes ativos: " . count($activeTests) . "\n";
                foreach ($activeTests as $test) {
                    echo "    • Teste #{$test['id']} - Item {$test['item_id']} ({$test['type']})\n";
                }
            } else {
                // Atualizar testes (rotacionar variantes + coletar métricas)
                $result = $tester->updateTests();
                $updated = $result['updated'] ?? 0;
                
                echo "  ✅ {$updated} teste(s) atualizado(s)\n";
                $totalUpdated += $updated;
            }
            
        } catch (\Exception $e) {
            echo "  ❌ Erro: " . $e->getMessage() . "\n";
            $totalFailed++;
            
            if ($verbose) {
                echo "  Stack trace:\n";
                foreach (explode("\n", $e->getTraceAsString()) as $line) {
                    echo "    {$line}\n";
                }
            }
        }
        
        echo "\n";
    }
    
    // Resumo
    echo str_repeat("=", 60) . "\n";
    echo "📊 RESUMO\n";
    echo str_repeat("=", 60) . "\n";
    echo "Contas processadas: " . count($accounts) . "\n";
    echo "Testes atualizados: {$totalUpdated}\n";
    echo "Erros: {$totalFailed}\n";
    echo str_repeat("=", 60) . "\n\n";
    
    if ($dryRun) {
        echo "💡 Execute sem --dry-run para aplicar as mudanças\n\n";
    }
    
    exit(0);
    
} catch (\Exception $e) {
    echo "\n❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}
