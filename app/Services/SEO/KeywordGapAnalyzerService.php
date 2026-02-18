<?php
declare(strict_types=1);

namespace App\Services\SEO;

use App\Services\MercadoLivreClient;
use App\Services\SEO\AIClient;

/**
 * Serviço de análise de lacunas de keywords vs concorrentes
 * Identifica oportunidades visíveis e ocultas no marketplace
 */
class KeywordGapAnalyzerService
{
    private AIClient $ai;
    private MercadoLivreClient $mlClient;
    private ?string $accountId;

    public function __construct(?string $accountId = null)
    {
        $this->ai = new AIClient();
        $this->mlClient = new MercadoLivreClient();
        $this->accountId = $accountId;
    }

    /**
     * Análise completa de lacunas de keywords
     */
    public function analyzeKeywordGaps(string $productId, array $context = []): array
    {
        // 1. Obtém dados do produto atual
        $myProduct = $this->getProductData($productId);
        
        // 2. Busca concorrentes diretos
        $competitors = $this->getDirectCompetitors($myProduct, $context);
        
        // 3. Análise de keywords
        $myKeywords = $this->extractProductKeywords($myProduct);
        $competitorKeywords = $this->extractCompetitorKeywords($competitors);
        
        // 4. Identificação de lacunas
        $gapAnalysis = $this->performGapAnalysis($myKeywords, $competitorKeywords);
        
        // 5. Análise semântica com IA
        $semanticGaps = $this->analyzeSemanticGaps($myProduct, $competitors);
        
        // 6. Oportunidades de cauda longa
        $longTailOpportunities = $this->findLongTailOpportunities($myProduct, $competitors);
        
        return [
            'success' => true,
            'product_id' => $productId,
            'my_keywords' => $myKeywords,
            'competitor_analysis' => [
                'competitors_count' => count($competitors),
                'total_competitor_keywords' => count($competitorKeywords),
                'keyword_overlap' => count(array_intersect($myKeywords, $competitorKeywords))
            ],
            'gap_analysis' => $gapAnalysis,
            'semantic_gaps' => $semanticGaps,
            'long_tail_opportunities' => $longTailOpportunities,
            'actionable_insights' => $this->generateActionableInsights($gapAnalysis, $semanticGaps, $longTailOpportunities),
            'estimated_impact' => $this->estimateImpact($gapAnalysis)
        ];
    }

    /**
     * Análise competitiva de keywords
     */
    public function competitiveKeywordAnalysis(string $category, array $filters = []): array
    {
        // Busca top produtos da categoria
        $topProducts = $this->getTopProductsByCategory($category, $filters);
        
        // Extrai keywords de todos
        $allKeywords = [];
        $productKeywords = [];
        
        foreach ($topProducts as $product) {
            $keywords = $this->extractProductKeywords($product);
            $productKeywords[$product['id']] = $keywords;
            $allKeywords = array_merge($allKeywords, $keywords);
        }
        
        // Análise de frequência e importância
        $keywordFrequency = array_count_values($allKeywords);
        arsort($keywordFrequency);
        
        // Identifica padrões de sucesso
        $successPatterns = $this->identifySuccessPatterns($productKeywords, $topProducts);
        
        return [
            'category' => $category,
            'products_analyzed' => count($topProducts),
            'total_keywords' => count($allKeywords),
            'keyword_frequency' => array_slice($keywordFrequency, 0, 50), // Top 50
            'success_patterns' => $successPatterns,
            'missing_opportunities' => $this->findMissingOpportunities($keywordFrequency, $category),
            'trending_keywords' => $this->identifyTrendingKeywords($category),
            'recommendations' => $this->generateCategoryRecommendations($successPatterns, $keywordFrequency)
        ];
    }

    /**
     * Monitoramento contínuo de lacunas
     */
    public function monitorKeywordGaps(array $productIds): array
    {
        $monitoringData = [];
        
        foreach ($productIds as $productId) {
            $currentAnalysis = $this->analyzeKeywordGaps($productId);
            $previousAnalysis = $this->getPreviousAnalysis($productId);
            
            $monitoringData[$productId] = [
                'current' => $currentAnalysis,
                'changes' => $this->detectChanges($currentAnalysis, $previousAnalysis),
                'trend' => $this->calculateTrend($productId, $currentAnalysis, $previousAnalysis),
                'alerts' => $this->generateAlerts($currentAnalysis, $previousAnalysis)
            ];
        }
        
        return [
            'monitoring_date' => date('Y-m-d H:i:s'),
            'products_monitored' => count($productIds),
            'data' => $monitoringData,
            'summary' => $this->generateMonitoringSummary($monitoringData)
        ];
    }

    /**
     * Obtém dados do produto
     */
    private function getProductData(string $productId): array
    {
        try {
            $response = $this->mlClient->get("/items/{$productId}");
            
            if ($response) {
                return [
                    'id' => $response['id'],
                    'title' => $response['title'],
                    'category_id' => $response['category_id'],
                    'price' => $response['price'],
                    'description' => $response['description'] ?? '',
                    'attributes' => $response['attributes'] ?? [],
                    'sold_quantity' => $response['sold_quantity'] ?? 0,
                    'available_quantity' => $response['available_quantity'] ?? 0
                ];
            }
        } catch (\Exception $e) {
            // Log erro
        }
        
        return [];
    }

    /**
     * Busca concorrentes diretos
     */
    private function getDirectCompetitors(array $myProduct, array $context): array
    {
        try {
            $categoryId = $myProduct['category_id'];
            $priceRange = $this->getPriceRange($myProduct['price'], $context['price_tolerance'] ?? 0.3);
            
            $params = [
                'category' => $categoryId,
                'price' => "{$priceRange['min']}-{$priceRange['max']}",
                'limit' => 20,
                'sort' => 'sold_quantity_desc'
            ];
            
            // Extrai keywords do título para busca mais específica
            $keywords = $this->extractBasicKeywords($myProduct['title']);
            if (!empty($keywords)) {
                $params['q'] = implode(' ', array_slice($keywords, 0, 3));
            }
            
            $response = $this->mlClient->get('/sites/MLB/search', $params);
            
            $competitors = [];
            foreach ($response['results'] ?? [] as $item) {
                if ($item['id'] !== $myProduct['id']) {
                    $competitors[] = [
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'price' => $item['price'],
                        'sold_quantity' => $item['sold_quantity'] ?? 0,
                        'attributes' => $item['attributes'] ?? [],
                        'relevance_score' => $this->calculateRelevanceScore($myProduct, $item)
                    ];
                }
            }
            
            // Ordena por relevância
            usort($competitors, function($a, $b) {
                return $b['relevance_score'] <=> $a['relevance_score'];
            });
            
            return array_slice($competitors, 0, 10); // Top 10 concorrentes
            
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Extrai keywords do produto
     */
    private function extractProductKeywords(array $product): array
    {
        $keywords = [];
        
        // 1. Keywords do título
        $titleKeywords = $this->extractBasicKeywords($product['title']);
        $keywords = array_merge($keywords, $titleKeywords);
        
        // 2. Keywords dos atributos
        foreach ($product['attributes'] as $attr) {
            if (isset($attr['value_name'])) {
                $attrKeywords = $this->extractBasicKeywords($attr['value_name']);
                $keywords = array_merge($keywords, $attrKeywords);
            }
        }
        
        // 3. Keywords da descrição (limitado)
        if (!empty($product['description'])) {
            $descKeywords = $this->extractBasicKeywords(substr($product['description'], 0, 500));
            $keywords = array_merge($keywords, $descKeywords);
        }
        
        // Remove duplicatas e filtra
        $keywords = array_unique($keywords);
        $keywords = array_filter($keywords, function($keyword) {
            return strlen($keyword) > 2 && !in_array(strtolower($keyword), $this->getStopWords());
        });
        
        return array_values($keywords);
    }

    /**
     * Extrai keywords dos concorrentes
     */
    private function extractCompetitorKeywords(array $competitors): array
    {
        $allKeywords = [];
        
        foreach ($competitors as $competitor) {
            $keywords = $this->extractProductKeywords($competitor);
            $allKeywords = array_merge($allKeywords, $keywords);
        }
        
        return array_unique($allKeywords);
    }

    /**
     * Realiza análise de lacunas
     */
    private function performGapAnalysis(array $myKeywords, array $competitorKeywords): array
    {
        $myKeywordsLower = array_map('strtolower', $myKeywords);
        $competitorKeywordsLower = array_map('strtolower', $competitorKeywords);
        
        // Lacunas (o que concorrentes têm e eu não)
        $missingKeywords = array_diff($competitorKeywordsLower, $myKeywordsLower);
        
        // Vantagens (o que eu tenho e concorrentes não)
        $uniqueKeywords = array_diff($myKeywordsLower, $competitorKeywordsLower);
        
        // Overlap (em comum)
        $overlapKeywords = array_intersect($myKeywordsLower, $competitorKeywordsLower);
        
        // Análise de importância das lacunas
        $keywordImportance = $this->calculateKeywordImportance($missingKeywords, $competitorKeywords);
        
        return [
            'missing_keywords' => array_values($missingKeywords),
            'unique_keywords' => array_values($uniqueKeywords),
            'overlap_keywords' => array_values($overlapKeywords),
            'gap_severity' => [
                'critical' => $keywordImportance['critical'],
                'high' => $keywordImportance['high'],
                'medium' => $keywordImportance['medium'],
                'low' => $keywordImportance['low']
            ],
            'gap_score' => $this->calculateGapScore(count($missingKeywords), count($competitorKeywords)),
            'opportunity_score' => $this->calculateOpportunityScore($missingKeywords, $keywordImportance)
        ];
    }

    /**
     * Análise semântica com IA
     */
    private function analyzeSemanticGaps(array $myProduct, array $competitors): array
    {
        $competitorSample = array_slice($competitors, 0, 5);
        $competitorText = implode(' ', array_column($competitorSample, 'title'));
        
        $prompt = "Análise semântica avançada para identificar lacunas ocultas:

MEU PRODUTO:
Título: {$myProduct['title']}
Atributos: " . json_encode($myProduct['attributes'], JSON_UNESCAPED_UNICODE) . "

CONCORRENTES (amostra):
{$competitorText}

Analise e retorne JSON:
{
    \"semantic_gaps\": [\"lacunas semânticas identificadas\"],
    \"conceptual_opportunities\": [\"oportunidades conceituais\"],
    \"latent_intent\": [\"intenções latentes não exploradas\"],
    \"contextual_keywords\": [\"keywords contextuais ausentes\"],
    \"semantic_clusters_missing\": [\"agrupamentos semânticos faltando\"],
    \"user_intent_gaps\": [\"lacunas de intenção do usuário\"],
    \"semantic_score_gap\": (diferença percentual),
    \"recommendations\": [\"recomendações semânticas específicas\"]
}";

        $response = $this->ai->chatJSON($prompt, [
            'cache_ttl' => 3600,
            'temperature' => 0.7
        ]);
        
        return $response['success'] ? $response['data'] : [];
    }

    /**
     * Encontra oportunidades de cauda longa
     */
    private function findLongTailOpportunities(array $myProduct, array $competitors): array
    {
        // Combina informações para análise
        $context = [
            'product' => $myProduct,
            'competitors' => array_slice($competitors, 0, 3)
        ];
        
        $prompt = "Identifique oportunidades de palavras-chave de cauda longa:

PRODUTO: {$myProduct['title']}
CATEGORIA: {$myProduct['category_id']}
CONCORRENTES: " . json_encode(array_column($competitors, 'title'), JSON_UNESCAPED_UNICODE) . "

Retorne JSON com oportunidades de cauda longa:
{
    \"long_tail_keywords\": [
        {
            \"keyword\": \"keyword de cauda longa\",
            \"search_intent\": \"intenção de busca\",
            \"competition_level\": \"alta/média/baixa\",
            \"estimated_traffic\": \"estimativa de tráfego\",
            \"conversion_potential\": \"potencial de conversão\"
        }
    ],
    \"question_keywords\": [\"keywords baseadas em perguntas\"],
    \"local_opportunities\": [\"oportunidades locais se aplicável\"],
    \"seasonal_opportunities\": [\"oportunidades sazonais\"],
    \"niche_opportunities\": [oportunidades de nicho],
    \"implementation_priority\": \"prioridade de implementação\"
}";

        $response = $this->ai->chatJSON($prompt, [
            'cache_ttl' => 7200,
            'temperature' => 0.8
        ]);
        
        return $response['success'] ? $response['data'] : [];
    }

    /**
     * Gera insights acionáveis
     */
    private function generateActionableInsights(array $gapAnalysis, array $semanticGaps, array $longTail): array
    {
        $insights = [];
        
        // Insights das lacunas principais
        if (!empty($gapAnalysis['gap_severity']['critical'])) {
            $insights[] = [
                'type' => 'critical_gap',
                'priority' => 'immediate',
                'action' => 'Adicionar keywords críticas ausentes: ' . implode(', ', array_slice($gapAnalysis['gap_severity']['critical'], 0, 3)),
                'expected_impact' => 'Alto - aumento significativo de visibilidade'
            ];
        }
        
        // Insights semânticos
        if (!empty($semanticGaps['semantic_gaps'])) {
            $insights[] = [
                'type' => 'semantic_opportunity',
                'priority' => 'high',
                'action' => 'Expandir semanticamente com: ' . implode(', ', array_slice($semanticGaps['semantic_gaps'], 0, 3)),
                'expected_impact' => 'Médio - melhora relevância semântica'
            ];
        }
        
        // Insights de cauda longa
        if (!empty($longTail['long_tail_keywords'])) {
            $insights[] = [
                'type' => 'long_tail',
                'priority' => 'medium',
                'action' => 'Implementar keywords de cauda longa específicas',
                'expected_impact' => 'Médio - captura tráfego qualificado'
            ];
        }
        
        return $insights;
    }

    /**
     * Estima impacto das otimizações
     */
    private function estimateImpact(array $gapAnalysis): array
    {
        $gapScore = $gapAnalysis['gap_score'] ?? 0;
        $opportunityScore = $gapAnalysis['opportunity_score'] ?? 0;
        
        $estimatedImprovement = [
            'visibility_increase' => min(50, $gapScore * 0.8),
            'traffic_increase' => min(40, $opportunityScore * 0.6),
            'conversion_improvement' => min(25, $opportunityScore * 0.4),
            'ranking_improvement' => min(30, $gapScore * 0.7)
        ];
        
        return [
            'overall_impact_score' => ($gapScore + $opportunityScore) / 2,
            'estimated_improvements' => $estimatedImprovement,
            'implementation_effort' => $gapScore > 50 ? 'high' : 'medium',
            'time_to_results' => '2-4 semanas'
        ];
    }

    /**
     * Métodos auxiliares
     */
    private function extractBasicKeywords(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', trim($text));
        
        return array_filter($words, function($word) {
            return strlen($word) > 2 && !in_array($word, $this->getStopWords());
        });
    }

    private function getStopWords(): array
    {
        return ['de', 'da', 'do', 'em', 'para', 'com', 'sem', 'a', 'o', 'as', 'os', 'e', 'ou', 'um', 'uma', 'uns', 'umas'];
    }

    private function getPriceRange(float $price, float $tolerance): array
    {
        $min = $price * (1 - $tolerance);
        $max = $price * (1 + $tolerance);
        return ['min' => round($min, 2), 'max' => round($max, 2)];
    }

    private function calculateRelevanceScore(array $myProduct, array $competitor): float
    {
        $score = 0;
        
        // Similaridade de preço
        $priceDiff = abs($myProduct['price'] - $competitor['price']) / $myProduct['price'];
        $score += (1 - $priceDiff) * 30;
        
        // Similaridade de vendas
        if (isset($competitor['sold_quantity'])) {
            $score += min($competitor['sold_quantity'] / 100, 1) * 40;
        }
        
        // Score base de relevância
        $score += 30;
        
        return $score;
    }

    private function calculateKeywordImportance(array $missingKeywords, array $competitorKeywords): array
    {
        $frequency = array_count_values($competitorKeywords);
        
        $critical = [];
        $high = [];
        $medium = [];
        $low = [];
        
        foreach ($missingKeywords as $keyword) {
            $freq = $frequency[$keyword] ?? 0;
            
            if ($freq >= 8) $critical[] = $keyword;
            elseif ($freq >= 5) $high[] = $keyword;
            elseif ($freq >= 3) $medium[] = $keyword;
            else $low[] = $keyword;
        }
        
        return compact('critical', 'high', 'medium', 'low');
    }

    private function calculateGapScore(int $missingCount, int $totalCompetitor): int
    {
        if ($totalCompetitor === 0) return 0;
        return min(100, ($missingCount / $totalCompetitor) * 100);
    }

    private function calculateOpportunityScore(array $missingKeywords, array $importance): int
    {
        $criticalCount = count($importance['critical'] ?? []);
        $highCount = count($importance['high'] ?? []);
        
        return min(100, ($criticalCount * 20) + ($highCount * 10));
    }

    private function getTopProductsByCategory(string $category, array $filters): array
    {
        // Implementação para buscar top produtos
        return [];
    }

    private function identifySuccessPatterns(array $productKeywords, array $products): array
    {
        // Análise de padrões de sucesso
        return [];
    }

    private function findMissingOpportunities(array $keywordFrequency, string $category): array
    {
        // Identifica oportunidades faltantes
        return [];
    }

    private function identifyTrendingKeywords(string $category): array
    {
        // Identifica keywords em tendência
        return [];
    }

    private function generateCategoryRecommendations(array $patterns, array $frequency): array
    {
        // Gera recomendações para a categoria
        return [];
    }

    private function getPreviousAnalysis(string $productId): array
    {
        // Busca análise anterior (cache/banco)
        return [];
    }

    private function detectChanges(array $current, array $previous): array
    {
        // Detecta mudanças entre análises
        return [];
    }

    private function calculateTrend(string $productId, array $current, array $previous): string
    {
        // Calcula tendência
        return 'stable';
    }

    private function generateAlerts(array $current, array $previous): array
    {
        // Gera alertas
        return [];
    }

    private function generateMonitoringSummary(array $monitoringData): array
    {
        // Gera resumo do monitoramento
        return [];
    }
}