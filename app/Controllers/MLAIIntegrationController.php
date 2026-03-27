<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MercadoLivre\MercadoLivreAIIntegrationService;

/**
 * Controller for ML ↔ AI Integration endpoints.
 *
 * Provides API routes for:
 *  - Health check (ML API + AI providers)
 *  - Context-aware item optimization
 *  - Full pipeline (fetch → enrich → optimize → apply)
 *  - Batch pipeline
 */
class MLAIIntegrationController extends BaseController
{
    private ?MercadoLivreAIIntegrationService $service = null;

    /**
     * Lazy-load the integration service with the active account.
     */
    private function getService(): MercadoLivreAIIntegrationService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        $accountId = $this->getActiveAccountId();
        if ($accountId === null) {
            // Degraded mode: allow single-token operation when ML_ACCESS_TOKEN is configured.
            // This keeps ML-AI endpoints usable even if the DB/session-based multi-account layer is unavailable.
            $envToken = (string)($_ENV['ML_ACCESS_TOKEN'] ?? getenv('ML_ACCESS_TOKEN') ?? '');
            $allowTokenHeaderRaw = $_ENV['ML_ALLOW_TOKEN_HEADER'] ?? getenv('ML_ALLOW_TOKEN_HEADER') ?? null;
            $allowTokenHeader = filter_var($allowTokenHeaderRaw, FILTER_VALIDATE_BOOLEAN);
            $headerToken = $allowTokenHeader ? (string)($_SERVER['HTTP_X_ML_ACCESS_TOKEN'] ?? '') : '';
            if ($envToken !== '' || ($allowTokenHeader && $headerToken !== '')) {
                $accountId = 0; // sentinel for env-token mode
            } else {
                $this->jsonError(
                    $allowTokenHeader
                        ? 'No active ML account. Select an account first or send X-ML-Account-Id / ?ml_account_id. Alternatively configure ML_ACCESS_TOKEN or send X-ML-Access-Token (ML_ALLOW_TOKEN_HEADER=true) for single-token mode.'
                        : 'No active ML account. Select an account first or send X-ML-Account-Id / ?ml_account_id. Alternatively configure ML_ACCESS_TOKEN for single-token mode.',
                    401
                );
            }
        }

        $this->service = new MercadoLivreAIIntegrationService($accountId);
        return $this->service;
    }

    /**
     * GET /api/ml-ai/health
     * Unified health status of ML API + AI providers.
     */
    public function health(): void
    {
        $status = $this->getService()->getHealthStatus();
        $this->jsonSuccess($status);
    }

    /**
     * GET /api/ml-ai/enrich/{itemId}
     * Get item data enriched with market context.
     */
    public function enrich(string $itemId): void
    {
        if (empty($itemId)) {
            $this->jsonError('Item ID is required', 400);
        }

        $enriched = $this->getService()->getEnrichedItemData($itemId);
        if ($enriched === null) {
            $this->jsonError('Failed to fetch or enrich item data', 404);
        }

        $this->jsonSuccess(['item' => $enriched]);
    }

    /**
     * POST /api/ml-ai/optimize/{itemId}
     * Optimize item with AI + ML market context.
     *
     * Body: { optimize_title: bool, optimize_description: bool, optimize_attributes: bool }
     */
    public function optimize(string $itemId): void
    {
        if (empty($itemId)) {
            $this->jsonError('Item ID is required', 400);
        }

        $options = $this->request->json() ?? [];
        $result = $this->getService()->optimizeWithContext($itemId, $options);

        if (!$result['success']) {
            $this->jsonError($result['error'] ?? 'Optimization failed', 500);
        }

        $this->jsonSuccess($result);
    }

    /**
     * POST /api/ml-ai/apply/{itemId}
     * Apply optimizations to the ML listing.
     *
     * Body: { optimizations: {...} } — from optimize response
     */
    public function apply(string $itemId): void
    {
        if (empty($itemId)) {
            $this->jsonError('Item ID is required', 400);
        }

        $body = $this->request->json() ?? [];
        $optimizations = $body['optimizations'] ?? [];

        if (empty($optimizations)) {
            $this->jsonError('No optimizations provided', 400);
        }

        $result = $this->getService()->applyOptimizations($itemId, $optimizations);
        
        if (!$result['success']) {
            $noValidOptimizations = str_contains($result['error'] ?? '', 'No valid optimizations generated for apply');
            $status = $noValidOptimizations ? 422 : 500;
        } else {
            $status = 200;
        }

        $this->json(array_merge(['success' => $result['success']], $result), $status);
    }

    /**
     * POST /api/ml-ai/pipeline/{itemId}
     * Full end-to-end pipeline: fetch → enrich → optimize → apply.
     *
     * Body: { auto_apply: bool, optimize_title: bool, optimize_description: bool, optimize_attributes: bool }
     */
    public function pipeline(string $itemId): void
    {
        if (empty($itemId)) {
            $this->jsonError('Item ID is required', 400);
        }

        $body = $this->request->json() ?? [];
        $autoApply = (bool)($body['auto_apply'] ?? false);
        $options = $body;
        unset($options['auto_apply']);

        $result = $this->getService()->fullPipeline($itemId, $options, $autoApply);

        if (!$result['success']) {
            $this->jsonError($result['error'] ?? 'Pipeline failed', 500);
        }

        $this->jsonSuccess($result);
    }

    /**
     * POST /api/ml-ai/batch
     * Batch pipeline for multiple items.
     *
     * Body: { item_ids: string[], auto_apply: bool, optimize_title: bool, ... }
     */
    public function batch(): void
    {
        $body = $this->request->json() ?? [];
        $itemIds = $body['item_ids'] ?? [];

        if (empty($itemIds) || !is_array($itemIds)) {
            $this->jsonError('item_ids array is required', 400);
        }

        $normalizedIds = [];
        foreach ($itemIds as $id) {
            if (!is_string($id) || trim($id) === '') {
                $this->jsonError('All item_ids must be non-empty strings', 400);
            }
            $normalizedIds[] = trim($id);
        }
        $itemIds = array_values(array_unique($normalizedIds));

        if (count($itemIds) > 50) {
            $this->jsonError('Maximum 50 items per batch', 400);
        }

        $autoApply = (bool)($body['auto_apply'] ?? false);
        $options = $body;
        unset($options['item_ids'], $options['auto_apply']);

        $result = $this->getService()->batchPipeline($itemIds, $options, $autoApply);

        $this->jsonSuccess($result);
    }

    /**
     * GET /api/ml-ai/items
     * List seller items with SEO scores for optimization triage.
     *
     * Query: ?category=MLB...&offset=0&limit=20
     */
    public function listItems(): void
    {
        $filters = \App\Services\ValidationService::getParams([
            'category' => ['type' => 'string', 'default' => null],
            'offset' => ['type' => 'int', 'default' => 0],
            'limit' => ['type' => 'int', 'default' => 20],
        ]);

        // Limitar máximo
        $filters['limit'] = min($filters['limit'], 50);
        $filters['offset'] = max($filters['offset'], 0);

        $result = $this->getService()->getItemsForOptimization(array_filter($filters));
        $this->jsonSuccess($result);
    }

    /**
     * GET /api/ml-ai/status/{itemId}
     * Get detailed optimization status for a specific item.
     */
    public function itemStatus(string $itemId): void
    {
        if (empty($itemId)) {
            $this->jsonError('Item ID is required', 400);
        }

        $status = $this->getService()->getItemStatus($itemId);
        if ($status === null) {
            $this->jsonError('Item not found or failed to fetch', 404);
        }

        $this->jsonSuccess(['item' => $status]);
    }

    /**
     * PUT /api/ml-ai/description/{itemId}
     * Update item description directly.
     *
     * Body: { description: "..." }
     */
    public function updateDescription(string $itemId): void
    {
        if (empty($itemId)) {
            $this->jsonError('Item ID is required', 400);
        }

        $body = $this->request->json() ?? [];
        $description = $body['description'] ?? '';

        if (empty(trim($description))) {
            $this->jsonError('Description text is required', 400);
        }

        $result = $this->getService()->updateDescription($itemId, $description);

        if (!$result['success']) {
            $message = $result['message'] ?? 'Failed to update description';
            
            // Map validation errors to 400 Bad Request
            if (str_contains($message, 'cannot be empty') || str_contains($message, 'at least 50 characters')) {
                $this->jsonError($message, 400);
            }

            $this->jsonError($message, 500);
        }

        $this->jsonSuccess($result);
    }

    /**
     * GET /api/ml-ai/history/{itemId}
     * Get optimization history for an item.
     *
     * Query: ?limit=50
     */
    public function history(string $itemId): void
    {
        if (empty($itemId)) {
            $this->jsonError('Item ID is required', 400);
        }

        $limit = \App\Services\ValidationService::getParam('limit', 'int', 50);
        $limit = min($limit, 200); // Máximo 200
        $limit = max($limit, 1);   // Mínimo 1

        $result = $this->getService()->getOptimizationHistory($itemId, $limit);

        $this->jsonSuccess($result);
    }

    /**
     * POST /api/ml-ai/rollback/{itemId}
     * Rollback a specific optimization version.
     *
     * Body: { version_id: int, reason?: string }
     */
    public function rollback(string $itemId): void
    {
        if (empty($itemId)) {
            $this->jsonError('Item ID is required', 400);
        }

        $body = $this->request->json() ?? [];
        $versionId = (int)($body['version_id'] ?? 0);
        $reason = trim((string)($body['reason'] ?? ''));

        if ($versionId <= 0) {
            $this->jsonError('version_id is required and must be a positive integer', 400);
        }

        $result = $this->getService()->rollbackOptimization($itemId, $versionId, $reason);

        if (!$result['success']) {
            $this->jsonError($result['message'] ?? 'Rollback failed', 500);
        }

        $this->jsonSuccess($result);
    }

    /**
     * GET /api/ml-ai/stats
     * Get optimization statistics for the current account.
     */
    public function stats(): void
    {
        $result = $this->getService()->getOptimizationStats();
        $this->jsonSuccess($result);
    }

    /**
     * GET /api/ml-ai/compare
     * Compare two optimization versions side by side.
     *
     * Query: ?v1=123&v2=456
     */
    public function compare(): void
    {
        $params = \App\Services\ValidationService::getParams([
            'v1' => ['type' => 'int', 'default' => 0],
            'v2' => ['type' => 'int', 'default' => 0],
        ]);

        $v1 = $params['v1'];
        $v2 = $params['v2'];

        if ($v1 <= 0 || $v2 <= 0) {
            $this->jsonError('Both v1 and v2 query params are required (positive integers)', 400);
        }

        if ($v1 === $v2) {
            $this->jsonError('v1 and v2 must be different version IDs', 400);
        }

        $result = $this->getService()->compareVersions($v1, $v2);

        if (!$result['success']) {
            $this->jsonError($result['error'] ?? 'Comparison failed', 404);
        }

        $this->jsonSuccess($result);
    }

    /**
     * POST /api/ml-ai/impact/{versionId}
     * Track measured impact for an optimization version.
     *
     * Body: { views_delta?: float, sales_delta?: float, conversion_delta?: float, notes?: string, ... }
     */
    public function impact(string $versionId): void
    {
        $id = (int)$versionId;
        if ($id <= 0) {
            $this->jsonError('version_id must be a positive integer', 400);
        }

        $body = $this->request->json() ?? [];
        if (empty($body)) {
            $this->jsonError('Impact data body is required', 400);
        }

        // Sanitize — only allow known metric keys + arbitrary extras
        $allowed = ['views_delta', 'sales_delta', 'conversion_delta', 'position_delta', 'notes', 'measured_at'];
        $impactData = [];
        foreach ($body as $key => $value) {
            if (in_array($key, $allowed, true) || is_numeric($value)) {
                $impactData[$key] = $value;
            }
        }

        if (empty($impactData)) {
            $this->jsonError('No valid impact metrics provided', 400);
        }

        // Add measurement timestamp if not provided
        if (!isset($impactData['measured_at'])) {
            $impactData['measured_at'] = date('Y-m-d H:i:s');
        }

        $result = $this->getService()->trackImpact($id, $impactData);

        if (!$result['success']) {
            $this->jsonError($result['message'] ?? 'Impact tracking failed', 500);
        }

        $this->jsonSuccess($result);
    }

    /**
     * POST /api/ml-ai/cleanup
     * Clean old optimization snapshots (retention policy).
     *
     * Body: { days_to_keep?: int } (default: 90)
     */
    public function cleanup(): void
    {
        $body = $this->request->json() ?? [];
        $daysToKeep = (int)($body['days_to_keep'] ?? 90);

        if ($daysToKeep < 1 || $daysToKeep > 365) {
            $this->jsonError('days_to_keep must be between 1 and 365', 400);
        }

        $result = $this->getService()->cleanupSnapshots($daysToKeep);

        if (!$result['success']) {
            $this->jsonError($result['error'] ?? 'Cleanup failed', 500);
        }

        $this->jsonSuccess($result);
    }
}
