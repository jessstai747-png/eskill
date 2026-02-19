<?php

namespace App\Services;

/**
 * Serviço de Pesquisa de Palavras-Chave (Palavras-Chave)
 */
class KeywordResearchService
{
    public const STOP_WORDS = [
        'a', 'o', 'e', 'de', 'da', 'do', 'em', 'um', 'uma', 'para', 'com',
        'sem', 'no', 'na', 'os', 'as', 'ou', 'por', 'que', 'dos', 'das'
    ];

    private MercadoLivreClient $client;
    private CacheService $cache;
    private string $siteId;

    public function __construct(
        ?int $accountId = null,
        ?MercadoLivreClient $client = null,
        ?CacheService $cache = null,
        ?string $siteId = null
    ) {
        $this->client = $client ?? new MercadoLivreClient($accountId);
        $this->cache = $cache ?? new CacheService();
        $this->siteId = $siteId ?? 'MLB';
    }

    /**
     * Pesquisa palavras-chave para categoria e keyword base
     */
    public function researchKeywords(string $categoryId, ?string $baseKeyword = null): array
    {
        $baseKeyword = trim((string) $baseKeyword);
        $cacheKey = 'keyword_research:' . $this->siteId . ':' . $categoryId . ':' . md5($baseKeyword);

        return $this->cache->remember($cacheKey, function () use ($categoryId, $baseKeyword) {
            $primary = $baseKeyword !== '' ? [$baseKeyword] : [];
            $variations = $baseKeyword !== '' ? $this->generateKeywordVariations($baseKeyword) : [];
            $categoryTerms = $this->getCategorySpecificTerms($categoryId);
            $trends = $this->getCategoryTrends($categoryId);
            $autocomplete = $baseKeyword !== '' ? $this->getAutocompleteKeywords($baseKeyword) : [];
            $competitors = $baseKeyword !== '' ? $this->extractCompetitorKeywords($baseKeyword, $categoryId) : [];

            $all = array_values(array_unique(array_filter(array_merge(
                $primary,
                $variations,
                $categoryTerms,
                $trends,
                $autocomplete,
                $competitors
            ))));

            $primaryKeywords = !empty($primary) ? $primary : array_slice($all, 0, 3);
            $secondary = array_values(array_diff($all, $primaryKeywords));

            return [
                'primary_keywords' => $primaryKeywords,
                'secondary_keywords' => array_slice($secondary, 0, 20),
                'category_terms' => $categoryTerms,
                'trends' => $trends,
                'autocomplete' => $autocomplete,
                'competitors' => $competitors,
                'all' => $all,
            ];
        }, 3600);
    }

    /**
     * Trends de keywords por categoria
     */
    public function getCategoryTrends(string $categoryId): array
    {
        $trends = $this->client->getTrends($categoryId);
        return array_values(array_unique(array_filter(is_array($trends) ? $trends : [])));
    }

    /**
     * Sugestões de autocomplete
     */
    public function getAutocompleteKeywords(string $keyword): array
    {
        $suggestions = $this->client->getAutocompleteSuggestions($keyword);
        return array_values(array_unique(array_filter(is_array($suggestions) ? $suggestions : [])));
    }

    /**
     * Extrai keywords de concorrentes
     */
    public function extractCompetitorKeywords(string $baseKeyword, string $categoryId, int $limit = 20): array
    {
        $analysis = $this->client->getCompetitorAnalysis($baseKeyword, $categoryId);
        $titles = array_column($analysis['top_performers'] ?? [], 'title');
        $keywords = [];

        foreach ($titles as $title) {
            if (!is_string($title)) {
                continue;
            }
            $words = preg_split('/\s+/', mb_strtolower($title));
            foreach ($words as $word) {
                $word = preg_replace('/[^\p{L}\p{N}]/u', '', $word);
                if ($word === '' || in_array($word, self::STOP_WORDS, true)) {
                    continue;
                }
                $keywords[] = $word;
            }
        }

        $keywords = array_values(array_unique($keywords));
        return array_slice($keywords, 0, $limit);
    }

    /**
     * Get keywords for a category and base keyword
     */
    public function getKeywords(string $categoryId, string $baseKeyword): array
    {
        // This would typically call external APIs or database
        // For now, returning a combination of base keyword and related terms
        
        $keywords = [$baseKeyword];
        
        // Add variations and related terms
        $variations = $this->generateKeywordVariations($baseKeyword);
        $keywords = array_merge($keywords, $variations);
        
        // Add category-specific terms
        $categoryTerms = $this->getCategorySpecificTerms($categoryId);
        $keywords = array_merge($keywords, $categoryTerms);
        
        return array_unique($keywords);
    }

    /**
     * Classifica keywords por tipo
     * @return array ['core' => [], 'suporte' => [], 'tecnica' => [], 'contexto' => []]
     */
    public function classifyByType(array $keywords, string $categoryId): array
    {
        $classification = [
            'core' => [],
            'suporte' => [],
            'tecnica' => [],
            'contexto' => []
        ];
        
        foreach ($keywords as $keyword) {
            $type = $this->classifySingleKeyword($keyword, $categoryId);
            $classification[$type][] = $keyword;
        }
        
        return $classification;
    }

    /**
     * Calcula volume de busca estimado
     */
    public function estimateSearchVolume(string $keyword, ?string $categoryId = null): array
    {
        $length = max(1, mb_strlen($keyword));
        $baseVolume = max(50, 2000 - ($length * 50));

        return [
            'keyword' => $keyword,
            'category_id' => $categoryId,
            'monthly_volume' => $baseVolume,
            'competition' => $this->estimateCompetition($keyword),
            'trend' => 0
        ];
    }

    /**
     * Retorna keywords com score de competição
     */
    public function getWithCompetitionScore(array $keywords): array
    {
        $result = [];
        
        foreach ($keywords as $keyword) {
            $result[] = [
                'keyword' => $keyword,
                'competition_score' => $this->estimateCompetition($keyword)
            ];
        }
        
        return $result;
    }

    /**
     * Generate keyword variations
     */
    public function generateKeywordVariations(string $baseKeyword): array
    {
        $variations = [];
        $words = explode(' ', $baseKeyword);
        
        // Add singular/plural variations if applicable
        foreach ($words as $word) {
            if (substr($word, -1) === 's') {
                // Word ends in 's', might be plural
                $singular = rtrim($word, 's');
                $variations[] = str_replace($word, $singular, $baseKeyword);
            } else {
                // Add plural form
                $plural = $word . 's';
                $variations[] = str_replace($word, $plural, $baseKeyword);
            }
        }
        
        // Add common modifiers
        $modifiers = ['barato', 'original', 'novo', 'usado', 'premium', 'economico'];
        foreach ($modifiers as $modifier) {
            $variations[] = $modifier . ' ' . $baseKeyword;
            $variations[] = $baseKeyword . ' ' . $modifier;
        }
        
        return $variations;
    }

    /**
     * Get category-specific terms
     */
    private function getCategorySpecificTerms(string $categoryId): array
    {
        // Define some common category terms
        $categoryTerms = [
            'MLB3530' => [ // Baús/Bagageiros
                'baú', 'bauleto', 'bagageiro', 'maleiro', 'porta objetos', 'compartimento'
            ],
            'MLB1071' => [ // Capacetes
                'capacete', 'viseira', 'concha', 'abajur', 'protetor'
            ],
            'MLB1234' => [ // Generic category
                'produto', 'item', 'artigo', 'equipamento', 'acessório'
            ]
        ];
        
        return $categoryTerms[$categoryId] ?? ['produto', 'item', 'artigo'];
    }

    /**
     * Classify a single keyword
     */
    private function classifySingleKeyword(string $keyword, string $categoryId): string
    {
        $keywordLower = strtolower($keyword);
        
        // Core keywords are typically the main product terms
        $coreTerms = ['produto', 'item', 'modelo', 'marca'];
        foreach ($coreTerms as $term) {
            if (strpos($keywordLower, $term) !== false) {
                return 'core';
            }
        }
        
        // Technical keywords describe specifications
        $techTerms = ['medida', 'tamanho', 'capacidade', 'material', 'cor', 'peso', 'dimensão'];
        foreach ($techTerms as $term) {
            if (strpos($keywordLower, $term) !== false) {
                return 'tecnica';
            }
        }
        
        // Context keywords relate to usage
        $contextTerms = ['uso', 'aplicação', 'função', 'finalidade'];
        foreach ($contextTerms as $term) {
            if (strpos($keywordLower, $term) !== false) {
                return 'contexto';
            }
        }
        
        // Default to support
        return 'suporte';
    }

    /**
     * Estimate competition for a keyword
     */
    private function estimateCompetition(string $keyword): float
    {
        $length = max(1, mb_strlen($keyword));
        $genericPenalty = $length < 6 ? 0.9 : 0.6;
        $score = 1 / ($length * 0.15 + 0.7);
        return min(1.0, round($score * $genericPenalty, 2));
    }
}
