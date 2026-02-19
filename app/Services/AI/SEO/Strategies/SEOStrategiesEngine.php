<?php

declare(strict_types=1);

namespace App\Services\AI\SEO\Strategies;

use App\Database;
use App\Services\MercadoLivreClient;

/**
 * 🎯 E12: SEO Strategies Engine (Orchestrator)
 * 
 * Motor principal que orquestra todas as 12 estratégias SEO:
 * - E1: Hierarquia de Sinônimos (SynonymExpansionService)
 * - E2: Campos Ocultos (HiddenFieldsService)
 * - E3: Injeção Natural (KeywordInjectorService)
 * - E4: Cobertura de Busca (SearchTypeCoverageService)
 * - E5: Peso dos Campos (FieldWeightService)
 * - E6: Contextos de Uso (UseContextService)
 * - E7: Long Tail (LongTailGeneratorService)
 * - E8: Densidade (incluso no E3)
 * - E9: Score Semântico (SemanticScoreService)
 * - E10: Compatibilidade (CompatibilityService)
 * - E11: FAQ Otimizada (FAQOptimizerService)
 * - E12: Monitoramento (este serviço)
 * 
 * Funcionalidades:
 * - Análise completa de anúncio
 * - Otimização automática
 * - Score consolidado
 * - Recomendações priorizadas
 * - Monitoramento contínuo
 * 
 * @package App\Services\AI\SEO\Strategies
 */
class SEOStrategiesEngine
{
    private ?int $accountId;
    private ?MercadoLivreClient $client;

    // Serviços das estratégias
    private SynonymExpansionService $synonymService;
    private HiddenFieldsService $hiddenFieldsService;
    private KeywordInjectorService $injectorService;
    private SearchTypeCoverageService $coverageService;
    private FieldWeightService $weightService;
    private UseContextService $contextService;
    private LongTailGeneratorService $longTailService;
    private SemanticScoreService $semanticService;
    private CompatibilityService $compatibilityService;
    private FAQOptimizerService $faqService;
    private KeywordSourceService $keywordService;
    private CompetitorBenchmarkStrategy $competitorStrategy;

    /**
     * Configuração de pesos das estratégias no score final
     */
    private const STRATEGY_WEIGHTS = [
        'E1_SYNONYMS' => 0.10,
        'E2_HIDDEN_FIELDS' => 0.12,
        'E3_INJECTION' => 0.10,
        'E4_COVERAGE' => 0.12,
        'E5_FIELD_WEIGHT' => 0.10,
        'E6_CONTEXTS' => 0.08,
        'E7_LONG_TAIL' => 0.10,
        'E8_DENSITY' => 0.08,
        'E9_SEMANTIC' => 0.08,
        'E10_COMPATIBILITY' => 0.06,
        'E11_FAQ' => 0.06,
        'E13_COMPETITOR' => 0.0 // Execução opcional, peso dinâmico se executado
    ];

    /**
     * Thresholds de qualidade
     */
    private const QUALITY_THRESHOLDS = [
        'excellent' => 85,
        'good' => 70,
        'average' => 50,
        'poor' => 30
    ];

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
        $this->client = $accountId ? new MercadoLivreClient($accountId) : null;
        
        // Inicializar todos os serviços
        $this->synonymService = new SynonymExpansionService($accountId);
        $this->hiddenFieldsService = new HiddenFieldsService($accountId);
        $this->injectorService = new KeywordInjectorService($accountId);
        $this->coverageService = new SearchTypeCoverageService($accountId);
        $this->weightService = new FieldWeightService($accountId);
        $this->contextService = new UseContextService($accountId);
        $this->longTailService = new LongTailGeneratorService($accountId);
        $this->semanticService = new SemanticScoreService($accountId);
        $this->compatibilityService = new CompatibilityService($accountId);
        $this->faqService = new FAQOptimizerService($accountId);
        $this->keywordService = new KeywordSourceService($accountId);
        $this->competitorStrategy = new CompetitorBenchmarkStrategy($accountId);
    }

    /**
     * Análise completa de um item usando todas as estratégias
     */
    public function analyzeItem(string $itemId): array
    {
        if (!$this->client) {
            return ['error' => 'Cliente ML não configurado'];
        }

        try {
            $item = $this->client->get("/items/{$itemId}");
            return $this->analyzeItemData($item);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Otimiza título usando sugestões de keywords
     */
    public function optimizeTitle(string $title, string $categoryId = 'MLB1234'): array
    {
        $keywords = $this->generateKeywords($title, $categoryId);

        return [
            'original' => $title,
            'optimized' => $title,
            'keywords' => $keywords['keywords'] ?? [],
            'category_id' => $categoryId,
        ];
    }

    /**
     * Otimiza descrição com base em keywords
     */
    public function optimizeDescription(string $description, array $context = []): array
    {
        $title = (string)($context['title'] ?? $description);
        $categoryId = (string)($context['category_id'] ?? 'MLB1234');
        $keywords = $this->generateKeywords($title, $categoryId);

        return [
            'original' => $description,
            'optimized' => $description,
            'keywords' => $keywords['keywords'] ?? [],
            'category_id' => $categoryId,
        ];
    }

    /**
     * Gera keywords básicas a partir do título
     */
    public function generateKeywords(string $title, ?string $categoryId = null): array
    {
        $categoryId = $categoryId ?? 'MLB1234';
        $keywords = $this->extractKeywordsFromTitle($title);

        return [
            'category_id' => $categoryId,
            'keywords' => $keywords,
        ];
    }

    /**
     * Calcula score consolidado a partir de um array de scores
     */
    public function calculateScore(array $analysis): float
    {
        $scores = $analysis['strategy_scores'] ?? $analysis['scores'] ?? [];
        if (!is_array($scores) || empty($scores)) {
            return 0.0;
        }

        return $this->calculateConsolidatedScore($scores);
    }

    /**
     * Análise completa a partir de dados do item
     */
    public function analyzeItemData(array $itemData): array
    {
        $startTime = microtime(true);
        
        $itemId = $itemData['id'] ?? null;
        $title = $itemData['title'] ?? '';
        $description = $this->getDescription($itemData);
        $categoryId = $itemData['category_id'] ?? '';
        $attributes = $itemData['attributes'] ?? [];

        // Preparar dados para análise
        $productData = [
            'title' => $title,
            'description' => $description,
            'category_id' => $categoryId,
            'brand' => $this->extractAttribute($attributes, 'BRAND'),
            'model' => $this->extractAttribute($attributes, 'MODEL'),
            'attributes' => $attributes
        ];

        // Executar todas as análises
        $analyses = [];
        $scores = [];

        // E1: Sinônimos
        $analyses['E1_SYNONYMS'] = $this->analyzeSynonyms($productData);
        $scores['E1_SYNONYMS'] = $analyses['E1_SYNONYMS']['score'] ?? 0;

        // E2: Campos Ocultos
        if ($itemId) {
            $analyses['E2_HIDDEN_FIELDS'] = $this->hiddenFieldsService->analyzeItem($itemId);
        } else {
            $analyses['E2_HIDDEN_FIELDS'] = $this->analyzeHiddenFieldsPre($productData);
        }
        $scores['E2_HIDDEN_FIELDS'] = $analyses['E2_HIDDEN_FIELDS']['score'] ?? 0;

        // E3: Injeção (inclui E8: Densidade)
        $analyses['E3_INJECTION'] = $this->analyzeInjection($productData);
        $scores['E3_INJECTION'] = $analyses['E3_INJECTION']['score'] ?? 0;
        $scores['E8_DENSITY'] = $analyses['E3_INJECTION']['density_score'] ?? 0;

        // E4: Cobertura
        $analyses['E4_COVERAGE'] = $this->coverageService->analyzeCoverage(['title' => $title]);
        $scores['E4_COVERAGE'] = $analyses['E4_COVERAGE']['coverage_score'] ?? 0;

        // E5: Peso dos Campos
        $analyses['E5_FIELD_WEIGHT'] = $this->weightService->analyzeCurrentDistribution($productData);
        $scores['E5_FIELD_WEIGHT'] = ($analyses['E5_FIELD_WEIGHT']['efficiency'] ?? 0) * 100;

        // E6: Contextos
        $analyses['E6_CONTEXTS'] = $this->contextService->detectContexts($title . ' ' . $description);
        $scores['E6_CONTEXTS'] = min(100, ($analyses['E6_CONTEXTS']['total_detected'] ?? 0) * 25);

        // E7: Long Tail
        $analyses['E7_LONG_TAIL'] = $this->analyzeLongTail($productData);
        $scores['E7_LONG_TAIL'] = $analyses['E7_LONG_TAIL']['score'] ?? 0;

        // E9: Semântico
        $keywords = $this->extractKeywordsFromTitle($title);
        $mainKeyword = $this->extractMainKeyword($title);
        $categoryId = $productData['category_id'] ?? 'MLB1234';
        $scoreValue = $this->semanticService->calculateScore($mainKeyword, $title, $categoryId);
        $analyses['E9_SEMANTIC'] = ['score' => $scoreValue, 'final_score' => $scoreValue];
        $scores['E9_SEMANTIC'] = ($scoreValue) * 100;

        // E10: Compatibilidade
        if ($itemId) {
            $analyses['E10_COMPATIBILITY'] = $this->compatibilityService->analyzeCompatibility($itemId);
        } else {
            $analyses['E10_COMPATIBILITY'] = $this->analyzeCompatibilityPre($productData);
        }
        $scores['E10_COMPATIBILITY'] = $analyses['E10_COMPATIBILITY']['score'] ?? 0;

        // E11: FAQ
        $analyses['E11_FAQ'] = $this->analyzeFAQ($description);
        $scores['E11_FAQ'] = $analyses['E11_FAQ']['score'] ?? 0;

        // E13: Competitor Benchmark (On-Demand)
        if (isset($itemData['analyze_competitors']) && $itemData['analyze_competitors'] === true && $itemId) {
            $analyses['E13_COMPETITOR'] = $this->competitorStrategy->analyze($itemId);
            $scores['E13_COMPETITOR'] = $analyses['E13_COMPETITOR']['score'] ?? 0;
            
            // Rebalance weights logic would go here if we wanted it to affect the main score
            // For now, it stays as an add-on score
        }

        // Calcular score consolidado
        $consolidatedScore = $this->calculateConsolidatedScore($scores);

        // Gerar recomendações
        $recommendations = $this->generateRecommendations($scores, $analyses);

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'item_id' => $itemId,
            'title' => $title,
            'category_id' => $categoryId,
            'consolidated_score' => $consolidatedScore,
            'quality_level' => $this->getQualityLevel($consolidatedScore),
            'strategy_scores' => $scores,
            'analyses' => $analyses,
            'recommendations' => $recommendations,
            'execution_time_ms' => $executionTime,
            'analyzed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Otimização automática de um item
     */
    public function optimizeItem(string $itemId, array $options = []): array
    {
        if (!$this->client) {
            return ['error' => 'Cliente ML não configurado'];
        }

        try {
            $item = $this->client->get("/items/{$itemId}");
            return $this->optimizeItemData($item, $options);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Otimização a partir de dados
     */
    public function optimizeItemData(array $itemData, array $options = []): array
    {
        $applyStrategies = $options['strategies'] ?? 'all';
        $dryRun = $options['dry_run'] ?? true;

        // Primeiro analisar
        $analysis = $this->analyzeItemData($itemData);

        // Determinar quais estratégias aplicar
        $strategiesToApply = $this->determineStrategiesToApply(
            $analysis['strategy_scores'],
            $applyStrategies
        );

        $optimizations = [];
        $optimizedData = $itemData;

        // Aplicar cada estratégia
        foreach ($strategiesToApply as $strategy) {
            $result = $this->applyStrategy($strategy, $optimizedData, $analysis);
            
            if (!empty($result['changes'])) {
                $optimizations[$strategy] = $result;
                $optimizedData = array_merge($optimizedData, $result['updated_data'] ?? []);
            }
        }

        // Re-analisar após otimizações
        $newAnalysis = $this->analyzeItemData($optimizedData);

        return [
            'original_score' => $analysis['consolidated_score'],
            'optimized_score' => $newAnalysis['consolidated_score'],
            'improvement' => $newAnalysis['consolidated_score'] - $analysis['consolidated_score'],
            'strategies_applied' => array_keys($optimizations),
            'optimizations' => $optimizations,
            'optimized_data' => $dryRun ? null : $optimizedData,
            'dry_run' => $dryRun
        ];
    }

    /**
     * Monitora performance de keywords
     */
    public function monitorKeywords(string $categoryId, array $keywords): array
    {
        $db = Database::getInstance();
        
        $performance = [];
        
        foreach ($keywords as $keyword) {
            // Buscar dados de performance
            $stmt = $db->prepare("
                SELECT 
                    keyword,
                    impressions,
                    clicks,
                    conversions,
                    avg_position,
                    updated_at
                FROM seo_keyword_performance
                WHERE category_id = :category_id AND keyword = :keyword
                ORDER BY updated_at DESC
                LIMIT 1
            ");
            $stmt->execute([
                'category_id' => $categoryId,
                'keyword' => $keyword
            ]);
            
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($data) {
                $performance[$keyword] = [
                    'impressions' => (int) $data['impressions'],
                    'clicks' => (int) $data['clicks'],
                    'conversions' => (int) $data['conversions'],
                    'avg_position' => (float) $data['avg_position'],
                    'ctr' => $data['impressions'] > 0 
                        ? round($data['clicks'] / $data['impressions'] * 100, 2) 
                        : 0,
                    'last_updated' => $data['updated_at']
                ];
            } else {
                $performance[$keyword] = [
                    'status' => 'no_data',
                    'message' => 'Sem dados de performance'
                ];
            }
        }

        return [
            'category_id' => $categoryId,
            'keywords' => $performance,
            'total_monitored' => count($keywords),
            'with_data' => count(array_filter($performance, fn($p) => !isset($p['status'])))
        ];
    }

    /**
     * Obtém dashboard de estratégias
     */
    public function getDashboard(?string $categoryId = null): array
    {
        return [
            'strategies' => $this->getStrategiesStatus(),
            'weights' => self::STRATEGY_WEIGHTS,
            'thresholds' => self::QUALITY_THRESHOLDS,
            'category_config' => $categoryId 
                ? $this->getCategoryConfig($categoryId) 
                : null
        ];
    }

    /**
     * Obtém relatório de otimização
     */
    public function getOptimizationReport(string $itemId): array
    {
        $analysis = $this->analyzeItem($itemId);
        
        if (isset($analysis['error'])) {
            return $analysis;
        }

        // Calcular potencial de melhoria
        $currentScore = $analysis['consolidated_score'];
        $potentialScore = $this->calculatePotentialScore($analysis);

        return [
            'item_id' => $itemId,
            'current_score' => $currentScore,
            'potential_score' => $potentialScore,
            'improvement_potential' => $potentialScore - $currentScore,
            'priority_actions' => $this->getPriorityActions($analysis),
            'detailed_analysis' => $analysis
        ];
    }

    /**
     * Compara dois itens
     */
    public function compareItems(string $itemId1, string $itemId2): array
    {
        $analysis1 = $this->analyzeItem($itemId1);
        $analysis2 = $this->analyzeItem($itemId2);

        if (isset($analysis1['error']) || isset($analysis2['error'])) {
            return [
                'error' => 'Erro ao analisar um ou mais itens',
                'item1_error' => $analysis1['error'] ?? null,
                'item2_error' => $analysis2['error'] ?? null
            ];
        }

        $comparison = [];
        foreach (self::STRATEGY_WEIGHTS as $strategy => $weight) {
            $score1 = $analysis1['strategy_scores'][$strategy] ?? 0;
            $score2 = $analysis2['strategy_scores'][$strategy] ?? 0;
            
            $comparison[$strategy] = [
                'item1' => $score1,
                'item2' => $score2,
                'difference' => $score1 - $score2,
                'better' => $score1 > $score2 ? 'item1' : ($score2 > $score1 ? 'item2' : 'equal')
            ];
        }

        return [
            'item1' => [
                'id' => $itemId1,
                'score' => $analysis1['consolidated_score'],
                'quality' => $analysis1['quality_level']
            ],
            'item2' => [
                'id' => $itemId2,
                'score' => $analysis2['consolidated_score'],
                'quality' => $analysis2['quality_level']
            ],
            'winner' => $analysis1['consolidated_score'] > $analysis2['consolidated_score'] 
                ? 'item1' 
                : 'item2',
            'strategy_comparison' => $comparison
        ];
    }

    // ========================================================================
    // MÉTODOS PRIVADOS - Análises
    // ========================================================================

    private function analyzeSynonyms(array $productData): array
    {
        $title = $productData['title'] ?? '';
        $baseKeyword = $this->extractMainKeyword($title);
        $categoryId = $productData['category_id'] ?? 'MLB1234';
        
        $synonyms = $this->synonymService->expand($title, $categoryId);
        $usedSynonyms = $this->countUsedSynonyms($synonyms['synonyms'] ?? [], $title);

        $score = min(100, $usedSynonyms * 20);

        return [
            'base_keyword' => $baseKeyword,
            'available_synonyms' => count($synonyms['synonyms'] ?? []),
            'used_synonyms' => $usedSynonyms,
            'score' => $score
        ];
    }

    private function analyzeHiddenFieldsPre(array $productData): array
    {
        $attributes = $productData['attributes'] ?? [];
        $hiddenFields = ['KEYWORDS', 'MPN', 'LINE', 'ALPHANUMERIC_MODEL', 'GTIN'];
        
        $filled = 0;
        foreach ($attributes as $attr) {
            if (in_array($attr['id'] ?? '', $hiddenFields)) {
                if (!empty($attr['value_name'])) {
                    $filled++;
                }
            }
        }

        return [
            'total_fields' => count($hiddenFields),
            'filled_fields' => $filled,
            'score' => min(100, ($filled / count($hiddenFields)) * 100)
        ];
    }

    private function analyzeInjection(array $productData): array
    {
        $title = $productData['title'] ?? '';
        $description = $productData['description'] ?? '';
        $keywords = $this->extractKeywordsFromTitle($title);

        $titleDensity = $this->injectorService->analyzeDensity($title, $keywords);
        $descDensity = $this->injectorService->analyzeDensity($description, $keywords);

        $idealMin = 0.5;
        $idealMax = 3.0;

        $titleScore = $this->scoreDensity($titleDensity['density'] ?? 0, $idealMin, $idealMax);
        $descScore = $this->scoreDensity($descDensity['density'] ?? 0, $idealMin, $idealMax);

        return [
            'title_density' => $titleDensity['density'] ?? 0,
            'description_density' => $descDensity['density'] ?? 0,
            'score' => ($titleScore + $descScore) / 2,
            'density_score' => ($titleScore + $descScore) / 2
        ];
    }

    private function analyzeLongTail(array $productData): array
    {
        $title = $productData['title'] ?? '';
        $description = $productData['description'] ?? '';
        
        $baseKeyword = $this->extractMainKeyword($title);
        $missing = $this->longTailService->suggestMissing($productData);

        $totalMissing = $missing['total_missing'] ?? 0;
        $score = max(0, 100 - ($totalMissing * 10));

        return [
            'base_keyword' => $baseKeyword,
            'missing_long_tails' => $totalMissing,
            'score' => $score,
            'suggestions' => array_slice($missing['missing_long_tails'] ?? [], 0, 5)
        ];
    }

    private function analyzeCompatibilityPre(array $productData): array
    {
        $title = $productData['title'] ?? '';
        $model = $productData['model'] ?? '';
        
        $modelsInTitle = $this->compatibilityService->getAllModels();
        $detected = 0;

        $textToCheck = $title . ' ' . $model;
        foreach ($modelsInTitle['brands'] ?? [] as $brand) {
            foreach ($modelsInTitle['models_by_brand'][$brand] ?? [] as $base => $variants) {
                if (stripos($textToCheck, $base) !== false) {
                    $detected++;
                }
            }
        }

        return [
            'models_detected' => $detected,
            'score' => min(100, $detected * 15)
        ];
    }

    private function analyzeFAQ(string $description): array
    {
        // Verificar se tem FAQ na descrição
        $hasFAQ = stripos($description, 'perguntas frequentes') !== false ||
                  stripos($description, 'faq') !== false ||
                  preg_match('/\?\s*\n/', $description);

        $score = $hasFAQ ? 70 : 0;

        // Contar perguntas
        preg_match_all('/\?/', $description, $matches);
        $questionCount = count($matches[0]);

        if ($questionCount >= 3) $score = 100;
        elseif ($questionCount >= 1) $score = 50;

        return [
            'has_faq_section' => $hasFAQ,
            'question_count' => $questionCount,
            'score' => $score
        ];
    }

    // ========================================================================
    // MÉTODOS PRIVADOS - Cálculos
    // ========================================================================

    private function calculateConsolidatedScore(array $scores): float
    {
        $weightedSum = 0;
        $totalWeight = 0;

        foreach (self::STRATEGY_WEIGHTS as $strategy => $weight) {
            $score = $scores[$strategy] ?? 0;
            $weightedSum += $score * $weight;
            $totalWeight += $weight;
        }

        return round($weightedSum / $totalWeight, 2);
    }

    private function getQualityLevel(float $score): string
    {
        if ($score >= self::QUALITY_THRESHOLDS['excellent']) return 'excellent';
        if ($score >= self::QUALITY_THRESHOLDS['good']) return 'good';
        if ($score >= self::QUALITY_THRESHOLDS['average']) return 'average';
        return 'poor';
    }

    private function calculatePotentialScore(array $analysis): float
    {
        $scores = $analysis['strategy_scores'];
        $improved = [];

        foreach ($scores as $strategy => $score) {
            // Assumir melhoria potencial de 80% dos gaps
            $gap = 100 - $score;
            $improved[$strategy] = $score + ($gap * 0.8);
        }

        return $this->calculateConsolidatedScore($improved);
    }

    private function scoreDensity(float $density, float $min, float $max): float
    {
        if ($density >= $min && $density <= $max) {
            return 100;
        }
        if ($density < $min) {
            return max(0, 100 - (($min - $density) * 50));
        }
        return max(0, 100 - (($density - $max) * 30));
    }

    // ========================================================================
    // MÉTODOS PRIVADOS - Recomendações
    // ========================================================================

    private function generateRecommendations(array $scores, array $analyses): array
    {
        $recommendations = [];

        foreach ($scores as $strategy => $score) {
            if ($score < 70) {
                $rec = $this->getRecommendationForStrategy($strategy, $score, $analyses[$strategy] ?? []);
                if ($rec) {
                    $recommendations[] = $rec;
                }
            }
        }

        // Ordenar por prioridade
        usort($recommendations, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return $recommendations;
    }

    private function getRecommendationForStrategy(string $strategy, float $score, array $analysis): ?array
    {
        $recommendations = [
            'E1_SYNONYMS' => [
                'priority' => 2,
                'message' => 'Adicione mais sinônimos ao título e descrição',
                'action' => 'Use o SynonymExpansionService para expandir keywords'
            ],
            'E2_HIDDEN_FIELDS' => [
                'priority' => 1,
                'message' => 'Preencha campos ocultos (KEYWORDS, MPN, LINE)',
                'action' => 'Use o HiddenFieldsService para sugerir valores'
            ],
            'E3_INJECTION' => [
                'priority' => 2,
                'message' => 'Otimize a densidade de keywords',
                'action' => 'Use o KeywordInjectorService para injeção natural'
            ],
            'E4_COVERAGE' => [
                'priority' => 1,
                'message' => 'Aumente a cobertura de tipos de busca',
                'action' => 'Inclua keywords genéricas, específicas e long-tail'
            ],
            'E5_FIELD_WEIGHT' => [
                'priority' => 3,
                'message' => 'Redistribua keywords pelos campos',
                'action' => 'Priorize título (100%) > modelo (70%) > descrição (50%)'
            ],
            'E6_CONTEXTS' => [
                'priority' => 3,
                'message' => 'Adicione contextos de uso',
                'action' => 'Inclua keywords de uso profissional, lazer, urbano'
            ],
            'E7_LONG_TAIL' => [
                'priority' => 2,
                'message' => 'Adicione mais keywords long-tail',
                'action' => 'Use o LongTailGeneratorService para gerar combinações'
            ],
            'E10_COMPATIBILITY' => [
                'priority' => 3,
                'message' => 'Expanda lista de compatibilidade',
                'action' => 'Adicione mais modelos compatíveis'
            ],
            'E11_FAQ' => [
                'priority' => 4,
                'message' => 'Adicione FAQ à descrição',
                'action' => 'Use o FAQOptimizerService para gerar perguntas'
            ]
        ];

        if (!isset($recommendations[$strategy])) {
            return null;
        }

        $rec = $recommendations[$strategy];
        $rec['strategy'] = $strategy;
        $rec['current_score'] = $score;
        $rec['target_score'] = 80;

        return $rec;
    }

    private function getPriorityActions(array $analysis): array
    {
        $actions = [];
        $scores = $analysis['strategy_scores'];

        // Ordenar por score (pior primeiro)
        asort($scores);

        $count = 0;
        foreach ($scores as $strategy => $score) {
            if ($score < 70 && $count < 3) {
                $actions[] = [
                    'strategy' => $strategy,
                    'current_score' => $score,
                    'action' => $this->getActionDescription($strategy),
                    'impact' => 'high'
                ];
                $count++;
            }
        }

        return $actions;
    }

    private function getActionDescription(string $strategy): string
    {
        $descriptions = [
            'E1_SYNONYMS' => 'Expandir sinônimos do produto',
            'E2_HIDDEN_FIELDS' => 'Preencher campos ocultos ML',
            'E3_INJECTION' => 'Otimizar injeção de keywords',
            'E4_COVERAGE' => 'Aumentar cobertura de busca',
            'E5_FIELD_WEIGHT' => 'Redistribuir peso dos campos',
            'E6_CONTEXTS' => 'Adicionar contextos de uso',
            'E7_LONG_TAIL' => 'Gerar mais long-tail',
            'E8_DENSITY' => 'Ajustar densidade de keywords',
            'E9_SEMANTIC' => 'Melhorar relevância semântica',
            'E10_COMPATIBILITY' => 'Expandir compatibilidade',
            'E11_FAQ' => 'Adicionar FAQ otimizada'
        ];

        return $descriptions[$strategy] ?? 'Otimizar estratégia';
    }

    // ========================================================================
    // MÉTODOS PRIVADOS - Aplicação
    // ========================================================================

    private function determineStrategiesToApply(array $scores, $applyStrategies): array
    {
        if ($applyStrategies === 'all') {
            return array_keys(array_filter($scores, fn($s) => $s < 80));
        }

        if ($applyStrategies === 'critical') {
            return array_keys(array_filter($scores, fn($s) => $s < 50));
        }

        if (is_array($applyStrategies)) {
            return $applyStrategies;
        }

        return [];
    }

    private function applyStrategy(string $strategy, array $itemData, array $analysis): array
    {
        // Implementação simplificada - cada estratégia terá seu próprio método de aplicação
        $changes = [];
        $updatedData = [];

        switch ($strategy) {
            case 'E7_LONG_TAIL':
                $result = $this->longTailService->generate(
                    $this->extractMainKeyword($itemData['title'] ?? ''),
                    ['brand' => $itemData['brand'] ?? null, 'limit' => 5]
                );
                $changes = ['long_tails_generated' => $result['total_generated']];
                break;

            case 'E6_CONTEXTS':
                $result = $this->contextService->suggestContexts($itemData);
                $changes = ['contexts_suggested' => count($result['suggestions'] ?? [])];
                break;

            case 'E11_FAQ':
                $result = $this->faqService->generateFAQs($itemData, 3);
                $changes = ['faqs_generated' => $result['total']];
                $updatedData['faq_text'] = $this->faqService->generateDescriptionText($result['faqs']);
                break;
        }

        return [
            'strategy' => $strategy,
            'changes' => $changes,
            'updated_data' => $updatedData
        ];
    }

    // ========================================================================
    // MÉTODOS PRIVADOS - Utilitários
    // ========================================================================

    private function getDescription(array $itemData): string
    {
        if (isset($itemData['description']['plain_text'])) {
            return $itemData['description']['plain_text'];
        }
        if (isset($itemData['description'])) {
            return is_string($itemData['description']) ? $itemData['description'] : '';
        }
        return '';
    }

    private function extractAttribute(array $attributes, string $attrId): string
    {
        foreach ($attributes as $attr) {
            if (($attr['id'] ?? '') === $attrId) {
                return $attr['value_name'] ?? '';
            }
        }
        return '';
    }

    private function extractMainKeyword(string $title): string
    {
        $stopWords = ['para', 'com', 'sem', 'de', 'da', 'do', 'em', 'no', 'na', 'e', 'ou'];
        $words = preg_split('/\s+/', mb_strtolower($title));
        
        $keywords = [];
        foreach ($words as $word) {
            $word = preg_replace('/[^\p{L}\p{N}]/u', '', $word);
            if (strlen($word) > 2 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
                if (count($keywords) >= 2) break;
            }
        }

        return implode(' ', $keywords);
    }

    private function extractKeywordsFromTitle(string $title): array
    {
        $stopWords = ['para', 'com', 'sem', 'de', 'da', 'do', 'em', 'no', 'na', 'e', 'ou', 'a', 'o'];
        $words = preg_split('/\s+/', mb_strtolower($title));
        
        $keywords = [];
        foreach ($words as $word) {
            $word = preg_replace('/[^\p{L}\p{N}]/u', '', $word);
            if (strlen($word) > 2 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }

        return array_unique($keywords);
    }

    private function countUsedSynonyms(array $synonyms, string $text): int
    {
        $count = 0;
        $textLower = mb_strtolower($text);

        foreach ($synonyms as $syn) {
            $word = is_array($syn) ? ($syn['word'] ?? '') : $syn;
            if (stripos($textLower, mb_strtolower($word)) !== false) {
                $count++;
            }
        }

        return $count;
    }

    private function getStrategiesStatus(): array
    {
        return [
            'E1_SYNONYMS' => ['name' => 'Hierarquia de Sinônimos', 'status' => 'active'],
            'E2_HIDDEN_FIELDS' => ['name' => 'Campos Ocultos', 'status' => 'active'],
            'E3_INJECTION' => ['name' => 'Injeção Natural', 'status' => 'active'],
            'E4_COVERAGE' => ['name' => 'Cobertura de Busca', 'status' => 'active'],
            'E5_FIELD_WEIGHT' => ['name' => 'Peso dos Campos', 'status' => 'active'],
            'E6_CONTEXTS' => ['name' => 'Contextos de Uso', 'status' => 'active'],
            'E7_LONG_TAIL' => ['name' => 'Long Tail', 'status' => 'active'],
            'E8_DENSITY' => ['name' => 'Densidade', 'status' => 'active'],
            'E9_SEMANTIC' => ['name' => 'Score Semântico', 'status' => 'active'],
            'E10_COMPATIBILITY' => ['name' => 'Compatibilidade', 'status' => 'active'],
            'E11_FAQ' => ['name' => 'FAQ Otimizada', 'status' => 'active'],
            'E12_MONITORING' => ['name' => 'Monitoramento', 'status' => 'active']
        ];
    }

    private function getCategoryConfig(string $categoryId): array
    {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("
            SELECT * FROM seo_category_config 
            WHERE category_id = :category_id
        ");
        $stmt->execute(['category_id' => $categoryId]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }
}
