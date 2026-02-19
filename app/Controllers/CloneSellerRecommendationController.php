<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\CloneSellerRecommendationService;
use Exception;

/**
 * Clone Seller Recommendation Controller
 * 
 * API para recomendações inteligentes de sellers
 */
class CloneSellerRecommendationController
{
    private int $accountId;
    private Request $request;

    public function __construct()
    {
        $this->request = new Request();
        $this->accountId = $_SESSION['account_id'] ?? 0;

        if (!$this->accountId) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autenticado']);
            exit;
        }
    }

    /**
     * Obtém recomendações de sellers
     * GET /api/clone/recommendations/sellers
     */
    public function getRecommendations(): void
    {
        header('Content-Type: application/json');

        try {
            $service = new CloneSellerRecommendationService($this->accountId);

            $filters = [
                'limit' => $this->request->getInt('limit', 20),
                'min_score' => $this->request->getFloat('min_score', 50.0),
            ];

            $categoryId = $this->request->get('category_id');
            if (!empty($categoryId)) {
                $filters['category_id'] = $categoryId;
            }

            $recommendations = $service->getRecommendations($filters);

            echo json_encode([
                'success' => true,
                'data' => $recommendations,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Obtém sellers similares
     * GET /api/clone/recommendations/sellers/{sellerId}/similar
     */
    public function getSimilarSellers(string $sellerId): void
    {
        header('Content-Type: application/json');

        try {
            $service = new CloneSellerRecommendationService($this->accountId);
            $limit = $this->request->getInt('limit', 10);

            $similar = $service->getSimilarSellers($sellerId, $limit);

            echo json_encode([
                'success' => true,
                'seller_id' => $sellerId,
                'similar_sellers' => $similar,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Obtém top sellers por categoria
     * GET /api/clone/recommendations/sellers/by-category
     */
    public function getTopByCategory(): void
    {
        header('Content-Type: application/json');

        try {
            $service = new CloneSellerRecommendationService($this->accountId);
            $limit = $this->request->getInt('limit', 5);

            $topSellers = $service->getTopSellersByCategory($limit);

            echo json_encode([
                'success' => true,
                'by_category' => $topSellers,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Obtém tendências de sellers
     * GET /api/clone/recommendations/trends
     */
    public function getTrends(): void
    {
        header('Content-Type: application/json');

        try {
            $service = new CloneSellerRecommendationService($this->accountId);
            $days = $this->request->getInt('days', 30);

            $trends = $service->getSellerTrends($days);

            echo json_encode([
                'success' => true,
                'days' => $days,
                'trends' => $trends,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Obtém estatísticas gerais
     * GET /api/clone/recommendations/stats
     */
    public function getStats(): void
    {
        header('Content-Type: application/json');

        try {
            $service = new CloneSellerRecommendationService($this->accountId);
            $stats = $service->getStats();

            echo json_encode([
                'success' => true,
                'stats' => $stats,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
