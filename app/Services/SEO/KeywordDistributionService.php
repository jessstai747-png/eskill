<?php
declare(strict_types=1);

namespace App\Services\SEO;

class KeywordDistributionService
{
    private const FIELD_WEIGHTS = [
        'title' => 10,
        'model' => 8,
        'description' => 6,
        'attributes' => 4
    ];

    private const FIELD_LIMITS = [
        'title' => ['min' => 3, 'max' => 5],
        'model' => ['min' => 2, 'max' => 4],
        'description' => ['min' => 8, 'max' => 15],
        'attributes' => ['min' => 3, 'max' => 8]
    ];

    private ?int $accountId;

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
    }

    public function distribute(array $item, string $categoryId): array
    {
        $keywords = $this->extractKeywords($item);
        $classified = $this->classifyKeywords($keywords, $categoryId);

        $titleKeywords = array_slice(array_merge($classified['core'], $classified['support']), 0, 5);
        $modelKeywords = array_slice(array_merge($classified['support'], $classified['technical']), 0, 7);
        $attributeKeywords = array_slice(array_merge($classified['technical'], $classified['context']), 0, 8);
        $descriptionKeywords = array_slice(
            array_merge($classified['core'], $classified['support'], $classified['technical'], $classified['context']),
            0,
            15
        );

        return [
            'title' => $this->buildFieldDistribution('title', $titleKeywords),
            'model' => $this->buildFieldDistribution('model', $modelKeywords),
            'attributes' => $this->buildFieldDistribution('attributes', $attributeKeywords),
            'description' => $this->buildFieldDistribution('description', $descriptionKeywords),
        ];
    }

    public function classifyKeywords(array $keywords, string $categoryId = ''): array
    {
        $core = [];
        $support = [];
        $technical = [];
        $context = [];

        foreach ($keywords as $keyword) {
            $word = trim((string)$keyword);
            if ($word === '') {
                continue;
            }

            $wordLower = mb_strtolower($word);
            $wordCount = str_word_count($wordLower);
            $isTechnical = (bool)preg_match('/\d/', $wordLower)
                || str_contains($wordLower, 'mlb')
                || str_contains($wordLower, 'modelo')
                || str_contains($wordLower, 'codigo');
            $isContext = str_contains($wordLower, 'viagem')
                || str_contains($wordLower, 'urbano')
                || str_contains($wordLower, 'dia a dia')
                || str_contains($wordLower, 'trabalho')
                || str_contains($wordLower, 'estrada');

            if ($isTechnical) {
                $technical[] = $word;
                continue;
            }

            if ($isContext) {
                $context[] = $word;
                continue;
            }

            if ($wordCount <= 2) {
                $core[] = $word;
                continue;
            }

            $support[] = $word;
        }

        $core = array_values(array_unique($core));
        $support = array_values(array_unique($support));
        $technical = array_values(array_unique($technical));
        $context = array_values(array_unique($context));
        $allClassified = array_values(array_unique(array_merge($core, $support, $technical, $context)));
        $lowPriority = array_values(array_diff($keywords, $allClassified));

        return [
            // Formato principal
            'core' => $core,
            'support' => $support,
            'technical' => $technical,
            'context' => $context,

            // Aliases PT-BR para compatibilidade de testes/legado
            'suporte' => $support,
            'tecnica' => $technical,
            'contexto' => $context,

            // Aliases legados
            'high_priority' => array_values(array_unique(array_merge($core, array_slice($support, 0, 3)))),
            'medium_priority' => array_values(array_unique(array_merge($technical, $context))),
            'low_priority' => $lowPriority,
        ];
    }

    public function validateDensity(string $text, array $keywords): array
    {
        $valid = [];
        foreach ($keywords as $keyword) {
            $term = (string)$keyword;
            $density = $this->calculateDensity($text, $term);
            $occurrences = $this->countOccurrences($text, $term);
            $status = 'ok';
            if ($density < 1.0) {
                $status = 'low';
            } elseif ($density > 3.0) {
                $status = 'high';
            }

            $valid[$keyword] = [
                'occurrences' => $occurrences,
                'density' => $density,
                'status' => $status,
                'is_valid' => $density >= 1.0 && $density <= 3.0,
            ];
        }
        return $valid;
    }

    public function calculateDensity(string $text, string $keyword): float
    {
        $wordCount = $this->countWords($text);
        if ($wordCount === 0) {
            return 0.0;
        }

        $keywordCount = $this->countOccurrences($text, $keyword);
        return ($keywordCount / $wordCount) * 100;
    }

    public function getFieldWeights(): array
    {
        return self::FIELD_WEIGHTS;
    }

    private function extractKeywords(array $item): array
    {
        $title = (string)($item['title'] ?? '');
        $description = (string)($item['description'] ?? '');
        $raw = trim($title . ' ' . $description);

        $words = preg_split('/\s+/', mb_strtolower($raw));
        if (!is_array($words)) {
            return [];
        }

        $keywords = [];
        foreach ($words as $word) {
            $clean = preg_replace('/[^a-z0-9à-ÿ]+/iu', '', $word);
            if (!is_string($clean) || mb_strlen($clean) < 3) {
                continue;
            }
            $keywords[] = $clean;
        }

        return array_values(array_unique($keywords));
    }

    private function countOccurrences(string $text, string $keyword): int
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return 0;
        }

        $pattern = '/\b' . preg_quote($keyword, '/') . '\b/iu';
        if (!preg_match_all($pattern, $text, $matches)) {
            return 0;
        }

        return count($matches[0]);
    }

    private function countWords(string $text): int
    {
        if (!preg_match_all('/[A-Za-z0-9]+/', $text, $matches)) {
            return 0;
        }

        return count($matches[0]);
    }

    /**
     * @param list<string> $keywords
     */
    private function buildFieldDistribution(string $field, array $keywords): array
    {
        $limits = self::FIELD_LIMITS[$field];
        $count = count($keywords);
        $densityStatus = 'ok';
        if ($count < $limits['min']) {
            $densityStatus = 'low';
        } elseif ($count > $limits['max']) {
            $densityStatus = 'high';
        }

        return [
            'keywords' => $keywords,
            'count' => $count,
            'density_status' => $densityStatus,
            'weight' => self::FIELD_WEIGHTS[$field],
            'limits' => $limits,
        ];
    }
}
