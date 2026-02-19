<?php

namespace App\Controllers;

use App\Services\SEO\KeywordDistributionService;
use App\Services\SEO\KeywordSourceService;
use App\Services\KeywordMinerService;

/**
 * @deprecated Functionality consolidated in SEOKillerController (KeywordKiller service).
 * API endpoints remain functional for backward compatibility.
 */
class SeoKeywordsController extends BaseController
{
    private KeywordDistributionService $distributionService;
    private KeywordSourceService $sourceService;
    private KeywordMinerService $minerService;

    public function __construct()
    {
        parent::__construct();
        // In a real application, these services would be injected by a dependency injection container.
        $this->distributionService = new KeywordDistributionService();
        $this->sourceService = new KeywordSourceService();
        $this->minerService = new KeywordMinerService();
    }

    public function distribute(): void
    {
        $item = $this->request->postArray('item');
        $categoryId = $this->request->post('categoryId', '');
        $data = $this->distributionService->distribute($item, $categoryId);
        $this->jsonResponse($data);
    }

    public function classify(): void
    {
        $keywords = $this->request->postArray('keywords');
        $categoryId = $this->request->post('categoryId', '');
        $data = $this->distributionService->classifyKeywords($keywords, $categoryId);
        $this->jsonResponse($data);
    }

    public function fetch(string $categoryId): void
    {
        $baseKeyword = $this->request->get('base_keyword', '');
        $data = $this->sourceService->getKeywords($categoryId, $baseKeyword);
        $this->jsonResponse($data);
    }

    public function generate(string $categoryId): void
    {
        $baseKeyword = $this->request->post('base_keyword') ?? $this->request->post('baseKeyword', '');
        $data = $this->sourceService->generateKeywords($categoryId, $baseKeyword);
        $this->jsonResponse($data);
    }

    public function validateDensity(): void
    {
        $text = $this->request->post('text', '');
        $keywords = $this->request->postArray('keywords');
        $data = $this->distributionService->validateDensity($text, $keywords);
        $this->jsonResponse($data);
    }

    public function calculateDensity(): void
    {
        $text = $this->request->post('text', '');
        $keyword = $this->request->post('keyword', '');
        $data = $this->distributionService->calculateDensity($text, $keyword);
        $this->jsonResponse(['density' => $data]);
    }

    public function getWeights(): void
    {
        $data = $this->distributionService->getFieldWeights();
        $this->jsonResponse($data);
    }

    public function invalidateCache(string $categoryId): void
    {
        $this->sourceService->invalidateCache($categoryId);
        $this->jsonResponse(['message' => 'Cache invalidated for category ' . $categoryId]);
    }

    /**
     * Minera keywords de uma categoria ML
     * GET /api/seo/keywords/mine/{categoryId}?term={termo}
     */
    public function mine(string $categoryId): void
    {
        $term = $this->request->get('term', '');
        $data = $this->minerService->mineKeywords($term, $categoryId);
        $this->jsonResponse([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Minera todas as categorias de motos
     * GET /api/seo/keywords/mine-moto
     */
    public function mineMoto(): void
    {
        $data = $this->minerService->mineAllMotoCategories();
        $this->jsonResponse([
            'success' => true,
            'statistics' => [
                'total_categories' => $data['total_categories'],
                'total_keywords' => $data['total_keywords'],
            ],
            'data' => $data
        ]);
    }

    /**
     * Busca keywords por atributos de uma categoria
     * GET /api/seo/keywords/attributes/{categoryId}
     */
    public function getAttributeKeywords(string $categoryId): void
    {
        $data = $this->minerService->getAttributeKeywords($categoryId);
        $this->jsonResponse([
            'success' => true,
            'count' => count($data),
            'data' => $data
        ]);
    }

    /**
     * Domain discovery para um termo
     * GET /api/seo/keywords/discover?term={termo}
     */
    public function discover(): void
    {
        $term = $this->request->get('term', '');
        if (empty($term)) {
            $this->jsonResponse(['success' => false, 'error' => 'Term is required'], 400);
            return;
        }
        
        $data = $this->minerService->getDomainDiscovery($term);
        $this->jsonResponse([
            'success' => true,
            'count' => count($data),
            'data' => $data
        ]);
    }

    /**
     * Gera sugestões de título baseadas em keywords
     * POST /api/seo/keywords/suggest-title
     */
    public function suggestTitle(): void
    {
        $productName = $this->request->post('product_name', '');
        $categoryId = $this->request->post('category_id', '');
        
        if (empty($productName) || empty($categoryId)) {
            $this->jsonResponse(['success' => false, 'error' => 'product_name and category_id are required'], 400);
            return;
        }
        
        $data = $this->minerService->generateTitleSuggestions($productName, $categoryId);
        $this->jsonResponse([
            'success' => true,
            'data' => $data
        ]);
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
    }
}