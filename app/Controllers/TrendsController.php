<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\TrendsService;

/**
 * Trends Controller
 * 
 * REST API para análise de tendências de mercado
 * 
 * Endpoints:
 * - GET /api/trends/:accountId/category/:categoryId
 * - GET /api/trends/:accountId/hot-products
 * - GET /api/trends/:accountId/seasonality/:keyword
 * - GET /api/trends/:accountId/opportunities
 * - GET /api/trends/:accountId/forecast/:keyword
 */
class TrendsController
{
    private TrendsService $trendsService;
    private Request $request;

    public function __construct(int $accountId)
    {
        $this->trendsService = new TrendsService($accountId);
        $this->request = new Request();
    }

    /**
     * GET /api/trends/:accountId/category/:categoryId?limit=20
     * Obtém tendências de uma categoria
     */
    public function getCategoryTrends(string $categoryId): void
    {
        header('Content-Type: application/json');

        $limit = $this->request->getInt('limit', 20);
        $filters = ['limit' => $limit];
        
        $result = $this->trendsService->getCategoryTrends($categoryId, $filters);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * GET /api/trends/:accountId/hot-products?category_id=MLB1234&limit=50
     * Lista produtos em alta (trending)
     */
    public function getHotProducts(): void
    {
        header('Content-Type: application/json');

        $categoryId = $this->request->get('category_id');
        $limit = $this->request->getInt('limit', 50);
        $filters = [
            'category_id' => $categoryId,
            'limit' => $limit,
        ];
        
        $result = $this->trendsService->getHotProducts($filters);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * GET /api/trends/:accountId/seasonality/:keyword?months=12
     * Analisa sazonalidade de uma keyword
     */
    public function analyzeSeasonality(string $keyword): void
    {
        header('Content-Type: application/json');

        $months = $this->request->getInt('months', 12);
        $options = ['months' => $months];
        
        $result = $this->trendsService->analyzeSeasonality($keyword, $options);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * GET /api/trends/:accountId/opportunities?category_id=MLB1234&min_volume=100
     * Identifica oportunidades de mercado (alto volume + baixa concorrência)
     */
    public function findOpportunities(): void
    {
        header('Content-Type: application/json');

        $categoryId = $this->request->get('category_id');
        $minVolume = $this->request->getInt('min_volume', 100);
        $criteria = [
            'category_id' => $categoryId,
            'min_demand' => $minVolume,
        ];
        
        $result = $this->trendsService->findMarketOpportunities($criteria);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * GET /api/trends/:accountId/forecast/:keyword?days=30
     * Previsão de demanda para os próximos N dias
     */
    public function forecastDemand(string $keyword): void
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
        
        $result = $this->trendsService->forecastDemand($keyword, $days);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }
}
