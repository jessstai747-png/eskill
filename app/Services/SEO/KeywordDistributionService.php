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
        
        return [
            'title' => [
                'keywords' => array_slice($classified['high_priority'], 0, 5),
                'limits' => self::FIELD_LIMITS['title']
            ],
            'model' => [
                'keywords' => array_slice($classified['medium_priority'], 0, 4),
                'limits' => self::FIELD_LIMITS['model']
            ],
            'description' => [
                'keywords' => array_merge(
                    array_slice($classified['high_priority'], 0, 3),
                    array_slice($classified['medium_priority'], 0, 5),
                    array_slice($classified['low_priority'], 0, 7)
                ),
                'limits' => self::FIELD_LIMITS['description']
            ]
        ];
    }

    public function classifyKeywords(array $keywords, string $categoryId): array
    {
        return [
            'high_priority' => array_slice($keywords, 0, 5),
            'medium_priority' => array_slice($keywords, 5, 5),
            'low_priority' => array_slice($keywords, 10, 10)
        ];
    }

    public function validateDensity(string $text, array $keywords): array
    {
        $valid = [];
        foreach ($keywords as $keyword) {
            $density = $this->calculateDensity($text, $keyword);
            $valid[$keyword] = [
                'density' => $density,
                'is_valid' => $density >= 1.0 && $density <= 3.0
            ];
        }
        return $valid;
    }

    public function calculateDensity(string $text, string $keyword): float
    {
        $wordCount = str_word_count($text);
        if ($wordCount === 0) return 0.0;
        
        $keywordCount = substr_count(strtolower($text), strtolower($keyword));
        return ($keywordCount / $wordCount) * 100;
    }

    public function getFieldWeights(): array
    {
        return self::FIELD_WEIGHTS;
    }

    private function extractKeywords(array $item): array
    {
        $title = $item['title'] ?? '';
        $words = preg_split('/\s+/', strtolower($title));
        return array_filter($words, fn($w) => strlen($w) > 3);
    }
}
