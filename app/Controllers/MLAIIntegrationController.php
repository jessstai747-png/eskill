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
            $this->jsonError('No active ML account. Please select an account first.', 401);
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
        $status = $result['success'] ? 200 : 500;
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

        if (count($itemIds) > 50) {
            $this->jsonError('Maximum 50 items per batch', 400);
        }

        $autoApply = (bool)($body['auto_apply'] ?? false);
        $options = $body;
        unset($options['item_ids'], $options['auto_apply']);

        $result = $this->getService()->batchPipeline($itemIds, $options, $autoApply);

        $this->jsonSuccess($result);
    }
}
