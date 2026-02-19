<?php

declare(strict_types=1);

namespace App\Services\AI\SEO\Strategies;



/**
 * ⚖️ E5: Field Weight Distribution Service
 * 
 * Distribui keywords pelos campos de acordo com seus pesos de indexação:
 * 
 * - TÍTULO: 100% peso (máx 60 chars) - Keywords principais
 * - MODELO: 70% peso (máx 255 chars) - Keywords secundárias + specs
 * - DESCRIÇÃO: 50% peso - Keywords terciárias + long-tail
 * - KEYWORDS (oculto): 30% peso (máx 60 chars) - Sinônimos não usados
 * 
 * Algoritmo otimiza a distribuição para maximizar indexação total.
 * 
 * @package App\Services\AI\SEO\Strategies
 */
class FieldWeightService
{
    private ?int $accountId;
    private SemanticScoreService $scoreService;
    private SynonymExpansionService $synonymService;

    /**
     * Configuração de pesos por campo
     */
    private const FIELD_WEIGHTS = [
        'title' => [
            'weight' => 1.0,       // 100%
            'max_length' => 60,
            'priority' => 1,
            'keyword_limit' => 4,
            'position' => 'start',
            'description' => 'Peso máximo - Keywords principais no início'
        ],
        'model' => [
            'weight' => 0.7,       // 70%
            'max_length' => 255,
            'priority' => 2,
            'keyword_limit' => 10,
            'position' => 'any',
            'description' => 'Peso alto - Keywords secundárias e especificações'
        ],
        'description' => [
            'weight' => 0.5,       // 50%
            'max_length' => null,  // Sem limite
            'priority' => 3,
            'keyword_limit' => 20,
            'position' => 'distributed',
            'description' => 'Peso médio - Keywords terciárias e long-tail'
        ],
        'keywords' => [
            'weight' => 0.3,       // 30%
            'max_length' => 60,
            'priority' => 4,
            'keyword_limit' => 10,
            'position' => 'any',
            'description' => 'Peso baixo - Sinônimos não usados nos outros campos'
        ]
    ];

    /**
     * Thresholds para score mínimo por campo
     */
    private const FIELD_MIN_SCORES = [
        'title' => 0.8,      // Só keywords de alto score
        'model' => 0.5,      // Score médio-alto
        'description' => 0.3, // Aceita score médio
        'keywords' => 0.1    // Aceita qualquer keyword
    ];

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
        $this->scoreService = new SemanticScoreService($accountId);
        $this->synonymService = new SynonymExpansionService($accountId);
    }

    /**
     * Distribui keywords pelos campos de forma otimizada
     * 
     * @param array $keywords Lista de keywords para distribuir
     * @param string|null $categoryId ID da categoria
     * @param array $currentValues Valores atuais dos campos
     * @return array Distribuição otimizada
     */
    public function distributeKeywords(
        array $keywords, 
        ?string $categoryId = null,
        array $currentValues = []
    ): array {
        // 1. Calcular scores semânticos para todas as keywords
        $scoredKeywords = $this->scoreKeywords($keywords, $categoryId, (string)($currentValues['title'] ?? ''));

        // 2. Ordenar por score (maior primeiro)
        usort($scoredKeywords, fn($a, $b) => $b['score'] <=> $a['score']);

        // 3. Distribuir pelos campos respeitando pesos e limites
        $distribution = $this->allocateToFields($scoredKeywords, $currentValues);

        // 4. Calcular métricas de cobertura
        $metrics = $this->calculateDistributionMetrics($distribution);

        return [
            'distribution' => $distribution,
            'metrics' => $metrics,
            'total_keywords' => count($keywords),
            'allocated_keywords' => $metrics['total_allocated'],
            'unallocated' => $this->getUnallocatedKeywords($scoredKeywords, $distribution),
            'field_weights' => self::FIELD_WEIGHTS
        ];
    }

    /**
     * Analisa a distribuição atual de um anúncio
     */
    public function analyzeCurrentDistribution(array $itemData): array
    {
        $texts = [
            'title' => $itemData['title'] ?? '',
            'model' => $itemData['model'] ?? '',
            'description' => $itemData['description'] ?? '',
            'keywords' => $itemData['keywords'] ?? ''
        ];
        $categoryId = $itemData['category_id'] ?? null;

        // Extrair keywords de cada campo
        $fieldKeywords = [
            'title' => $this->extractKeywords($texts['title']),
            'model' => $this->extractKeywords($texts['model']),
            'description' => $this->extractKeywords($texts['description']),
            'keywords' => $this->extractKeywords($texts['keywords'])
        ];

        // Calcular utilização de cada campo
        $analysis = [];
        foreach (self::FIELD_WEIGHTS as $field => $config) {
            $text = $texts[$field];
            $keywords = $fieldKeywords[$field];
            
            $analysis[$field] = [
                'weight' => $config['weight'],
                'max_length' => $config['max_length'],
                'current_length' => strlen($text),
                'usage_percent' => $config['max_length'] 
                    ? round((strlen($text) / $config['max_length']) * 100, 1) 
                    : null,
                'keyword_count' => count($keywords),
                'keyword_limit' => $config['keyword_limit'],
                'keywords' => $keywords,
                'optimization_status' => $this->getOptimizationStatus($field, $keywords, $config)
            ];
        }

        // Calcular score total de distribuição
        $totalScore = $this->calculateTotalWeightedScore($analysis);

        // Identificar problemas e oportunidades
        $issues = $this->identifyDistributionIssues($analysis);
        $opportunities = $this->identifyOpportunities($analysis);

        return [
            'analysis' => $analysis,
            'total_score' => $totalScore,
            'issues' => $issues,
            'opportunities' => $opportunities,
            'recommendations' => $this->generateDistributionRecommendations($analysis, $issues)
        ];
    }

    /**
     * Calcula um score simples de peso do título
     */
    public function calculateTitleWeight(string $title): float
    {
        $config = self::FIELD_WEIGHTS['title'];
        $maxLength = $config['max_length'] ?? 60;
        $length = strlen($title);
        $usageRatio = $maxLength > 0 ? min(1, $length / $maxLength) : 0;

        $keywords = $this->extractKeywords($title);
        $keywordLimit = $config['keyword_limit'] ?? 4;
        $keywordRatio = $keywordLimit > 0 ? min(1, count($keywords) / $keywordLimit) : 0;

        $score = (($usageRatio + $keywordRatio) / 2) * 100;
        return round($score, 2);
    }

    /**
     * Otimiza a distribuição de um anúncio
     */
    public function optimizeDistribution(
        array $itemData,
        array $additionalKeywords = [],
        ?string $categoryId = null
    ): array {
        $currentTitle = $itemData['title'] ?? '';
        $currentModel = $itemData['model'] ?? '';
        $currentDesc = $itemData['description'] ?? '';
        $currentKeywords = $itemData['keywords'] ?? '';

        // Coletar todas as keywords atuais + adicionais
        $allKeywords = array_merge(
            $this->extractKeywords($currentTitle),
            $this->extractKeywords($currentModel),
            $this->extractKeywords($currentDesc),
            $this->extractKeywords($currentKeywords),
            $additionalKeywords
        );
        $allKeywords = array_unique($allKeywords);

        // Redistribuir
        $distribution = $this->distributeKeywords($allKeywords, $categoryId);

        // Construir campos otimizados
        $optimized = [
            'title' => $this->buildOptimizedField('title', $distribution['distribution']['title']),
            'model' => $this->buildOptimizedField('model', $distribution['distribution']['model']),
            'description' => $this->optimizeDescription($currentDesc, $distribution['distribution']['description']),
            'keywords' => $this->buildOptimizedField('keywords', $distribution['distribution']['keywords'])
        ];

        // Comparar antes/depois
        $beforeScore = $this->analyzeCurrentDistribution($itemData)['total_score'];
        $afterData = [
            'title' => $optimized['title']['value'],
            'model' => $optimized['model']['value'],
            'description' => $optimized['description']['value'],
            'keywords' => $optimized['keywords']['value'],
            'category_id' => $categoryId
        ];
        $afterScore = $this->analyzeCurrentDistribution($afterData)['total_score'];

        return [
            'original' => $itemData,
            'optimized' => $optimized,
            'distribution' => $distribution,
            'score_improvement' => [
                'before' => $beforeScore,
                'after' => $afterScore,
                'improvement' => $afterScore - $beforeScore,
                'improvement_percent' => $beforeScore > 0 
                    ? round((($afterScore - $beforeScore) / $beforeScore) * 100, 1) 
                    : 0
            ]
        ];
    }

    /**
     * Sugere realocação de keywords entre campos
     */
    public function suggestReallocation(array $itemData, ?string $categoryId = null): array
    {
        $analysis = $this->analyzeCurrentDistribution($itemData);
        $suggestions = [];

        // Identificar keywords mal posicionadas
        foreach ($analysis['analysis'] as $field => $fieldData) {
            if ($fieldData['keyword_count'] > $fieldData['keyword_limit']) {
                // Excesso de keywords - sugerir mover para campo de menor peso
                $excess = $fieldData['keyword_count'] - $fieldData['keyword_limit'];
                $targetField = $this->findTargetField($field, $excess);
                
                if ($targetField) {
                    $suggestions[] = [
                        'type' => 'move',
                        'from' => $field,
                        'to' => $targetField,
                        'keywords' => array_slice($fieldData['keywords'], -$excess),
                        'reason' => "Campo {$field} excede limite de {$fieldData['keyword_limit']} keywords"
                    ];
                }
            }
        }

        // Sugerir promoção de keywords importantes
        $keywordsField = $analysis['analysis']['keywords']['keywords'] ?? [];
        if (!empty($keywordsField) && $categoryId) {
            $scored = $this->scoreKeywords($keywordsField, $categoryId);
            foreach ($scored as $kw) {
                if ($kw['score'] >= self::FIELD_MIN_SCORES['title']) {
                    // Keyword de alto score em campo de baixo peso
                    if ($this->hasSpace('title', $analysis['analysis']['title'])) {
                        $suggestions[] = [
                            'type' => 'promote',
                            'from' => 'keywords',
                            'to' => 'title',
                            'keywords' => [$kw['keyword']],
                            'reason' => "Keyword '{$kw['keyword']}' tem score alto ({$kw['score']}) e deve estar no título"
                        ];
                    }
                }
            }
        }

        return [
            'current_analysis' => $analysis,
            'suggestions' => $suggestions,
            'potential_improvement' => $this->calculatePotentialImprovement($suggestions)
        ];
    }

    /**
     * Calcula a eficiência de indexação baseada nos pesos
     */
    public function calculateIndexingEfficiency(array $itemData): array
    {
        $analysis = $this->analyzeCurrentDistribution($itemData);
        
        $efficiency = [];
        $totalWeight = 0;
        $achievedWeight = 0;

        foreach (self::FIELD_WEIGHTS as $field => $config) {
            $fieldAnalysis = $analysis['analysis'][$field] ?? [];
            $usage = $fieldAnalysis['usage_percent'] ?? 0;
            $keywordDensity = isset($fieldAnalysis['keyword_count'], $fieldAnalysis['keyword_limit']) 
                ? min(100, ($fieldAnalysis['keyword_count'] / $fieldAnalysis['keyword_limit']) * 100) 
                : 0;

            // Eficiência = média entre uso de espaço e densidade de keywords
            $fieldEfficiency = ($usage + $keywordDensity) / 2;
            $weightedEfficiency = $fieldEfficiency * $config['weight'];

            $efficiency[$field] = [
                'weight' => $config['weight'] * 100,
                'space_usage' => round($usage, 1),
                'keyword_density' => round($keywordDensity, 1),
                'efficiency' => round($fieldEfficiency, 1),
                'weighted_efficiency' => round($weightedEfficiency, 1)
            ];

            $totalWeight += $config['weight'] * 100;
            $achievedWeight += $weightedEfficiency;
        }

        return [
            'by_field' => $efficiency,
            'overall_efficiency' => $totalWeight > 0 
                ? round(($achievedWeight / $totalWeight) * 100, 1) 
                : 0,
            'max_possible' => 100,
            'room_for_improvement' => $totalWeight > 0 
                ? round(100 - ($achievedWeight / $totalWeight) * 100, 1) 
                : 100
        ];
    }

    /**
     * Retorna a configuração de pesos dos campos
     */
    public function getFieldWeights(): array
    {
        return self::FIELD_WEIGHTS;
    }

    /**
     * Retorna recomendações para maximizar peso de indexação
     */
    public function getWeightMaximizationStrategy(array $itemData): array
    {
        $analysis = $this->analyzeCurrentDistribution($itemData);
        $efficiency = $this->calculateIndexingEfficiency($itemData);
        
        $strategies = [];

        // Priorizar campos de maior peso
        foreach (self::FIELD_WEIGHTS as $field => $config) {
            $fieldEfficiency = $efficiency['by_field'][$field]['efficiency'] ?? 0;
            
            if ($fieldEfficiency < 70) {
                $strategies[] = [
                    'field' => $field,
                    'priority' => $config['priority'],
                    'weight' => $config['weight'] * 100,
                    'current_efficiency' => $fieldEfficiency,
                    'target_efficiency' => 90,
                    'impact' => round($config['weight'] * (90 - $fieldEfficiency), 1),
                    'action' => $this->getFieldOptimizationAction($field, $analysis['analysis'][$field] ?? [])
                ];
            }
        }

        // Ordenar por impacto (campo de maior peso com menor eficiência = maior impacto)
        usort($strategies, fn($a, $b) => $b['impact'] <=> $a['impact']);

        return [
            'current_efficiency' => $efficiency['overall_efficiency'],
            'target_efficiency' => 90,
            'strategies' => $strategies,
            'estimated_improvement' => array_sum(array_column($strategies, 'impact'))
        ];
    }

    // ========================================================================
    // MÉTODOS PRIVADOS
    // ========================================================================

    private function scoreKeywords(array $keywords, ?string $categoryId, string $title = ''): array
    {
        if (!$categoryId || empty($keywords)) {
            return array_map(fn($kw) => ['keyword' => $kw, 'score' => 0.5], $keywords);
        }

        $result = $this->scoreService->scoreWords($keywords, $title, $categoryId);
        return $result['scored_words'] ?? array_map(fn($kw) => ['keyword' => $kw, 'score' => 0.5], $keywords);
    }

    private function allocateToFields(array $scoredKeywords, array $currentValues): array
    {
        $allocation = [
            'title' => ['keywords' => [], 'length' => 0],
            'model' => ['keywords' => [], 'length' => 0],
            'description' => ['keywords' => [], 'length' => 0],
            'keywords' => ['keywords' => [], 'length' => 0]
        ];

        // Inicializar com valores atuais
        foreach ($currentValues as $field => $value) {
            if (isset($allocation[$field])) {
                $allocation[$field]['current_value'] = $value;
                $allocation[$field]['length'] = strlen($value);
            }
        }

        foreach ($scoredKeywords as $kw) {
            $keyword = $kw['keyword'];
            $score = $kw['score'];

            // Tentar alocar no campo de maior peso possível
            $allocated = false;
            foreach (self::FIELD_WEIGHTS as $field => $config) {
                // Verificar se score atende ao mínimo do campo
                if ($score < self::FIELD_MIN_SCORES[$field]) {
                    continue;
                }

                // Verificar limite de keywords
                if (count($allocation[$field]['keywords']) >= $config['keyword_limit']) {
                    continue;
                }

                // Verificar limite de caracteres
                $keywordLength = strlen($keyword) + 1; // +1 para espaço
                if ($config['max_length'] && 
                    $allocation[$field]['length'] + $keywordLength > $config['max_length']) {
                    continue;
                }

                // Verificar se keyword já está alocada
                if ($this->keywordExistsInAllocation($keyword, $allocation)) {
                    break;
                }

                // Alocar
                $allocation[$field]['keywords'][] = [
                    'keyword' => $keyword,
                    'score' => $score
                ];
                $allocation[$field]['length'] += $keywordLength;
                $allocated = true;
                break;
            }
        }

        return $allocation;
    }

    private function keywordExistsInAllocation(string $keyword, array $allocation): bool
    {
        $keyword = mb_strtolower($keyword);
        foreach ($allocation as $field) {
            foreach ($field['keywords'] ?? [] as $kw) {
                if (mb_strtolower($kw['keyword']) === $keyword) {
                    return true;
                }
            }
        }
        return false;
    }

    private function calculateDistributionMetrics(array $distribution): array
    {
        $totalAllocated = 0;
        $totalWeight = 0;
        $achievedWeight = 0;

        foreach (self::FIELD_WEIGHTS as $field => $config) {
            $fieldKeywords = $distribution[$field]['keywords'] ?? [];
            $count = count($fieldKeywords);
            $totalAllocated += $count;

            $maxWeight = $config['weight'] * $config['keyword_limit'];
            $fieldWeight = $config['weight'] * min($count, $config['keyword_limit']);

            $totalWeight += $maxWeight;
            $achievedWeight += $fieldWeight;
        }

        return [
            'total_allocated' => $totalAllocated,
            'total_weight' => round($totalWeight, 2),
            'achieved_weight' => round($achievedWeight, 2),
            'efficiency' => $totalWeight > 0 
                ? round(($achievedWeight / $totalWeight) * 100, 1) 
                : 0
        ];
    }

    private function getUnallocatedKeywords(array $scoredKeywords, array $distribution): array
    {
        $allocated = [];
        foreach ($distribution as $field) {
            foreach ($field['keywords'] ?? [] as $kw) {
                $allocated[] = mb_strtolower($kw['keyword']);
            }
        }

        $unallocated = [];
        foreach ($scoredKeywords as $kw) {
            if (!in_array(mb_strtolower($kw['keyword']), $allocated)) {
                $unallocated[] = $kw;
            }
        }

        return $unallocated;
    }

    private function extractKeywords(string $text): array
    {
        if (empty($text)) return [];
        
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = array_filter(preg_split('/\s+/', $text));
        
        // Filtrar stopwords
        $stopWords = ['para', 'com', 'sem', 'de', 'da', 'do', 'em', 'no', 'na', 
                      'e', 'ou', 'um', 'uma', 'os', 'as', 'que', 'por', 'o', 'a'];
        
        return array_values(array_filter($words, function($w) use ($stopWords) {
            return strlen($w) > 2 && !in_array($w, $stopWords);
        }));
    }

    private function getOptimizationStatus(string $field, array $keywords, array $config): string
    {
        $count = count($keywords);
        $limit = $config['keyword_limit'];

        if ($count >= $limit * 0.8) return 'optimal';
        if ($count >= $limit * 0.5) return 'good';
        if ($count >= $limit * 0.2) return 'needs_improvement';
        return 'poor';
    }

    private function calculateTotalWeightedScore(array $analysis): int
    {
        $score = 0;
        $maxScore = 0;

        foreach (self::FIELD_WEIGHTS as $field => $config) {
            $fieldData = $analysis[$field] ?? [];
            $maxScore += $config['weight'] * 100;

            $keywordRatio = isset($fieldData['keyword_count'], $fieldData['keyword_limit']) 
                ? min(1, $fieldData['keyword_count'] / $fieldData['keyword_limit']) 
                : 0;
            
            $spaceRatio = isset($fieldData['usage_percent']) 
                ? min(1, $fieldData['usage_percent'] / 100) 
                : ($config['max_length'] ? 0 : 1);

            $fieldScore = (($keywordRatio + $spaceRatio) / 2) * $config['weight'] * 100;
            $score += $fieldScore;
        }

        return $maxScore > 0 ? (int) round(($score / $maxScore) * 100) : 0;
    }

    private function identifyDistributionIssues(array $analysis): array
    {
        $issues = [];

        foreach (self::FIELD_WEIGHTS as $field => $config) {
            $fieldData = $analysis[$field] ?? [];

            // Campo subutilizado (menos de 30% do limite)
            if (isset($fieldData['keyword_count']) && 
                $fieldData['keyword_count'] < $config['keyword_limit'] * 0.3) {
                $issues[] = [
                    'type' => 'underutilized',
                    'field' => $field,
                    'severity' => 'high',
                    'message' => "Campo {$field} tem apenas {$fieldData['keyword_count']} de {$config['keyword_limit']} keywords",
                    'weight_lost' => $config['weight'] * ($config['keyword_limit'] - $fieldData['keyword_count'])
                ];
            }

            // Espaço não utilizado em campos com limite
            if ($config['max_length'] && isset($fieldData['usage_percent']) && 
                $fieldData['usage_percent'] < 70) {
                $issues[] = [
                    'type' => 'space_wasted',
                    'field' => $field,
                    'severity' => 'medium',
                    'message' => "Campo {$field} usa apenas {$fieldData['usage_percent']}% do espaço disponível"
                ];
            }
        }

        return $issues;
    }

    private function identifyOpportunities(array $analysis): array
    {
        $opportunities = [];

        // Oportunidade de mover keywords de campos de baixo peso para alto peso
        $keywordsInLowWeight = $analysis['keywords']['keywords'] ?? [];
        if (!empty($keywordsInLowWeight) && 
            $analysis['title']['keyword_count'] < self::FIELD_WEIGHTS['title']['keyword_limit']) {
            $opportunities[] = [
                'type' => 'promotion',
                'description' => 'Mover keywords do campo oculto para o título',
                'impact' => 'high',
                'keywords_available' => count($keywordsInLowWeight),
                'slots_available' => self::FIELD_WEIGHTS['title']['keyword_limit'] - $analysis['title']['keyword_count']
            ];
        }

        return $opportunities;
    }

    private function generateDistributionRecommendations(array $analysis, array $issues): array
    {
        $recommendations = [];

        foreach ($issues as $issue) {
            switch ($issue['type']) {
                case 'underutilized':
                    $recommendations[] = [
                        'priority' => 1,
                        'field' => $issue['field'],
                        'action' => "Adicione mais keywords ao campo {$issue['field']}",
                        'impact' => 'high'
                    ];
                    break;
                case 'space_wasted':
                    $recommendations[] = [
                        'priority' => 2,
                        'field' => $issue['field'],
                        'action' => "Utilize mais caracteres no campo {$issue['field']}",
                        'impact' => 'medium'
                    ];
                    break;
            }
        }

        usort($recommendations, fn($a, $b) => $a['priority'] <=> $b['priority']);
        return $recommendations;
    }

    private function buildOptimizedField(string $field, array $allocation): array
    {
        $config = self::FIELD_WEIGHTS[$field];
        $keywords = array_column($allocation['keywords'] ?? [], 'keyword');
        
        $value = implode(' ', $keywords);
        if ($config['max_length']) {
            $value = substr($value, 0, $config['max_length']);
        }

        return [
            'field' => $field,
            'value' => $value,
            'length' => strlen($value),
            'max_length' => $config['max_length'],
            'keywords' => $keywords
        ];
    }

    private function optimizeDescription(string $currentDesc, array $allocation): array
    {
        $injector = new KeywordInjectorService($this->accountId);
        $keywords = array_column($allocation['keywords'] ?? [], 'keyword');
        
        $result = $injector->injectInDescription($currentDesc, $keywords);
        
        return [
            'field' => 'description',
            'value' => $result['optimized'],
            'original' => $currentDesc,
            'keywords' => $keywords
        ];
    }

    private function findTargetField(string $fromField, int $count): ?string
    {
        $fieldOrder = ['title', 'model', 'description', 'keywords'];
        $fromIndex = array_search($fromField, $fieldOrder);
        
        // Procurar campo de menor peso com espaço
        for ($i = $fromIndex + 1; $i < count($fieldOrder); $i++) {
            return $fieldOrder[$i];
        }
        
        return null;
    }

    private function hasSpace(string $field, array $fieldData): bool
    {
        $config = self::FIELD_WEIGHTS[$field];
        return ($fieldData['keyword_count'] ?? 0) < $config['keyword_limit'];
    }

    private function calculatePotentialImprovement(array $suggestions): float
    {
        $improvement = 0;
        foreach ($suggestions as $suggestion) {
            if ($suggestion['type'] === 'promote') {
                // Mover de 30% para 100% = ganho de 70%
                $improvement += 0.7 * count($suggestion['keywords']);
            } elseif ($suggestion['type'] === 'move') {
                // Libera espaço no campo superior
                $improvement += 0.1 * count($suggestion['keywords']);
            }
        }
        return round($improvement, 2);
    }

    private function getFieldOptimizationAction(string $field, array $fieldAnalysis): string
    {
        $count = $fieldAnalysis['keyword_count'] ?? 0;
        $limit = self::FIELD_WEIGHTS[$field]['keyword_limit'];

        if ($count < $limit * 0.5) {
            return "Adicione pelo menos " . ($limit - $count) . " keywords ao {$field}";
        }
        if (($fieldAnalysis['usage_percent'] ?? 0) < 70 && self::FIELD_WEIGHTS[$field]['max_length']) {
            return "Utilize mais caracteres (atual: {$fieldAnalysis['usage_percent']}%)";
        }
        return "Otimize a qualidade das keywords existentes";
    }
}
