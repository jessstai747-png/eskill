<?php
declare(strict_types=1);

namespace App\Services\SEO;

use App\Database;
use PDO;
use Throwable;

class SynonymExpansionService
{
    private PDO $db;
    private ?int $accountId;
    /** @var array<string, array{weight: float, destination: string}> */
    private const DEFAULT_HIERARCHY = [
        'nivel_1_generico' => ['weight' => 1.00, 'destination' => 'title'],
        'nivel_2_qualificado' => ['weight' => 0.70, 'destination' => 'model'],
        'nivel_3_contextual' => ['weight' => 0.50, 'destination' => 'description'],
        'nivel_4_long_tail' => ['weight' => 0.30, 'destination' => 'keywords'],
    ];

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    public function expand(string $title, string $categoryId): array
    {
        $titleWords = $this->extractKeywords($title);
        $expanded = $this->buildFallbackSynonyms($title);

        foreach ($this->loadHierarchySynonymsFromDb($categoryId) as $level => $words) {
            $expanded[$level] = array_merge($expanded[$level] ?? [], $words);
        }

        foreach (array_keys(self::DEFAULT_HIERARCHY) as $level) {
            $unique = [];
            $seen = [];
            foreach ($expanded[$level] ?? [] as $word) {
                $normalized = $this->normalize($word);
                if ($normalized === '' || isset($seen[$normalized])) {
                    continue;
                }
                if ($this->containsAnyTitleKeyword($word, $titleWords)) {
                    continue;
                }
                $seen[$normalized] = true;
                $unique[] = $word;
            }
            $expanded[$level] = $unique;
        }

        return $expanded;
    }

    public function getHierarchy(string $categoryId): array
    {
        $hierarchy = self::DEFAULT_HIERARCHY;

        try {
            $stmt = $this->db->prepare("
                SELECT level, weight, destination
                FROM seo_synonym_hierarchy
                WHERE category_id = :category_id AND is_active = 1
            ");
            $stmt->execute(['category_id' => $categoryId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $canonicalLevel = $this->canonicalizeLevel((string)($row['level'] ?? ''));
                if ($canonicalLevel === null) {
                    continue;
                }

                if (isset($row['weight'])) {
                    $hierarchy[$canonicalLevel]['weight'] = (float)$row['weight'];
                }
                if (isset($row['destination']) && trim((string)$row['destination']) !== '') {
                    $hierarchy[$canonicalLevel]['destination'] = (string)$row['destination'];
                }
            }
        } catch (Throwable $e) {
            // fallback silencioso para hierarquia padrão
        }

        return $hierarchy;
    }

    public function identifyLevel(string $text): string
    {
        $wordCount = count($this->extractKeywords($text));
        if ($wordCount <= 4) {
            return 'nivel_1';
        }
        if ($wordCount <= 6) {
            return 'nivel_2';
        }
        if ($wordCount <= 9) {
            return 'nivel_3';
        }
        return 'nivel_4';
    }

    public function selectForField(string $title, string $field, string $categoryId): array
    {
        $expanded = $this->expand($title, $categoryId);
        $hierarchy = $this->getHierarchy($categoryId);

        $levelByField = [
            'title' => ['nivel_1_generico'],
            'model' => ['nivel_2_qualificado', 'nivel_3_contextual'],
            'description' => ['nivel_3_contextual', 'nivel_4_long_tail'],
            'keywords' => ['nivel_1_generico', 'nivel_2_qualificado', 'nivel_3_contextual', 'nivel_4_long_tail'],
        ];

        $levels = $levelByField[$field] ?? $levelByField['keywords'];
        $selected = [];

        foreach ($levels as $level) {
            $weight = (float)($hierarchy[$level]['weight'] ?? 0.5);
            foreach ($expanded[$level] ?? [] as $word) {
                $selected[] = [
                    'word' => $word,
                    'level' => $level,
                    'score' => round($weight * 100, 2),
                ];
            }
        }

        usort($selected, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        $limits = [
            'title' => 3,
            'model' => 7,
            'description' => 10,
            'keywords' => 15,
        ];
        $limit = $limits[$field] ?? 10;

        return array_slice($selected, 0, $limit);
    }

    public function generateOptimizedModel(string $title, string $categoryId): array
    {
        $selected = $this->selectForField($title, 'model', $categoryId);
        $parts = [];
        $used = [];

        foreach ($selected as $candidate) {
            $next = trim(implode(' ', array_merge($parts, [(string)$candidate['word']])));
            if ($next === '' || mb_strlen($next) > 250) {
                continue;
            }
            $parts[] = (string)$candidate['word'];
            $used[] = $candidate;
        }

        $model = trim(implode(' ', $parts));
        $score = 0.0;
        if ($used !== []) {
            $score = array_sum(array_column($used, 'score')) / count($used);
        }

        return [
            'model' => $model,
            'synonyms_used' => $used,
            'score' => round($score, 2),
        ];
    }

    public function generateAISynonyms(string $term, string $categoryId, int $limit = 5): array
    {
        $base = trim($term);
        if ($base === '' || $limit <= 0) {
            return [];
        }

        $categoryToken = strtoupper(substr($categoryId, 0, 3));
        $variants = [
            $base . ' premium',
            $base . ' profissional',
            $base . ' original',
            $base . ' alta performance',
            $base . ' ' . $categoryToken,
            'linha ' . $base,
            $base . ' reforcado',
        ];

        $normalizedBase = $this->normalize($base);
        $result = [];
        $seen = [];
        foreach ($variants as $variant) {
            $normalized = $this->normalize($variant);
            if ($normalized === '' || $normalized === $normalizedBase || isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;
            $result[] = $variant;
            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    /**
     * @return array<string, list<string>>
     */
    private function buildFallbackSynonyms(string $title): array
    {
        $keywords = $this->extractKeywords($title);
        $keywordsText = strtolower(implode(' ', $keywords));
        $isMoto = str_contains($keywordsText, 'moto') || str_contains($keywordsText, 'bauleto') || str_contains($keywordsText, 'bau');

        return [
            'nivel_1_generico' => $isMoto ? ['bagageiro', 'acessorio moto'] : ['acessorio', 'produto premium'],
            'nivel_2_qualificado' => $isMoto ? ['bagageiro traseiro', 'bagageiro universal'] : ['uso profissional', 'alta resistencia'],
            'nivel_3_contextual' => $isMoto ? ['ideal para viagens', 'instalacao simples'] : ['durabilidade elevada', 'acabamento premium'],
            'nivel_4_long_tail' => $isMoto ? ['para motociclistas de uso urbano', 'para transporte seguro no dia a dia'] : ['para uso cotidiano com alta confiabilidade'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function loadHierarchySynonymsFromDb(string $categoryId): array
    {
        $result = [
            'nivel_1_generico' => [],
            'nivel_2_qualificado' => [],
            'nivel_3_contextual' => [],
            'nivel_4_long_tail' => [],
        ];

        try {
            $stmt = $this->db->prepare("
                SELECT level, word
                FROM seo_synonym_hierarchy
                WHERE category_id = :category_id AND is_active = 1
                ORDER BY weight DESC
            ");
            $stmt->execute(['category_id' => $categoryId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $level = $this->canonicalizeLevel((string)($row['level'] ?? ''));
                $word = trim((string)($row['word'] ?? ''));
                if ($level === null || $word === '') {
                    continue;
                }
                $result[$level][] = $word;
            }
        } catch (Throwable $e) {
            // fallback sem dados de banco
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function extractKeywords(string $title): array
    {
        $tokens = preg_split('/\s+/u', trim(mb_strtolower($title)));
        if (!is_array($tokens)) {
            return [];
        }

        $keywords = [];
        foreach ($tokens as $token) {
            $clean = preg_replace('/[^a-z0-9à-ÿ]+/iu', '', $token);
            if (!is_string($clean) || $clean === '') {
                continue;
            }
            $keywords[] = $clean;
        }

        return $keywords;
    }

    /**
     * @param list<string> $titleWords
     */
    private function containsAnyTitleKeyword(string $phrase, array $titleWords): bool
    {
        $phraseWords = $this->extractKeywords($phrase);
        if ($phraseWords === [] || $titleWords === []) {
            return false;
        }

        $titleMap = [];
        foreach ($titleWords as $word) {
            $titleMap[$this->normalize($word)] = true;
        }

        foreach ($phraseWords as $word) {
            if (isset($titleMap[$this->normalize($word)])) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);

        return trim((string)$value);
    }

    private function canonicalizeLevel(string $level): ?string
    {
        $normalized = $this->normalize($level);

        return match ($normalized) {
            'nivel 1', 'nivel_1', 'nivel 1 generico', 'nivel_1_generico' => 'nivel_1_generico',
            'nivel 2', 'nivel_2', 'nivel 2 qualificado', 'nivel_2_qualificado' => 'nivel_2_qualificado',
            'nivel 3', 'nivel_3', 'nivel 3 contextual', 'nivel_3_contextual' => 'nivel_3_contextual',
            'nivel 4', 'nivel_4', 'nivel 4 long tail', 'nivel_4_long_tail' => 'nivel_4_long_tail',
            default => null,
        };
    }
}
