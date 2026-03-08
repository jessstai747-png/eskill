<?php

declare(strict_types=1);

namespace App\Services\AI\Core;

use App\Services\AI\Core\AIProviderManager;
use App\Services\AI\Optimizers\TitleOptimizer;
use App\Services\AI\Optimizers\DescriptionOptimizer;
use App\Services\AI\Optimizers\TechSheetOptimizer;
use App\Services\AI\Analyzers\KeywordResearchService;
use App\Services\AI\Analyzers\CompetitiveAnalysisService;
use App\Services\ItemService;
use App\Services\MercadoLivreClient;
use App\Traits\NormalizesMLItems;

/**
 * Main AI Optimization Engine
 * Orchestrates all optimization tasks
 */
class AIOptimizationEngine
{
    use NormalizesMLItems;

    private AIProviderManager $providerManager;
    private TitleOptimizer $titleOptimizer;
    private DescriptionOptimizer $descriptionOptimizer;
    private TechSheetOptimizer $techSheetOptimizer;
    private KeywordResearchService $keywordService;
    private CompetitiveAnalysisService $competitiveService;
    private ?ItemService $itemService = null;
    private ?MercadoLivreClient $mlClient = null;
    private ?int $accountId = null;
    private array $config;

    public function __construct(?array $config = null, ?int $accountId = null)
    {
        $this->config = $config ?? [
            'model' => $_ENV['AI_DEFAULT_MODEL'] ?? 'gpt-4o',
            'temperature' => floatval($_ENV['AI_TEMPERATURE'] ?? 0.7),
            'max_tokens' => intval($_ENV['AI_MAX_TOKENS'] ?? 4000),
            'cache_enabled' => filter_var($_ENV['AI_CACHE_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];

        $this->accountId = $accountId;
        $this->providerManager = new AIProviderManager($this->config);
        $this->titleOptimizer = new TitleOptimizer($this->config);
        $this->descriptionOptimizer = new DescriptionOptimizer($this->config);
        $this->techSheetOptimizer = new TechSheetOptimizer();
        $this->keywordService = new KeywordResearchService($accountId);
        $this->competitiveService = new CompetitiveAnalysisService($accountId);

        // Initialize ItemService and MercadoLivreClient for ML API integration
        if ($accountId) {
            $this->itemService = new ItemService($accountId);
            $this->mlClient = new MercadoLivreClient($accountId);
        }
    }

    /**
     * Optimize a complete listing
     *
     * @param string $itemId ML item ID
     * @param array $options Optimization options
     * @return array
     */
    public function optimizeListing(string $itemId, array $options = []): array
    {
        $startTime = microtime(true);

        $result = [
            'success' => false,
            'item_id' => $itemId,
            'optimizations' => [],
            'score_before' => 0,
            'score_after' => 0,
            'improvement' => 0,
        ];

        // Get current listing data (would integrate with ItemService)
        $currentData = $this->getCurrentListingData($itemId);

        if (!$currentData) {
            return array_merge($result, [
                'error' => 'Failed to fetch listing data'
            ]);
        }

        $result['score_before'] = $this->calculateScore($currentData);

        // Determine what to optimize
        $optimizeTitle = $options['optimize_title'] ?? true;
        $optimizeDescription = $options['optimize_description'] ?? true;
        $optimizeAttributes = $options['optimize_attributes'] ?? true;

        // Optimize title
        if ($optimizeTitle && !empty($currentData['title'])) {
            $titleResult = $this->titleOptimizer->optimize(
                $currentData['title'],
                [
                    'category' => $currentData['category'] ?? '',
                    'brand' => $currentData['brand'] ?? '',
                    'model' => $currentData['model'] ?? '',
                    'attributes' => $currentData['attributes'] ?? [],
                ]
            );

            $result['optimizations']['title'] = $titleResult;
        }

        // Optimize description
        if ($optimizeDescription && !empty($currentData['title'])) {
            $descResult = $this->descriptionOptimizer->generate(
                [
                    'title' => $currentData['title'],
                    'category' => $currentData['category'] ?? '',
                    'brand' => $currentData['brand'] ?? '',
                    'attributes' => $currentData['attributes'] ?? [],
                    'current_description' => $currentData['description'] ?? '',
                ],
                [] // keywords would come from KeywordService
            );

            $result['optimizations']['description'] = $descResult;
        }

        // Optimize attributes
        if ($optimizeAttributes) {
            $attrResult = $this->techSheetOptimizer->complete(
                $currentData['category_id'] ?? $itemId,
                $currentData['attributes'] ?? []
            );

            $result['optimizations']['attributes'] = $attrResult;
        }

        // Calculate final score (with optimizations applied)
        $optimizedData = $this->applyOptimizations($currentData, $result['optimizations']);
        $result['score_after'] = $this->calculateScore($optimizedData);
        $result['improvement'] = $result['score_after'] - $result['score_before'];

        $result['success'] = true;
        $result['duration'] = round(microtime(true) - $startTime, 3);

        return $result;
    }

    /**
     * Optimize only the title
     *
     * @param string $itemId
     * @return array
     */
    public function optimizeTitle(string $itemId): array
    {
        return $this->optimizeListing($itemId, [
            'optimize_title' => true,
            'optimize_description' => false,
            'optimize_attributes' => false,
        ]);
    }

    /**
     * Batch optimize multiple listings
     *
     * @param array $itemIds Array of item IDs
     * @param array $options
     * @return array
     */
    public function batchOptimize(array $itemIds, array $options = []): array
    {
        $results = [];
        $stats = [
            'total' => count($itemIds),
            'success' => 0,
            'failed' => 0,
            'total_improvement' => 0,
        ];

        foreach ($itemIds as $itemId) {
            $result = $this->optimizeListing($itemId, $options);

            $results[] = $result;

            if ($result['success']) {
                $stats['success']++;
                $stats['total_improvement'] += $result['improvement'];
            } else {
                $stats['failed']++;
            }

            // Rate limiting (if needed)
            if (isset($options['delay_ms'])) {
                usleep($options['delay_ms'] * 1000);
            }
        }

        $stats['average_improvement'] = $stats['success'] > 0
            ? round($stats['total_improvement'] / $stats['success'], 1)
            : 0;

        return [
            'results' => $results,
            'stats' => $stats,
        ];
    }

    /**
     * Get optimization suggestions without applying
     *
     * @param string $itemId
     * @return array
     */
    public function getSuggestions(string $itemId): array
    {
        $currentData = $this->getCurrentListingData($itemId);

        if (!$currentData) {
            return ['error' => 'Failed to fetch listing data'];
        }

        $suggestions = [];
        $currentScore = $this->calculateScore($currentData);

        // Title suggestions
        if (!empty($currentData['title'])) {
            $titleAnalysis = $this->titleOptimizer->analyze($currentData['title']);

            if ($titleAnalysis['score'] < 80) {
                $suggestions[] = [
                    'type' => 'title',
                    'priority' => 'high',
                    'current_score' => $titleAnalysis['score'],
                    'issues' => $titleAnalysis['issues'],
                    'estimated_improvement' => '+15-25 points',
                ];
            }
        }

        // More suggestions would be added here (description, attributes, etc.)

        return [
            'item_id' => $itemId,
            'current_score' => $currentScore,
            'suggestions' => $suggestions,
            'priority_count' => [
                'high' => count(array_filter($suggestions, fn($s) => $s['priority'] === 'high')),
                'medium' => count(array_filter($suggestions, fn($s) => $s['priority'] === 'medium')),
                'low' => count(array_filter($suggestions, fn($s) => $s['priority'] === 'low')),
            ],
        ];
    }

    /**
     * Calculate overall quality score for a listing
     *
     * @param array $listingData
     * @return int Score 0-100
     */
    private function calculateScore(array $listingData): int
    {
        $scores = [];

        // Title score (25 points max)
        if (!empty($listingData['title'])) {
            $titleAnalysis = $this->titleOptimizer->analyze($listingData['title']);
            $scores['title'] = ($titleAnalysis['score'] / 100) * 25;
        } else {
            $scores['title'] = 0;
        }

        // Description score (20 points max) - simplified for now
        $descLength = mb_strlen($listingData['description'] ?? '');
        if ($descLength >= 1500) {
            $scores['description'] = 20;
        } elseif ($descLength >= 800) {
            $scores['description'] = 15;
        } elseif ($descLength >= 400) {
            $scores['description'] = 10;
        } else {
            $scores['description'] = 5;
        }

        // Attributes score (25 points max)
        $attrCount = count($listingData['attributes'] ?? []);
        if ($attrCount >= 20) {
            $scores['attributes'] = 25;
        } elseif ($attrCount >= 15) {
            $scores['attributes'] = 20;
        } elseif ($attrCount >= 10) {
            $scores['attributes'] = 15;
        } else {
            $scores['attributes'] = min($attrCount, 10);
        }

        // Images score (15 points max)
        $imageCount = count($listingData['images'] ?? []);
        if ($imageCount >= 6) {
            $scores['images'] = 15;
        } else {
            $scores['images'] = $imageCount * 2.5;
        }

        // Price score (10 points max) - default to 8 if price exists
        $scores['price'] = !empty($listingData['price']) ? 8 : 0;

        // Shipping score (5 points max)
        $scores['shipping'] = ($listingData['free_shipping'] ?? false) ? 5 : 3;

        return (int) round(array_sum($scores));
    }

    /**
     * Apply optimizations to listing data (simulation)
     *
     * @param array $currentData
     * @param array $optimizations
     * @return array
     */
    private function applyOptimizations(array $currentData, array $optimizations): array
    {
        $optimized = $currentData;

        if (isset($optimizations['title']['optimized_title'])) {
            $optimized['title'] = $optimizations['title']['optimized_title'];
        }

        if (isset($optimizations['description']['description'])) {
            $optimized['description'] = $optimizations['description']['description'];
        }

        // More optimizations would be applied here

        return $optimized;
    }

    /**
     * Get current listing data from Mercado Livre API
     *
     * @param string $itemId
     * @return array|null
     */
    private function getCurrentListingData(string $itemId): ?array
    {
        try {
            // Use ItemService if available (real ML API)
            if ($this->itemService) {
                $mlItem = $this->itemService->getItem($itemId);

                if ($mlItem && isset($mlItem['id'])) {
                    return $this->normalizeMLItem($mlItem);
                }
            }

            // Fallback: try to create ItemService with session account
            $accountId = $this->accountId ?? ($_SESSION['active_ml_account_id'] ?? null);
            if ($accountId) {
                $itemService = new ItemService($accountId);
                $mlItem = $itemService->getItem($itemId);

                if ($mlItem && isset($mlItem['id'])) {
                    return $this->normalizeMLItem($mlItem);
                }
            }

            return null;
        } catch (\Exception $e) {
            log_error('Failed to fetch item', ['service' => 'AIOptimizationEngine', 'item_id' => $itemId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    // normalizeMLItem() and extractMLAttribute() provided by NormalizesMLItems trait.
    // fetchDescription() is local since it needs $this->mlClient with session fallback.

    /**
     * Fetch description separately (ML API returns description in separate endpoint)
     */
    private function fetchDescription(string $itemId): string
    {
        if (!$itemId) return '';

        try {
            $client = $this->mlClient;
            if ($client === null) {
                $accountId = $this->accountId ?? ($_SESSION['active_ml_account_id'] ?? null);
                if ($accountId) {
                    $client = new MercadoLivreClient((int)$accountId);
                }
            }

            if ($client !== null) {
                $desc = $client->get("/items/{$itemId}/description");
                return $desc['plain_text'] ?? $desc['text'] ?? '';
            }
        } catch (\Exception $e) {
            // Description might not exist
        }

        return '';
    }

    /**
     * Apply optimizations to Mercado Livre via API
     *
     * @param string $itemId
     * @param array $optimizations
     * @return array Result with success status
     */
    public function applyToMercadoLivre(string $itemId, array $optimizations): array
    {
        $result = [
            'success' => false,
            'item_id' => $itemId,
            'applied' => [],
            'errors' => []
        ];

        try {
            $accountId = $this->accountId ?? ($_SESSION['active_ml_account_id'] ?? null);
            if (!$accountId) {
                $result['errors'][] = 'No account ID available';
                return $result;
            }

            $itemService = $this->itemService ?? new ItemService($accountId);
            $updateData = [];

            // Apply title optimization
            if (isset($optimizations['title']['optimized_title'])) {
                $updateData['title'] = $optimizations['title']['optimized_title'];
                $result['applied'][] = 'title';
            }

            // Apply description optimization
            if (isset($optimizations['description']['description'])) {
                // Description is updated via separate endpoint
                try {
                    $client = $this->mlClient ?? new MercadoLivreClient((int)$accountId);
                    $client->put("/items/{$itemId}/description", [
                        'plain_text' => $optimizations['description']['description']
                    ]);
                    $result['applied'][] = 'description';
                } catch (\Exception $e) {
                    $result['errors'][] = 'Description update failed: ' . $e->getMessage();
                }
            }

            // Apply attribute optimizations
            if (isset($optimizations['attributes']['completed_attributes'])) {
                $updateData['attributes'] = $optimizations['attributes']['completed_attributes'];
                $result['applied'][] = 'attributes';
            }

            // Update item via ML API
            if (!empty($updateData)) {
                $apiResult = $itemService->updateItem($itemId, $updateData);

                if (isset($apiResult['error'])) {
                    $result['errors'][] = $apiResult['error'];
                } else {
                    $result['success'] = true;
                    $result['ml_response'] = $apiResult;
                }
            } else {
                $result['success'] = count($result['applied']) > 0;
            }
        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Set account ID for API calls
     */
    public function setAccountId(int $accountId): void
    {
        $this->accountId = $accountId;
        $this->itemService = new ItemService($accountId);
        $this->mlClient = new MercadoLivreClient($accountId);
    }

    /**
     * Check if AI is available (circuit breaker aware)
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        $stats = $this->providerManager->getProviderStats();
        return ($stats['available_count'] ?? 0) > 0;
    }

    /**
     * Get AI provider info
     *
     * @return array
     */
    public function getProviderInfo(): array
    {
        $provider = $this->providerManager->getPrimaryProvider();

        if (!$provider) {
            return [
                'provider' => 'none',
                'available' => false
            ];
        }

        return [
            'provider' => $provider->getName(),
            'model' => $provider->getDefaultModel(),
            'available' => $provider->isAvailable(),
            'config' => [
                'temperature' => $this->config['temperature'],
                'max_tokens' => $this->config['max_tokens'],
            ],
        ];
    }
}
