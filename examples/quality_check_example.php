#!/usr/bin/env php
<?php

/**
 * Exemplo de uso do Quality Check System
 * 
 * Execute: php examples/quality_check_example.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\Quality\HealthCheckService;
use App\Services\Quality\QualityScoreService;
use App\Services\Quality\ValidationService;

echo "🔍 QUALITY CHECK SYSTEM - DEMO\n";
echo str_repeat("=", 60) . "\n\n";

// Configuração
$accountId = null; // Use null para teste ou configure um accountId real
$itemId = 'MLB3698937524'; // Item de exemplo

// ========================================
// 1. HEALTH CHECK
// ========================================
echo "1️⃣  HEALTH CHECK\n";
echo str_repeat("-", 60) . "\n";

try {
    $healthService = new HealthCheckService($accountId);
    $health = $healthService->checkItemHealth($itemId);

    if ($health['success']) {
        echo "✓ Item: {$health['title']}\n";
        echo "✓ Status: {$health['health']['status']}\n";
        echo "✓ Score: {$health['health']['score']}\n";
        echo "✓ Problemas: {$health['summary']['total_issues']}\n";
        echo "✓ Problemas Críticos: {$health['summary']['critical_issues']}\n\n";

        if (!empty($health['issues'])) {
            echo "⚠️  Problemas Encontrados:\n";
            foreach (array_slice($health['issues'], 0, 3) as $issue) {
                echo "   - [{$issue['severity']}] {$issue['title']}\n";
            }
            echo "\n";
        }

        if (!empty($health['recommendations'])) {
            echo "💡 Top 3 Recomendações:\n";
            foreach (array_slice($health['recommendations'], 0, 3) as $rec) {
                echo "   - [{$rec['priority']}] {$rec['action']}\n";
            }
            echo "\n";
        }
    } else {
        echo "✗ Erro: {$health['error']}\n\n";
    }
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n\n";
}

// ========================================
// 2. QUALITY SCORE
// ========================================
echo "2️⃣  QUALITY SCORE\n";
echo str_repeat("-", 60) . "\n";

try {
    $qualityService = new QualityScoreService($accountId);
    $score = $qualityService->calculateQualityScore($itemId);

    if ($score['success']) {
        echo "✓ Item: {$score['title']}\n";
        echo "✓ Score Total: {$score['quality_score']['total']}\n";
        echo "✓ Rating: {$score['quality_score']['rating']['label']}\n\n";

        echo "📊 Componentes:\n";
        foreach ($score['quality_score']['components'] as $key => $component) {
            echo "   - " . ucfirst($key) . ": {$component['percentage']}%\n";
        }
        echo "\n";

        if (!empty($score['strengths'])) {
            echo "💪 Pontos Fortes:\n";
            foreach (array_slice($score['strengths'], 0, 3) as $strength) {
                echo "   - {$strength}\n";
            }
            echo "\n";
        }

        if (!empty($score['weaknesses'])) {
            echo "⚠️  Pontos Fracos:\n";
            foreach (array_slice($score['weaknesses'], 0, 3) as $weakness) {
                echo "   - {$weakness}\n";
            }
            echo "\n";
        }

        echo "📈 Potencial de Melhoria: {$score['improvement_potential']['potential_gain']}\n";
        echo "   Score Atual: {$score['improvement_potential']['current_score']}\n";
        echo "   Score Máximo Possível: {$score['improvement_potential']['max_possible_score']}\n\n";
    } else {
        echo "✗ Erro: {$score['error']}\n\n";
    }
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n\n";
}

// ========================================
// 3. VALIDATION
// ========================================
echo "3️⃣  VALIDATION (Exemplo de Dados)\n";
echo str_repeat("-", 60) . "\n";

// Dados de exemplo para validação
$itemData = [
    'title' => 'Produto Teste Original Novo Lacrado',
    'category_id' => 'MLB1051',
    'price' => 199.90,
    'currency_id' => 'BRL',
    'available_quantity' => 10,
    'buying_mode' => 'buy_it_now',
    'condition' => 'new',
    'listing_type_id' => 'gold_special',
    'pictures' => [
        ['source' => 'https://http2.mlstatic.com/D_NQ_NP_123456-MLB1234567890_012024-O.jpg']
    ],
    'attributes' => [
        ['id' => 'BRAND', 'value_name' => 'Samsung']
    ]
];

try {
    $validator = new ValidationService($accountId);
    $validation = $validator->validateListing($itemData);

    echo "✓ Pode Publicar: " . ($validation['can_publish'] ? 'SIM' : 'NÃO') . "\n";
    echo "✓ Total de Erros: {$validation['summary']['total_errors']}\n";
    echo "✓ Total de Warnings: {$validation['summary']['total_warnings']}\n\n";

    if (!empty($validation['errors'])) {
        echo "❌ Erros:\n";
        foreach ($validation['errors'] as $error) {
            echo "   - [{$error['category']}] {$error['message']}\n";
        }
        echo "\n";
    }

    if (!empty($validation['warnings'])) {
        echo "⚠️  Avisos:\n";
        foreach (array_slice($validation['warnings'], 0, 3) as $warning) {
            echo "   - [{$warning['category']}] {$warning['message']}\n";
        }
        echo "\n";
    }

    // Auto-fix
    echo "🔧 Auto-Fix:\n";
    $fixed = $validator->autoFix($itemData);
    if ($fixed['changed']) {
        echo "   ✓ {$fixed['changes'][count($fixed['changes']) - 1]}\n";
        echo "   Total de mudanças: " . count($fixed['changes']) . "\n\n";
    } else {
        echo "   ✓ Nenhuma correção necessária\n\n";
    }

} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n\n";
}

// ========================================
// 4. RESUMO
// ========================================
echo "4️⃣  RESUMO\n";
echo str_repeat("-", 60) . "\n";
echo "✅ Sistema Quality Check está funcionando!\n";
echo "\n";
echo "📡 Endpoints disponíveis:\n";
echo "   - GET  /api/quality/health/{itemId}\n";
echo "   - GET  /api/quality/score/{itemId}\n";
echo "   - POST /api/quality/validate\n";
echo "   - GET  /api/quality/report/{itemId}\n";
echo "\n";
echo "📚 Documentação completa:\n";
echo "   docs/QUALITY_CHECK_SYSTEM.md\n";
echo "\n";

echo str_repeat("=", 60) . "\n";
echo "✨ Demo concluída!\n";
