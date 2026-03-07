<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AIPredictionsService;

/**
 * AI Predictions Controller
 * 
 * REST API para previsões com Machine Learning
 * 
 * Endpoints:
 * - GET /api/ai/:accountId/predict-sales/:itemId
 * - GET /api/ai/:accountId/rising-stars
 * - GET /api/ai/:accountId/best-promo-time/:itemId
 * - GET /api/ai/:accountId/category-demand/:categoryId
 */
class AIPredictionsController extends BaseController
{
    private AIPredictionsService $aiService;

    public function __construct(int $accountId)
    {
        parent::__construct();
        $this->aiService = new AIPredictionsService($accountId);
    }

    /**
     * GET /api/ai/:accountId/predict-sales/:itemId?days=30
     * Prevê vendas futuras usando ML
     */
    public function predictSales(string $itemId): void
    {
        header('Content-Type: application/json');

        $days = $this->request->getInt('days', 30);
        
        if ($days < 1 || $days > 90) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'days must be between 1 and 90'
            ]);
            return;
        }
        
        $result = $this->aiService->predictSales($itemId, $days);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * GET /api/ai/:accountId/rising-stars?limit=20
     * Identifica produtos com potencial de crescimento
     */
    public function identifyRisingStars(): void
    {
        header('Content-Type: application/json');

        $limit = $this->request->getInt('limit', 20);
        
        $result = $this->aiService->identifyRisingStars($limit);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * GET /api/ai/:accountId/best-promo-time/:itemId
     * Prevê melhor momento para lançar promoção
     */
    public function predictBestPromotionTime(string $itemId): void
    {
        header('Content-Type: application/json');

        $result = $this->aiService->predictBestPromotionTime($itemId);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * GET /api/ai/:accountId/category-demand/:categoryId?days=30
     * Prevê demanda por categoria
     */
    public function predictCategoryDemand(string $categoryId): void
    {
        header('Content-Type: application/json');

        $days = $this->request->getInt('days', 30);
        
        if ($days < 1 || $days > 90) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'days must be between 1 and 90'
            ]);
            return;
        }
        
        $result = $this->aiService->predictCategoryDemand($categoryId, $days);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }
}
