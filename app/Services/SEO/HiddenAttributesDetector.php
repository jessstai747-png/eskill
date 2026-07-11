<?php

declare(strict_types=1);

namespace App\Services\SEO;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\CompetitorAnalysisService;
use Exception;

/**
 * Hidden Attributes Detector
 *
 * Detects non-required attributes that appear frequently in top competitors
 * and can significantly improve listing visibility
 */
class HiddenAttributesDetector
{
    private \PDO $db;
    private MercadoLivreClient $mlClient;
    private CompetitorAnalysisService $competitorService;
    private $synonymService;
    private ?int $accountId;

    // Minimum frequency to consider an attribute "hidden but important"
    private const MIN_FREQUENCY_HIGH = 80;    // High impact
    private const MIN_FREQUENCY_MEDIUM = 60;  // Medium impact
    private const MIN_FREQUENCY_LOW = 40;     // Low impact

    // Technical attribute IDs that require validation
    private const TECHNICAL_ATTRIBUTES = [
        'BRAND',
        'MODEL',
        'MANUFACTURER',
        'PART_NUMBER',
        'MPN',
        'GTIN',
        'EAN',
        'UPC',
        'ISBN',
        'VOLTAGE',
        'POWER',
        'CAPACITY',
        'SIZE',
        'WEIGHT',
        'PROCESSOR',
        'RAM',
        'STORAGE',
        'SCREEN_SIZE',
    ];

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->mlClient = new MercadoLivreClient($accountId);
        $this->competitorService = new CompetitorAnalysisService($accountId);
        $this->synonymService = $this->resolveSynonymService($accountId);
        $this->accountId = $accountId;
    }

    /**
     * Detect hidden attributes for an item
     *
     * @param string $itemId ML item ID
     * @param bool $forceRefresh Force new detection
     * @return array Detection results
     */
    public function detectHiddenAttributes(string $itemId, bool $forceRefresh = false): array
    {
        $item = $this->fetchItem($itemId);

        if (!$item) {
            throw new Exception("Item not found: {$itemId}");
        }

        $categoryId = $item['category_id'] ?? '';
        if (empty($categoryId)) {
            throw new Exception("Item has no category");
        }

        // Get category attributes to identify required ones
        $categoryAttributes = $this->mlClient->getCategoryAttributes($categoryId);
        $requiredAttributeIds = $this->getRequiredAttributeIds($categoryAttributes);

        // Get our item's current attributes
        $ourAttributeIds = $this->getItemAttributeIds($item);

        // Analyze competitors
        $competitorAnalysis = $this->competitorService->analyzeCompetitors($itemId, 20, $forceRefresh);
        $attributePatterns = $competitorAnalysis['attribute_patterns'] ?? [];

        // Find hidden attributes
        $hiddenAttributes = [];

        foreach ($attributePatterns as $pattern) {
            $attrId = $pattern['id'];
            $frequency = $pattern['frequency'];

            // Skip if it's required
            if (in_array($attrId, $requiredAttributeIds)) {
                continue;
            }

            // Skip if we already have it
            if (in_array($attrId, $ourAttributeIds)) {
                continue;
            }

            // Only consider attributes with significant frequency
            if ($frequency < self::MIN_FREQUENCY_LOW) {
                continue;
            }

            // Classify impact
            $impact = $this->classifyImpact($frequency, $attrId);

            // Determine if it's technical
            $isTechnical = $this->isTechnicalAttribute($attrId);

            // Get suggested values
            $suggestedValues = $this->getSuggestedValues($pattern['values'] ?? []);

            $hiddenAttributes[] = [
                'attribute_id' => $attrId,
                'attribute_name' => $pattern['name'],
                'frequency' => $frequency,
                'competitor_count' => $competitorAnalysis['competitor_count'],
                'impact' => $impact,
                'is_technical' => $isTechnical,
                'requires_validation' => $isTechnical || $impact === 'high',
                'suggested_values' => $suggestedValues,
                'value_distribution' => $pattern['values'] ?? [],
            ];
        }

        // Sort by impact and frequency
        usort($hiddenAttributes, function ($a, $b) {
            $impactOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
            $impactCompare = ($impactOrder[$a['impact']] ?? 99) <=> ($impactOrder[$b['impact']] ?? 99);

            if ($impactCompare !== 0) {
                return $impactCompare;
            }

            return $b['frequency'] <=> $a['frequency'];
        });

        // Save to database
        $this->saveHiddenAttributes($itemId, $hiddenAttributes);

        // Calculate completeness score
        $completenessScore = $this->calculateCompletenessScore($item, $hiddenAttributes);

        return [
            'item_id' => $itemId,
            'category_id' => $categoryId,
            'hidden_attributes' => $hiddenAttributes,
            'completeness_score' => $completenessScore,
            'total_hidden' => count($hiddenAttributes),
            'high_impact' => count(array_filter($hiddenAttributes, fn(array $a): bool => $a['impact'] === 'high')),
            'medium_impact' => count(array_filter($hiddenAttributes, fn(array $a): bool => $a['impact'] === 'medium')),
            'low_impact' => count(array_filter($hiddenAttributes, fn(array $a): bool => $a['impact'] === 'low')),
        ];
    }

    /**
     * Get required attribute IDs from category
     */
    private function getRequiredAttributeIds(array $categoryAttributes): array
    {
        $required = [];

        foreach ($categoryAttributes as $attr) {
            if ($attr['tags']['required'] ?? false) {
                $required[] = $attr['id'];
            }
        }

        return $required;
    }

    /**
     * Get attribute IDs from item
     */
    private function getItemAttributeIds(array $item): array
    {
        $ids = [];
        $attributes = $item['attributes'] ?? [];

        foreach ($attributes as $attr) {
            if (!empty($attr['id'])) {
                $ids[] = $attr['id'];
            }
        }

        return $ids;
    }

    /**
     * Classify impact level based on frequency and attribute type
     */
    private function classifyImpact(int $frequency, string $attributeId): string
    {
        // Technical attributes with high frequency are high impact
        if ($this->isTechnicalAttribute($attributeId) && $frequency >= self::MIN_FREQUENCY_MEDIUM) {
            return 'high';
        }

        // Frequency-based classification
        if ($frequency >= self::MIN_FREQUENCY_HIGH) {
            return 'high';
        } elseif ($frequency >= self::MIN_FREQUENCY_MEDIUM) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Check if attribute is technical
     */
    private function isTechnicalAttribute(string $attributeId): bool
    {
        return in_array($attributeId, self::TECHNICAL_ATTRIBUTES);
    }

    /**
     * Get suggested values from distribution
     * Returns top 3 most common values
     */
    private function getSuggestedValues(array $valueDistribution): array
    {
        if (empty($valueDistribution)) {
            return [];
        }

        // Sort by frequency
        arsort($valueDistribution);

        // Get top 3
        $top = array_slice($valueDistribution, 0, 3, true);

        $suggestions = [];
        foreach ($top as $value => $count) {
            $suggestions[] = [
                'value' => $value,
                'count' => $count,
            ];
        }

        return $suggestions;
    }

    /**
     * Calculate completeness score
     *
     * Score based on how many high-impact hidden attributes are filled
     */
    private function calculateCompletenessScore(array $item, array $hiddenAttributes): int
    {
        if (empty($hiddenAttributes)) {
            return 100; // No hidden attributes to fill
        }

        $totalWeight = 0;
        $filledWeight = 0;

        $ourAttributeIds = $this->getItemAttributeIds($item);

        foreach ($hiddenAttributes as $hidden) {
            // Weight by impact
            $weight = match ($hidden['impact']) {
                'high' => 3,
                'medium' => 2,
                'low' => 1,
                default => 1,
            };

            $totalWeight += $weight;

            // Check if we have it
            if (in_array($hidden['attribute_id'], $ourAttributeIds)) {
                $filledWeight += $weight;
            }
        }

        if ($totalWeight === 0) {
            return 100;
        }

        return round(($filledWeight / $totalWeight) * 100);
    }

    /**
     * Save hidden attributes to database
     */
    private function saveHiddenAttributes(string $itemId, array $hiddenAttributes): void
    {
        $accountId = $this->accountId ?? 0;

        $item = $this->fetchItem($itemId);
        $categoryId = $item['category_id'] ?? '';

        // Mark existing as outdated
        $stmt = $this->db->prepare(
            "UPDATE seo_hidden_attributes
             SET status = 'rejected'
             WHERE item_id = :item_id
             AND status = 'detected'"
        );
        $stmt->execute(['item_id' => $itemId]);

        // Insert new detections
        $stmtInsert = $this->db->prepare(
            "INSERT INTO seo_hidden_attributes (
                item_id, account_id, category_id,
                attribute_id, attribute_name, attribute_type,
                frequency, competitor_count, impact_level,
                suggested_values, value_distribution,
                requires_validation, is_technical, status
            ) VALUES (
                :item_id, :account_id, :category_id,
                :attribute_id, :attribute_name, :attribute_type,
                :frequency, :competitor_count, :impact_level,
                :suggested_values, :value_distribution,
                :requires_validation, :is_technical, 'detected'
            )
            ON DUPLICATE KEY UPDATE
                frequency = VALUES(frequency),
                competitor_count = VALUES(competitor_count),
                impact_level = VALUES(impact_level),
                suggested_values = VALUES(suggested_values),
                value_distribution = VALUES(value_distribution),
                status = 'detected',
                detected_at = NOW()"
        );

        foreach ($hiddenAttributes as $attr) {
            $stmtInsert->execute([
                'item_id' => $itemId,
                'account_id' => $accountId,
                'category_id' => $categoryId,
                'attribute_id' => $attr['attribute_id'],
                'attribute_name' => $attr['attribute_name'],
                'attribute_type' => 'string', // Default, could be enhanced
                'frequency' => $attr['frequency'],
                'competitor_count' => $attr['competitor_count'],
                'impact_level' => $attr['impact'],
                'suggested_values' => json_encode($attr['suggested_values']),
                'value_distribution' => json_encode($attr['value_distribution']),
                'requires_validation' => $attr['requires_validation'] ? 1 : 0,
                'is_technical' => $attr['is_technical'] ? 1 : 0,
            ]);
        }
    }

    /**
     * Apply a hidden attribute to an item
     *
     * @param string $itemId
     * @param string $attributeId
     * @param string $value
     * @param int $userId User who applied it
     * @return bool Success
     */
    public function applyHiddenAttribute(string $itemId, string $attributeId, string $value, int $userId): bool
    {
        try {
            $item = $this->fetchItem($itemId);

            if (!$item) {
                throw new Exception("Item not found");
            }

            // Get current attributes
            $attributes = $item['attributes'] ?? [];

            // Add new attribute
            $attributes[] = [
                'id' => $attributeId,
                'value_name' => $value,
            ];

            // Update via ML API
            $result = $this->mlClient->updateItem($itemId, [
                'attributes' => $attributes,
            ]);

            if (!empty($result)) {
                // Mark as applied in database
                $stmt = $this->db->prepare(
                    "UPDATE seo_hidden_attributes
                     SET status = 'applied',
                         applied_value = :value,
                         applied_at = NOW(),
                         applied_by = :user_id
                     WHERE item_id = :item_id
                     AND attribute_id = :attribute_id"
                );
                $stmt->execute([
                    'item_id' => $itemId,
                    'attribute_id' => $attributeId,
                    'value' => $value,
                    'user_id' => $userId,
                ]);

                return true;
            }

            return false;
        } catch (Exception $e) {
            log_error('Falha ao aplicar atributo oculto', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get stored hidden attributes for an item
     */
    public function getStoredHiddenAttributes(string $itemId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM seo_hidden_attributes
             WHERE item_id = :item_id
             AND status IN ('detected', 'pending')
             ORDER BY
                FIELD(impact_level, 'high', 'medium', 'low'),
                frequency DESC"
        );
        $stmt->execute(['item_id' => $itemId]);
        $results = $stmt->fetchAll(
            // Use FETCH_ASSOC to get array
            \PDO::FETCH_ASSOC
        );

        $formatted = [];
        foreach ($results as $row) {
            $formatted[] = [
                'id' => $row['id'],
                'attribute_id' => $row['attribute_id'],
                'attribute_name' => $row['attribute_name'],
                'frequency' => (int)$row['frequency'],
                'competitor_count' => (int)$row['competitor_count'],
                'impact' => $row['impact_level'],
                'is_technical' => (bool)$row['is_technical'],
                'requires_validation' => (bool)$row['requires_validation'],
                'suggested_values' => json_decode($row['suggested_values'], true) ?? [],
                'value_distribution' => json_decode($row['value_distribution'], true) ?? [],
                'status' => $row['status'],
                'detected_at' => $row['detected_at'],
            ];
        }

        return $formatted;
    }

    /**
     * Reject a hidden attribute suggestion
     */
    public function rejectHiddenAttribute(string $itemId, string $attributeId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE seo_hidden_attributes
             SET status = 'rejected'
             WHERE item_id = :item_id
             AND attribute_id = :attribute_id"
        );
        return $stmt->execute([
            'item_id' => $itemId,
            'attribute_id' => $attributeId,
        ]);
    }

    /**
     * Get statistics about hidden attributes
     */
    public function getStatistics(int $accountId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) as total_detected,
                SUM(CASE WHEN status = 'applied' THEN 1 ELSE 0 END) as total_applied,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as total_rejected,
                SUM(CASE WHEN impact_level = 'high' THEN 1 ELSE 0 END) as high_impact,
                SUM(CASE WHEN impact_level = 'medium' THEN 1 ELSE 0 END) as medium_impact,
                SUM(CASE WHEN impact_level = 'low' THEN 1 ELSE 0 END) as low_impact,
                AVG(frequency) as avg_frequency
             FROM seo_hidden_attributes
             WHERE account_id = :account_id"
        );
        $stmt->execute(['account_id' => $accountId]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $stats ?: [];
    }

    /**
     * Detect and suggest filling for KEYWORDS, MPN, LINE fields
     */
    public function detectKeywordFields(string $itemId): array
    {
        $item = $this->fetchItem($itemId);
        if (!$item) {
            return [];
        }

        $currentAttributes = $this->getItemAttributeIds($item);
        $attributeValues = $this->mapAttributes($item['attributes'] ?? []);
        $suggestions = [];

        $mpnValue = $attributeValues['MPN'] ?? $attributeValues['PART_NUMBER'] ?? null;
        $suggestions['MPN'] = $this->buildHiddenFieldSuggestion(
            'MPN',
            $mpnValue,
            $this->generateMPNValue($item),
            $currentAttributes
        );

        $lineValue = $attributeValues['LINE'] ?? null;
        $suggestions['LINE'] = $this->buildHiddenFieldSuggestion(
            'LINE',
            $lineValue,
            $this->generateLineValue($item),
            $currentAttributes
        );

        $title = (string)($item['title'] ?? '');
        $categoryId = (string)($item['category_id'] ?? '');
        $synonyms = $this->resolveKeywordSynonyms($title, $categoryId);
        $keywordsValue = $this->generateKeywordsFieldValue($title, $synonyms);

        $suggestions['KEYWORDS'] = $this->buildHiddenFieldSuggestion(
            'KEYWORDS',
            $attributeValues['KEYWORDS'] ?? null,
            $keywordsValue,
            $currentAttributes
        );
        $suggestions['KEYWORDS']['synonyms'] = $synonyms;

        return $suggestions;
    }

    /**
     * Generate value for KEYWORDS field
     * Combines synonyms into a comma-separated string or similar format
     */
    public function generateKeywordsFieldValue(string $title, array $synonyms): string
    {
        $words = [];

        foreach ($synonyms as $syn) {
            $value = $this->normalizeSynonymValue($syn);
            if ($value !== '') {
                $words[] = $value;
            }
        }

        $words = array_merge($words, $this->extractKeywordsFromTitle($title));
        $unique = array_values(array_unique($words));
        $sliced = array_slice($unique, 0, 12);

        return implode('; ', $sliced);
    }

    /**
     * Generate value for MPN (Manufacturer Part Number)
     */
    public function generateMPNValue(array $item): string
    {
        $attributes = $this->mapAttributes($item['attributes'] ?? []);
        $attributeCandidates = [
            'MPN',
            'PART_NUMBER',
            'MANUFACTURER_PART_NUMBER',
            'SKU',
            'SELLER_SKU',
            'GTIN',
            'EAN',
            'UPC',
            'ISBN',
        ];

        foreach ($attributeCandidates as $candidate) {
            if (!empty($attributes[$candidate])) {
                return (string) $attributes[$candidate];
            }
        }

        if (!empty($item['seller_custom_field'])) {
            return (string) $item['seller_custom_field'];
        }

        if (!empty($item['variations'])) {
            foreach ($item['variations'] as $variation) {
                if (!empty($variation['seller_custom_field'])) {
                    return (string) $variation['seller_custom_field'];
                }
                if (!empty($variation['attributes'])) {
                    $variationAttributes = $this->mapAttributes($variation['attributes']);
                    foreach ($attributeCandidates as $candidate) {
                        if (!empty($variationAttributes[$candidate])) {
                            return (string) $variationAttributes[$candidate];
                        }
                    }
                }
            }
        }

        return '';
    }

    /**
     * Generate value for LINE field
     */
    public function generateLineValue(array $item): string
    {
        $attributes = $this->mapAttributes($item['attributes'] ?? []);

        if (!empty($attributes['LINE'])) {
            return $attributes['LINE'];
        }

        // Try to construct from Brand + Model
        if (!empty($attributes['BRAND']) && !empty($attributes['MODEL'])) {
            return $attributes['BRAND'] . ' ' . $attributes['MODEL'];
        }

        if (!empty($attributes['MODEL'])) {
            return $attributes['MODEL'];
        }

        if (!empty($attributes['BRAND'])) {
            return $attributes['BRAND'];
        }

        return '';
    }

    /**
     * Apply hidden fields via API
     */
    public function applyHiddenFields(string $itemId, array $fields, ?int $userId = null): array
    {
        $results = [];
        $userId = $userId ?? ($_SESSION['user_id'] ?? 0);

        foreach ($fields as $attrId => $value) {
            if (empty($value)) continue;

            $success = $this->applyHiddenAttribute($itemId, $attrId, $value, $userId);
            $results[$attrId] = $success ? 'applied' : 'failed';
        }

        return $results;
    }

    private function buildHiddenFieldSuggestion(
        string $fieldId,
        ?string $currentValue,
        string $suggestion,
        array $currentAttributes
    ): array {
        return [
            'detected' => !in_array($fieldId, $currentAttributes, true),
            'current_value' => $currentValue,
            'suggestion' => $suggestion
        ];
    }

    private function resolveKeywordSynonyms(string $title, string $categoryId): array
    {
        if ($title === '' || $categoryId === '') {
            return [];
        }

        if (!$this->synonymService) {
            return [];
        }

        try {
            $synonymData = $this->synonymService->expand($title, $categoryId);
            if (is_array($synonymData) && isset($synonymData['synonyms'])) {
                return $synonymData['synonyms'];
            }

            return is_array($synonymData) ? $synonymData : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function resolveSynonymService(?int $accountId)
    {
        $class = '\\App\\Services\\SEO\\SynonymExpansionService';
        if (class_exists($class)) {
            return new $class($accountId);
        }

        return null;
    }

    private function normalizeSynonymValue($synonym): string
    {
        if (is_string($synonym)) {
            return trim($synonym);
        }

        if (is_array($synonym)) {
            if (!empty($synonym['word'])) {
                return trim((string)$synonym['word']);
            }
            if (!empty($synonym['keyword'])) {
                return trim((string)$synonym['keyword']);
            }
        }

        return '';
    }

    private function extractKeywordsFromTitle(string $title): array
    {
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', mb_strtolower($title));
        $words = preg_split('/\s+/', (string)$clean, -1, PREG_SPLIT_NO_EMPTY);

        $keywords = [];
        foreach ($words as $word) {
            if (mb_strlen($word) > 2) {
                $keywords[] = $word;
            }
        }

        return $keywords;
    }

    private function mapAttributes(array $attributes): array
    {
        $mapped = [];
        foreach ($attributes as $attr) {
            if (!empty($attr['id'])) {
                $mapped[$attr['id']] = $attr['value_name'] ?? null;
            }
        }
        return $mapped;
    }

    private function fetchItem(string $itemId): ?array
    {
        $item = $this->mlClient->getItemDetails($itemId);
        return is_array($item) ? $item : null;
    }
}
