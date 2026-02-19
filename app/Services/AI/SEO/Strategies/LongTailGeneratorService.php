<?php

declare(strict_types=1);

namespace App\Services\AI\SEO\Strategies;

use App\Services\MercadoLivreClient;
use App\Services\AI\ML\SynonymGenerator;

/**
 * 🔗 E7: Long Tail Generator Service
 * 
 * Gera keywords long-tail automaticamente combinando:
 * - Keyword base + especificação
 * - Keyword base + marca + modelo
 * - Keyword base + contexto de uso
 * - Keyword base + benefício
 * 
 * Long-tail = baixo volume + altíssima conversão
 * 
 * @package App\Services\AI\SEO\Strategies
 */
class LongTailGeneratorService
{
    private ?int $accountId;
    private ?MercadoLivreClient $client;
    private SynonymExpansionService $synonymService;
    private UseContextService $contextService;

    /**
     * Templates de geração long-tail
     */
    private const LONG_TAIL_TEMPLATES = [
        // [base] + [especificação]
        'spec' => [
            '{base} {capacity}',
            '{base} {size}',
            '{base} {color}',
            '{base} {material}'
        ],
        // [base] + [marca/modelo]
        'brand_model' => [
            '{base} {brand}',
            '{base} {brand} {model}',
            '{base} para {brand}',
            '{base} compatível {brand} {model}'
        ],
        // [base] + [uso]
        'use_case' => [
            '{base} para {use}',
            '{base} uso {use}',
            '{base} {use} profissional',
            '{base} ideal para {use}'
        ],
        // [base] + [benefício]
        'benefit' => [
            '{base} com {benefit}',
            '{base} {benefit}',
            '{base} alta {benefit}',
            '{base} máxima {benefit}'
        ],
        // Combinações complexas
        'complex' => [
            '{base} {brand} {capacity} para {use}',
            '{base} {capacity} {use} {benefit}',
            '{base} {brand} {model} {use}'
        ]
    ];

    /**
     * Especificações comuns por tipo
     */
    private const COMMON_SPECS = [
        'capacity' => ['41 litros', '45 litros', '52 litros', '30 litros', '28 litros'],
        'size' => ['grande', 'médio', 'pequeno', 'compacto', 'extra grande'],
        'color' => ['preto', 'branco', 'vermelho', 'prata', 'fosco'],
        'material' => ['plástico', 'alumínio', 'fibra', 'abs', 'resistente'],
        'benefit' => ['qualidade', 'durabilidade', 'resistência', 'praticidade', 'segurança']
    ];

    /**
     * Marcas comuns (categoria motos como exemplo)
     */
    private const COMMON_BRANDS = [
        'honda', 'yamaha', 'suzuki', 'kawasaki', 'bmw', 'harley',
        'givi', 'proos', 'protork', 'shad', 'kappa'
    ];

    /**
     * Modelos comuns
     */
    private const COMMON_MODELS = [
        'cg 160', 'cg 150', 'fan 160', 'fazer 250', 'cb 300',
        'bros 160', 'xre 300', 'cb 500', 'pcx 150', 'nmax 160'
    ];

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
        $this->client = $accountId ? new MercadoLivreClient($accountId) : null;
        $this->synonymService = new SynonymExpansionService($accountId);
        $this->contextService = new UseContextService($accountId);
    }

    /**
     * Gera keywords long-tail a partir de uma base
     * 
     * @param string $baseKeyword Keyword base
     * @param array $options Opções de geração
     * @return array Keywords long-tail geradas
     */
    public function generate(string $baseKeyword, array $options = []): array
    {
        $brand = $options['brand'] ?? null;
        $model = $options['model'] ?? null;
        $specs = $options['specs'] ?? [];
        $contexts = $options['contexts'] ?? ['profissional', 'lazer'];
        $limit = $options['limit'] ?? 20;
        $categoryId = $options['category_id'] ?? null;

        $longTails = [];

        // 1. Gerar com especificações
        $specLongTails = $this->generateWithSpecs($baseKeyword, $specs);
        $longTails = array_merge($longTails, $specLongTails);

        // 2. Gerar com marca/modelo
        $brandLongTails = $this->generateWithBrandModel($baseKeyword, $brand, $model);
        $longTails = array_merge($longTails, $brandLongTails);

        // 3. Gerar com contextos de uso
        $contextLongTails = $this->generateWithContexts($baseKeyword, $contexts);
        $longTails = array_merge($longTails, $contextLongTails);

        // 4. Gerar com benefícios
        $benefitLongTails = $this->generateWithBenefits($baseKeyword);
        $longTails = array_merge($longTails, $benefitLongTails);

        // 5. Gerar combinações complexas
        $complexLongTails = $this->generateComplex($baseKeyword, $brand, $model, $specs, $contexts);
        $longTails = array_merge($longTails, $complexLongTails);

        // Remover duplicatas e ordenar
        $longTails = $this->deduplicateAndScore($longTails, $categoryId);

        // Limitar resultado
        $longTails = array_slice($longTails, 0, $limit);

        return [
            'base_keyword' => $baseKeyword,
            'long_tails' => $longTails,
            'total_generated' => count($longTails),
            'options_used' => [
                'brand' => $brand,
                'model' => $model,
                'contexts' => $contexts
            ]
        ];
    }

    /**
     * Gera long-tails baseado em autocomplete do ML
     */
    public function generateFromAutocomplete(string $baseKeyword, int $limit = 15): array
    {
        if (!$this->client) {
            return ['error' => 'Cliente ML não configurado', 'long_tails' => []];
        }

        $longTails = [];

        try {
            // Buscar sugestões de autocomplete
            $suggestions = $this->client->get('/sites/MLB/autosuggest', [
                'q' => $baseKeyword,
                'limit' => 20
            ]);

            foreach ($suggestions['suggested_queries'] ?? [] as $suggestion) {
                $query = $suggestion['q'] ?? '';
                
                // Filtrar apenas long-tail (3+ palavras)
                if (str_word_count($query) >= 3 && stripos($query, $baseKeyword) !== false) {
                    $longTails[] = [
                        'keyword' => $query,
                        'source' => 'autocomplete',
                        'word_count' => str_word_count($query),
                        'score' => 0.8 // Autocomplete tem alta relevância
                    ];
                }
            }
        } catch (\Exception $e) {
            // Ignorar erro de autocomplete
        }

        return [
            'base_keyword' => $baseKeyword,
            'long_tails' => array_slice($longTails, 0, $limit),
            'source' => 'ml_autocomplete'
        ];
    }

    /**
     * Gera long-tails a partir de concorrentes
     */
    public function generateFromCompetitors(
        string $categoryId, 
        string $searchQuery,
        int $limit = 20
    ): array {
        if (!$this->client) {
            return ['error' => 'Cliente ML não configurado', 'long_tails' => []];
        }

        $longTails = [];

        try {
            // Buscar top anúncios
            $search = $this->client->get('/sites/MLB/search', [
                'category' => $categoryId,
                'q' => $searchQuery,
                'limit' => 10
            ]);

            foreach ($search['results'] ?? [] as $item) {
                $title = $item['title'] ?? '';
                
                // Extrair potenciais long-tails do título
                $extracted = $this->extractLongTailsFromTitle($title);
                
                foreach ($extracted as $lt) {
                    $longTails[] = [
                        'keyword' => $lt,
                        'source' => 'competitor',
                        'word_count' => str_word_count($lt),
                        'score' => 0.7,
                        'source_item' => $item['id']
                    ];
                }
            }
        } catch (\Exception $e) {
            // Ignorar erro
        }

        // Deduplica
        $unique = [];
        foreach ($longTails as $lt) {
            $key = mb_strtolower($lt['keyword']);
            if (!isset($unique[$key])) {
                $unique[$key] = $lt;
            }
        }

        return [
            'category_id' => $categoryId,
            'search_query' => $searchQuery,
            'long_tails' => array_slice(array_values($unique), 0, $limit),
            'source' => 'competitors'
        ];
    }

    /**
     * Gera long-tails via IA
     */
    public function generateWithAI(
        string $baseKeyword, 
        array $context,
        int $limit = 15
    ): array {
        try {
            $generator = new SynonymGenerator();
            
            $result = $generator->generateLongTail($baseKeyword, $context, $limit);
            
            return [
                'base_keyword' => $baseKeyword,
                'long_tails' => $result['long_tails'] ?? [],
                'source' => 'ai'
            ];
        } catch (\Exception $e) {
            return [
                'base_keyword' => $baseKeyword,
                'long_tails' => [],
                'source' => 'ai',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Analisa uma keyword para determinar se é long-tail
     */
    public function analyzeLongTail(string $keyword): array
    {
        $wordCount = str_word_count($keyword);
        $hasSpec = $this->hasSpecification($keyword);
        $hasBrand = $this->hasBrand($keyword);
        $hasModel = $this->hasModel($keyword);
        $hasContext = $this->hasContext($keyword);

        $isLongTail = $wordCount >= 3 || ($wordCount >= 2 && ($hasSpec || $hasBrand));

        $characteristics = [];
        if ($hasSpec) $characteristics[] = 'specification';
        if ($hasBrand) $characteristics[] = 'brand';
        if ($hasModel) $characteristics[] = 'model';
        if ($hasContext) $characteristics[] = 'use_context';

        return [
            'keyword' => $keyword,
            'is_long_tail' => $isLongTail,
            'word_count' => $wordCount,
            'characteristics' => $characteristics,
            'type' => $this->classifyLongTailType($keyword, $characteristics),
            'estimated_volume' => $this->estimateVolume($wordCount),
            'estimated_conversion' => $this->estimateConversion($wordCount, count($characteristics))
        ];
    }

    /**
     * Sugere long-tails faltantes para um anúncio
     */
    public function suggestMissing(array $itemData): array
    {
        $title = $itemData['title'] ?? '';
        $description = $itemData['description'] ?? '';
        $brand = $itemData['brand'] ?? '';
        $model = $itemData['model'] ?? '';
        $categoryId = $itemData['category_id'] ?? null;

        // Extrair keyword base do título
        $baseKeyword = $this->extractBaseKeyword($title);

        // Gerar todas as possibilidades
        $allPossible = $this->generate($baseKeyword, [
            'brand' => $brand,
            'model' => $model,
            'category_id' => $categoryId
        ]);

        // Filtrar as que já existem
        $fullText = mb_strtolower($title . ' ' . $description);
        $missing = [];

        foreach ($allPossible['long_tails'] as $lt) {
            $keyword = mb_strtolower($lt['keyword']);
            
            // Verificar se NÃO está presente
            if (stripos($fullText, $keyword) === false) {
                $missing[] = $lt;
            }
        }

        return [
            'base_keyword' => $baseKeyword,
            'missing_long_tails' => $missing,
            'total_missing' => count($missing),
            'recommendation' => $this->getRecommendation($missing)
        ];
    }

    // ========================================================================
    // MÉTODOS PRIVADOS - Geração
    // ========================================================================

    private function generateWithSpecs(string $base, array $customSpecs = []): array
    {
        $longTails = [];

        // Usar specs customizadas ou padrão
        $specs = !empty($customSpecs) ? $customSpecs : self::COMMON_SPECS;

        foreach ($specs as $type => $values) {
            foreach ($values as $value) {
                $longTails[] = [
                    'keyword' => "{$base} {$value}",
                    'type' => 'spec',
                    'spec_type' => $type,
                    'word_count' => str_word_count("{$base} {$value}")
                ];
            }
        }

        return $longTails;
    }

    private function generateWithBrandModel(string $base, ?string $brand, ?string $model): array
    {
        $longTails = [];
        $brands = $brand ? [$brand] : self::COMMON_BRANDS;
        $models = $model ? [$model] : [];

        foreach ($brands as $b) {
            $longTails[] = [
                'keyword' => "{$base} {$b}",
                'type' => 'brand',
                'word_count' => str_word_count("{$base} {$b}")
            ];

            $longTails[] = [
                'keyword' => "{$base} para {$b}",
                'type' => 'brand',
                'word_count' => str_word_count("{$base} para {$b}")
            ];

            // Com modelo específico
            if (!empty($models)) {
                foreach ($models as $m) {
                    $longTails[] = [
                        'keyword' => "{$base} {$b} {$m}",
                        'type' => 'brand_model',
                        'word_count' => str_word_count("{$base} {$b} {$m}")
                    ];
                }
            }
        }

        // Adicionar modelos comuns se não houver modelo específico
        if (empty($model)) {
            foreach (self::COMMON_MODELS as $m) {
                $longTails[] = [
                    'keyword' => "{$base} {$m}",
                    'type' => 'model',
                    'word_count' => str_word_count("{$base} {$m}")
                ];
            }
        }

        return $longTails;
    }

    private function generateWithContexts(string $base, array $contexts): array
    {
        $longTails = [];
        $contextData = $this->contextService->generateContextKeywords($contexts, null, 10);

        foreach ($contextData['keywords'] as $kw) {
            $keyword = $kw['keyword'];
            
            $longTails[] = [
                'keyword' => "{$base} {$keyword}",
                'type' => 'context',
                'context' => $kw['context'],
                'word_count' => str_word_count("{$base} {$keyword}")
            ];

            $longTails[] = [
                'keyword' => "{$base} para {$keyword}",
                'type' => 'context',
                'context' => $kw['context'],
                'word_count' => str_word_count("{$base} para {$keyword}")
            ];
        }

        return $longTails;
    }

    private function generateWithBenefits(string $base): array
    {
        $longTails = [];
        $benefits = self::COMMON_SPECS['benefit'];

        foreach ($benefits as $benefit) {
            $longTails[] = [
                'keyword' => "{$base} {$benefit}",
                'type' => 'benefit',
                'word_count' => str_word_count("{$base} {$benefit}")
            ];

            $longTails[] = [
                'keyword' => "{$base} com {$benefit}",
                'type' => 'benefit',
                'word_count' => str_word_count("{$base} com {$benefit}")
            ];
        }

        return $longTails;
    }

    private function generateComplex(
        string $base, 
        ?string $brand, 
        ?string $model, 
        array $specs,
        array $contexts
    ): array {
        $longTails = [];
        $b = $brand ?: 'universal';
        $m = $model ?: '';

        // Pegar primeira spec de capacidade
        $capacity = $specs['capacity'] ?? (self::COMMON_SPECS['capacity'][0] ?? '');
        
        // Pegar primeiro contexto
        $use = $contexts[0] ?? 'uso geral';

        if (!empty($capacity)) {
            $longTails[] = [
                'keyword' => "{$base} {$capacity} {$use}",
                'type' => 'complex',
                'word_count' => str_word_count("{$base} {$capacity} {$use}")
            ];

            if ($brand) {
                $longTails[] = [
                    'keyword' => "{$base} {$brand} {$capacity}",
                    'type' => 'complex',
                    'word_count' => str_word_count("{$base} {$brand} {$capacity}")
                ];
            }
        }

        if ($brand && $model) {
            $longTails[] = [
                'keyword' => "{$base} {$brand} {$model} {$use}",
                'type' => 'complex',
                'word_count' => str_word_count("{$base} {$brand} {$model} {$use}")
            ];
        }

        return $longTails;
    }

    // ========================================================================
    // MÉTODOS PRIVADOS - Utilitários
    // ========================================================================

    private function deduplicateAndScore(array $longTails, ?string $categoryId): array
    {
        $unique = [];

        foreach ($longTails as $lt) {
            $key = mb_strtolower(trim($lt['keyword']));
            
            if (!isset($unique[$key])) {
                // Calcular score
                $score = $this->calculateScore($lt);
                $lt['score'] = $score;
                $unique[$key] = $lt;
            }
        }

        // Ordenar por score
        $result = array_values($unique);
        usort($result, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return $result;
    }

    private function calculateScore(array $longTail): float
    {
        $score = 0.5; // Base
        $wordCount = $longTail['word_count'] ?? 0;
        $type = $longTail['type'] ?? 'unknown';

        // Bonus por tipo
        $typeBonus = [
            'brand_model' => 0.3,
            'complex' => 0.25,
            'context' => 0.2,
            'brand' => 0.15,
            'spec' => 0.1,
            'benefit' => 0.1
        ];
        $score += $typeBonus[$type] ?? 0;

        // Bonus por tamanho (3-5 palavras ideal)
        if ($wordCount >= 3 && $wordCount <= 5) {
            $score += 0.2;
        } elseif ($wordCount > 5) {
            $score += 0.1;
        }

        return min(1.0, $score);
    }

    private function extractLongTailsFromTitle(string $title): array
    {
        $longTails = [];
        
        // Remover caracteres especiais
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title);
        $words = preg_split('/\s+/', mb_strtolower($clean));
        $words = array_filter($words, fn($w) => strlen($w) > 2);
        $words = array_values($words);

        // Gerar n-gramas (3-5 palavras)
        for ($n = 3; $n <= min(5, count($words)); $n++) {
            for ($i = 0; $i <= count($words) - $n; $i++) {
                $ngram = implode(' ', array_slice($words, $i, $n));
                $longTails[] = $ngram;
            }
        }

        return array_unique($longTails);
    }

    private function extractBaseKeyword(string $title): string
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

    private function hasSpecification(string $keyword): bool
    {
        return (bool) preg_match('/\d+\s*(litros?|l|mm|cm|kg|w|v)/i', $keyword);
    }

    private function hasBrand(string $keyword): bool
    {
        foreach (self::COMMON_BRANDS as $brand) {
            if (stripos($keyword, $brand) !== false) {
                return true;
            }
        }
        return false;
    }

    private function hasModel(string $keyword): bool
    {
        foreach (self::COMMON_MODELS as $model) {
            if (stripos($keyword, $model) !== false) {
                return true;
            }
        }
        return (bool) preg_match('/[a-z]{2,}\s*\d{2,}/i', $keyword);
    }

    private function hasContext(string $keyword): bool
    {
        $contextWords = ['delivery', 'motoboy', 'viagem', 'trabalho', 'profissional', 'lazer'];
        foreach ($contextWords as $word) {
            if (stripos($keyword, $word) !== false) {
                return true;
            }
        }
        return false;
    }

    private function classifyLongTailType(string $keyword, array $characteristics): string
    {
        if (in_array('brand', $characteristics) && in_array('model', $characteristics)) {
            return 'brand_model';
        }
        if (in_array('specification', $characteristics)) {
            return 'specification';
        }
        if (in_array('use_context', $characteristics)) {
            return 'use_case';
        }
        if (in_array('brand', $characteristics)) {
            return 'brand';
        }
        return 'generic';
    }

    private function estimateVolume(int $wordCount): string
    {
        if ($wordCount <= 2) return 'high';
        if ($wordCount <= 4) return 'medium';
        return 'low';
    }

    private function estimateConversion(int $wordCount, int $characteristicCount): string
    {
        $score = $wordCount * 0.5 + $characteristicCount * 1.5;
        
        if ($score >= 4) return 'very_high';
        if ($score >= 3) return 'high';
        if ($score >= 2) return 'medium';
        return 'low';
    }

    private function getRecommendation(array $missing): string
    {
        if (empty($missing)) {
            return 'Excelente! Cobertura de long-tail completa.';
        }

        $count = count($missing);
        if ($count > 10) {
            return "Adicione pelo menos 10 long-tails ao campo KEYWORDS ou descrição.";
        }
        if ($count > 5) {
            return "Considere adicionar mais {$count} long-tails para melhor cobertura.";
        }
        return "Adicione as {$count} long-tails sugeridas para otimização completa.";
    }
}
