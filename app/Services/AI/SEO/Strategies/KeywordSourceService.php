<?php

declare(strict_types=1);

namespace App\Services\AI\SEO\Strategies;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\AI\ML\SynonymGenerator;
use App\Services\TrendsService;
use PDO;
use Exception;

/**
 * KeywordSourceService - Arquitetura Híbrida de Keywords
 *
 * Orquestra a obtenção de keywords de 3 fontes:
 * 1. DATABASE (Prioridade 1): Cache curado, hierarquias validadas
 * 2. ML API (Prioridade 2): Trends, autocomplete, atributos oficiais
 * 3. AI/LLM (Prioridade 3): Geração de sinônimos, expansão, fallback
 *
 * @package App\Services\AI\SEO\Strategies
 */
class KeywordSourceService
{
    private PDO $db;
    private ?MercadoLivreClient $mlClient = null;
    private ?SynonymGenerator $aiGenerator = null;
    private ?TrendsService $trendsService = null;
    private ?int $accountId;

    /**
     * Configuração de prioridade de fontes
     */
    private const SOURCE_PRIORITY = [
        'database' => 1,
        'ml_api' => 2,
        'ai' => 3
    ];

    /**
     * TTL do cache em segundos (24 horas)
     */
    private const CACHE_TTL = 86400;

    /**
     * Mínimo de keywords necessárias
     */
    private const MIN_KEYWORDS = 15;

    /**
     * Cache em memória
     */
    private array $memoryCache = [];

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * Obtém keywords usando arquitetura híbrida
     *
     * @param string $categoryId ID da categoria
     * @param string $baseKeyword Palavra-chave base (título ou termo)
     * @param array $options Opções adicionais
     * @return array Lista de keywords com metadados
     */
    public function getKeywords(string $categoryId, string $baseKeyword, array $options = []): array
    {
        $cacheKey = $this->buildCacheKey($categoryId, $baseKeyword);

        // 1. Verificar cache em memória
        if (isset($this->memoryCache[$cacheKey])) {
            return $this->memoryCache[$cacheKey];
        }

        $keywords = [];
        $sources = [];

        // 2. Buscar no banco de dados (fonte primária)
        $dbKeywords = $this->fetchFromDatabase($categoryId, $baseKeyword);
        if (!empty($dbKeywords)) {
            $keywords = $dbKeywords;
            $sources[] = 'database';
        }

        // 3. Se não houver keywords suficientes, buscar via ML API
        if (count($keywords) < self::MIN_KEYWORDS) {
            $mlKeywords = $this->fetchFromMLAPI($categoryId, $baseKeyword);
            if (!empty($mlKeywords)) {
                $keywords = $this->mergeKeywords($keywords, $mlKeywords);
                $sources[] = 'ml_api';
            }
        }

        // 4. Se ainda não houver keywords suficientes, gerar via AI
        if (count($keywords) < self::MIN_KEYWORDS) {
            $aiKeywords = $this->generateViaAI($categoryId, $baseKeyword);
            if (!empty($aiKeywords)) {
                $keywords = $this->mergeKeywords($keywords, $aiKeywords);
                $sources[] = 'ai';
            }
        }

        // 5. Classificar keywords
        $keywords = $this->classifyKeywords($keywords);

        // 6. Salvar no cache
        $this->cacheKeywords($categoryId, $baseKeyword, $keywords);

        $result = [
            'success' => true,
            'category_id' => $categoryId,
            'base_keyword' => $baseKeyword,
            'keywords' => $keywords,
            'total_count' => count($keywords),
            'sources' => $sources,
            'cached' => false
        ];

        $this->memoryCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Busca keywords por tipo específico
     *
     * @param string $categoryId ID da categoria
     * @param string $baseKeyword Palavra-chave base
     * @param string $type Tipo: core, suporte, tecnica, contexto
     * @return array Keywords filtradas por tipo
     */
    public function getKeywordsByType(string $categoryId, string $baseKeyword, string $type): array
    {
        $allKeywords = $this->getKeywords($categoryId, $baseKeyword);

        if (!$allKeywords['success']) {
            return [];
        }

        return array_filter(
            $allKeywords['keywords'],
            fn(array $kw): bool => ($kw['type'] ?? 'core') === $type
        );
    }

    /**
     * Busca keywords de tendências atuais
     *
     * @param string $categoryId ID da categoria
     * @return array Keywords em tendência
     */
    public function getTrendingKeywords(string $categoryId): array
    {
        try {
            $trends = $this->getTrendsService();
            $trendingData = $trends->getCategoryTrends($categoryId);

            if (empty($trendingData)) {
                return [];
            }

            return array_map(function (array $trend): array {
                return [
                    'keyword' => $trend['keyword'] ?? $trend['term'] ?? '',
                    'volume' => $trend['volume'] ?? 0,
                    'growth' => $trend['growth'] ?? 0,
                    'type' => 'trending',
                    'source' => 'ml_api'
                ];
            }, $trendingData);
        } catch (Exception $e) {
            log_warning('Erro ao buscar trends do ML', [
                'service' => 'KeywordSourceService',
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Busca keywords via autocomplete do ML
     *
     * @param string $query Termo de busca
     * @param string $categoryId ID da categoria (opcional)
     * @return array Sugestões de autocomplete
     */
    public function getAutocompleteKeywords(string $query, ?string $categoryId = null): array
    {
        try {
            $client = $this->getMLClient();
            $suggestions = $client->getAutocompleteSuggestions($query, $categoryId);

            return array_map(function (mixed $suggestion): array {
                if (is_array($suggestion)) {
                    $keyword = (string)($suggestion['q'] ?? $suggestion['suggestion'] ?? $suggestion['term'] ?? '');
                } else {
                    $keyword = (string)$suggestion;
                }

                return [
                    'keyword' => $keyword,
                    'type' => 'autocomplete',
                    'source' => 'ml_api'
                ];
            }, is_array($suggestions) ? $suggestions : []);
        } catch (Exception $e) {
            log_warning('Erro no autocomplete ML', [
                'service' => 'KeywordSourceService',
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Invalida cache de uma categoria
     *
     * @param string $categoryId ID da categoria
     * @return bool Sucesso da operação
     */
    public function invalidateCache(string $categoryId): bool
    {
        try {
            // Limpar cache do banco
            $stmt = $this->db->prepare("
                UPDATE seo_keyword_cache
                SET is_valid = 0
                WHERE category_id = :category_id
            ");
            $stmt->execute(['category_id' => $categoryId]);

            // Limpar cache em memória
            foreach (array_keys($this->memoryCache) as $key) {
                if (str_starts_with($key, "kw_{$categoryId}_")) {
                    unset($this->memoryCache[$key]);
                }
            }

            return true;
        } catch (Exception $e) {
            log_warning('Erro ao invalidar cache de keywords', [
                'service' => 'KeywordSourceService',
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Força refresh de keywords (ignora cache)
     *
     * @param string $categoryId ID da categoria
     * @param string $baseKeyword Palavra-chave base
     * @return array Keywords atualizadas
     */
    public function refreshKeywords(string $categoryId, string $baseKeyword): array
    {
        // Invalidar cache primeiro
        $cacheKey = $this->buildCacheKey($categoryId, $baseKeyword);
        unset($this->memoryCache[$cacheKey]);

        $this->invalidateCacheEntry($categoryId, $baseKeyword);

        // Buscar novamente
        return $this->getKeywords($categoryId, $baseKeyword);
    }

    /**
     * Obtém keywords de concorrentes
     *
     * @param string $categoryId ID da categoria
     * @param int $limit Limite de concorrentes a analisar
     * @return array Keywords extraídas de concorrentes
     */
    public function getCompetitorKeywords(string $categoryId, int $limit = 10): array
    {
        try {
            $client = $this->getMLClient();

            // Buscar top items da categoria
            $topItems = $client->searchItems([
                'category' => $categoryId,
                'sort' => 'sold_quantity_desc',
                'limit' => $limit
            ]);

            if (empty($topItems['results'])) {
                return [];
            }

            $keywords = [];
            foreach ($topItems['results'] as $item) {
                // Extrair keywords do título
                $titleKeywords = $this->extractKeywordsFromText($item['title'] ?? '');
                foreach ($titleKeywords as $kw) {
                    $keywords[$kw] = ($keywords[$kw] ?? 0) + 1;
                }
            }

            // Ordenar por frequência
            arsort($keywords);

            return array_map(function ($keyword, $frequency) {
                return [
                    'keyword' => $keyword,
                    'frequency' => $frequency,
                    'type' => 'competitor',
                    'source' => 'ml_api'
                ];
            }, array_keys($keywords), array_values($keywords));
        } catch (Exception $e) {
            log_warning('Erro ao buscar keywords de concorrentes', [
                'service' => 'KeywordSourceService',
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Estima volume de busca para uma keyword
     *
     * @param string $keyword Palavra-chave
     * @param string $categoryId ID da categoria
     * @return array Estimativa de volume
     */
    public function estimateSearchVolume(string $keyword, string $categoryId): array
    {
        try {
            // Buscar via trends
            $trends = $this->getTrendsService();
            $volume = $trends->estimateVolume($keyword, $categoryId);

            return [
                'keyword' => $keyword,
                'estimated_volume' => $volume['volume'] ?? 0,
                'confidence' => $volume['confidence'] ?? 'low',
                'trend' => $volume['trend'] ?? 'stable'
            ];
        } catch (Exception $e) {
            return [
                'keyword' => $keyword,
                'estimated_volume' => 0,
                'confidence' => 'none',
                'trend' => 'unknown'
            ];
        }
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Busca keywords no banco de dados
     */
    private function fetchFromDatabase(string $categoryId, string $baseKeyword): array
    {
        $keywords = [];

        try {
            // Buscar do cache de keywords
            $stmt = $this->db->prepare("
                SELECT keyword, type, weight, source
                FROM seo_keyword_cache
                WHERE category_id = :category_id
                AND is_valid = 1
                AND expires_at > NOW()
                ORDER BY weight DESC
                LIMIT 100
            ");
            $stmt->execute(['category_id' => $categoryId]);
            $cached = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($cached as $row) {
                $keywords[] = [
                    'keyword' => $row['keyword'],
                    'type' => $row['type'] ?? 'core',
                    'weight' => (float) ($row['weight'] ?? 1.0),
                    'source' => 'database'
                ];
            }

            // Buscar também da hierarquia de sinônimos
            $stmt2 = $this->db->prepare("
                SELECT word as keyword, level, weight
                FROM seo_synonym_hierarchy
                WHERE category_id = :category_id
                AND is_active = 1
                ORDER BY weight DESC
                LIMIT 50
            ");
            $stmt2->execute(['category_id' => $categoryId]);
            $synonyms = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            foreach ($synonyms as $row) {
                $type = $this->levelToType($row['level']);
                $keywords[] = [
                    'keyword' => $row['keyword'],
                    'type' => $type,
                    'weight' => (float) ($row['weight'] ?? 1.0),
                    'source' => 'database'
                ];
            }
        } catch (Exception $e) {
            log_warning('Erro ao buscar keywords do banco (tabelas podem não existir)', [
                'service' => 'KeywordSourceService',
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
        }

        return $keywords;
    }

    /**
     * Busca keywords via ML API
     */
    private function fetchFromMLAPI(string $categoryId, string $baseKeyword): array
    {
        $keywords = [];

        try {
            $client = $this->getMLClient();

            // 1. Autocomplete
            $autocomplete = $this->getAutocompleteKeywords($baseKeyword, $categoryId);
            $keywords = array_merge($keywords, $autocomplete);

            // 2. Trends
            $trends = $this->getTrendingKeywords($categoryId);
            $keywords = array_merge($keywords, $trends);

            // 3. Atributos da categoria
            $attributes = $client->getCategoryAttributes($categoryId);
            foreach ($attributes as $attr) {
                if (!empty($attr['values'])) {
                    foreach (array_slice($attr['values'], 0, 5) as $value) {
                        $keywords[] = [
                            'keyword' => $value['name'] ?? $value,
                            'type' => 'tecnica',
                            'source' => 'ml_api'
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            log_warning('Erro ao buscar keywords da ML API', [
                'service' => 'KeywordSourceService',
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
        }

        return $keywords;
    }

    /**
     * Gera keywords via AI/LLM
     */
    private function generateViaAI(string $categoryId, string $baseKeyword): array
    {
        try {
            $generator = $this->getAIGenerator();
            $generated = $generator->generateKeywords($baseKeyword, $categoryId);

            return array_map(function (mixed $kw): array {
                return [
                    'keyword' => is_array($kw) ? $kw['keyword'] : $kw,
                    'type' => is_array($kw) ? ($kw['type'] ?? 'core') : 'core',
                    'weight' => is_array($kw) ? ($kw['weight'] ?? 0.8) : 0.8,
                    'source' => 'ai'
                ];
            }, $generated);
        } catch (Exception $e) {
            log_warning('Erro ao gerar keywords via AI', [
                'service' => 'KeywordSourceService',
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Classifica keywords por tipo
     */
    private function classifyKeywords(array $keywords): array
    {
        foreach ($keywords as &$kw) {
            if (isset($kw['type'])) {
                continue;
            }

            $word = $kw['keyword'] ?? '';
            $wordCount = str_word_count($word);

            if ($wordCount <= 2) {
                $kw['type'] = 'core';
            } elseif ($wordCount <= 4) {
                $kw['type'] = 'suporte';
            } elseif ($this->isTechnicalKeyword($word)) {
                $kw['type'] = 'tecnica';
            } else {
                $kw['type'] = 'contexto';
            }
        }

        return $keywords;
    }

    /**
     * Verifica se é keyword técnica
     */
    private function isTechnicalKeyword(string $word): bool
    {
        $technicalPatterns = [
            '/\d+\s*(mm|cm|m|kg|g|l|ml|w|v|a|hz)/i',
            '/\d+x\d+/i',
            '/(ip\d+|led|lcd|hdmi|usb|wifi)/i'
        ];

        foreach ($technicalPatterns as $pattern) {
            if (preg_match($pattern, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mescla listas de keywords removendo duplicatas
     */
    private function mergeKeywords(array $existing, array $new): array
    {
        $merged = $existing;
        $existingWords = array_map(
            fn(array $kw): string => mb_strtolower($kw['keyword'] ?? ''),
            $existing
        );

        foreach ($new as $kw) {
            $word = mb_strtolower($kw['keyword'] ?? '');
            if (!in_array($word, $existingWords) && !empty($word)) {
                $merged[] = $kw;
                $existingWords[] = $word;
            }
        }

        return $merged;
    }

    /**
     * Salva keywords no cache do banco
     */
    private function cacheKeywords(string $categoryId, string $baseKeyword, array $keywords): void
    {
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + self::CACHE_TTL);

            foreach ($keywords as $kw) {
                $stmt = $this->db->prepare("
                    INSERT INTO seo_keyword_cache
                    (category_id, base_keyword, keyword, type, weight, source, is_valid, expires_at, created_at)
                    VALUES (:category_id, :base_keyword, :keyword, :type, :weight, :source, 1, :expires_at, NOW())
                    ON DUPLICATE KEY UPDATE
                        type = :type2,
                        weight = :weight2,
                        source = :source2,
                        is_valid = 1,
                        expires_at = :expires_at2
                ");

                $stmt->execute([
                    'category_id' => $categoryId,
                    'base_keyword' => $baseKeyword,
                    'keyword' => $kw['keyword'] ?? '',
                    'type' => $kw['type'] ?? 'core',
                    'weight' => $kw['weight'] ?? 1.0,
                    'source' => $kw['source'] ?? 'unknown',
                    'expires_at' => $expiresAt,
                    'type2' => $kw['type'] ?? 'core',
                    'weight2' => $kw['weight'] ?? 1.0,
                    'source2' => $kw['source'] ?? 'unknown',
                    'expires_at2' => $expiresAt
                ]);
            }
        } catch (Exception $e) {
            log_warning('Erro ao salvar cache de keywords', [
                'service' => 'KeywordSourceService',
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Invalida entrada específica do cache
     */
    private function invalidateCacheEntry(string $categoryId, string $baseKeyword): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE seo_keyword_cache
                SET is_valid = 0
                WHERE category_id = :category_id AND base_keyword = :base_keyword
            ");
            $stmt->execute([
                'category_id' => $categoryId,
                'base_keyword' => $baseKeyword
            ]);
        } catch (Exception $e) {
            // Ignorar erro se tabela não existir
        }
    }

    /**
     * Constrói chave de cache
     */
    private function buildCacheKey(string $categoryId, string $baseKeyword): string
    {
        return "kw_{$categoryId}_" . md5($baseKeyword);
    }

    /**
     * Converte nível hierárquico para tipo de keyword
     */
    private function levelToType(string $level): string
    {
        $mapping = [
            'nivel_1' => 'core',
            'nivel_2' => 'suporte',
            'nivel_3' => 'contexto',
            'nivel_4' => 'contexto'
        ];

        return $mapping[$level] ?? 'core';
    }

    /**
     * Extrai keywords de um texto
     */
    private function extractKeywordsFromText(string $text): array
    {
        $stopwords = ['de', 'da', 'do', 'e', 'para', 'com', 'em', 'a', 'o', 'um', 'uma', 'os', 'as'];

        $words = preg_split('/\s+/', mb_strtolower($text));
        $words = array_filter($words, function (string $w) use ($stopwords): bool {
            return strlen($w) > 2 && !in_array($w, $stopwords) && !is_numeric($w);
        });

        return array_values(array_unique($words));
    }

    /**
     * Obtém instância do MercadoLivreClient
     */
    private function getMLClient(): MercadoLivreClient
    {
        if ($this->mlClient === null) {
            $this->mlClient = new MercadoLivreClient($this->accountId);
        }
        return $this->mlClient;
    }

    /**
     * Obtém instância do SynonymGenerator
     */
    private function getAIGenerator(): SynonymGenerator
    {
        if ($this->aiGenerator === null) {
            $this->aiGenerator = new SynonymGenerator($this->accountId);
        }
        return $this->aiGenerator;
    }

    /**
     * Obtém instância do TrendsService
     */
    private function getTrendsService(): TrendsService
    {
        if ($this->trendsService === null) {
            $this->trendsService = new TrendsService($this->accountId);
        }
        return $this->trendsService;
    }
}
