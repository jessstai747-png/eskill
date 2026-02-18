<?php
declare(strict_types=1);

namespace App\Services\SEO;

use App\Services\AI\Utils\CacheManager;
use App\Services\KeywordResearchService;
use App\Services\MercadoLivreClient;

class KeywordSourceService
{
    private MercadoLivreClient $mlClient;
    private KeywordResearchService $keywordResearch;
    private CacheManager $cacheManager;
    private string $siteId;

    // Prioridade de fontes
    private const SOURCE_PRIORITY = [
        'database' => 1,  // Cache primeiro (mais rápido)
        'ml_api' => 2,    // ML API segundo (dados frescos)
        'ai' => 3         // AI terceiro (expansão/geração)
    ];

    public function __construct(?int $accountId = null)
    {
        $config = require __DIR__ . '/../../../config/app.php';
        $this->siteId = $config['mercadolivre']['site_id'] ?? 'MLB';
        $this->mlClient = new MercadoLivreClient($accountId);
        $this->keywordResearch = new KeywordResearchService($accountId);
        $this->cacheManager = new CacheManager();
    }

    /**
     * Obtém keywords usando arquitetura híbrida
     */
    public function getKeywords(string $categoryId, string $baseKeyword): array
    {
        $query = trim($baseKeyword);
        if ($query === '') {
            $query = $categoryId;
        }

        $cached = $this->fetchFromDatabase($categoryId, $query);
        if (!empty($cached)) {
            return $this->ensureKeywordPayload($cached, $categoryId, $query, 'database');
        }

        $mlKeywords = $this->fetchFromMLAPI($categoryId, $query);
        if (!empty($mlKeywords)) {
            $payload = $this->buildPayload($categoryId, $query, 'ml_api', $mlKeywords);
            $this->cacheKeywords($categoryId, $query, $payload);
            return $payload;
        }

        $aiKeywords = $this->generateViaAI($categoryId, $query);
        $payload = $this->buildPayload($categoryId, $query, 'ai', $aiKeywords);
        $this->cacheKeywords($categoryId, $query, $payload);

        return $payload;
    }

    /**
     * Gera keywords via AI (endpoint dedicado)
     */
    public function generateKeywords(string $categoryId, string $baseKeyword): array
    {
        $query = trim($baseKeyword);
        if ($query === '') {
            $query = $categoryId;
        }

        $aiKeywords = $this->generateViaAI($categoryId, $query);
        $payload = $this->buildPayload($categoryId, $query, 'ai', $aiKeywords);
        $this->cacheKeywords($categoryId, $query, $payload);

        return $payload;
    }

    /**
     * Busca no cache local primeiro
     */
    private function fetchFromDatabase(string $categoryId, string $query): ?array
    {
        $cached = $this->cacheManager->getKeywords($query, $categoryId);
        if (is_array($cached)) {
            return $cached;
        }

        return null;
    }

    /**
     * Busca via ML API (Trends, Autocomplete, Atributos)
     */
    private function fetchFromMLAPI(string $categoryId, string $keyword): array
    {
        $response = $this->mlClient->get("/sites/{$this->siteId}/search", [
            'q' => $keyword,
            'category' => $categoryId,
            'limit' => 10,
        ], true);

        if (empty($response['success']) || empty($response['body']['results'])) {
            return [];
        }

        $titles = [];
        foreach ($response['body']['results'] as $item) {
            if (!empty($item['title'])) {
                $titles[] = (string)$item['title'];
            }
        }

        return $this->extractKeywordsFromTitles($titles, 'ml_api');
    }

    /**
     * Gera/expande via AI quando não há dados
     */
    private function generateViaAI(string $categoryId, string $keyword): array
    {
        $results = $this->keywordResearch->getKeywords($categoryId, $keyword);
        return $this->normalizeKeywordList($results, 'ai');
    }

    /**
     * Salva keywords no cache para uso futuro
     */
    private function cacheKeywords(string $categoryId, string $query, array $payload): void
    {
        $this->cacheManager->setKeywords($query, $categoryId, $payload);
    }

    /**
     * Invalida cache quando dados ficam obsoletos
     */
    public function invalidateCache(string $categoryId): void
    {
        $this->cacheManager->invalidateKeywordsByCategory($categoryId);
    }

    private function extractKeywordsFromTitles(array $titles, string $source): array
    {
        $counts = [];
        foreach ($titles as $title) {
            $tokens = $this->extractKeywordsFromText($title);
            foreach ($tokens as $token) {
                $key = mb_strtolower($token);
                if (!isset($counts[$key])) {
                    $counts[$key] = ['keyword' => $token, 'count' => 0];
                }
                $counts[$key]['count']++;
            }
        }

        usort($counts, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        $keywords = [];
        foreach (array_slice($counts, 0, 20) as $item) {
            $keywords[] = [
                'keyword' => $item['keyword'],
                'score' => min(100, 50 + ($item['count'] * 10)),
                'source' => $source,
            ];
        }

        return $keywords;
    }

    private function extractKeywordsFromText(string $text): array
    {
        $clean = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text));
        $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

        $tokens = [];
        foreach ($words as $word) {
            if (mb_strlen($word) > 2) {
                $tokens[] = $word;
            }
        }

        return array_values(array_unique($tokens));
    }

    private function normalizeKeywordList(array $keywords, string $source): array
    {
        $items = [];
        foreach ($keywords as $keyword) {
            $value = $this->normalizeKeywordItem($keyword);
            if ($value === '') {
                continue;
            }

            $items[] = [
                'keyword' => $value,
                'score' => 70,
                'source' => $source,
            ];
        }

        return $this->dedupeKeywordObjects($items);
    }

    private function normalizeKeywordItem($item): string
    {
        if (is_string($item)) {
            return trim($item);
        }

        if (is_array($item)) {
            if (!empty($item['keyword'])) {
                return trim((string)$item['keyword']);
            }
            if (!empty($item['word'])) {
                return trim((string)$item['word']);
            }
        }

        return '';
    }

    private function dedupeKeywordObjects(array $keywords): array
    {
        $unique = [];
        foreach ($keywords as $item) {
            $value = trim((string)($item['keyword'] ?? ''));
            if ($value === '') {
                continue;
            }

            $key = mb_strtolower($value);
            if (!isset($unique[$key])) {
                $unique[$key] = $item;
            }
        }

        return array_values($unique);
    }

    private function buildPayload(string $categoryId, string $query, string $source, array $keywords): array
    {
        return [
            'category_id' => $categoryId,
            'base_keyword' => $query,
            'source' => $source,
            'keywords' => $keywords,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function ensureKeywordPayload(array $payload, string $categoryId, string $query, string $source): array
    {
        if (isset($payload['keywords'])) {
            return $payload;
        }

        return $this->buildPayload($categoryId, $query, $source, $payload);
    }
}