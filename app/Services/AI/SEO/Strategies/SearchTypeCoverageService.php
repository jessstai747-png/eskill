<?php

declare(strict_types=1);

namespace App\Services\AI\SEO\Strategies;

use App\Services\MercadoLivreClient;

/**
 * 🔍 E4: Search Type Coverage Service
 * 
 * Garante cobertura para os 5 tipos de busca do Mercado Livre:
 * 
 * 1. GENÉRICA: "bauleto moto" - alto volume, baixa conversão
 * 2. ESPECÍFICA: "bauleto 45 litros" - médio volume, média conversão
 * 3. MARCA: "bauleto givi" - médio volume, alta conversão
 * 4. MODELO: "bauleto honda cg 160" - baixo volume, alta conversão
 * 5. LONG-TAIL: "bauleto moto delivery 45 litros com base" - baixíssimo volume, altíssima conversão
 * 
 * @package App\Services\AI\SEO\Strategies
 */
class SearchTypeCoverageService
{
    private ?int $accountId;
    private ?MercadoLivreClient $client;
    private SynonymExpansionService $synonymService;
    private SemanticScoreService $scoreService;

    /**
     * Tipos de busca com seus pesos e características
     */
    private const SEARCH_TYPES = [
        'generic' => [
            'name' => 'Genérica',
            'weight' => 1.0,
            'volume' => 'high',
            'conversion' => 'low',
            'priority' => 1,
            'example' => 'bauleto moto',
            'min_words' => 1,
            'max_words' => 2
        ],
        'specific' => [
            'name' => 'Específica',
            'weight' => 0.9,
            'volume' => 'medium',
            'conversion' => 'medium',
            'priority' => 2,
            'example' => 'bauleto 45 litros',
            'min_words' => 2,
            'max_words' => 4
        ],
        'brand' => [
            'name' => 'Marca',
            'weight' => 0.85,
            'volume' => 'medium',
            'conversion' => 'high',
            'priority' => 3,
            'example' => 'bauleto givi',
            'min_words' => 2,
            'max_words' => 3
        ],
        'model' => [
            'name' => 'Modelo',
            'weight' => 0.75,
            'volume' => 'low',
            'conversion' => 'high',
            'priority' => 4,
            'example' => 'bauleto honda cg 160',
            'min_words' => 3,
            'max_words' => 5
        ],
        'long_tail' => [
            'name' => 'Long Tail',
            'weight' => 0.6,
            'volume' => 'very_low',
            'conversion' => 'very_high',
            'priority' => 5,
            'example' => 'bauleto moto delivery 45 litros com base',
            'min_words' => 5,
            'max_words' => 10
        ]
    ];

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
        $this->client = $accountId ? new MercadoLivreClient($accountId) : null;
        $this->synonymService = new SynonymExpansionService($accountId);
        $this->scoreService = new SemanticScoreService($accountId);
    }

    /**
     * Analisa a cobertura de tipos de busca de um anúncio
     * 
     * @param array $itemData Dados do anúncio
     * @return array Análise de cobertura
     */
    public function analyzeCoverage(array $itemData): array
    {
        $title = $itemData['title'] ?? '';
        $description = $itemData['description'] ?? '';
        $model = $itemData['model'] ?? '';
        $brand = $itemData['brand'] ?? '';
        $attributes = $itemData['attributes'] ?? [];
        $categoryId = $itemData['category_id'] ?? null;

        // Texto completo para análise
        $fullText = implode(' ', [
            $title,
            $model,
            $description,
            $this->extractAttributeText($attributes)
        ]);

        $coverage = [];
        $totalScore = 0;
        $maxScore = 0;

        foreach (self::SEARCH_TYPES as $type => $config) {
            $maxScore += $config['weight'] * 100;
            
            $typeCoverage = $this->analyzeSearchType(
                $type,
                $config,
                $fullText,
                [
                    'title' => $title,
                    'model' => $model,
                    'brand' => $brand,
                    'category_id' => $categoryId
                ]
            );

            $coverage[$type] = $typeCoverage;
            $totalScore += $typeCoverage['score'] * $config['weight'];
        }

        // Calcular score geral de cobertura
        $overallScore = $maxScore > 0 ? round(($totalScore / $maxScore) * 100) : 0;

        // Identificar gaps e oportunidades
        $gaps = $this->identifyGaps($coverage);
        $opportunities = $this->identifyOpportunities($coverage, $categoryId);

        return [
            'overall_score' => $overallScore,
            'coverage' => $coverage,
            'gaps' => $gaps,
            'opportunities' => $opportunities,
            'recommendations' => $this->generateRecommendations($coverage, $gaps),
            'search_types_info' => self::SEARCH_TYPES
        ];
    }

    /**
     * Gera keywords para cobrir todos os tipos de busca
     * 
     * @param string $baseKeyword Keyword base
     * @param array $productData Dados do produto
     * @param string|null $categoryId ID da categoria
     * @return array Keywords organizadas por tipo
     */
    public function generateCoverageKeywords(
        string $baseKeyword,
        array $productData,
        ?string $categoryId = null
    ): array {
        $brand = $productData['brand'] ?? '';
        $model = $productData['model'] ?? '';
        $specs = $productData['specs'] ?? [];
        $useCase = $productData['use_case'] ?? 'geral';

        $keywords = [];

        // 1. GENÉRICA
        $keywords['generic'] = $this->generateGenericKeywords($baseKeyword, $categoryId);

        // 2. ESPECÍFICA
        $keywords['specific'] = $this->generateSpecificKeywords($baseKeyword, $specs);

        // 3. MARCA
        $keywords['brand'] = $this->generateBrandKeywords($baseKeyword, $brand, $categoryId);

        // 4. MODELO
        $keywords['model'] = $this->generateModelKeywords($baseKeyword, $brand, $model);

        // 5. LONG TAIL
        $keywords['long_tail'] = $this->generateLongTailKeywords(
            $baseKeyword, 
            $brand, 
            $model, 
            $specs, 
            $useCase
        );

        // Adicionar scores semânticos
        if ($categoryId) {
            foreach ($keywords as $type => &$typeKeywords) {
                // scoreWords(array $words, string $title, string $categoryId)
                $scored = $this->scoreService->scoreWords($typeKeywords, $baseKeyword, $categoryId);
                $typeKeywords = $scored['scored_words'] ?? array_map(
                    fn($k) => ['keyword' => $k, 'score' => 0.5],
                    $typeKeywords
                );
            }
        }

        return [
            'base_keyword' => $baseKeyword,
            'keywords_by_type' => $keywords,
            'total_keywords' => array_sum(array_map('count', $keywords)),
            'coverage_potential' => $this->calculateCoveragePotential($keywords)
        ];
    }

    /**
     * Otimiza um anúncio para cobrir todos os tipos de busca
     * 
     * @param array $itemData Dados atuais do anúncio
     * @param array $keywords Keywords a incluir
     * @return array Campos otimizados
     */
    public function optimizeForCoverage(array $itemData, array $keywords): array
    {
        $title = $itemData['title'] ?? '';
        $model = $itemData['model'] ?? '';
        $description = $itemData['description'] ?? '';
        $categoryId = $itemData['category_id'] ?? null;

        $optimized = [
            'title' => $this->optimizeTitle($title, $keywords),
            'model' => $this->optimizeModel($model, $keywords),
            'description' => $this->optimizeDescription($description, $keywords),
            'hidden_keywords' => $this->generateHiddenKeywords($keywords)
        ];

        // Analisar cobertura após otimização
        $newCoverage = $this->analyzeCoverage([
            'title' => $optimized['title']['optimized'],
            'model' => $optimized['model']['optimized'],
            'description' => $optimized['description']['optimized'],
            'category_id' => $categoryId
        ]);

        return [
            'optimized_fields' => $optimized,
            'coverage_before' => $this->analyzeCoverage($itemData)['overall_score'],
            'coverage_after' => $newCoverage['overall_score'],
            'improvement' => $newCoverage['overall_score'] - $this->analyzeCoverage($itemData)['overall_score']
        ];
    }

    /**
     * Classifica uma query de busca por tipo
     */
    public function classifySearchQuery(string $query): array
    {
        $words = preg_split('/\s+/', trim($query));
        $wordCount = count($words);
        $hasBrand = $this->detectBrand($query);
        $hasModel = $this->detectModel($query);
        $hasSpecs = $this->detectSpecs($query);

        $classification = 'generic';
        $confidence = 0;

        // Regras de classificação
        if ($wordCount >= 5 || ($hasSpecs && $hasBrand)) {
            $classification = 'long_tail';
            $confidence = min(100, 60 + ($wordCount * 5));
        } elseif ($hasModel || ($hasBrand && $wordCount >= 3)) {
            $classification = 'model';
            $confidence = $hasModel ? 90 : 70;
        } elseif ($hasBrand) {
            $classification = 'brand';
            $confidence = 85;
        } elseif ($hasSpecs) {
            $classification = 'specific';
            $confidence = 80;
        } else {
            $classification = 'generic';
            $confidence = max(50, 100 - ($wordCount * 10));
        }

        return [
            'query' => $query,
            'type' => $classification,
            'type_name' => self::SEARCH_TYPES[$classification]['name'],
            'confidence' => $confidence,
            'analysis' => [
                'word_count' => $wordCount,
                'has_brand' => $hasBrand,
                'has_model' => $hasModel,
                'has_specs' => $hasSpecs
            ]
        ];
    }

    /**
     * Sugere keywords faltantes para melhorar cobertura
     */
    public function suggestMissingKeywords(
        array $currentCoverage,
        string $baseKeyword,
        ?string $categoryId = null
    ): array {
        $suggestions = [];

        foreach ($currentCoverage['gaps'] as $gap) {
            $type = $gap['type'];
            $config = self::SEARCH_TYPES[$type];

            // Gerar sugestões específicas para o gap
            $typeSuggestions = $this->generateKeywordsForType(
                $type,
                $baseKeyword,
                $categoryId,
                $config['max_words']
            );

            $suggestions[$type] = [
                'type_name' => $config['name'],
                'priority' => $config['priority'],
                'impact' => $gap['impact'],
                'keywords' => $typeSuggestions,
                'placement' => $this->suggestPlacement($type)
            ];
        }

        // Ordenar por prioridade/impacto
        uasort($suggestions, fn($a, $b) => 
            ($b['impact'] <=> $a['impact']) ?: ($a['priority'] <=> $b['priority'])
        );

        return $suggestions;
    }

    // ========================================================================
    // MÉTODOS PRIVADOS - Análise
    // ========================================================================

    private function analyzeSearchType(
        string $type, 
        array $config, 
        string $fullText, 
        array $context
    ): array {
        $score = 0;
        $indicators = [];

        switch ($type) {
            case 'generic':
                $score = $this->scoreGenericCoverage($fullText, $context);
                $indicators = $this->getGenericIndicators($fullText);
                break;
                
            case 'specific':
                $score = $this->scoreSpecificCoverage($fullText);
                $indicators = $this->getSpecificIndicators($fullText);
                break;
                
            case 'brand':
                $score = $this->scoreBrandCoverage($context['brand'], $fullText);
                $indicators = $this->getBrandIndicators($context['brand'], $fullText);
                break;
                
            case 'model':
                $score = $this->scoreModelCoverage($context['model'], $fullText);
                $indicators = $this->getModelIndicators($context['model'], $fullText);
                break;
                
            case 'long_tail':
                $score = $this->scoreLongTailCoverage($fullText, $context);
                $indicators = $this->getLongTailIndicators($fullText);
                break;
        }

        return [
            'type' => $type,
            'name' => $config['name'],
            'score' => min(100, max(0, $score)),
            'status' => $this->getScoreStatus($score),
            'indicators' => $indicators,
            'expected_volume' => $config['volume'],
            'expected_conversion' => $config['conversion']
        ];
    }

    private function scoreGenericCoverage(string $text, array $context): int
    {
        $score = 0;
        $title = $context['title'] ?? '';
        
        // Verificar se título tem termo genérico principal
        $words = $this->tokenize($title);
        if (count($words) >= 2 && count($words) <= 4) {
            $score += 40;
        }
        
        // Verificar sinônimos genéricos
        if ($this->hasGenericSynonyms($text)) {
            $score += 30;
        }
        
        // Verificar se não está muito específico
        if (count($words) <= 6) {
            $score += 30;
        }
        
        return $score;
    }

    private function scoreSpecificCoverage(string $text): int
    {
        $score = 0;
        
        // Verificar especificações numéricas
        if (preg_match('/\d+\s*(litros?|l|mm|cm|kg|w)/i', $text)) {
            $score += 50;
        }
        
        // Verificar características específicas
        $specificTerms = ['capacidade', 'tamanho', 'dimensão', 'peso', 'potência', 'voltagem'];
        foreach ($specificTerms as $term) {
            if (stripos($text, $term) !== false) {
                $score += 10;
            }
        }
        
        return min(100, $score);
    }

    private function scoreBrandCoverage(string $brand, string $text): int
    {
        if (empty($brand)) {
            return 0;
        }
        
        $score = 0;
        
        // Marca presente no texto
        if (stripos($text, $brand) !== false) {
            $score += 70;
        }
        
        // Variações da marca
        $brandVariations = $this->generateBrandVariations($brand);
        foreach ($brandVariations as $variation) {
            if (stripos($text, $variation) !== false) {
                $score += 10;
            }
        }
        
        return min(100, $score);
    }

    private function scoreModelCoverage(string $model, string $text): int
    {
        if (empty($model)) {
            return 0;
        }
        
        $score = 0;
        
        // Modelo presente
        if (stripos($text, $model) !== false) {
            $score += 60;
        }
        
        // Códigos alfanuméricos
        if (preg_match('/[A-Z]{2,}\d+|\d+[A-Z]{2,}/i', $text)) {
            $score += 20;
        }
        
        // Anos de modelo
        if (preg_match('/20[1-2]\d/', $text)) {
            $score += 20;
        }
        
        return min(100, $score);
    }

    private function scoreLongTailCoverage(string $text, array $context): int
    {
        $score = 0;
        $words = $this->tokenize($text);
        
        // Quantidade de palavras-chave
        if (count($words) >= 10) {
            $score += 30;
        }
        
        // Combinação de elementos
        $hasGeneric = $this->hasGenericSynonyms($text);
        $hasBrand = !empty($context['brand']) && stripos($text, $context['brand']) !== false;
        $hasSpecs = preg_match('/\d+\s*(litros?|l|mm|cm)/i', $text);
        $hasUseCase = preg_match('/delivery|viagem|trabalho|profissional|lazer/i', $text);
        
        if ($hasGeneric) $score += 15;
        if ($hasBrand) $score += 15;
        if ($hasSpecs) $score += 20;
        if ($hasUseCase) $score += 20;
        
        return min(100, $score);
    }

    // ========================================================================
    // MÉTODOS PRIVADOS - Geração de Keywords
    // ========================================================================

    private function generateGenericKeywords(string $baseKeyword, ?string $categoryId): array
    {
        $keywords = [$baseKeyword];
        
        if ($categoryId) {
            $synonyms = $this->synonymService->expand($baseKeyword, $categoryId, [
                'levels' => [1],
                'limit_per_level' => 5
            ]);
            
            foreach ($synonyms['synonyms'] ?? [] as $syn) {
                if (is_array($syn)) {
                    $word = $syn['word'] ?? $syn['value'] ?? '';
                } else {
                    $word = (string)$syn;
                }
                if (!empty($word)) {
                    $keywords[] = $word;
                }
            }
        }
        
        return array_unique($keywords);
    }

    private function generateSpecificKeywords(string $baseKeyword, array $specs): array
    {
        $keywords = [];
        
        foreach ($specs as $specKey => $specValue) {
            // Tratar valores que podem ser arrays
            if (is_array($specValue)) {
                $specValue = $specValue['value'] ?? $specValue[0] ?? '';
            }
            if (empty($specValue) || !is_scalar($specValue)) {
                continue;
            }
            
            $keywords[] = "{$baseKeyword} {$specValue}";
            
            // Variações com unidades
            if (is_numeric($specValue)) {
                $units = ['litros', 'l', 'cm', 'mm', 'kg'];
                foreach ($units as $unit) {
                    $keywords[] = "{$baseKeyword} {$specValue}{$unit}";
                    $keywords[] = "{$baseKeyword} {$specValue} {$unit}";
                }
            }
        }
        
        return array_unique($keywords);
    }

    private function generateBrandKeywords(string $baseKeyword, string $brand, ?string $categoryId): array
    {
        if (empty($brand)) {
            return [];
        }
        
        $keywords = [
            "{$baseKeyword} {$brand}",
            "{$brand} {$baseKeyword}",
        ];
        
        // Variações da marca
        $brandVariations = $this->generateBrandVariations($brand);
        foreach ($brandVariations as $variation) {
            $keywords[] = "{$baseKeyword} {$variation}";
        }
        
        return array_unique($keywords);
    }

    private function generateModelKeywords(string $baseKeyword, string $brand, string $model): array
    {
        $keywords = [];
        
        if ($model) {
            $keywords[] = "{$baseKeyword} {$model}";
            
            if ($brand) {
                $keywords[] = "{$baseKeyword} {$brand} {$model}";
                $keywords[] = "{$brand} {$model} {$baseKeyword}";
            }
        }
        
        return array_unique($keywords);
    }

    private function generateLongTailKeywords(
        string $baseKeyword, 
        string $brand, 
        string $model, 
        array $specs,
        string $useCase
    ): array {
        $keywords = [];
        
        // Combinar elementos
        $parts = [$baseKeyword];
        if ($brand) $parts[] = $brand;
        if ($model) $parts[] = $model;
        if (!empty($specs)) $parts[] = implode(' ', array_values($specs));
        
        // Adicionar caso de uso
        $useCases = [
            'profissional' => ['delivery', 'motoboy', 'trabalho'],
            'lazer' => ['viagem', 'passeio', 'turismo'],
            'urbano' => ['cidade', 'dia a dia', 'diário'],
        ];
        
        $cases = $useCases[$useCase] ?? ['uso geral'];
        
        foreach ($cases as $case) {
            $keywords[] = implode(' ', $parts) . " {$case}";
            $keywords[] = "{$baseKeyword} para {$case} " . implode(' ', array_slice($parts, 1));
        }
        
        return array_unique($keywords);
    }

    private function generateKeywordsForType(
        string $type, 
        string $baseKeyword, 
        ?string $categoryId,
        int $maxWords
    ): array {
        switch ($type) {
            case 'generic':
                return $this->generateGenericKeywords($baseKeyword, $categoryId);
            case 'specific':
                return $this->generateSpecificKeywords($baseKeyword, ['tamanho' => 'médio']);
            case 'brand':
                return $this->generateBrandKeywords($baseKeyword, '', $categoryId);
            case 'model':
                return $this->generateModelKeywords($baseKeyword, '', '');
            case 'long_tail':
                return $this->generateLongTailKeywords($baseKeyword, '', '', [], 'geral');
            default:
                return [$baseKeyword];
        }
    }

    // ========================================================================
    // MÉTODOS PRIVADOS - Otimização
    // ========================================================================

    private function optimizeTitle(string $title, array $keywords): array
    {
        $maxLength = 60;
        $optimized = $title;
        
        // Adicionar keywords genéricas no início
        $genericKws = $keywords['generic'] ?? [];
        foreach ($genericKws as $kw) {
            $keyword = is_array($kw) ? $kw['keyword'] : $kw;
            if (stripos($optimized, $keyword) === false) {
                $newTitle = $keyword . ' ' . $optimized;
                if (strlen($newTitle) <= $maxLength) {
                    $optimized = $newTitle;
                    break;
                }
            }
        }
        
        return [
            'original' => $title,
            'optimized' => $optimized,
            'changes' => $title !== $optimized
        ];
    }

    private function optimizeModel(string $model, array $keywords): array
    {
        $maxLength = 255;
        $optimized = $model;
        
        // Adicionar keywords de modelo e específicas
        $modelKws = array_merge(
            $keywords['model'] ?? [],
            $keywords['specific'] ?? []
        );
        
        foreach ($modelKws as $kw) {
            $keyword = is_array($kw) ? $kw['keyword'] : $kw;
            if (stripos($optimized, $keyword) === false) {
                $newModel = trim($optimized . ' ' . $keyword);
                if (strlen($newModel) <= $maxLength) {
                    $optimized = $newModel;
                }
            }
        }
        
        return [
            'original' => $model,
            'optimized' => $optimized,
            'changes' => $model !== $optimized
        ];
    }

    private function optimizeDescription(string $description, array $keywords): array
    {
        $injector = new KeywordInjectorService($this->accountId);
        
        $allKeywords = [];
        foreach ($keywords as $typeKws) {
            // Garantir que typeKws é array
            if (!is_array($typeKws)) {
                continue;
            }
            foreach ($typeKws as $kw) {
                $allKeywords[] = is_array($kw) ? ($kw['keyword'] ?? $kw[0] ?? '') : (string)$kw;
            }
        }
        
        // Filtrar strings vazias
        $allKeywords = array_filter($allKeywords, fn($k) => !empty($k));
        
        return $injector->injectInDescription($description, $allKeywords);
    }

    private function generateHiddenKeywords(array $keywords): string
    {
        $hidden = [];
        
        // Priorizar long-tail para campo oculto (60 chars)
        $longTail = $keywords['long_tail'] ?? [];
        foreach ($longTail as $kw) {
            $keyword = is_array($kw) ? $kw['keyword'] : $kw;
            $hidden[] = $keyword;
        }
        
        $result = implode(' ', $hidden);
        return substr($result, 0, 60);
    }

    // ========================================================================
    // MÉTODOS PRIVADOS - Utilidades
    // ========================================================================

    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        return array_filter(preg_split('/\s+/', $text));
    }

    private function extractAttributeText(array $attributes): string
    {
        $texts = [];
        foreach ($attributes as $attr) {
            if (!empty($attr['value_name'])) {
                $texts[] = $attr['value_name'];
            }
        }
        return implode(' ', $texts);
    }

    private function hasGenericSynonyms(string $text): bool
    {
        $genericTerms = ['bau', 'bauleto', 'bagageiro', 'maleiro', 'caixa', 'moto', 'motocicleta'];
        foreach ($genericTerms as $term) {
            if (stripos($text, $term) !== false) {
                return true;
            }
        }
        return false;
    }

    private function generateBrandVariations(string $brand): array
    {
        $variations = [];
        $variations[] = mb_strtoupper($brand);
        $variations[] = mb_strtolower($brand);
        $lowerBrand = mb_strtolower($brand);
        $variations[] = mb_strtoupper(mb_substr($lowerBrand, 0, 1)) . mb_substr($lowerBrand, 1);
        $variations[] = preg_replace('/[^a-zA-Z0-9]/', '', $brand);
        return array_unique($variations);
    }

    private function detectBrand(string $query): bool
    {
        $commonBrands = ['givi', 'proos', 'protork', 'honda', 'yamaha', 'suzuki', 'shad'];
        foreach ($commonBrands as $brand) {
            if (stripos($query, $brand) !== false) {
                return true;
            }
        }
        return false;
    }

    private function detectModel(string $query): bool
    {
        // Detectar padrões de modelo (letras + números)
        return (bool) preg_match('/[A-Z]{2,}\s*\d+|\d+\s*[A-Z]{2,}|(?:cg|cb|fazer|fan|bros)\s*\d+/i', $query);
    }

    private function detectSpecs(string $query): bool
    {
        return (bool) preg_match('/\d+\s*(litros?|l|mm|cm|kg|w|v)/i', $query);
    }

    private function getScoreStatus(int $score): string
    {
        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'needs_improvement';
        return 'poor';
    }

    private function identifyGaps(array $coverage): array
    {
        $gaps = [];
        foreach ($coverage as $type => $data) {
            if ($data['score'] < 60) {
                $gaps[] = [
                    'type' => $type,
                    'current_score' => $data['score'],
                    'impact' => self::SEARCH_TYPES[$type]['weight'] * (100 - $data['score']) / 100
                ];
            }
        }
        
        usort($gaps, fn($a, $b) => $b['impact'] <=> $a['impact']);
        return $gaps;
    }

    private function identifyOpportunities(array $coverage, ?string $categoryId): array
    {
        $opportunities = [];
        
        foreach ($coverage as $type => $data) {
            if ($data['score'] >= 60 && $data['score'] < 90) {
                $opportunities[] = [
                    'type' => $type,
                    'current_score' => $data['score'],
                    'potential_gain' => 100 - $data['score'],
                    'recommendation' => "Melhorar cobertura {$data['name']} de {$data['score']}% para 90%+"
                ];
            }
        }
        
        return $opportunities;
    }

    private function generateRecommendations(array $coverage, array $gaps): array
    {
        $recommendations = [];
        
        foreach ($gaps as $gap) {
            $type = $gap['type'];
            $config = self::SEARCH_TYPES[$type];
            
            $recommendations[] = [
                'priority' => $config['priority'],
                'type' => $type,
                'message' => "Adicione keywords de busca {$config['name']} (ex: {$config['example']})",
                'impact' => $gap['impact'] > 0.3 ? 'high' : ($gap['impact'] > 0.15 ? 'medium' : 'low')
            ];
        }
        
        usort($recommendations, fn($a, $b) => $a['priority'] <=> $b['priority']);
        return $recommendations;
    }

    private function suggestPlacement(string $type): string
    {
        $placements = [
            'generic' => 'Início do título',
            'specific' => 'Campo MODEL ou descrição',
            'brand' => 'Título após termo genérico',
            'model' => 'Campo MODEL e descrição',
            'long_tail' => 'Campo KEYWORDS oculto e descrição'
        ];
        
        return $placements[$type] ?? 'Descrição';
    }

    private function calculateCoveragePotential(array $keywords): int
    {
        $score = 0;
        foreach (self::SEARCH_TYPES as $type => $config) {
            if (!empty($keywords[$type])) {
                $score += $config['weight'] * 20;
            }
        }
        return min(100, (int) $score);
    }

    private function getGenericIndicators(string $text): array
    {
        $indicators = [];
        $genericTerms = ['bau', 'bauleto', 'bagageiro', 'moto'];
        foreach ($genericTerms as $term) {
            if (stripos($text, $term) !== false) {
                $indicators[] = $term;
            }
        }
        return $indicators;
    }

    private function getSpecificIndicators(string $text): array
    {
        preg_match_all('/\d+\s*(litros?|l|mm|cm|kg)/i', $text, $matches);
        return $matches[0] ?? [];
    }

    private function getBrandIndicators(string $brand, string $text): array
    {
        $indicators = [];
        if ($brand && stripos($text, $brand) !== false) {
            $indicators[] = $brand;
        }
        return $indicators;
    }

    private function getModelIndicators(string $model, string $text): array
    {
        $indicators = [];
        if ($model && stripos($text, $model) !== false) {
            $indicators[] = $model;
        }
        preg_match_all('/[A-Z]{2,}\d+|\d+[A-Z]{2,}/i', $text, $matches);
        $indicators = array_merge($indicators, $matches[0] ?? []);
        return array_unique($indicators);
    }

    private function getLongTailIndicators(string $text): array
    {
        $indicators = [];
        $longTailPatterns = ['delivery', 'viagem', 'trabalho', 'para', 'com', 'universal'];
        foreach ($longTailPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) {
                $indicators[] = $pattern;
            }
        }
        return $indicators;
    }
}
