<?php

declare(strict_types=1);

namespace App\Services\MercadoLivre;

use App\Services\MercadoLivreClient;
use App\Services\AI\Core\AIProviderManager;
use App\Services\AI\Core\AIOptimizationEngine;
use App\Services\ItemService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * MercadoLivre ↔ AI Integration Service
 *
 * Bridges the Mercado Livre API with the AI Provider system (with circuit breaker).
 * Provides:
 *  - Unified health status (ML API + AI providers)
 *  - ML context enrichment (trends, competitors, category attributes)
 *  - Context-aware AI optimization with template fallback
 *  - Full pipeline: fetch → enrich → optimize → apply back to ML
 *  - Batch processing with circuit breaker awareness
 */
class MercadoLivreAIIntegrationService
{
    private MercadoLivreClient $mlClient;
    private AIProviderManager $aiProviderManager;
    private AIOptimizationEngine $optimizationEngine;
    private ?ItemService $itemService = null;
    private Logger $logger;
    private int $accountId;

    /** @var array<string, mixed> Cached category data to avoid repeated API calls */
    private array $categoryCache = [];

    public function __construct(int $accountId)
    {
        $this->accountId = $accountId;
        $this->mlClient = new MercadoLivreClient($accountId);
        $this->aiProviderManager = new AIProviderManager();
        $this->optimizationEngine = new AIOptimizationEngine(null, $accountId);
        $this->itemService = new ItemService($accountId);

        $this->logger = new Logger('ml_ai_integration');
        $logPath = dirname(__DIR__, 2) . '/storage/logs/ml_ai_integration.log';
        if (is_dir(dirname($logPath))) {
            $this->logger->pushHandler(new StreamHandler($logPath, Logger::INFO));
        }
    }

    // ─── Health & Diagnostics ───────────────────────────────────────

    /**
     * Get unified health status of both ML API and AI providers.
     *
     * @return array{ml: array, ai: array, integrated: bool, recommendations: string[]}
     */
    public function getHealthStatus(): array
    {
        $mlHealth = $this->getMercadoLivreHealth();
        $aiHealth = $this->getAIHealth();

        $integrated = $mlHealth['connected'] && $aiHealth['available_count'] > 0;

        $recommendations = [];
        if (!$mlHealth['connected']) {
            $recommendations[] = 'ML API disconnected — check access token and account configuration';
        }
        if ($aiHealth['available_count'] === 0) {
            $recommendations[] = 'All AI providers unavailable — check API keys or wait for circuit breaker reset';
        }
        if ($mlHealth['connected'] && $aiHealth['available_count'] === 0) {
            $recommendations[] = 'ML connected but no AI — template-based optimization will be used as fallback';
        }

        return [
            'ml' => $mlHealth,
            'ai' => $aiHealth,
            'integrated' => $integrated,
            'recommendations' => $recommendations,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Check ML API connectivity and capabilities.
     */
    private function getMercadoLivreHealth(): array
    {
        try {
            $diagnosis = $this->mlClient->diagnose();

            return [
                'connected' => ($diagnosis['token_valid'] ?? false) || ($diagnosis['public_api'] ?? false),
                'token_valid' => $diagnosis['token_valid'] ?? false,
                'public_api' => $diagnosis['public_api'] ?? false,
                'auth_ok' => $diagnosis['auth_ok'] ?? false,
                'items_count' => $diagnosis['items_count'] ?? 0,
                'account_id' => $this->accountId,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('ML health check failed', [
                'account_id' => $this->accountId,
                'error' => $e->getMessage(),
            ]);

            return [
                'connected' => false,
                'token_valid' => false,
                'public_api' => false,
                'auth_ok' => false,
                'items_count' => 0,
                'account_id' => $this->accountId,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check AI provider availability with circuit breaker awareness.
     */
    private function getAIHealth(): array
    {
        $stats = $this->aiProviderManager->getProviderStats();
        $providers = $this->aiProviderManager->getAvailableProviders();

        $providerDetails = [];
        foreach ($providers as $name => $info) {
            $providerDetails[$name] = [
                'name' => $info['name'] ?? $name,
                'model' => $info['model'] ?? 'unknown',
                'available' => $info['available'] ?? false,
            ];
        }

        return [
            'available_count' => $stats['available_count'] ?? 0,
            'total_providers' => $stats['total_providers'] ?? 0,
            'preferred_provider' => $stats['preferred_provider'] ?? 'none',
            'fallback_enabled' => $stats['fallback_enabled'] ?? false,
            'providers' => $providerDetails,
        ];
    }

    // ─── Context Enrichment ─────────────────────────────────────────

    /**
     * Fetch an item with full ML market context for AI optimization.
     *
     * Returns the item data enriched with:
     *  - Category name and path
     *  - Category required/optional attributes
     *  - Market trends for the item's category
     *  - Competitor titles and pricing
     *
     * @param string $itemId ML item ID (e.g. MLB1234567890)
     * @return array|null Enriched item data or null on failure
     */
    public function getEnrichedItemData(string $itemId): ?array
    {
        try {
            $item = $this->mlClient->getItemDetails($itemId);

            if (empty($item) || isset($item['error'])) {
                $this->logger->warning('Failed to fetch item from ML', [
                    'item_id' => $itemId,
                    'error' => $item['error'] ?? 'empty response',
                ]);
                return null;
            }

            $normalized = $this->normalizeMLItem($item);
            $categoryId = $normalized['category_id'] ?? '';

            // Enrich with market context (parallel-safe: each call is independent)
            $normalized['market_context'] = [
                'category' => $this->getCategoryInfo($categoryId),
                'category_attributes' => $this->getCategoryAttributes($categoryId),
                'trends' => $this->getTrends($categoryId),
                'competitors' => $this->getCompetitorContext(
                    $normalized['title'] ?? '',
                    $categoryId
                ),
            ];

            $this->logger->info('Item enriched with market context', [
                'item_id' => $itemId,
                'category' => $categoryId,
                'trends_count' => count($normalized['market_context']['trends']),
                'competitors_count' => count($normalized['market_context']['competitors']['titles'] ?? []),
            ]);

            return $normalized;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to enrich item data', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get category info with in-memory cache.
     */
    private function getCategoryInfo(string $categoryId): array
    {
        if (empty($categoryId)) {
            return [];
        }

        $cacheKey = "cat_{$categoryId}";
        if (isset($this->categoryCache[$cacheKey])) {
            return $this->categoryCache[$cacheKey];
        }

        try {
            $category = $this->mlClient->getCategory($categoryId);
            $result = [
                'id' => $category['id'] ?? $categoryId,
                'name' => $category['name'] ?? '',
                'path' => array_map(
                    fn(array $p): string => $p['name'] ?? '',
                    $category['path_from_root'] ?? []
                ),
            ];
            $this->categoryCache[$cacheKey] = $result;
            return $result;
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to fetch category', [
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return ['id' => $categoryId, 'name' => '', 'path' => []];
        }
    }

    /**
     * Get required and optional attributes for a category.
     */
    private function getCategoryAttributes(string $categoryId): array
    {
        if (empty($categoryId)) {
            return [];
        }

        $cacheKey = "cat_attrs_{$categoryId}";
        if (isset($this->categoryCache[$cacheKey])) {
            return $this->categoryCache[$cacheKey];
        }

        try {
            $attrs = $this->mlClient->getCategoryAttributes($categoryId);
            $result = [
                'required' => [],
                'optional' => [],
            ];

            foreach ($attrs as $attr) {
                $entry = [
                    'id' => $attr['id'] ?? '',
                    'name' => $attr['name'] ?? '',
                    'type' => $attr['value_type'] ?? 'string',
                    'values' => array_map(
                        fn(array $v): string => $v['name'] ?? '',
                        $attr['values'] ?? []
                    ),
                ];

                $tags = $attr['tags'] ?? [];
                if (isset($tags['required']) && $tags['required']) {
                    $result['required'][] = $entry;
                } else {
                    $result['optional'][] = $entry;
                }
            }

            $this->categoryCache[$cacheKey] = $result;
            return $result;
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to fetch category attributes', [
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return ['required' => [], 'optional' => []];
        }
    }

    /**
     * Get market trends for a category.
     */
    private function getTrends(string $categoryId): array
    {
        if (empty($categoryId)) {
            return [];
        }

        try {
            $trends = $this->mlClient->getTrends($categoryId);
            return array_slice(
                array_map(fn(array $t): string => $t['keyword'] ?? '', $trends),
                0,
                15
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to fetch trends', [
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get competitor titles and pricing context.
     */
    private function getCompetitorContext(string $title, string $categoryId): array
    {
        if (empty($title) || empty($categoryId)) {
            return ['titles' => [], 'avg_price' => 0, 'min_price' => 0, 'max_price' => 0];
        }

        try {
            // Extract first 3 meaningful words for search
            $words = array_filter(explode(' ', $title), fn(string $w): bool => mb_strlen($w) > 2);
            $keyword = implode(' ', array_slice(array_values($words), 0, 3));

            if (empty($keyword)) {
                return ['titles' => [], 'avg_price' => 0, 'min_price' => 0, 'max_price' => 0];
            }

            $results = $this->mlClient->searchByKeyword($keyword, $categoryId, 10);
            $items = $results['results'] ?? [];

            $titles = array_map(fn(array $item): string => $item['title'] ?? '', $items);
            $prices = array_filter(array_map(
                fn(array $item): float => floatval($item['price'] ?? 0),
                $items
            ));

            return [
                'titles' => array_slice($titles, 0, 10),
                'avg_price' => count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0,
                'min_price' => count($prices) > 0 ? min($prices) : 0,
                'max_price' => count($prices) > 0 ? max($prices) : 0,
                'total_results' => $results['paging']['total'] ?? 0,
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to fetch competitor context', [
                'title' => $title,
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return ['titles' => [], 'avg_price' => 0, 'min_price' => 0, 'max_price' => 0];
        }
    }

    // ─── AI Optimization with ML Context ────────────────────────────

    /**
     * Optimize an item using AI with full ML market context.
     *
     * If AI providers are all circuit-broken, falls back to template-based rules.
     *
     * @param string $itemId ML item ID
     * @param array $options {optimize_title: bool, optimize_description: bool, optimize_attributes: bool}
     * @return array Optimization result with suggestions and scores
     */
    public function optimizeWithContext(string $itemId, array $options = []): array
    {
        $startTime = microtime(true);

        // 1. Fetch enriched item data
        $enriched = $this->getEnrichedItemData($itemId);
        if ($enriched === null) {
            return [
                'success' => false,
                'error' => 'Failed to fetch item data from Mercado Livre',
                'item_id' => $itemId,
            ];
        }

        // 2. Check AI availability
        $aiStats = $this->aiProviderManager->getProviderStats();
        $aiAvailable = ($aiStats['available_count'] ?? 0) > 0;

        $optimizations = [];
        $method = $aiAvailable ? 'ai' : 'template';

        if ($aiAvailable) {
            $optimizations = $this->optimizeWithAI($enriched, $options);
        } else {
            $this->logger->info('AI unavailable, using template fallback', [
                'item_id' => $itemId,
                'circuit_broken_count' => ($aiStats['total_providers'] ?? 0) - ($aiStats['available_count'] ?? 0),
            ]);
            $optimizations = $this->optimizeWithTemplate($enriched, $options);
        }

        $duration = round(microtime(true) - $startTime, 3);

        $this->logger->info('Optimization completed', [
            'item_id' => $itemId,
            'method' => $method,
            'duration_s' => $duration,
            'has_title' => isset($optimizations['title']),
            'has_description' => isset($optimizations['description']),
            'has_attributes' => isset($optimizations['attributes']),
        ]);

        return [
            'success' => true,
            'item_id' => $itemId,
            'method' => $method,
            'current' => [
                'title' => $enriched['title'] ?? '',
                'description' => $enriched['description'] ?? '',
                'attributes_count' => count($enriched['attributes'] ?? []),
                'images_count' => count($enriched['images'] ?? []),
            ],
            'optimizations' => $optimizations,
            'market_context' => [
                'category' => $enriched['market_context']['category']['name'] ?? '',
                'trends' => array_slice($enriched['market_context']['trends'] ?? [], 0, 5),
                'competitor_count' => $enriched['market_context']['competitors']['total_results'] ?? 0,
                'avg_competitor_price' => $enriched['market_context']['competitors']['avg_price'] ?? 0,
            ],
            'duration_seconds' => $duration,
        ];
    }

    /**
     * Optimize using AI providers with enriched ML context.
     */
    private function optimizeWithAI(array $enriched, array $options): array
    {
        $optimizations = [];

        $optimizeTitle = $options['optimize_title'] ?? true;
        $optimizeDescription = $options['optimize_description'] ?? true;
        $optimizeAttributes = $options['optimize_attributes'] ?? true;

        // Title optimization with market context
        if ($optimizeTitle) {
            $titlePrompt = $this->buildTitlePrompt($enriched);
            $titleResult = $this->aiProviderManager->chat([
                ['role' => 'system', 'content' => 'You are an expert SEO optimizer for Mercado Livre marketplace in Brazil. Respond in Portuguese (BR). Return ONLY the optimized title, nothing else.'],
                ['role' => 'user', 'content' => $titlePrompt],
            ]);

            if (!isset($titleResult['error'])) {
                $optimizedTitle = trim($titleResult['content'] ?? $titleResult['text'] ?? '');
                // Enforce ML title limit (60 chars)
                if (mb_strlen($optimizedTitle) > 60) {
                    $optimizedTitle = mb_substr($optimizedTitle, 0, 60);
                }
                $optimizations['title'] = [
                    'optimized_title' => $optimizedTitle,
                    'original_title' => $enriched['title'] ?? '',
                    'provider' => $titleResult['fallback_provider'] ?? $titleResult['provider'] ?? 'unknown',
                ];
            } else {
                $this->logger->warning('AI title optimization failed, using template', [
                    'item_id' => $enriched['id'] ?? '',
                    'error' => $titleResult['message'] ?? 'unknown',
                ]);
                $optimizations['title'] = $this->templateTitle($enriched);
            }
        }

        // Description optimization with market context
        if ($optimizeDescription) {
            $descPrompt = $this->buildDescriptionPrompt($enriched);
            $descResult = $this->aiProviderManager->chat([
                ['role' => 'system', 'content' => 'You are an expert copywriter for Mercado Livre marketplace in Brazil. Write compelling product descriptions in Portuguese (BR). Return ONLY the description text.'],
                ['role' => 'user', 'content' => $descPrompt],
            ]);

            if (!isset($descResult['error'])) {
                $optimizations['description'] = [
                    'description' => trim($descResult['content'] ?? $descResult['text'] ?? ''),
                    'original_description' => $enriched['description'] ?? '',
                    'provider' => $descResult['fallback_provider'] ?? $descResult['provider'] ?? 'unknown',
                ];
            } else {
                $this->logger->warning('AI description optimization failed, using template', [
                    'item_id' => $enriched['id'] ?? '',
                    'error' => $descResult['message'] ?? 'unknown',
                ]);
                $optimizations['description'] = $this->templateDescription($enriched);
            }
        }

        // Attribute completion
        if ($optimizeAttributes) {
            $optimizations['attributes'] = $this->suggestMissingAttributes($enriched);
        }

        return $optimizations;
    }

    /**
     * Template-based optimization fallback when all AI providers are unavailable.
     */
    private function optimizeWithTemplate(array $enriched, array $options): array
    {
        $optimizations = [];

        if ($options['optimize_title'] ?? true) {
            $optimizations['title'] = $this->templateTitle($enriched);
        }

        if ($options['optimize_description'] ?? true) {
            $optimizations['description'] = $this->templateDescription($enriched);
        }

        if ($options['optimize_attributes'] ?? true) {
            $optimizations['attributes'] = $this->suggestMissingAttributes($enriched);
        }

        return $optimizations;
    }

    // ─── Apply Back to ML ───────────────────────────────────────────

    /**
     * Apply optimizations back to the Mercado Livre listing.
     *
     * @param string $itemId ML item ID
     * @param array $optimizations Result from optimizeWithContext()['optimizations']
     * @return array{success: bool, applied: string[], errors: string[]}
     */
    public function applyOptimizations(string $itemId, array $optimizations): array
    {
        $result = [
            'success' => false,
            'item_id' => $itemId,
            'applied' => [],
            'errors' => [],
        ];

        try {
            $updateData = [];

            // Title
            if (isset($optimizations['title']['optimized_title'])) {
                $updateData['title'] = $optimizations['title']['optimized_title'];
            }

            // Attributes
            if (!empty($optimizations['attributes']['completed_attributes'])) {
                $updateData['attributes'] = $optimizations['attributes']['completed_attributes'];
            }

            // Update main item data
            if (!empty($updateData)) {
                $apiResult = $this->itemService->updateItem($itemId, $updateData);

                if (isset($apiResult['error'])) {
                    $result['errors'][] = 'Item update failed: ' . ($apiResult['error'] ?? 'unknown');
                } else {
                    if (isset($updateData['title'])) {
                        $result['applied'][] = 'title';
                    }
                    if (isset($updateData['attributes'])) {
                        $result['applied'][] = 'attributes';
                    }
                }
            }

            // Description (separate endpoint)
            if (isset($optimizations['description']['description'])) {
                try {
                    $this->mlClient->put("/items/{$itemId}/description", [
                        'plain_text' => $optimizations['description']['description'],
                    ]);
                    $result['applied'][] = 'description';
                } catch (\Throwable $e) {
                    $result['errors'][] = 'Description update failed: ' . $e->getMessage();
                }
            }

            $result['success'] = count($result['applied']) > 0 && count($result['errors']) === 0;

            $this->logger->info('Optimizations applied to ML', [
                'item_id' => $itemId,
                'applied' => $result['applied'],
                'errors' => $result['errors'],
            ]);
        } catch (\Throwable $e) {
            $result['errors'][] = $e->getMessage();
            $this->logger->error('Failed to apply optimizations', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    // ─── Full Pipeline ──────────────────────────────────────────────

    /**
     * Full end-to-end pipeline: fetch → enrich → optimize → apply.
     *
     * @param string $itemId ML item ID
     * @param array $options Optimization options
     * @param bool $autoApply Whether to apply optimizations automatically
     * @return array Complete pipeline result
     */
    public function fullPipeline(string $itemId, array $options = [], bool $autoApply = false): array
    {
        $this->logger->info('Starting full pipeline', [
            'item_id' => $itemId,
            'auto_apply' => $autoApply,
            'options' => $options,
        ]);

        $pipelineStart = microtime(true);

        // Step 1: Optimize with context (includes fetch + enrich)
        $optimizationResult = $this->optimizeWithContext($itemId, $options);

        if (!$optimizationResult['success']) {
            return [
                'success' => false,
                'item_id' => $itemId,
                'step_failed' => 'optimize',
                'error' => $optimizationResult['error'] ?? 'Optimization failed',
                'duration_seconds' => round(microtime(true) - $pipelineStart, 3),
            ];
        }

        // Step 2: Apply if requested
        $applyResult = null;
        if ($autoApply) {
            $applyResult = $this->applyOptimizations($itemId, $optimizationResult['optimizations']);
        }

        $duration = round(microtime(true) - $pipelineStart, 3);

        $this->logger->info('Full pipeline completed', [
            'item_id' => $itemId,
            'auto_apply' => $autoApply,
            'applied' => $applyResult['applied'] ?? [],
            'duration_s' => $duration,
        ]);

        return [
            'success' => true,
            'item_id' => $itemId,
            'optimization' => $optimizationResult,
            'applied' => $autoApply ? $applyResult : null,
            'auto_apply' => $autoApply,
            'duration_seconds' => $duration,
        ];
    }

    /**
     * Batch pipeline with circuit breaker awareness.
     *
     * Checks AI health every 5 items and switches to template fallback
     * if all providers become circuit-broken mid-batch.
     *
     * @param array<string> $itemIds List of ML item IDs
     * @param array $options Optimization options
     * @param bool $autoApply Whether to apply automatically
     * @return array{results: array, summary: array}
     */
    public function batchPipeline(array $itemIds, array $options = [], bool $autoApply = false): array
    {
        $results = [];
        $succeeded = 0;
        $failed = 0;
        $batchStart = microtime(true);

        $this->logger->info('Starting batch pipeline', [
            'item_count' => count($itemIds),
            'auto_apply' => $autoApply,
        ]);

        foreach ($itemIds as $index => $itemId) {
            // Recheck AI health every 5 items
            if ($index > 0 && $index % 5 === 0) {
                $aiStats = $this->aiProviderManager->getProviderStats();
                $this->logger->info('Batch health check', [
                    'processed' => $index,
                    'ai_available' => $aiStats['available_count'] ?? 0,
                ]);
            }

            try {
                $result = $this->fullPipeline($itemId, $options, $autoApply);
                $results[] = $result;

                if ($result['success']) {
                    $succeeded++;
                } else {
                    $failed++;
                }

                // Rate limiting: 200ms between items
                if ($index < count($itemIds) - 1) {
                    usleep(200_000);
                }
            } catch (\Throwable $e) {
                $failed++;
                $results[] = [
                    'success' => false,
                    'item_id' => $itemId,
                    'error' => $e->getMessage(),
                ];
                $this->logger->error('Batch item failed', [
                    'item_id' => $itemId,
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $duration = round(microtime(true) - $batchStart, 3);

        $this->logger->info('Batch pipeline completed', [
            'total' => count($itemIds),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'duration_s' => $duration,
        ]);

        return [
            'results' => $results,
            'summary' => [
                'total' => count($itemIds),
                'succeeded' => $succeeded,
                'failed' => $failed,
                'success_rate' => count($itemIds) > 0
                    ? round(($succeeded / count($itemIds)) * 100, 1)
                    : 0,
                'duration_seconds' => $duration,
                'avg_per_item' => count($itemIds) > 0
                    ? round($duration / count($itemIds), 3)
                    : 0,
            ],
        ];
    }

    // ─── Prompt Builders ────────────────────────────────────────────

    /**
     * Build AI prompt for title optimization with full market context.
     */
    private function buildTitlePrompt(array $enriched): string
    {
        $context = $enriched['market_context'] ?? [];
        $trends = $context['trends'] ?? [];
        $competitors = $context['competitors'] ?? [];
        $category = $context['category'] ?? [];

        $prompt = "Otimize o título deste anúncio para Mercado Livre.\n\n";
        $prompt .= "TÍTULO ATUAL: {$enriched['title']}\n";
        $prompt .= "MARCA: {$enriched['brand']}\n";
        $prompt .= "MODELO: {$enriched['model']}\n";
        $prompt .= "PREÇO: R\$ " . number_format($enriched['price'] ?? 0, 2, ',', '.') . "\n";

        if (!empty($category['name'])) {
            $prompt .= "CATEGORIA: {$category['name']}\n";
        }

        if (!empty($trends)) {
            $prompt .= "\nTENDÊNCIAS DE BUSCA NA CATEGORIA:\n";
            $prompt .= implode(', ', array_slice($trends, 0, 10)) . "\n";
        }

        if (!empty($competitors['titles'])) {
            $prompt .= "\nTÍTULOS DOS CONCORRENTES (top 5):\n";
            foreach (array_slice($competitors['titles'], 0, 5) as $i => $t) {
                $prompt .= ($i + 1) . ". {$t}\n";
            }
        }

        $prompt .= "\nREGRAS:\n";
        $prompt .= "- Máximo 60 caracteres\n";
        $prompt .= "- Palavras-chave mais importantes no início\n";
        $prompt .= "- Formato: [Produto] + [Modelo Moto] + [Marca] + [Diferencial]\n";
        $prompt .= "- NÃO use CAPS LOCK no título inteiro\n";
        $prompt .= "- Inclua palavras das tendências de busca quando relevante\n";
        $prompt .= "- Retorne APENAS o título otimizado, sem explicações\n";

        return $prompt;
    }

    /**
     * Build AI prompt for description optimization with market context.
     */
    private function buildDescriptionPrompt(array $enriched): string
    {
        $context = $enriched['market_context'] ?? [];
        $attrs = $context['category_attributes'] ?? [];
        $competitors = $context['competitors'] ?? [];

        $prompt = "Escreva uma descrição otimizada para este anúncio do Mercado Livre.\n\n";
        $prompt .= "PRODUTO: {$enriched['title']}\n";
        $prompt .= "MARCA: {$enriched['brand']}\n";
        $prompt .= "MODELO: {$enriched['model']}\n";
        $prompt .= "PREÇO: R\$ " . number_format($enriched['price'] ?? 0, 2, ',', '.') . "\n";

        if (!empty($enriched['attributes'])) {
            $prompt .= "\nATRIBUTOS EXISTENTES:\n";
            foreach (array_slice($enriched['attributes'], 0, 10) as $attr) {
                $prompt .= "- {$attr['name']}: {$attr['value']}\n";
            }
        }

        if (!empty($attrs['required'])) {
            $prompt .= "\nATRIBUTOS IMPORTANTES DA CATEGORIA:\n";
            foreach (array_slice($attrs['required'], 0, 8) as $attr) {
                $prompt .= "- {$attr['name']}\n";
            }
        }

        if (!empty($competitors['avg_price'])) {
            $prompt .= "\nCONTEXTO DE MERCADO:\n";
            $prompt .= "- Preço médio concorrência: R\$ " . number_format($competitors['avg_price'], 2, ',', '.') . "\n";
            $prompt .= "- Total de concorrentes: " . ($competitors['total_results'] ?? 0) . "\n";
        }

        $prompt .= "\nREGRAS:\n";
        $prompt .= "- Escreva em português do Brasil\n";
        $prompt .= "- Foque nos benefícios e diferenciais do produto\n";
        $prompt .= "- Mencione compatibilidade com motos quando aplicável\n";
        $prompt .= "- Inclua informações de garantia e qualidade\n";
        $prompt .= "- Use parágrafos curtos e fáceis de ler\n";
        $prompt .= "- NÃO inclua preço na descrição\n";
        $prompt .= "- Retorne APENAS o texto da descrição\n";

        return $prompt;
    }

    // ─── Template Fallbacks ─────────────────────────────────────────

    /**
     * Template-based title optimization (rule-based, no AI needed).
     */
    private function templateTitle(array $enriched): array
    {
        $brand = $enriched['brand'] ?? '';
        $model = $enriched['model'] ?? '';
        $title = $enriched['title'] ?? '';

        // Remove excessive CAPS
        if (mb_strtoupper($title) === $title && mb_strlen($title) > 5) {
            $title = mb_convert_case($title, MB_CASE_TITLE, 'UTF-8');
        }

        // Ensure brand/model are present
        $titleLower = mb_strtolower($title);
        if (!empty($brand) && mb_strpos($titleLower, mb_strtolower($brand)) === false) {
            $title = trim($title . ' ' . $brand);
        }
        if (!empty($model) && mb_strpos($titleLower, mb_strtolower($model)) === false) {
            $title = trim($title . ' ' . $model);
        }

        // Enforce 60 char limit
        if (mb_strlen($title) > 60) {
            $title = mb_substr($title, 0, 60);
        }

        return [
            'optimized_title' => $title,
            'original_title' => $enriched['title'] ?? '',
            'provider' => 'template',
        ];
    }

    /**
     * Template-based description generation (rule-based, no AI needed).
     */
    private function templateDescription(array $enriched): array
    {
        $lines = [];
        $lines[] = $enriched['title'] ?? 'Produto';
        $lines[] = '';

        if (!empty($enriched['brand'])) {
            $lines[] = "Marca: {$enriched['brand']}";
        }
        if (!empty($enriched['model'])) {
            $lines[] = "Modelo: {$enriched['model']}";
        }

        if (!empty($enriched['attributes'])) {
            $lines[] = '';
            $lines[] = 'Especificações:';
            foreach (array_slice($enriched['attributes'], 0, 15) as $attr) {
                if (!empty($attr['value'])) {
                    $lines[] = "- {$attr['name']}: {$attr['value']}";
                }
            }
        }

        $lines[] = '';
        $lines[] = 'Garantia de qualidade AWA Motos.';
        $lines[] = 'Enviamos para todo o Brasil.';

        return [
            'description' => implode("\n", $lines),
            'original_description' => $enriched['description'] ?? '',
            'provider' => 'template',
        ];
    }

    /**
     * Suggest missing attributes based on category requirements.
     */
    private function suggestMissingAttributes(array $enriched): array
    {
        $result = [
            'missing_required' => [],
            'missing_optional' => [],
            'completed_attributes' => [],
        ];

        $catAttrs = $enriched['market_context']['category_attributes'] ?? [];
        $existingIds = array_map(
            fn(array $a): string => $a['id'] ?? '',
            $enriched['attributes'] ?? []
        );

        // Check required attributes
        foreach ($catAttrs['required'] ?? [] as $required) {
            if (!in_array($required['id'], $existingIds, true)) {
                $result['missing_required'][] = [
                    'id' => $required['id'],
                    'name' => $required['name'],
                    'suggested_values' => array_slice($required['values'] ?? [], 0, 5),
                ];
            }
        }

        // Check optional attributes
        foreach ($catAttrs['optional'] ?? [] as $optional) {
            if (!in_array($optional['id'], $existingIds, true)) {
                $result['missing_optional'][] = [
                    'id' => $optional['id'],
                    'name' => $optional['name'],
                    'suggested_values' => array_slice($optional['values'] ?? [], 0, 5),
                ];
            }
        }

        return $result;
    }

    // ─── Item Normalization ─────────────────────────────────────────

    /**
     * Normalize ML API item response to internal format.
     */
    private function normalizeMLItem(array $mlItem): array
    {
        return [
            'id' => $mlItem['id'] ?? '',
            'title' => $mlItem['title'] ?? '',
            'description' => $mlItem['description'] ?? $this->fetchDescription($mlItem['id'] ?? ''),
            'category_id' => $mlItem['category_id'] ?? '',
            'brand' => $this->extractAttribute($mlItem, 'BRAND') ?? '',
            'model' => $this->extractAttribute($mlItem, 'MODEL') ?? '',
            'price' => floatval($mlItem['price'] ?? 0),
            'original_price' => floatval($mlItem['original_price'] ?? $mlItem['price'] ?? 0),
            'currency_id' => $mlItem['currency_id'] ?? 'BRL',
            'available_quantity' => intval($mlItem['available_quantity'] ?? 0),
            'sold_quantity' => intval($mlItem['sold_quantity'] ?? 0),
            'images' => array_map(fn(array $img): array => [
                'url' => $img['url'] ?? $img['secure_url'] ?? '',
                'id' => $img['id'] ?? '',
            ], $mlItem['pictures'] ?? []),
            'attributes' => array_map(fn(array $attr): array => [
                'id' => $attr['id'] ?? '',
                'name' => $attr['name'] ?? '',
                'value' => $attr['value_name'] ?? '',
            ], $mlItem['attributes'] ?? []),
            'free_shipping' => $mlItem['shipping']['free_shipping'] ?? false,
            'status' => $mlItem['status'] ?? 'unknown',
            'permalink' => $mlItem['permalink'] ?? '',
            'health' => $mlItem['health'] ?? null,
        ];
    }

    /**
     * Extract a specific attribute value from ML item.
     */
    private function extractAttribute(array $mlItem, string $attrId): ?string
    {
        foreach ($mlItem['attributes'] ?? [] as $attr) {
            if (($attr['id'] ?? '') === $attrId) {
                return $attr['value_name'] ?? null;
            }
        }
        return null;
    }

    /**
     * Fetch item description from ML API (separate endpoint).
     */
    private function fetchDescription(string $itemId): string
    {
        if (empty($itemId)) {
            return '';
        }

        try {
            $desc = $this->mlClient->get("/items/{$itemId}/description");
            return $desc['plain_text'] ?? $desc['text'] ?? '';
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to fetch description', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }
}
