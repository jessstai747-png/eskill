<?php

namespace App\Controllers;

use App\Services\OpportunityDetectorService;

class OpportunityController extends BaseController
{
    private OpportunityDetectorService $opportunityService;
    
    public function __construct()
    {
        parent::__construct();
        $this->opportunityService = new OpportunityDetectorService();
    }
    
    /**
     * Detecta produtos sem catálogo
     */
    public function productsWithoutCatalog(): void
    {
        $categoryId = $this->request->get('category');
        $brand = $this->request->get('brand');
        
        if (!$categoryId || !$brand) {
            http_response_code(400);
            echo json_encode(['error' => 'Parâmetros "category" e "brand" são obrigatórios']);
            return;
        }
        
        $result = $this->opportunityService->detectProductsWithoutCatalog($categoryId, $brand);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * Detecta categorias com pouca concorrência
     */
    public function lowCompetitionCategories(): void
    {
        $parentCategoryId = $this->request->get('parent_category');
        
        $result = $this->opportunityService->detectLowCompetitionCategories($parentCategoryId);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * Detecta produtos mais vendidos sem anúncio do usuário
     */
    public function bestSellersWithoutListing(): void
    {
        $categoryId = $this->request->get('category');
        $brand = $this->request->get('brand');
        $accountId = $this->request->getInt('account_id');
        
        if (!$categoryId || !$brand || !$accountId) {
            http_response_code(400);
            echo json_encode(['error' => 'Parâmetros "category", "brand" e "account_id" são obrigatórios']);
            return;
        }
        
        $result = $this->opportunityService->detectBestSellersWithoutUserListing($categoryId, $brand, $accountId);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
}

