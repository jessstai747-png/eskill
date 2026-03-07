<?php

declare(strict_types=1);

namespace App\Services\SEO;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\CacheService;
use Exception;

/**
 * Competitor Analysis Service
 *
 * Discovers and analyzes top competitors for benchmarking and pattern detection
 */
class CompetitorAnalysisService
{
    private \PDO $db;
    private MercadoLivreClient $mlClient;
    private CacheService $cache;

    // Default number of competitors to analyze
    private const DEFAULT_COMPETITOR_COUNT = 20;

    // Cache TTL for competitor data (6 hours)
    private const CACHE_TTL = 21600;

    // Price range tolerance (±30%)
    private const PRICE_TOLERANCE = 0.30;

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->mlClient = new MercadoLivreClient($accountId);
        $this->cache = new CacheService();
    }

    /**
     * Discover and analyze competitors for an item
     *
     * @param string $itemId ML item ID
     * @param int $limit Number of competitors to find
     * @param bool $forceRefresh Force new discovery
     * @return array Competitor analysis results
     */
    public function analyzeCompetitors(string $itemId, int $limit = self::DEFAULT_COMPETITOR_COUNT, bool $forceRefresh = false): array
    {
        // Check cache
        if (!$forceRefresh) {
            $cached = $this->getCachedAnalysis($itemId);
            if ($cached) {
                return $cached;
            }
        }

        // Get our item data
        $item = $this->mlClient->getItem($itemId);

        if (!$item) {
            throw new Exception("Item not found: {$itemId}");
        }

        // Discover competitors
        $competitors = $this->discoverCompetitors($item, $limit);

        // Analyze patterns
        $analysis = $this->analyzePatterns($item, $competitors);

        // Save to database
        $this->saveCompetitors($itemId, $competitors);

        return $analysis;
    }

    /**
     * Discover competitors using ML search API
     *
     * @param array $item Our item data
     * @param int $limit Max competitors to find
     * @return array List of competitor items
     */
    private function discoverCompetitors(array $item, int $limit): array
    {
        $categoryId = $item['category_id'] ?? '';
        $price = $item['price'] ?? 0;
        $condition = $item['condition'] ?? 'new';

        if (empty($categoryId)) {
            return [];
        }

        // Build search query
        $searchQuery = $this->buildSearchQuery($item);

        // Search in same category
        try {
            $searchResults = $this->mlClient->get('/sites/MLB/search', [
                'category' => $categoryId,
                'q' => $searchQuery,
                'condition' => $condition,
                'limit' => $limit * 2, // Get more to filter
                'sort' => 'relevance',
            ]);

            $results = $searchResults['results'] ?? [];
        } catch (Exception $e) {
            log_error('Falha na busca de concorrentes', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }

        // Filter and rank competitors
        $competitors = [];
        $ourItemId = $item['id'];

        foreach ($results as $result) {
            // Skip our own item
            if ($result['id'] === $ourItemId) {
                continue;
            }

            // Filter by price range (±30%)
            $competitorPrice = $result['price'] ?? 0;
            if ($price > 0) {
                $minPrice = $price * (1 - self::PRICE_TOLERANCE);
                $maxPrice = $price * (1 + self::PRICE_TOLERANCE);

                if ($competitorPrice < $minPrice || $competitorPrice > $maxPrice) {
                    continue;
                }
            }

            // Calculate relevance score
            $relevanceScore = $this->calculateRelevance($item, $result);

            // Get full item data for top candidates
            if (count($competitors) < $limit) {
                try {
                    $fullItem = $this->mlClient->getItem($result['id']);
                    if ($fullItem) {
                        $fullItem['relevance_score'] = $relevanceScore;
                        $competitors[] = $fullItem;
                    }
                } catch (Exception $e) {
                    // Skip items we can't fetch
                    continue;
                }
            }
        }

        // Sort by relevance
        usort($competitors, function ($a, $b) {
            return ($b['relevance_score'] ?? 0) <=> ($a['relevance_score'] ?? 0);
        });

        return array_slice($competitors, 0, $limit);
    }

    /**
     * Build search query from item data
     *
     * @param array $item Item data
     * @return string Search query
     */
    private function buildSearchQuery(array $item): string
    {
        $title = $item['title'] ?? '';

        // Extract key terms (remove common words)
        $stopWords = ['de', 'da', 'do', 'para', 'com', 'em', 'o', 'a', 'e', 'ou'];
        $words = explode(' ', strtolower($title));
        $keywords = [];

        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) > 3 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }

        // Use first 3-5 keywords
        $keywords = array_slice($keywords, 0, 5);

        return implode(' ', $keywords);
    }

    /**
     * Calculate relevance score between our item and competitor
     *
     * @param array $ourItem Our item
     * @param array $competitor Competitor item
     * @return float Relevance score (0-100)
     */
    private function calculateRelevance(array $ourItem, array $competitor): float
    {
        $score = 0;

        // Same category: +30 points
        if (($ourItem['category_id'] ?? '') === ($competitor['category_id'] ?? '')) {
            $score += 30;
        }

        // Same condition: +20 points
        if (($ourItem['condition'] ?? '') === ($competitor['condition'] ?? '')) {
            $score += 20;
        }

        // Similar price: +20 points
        $ourPrice = $ourItem['price'] ?? 0;
        $compPrice = $competitor['price'] ?? 0;
        if ($ourPrice > 0 && $compPrice > 0) {
            $priceDiff = abs($ourPrice - $compPrice) / $ourPrice;
            $score += (1 - $priceDiff) * 20;
        }

        // Title similarity: +30 points
        $titleSimilarity = $this->calculateTitleSimilarity(
            $ourItem['title'] ?? '',
            $competitor['title'] ?? ''
        );
        $score += $titleSimilarity * 30;

        return round($score, 2);
    }

    /**
     * Calculate title similarity using word overlap
     *
     * @param string $title1
     * @param string $title2
     * @return float Similarity (0-1)
     */
    private function calculateTitleSimilarity(string $title1, string $title2): float
    {
        $words1 = array_unique(explode(' ', strtolower($title1)));
        $words2 = array_unique(explode(' ', strtolower($title2)));

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        if (count($union) === 0) {
            return 0;
        }

        return count($intersection) / count($union);
    }

    /**
     * Analyze patterns from competitors
     *
     * @param array $item Our item
     * @param array $competitors List of competitors
     * @return array Analysis results
     */
    private function analyzePatterns(array $item, array $competitors): array
    {
        if (empty($competitors)) {
            return [
                'competitor_count' => 0,
                'attribute_patterns' => [],
                'pricing_analysis' => [],
                'image_analysis' => [],
                'title_patterns' => [],
                'competitors' => [],
            ];
        }

        return [
            'competitor_count' => count($competitors),
            'attribute_patterns' => $this->analyzeAttributePatterns($competitors),
            'pricing_analysis' => $this->analyzePricing($item, $competitors),
            'image_analysis' => $this->analyzeImages($competitors),
            'title_patterns' => $this->analyzeTitles($competitors),
            'shipping_analysis' => $this->analyzeShipping($competitors),
            'competitors' => $this->formatCompetitors($competitors),
        ];
    }

    /**
     * Analyze attribute frequency patterns
     *
     * @param array $competitors
     * @return array Attribute patterns
     */
    private function analyzeAttributePatterns(array $competitors): array
    {
        $attributeFrequency = [];
        $totalCompetitors = count($competitors);

        foreach ($competitors as $competitor) {
            $attributes = $competitor['attributes'] ?? [];

            foreach ($attributes as $attr) {
                $attrId = $attr['id'] ?? '';
                $attrName = $attr['name'] ?? '';
                $attrValue = $attr['value_name'] ?? $attr['value_id'] ?? '';

                if (empty($attrId)) {
                    continue;
                }

                // Initialize if first time
                if (!isset($attributeFrequency[$attrId])) {
                    $attributeFrequency[$attrId] = [
                        'id' => $attrId,
                        'name' => $attrName,
                        'count' => 0,
                        'frequency' => 0,
                        'values' => [],
                    ];
                }

                $attributeFrequency[$attrId]['count']++;

                // Track value distribution
                if (!empty($attrValue)) {
                    if (!isset($attributeFrequency[$attrId]['values'][$attrValue])) {
                        $attributeFrequency[$attrId]['values'][$attrValue] = 0;
                    }
                    $attributeFrequency[$attrId]['values'][$attrValue]++;
                }
            }
        }

        // Calculate frequencies
        foreach ($attributeFrequency as $attrId => &$data) {
            $data['frequency'] = round(($data['count'] / $totalCompetitors) * 100);

            // Sort values by frequency
            arsort($data['values']);
        }

        // Sort by frequency
        usort($attributeFrequency, function ($a, $b) {
            return $b['frequency'] <=> $a['frequency'];
        });

        return $attributeFrequency;
    }

    /**
     * Analyze pricing strategies
     *
     * @param array $item Our item
     * @param array $competitors
     * @return array Pricing analysis
     */
    private function analyzePricing(array $item, array $competitors): array
    {
        $prices = [];
        $ourPrice = $item['price'] ?? 0;

        foreach ($competitors as $competitor) {
            $price = $competitor['price'] ?? 0;
            if ($price > 0) {
                $prices[] = $price;
            }
        }

        if (empty($prices)) {
            return [];
        }

        sort($prices);

        $count = count($prices);
        $min = min($prices);
        $max = max($prices);
        $avg = array_sum($prices) / $count;
        $median = $count % 2 === 0
            ? ($prices[(int) ($count / 2) - 1] + $prices[(int) ($count / 2)]) / 2
            : $prices[(int) floor($count / 2)];

        // Calculate our position
        $position = 'average';
        if ($ourPrice > 0) {
            if ($ourPrice < $median * 0.9) {
                $position = 'below_market';
            } elseif ($ourPrice > $median * 1.1) {
                $position = 'above_market';
            }
        }

        return [
            'min' => $min,
            'max' => $max,
            'average' => round($avg, 2),
            'median' => round($median, 2),
            'our_price' => $ourPrice,
            'position' => $position,
            'percentile' => $this->calculatePercentile($ourPrice, $prices),
        ];
    }

    /**
     * Calculate percentile position
     */
    private function calculatePercentile(float $value, array $sortedValues): int
    {
        if (empty($sortedValues) || $value <= 0) {
            return 50;
        }

        $count = count($sortedValues);
        $below = 0;

        foreach ($sortedValues as $v) {
            if ($v < $value) {
                $below++;
            }
        }

        return (int) round(($below / $count) * 100);
    }

    /**
     * Analyze image strategies
     */
    private function analyzeImages(array $competitors): array
    {
        $imageCounts = [];

        foreach ($competitors as $competitor) {
            $pictures = $competitor['pictures'] ?? [];
            $count = count($pictures);
            $imageCounts[] = $count;
        }

        if (empty($imageCounts)) {
            return [];
        }

        return [
            'min' => min($imageCounts),
            'max' => max($imageCounts),
            'average' => round(array_sum($imageCounts) / count($imageCounts), 1),
            'recommended' => max($imageCounts), // Use the max as recommendation
        ];
    }

    /**
     * Analyze title patterns
     */
    private function analyzeTitles(array $competitors): array
    {
        $lengths = [];
        $commonWords = [];

        foreach ($competitors as $competitor) {
            $title = $competitor['title'] ?? '';
            $lengths[] = mb_strlen($title);

            // Extract words
            $words = explode(' ', strtolower($title));
            foreach ($words as $word) {
                $word = trim($word);
                if (strlen($word) > 3) {
                    if (!isset($commonWords[$word])) {
                        $commonWords[$word] = 0;
                    }
                    $commonWords[$word]++;
                }
            }
        }

        // Sort common words
        arsort($commonWords);
        $topWords = array_slice($commonWords, 0, 10, true);

        return [
            'min_length' => min($lengths),
            'max_length' => max($lengths),
            'average_length' => round(array_sum($lengths) / count($lengths)),
            'common_keywords' => array_keys($topWords),
        ];
    }

    /**
     * Analyze shipping strategies
     */
    private function analyzeShipping(array $competitors): array
    {
        $freeShippingCount = 0;
        $total = count($competitors);

        foreach ($competitors as $competitor) {
            $shipping = $competitor['shipping'] ?? [];
            if ($shipping['free_shipping'] ?? false) {
                $freeShippingCount++;
            }
        }

        return [
            'free_shipping_percentage' => round(($freeShippingCount / $total) * 100),
            'recommendation' => $freeShippingCount > ($total / 2) ? 'enable_free_shipping' : 'optional',
        ];
    }

    /**
     * Format competitors for response
     */
    private function formatCompetitors(array $competitors): array
    {
        $formatted = [];

        foreach ($competitors as $competitor) {
            $formatted[] = [
                'id' => $competitor['id'],
                'title' => $competitor['title'] ?? '',
                'price' => $competitor['price'] ?? 0,
                'currency_id' => $competitor['currency_id'] ?? 'BRL',
                'condition' => $competitor['condition'] ?? '',
                'sold_quantity' => $competitor['sold_quantity'] ?? 0,
                'available_quantity' => $competitor['available_quantity'] ?? 0,
                'thumbnail' => $competitor['thumbnail'] ?? '',
                'permalink' => $competitor['permalink'] ?? '',
                'image_count' => count($competitor['pictures'] ?? []),
                'attribute_count' => count($competitor['attributes'] ?? []),
                'free_shipping' => ($competitor['shipping']['free_shipping'] ?? false),
                'listing_type' => $competitor['listing_type_id'] ?? '',
                'relevance_score' => $competitor['relevance_score'] ?? 0,
            ];
        }

        return $formatted;
    }

    /**
     * Save competitors to database
     */
    private function saveCompetitors(string $itemId, array $competitors): void
    {
        $accountId = $this->mlClient->getAccountId();

        // Mark existing competitors as inactive
        $stmt = $this->db->prepare(
            "UPDATE seo_competitors
             SET is_active = FALSE
             WHERE item_id = :item_id"
        );
        $stmt->execute(['item_id' => $itemId]);

        // Insert new competitors
        $stmtInsert = $this->db->prepare(
            "INSERT INTO seo_competitors (
                item_id, competitor_item_id, account_id,
                title, price, currency_id, condition_type,
                sold_quantity, available_quantity,
                image_count, attribute_count, has_free_shipping,
                listing_type, relevance_score, data, is_active
            ) VALUES (
                :item_id, :competitor_item_id, :account_id,
                :title, :price, :currency_id, :condition_type,
                :sold_quantity, :available_quantity,
                :image_count, :attribute_count, :has_free_shipping,
                :listing_type, :relevance_score, :data, TRUE
            )
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                price = VALUES(price),
                sold_quantity = VALUES(sold_quantity),
                available_quantity = VALUES(available_quantity),
                image_count = VALUES(image_count),
                attribute_count = VALUES(attribute_count),
                has_free_shipping = VALUES(has_free_shipping),
                relevance_score = VALUES(relevance_score),
                data = VALUES(data),
                is_active = TRUE,
                last_updated = NOW()"
        );

        foreach ($competitors as $competitor) {
            $shipping = $competitor['shipping'] ?? [];

            $stmtInsert->execute([
                'item_id' => $itemId,
                'competitor_item_id' => $competitor['id'],
                'account_id' => $accountId,
                'title' => $competitor['title'] ?? '',
                'price' => $competitor['price'] ?? 0,
                'currency_id' => $competitor['currency_id'] ?? 'BRL',
                'condition_type' => $competitor['condition'] ?? '',
                'sold_quantity' => $competitor['sold_quantity'] ?? 0,
                'available_quantity' => $competitor['available_quantity'] ?? 0,
                'image_count' => count($competitor['pictures'] ?? []),
                'attribute_count' => count($competitor['attributes'] ?? []),
                'has_free_shipping' => ($shipping['free_shipping'] ?? false) ? 1 : 0,
                'listing_type' => $competitor['listing_type_id'] ?? '',
                'relevance_score' => $competitor['relevance_score'] ?? 0,
                'data' => json_encode($competitor),
            ]);
        }
    }

    /**
     * Get cached competitor analysis
     */
    private function getCachedAnalysis(string $itemId): ?array
    {
        $cacheKey = "competitor_analysis_{$itemId}";
        return $this->cache->get($cacheKey);
    }

    /**
     * Get competitors from database
     */
    public function getStoredCompetitors(string $itemId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM seo_competitors
             WHERE item_id = :item_id
             AND is_active = TRUE
             ORDER BY relevance_score DESC"
        );
        $stmt->execute(['item_id' => $itemId]);

        return $stmt->fetchAll();
    }
}
