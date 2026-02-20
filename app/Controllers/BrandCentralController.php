<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\BrandCentralService;

/**
 * Brand Central Controller
 * 
 * REST API para gerenciamento de Brand Central (lojas oficiais)
 * 
 * Endpoints:
 * - GET    /api/brand/:accountId/store
 * - PUT    /api/brand/:accountId/store
 * - GET    /api/brand/:accountId/products
 * - POST   /api/brand/:accountId/showcase
 * - DELETE /api/brand/:accountId/showcase/:itemId
 * - GET    /api/brand/:accountId/performance
 * - PUT    /api/brand/:accountId/sections
 */
class BrandCentralController
{
    private BrandCentralService $brandService;
    private Request $request;

    public function __construct(int $accountId)
    {
        $this->brandService = new BrandCentralService($accountId);
        $this->request = new Request();
    }

    /**
     * GET /api/brand/:accountId/store
     * Obtém informações da loja oficial
     */
    public function getStore(): void
    {
        header('Content-Type: application/json');

        $result = $this->brandService->getBrandStore();

        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * PUT /api/brand/:accountId/store
     * Atualiza customização da loja oficial
     * 
     * Body: {
     *   "brand_color": "#FF5733",
     *   "banner_url": "https://...",
     *   "logo_url": "https://...",
     *   "layout_type": "grid"
     * }
     */
    public function updateStore(): void
    {
        header('Content-Type: application/json');

        $data = $this->request->json();

        if (!is_array($data) || $data === []) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
            return;
        }

        $result = $this->brandService->updateStoreCustomization($data);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * GET /api/brand/:accountId/products?limit=50&offset=0
     * Lista produtos da loja oficial
     */
    public function getProducts(): void
    {
        header('Content-Type: application/json');

        $limit = $this->request->getInt('limit', 50);
        $offset = $this->request->getInt('offset', 0);
        $filters = [
            'limit' => $limit,
            'offset' => $offset,
        ];

        $result = $this->brandService->getBrandProducts($filters);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * POST /api/brand/:accountId/showcase
     * Adiciona produto ao showcase
     * 
     * Body: {
     *   "item_id": "MLB123",
     *   "position": 1,
     *   "featured": true
     * }
     */
    public function addToShowcase(): void
    {
        header('Content-Type: application/json');

        $data = $this->request->json();
        $itemId = is_array($data) ? (string) ($data['item_id'] ?? '') : '';

        if ($itemId === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'item_id required']);
            return;
        }

        $options = [
            'position' => is_array($data) ? ($data['position'] ?? null) : null,
            'featured' => is_array($data) ? (bool) ($data['featured'] ?? false) : false,
            'section' => is_array($data) ? ($data['section'] ?? null) : null,
        ];

        $result = $this->brandService->addToShowcase(
            $itemId,
            $options
        );

        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * DELETE /api/brand/:accountId/showcase/:itemId
     * Remove produto do showcase
     */
    public function removeFromShowcase(string $itemId): void
    {
        header('Content-Type: application/json');

        $result = $this->brandService->removeFromShowcase($itemId);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * GET /api/brand/:accountId/performance?start_date=2024-01-01&end_date=2024-12-31
     * Análise de performance da loja oficial
     */
    public function getPerformance(): void
    {
        header('Content-Type: application/json');

        $startDate = $this->request->get('start_date', date('Y-m-01')) ?? date('Y-m-01');
        $endDate = $this->request->get('end_date', date('Y-m-t')) ?? date('Y-m-t');
        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        $result = $this->brandService->analyzeBrandPerformance($filters);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * PUT /api/brand/:accountId/sections
     * Gerencia seções do showcase
     * 
     * Body: {
     *   "sections": [
     *     {"name": "Lançamentos", "item_ids": ["MLB1", "MLB2"]},
     *     {"name": "Mais Vendidos", "item_ids": ["MLB3", "MLB4"]}
     *   ]
     * }
     */
    public function manageSections(): void
    {
        header('Content-Type: application/json');

        $data = $this->request->json();

        if (!is_array($data) || !isset($data['sections']) || !is_array($data['sections'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'sections array required']);
            return;
        }

        $result = $this->brandService->manageShowcaseSections($data['sections']);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }
}
