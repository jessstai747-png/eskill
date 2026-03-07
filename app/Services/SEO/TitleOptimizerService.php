<?php

declare(strict_types=1);

namespace App\Services\SEO;

use App\Services\MercadoLivreClient;
use App\Services\SEO\AIClient;

/**
 * Serviço especializado em otimização de títulos com integração direta ML
 * Focado em lacunas visíveis/ocultas e estratégias semânticas
 */
class TitleOptimizerService
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
     * Análise completa de título com API ML
     */
    public function analyzeTitle(string $title, array $context = []): array
    {
        $category = $context['category'] ?? '';
        $itemId = $context['item_id'] ?? '';

        // 1. Busca produtos similares via API ML
        $similarProducts = $this->getSimilarProducts($title, $category);

        // 2. Análise com IA integrando dados ML
        $analysis = $this->performTitleAnalysis($title, $context, $similarProducts);

        // 3. Identificação de lacunas
        $gaps = $this->identifyTitleGaps($title, $similarProducts);

        return [
            'success' => true,
            'title' => $title,
            'analysis' => $analysis,
            'gaps' => $gaps,
            'similar_products' => array_slice($similarProducts, 0, 5), // Top 5
            'recommendations' => $this->generateTitleRecommendations($title, $analysis, $gaps)
        ];
    }

    /**
     * Gera títulos otimizados com estratégias semânticas
     */
    public function generateOptimizedTitles(string $title, array $context = []): array
    {
        $category = $context['category'] ?? '';
        $brand = $context['brand'] ?? '';
        $keywords = $context['target_keywords'] ?? [];

        // Busca tendências e sugestões ML
        $mlSuggestions = $this->getMLTitleSuggestions($title, $category);

        // Análise semântica
        $semanticAnalysis = $this->performSemanticAnalysis($title, $keywords, $mlSuggestions);

        // Geração de títulos
        $prompt = "Você é especialista em títulos para Mercado Livre. Use estratégias semânticas e de cauda longa.

TÍTULO ORIGINAL: {$title}
CATEGORIA: {$category}
MARCA: {$brand}
KEYWORDS: " . implode(', ', $keywords) . "
SUGESTÕES ML: " . json_encode($mlSuggestions, JSON_UNESCAPED_UNICODE) . "
ANÁLISE SEMÂNTICA: " . json_encode($semanticAnalysis, JSON_UNESCAPED_UNICODE) . "

Gere 5 títulos otimizados usando:
1. Semântica LAT (termos relacionados)
2. Cauda longa específica
3. Intenção de compra clara
4. Atributos diferenciais
5. Palavras de alta conversão

Retorne JSON:
{
    \"optimized_titles\": [
        {
            \"title\": \"título otimizado\",
            \"strategy\": \"semântica/cauda_longa/mix\",
            \"keywords_included\": [\"keywords presentes\"],
            \"character_count\": número,
            \"improvement_reason\": \"por que é melhor\"
        }
    ],
    \"semantic_keywords\": [\"descobertas semânticas\"],
    \"long_tail_opportunities\": [\"oportunidades de cauda longa\"],
    \"missing_attributes\": [\"atributos que poderiam incluir\"],
    \"competitive_advantage\": \"vantagem sobre concorrentes\"
}";

        $response = $this->ai->chatJSON($prompt, [
            'temperature' => 0.8,
            'cache_ttl' => 3600
        ]);

        return $response['success'] ? $response['data'] : ['success' => false, 'error' => $response['error']];
    }

    /**
     * Preenche campo modelo automaticamente
     */
    public function generateModelAttribute(string $title, array $productData = []): array
    {
        $category = $productData['category'] ?? '';
        $brand = $productData['brand'] ?? '';
        $attributes = $productData['attributes'] ?? [];

        // Análise do produto para extração de modelo
        $prompt = "Extraia o modelo do produto e sugira otimizações:

TÍTULO: {$title}
CATEGORIA: {$category}
MARCA: {$brand}
ATRIBUTOS: " . json_encode($attributes, JSON_UNESCAPED_UNICODE) . "

Analise e retorne JSON:
{
    \"current_model\": \"modelo extraído do título (se existir)\",
    \"suggested_model\": \"modelo otimizado\",
    \"model_variations\": [\"variações possíveis\"],
    \"extraction_confidence\": (0-100),
    \"brand_model_pattern\": \"padrão marca-modelo identificado\",
    \"missing_model_info\": \"informações de modelo faltando\",
    \"seo_impact\": \"impacto SEO do modelo\",
    \"competitor_models\": [\"modelos que concorrentes usam\"],
    \"recommended_action\": \"ação recomendada para campo modelo\"
}";

        $response = $this->ai->chatJSON($prompt, [
            'cache_ttl' => 7200
        ]);

        // Enriquece com dados da API ML
        if ($response['success']) {
            $mlModels = $this->getCompetitorModels($category, $brand);
            $response['data']['ml_insights'] = $mlModels;
        }

        return $response['success'] ? $response['data'] : ['success' => false, 'error' => $response['error']];
    }

    /**
     * Identifica lacunas no título
     */
    private function identifyTitleGaps(string $title, array $competitors): array
    {
        $competitorTitles = array_column($competitors, 'title');
        $competitorKeywords = [];

        // Extrai keywords dos concorrentes
        foreach ($competitorTitles as $compTitle) {
            $keywords = $this->extractKeywords($compTitle);
            $competitorKeywords = array_merge($competitorKeywords, $keywords);
        }

        $myKeywords = $this->extractKeywords($title);
        $missingKeywords = array_diff($competitorKeywords, $myKeywords);

        return [
            'visible_gaps' => [
                'missing_keywords' => array_unique($missingKeywords),
                'character_optimization' => mb_strlen($title) > 60 ? 'too_long' : (mb_strlen($title) < 45 ? 'too_short' : 'optimal'),
                'brand_position' => strpos($title, $brand ?? '') !== false ? 'present' : 'missing'
            ],
            'hidden_gaps' => [
                'semantic_gaps' => $this->findSemanticGaps($title, $competitorTitles),
                'intent_gaps' => $this->analyzeSearchIntent($title, $competitorTitles),
                'attribute_gaps' => $this->findMissingAttributes($title, $competitors)
            ],
            'opportunity_score' => $this->calculateOpportunityScore($title, $competitors)
        ];
    }

    /**
     * Busca produtos similares via API ML
     */
    private function getSimilarProducts(string $title, string $category): array
    {
        try {
            // Extrai keywords principais do título
            $keywords = $this->extractKeywords($title);
            $searchQuery = implode(' ', array_slice($keywords, 0, 3)); // Primeiras 3 keywords

            $params = [
                'q' => $searchQuery,
                'category' => $category,
                'limit' => 20,
                'sort' => 'relevance'
            ];

            $response = $this->mlClient->get('/sites/MLB/search', $params);

            if (isset($response['results'])) {
                return array_map(function ($item) {
                    return [
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'price' => $item['price'],
                        'sold_quantity' => $item['sold_quantity'] ?? 0,
                        'available_quantity' => $item['available_quantity'] ?? 0,
                        'attributes' => $item['attributes'] ?? []
                    ];
                }, $response['results']);
            }
        } catch (\Exception $e) {
            // Log erro mas continua com array vazio
        }

        return [];
    }

    /**
     * Análise de título com IA
     */
    private function performTitleAnalysis(string $title, array $context, array $competitors): array
    {
        $competitorSample = array_slice($competitors, 0, 3);
        $competitorJson = json_encode($competitorSample, JSON_UNESCAPED_UNICODE);

        $prompt = "Analise este título para Mercado Livre comparando com concorrentes:

TÍTULO: {$title}
CONTEXTO: " . json_encode($context, JSON_UNESCAPED_UNICODE) . "
TOP CONCORRENTES: {$competitorJson}

Retorne JSON de análise:
{
    \"score\": (0-100),
    \"strengths\": [\"pontos fortes\"],
    \"weaknesses\": [\"pontos fracos\"],
    \"keyword_density\": \"análise de densidade\",
    \"readability_score\": (0-100),
    \"seo_compliance\": {
        \"length_ok\": boolean,
        \"has_brand\": boolean,
        \"has_main_keywords\": boolean,
        \"no_forbidden_words\": boolean
    },
    \"competitive_position\": \"posição vs concorrentes\",
    \"improvement_potential\": (0-100)
}";

        $response = $this->ai->chatJSON($prompt, ['cache_ttl' => 1800]);

        return $response['success'] ? $response['data'] : ['score' => 50];
    }

    /**
     * Análise semântica avançada
     */
    private function performSemanticAnalysis(string $title, array $keywords, array $mlSuggestions): array
    {
        $prompt = "Análise semântica profunda para otimização:

TÍTULO: {$title}
KEYWORDS ALVO: " . implode(', ', $keywords) . "
SUGESTÕES ML: " . json_encode($mlSuggestions, JSON_UNESCAPED_UNICODE) . "

Retorne JSON semântico:
{
    \"latent_semantic_keywords\": [\"keywords LAT identificadas\"],
    \"semantic_clusters\": [\"agrupamentos semânticos\"],
    \"intent_signals\": [\"sinais de intenção detectados\"],
    \"conceptual_gaps\": [\"lacunas conceituais\"],
    \"semantic_density\": (0-100),
    \"topic_relevance\": (0-100),
    \"semantic_opportunities\": [\"oportunidades semânticas\"]
}";

        $response = $this->ai->chatJSON($prompt, ['cache_ttl' => 3600]);

        return $response['success'] ? $response['data'] : [];
    }

    /**
     * Gera recomendações finais
     */
    private function generateTitleRecommendations(string $title, array $analysis, array $gaps): array
    {
        $recommendations = [];

        // Recomendações baseadas na análise
        if (($analysis['score'] ?? 0) < 70) {
            $recommendations[] = [
                'type' => 'optimization',
                'priority' => 'high',
                'action' => 'Título precisa de otimização imediata',
                'reason' => 'Score abaixo de 70'
            ];
        }

        // Recomendações baseadas nas lacunas
        if (!empty($gaps['visible_gaps']['missing_keywords'])) {
            $recommendations[] = [
                'type' => 'keywords',
                'priority' => 'high',
                'action' => 'Incluir keywords ausentes: ' . implode(', ', array_slice($gaps['visible_gaps']['missing_keywords'], 0, 3)),
                'reason' => 'Keywords presentes em concorrentes'
            ];
        }

        // Recomendações semânticas
        if (!empty($gaps['hidden_gaps']['semantic_gaps'])) {
            $recommendations[] = [
                'type' => 'semantic',
                'priority' => 'medium',
                'action' => 'Considerar termos semânticos relacionados',
                'reason' => 'Oportunidades de expansão semântica'
            ];
        }

        return $recommendations;
    }

    /**
     * Extrai keywords de um texto
     */
    private function extractKeywords(string $text): array
    {
        // Remove palavras irrelevantes e extrai keywords
        $stopWords = ['de', 'da', 'do', 'em', 'para', 'com', 'sem', 'a', 'o', 'as', 'os', 'e', 'ou'];
        $words = preg_split('/[\s,\-\/]+/', mb_strtolower(trim($text)));

        return array_filter($words, function ($word) use ($stopWords) {
            return mb_strlen($word) > 2 && !in_array($word, $stopWords);
        });
    }

    /**
     * Encontra lacunas semânticas
     */
    private function findSemanticGaps(string $title, array $competitorTitles): array
    {
        // Implementação simplificada - poderia usar NLP mais avançado
        $gaps = [];

        foreach ($competitorTitles as $compTitle) {
            $compWords = $this->extractKeywords($compTitle);
            $myWords = $this->extractKeywords($title);
            $differences = array_diff($compWords, $myWords);
            $gaps = array_merge($gaps, $differences);
        }

        return array_unique($gaps);
    }

    /**
     * Analisa intenção de busca
     */
    private function analyzeSearchIntent(string $title, array $competitorTitles): array
    {
        $intentGaps = [];

        // Palavras que indicam intenção
        $intentWords = [
            'comprar' => 'transacional',
            'preço' => 'comercial',
            'barato' => 'preço',
            'promoção' => 'oferta',
            'original' => 'autenticidade'
        ];

        foreach ($intentWords as $word => $intent) {
            $hasInCompetitors = 0;
            foreach ($competitorTitles as $compTitle) {
                if (stripos($compTitle, $word) !== false) {
                    $hasInCompetitors++;
                }
            }

            if ($hasInCompetitors > 0 && stripos($title, $word) === false) {
                $intentGaps[] = $word;
            }
        }

        return $intentGaps;
    }

    /**
     * Encontra atributos ausentes
     */
    private function findMissingAttributes(string $title, array $competitors): array
    {
        $missingAttributes = [];

        // Atributos comuns que devem estar no título
        $commonAttributes = ['cor', 'tamanho', 'capacidade', 'modelo', 'garantia', 'voltagem'];

        foreach ($competitors as $competitor) {
            foreach ($competitor['attributes'] ?? [] as $attr) {
                $value = $attr['value_name'] ?? '';
                if (stripos($title, $value) === false && mb_strlen($value) > 1) {
                    $missingAttributes[] = $value;
                }
            }
        }

        return array_unique(array_slice($missingAttributes, 0, 5));
    }

    /**
     * Calcula score de oportunidade
     */
    private function calculateOpportunityScore(string $title, array $competitors): int
    {
        $score = 50; // Base

        // Fatores que aumentam oportunidade
        if (mb_strlen($title) < 45 || mb_strlen($title) > 60) $score += 15;
        if (count($this->extractKeywords($title)) < 3) $score += 20;
        if (!preg_match('/[0-9]/', $title)) $score += 10; // Especificidades numéricas

        return min(100, $score);
    }

    /**
     * Busca sugestões de títulos da API ML (trends + autocomplete + top sellers)
     */
    private function getMLTitleSuggestions(string $title, string $category): array
    {
        $trendingKeywords = [];
        $popularAttributes = [];
        $recommendedPatterns = [];

        try {
            // 1. Buscar tendências da categoria
            if (!empty($category)) {
                $trends = $this->mlClient->getTrends($category);
                if (!empty($trends)) {
                    foreach ($trends as $trend) {
                        $keyword = $trend['keyword'] ?? '';
                        if (!empty($keyword)) {
                            $trendingKeywords[] = [
                                'keyword' => $keyword,
                                'volume' => $trend['volume'] ?? null,
                                'source' => 'trends',
                            ];
                        }
                    }
                }
            }

            // 2. Buscar autocomplete para palavras-chave do título
            $titleWords = $this->extractKeywords($title);
            $mainKeyword = implode(' ', array_slice($titleWords, 0, 3));
            if (!empty($mainKeyword)) {
                $autocompleteSuggestions = $this->mlClient->getAutocompleteSuggestions(
                    $mainKeyword,
                    $category ?: null
                );
                if (!empty($autocompleteSuggestions)) {
                    foreach (array_slice($autocompleteSuggestions, 0, 10) as $suggestion) {
                        $text = is_array($suggestion) ? ($suggestion['q'] ?? ($suggestion['suggested_query'] ?? '')) : (string)$suggestion;
                        if (!empty($text)) {
                            $trendingKeywords[] = [
                                'keyword' => $text,
                                'volume' => null,
                                'source' => 'autocomplete',
                            ];
                        }
                    }
                }
            }

            // 3. Analisar padrões de títulos mais vendidos
            $searchParams = ['q' => $mainKeyword, 'sort' => 'relevance', 'limit' => 10];
            if (!empty($category)) {
                $searchParams['category'] = $category;
            }
            $searchResults = $this->mlClient->searchItems($searchParams, 3600);
            $results = $searchResults['results'] ?? [];

            foreach ($results as $item) {
                $itemTitle = $item['title'] ?? '';
                if (empty($itemTitle)) {
                    continue;
                }

                // Extrair atributos populares dos resultados
                foreach ($item['attributes'] ?? [] as $attr) {
                    $name = $attr['name'] ?? '';
                    $value = $attr['value_name'] ?? '';
                    if (!empty($name) && !empty($value) && mb_strlen($value) > 1) {
                        $popularAttributes[$name] = $popularAttributes[$name] ?? [];
                        $popularAttributes[$name][] = $value;
                    }
                }

                // Identificar padrões de título (termos recorrentes nos top results)
                $words = $this->extractKeywords($itemTitle);
                foreach ($words as $word) {
                    if (mb_strlen($word) > 2) {
                        $recommendedPatterns[$word] = ($recommendedPatterns[$word] ?? 0) + 1;
                    }
                }
            }

            // Consolidar atributos populares (valores mais comuns)
            $consolidatedAttributes = [];
            foreach ($popularAttributes as $name => $values) {
                $freq = array_count_values($values);
                arsort($freq);
                $consolidatedAttributes[] = [
                    'attribute' => $name,
                    'top_values' => array_slice(array_keys($freq), 0, 3),
                    'frequency' => reset($freq),
                ];
            }

            // Ordenar padrões recomendados por frequência
            arsort($recommendedPatterns);
            $topPatterns = array_slice(array_keys($recommendedPatterns), 0, 15);
        } catch (\Exception $e) {
            // Falha silenciosa — retornar o que já foi coletado
        }

        return [
            'trending_keywords' => array_slice($trendingKeywords, 0, 20),
            'popular_attributes' => $consolidatedAttributes ?? [],
            'recommended_patterns' => $topPatterns ?? [],
        ];
    }

    /**
     * Busca modelos de concorrentes via API ML
     */
    private function getCompetitorModels(string $category, string $brand): array
    {
        if (empty($category) && empty($brand)) {
            return [];
        }

        try {
            $searchParams = ['sort' => 'sold_quantity_desc', 'limit' => 20];
            if (!empty($category)) {
                $searchParams['category'] = $category;
            }
            if (!empty($brand)) {
                $searchParams['q'] = $brand;
            }

            $searchResults = $this->mlClient->searchItems($searchParams, 3600);
            $results = $searchResults['results'] ?? [];

            $models = [];
            $titlePatterns = [];

            foreach ($results as $item) {
                $itemTitle = $item['title'] ?? '';

                // Extrair atributo MODEL dos resultados
                foreach ($item['attributes'] ?? [] as $attr) {
                    $attrId = strtoupper($attr['id'] ?? '');
                    if (in_array($attrId, ['MODEL', 'MODELO', 'LINE'])) {
                        $value = $attr['value_name'] ?? '';
                        if (!empty($value) && $value !== 'N/A') {
                            $models[$value] = ($models[$value] ?? 0) + 1;
                        }
                    }
                }

                // Padrões de título dos concorrentes
                if (!empty($itemTitle)) {
                    $titlePatterns[] = [
                        'title' => $itemTitle,
                        'sold_quantity' => $item['sold_quantity'] ?? 0,
                    ];
                }
            }

            // Ordenar modelos por frequência
            arsort($models);

            return [
                'popular_models' => array_slice(array_keys($models), 0, 10),
                'model_frequency' => array_slice($models, 0, 10, true),
                'competitor_titles' => array_slice($titlePatterns, 0, 5),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
}
