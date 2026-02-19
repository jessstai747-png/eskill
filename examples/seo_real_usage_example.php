<?php
/**
 * Exemplo de Uso Real do Sistema SEO
 * 
 * Este arquivo demonstra como usar o sistema de otimização SEO
 * em um cenário real de produção.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\SEO\SEOStrategiesEngine;

// ============================================================================
// EXEMPLO 1: Otimização Completa de um Item
// ============================================================================

echo "=== EXEMPLO 1: Otimização Completa ===\n\n";

try {
    $engine = new SEOStrategiesEngine();
    
    // ID real de um item do Mercado Livre
    $itemId = 'MLB123456789'; // Substitua por um ID real
    
    echo "Otimizando item: {$itemId}\n";
    
    // Executa otimização completa (12 estratégias)
    $result = $engine->optimizeFull($itemId);
    
    echo "Score SEO: {$result['overall_score']}/100\n";
    echo "Estratégias executadas: " . count($result) . "\n\n";
    
    // Exibe sinônimos encontrados
    if (!empty($result['synonym_expansion'])) {
        echo "Sinônimos expandidos:\n";
        foreach ($result['synonym_expansion'] as $level => $data) {
            echo "  {$level}: " . implode(', ', $data['words'] ?? []) . "\n";
        }
        echo "\n";
    }
    
    // Exibe distribuição de keywords
    if (!empty($result['keyword_distribution'])) {
        echo "Distribuição de Keywords:\n";
        foreach ($result['keyword_distribution'] as $field => $data) {
            $keywords = $data['keywords'] ?? [];
            echo "  {$field}: " . implode(', ', $keywords) . "\n";
        }
        echo "\n";
    }
    
    // Exibe descrição otimizada
    if (!empty($result['description_building']['full_description'])) {
        echo "Descrição Otimizada:\n";
        echo substr($result['description_building']['full_description'], 0, 200) . "...\n\n";
    }
    
    // Exibe recomendações
    if (!empty($result['report']['recommendations'])) {
        echo "Recomendações:\n";
        foreach ($result['report']['recommendations'] as $rec) {
            echo "  - {$rec}\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// EXEMPLO 2: Otimização Parcial (Apenas Algumas Estratégias)
// ============================================================================

echo "=== EXEMPLO 2: Otimização Parcial ===\n\n";

try {
    $engine = new SEOStrategiesEngine();
    $itemId = 'MLB123456789';
    
    // Seleciona apenas algumas estratégias
    $strategies = ['synonyms', 'keywords', 'description'];
    
    echo "Executando estratégias: " . implode(', ', $strategies) . "\n";
    
    $result = $engine->optimizePartial($itemId, $strategies);
    
    echo "Score parcial: {$result['overall_score']}/100\n\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// EXEMPLO 3: Preview Sem Aplicar
// ============================================================================

echo "=== EXEMPLO 3: Preview de Otimização ===\n\n";

try {
    $engine = new SEOStrategiesEngine();
    $itemId = 'MLB123456789';
    
    // Gera preview sem aplicar mudanças
    $preview = $engine->previewOptimization($itemId);
    
    echo "Preview gerado com sucesso!\n";
    echo "Score previsto: {$preview['overall_score']}/100\n";
    echo "Potencial de melhoria: {$preview['report']['improvement_potential']}%\n\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// EXEMPLO 4: Aplicar Otimizações via API do ML
// ============================================================================

echo "=== EXEMPLO 4: Aplicar Otimizações ===\n\n";

try {
    $engine = new SEOStrategiesEngine();
    $itemId = 'MLB123456789';
    
    // Primeiro, gera as otimizações
    $optimizations = $engine->optimizeFull($itemId);
    
    // Depois, aplica via API do Mercado Livre
    $applyResult = $engine->applyOptimization($itemId, $optimizations);
    
    if ($applyResult['success']) {
        echo "✓ Otimizações aplicadas com sucesso!\n";
        echo "Campos atualizados: " . implode(', ', $applyResult['changes_applied']) . "\n\n";
    } else {
        echo "✗ Falha ao aplicar otimizações\n";
        echo "Mensagem: {$applyResult['message']}\n\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// EXEMPLO 5: Calcular Score de um Item
// ============================================================================

echo "=== EXEMPLO 5: Calcular Score SEO ===\n\n";

try {
    $engine = new SEOStrategiesEngine();
    $itemId = 'MLB123456789';
    
    $analysis = $engine->optimizeFull($itemId);
    $score = $engine->calculateOverallScore($analysis);
    
    echo "Score SEO do item: {$score}/100\n";
    
    if ($score < 50) {
        echo "Status: ⚠️ CRÍTICO - Necessita otimização urgente\n";
    } elseif ($score < 70) {
        echo "Status: ⚡ MÉDIO - Pode melhorar\n";
    } elseif ($score < 90) {
        echo "Status: ✓ BOM - Bem otimizado\n";
    } else {
        echo "Status: ⭐ EXCELENTE - Otimização máxima\n";
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// EXEMPLO 6: Otimização em Lote (Múltiplos Items)
// ============================================================================

echo "=== EXEMPLO 6: Otimização em Lote ===\n\n";

try {
    $engine = new SEOStrategiesEngine();
    
    // Lista de items para otimizar
    $items = ['MLB123456789', 'MLB987654321', 'MLB555555555'];
    
    $results = [];
    
    foreach ($items as $itemId) {
        echo "Processando {$itemId}... ";
        
        try {
            $result = $engine->optimizeFull($itemId);
            $results[$itemId] = [
                'success' => true,
                'score' => $result['overall_score']
            ];
            echo "✓ Score: {$result['overall_score']}\n";
        } catch (Exception $e) {
            $results[$itemId] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
            echo "✗ Erro\n";
        }
        
        // Aguarda 1 segundo entre requisições (rate limiting)
        sleep(1);
    }
    
    echo "\nResumo:\n";
    $successful = count(array_filter($results, fn($r) => $r['success']));
    echo "Sucesso: {$successful}/" . count($items) . "\n";
    
    $avgScore = array_sum(array_column(array_filter($results, fn($r) => $r['success']), 'score')) / max($successful, 1);
    echo "Score médio: " . round($avgScore) . "/100\n\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// EXEMPLO 7: Uso via API REST
// ============================================================================

echo "=== EXEMPLO 7: Uso via API REST ===\n\n";

echo "Exemplos de chamadas via cURL:\n\n";

echo "# Otimização completa\n";
echo "curl -X POST http://seu-dominio.com/api/seo/strategies/optimize/full/MLB123456789\n\n";

echo "# Otimização parcial\n";
echo "curl -X POST http://seu-dominio.com/api/seo/strategies/optimize/partial/MLB123456789 \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"strategies\": [\"synonyms\", \"keywords\"]}'\n\n";

echo "# Ver score\n";
echo "curl http://seu-dominio.com/api/seo/strategies/score/MLB123456789\n\n";

echo "# Preview\n";
echo "curl http://seu-dominio.com/api/seo/strategies/preview/MLB123456789\n\n";

echo "# Aplicar otimizações\n";
echo "curl -X POST http://seu-dominio.com/api/seo/strategies/apply/MLB123456789 \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"optimizations\": {...}}'\n\n";

echo "=== FIM DOS EXEMPLOS ===\n";
