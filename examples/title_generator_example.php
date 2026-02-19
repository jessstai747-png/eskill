<?php
/**
 * SEO Title Generator - Exemplo Completo de Uso
 * 
 * Demonstra geração, análise, otimização e variações de títulos
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\TitleGenerator\TitleGeneratorService;
use App\Services\TitleGenerator\TitleAnalyzerService;
use App\Services\TitleGenerator\TitleVariationsService;

echo "=== SEO TITLE GENERATOR - EXEMPLO COMPLETO ===\n\n";

// ========================================
// EXEMPLO 1: GERAR TÍTULOS PARA NOVO PRODUTO
// ========================================
echo "EXEMPLO 1: Gerar títulos para novo produto\n";
echo str_repeat('-', 60) . "\n";

$generator = new TitleGeneratorService(1);

$result = $generator->generateTitles([
    'category_id' => 'MLB1234',
    'brand' => 'Apple',
    'model' => 'iPhone 15 Pro Max',
    'attributes' => [
        ['id' => 'INTERNAL_MEMORY', 'value_name' => '256 GB'],
        ['id' => 'COLOR', 'value_name' => 'Titanio Natural']
    ]
], [
    'count' => 5,
    'optimize_for' => 'both',
    'min_score' => 70
]);

echo "Títulos gerados: {$result['generated_count']}\n";
echo "\nMelhor título:\n";
echo "  Título: {$result['best_title']['title']}\n";
echo "  Score: {$result['best_title']['score']}/100\n";
echo "  Comprimento: {$result['best_title']['length']} caracteres\n";
echo "\nTop 3 títulos:\n";
foreach (array_slice($result['titles'], 0, 3) as $i => $title) {
    echo "  " . ($i + 1) . ". {$title['title']} (score: {$title['score']})\n";
}
echo "\n";

// ========================================
// EXEMPLO 2: ANALISAR TÍTULO EXISTENTE
// ========================================
echo "EXEMPLO 2: Analisar título existente\n";
echo str_repeat('-', 60) . "\n";

$analyzer = new TitleAnalyzerService(1);

$titulo1 = "iPhone 15 Pro";
$analysis1 = $analyzer->analyzeTitle($titulo1, 'MLB1234');

echo "Título: $titulo1\n";
echo "Score Geral: {$analysis1['overall_score']}/100\n";
echo "Status: {$analysis1['status']}\n";
echo "Comprimento: {$analysis1['length']} caracteres\n\n";

echo "Análise Detalhada:\n";
echo "  - Comprimento: {$analysis1['length_analysis']['score']}/100 ({$analysis1['length_analysis']['status']})\n";
echo "  - Keywords: {$analysis1['keyword_analysis']['score']}/100\n";
echo "  - Clareza: {$analysis1['clarity_analysis']['score']}/100\n";
echo "  - Estrutura: {$analysis1['structure_analysis']['score']}/100\n";
echo "  - Termos Proibidos: {$analysis1['forbidden_words_analysis']['score']}/100\n";
echo "  - Competitividade: {$analysis1['competitive_analysis']['score']}/100\n\n";

if (!empty($analysis1['issues'])) {
    echo "Issues:\n";
    foreach ($analysis1['issues'] as $issue) {
        echo "  ✗ $issue\n";
    }
    echo "\n";
}

if (!empty($analysis1['suggestions'])) {
    echo "Sugestões:\n";
    foreach (array_slice($analysis1['suggestions'], 0, 3) as $suggestion) {
        echo "  → $suggestion\n";
    }
    echo "\n";
}

// ========================================
// EXEMPLO 3: OTIMIZAR TÍTULO
// ========================================
echo "EXEMPLO 3: Otimizar título ruim\n";
echo str_repeat('-', 60) . "\n";

$titulo2 = "Celular Samsung";
echo "Título Original: $titulo2\n";

$analysis2 = $analyzer->analyzeTitle($titulo2, 'MLB1234');
echo "Score Original: {$analysis2['overall_score']}/100 ({$analysis2['status']})\n\n";

$variations = new TitleVariationsService();
$variationsResult = $variations->generateVariations($titulo2, [
    'count' => 5,
    'category_id' => 'MLB1234',
    'min_score' => 70
]);

if (!empty($variationsResult['variations'])) {
    echo "Variações otimizadas geradas: {$variationsResult['variations_suitable']}\n\n";
    echo "Top 3 melhorias:\n";
    foreach (array_slice($variationsResult['variations'], 0, 3) as $i => $var) {
        $improvement = $var['score'] - $analysis2['overall_score'];
        echo "  " . ($i + 1) . ". {$var['title']}\n";
        echo "     Score: {$var['score']}/100 (+$improvement pontos)\n";
        echo "     Estratégia: {$var['strategy']}\n";
    }
} else {
    echo "Nenhuma variação adequada encontrada (título muito curto).\n";
}
echo "\n";

// ========================================
// EXEMPLO 4: A/B TESTING
// ========================================
echo "EXEMPLO 4: Gerar variações para A/B Testing\n";
echo str_repeat('-', 60) . "\n";

$titulo3 = "Samsung Galaxy S23 128GB";
echo "Título Base: $titulo3\n\n";

$abResult = $variations->generateABTestingVariations($titulo3, [
    'category_id' => 'MLB1234'
]);

echo "Variações A/B/C geradas:\n\n";
foreach ($abResult['ab_variations'] as $var) {
    echo "[VARIAÇÃO {$var['type']}]\n";
    echo "  Título: {$var['title']}\n";
    echo "  Score: {$var['score']}/100\n";
    echo "  Foco: {$var['focus']}\n";
    echo "  Descrição: {$var['description']}\n";
    echo "  CTR Estimado: {$var['estimated_ctr']}\n";
    echo "  Ranking: {$var['ranking_potential']}\n\n";
}

echo "Recomendação:\n";
echo "  → Usar Variação {$abResult['recommendation']['recommended_type']}\n";
echo "  → Razão: {$abResult['recommendation']['reason']}\n";
echo "\n";

// ========================================
// EXEMPLO 5: COMPARAR MÚLTIPLOS TÍTULOS
// ========================================
echo "EXEMPLO 5: Comparar múltiplos títulos\n";
echo str_repeat('-', 60) . "\n";

$titulosParaComparar = [
    "iPhone 15 Pro Max 256GB",
    "Apple iPhone 15 Pro Max Titanio Natural",
    "256GB iPhone 15 Pro Max Apple",
    "iPhone 15"
];

echo "Comparando " . count($titulosParaComparar) . " títulos:\n\n";

$comparisons = [];
foreach ($titulosParaComparar as $titulo) {
    $analysis = $analyzer->analyzeTitle($titulo, 'MLB1234');
    $comparisons[] = [
        'title' => $titulo,
        'score' => $analysis['overall_score'],
        'length' => $analysis['length'],
        'status' => $analysis['status']
    ];
}

// Ordenar por score
usort($comparisons, fn($a, $b) => $b['score'] <=> $a['score']);

foreach ($comparisons as $i => $comp) {
    $medal = $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '  '));
    echo "$medal " . ($i + 1) . ". {$comp['title']}\n";
    echo "     Score: {$comp['score']}/100 ({$comp['status']})\n";
    echo "     Comprimento: {$comp['length']} caracteres\n\n";
}

echo "Vencedor: {$comparisons[0]['title']} ({$comparisons[0]['score']}/100)\n";
echo "\n";

// ========================================
// EXEMPLO 6: PERFORMANCE ESTIMADO
// ========================================
echo "EXEMPLO 6: Estimativa de Performance\n";
echo str_repeat('-', 60) . "\n";

$tituloPro = "iPhone 15 Pro Max 256GB Titanio Natural Apple";
$analysisPro = $analyzer->analyzeTitle($tituloPro, 'MLB1234');

echo "Título: $tituloPro\n";
echo "Score: {$analysisPro['overall_score']}/100\n\n";

echo "Estimativas de Performance:\n";
echo "  - Performance Score: {$analysisPro['performance_estimate']['performance_score']}/100\n";
echo "  - CTR Estimado: {$analysisPro['performance_estimate']['click_through_rate_estimate']}\n";
echo "  - Conversão: {$analysisPro['performance_estimate']['conversion_probability']}\n";
echo "  - Potencial de Ranking: {$analysisPro['performance_estimate']['ranking_potential']}\n";
echo "  - Views Estimadas: {$analysisPro['performance_estimate']['estimated_views']}\n";
echo "  - Cliques Estimados: {$analysisPro['performance_estimate']['estimated_clicks']}\n";
echo "\n";

// ========================================
// RESUMO GERAL
// ========================================
echo "=== RESUMO DOS EXEMPLOS ===\n";
echo str_repeat('=', 60) . "\n";
echo "✓ EXEMPLO 1: Gerados {$result['generated_count']} títulos otimizados\n";
echo "✓ EXEMPLO 2: Análise completa de título existente\n";
echo "✓ EXEMPLO 3: Otimização de título ruim\n";
echo "✓ EXEMPLO 4: Variações A/B/C para testing\n";
echo "✓ EXEMPLO 5: Comparação de múltiplos títulos\n";
echo "✓ EXEMPLO 6: Estimativa de performance\n";
echo str_repeat('=', 60) . "\n";

echo "\n✅ Todos exemplos executados com sucesso!\n";
