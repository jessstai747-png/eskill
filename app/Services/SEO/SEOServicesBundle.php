<?php
declare(strict_types=1);

namespace App\Services\SEO;

use App\Database;
use PDO;

class SemanticScoreService
{
    private ?int $accountId;

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
    }

    public function calculateScore(string $word, string $title, string $categoryId): float
    {
        $titleLower = strtolower($title);
        $wordLower = strtolower($word);
        
        if (strpos($titleLower, $wordLower) !== false) {
            return 1.0;
        }
        
        return 0.5;
    }

    public function scoreWords(array $words, string $title, string $categoryId): array
    {
        $scores = [];
        foreach ($words as $word) {
            $scores[$word] = $this->calculateScore($word, $title, $categoryId);
        }
        return $scores;
    }
}

class HiddenAttributesDetector
{
    private ?int $accountId;

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
    }

    public function detectKeywordFields(string $itemId): array
    {
        return [
            'KEYWORDS' => ['detected' => false, 'suggestion' => ''],
            'MPN' => ['detected' => false, 'suggestion' => ''],
            'LINE' => ['detected' => false, 'suggestion' => '']
        ];
    }

    public function generateKeywordsFieldValue(string $title, array $synonyms): string
    {
        return implode(', ', array_slice($synonyms, 0, 10));
    }

    public function generateMPNValue(array $item): string
    {
        return $item['id'] ?? '';
    }

    public function generateLineValue(array $item): string
    {
        return $item['title'] ?? '';
    }

    public function applyHiddenFields(string $itemId, array $fields, ?int $userId): array
    {
        return ['success' => true, 'applied_fields' => count($fields)];
    }
}

class SearchCoverageService
{
    private ?int $accountId;

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
    }

    public function analyzeCoverage(array $item): array
    {
        return [
            'score' => 75,
            'covered_types' => ['exact', 'partial'],
            'gaps' => [
                ['type' => 'long_tail', 'suggestion' => 'Adicionar keywords long tail']
            ]
        ];
    }
}

class KeywordSourceService
{
    private ?int $accountId;

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
    }

    public function getKeywords(string $categoryId, string $baseKeyword): array
    {
        return [
            $baseKeyword,
            $baseKeyword . ' premium',
            $baseKeyword . ' profissional',
            $baseKeyword . ' original'
        ];
    }

    public function invalidateCache(string $categoryId): void
    {
        // Cache invalidation logic
    }
}

class ContextInjectorService
{
    private ?int $accountId;

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
    }

    public function detectApplicableContexts(array $item): array
    {
        $db = Database::getInstance();
        $categoryId = $item['category_id'] ?? '';
        
        if (empty($categoryId)) {
            return [];
        }

        $stmt = $db->prepare("
            SELECT context_type, keyword, weight
            FROM seo_use_contexts
            WHERE category_id = :category_id AND is_active = 1
            ORDER BY weight DESC
        ");
        
        $stmt->execute(['category_id' => $categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

class LongTailGeneratorService
{
    private ?int $accountId;

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
    }

    public function generate(string $title, string $categoryId): array
    {
        $words = explode(' ', $title);
        $longTail = [];
        
        $suffixes = ['barato', 'original', 'profissional', 'premium', 'melhor'];
        
        foreach ($suffixes as $suffix) {
            $longTail[] = $title . ' ' . $suffix;
        }
        
        return $longTail;
    }
}

class CompatibilityService
{
    private ?int $accountId;

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
    }

    public function getCompatibilityList(string $categoryId): array
    {
        return [
            'compatible_models' => [],
            'compatible_brands' => []
        ];
    }
}

class SEOMonitoringService
{
    private PDO $db;
    private ?int $accountId;

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    public function collectMetrics(string $itemId): array
    {
        return [
            'views' => 0,
            'sales' => 0,
            'conversion_rate' => 0.0,
            'seo_score' => 0
        ];
    }

    public function scheduleCheck(string $itemId, int $intervalDays): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO seo_monitoring_schedule (item_id, interval_days, next_check)
            VALUES (:item_id, :interval_days, DATE_ADD(NOW(), INTERVAL :interval_days DAY))
            ON DUPLICATE KEY UPDATE 
                interval_days = :interval_days,
                next_check = DATE_ADD(NOW(), INTERVAL :interval_days DAY)
        ");
        
        $stmt->execute([
            'item_id' => $itemId,
            'interval_days' => $intervalDays
        ]);
    }
}
