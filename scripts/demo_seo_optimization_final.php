<?php

// Include the autoloader
require_once __DIR__ . '/autoload.php';

// Demo script to showcase the complete SEO optimization system

echo "🔍 Iniciando demonstração do Sistema Avançado de SEO\n";
echo "=====================================================\n\n";

// Create the SEO engine
$engine = new \App\Services\SEO\SEOStrategiesEngine();

// Sample item to optimize
$itemId = 'DEMO_ITEM_001';
$itemData = [
    'id' => $itemId,
    'title' => 'Bauleto 41L Universal para Moto',
    'description' => 'Baú traseiro para moto, capacidade de 41 litros, modelo universal compatível com várias marcas.',
    'category_id' => 'MLB3530', // Baús e Bagageiros
    'model' => '',
    'attributes' => [
        'capacity' => '41L',
        'color' => 'preto',
        'material' => 'ABS'
    ]
];

echo "📦 Item para otimização:\n";
echo "- ID: {$itemData['id']}\n";
echo "- Título: {$itemData['title']}\n";
echo "- Categoria: {$itemData['category_id']}\n\n";

echo "⚙️ Executando otimização completa (12 estratégias SEO)...\n\n";

// Perform full optimization
$start_time = microtime(true);
$result = $engine->optimizeFull($itemId);
$end_time = microtime(true);

$execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds

echo "✅ Otimização concluída!\n";
echo "⏱️ Tempo de execução: " . number_format($execution_time, 2) . " ms\n";
echo "📈 Score SEO geral: {$result['overall_score']}/100\n\n";

echo "📊 Resultado da Otimização:\n";
echo "---------------------------\n";

if (isset($result['synonym_expansion'])) {
    echo "🔹 Expansão de Sinônimos: ";
    $synonymCount = 0;
    foreach ($result['synonym_expansion'] as $level => $data) {
        if (isset($data['words'])) {
            $synonymCount += count($data['words']);
        }
    }
    echo $synonymCount . " sinônimos encontrados\n";
}

if (isset($result['keyword_distribution'])) {
    echo "🔹 Distribuição de Keywords: ";
    $keywordCount = 0;
    foreach ($result['keyword_distribution'] as $field => $data) {
        if (isset($data['count'])) {
            $keywordCount += $data['count'];
        }
    }
    echo $keywordCount . " keywords distribuídas\n";
}

if (isset($result['description_building'])) {
    echo "🔹 Construção de Descrição: ";
    $wordCount = $result['description_building']['word_count'] ?? 0;
    $blockCount = count($result['description_building']['blocks'] ?? []);
    echo "{$wordCount} palavras em {$blockCount} blocos\n";
}

if (isset($result['coverage_analysis'])) {
    echo "🔹 Análise de Cobertura: ";
    $coveredTypes = 0;
    foreach ($result['coverage_analysis'] as $type => $data) {
        if ($data['covered']) {
            $coveredTypes++;
        }
    }
    echo "{$coveredTypes} de " . count($result['coverage_analysis']) . " tipos de busca cobertos\n";
}

echo "\n📋 Relatório de Otimização:\n";
echo "---------------------------\n";
$report = $result['report'];
echo "- Data/Hora: {$report['timestamp']}\n";
echo "- Estratégias executadas: " . count($report['executed_strategies']) . "\n";
echo "- Potencial de melhoria: {$report['improvement_potential']}\n";

if (!empty($report['recommendations'])) {
    echo "- Recomendações:\n";
    foreach (array_slice($report['recommendations'], 0, 3) as $rec) { // Show first 3 recommendations
        echo "  • {$rec}\n";
    }
}

echo "\n🎯 Demonstração de Componentes Individuais:\n";
echo "------------------------------------------\n";

// Demonstrate individual services
$synonymService = new \App\Services\SEO\SynonymExpansionService();
$semanticService = new \App\Services\SEO\SemanticScoreService();
$keywordService = new \App\Services\SEO\KeywordDistributionService();
$descService = new \App\Services\SEO\DescriptionBuilderService();

// Synonym expansion demo
echo "\n🔹 Expansão de Sinônimos:\n";
$title = $itemData['title'];
$category = $itemData['category_id'];
$synonyms = $synonymService->expand($title, $category);

foreach ($synonyms as $level => $data) {
    if (!empty($data['words'])) {
        echo "  {$level}: " . implode(', ', array_slice($data['words'], 0, 3)) . "\n";
    }
}

// Semantic scoring demo
echo "\n🔹 Pontuação Semântica:\n";
$sampleWords = ['baú', 'moto', 'entrega'];
$wordScores = $semanticService->scoreWords($sampleWords, $title, $category);
foreach ($wordScores as $word => $score) {
    echo "  {$word}: " . number_format($score, 2) . "/100\n";
}

// Keyword distribution demo
echo "\n🔹 Distribuição de Keywords:\n";
$distribution = $keywordService->distribute($itemData, $category);
foreach ($distribution as $field => $data) {
    if (isset($data['count'])) {
        echo "  {$field}: {$data['count']} keywords (peso: {$data['weight']})\n";
    }
}

// Description building demo
echo "\n🔹 Construção de Descrição:\n";
$descResult = $descService->build($itemData, $distribution);
echo "  Blocos gerados: " . count($descResult['blocks']) . "\n";
echo "  Palavras: {$descResult['word_count']}\n";
echo "  Score: {$descResult['score']}/100\n";

echo "\n✨ Demonstração concluída!\n";
echo "O sistema está pronto para otimizar anúncios do Mercado Livre com as 12 estratégias SEO.\n";