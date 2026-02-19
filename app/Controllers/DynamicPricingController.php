<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\DynamicPricingService;

/**
 * Dynamic Pricing Controller
 * 
 * REST API para precificação dinâmica e automática
 * 
 * Endpoints:
 * - POST /api/pricing/:accountId/calculate/:itemId
 * - POST /api/pricing/:accountId/demand/:itemId
 * - POST /api/pricing/:accountId/liquidation/:sku
 * - POST /api/pricing/:accountId/apply/:itemId
 * - POST /api/pricing/:accountId/batch
 */
class DynamicPricingController
{
    private DynamicPricingService $pricingService;
    private Request $request;

    public function __construct(int $accountId)
    {
        $this->pricingService = new DynamicPricingService($accountId);
        $this->request = new Request();
    }

    /**
     * POST /api/pricing/:accountId/calculate/:itemId
     * Calcula preço ótimo baseado em concorrência
     * 
     * Body: {
     *   "min_margin": 0.15,
     *   "max_discount": 0.30,
     *   "aggressive": false
     * }
     */
    public function calculateOptimalPrice(string $itemId): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        
        $result = $this->pricingService->calculateOptimalPrice($itemId, $data);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * POST /api/pricing/:accountId/demand/:itemId?days=30
     * Calcula preço baseado em elasticidade de demanda
     */
    public function demandBasedPricing(string $itemId): void
    {
        header('Content-Type: application/json');

        $days = $this->request->getInt('days', 30);
        
        $result = $this->pricingService->demandBasedPricing($itemId, $days);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * POST /api/pricing/:accountId/liquidation/:sku
     * Estratégia para liquidar estoque parado
     * 
     * Body: {
     *   "days_in_stock": 90,
     *   "target_days": 30,
     *   "min_margin": 0.05
     * }
     */
    public function inventoryLiquidation(string $sku): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        
        $result = $this->pricingService->inventoryLiquidation($sku, $data);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * POST /api/pricing/:accountId/apply/:itemId
     * Aplica ajuste de preço automaticamente
     * 
     * Body: {
     *   "new_price": 199.99,
     *   "strategy": "competition"
     * }
     */
    public function applyPriceAdjustment(string $itemId): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['new_price'], $data['strategy'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'new_price and strategy are required'
            ]);
            return;
        }

        $result = $this->pricingService->applyPriceAdjustment(
            $itemId,
            (float)$data['new_price'],
            $data['strategy']
        );
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * POST /api/pricing/:accountId/batch
     * Análise batch de múltiplos itens
     * 
     * Body: {
     *   "item_ids": ["MLB123", "MLB456"],
     *   "strategy": "competition",
     *   "options": {
     *     "min_margin": 0.15,
     *     "aggressive": false
     *   }
     * }
     */
    public function batchAnalysis(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['item_ids']) || !is_array($data['item_ids'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'item_ids array is required'
            ]);
            return;
        }

        $result = $this->pricingService->batchAnalysis(
            $data['item_ids'],
            $data['strategy'] ?? 'competition',
            $data['options'] ?? []
        );
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }
}
