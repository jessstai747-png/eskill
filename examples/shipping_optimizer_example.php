<?php

/**
 * SHIPPING STRATEGY OPTIMIZER - EXAMPLE
 * 
 * Demonstra o uso completo do sistema de otimização de envios
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Services\Shipping\ShippingSimulatorService;
use App\Services\Shipping\ShippingOptimizerService;
use App\Services\Shipping\DimensionCalculatorService;

echo "========================================\n";
echo "📦 SHIPPING STRATEGY OPTIMIZER - DEMO\n";
echo "========================================\n\n";

// ========================================
// EXEMPLO 1: NOVO PRODUTO - ESCOLHER MODALIDADE
// ========================================

echo "📋 EXEMPLO 1: Novo Produto - Escolher Melhor Modalidade\n";
echo "---------------------------------------------------\n";

$productData = [
    'name' => 'Smart Watch Premium',
    'dimensions' => [
        'length' => 12,
        'width' => 8,
        'height' => 3,
    ],
    'weight' => 0.2,
    'price' => 299.90,
    'category_id' => 'MLB1953', // Relógios
];

echo "Produto: {$productData['name']}\n";
echo "Dimensões: {$productData['dimensions']['length']}x{$productData['dimensions']['width']}x{$productData['dimensions']['height']} cm\n";
echo "Peso: {$productData['weight']} kg\n";
echo "Preço: R$ {$productData['price']}\n\n";

// Passo 1: Validar dimensões
echo "1️⃣  Validando dimensões...\n";
$calculator = new DimensionCalculatorService();
$validation = $calculator->validateForAllModes(
    $productData['dimensions']['length'],
    $productData['dimensions']['width'],
    $productData['dimensions']['height'],
    $productData['weight']
);

echo "   Modalidades compatíveis: " . implode(', ', $validation['compatible_modes']) . "\n";
echo "   Recomendada: {$validation['recommended_mode']}\n\n";

// Passo 2: Simular custos
echo "2️⃣  Simulando custos de envio...\n";
$simulator = new ShippingSimulatorService();
$simulation = $simulator->simulateShipping([
    'dimensions' => $productData['dimensions'],
    'weight' => $productData['weight'],
    'zip_code' => '01310-100', // São Paulo
]);

if ($simulation['success']) {
    echo "   Custos estimados:\n";
    foreach ($simulation['estimated_costs'] as $mode => $cost) {
        if ($cost['available']) {
            echo "   • {$mode}: R$ {$cost['cost']} (entrega em {$cost['delivery_days']} dias)\n";
        }
    }
    
    echo "\n   ✅ Recomendação: {$simulation['recommendation']['best']}\n";
    echo "   📈 Motivo: {$simulation['recommendation']['reason']}\n";
    echo "   🎯 Impacto: {$simulation['recommendation']['conversion_impact']}\n";
}

echo "\n";

// ========================================
// EXEMPLO 2: PRODUTO EXISTENTE - OTIMIZAR
// ========================================

echo "📋 EXEMPLO 2: Produto Existente - Otimizar Estratégia\n";
echo "---------------------------------------------------\n";

// Simulando um item real do ML
$itemId = 'MLB123456789';

echo "Item ID: {$itemId}\n\n";

// Otimizar estratégia
echo "3️⃣  Otimizando estratégia de envio...\n";
$optimizer = new ShippingOptimizerService();
$optimization = $optimizer->optimizeShipping($itemId, [
    'target_margin' => 0.30, // 30% de margem desejada
]);

if ($optimization['success']) {
    $current = $optimization['current_shipping'];
    $recommendation = $optimization['recommendation'];
    
    // Status atual
    echo "   Status Atual:\n";
    echo "   • Modalidade: {$current['mode_label']}\n";
    echo "   • Score de Saúde: {$current['score']}/100 ({$current['score_label']})\n";
    
    if (!empty($current['issues'])) {
        echo "\n   ⚠️  Problemas Detectados:\n";
        foreach ($current['issues'] as $issue) {
            echo "   • [{$issue['severity']}] {$issue['issue']}\n";
            echo "     Impacto: {$issue['impact']}\n";
            echo "     Solução: {$issue['solution']}\n";
        }
    }
    
    // Recomendação
    echo "\n   ✅ Recomendação Otimizada:\n";
    echo "   • Migrar para: " . ($recommendation['recommended_mode'] ?? 'N/A') . "\n";
    echo "   • Confiança: {$recommendation['confidence_score']}/100\n";
    echo "   • Aumento de conversão: {$recommendation['estimated_conversion_increase']}\n";
    
    // Próximos passos
    if (!empty($recommendation['next_steps'])) {
        echo "\n   📋 Próximos Passos:\n";
        foreach ($recommendation['next_steps'] as $i => $step) {
            echo "   " . ($i + 1) . ". {$step['description']}\n";
        }
    }
    
    // Análise de concorrência
    if ($optimization['competition_analysis']['available']) {
        echo "\n   🔍 Análise de Concorrência:\n";
        $stats = $optimization['competition_analysis']['statistics']['percentages'];
        echo "   • Frete grátis: {$stats['free_shipping']}%\n";
        echo "   • Full: {$stats['full']}%\n";
        echo "   • Flex: {$stats['flex']}%\n";
    }
}

echo "\n";

// ========================================
// EXEMPLO 3: OTIMIZAR DIMENSÕES
// ========================================

echo "📋 EXEMPLO 3: Otimizar Dimensões para Reduzir Custo\n";
echo "---------------------------------------------------\n";

$oversizedProduct = [
    'name' => 'Caixa de Som Grande',
    'length' => 50,
    'width' => 40,
    'height' => 30,
    'weight' => 8.0,
];

echo "Produto: {$oversizedProduct['name']}\n";
echo "Dimensões: {$oversizedProduct['length']}x{$oversizedProduct['width']}x{$oversizedProduct['height']} cm\n";
echo "Peso: {$oversizedProduct['weight']} kg\n\n";

// Calcular peso cubado
echo "4️⃣  Analisando peso cubado...\n";
$cubicWeight = $calculator->calculateCubicWeight(
    $oversizedProduct['length'],
    $oversizedProduct['width'],
    $oversizedProduct['height']
);

echo "   Peso cubado: {$cubicWeight} kg\n";
echo "   Peso real: {$oversizedProduct['weight']} kg\n";
echo "   Peso cobrado: " . max($cubicWeight, $oversizedProduct['weight']) . " kg\n";
echo "   🔔 Será cobrado pelo " . ($cubicWeight > $oversizedProduct['weight'] ? 'peso cubado' : 'peso real') . "\n\n";

// Otimizar dimensões
echo "5️⃣  Buscando oportunidades de otimização...\n";
$optDimensions = $calculator->optimizeDimensions(
    $oversizedProduct['length'],
    $oversizedProduct['width'],
    $oversizedProduct['height'],
    $oversizedProduct['weight'],
    'me2'
);

if (!empty($optDimensions['suggestions'])) {
    echo "   💡 Sugestões de Melhoria:\n\n";
    foreach ($optDimensions['suggestions'] as $suggestion) {
        echo "   🎯 {$suggestion['type']}\n";
        echo "      {$suggestion['description']}\n";
        if (isset($suggestion['cost_savings_estimate'])) {
            echo "      Economia potencial: {$suggestion['cost_savings_estimate']}\n";
        }
        echo "      Impacto: {$suggestion['impact']}\n\n";
    }
    
    echo "   💰 Economia Total Potencial: {$optDimensions['total_potential_savings']}\n";
}

echo "\n";

// ========================================
// EXEMPLO 4: COMPARAR CUSTOS REGIONAIS
// ========================================

echo "📋 EXEMPLO 4: Comparar Custos para Diferentes Regiões\n";
echo "---------------------------------------------------\n";

$regions = [
    '01310-100' => 'São Paulo - SP',
    '20040-020' => 'Rio de Janeiro - RJ',
    '30130-100' => 'Belo Horizonte - MG',
    '40020-000' => 'Salvador - BA',
    '50010-000' => 'Recife - PE',
];

echo "Produto: Smart Watch Premium\n";
echo "Comparando custos para " . count($regions) . " regiões...\n\n";

foreach ($regions as $zipCode => $city) {
    $regionalSim = $simulator->simulateShipping([
        'dimensions' => $productData['dimensions'],
        'weight' => $productData['weight'],
        'zip_code' => $zipCode,
    ]);
    
    if ($regionalSim['success']) {
        $me2 = $regionalSim['estimated_costs']['me2'];
        echo "   📍 {$city}\n";
        echo "      Custo: R$ {$me2['cost']}\n";
        echo "      Prazo: {$me2['delivery_days']} dias\n\n";
    }
}

// ========================================
// EXEMPLO 5: ANÁLISE COMPLETA
// ========================================

echo "📋 EXEMPLO 5: Análise Completa de Dimensões\n";
echo "---------------------------------------------------\n";

$completeAnalysis = $calculator->analyzeComplete(
    $productData['dimensions']['length'],
    $productData['dimensions']['width'],
    $productData['dimensions']['height'],
    $productData['weight']
);

echo "   Análise Completa:\n\n";

// Peso cobrável
$chargeable = $completeAnalysis['chargeable_weight'];
echo "   💰 Peso Cobrável:\n";
echo "      Real: {$chargeable['actual_weight_kg']} kg\n";
echo "      Cubado: {$chargeable['cubic_weight_kg']} kg\n";
echo "      Cobrado: {$chargeable['chargeable_weight_kg']} kg ({$chargeable['using']})\n\n";

// Modalidades compatíveis
$validation = $completeAnalysis['validation_all_modes'];
echo "   ✅ Modalidades Compatíveis: " . implode(', ', $validation['compatible_modes']) . "\n";
echo "   🎯 Recomendada: {$validation['recommended_mode']}\n\n";

// Sugestão de embalagem
$packaging = $completeAnalysis['packaging_suggestions'];
if ($packaging['recommended']) {
    $box = $packaging['recommended'];
    echo "   📦 Embalagem Recomendada: {$box['name']}\n";
    echo "      Dimensões: {$box['dimensions']['length']}x{$box['dimensions']['width']}x{$box['dimensions']['height']} cm\n";
    echo "      Eficiência: {$box['efficiency']}%\n";
    echo "      Peso cubado: {$box['cubic_weight_kg']} kg\n\n";
}

// Custo de embalagem
$packagingCost = $completeAnalysis['packaging_cost'];
echo "   💵 Custo Estimado de Embalagem: R$ {$packagingCost['estimated_cost_brl']}\n";
echo "      Tipo: {$packagingCost['packaging_type']}\n\n";

echo "========================================\n";
echo "✅ DEMO CONCLUÍDA COM SUCESSO\n";
echo "========================================\n";
