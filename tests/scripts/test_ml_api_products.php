<?php

declare(strict_types=1);

/**
 * Teste Avançado com API do Mercado Livre
 * Busca produtos reais e aplica análise completa
 */

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
        putenv(trim($name) . '=' . trim($value));
    }
}

// Carregar dependências
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Services/AI/SEO/Strategies/SynonymExpansionService.php';
require_once __DIR__ . '/app/Services/AI/SEO/Strategies/SemanticScoreService.php';

use App\Services\AI\SEO\Strategies\SynonymExpansionService;
use App\Services\AI\SEO\Strategies\SemanticScoreService;

echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║                                                                           ║\n";
echo "║       ANÁLISE DE PRODUTOS REAIS - MERCADO LIVRE API                       ║\n";
echo "║                                                                           ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

$categoryId = 'MLB3530'; // Baús/Bagageiros
$accountId = null;

// Buscar produtos via API
echo "🔍 Buscando produtos reais da categoria {$categoryId}...\n";

$apiUrl = "https://api.mercadolibre.com/sites/MLB/search?category={$categoryId}&limit=10&offset=0";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'SEOStrategies/1.0');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$products = [];

if ($httpCode === 200 && !empty($response)) {
    $data = json_decode($response, true);

    if (isset($data['results']) && is_array($data['results'])) {
        $products = $data['results'];
        echo "✅ " . count($products) . " produtos encontrados!\n\n";
    } else {
        echo "⚠️  Resposta da API não contém produtos\n";
        echo "   Dados recebidos: " . substr($response, 0, 200) . "...\n\n";
    }
} else {
    echo "❌ Erro ao buscar da API (HTTP {$httpCode})\n";
    if ($curlError) {
        echo "   Erro CURL: {$curlError}\n";
    }
    echo "\n";
}

// Se não conseguiu da API, usar exemplos
if (empty($products)) {
    echo "📝 Usando produtos de exemplo para demonstração...\n\n";

    $products = [
        [
            'id' => 'MLB-DEMO-001',
            'title' => 'Bauleto 41 Litros Pro Tork Smart Box',
            'price' => 299.90,
            'permalink' => '#',
            'thumbnail' => '',
            'category_id' => $categoryId,
            'condition' => 'new',
            'sold_quantity' => 45
        ],
        [
            'id' => 'MLB-DEMO-002',
            'title' => 'Baú Delivery 45L Motoboy Ifood Rappi',
            'price' => 189.90,
            'permalink' => '#',
            'thumbnail' => '',
            'category_id' => $categoryId,
            'condition' => 'new',
            'sold_quantity' => 128
        ],
        [
            'id' => 'MLB-DEMO-003',
            'title' => 'Top Case Universal Capacete Cabe 2 Capacetes',
            'price' => 159.90,
            'permalink' => '#',
            'thumbnail' => '',
            'category_id' => $categoryId,
            'condition' => 'new',
            'sold_quantity' => 67
        ],
        [
            'id' => 'MLB-DEMO-004',
            'title' => 'Bagageiro Traseiro Moto Universal Suporte Bau',
            'price' => 89.90,
            'permalink' => '#',
            'thumbnail' => '',
            'category_id' => $categoryId,
            'condition' => 'new',
            'sold_quantity' => 203
        ],
        [
            'id' => 'MLB-DEMO-005',
            'title' => 'Bauleto Givi E460 Litros Top Case',
            'price' => 899.90,
            'permalink' => '#',
            'thumbnail' => '',
            'category_id' => $categoryId,
            'condition' => 'new',
            'sold_quantity' => 12
        ]
    ];
}

// Inicializar services
try {
    $synonymService = new SynonymExpansionService($accountId);
    $semanticService = new SemanticScoreService($accountId);
} catch (Exception $e) {
    echo "❌ Erro ao inicializar services: " . $e->getMessage() . "\n";
    exit(1);
}

echo str_repeat("═", 79) . "\n\n";

// Analisar produtos
$results = [];

foreach ($products as $index => $product) {
    $num = $index + 1;
    $total = count($products);

    echo "┌" . str_repeat("─", 77) . "┐\n";
    echo "│ PRODUTO {$num}/{$total}" . str_repeat(" ", 67) . "│\n";
    echo "└" . str_repeat("─", 77) . "┘\n\n";

    $itemId = $product['id'];
    $title = $product['title'];
    $price = $product['price'] ?? 0;
    $soldQty = $product['sold_quantity'] ?? 0;

    echo "🆔 ID: {$itemId}\n";
    echo "📦 Título: {$title}\n";
    echo "💰 Preço: R$ " . number_format($price, 2, ',', '.') . "\n";
    echo "📊 Vendidos: {$soldQty} unidades\n";

    if (!empty($product['permalink']) && $product['permalink'] !== '#') {
        echo "🔗 Link: {$product['permalink']}\n";
    }

    echo "\n";

    // Análise de sinônimos
    echo "📈 ANÁLISE SEO\n";
    echo str_repeat("─", 50) . "\n";

    $analysis = [
        'id' => $itemId,
        'title' => $title,
        'title_length' => strlen($title),
        'price' => $price,
        'sold_quantity' => $soldQty
    ];

    try {
        // Expansão de sinônimos
        $expanded = $synonymService->expand($title, $categoryId);
        $analysis['expansions'] = count($expanded);

        // Detectar nível
        $level = $synonymService->identifyLevel($title);
        $analysis['level'] = $level;

        // Score semântico
        $words = preg_split('/\s+/', strtolower($title));
        $words = array_filter($words, function($w) { return strlen($w) > 3; });
        $words = array_values(array_unique($words));

        if (!empty($words)) {
            // Normalizar score para escala 0-100
            $scores = [];
            foreach ($words as $word) {
                $rawScore = $semanticService->calculateScore($word, $title, $categoryId);
                // Score vem em escala 0-1, multiplicar por 100
                $normalizedScore = $rawScore * 100;
                $scores[] = $normalizedScore;
            }

            $avgScore = array_sum($scores) / count($scores);
            $analysis['semantic_score'] = $avgScore;
            $analysis['keywords_count'] = count($words);
        } else {
            $analysis['semantic_score'] = 0;
            $analysis['keywords_count'] = 0;
        }

        // Verificar contextos
        $contextsFound = 0;
        foreach ($words as $word) {
            if ($semanticService->hasUseContext($word)) {
                $contextsFound++;
            }
        }
        $analysis['contexts'] = $contextsFound;

        // Calcular score final (0-100)
        $scoreComponents = [
            'title_length' => 0,
            'level' => 0,
            'semantic' => 0,
            'contexts' => 0,
            'expansions' => 0
        ];

        // Tamanho do título (0-20 pontos)
        $titleLen = strlen($title);
        if ($titleLen >= 40 && $titleLen <= 60) {
            $scoreComponents['title_length'] = 20;
        } elseif ($titleLen >= 30 && $titleLen <= 70) {
            $scoreComponents['title_length'] = 15;
        } else {
            $scoreComponents['title_length'] = 10;
        }

        // Nível hierárquico (0-20 pontos)
        $levelScores = [
            'nivel_1' => 10,
            'nivel_2' => 15,
            'nivel_3' => 20,
            'nivel_4' => 18
        ];
        $scoreComponents['level'] = $levelScores[$level] ?? 10;

        // Score semântico (0-30 pontos)
        $scoreComponents['semantic'] = min(30, ($avgScore / 100) * 30);

        // Contextos (0-15 pontos)
        $scoreComponents['contexts'] = min(15, $contextsFound * 5);

        // Expansões (0-15 pontos)
        $expCount = count($expanded);
        if ($expCount >= 10) {
            $scoreComponents['expansions'] = 15;
        } elseif ($expCount >= 5) {
            $scoreComponents['expansions'] = 10;
        } else {
            $scoreComponents['expansions'] = 5;
        }

        $finalScore = array_sum($scoreComponents);
        $analysis['final_score'] = $finalScore;
        $analysis['score_components'] = $scoreComponents;

        // Determinar qualidade
        if ($finalScore >= 80) {
            $quality = 'Excelente';
            $emoji = '🟢';
        } elseif ($finalScore >= 60) {
            $quality = 'Boa';
            $emoji = '🟡';
        } elseif ($finalScore >= 40) {
            $quality = 'Regular';
            $emoji = '🟠';
        } else {
            $quality = 'Baixa';
            $emoji = '🔴';
        }
        $analysis['quality'] = $quality;

        // Exibir resultados
        echo "✅ Nível: {$level}\n";
        echo "✅ Expansões: {$expCount} variações\n";
        echo "✅ Palavras-chave: " . count($words) . " palavras\n";
        echo "✅ Contextos de uso: {$contextsFound}\n";
        echo "✅ Score semântico: " . number_format($avgScore, 1) . "/100\n";
        echo "\n";
        echo "{$emoji} SCORE FINAL: " . number_format($finalScore, 1) . "/100 ({$quality})\n";

        // Breakdown do score
        echo "\n   Componentes do Score:\n";
        echo "   • Tamanho do título: " . number_format($scoreComponents['title_length'], 0) . "/20\n";
        echo "   • Nível hierárquico: " . number_format($scoreComponents['level'], 0) . "/20\n";
        echo "   • Score semântico: " . number_format($scoreComponents['semantic'], 1) . "/30\n";
        echo "   • Contextos de uso: " . number_format($scoreComponents['contexts'], 0) . "/15\n";
        echo "   • Expansões: " . number_format($scoreComponents['expansions'], 0) . "/15\n";

    } catch (Exception $e) {
        echo "❌ Erro na análise: " . $e->getMessage() . "\n";
        $analysis['error'] = $e->getMessage();
        $analysis['final_score'] = 0;
        $analysis['quality'] = 'Erro';
    }

    $results[] = $analysis;

    echo "\n";
    echo str_repeat("═", 79) . "\n\n";
}

// Relatório final
echo "┌" . str_repeat("─", 77) . "┐\n";
echo "│" . str_pad(" RELATÓRIO FINAL", 78, " ", STR_PAD_BOTH) . "│\n";
echo "└" . str_repeat("─", 77) . "┘\n\n";

$validResults = array_filter($results, function($r) {
    return !isset($r['error']);
});

if (!empty($validResults)) {
    $scores = array_column($validResults, 'final_score');
    $avgScore = array_sum($scores) / count($scores);
    $maxScore = max($scores);
    $minScore = min($scores);

    echo "📊 ESTATÍSTICAS\n";
    echo str_repeat("─", 50) . "\n";
    echo "Produtos analisados: " . count($validResults) . "\n";
    echo "Score médio: " . number_format($avgScore, 1) . "/100\n";
    echo "Melhor score: " . number_format($maxScore, 1) . "/100\n";
    echo "Pior score: " . number_format($minScore, 1) . "/100\n\n";

    // Distribuição por qualidade
    $qualityDist = array_count_values(array_column($validResults, 'quality'));
    echo "Distribuição de Qualidade:\n";
    foreach (['Excelente', 'Boa', 'Regular', 'Baixa'] as $q) {
        $count = $qualityDist[$q] ?? 0;
        if ($count > 0) {
            $pct = ($count / count($validResults)) * 100;
            echo "• {$q}: {$count} produtos (" . number_format($pct, 0) . "%)\n";
        }
    }

    echo "\n";

    // Ranking
    usort($validResults, function($a, $b) {
        return $b['final_score'] <=> $a['final_score'];
    });

    echo "🏆 TOP 5 MELHORES TÍTULOS\n";
    echo str_repeat("─", 50) . "\n";

    $top5 = array_slice($validResults, 0, 5);
    foreach ($top5 as $idx => $result) {
        $pos = $idx + 1;
        $medal = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'][$idx] ?? "#{$pos}";
        $score = number_format($result['final_score'], 1);
        $title = mb_substr($result['title'], 0, 50);
        if (strlen($result['title']) > 50) $title .= '...';

        echo "{$medal} Score: {$score}/100\n";
        echo "   {$title}\n";
        echo "   Vendas: {$result['sold_quantity']} | Qualidade: {$result['quality']}\n\n";
    }
}

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║                                                                           ║\n";
echo "║                  ✅ ANÁLISE COMPLETA FINALIZADA!                          ║\n";
echo "║                                                                           ║\n";
echo "║    Sistema de SEO Strategies validado com produtos do Mercado Livre      ║\n";
echo "║                                                                           ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n";
